<?php

namespace App\Http\Controllers;

use App\Models\StockOut;
use Illuminate\Http\Request;

class PublicReceiptController extends Controller
{
    /**
     * Display the specified receipt publicly.
     */
    public function show($receiptId)
    {
        // Sanitize input: handle spaces, URL encoding, etc.
        $sanitizedId = trim(str_replace(['%20', ' '], '-', urldecode($receiptId)));
        $rawId = trim(urldecode($receiptId));

        $transaction = StockOut::with([
            'items.product',
            'nonHpItems.product',
            'user',
            'destinationBranch',
            'destination'
        ])
            ->where('receipt_id', $sanitizedId)
            ->orWhere('receipt_id', $rawId)
            ->orWhere('id', $receiptId)
            ->orWhere('id', $sanitizedId)
            ->firstOrFail();

        return view('receipts.show', compact('transaction'));
    }

    /**
     * Proxy tracking to bypass Iframe restrictions
     */
    public function proxyTracking(Request $request)
    {
        $noResi = $request->query('nums');
        if (!$noResi) return response('Nomor resi tidak ditemukan', 400);

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get("https://cekresi.com/?noresi=" . $noResi);
            if (!$response->successful()) return response('Gagal mengambil data', 502);

            $html = $response->body();
            $html = str_replace('<head>', '<head><base href="https://cekresi.com/">', $html);
            $cleanCss = '<style>
                header, footer, nav, .navbar, .ad-section, .sidebar, .breadcrumb, .footer-section { display: none !important; }
                body { padding: 0 !important; margin: 0 !important; background: transparent !important; }
                .container { width: 100% !important; max-width: 100% !important; padding: 10px !important; }
            </style>';
            $html = str_replace('</head>', $cleanCss . '</head>', $html);

            return response($html)->header('Content-Type', 'text/html');
        } catch (\Exception $e) {
            return response('Error: ' . $e->getMessage(), 500);
        }
    }
}
