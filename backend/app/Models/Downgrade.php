<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Downgrade extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'receipt_id',
        'customer_name',
        'customer_phone',
        'incoming_source',
        'incoming_product_type_id',
        'incoming_imei',
        'incoming_storage',
        'incoming_condition',
        'distributor_id',
        'incoming_cost_price',
        'outgoing_product_detail_id',
        'outgoing_price',
        'price_difference',
        'payment_method_id',
        'split_payments',
        'reason',
        'notes',
        'photo_unit',
        'photo_customer',
        'user_id',
        'inventory_user_id',
        'branch_id',
    ];

    protected $casts = [
        'split_payments' => 'array',
    ];

    public function incomingProductType()
    {
        return $this->belongsTo(ProductType::class, 'incoming_product_type_id');
    }

    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }

    public function outgoingProductDetail()
    {
        return $this->belongsTo(ProductDetail::class, 'outgoing_product_detail_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public static function generateReceiptId()
    {
        $prefix = 'DG' . date('dMy'); // DG19Mar26

        // Query all existing receipt IDs with today's prefix from Downgrade (including trashed)
        $existingModelIds = self::withTrashed()
            ->where('receipt_id', 'like', $prefix . '-%')
            ->pluck('receipt_id');

        // Query from StockOut as well
        $existingStockOutIds = \App\Models\StockOut::withTrashed()
            ->where('receipt_id', 'like', $prefix . '-%')
            ->pluck('receipt_id');

        $allExisting = $existingModelIds->concat($existingStockOutIds);

        $maxNumber = 0;
        foreach ($allExisting as $rid) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)/', (string) $rid, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        $nextNumber = $maxNumber + 1;

        // Loop until candidate is guaranteed unique in both Downgrade and StockOut
        do {
            $candidate = $prefix . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            $existsInModel = self::withTrashed()
                ->where(function ($q) use ($candidate) {
                    $q->where('receipt_id', $candidate)
                      ->orWhere('receipt_id', 'like', $candidate . '-%');
                })
                ->exists();

            $existsInStockOut = \App\Models\StockOut::withTrashed()
                ->where(function ($q) use ($candidate) {
                    $q->where('receipt_id', $candidate)
                      ->orWhere('receipt_id', 'like', $candidate . '-%');
                })
                ->exists();

            if ($existsInModel || $existsInStockOut) {
                $nextNumber++;
            } else {
                break;
            }
        } while (true);

        return $candidate;
    }
}
