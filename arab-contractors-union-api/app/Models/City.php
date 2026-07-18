<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = ['governorate_id', 'name', 'sort'];

    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }

    public function contractors()
    {
        return $this->hasMany(Contractor::class);
    }
}
