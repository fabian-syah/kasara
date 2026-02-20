<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditAnswer extends Model
{
    protected $fillable = [
        'stock_out_id',
        'question_id',
        'answer',
        'auditor_id',
    ];

    protected $casts = [
        'answer' => 'boolean',
    ];

    public function stockOut()
    {
        return $this->belongsTo(StockOut::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function auditor()
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }
}
