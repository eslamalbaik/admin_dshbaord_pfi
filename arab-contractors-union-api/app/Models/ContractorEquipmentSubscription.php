<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractorEquipmentSubscription extends Model
{
    protected $fillable = [
        'contractor_id', 'equipment_package_id', 'payment_id', 'starts_at', 'expires_at', 'is_free_trial',
    ];

    protected $casts = [
        'starts_at'     => 'datetime',
        'expires_at'    => 'datetime',
        'is_free_trial' => 'boolean',
    ];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function package()
    {
        return $this->belongsTo(EquipmentPackage::class, 'equipment_package_id');
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>=', now());
    }
}
