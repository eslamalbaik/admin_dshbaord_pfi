<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        'total_revenue',
        'active_contractors_count',
        'pending_memberships_count',
        'published_tenders_count',
        'active_equipment_count',
        'total_tenders_value',
        'total_penalties_value',
    ];
}
