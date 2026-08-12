<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentPackage extends Model
{
    protected $fillable = ['name', 'price', 'currency', 'duration_days', 'ads_limit', 'is_active'];

    protected $casts = [
        'price'         => 'decimal:2',
        'duration_days' => 'integer',
        'ads_limit'     => 'integer',
        'is_active'     => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
