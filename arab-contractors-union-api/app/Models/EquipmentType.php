<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentType extends Model
{
    protected $fillable = ['name_ar', 'name_en', 'icon', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function equipment()
    {
        return $this->hasMany(Equipment::class);
    }
}
