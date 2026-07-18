<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentBlockedDate extends Model
{
    protected $fillable = ['equipment_id', 'blocked_date', 'reason'];

    protected $casts = [
        'blocked_date' => 'date',
    ];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }
}
