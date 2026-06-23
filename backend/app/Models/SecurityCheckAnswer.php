<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityCheckAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'security_check_id',
        'question_id',
        'answer',
    ];

    public function securityCheck()
    {
        return $this->belongsTo(SecurityCheck::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
