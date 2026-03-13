<?php

namespace App\Http\Controllers;

use App\Models\StockOut;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppShareController extends Controller
{
    public function share($id)
    {
        try {
            // 1. Ambil Data Transaksi
            $transaction = StockOut::with(['items.product', 'nonHpItems.product', 'user', 'destinationBranch'])->findOrFail($id);

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

            // 2.5 Encode Base64 Images for PDF
            $logoBase64 = '';
            $shopeeBase64 = '';
            $tokopediaBase64 = '';

            try {
                $logoPath = public_path('images/logo-pstore.png');
                if (file_exists($logoPath)) {
                    $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
                }

                $shopeePath = public_path('images/shopee-icon-small.png');
                if (file_exists($shopeePath)) {
                    $shopeeBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($shopeePath));
                }

                $tokopediaPath = public_path('images/tokopedia-icon-small.png');
                if (file_exists($tokopediaPath)) {
                    $tokopediaBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($tokopediaPath));
                }
            } catch (\Exception $e) {
                Log::warning('Base64 Image Encoding Failed: ' . $e->getMessage());
            }

            // 3. Hitung Total & Diskon (Untuk View)
            $total_original = 0;
            foreach ($transaction->items as $item) {
                $netPrice = ($item->pivot->selling_price ?? 0) - ($item->pivot->item_discount ?? 0) - ($item->pivot->distributed_discount ?? 0);
                $total_original += $netPrice;
            }
            foreach ($transaction->nonHpItems as $item) {
                $netPrice = ($item->selling_price ?? 0) - ($item->item_discount ?? 0);
                $total_original += ($item->quantity ?? 1) * $netPrice;
            }

            $total_discount = 0;
            if ($transaction->global_discount_value > 0) {
                if ($transaction->global_discount_type === 'percentage') {
                    $total_discount = ($total_original * $transaction->global_discount_value) / 100;
                } else {
                    $total_discount = $transaction->global_discount_value;
                }
            }

            // 4. Generate HTML dari View (Thermal-Style)
            $htmlContent = view('receipts.show_thermal', [
                'transaction' => $transaction,
                'total_original' => $total_original,
                'total_discount' => $total_discount,
                'logoBase64' => $logoBase64,
                'shopeeBase64' => $shopeeBase64,
                'tokopediaBase64' => $tokopediaBase64,
            ])->render();

            // 5. Kirim ke GDrive Bridge (Apps Script baru yang bisa ubah HTML -> PDF)
            $scriptUrl = 'https://script.google.com/macros/s/AKfycbwZIhLxZK_AhiC5k1JPctPfjOa2zPLUO8vcYfwSbyVt2nKF3dVOlRptkF07M0xdDBbY/exec';

            // Logika Folder: Tahun / Bulan / Nama_Cabang
            $branchName = $transaction->destinationBranch->name ?? ($transaction->user->branch->name ?? 'Pusat');
            $folderPath = date('Y') . '/' . date('m') . '/' . Str::slug($branchName);
            $filename = "Nota-{$transaction->receipt_id}.pdf";

            Log::info("GDrive: Uploading PDF for {$transaction->receipt_id} to {$folderPath}");

            $response = Http::timeout(60)->post($scriptUrl, [
                'htmlContent' => $htmlContent,
                'filename' => $filename,
                'folderPath' => $folderPath
            ]);

            if (!$response->successful()) {
                Log::error('GDrive Error: ' . $response->body());
                throw new \Exception('Gagal upload ke Google Drive: ' . $response->status());
            }

            $result = $response->json();
            $driveLink = $result['url'] ?? null;

            if (!$driveLink) {
                throw new \Exception('Link Google Drive tidak didapatkan dari server');
            }

            // 6. Susun Pesan WA
            $customerName = $transaction->customer_name ?: 'Pelanggan';
            $pesan = "Halo Kak *{$customerName}*,\n";
            $pesan .= "Terima kasih telah berbelanja di *PSTORE*.\n\n";
            $pesan .= "Berikut Link Nota Resmi transaksi Anda:\n";
            $pesan .= "{$driveLink}\n\n";
            $pesan .= "Total: Rp " . number_format($transaction->selling_price, 0, ',', '.') . "\n";
            $pesan .= "Nota ini aman dan valid. Terima kasih!";

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
}
