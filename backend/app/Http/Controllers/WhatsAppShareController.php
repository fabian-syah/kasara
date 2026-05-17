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
    public function share($id)
    {
        set_time_limit(150); // Increase execution time for this specific long-running task
        try {
            // 1. Ambil Data Transaksi
            $transaction = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'destinationBranch', 'paymentMethod'])->findOrFail($id);

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
            $driveLink = self::getDriveLink($id);

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
            $pesan = "Halo Kak *{$customerName}* 👋😊\n\n";
            $pesan .= "Terima kasih banyak ya Kak sudah berbelanja di *PSTORE*! Kami sangat senang bisa melayani Kakak. Semoga produknya awet, berkah, dan bermanfaat yaa ✨\n\n";
            $pesan .= "Berikut adalah link resmi Google Drive untuk mengunduh Nota Pembelian (PDF) Kakak:\n";
            $pesan .= "👉 {$driveLink}\n\n";
            $pesan .= "*Penting:* Jangan lupa untuk menyimpan (save) nomor WhatsApp toko kami ini ya Kak, agar link di atas bisa langsung diklik dengan mudah dari HP Kakak, dan juga untuk mempermudah klaim garansi atau promo menarik kami ke depannya 😉👍\n\n";
            $pesan .= "Sehat dan sukses selalu untuk Kakak sekeluarga! Terima kasih! ❤️";

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

    /**
     * Heavy lifting for PDF Generation & Upload
     * Can be called synchronously or from a background job
     */
    public static function getDriveLink($id)
    {
        $cacheKey = "receipt_drive_link_{$id}";
        
        // 0. Cek Cache
        if ($cachedLink = Cache::get($cacheKey)) {
            return $cachedLink;
        }

        try {
            $transaction = StockOut::with([
                'items.product',
                'nonHpItems.product',
                'user.branch.receiptSetting',
                'user.onlineShop.receiptSetting',
                'branch.receiptSetting',
                'onlineShop.receiptSetting',
                'destinationBranch.receiptSetting',
                'paymentMethod'
            ])->findOrFail($id);
            
            // Resolve receipt settings exactly like ReceiptModal.vue
            $targetLocation = $transaction->branch 
                ?? ($transaction->onlineShop 
                ?? ($transaction->destinationBranch 
                ?? ($transaction->user->branch ?? ($transaction->user->onlineShop ?? null))));
                
            $receiptSetting = $targetLocation ? $targetLocation->receiptSetting : null;
            
            // 1. Get Cached Logos
            $logos = self::getBase64Images();

            // 2. Hitung Total & Diskon langsung dari database (seperti ReceiptModal.vue)
            $total_discount = abs($transaction->total_discount ?? 0);
            $total_original = $transaction->original_price 
                ?: (abs($transaction->selling_price) + $total_discount);

            // 3. Process split payments
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

            // 4. Render HTML
            $htmlContent = view('receipts.show_thermal', [
                'transaction' => $transaction,
                'total_original' => $total_original,
                'total_discount' => $total_discount,
                'logoBase64' => $logos['logo'] ?? '',
                'shopeeBase64' => $logos['shopee'] ?? '',
                'tokopediaBase64' => $logos['tokopedia'] ?? '',
                'split_payments_data' => $processedSplitPayments,
                'receiptSetting' => $receiptSetting,
            ])->render();

            // 5. Kirim ke GDrive Bridge
            $scriptUrl = 'https://script.google.com/macros/s/AKfycbwZIhLxZK_AhiC5k1JPctPfjOa2zPLUO8vcYfwSbyVt2nKF3dVOlRptkF07M0xdDBbY/exec';
            $branchName = $transaction->destinationBranch->name ?? ($transaction->user->branch->name ?? 'Pusat');
            $folderPath = date('Y') . '/' . date('m') . '/' . Str::slug($branchName);
            $customerNameClean = $transaction->customer_name ? Str::slug($transaction->customer_name, '_') : 'Pelanggan';
            $filename = "Nota_{$customerNameClean}_{$transaction->receipt_id}.pdf";

            $response = Http::timeout(120)->post($scriptUrl, [
                'htmlContent' => $htmlContent,
                'filename' => $filename,
                'folderPath' => $folderPath
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $driveLink = $result['url'] ?? null;
                
                if ($driveLink) {
                    // Simpan di cache selama 24 jam
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
        return Cache::rememberForever('receipt_logos_base64_v2', function() {
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
