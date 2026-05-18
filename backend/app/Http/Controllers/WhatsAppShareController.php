<?php

namespace App\Http\Controllers;

use App\Models\StockOut;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class WhatsAppShareController extends Controller
{
    public function share(Request $request, $id)
    {
        set_time_limit(150); // Increase execution time for this specific long-running task
        try {
            // 1. Ambil Data Transaksi
            $transaction = StockOut::with(['items.product', 'nonHpItems.product', 'user.branch', 'branch', 'destinationBranch', 'paymentMethod'])->findOrFail($id);

            // 2. Format Nomor WA
            $phone = $transaction->customer_phone ?: $transaction->customer_wa ?: $transaction->shopee_phone;
            if (!$phone || $phone === '-') {
                return response()->json(['success' => false, 'error' => 'Nomor WhatsApp tidak ditemukan'], 400);
            }

            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            if (str_starts_with($cleanPhone, '0')) {
                $cleanPhone = '62' . substr($cleanPhone, 1);
            } elseif (!str_starts_with($cleanPhone, '62')) {
                $cleanPhone = '62' . $cleanPhone;
            }

            // 3. Ambil/Generate Link Google Drive (Bisa dari Cache hasil Pre-generation)
            $htmlContent = $request->input('htmlContent');
            $driveLink = self::getDriveLink($id, $htmlContent);

            if (!$driveLink) {
                throw new \Exception('Gagal mendapatkan Link Google Drive. Silakan coba lagi.');
            }

            // Convert to a Google Drive web preview link so it opens a preview in-browser rather than downloading directly
            if ($driveLink && str_contains($driveLink, 'drive.google.com')) {
                $fileId = null;
                if (preg_match('/id=([a-zA-Z0-9_-]+)/', $driveLink, $matches)) {
                    $fileId = $matches[1];
                } elseif (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $driveLink, $matches)) {
                    $fileId = $matches[1];
                }
                if ($fileId) {
                    $driveLink = "https://drive.google.com/file/d/{$fileId}/view?usp=drivesdk";
                }
            }

            // 6. Susun Pesan WA
            $customerName = $transaction->customer_name ?: 'Pelanggan';
            $branchName = $transaction->branch->name 
                ?? ($transaction->destinationBranch->name 
                ?? ($transaction->user->branch->name ?? ''));
            $displayBranch = $branchName ? "PSTORE {$branchName}" : "PSTORE";

            $pesan = "Halo Kak *{$customerName}*  \u{1F44B}\u{1F3FB}\n\n";
            $pesan .= "Terima kasih banyak ya Kak sudah berbelanja di *{$displayBranch}*!\n\n";
            $pesan .= "Kami sangat senang bisa melayani Kakak. Semoga produknya awet, berkah, dan bermanfaat yaa \u{1F932}\u{1F3FB}\n\n";
            $pesan .= "Berikut adalah link resmi Google Drive untuk mengunduh Nota Pembelian (PDF) Kakak:\n";
            $pesan .= "\u{1F4CC} {$driveLink}\n\n";
            $pesan .= "*Penting:* \n";
            $pesan .= "Jangan lupa untuk menyimpan (save) nomor WhatsApp toko kami ini ya Kak, untuk mempermudah klaim garansi atau untuk mendapatkan promo menarik kami ke depannya \u{1FAF6}\u{1F3FB}\n\n";
            $pesan .= "Sehat dan sukses selalu untuk Kakak sekeluarga! Terima kasih! \u{1F64F}\u{1F3FB}";

            $waUrl = "https://wa.me/{$cleanPhone}?text=" . urlencode($pesan);

            return response()->json([
                'success' => true,
                'wa_url' => $waUrl,
                'drive_link' => $driveLink
            ]);

        } catch (\Exception $e) {
            Log::error('WhatsApp Share Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public static function getDriveLink($id, $htmlContent = null)
    {
        $cacheKey = "receipt_drive_link_{$id}";
        
        // Jika ada htmlContent kiriman baru dari modal web, hapus cache lama 
        // agar PDF di Google Drive selalu ter-update dengan data modal terbaru
        if ($htmlContent) {
            Cache::forget($cacheKey);
        } else {
            // Jika dipanggil otomatis tanpa payload HTML (misal cron / background job), gunakan cache jika ada
            if ($cachedLink = Cache::get($cacheKey)) {
                return $cachedLink;
            }
        }

        try {
            $transaction = StockOut::findOrFail($id);
            
            // JIKA JALUR BACKEND CADANGAN (htmlContent kosong), baru panggil template lama
            if (!$htmlContent) {
                $transaction->load([
                    'items.product', 'nonHpItems.product', 'user.branch.receiptSetting',
                    'user.onlineShop.receiptSetting', 'branch.receiptSetting',
                    'onlineShop.receiptSetting', 'destinationBranch.receiptSetting', 'paymentMethod'
                ]);

                $targetLocation = $transaction->branch 
                    ?? ($transaction->onlineShop 
                    ?? ($transaction->destinationBranch 
                    ?? ($transaction->user->branch ?? ($transaction->user->onlineShop ?? null))));
                    
                $receiptSetting = $targetLocation ? $targetLocation->receiptSetting : null;
                $logos = self::getBase64Images();

                $total_discount = abs($transaction->total_discount ?? 0);
                $total_original = $transaction->original_price ?: (abs($transaction->selling_price) + $total_discount);

                $processedSplitPayments = [];
                if ($transaction->split_payments && count($transaction->split_payments) > 0) {
                    $methodIds = array_column($transaction->split_payments, 'payment_method_id');
                    $methodNames = \App\Models\PaymentMethod::whereIn('id', $methodIds)->pluck('name', 'id');
                    foreach ($transaction->split_payments as $sp) {
                        $processedSplitPayments[] = [
                            'method_name' => $methodNames[$sp['payment_method_id']] ?? 'Unknown',
                            'amount' => $sp['amount'] ?? 0
                        ];
                    }
                }

                $paymentMethodNames = [];
                if ($transaction->payment_method_id) {
                    $pm = \App\Models\PaymentMethod::find($transaction->payment_method_id);
                    if ($pm) $paymentMethodNames[] = $pm->name;
                }
                $paymentMethodNameFormatted = implode(', ', array_unique($paymentMethodNames)) ?: '-';

                $htmlContent = view('receipts.show_thermal', [
                    'transaction' => $transaction,
                    'total_original' => $total_original,
                    'total_discount' => $total_discount,
                    'logoBase64' => $logos['logo'] ?? '',
                    'shopeeBase64' => $logos['shopee'] ?? '',
                    'tokopediaBase64' => $logos['tokopedia'] ?? '',
                    'split_payments_data' => $processedSplitPayments,
                    'receiptSetting' => $receiptSetting,
                    'payment_method_name' => $paymentMethodNameFormatted,
                ])->render();
            }

            // Ambil data folder & nama file berdasarkan transaksi saat ini
            $scriptUrl = 'https://script.google.com/macros/s/AKfycbwZIhLxZK_AhiC5k1JPctPfjOa2zPLUO8vcYfwSbyVt2nKF3dVOlRptkF07M0xdDBbY/exec';
            $branchName = $transaction->destinationBranch->name ?? ($transaction->user->branch->name ?? 'Pusat');
            $folderPath = date('Y') . '/' . date('m') . '/' . Str::slug($branchName);
            $customerNameClean = $transaction->customer_name ? Str::slug($transaction->customer_name, '_') : 'Pelanggan';
            $filename = "Nota_{$customerNameClean}_{$transaction->receipt_id}.pdf";

            // Kirim raw HTML langsung ke Google API Macro tanpa render ulang di PHP
            $response = Http::timeout(120)->post($scriptUrl, [
                'htmlContent' => $htmlContent,
                'filename' => $filename,
                'folderPath' => $folderPath
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $driveLink = $result['url'] ?? null;
                
                if ($driveLink) {
                    Cache::put($cacheKey, $driveLink, now()->addHours(24));
                    return $driveLink;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("GDrive Generation Failed for ID {$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cache logo base64 to speed up generation
     */
    private static function getBase64Images()
    {
        return Cache::rememberForever('receipt_logos_base64_v5', function() {
            $images = [
                'logo' => 'ps.png', 
                'shopee' => 'shopee-icon-small.png', 
                'tokopedia' => 'tokopedia-icon-small.png'
            ];
            $res = [];
            foreach ($images as $key => $file) {
                $path = public_path("images/{$file}");
                if (file_exists($path)) {
                    $res[$key] = 'data:image/png;base64,' . base64_encode(file_get_contents($path));
                } else {
                    $res[$key] = '';
                }
            }
            return $res;
        });
    }
}
