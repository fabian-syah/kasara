<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchLeague extends Model
{
    protected $fillable = [
        'branch_id',
        'league',
        'month',
        'year',
        'rank',
        'notes',
        'assigned_by',
    ];

    public const LEAGUES = [
        'liga_1' => 'Liga 1',
        'liga_2' => 'Liga 2',
        'zona_merah' => 'Zona Merah',
        'non_liga' => 'Non Liga',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedByUser()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeForPeriod($query, $month, $year)
    {
        return $query->where('month', $month)->where('year', $year);
    }

    public function scopeByLeague($query, $league)
    {
        return $query->where('league', $league);
    }
}
