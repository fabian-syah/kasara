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
        $transaction = StockOut::with([
            'items.product',
            'nonHpItems.product',
            'user',
            'destinationBranch',
            'destination'
        ])
            ->where('receipt_id', $receiptId)
            ->orWhere('id', $receiptId)
            ->firstOrFail();

        return view('receipts.show', compact('transaction'));
    }
}
