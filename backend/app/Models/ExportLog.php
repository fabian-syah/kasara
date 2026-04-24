<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportLog extends Model
{
    protected $fillable = [
        'user_id',
        'report_name',
        'filename',
        'params',
        'downloaded_at'
    ];

    protected $casts = [
        'params' => 'array',
        'downloaded_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
