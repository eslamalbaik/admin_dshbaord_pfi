<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentImage extends Model
{
    protected $fillable = ['equipment_id', 'path', 'is_primary', 'sort_order'];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }
}
