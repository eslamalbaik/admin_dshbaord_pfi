<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Governorate extends Model
{
    protected $fillable = ['name', 'sort', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    public function cities()
    {
        return $this->hasMany(City::class)->orderBy('sort')->orderBy('id');
    }

    public function activeCities()
    {
        return $this->hasMany(City::class)->where('is_active', true)->orderBy('sort')->orderBy('id');
    }

    public function contractors()
    {
        return $this->hasMany(Contractor::class);
    }
}
