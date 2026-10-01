<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class City extends Model
{
    protected $fillable = ['governorate_id', 'name', 'sort', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }

    public function contractors()
    {
        return $this->hasMany(Contractor::class);
    }
}
