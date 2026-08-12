<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentReport extends Model
{
    protected $fillable = ['equipment_id', 'contractor_id', 'reason', 'status'];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }
}
