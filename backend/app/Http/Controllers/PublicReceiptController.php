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
}
