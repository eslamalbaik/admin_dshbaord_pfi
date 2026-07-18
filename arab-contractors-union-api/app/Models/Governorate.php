<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Governorate extends Model
{
    protected $fillable = ['name', 'sort'];

    public function cities()
    {
        return $this->hasMany(City::class)->orderBy('sort')->orderBy('id');
    }

    public function contractors()
    {
        return $this->hasMany(Contractor::class);
    }
}
