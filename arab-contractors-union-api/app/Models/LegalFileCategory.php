<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalFileCategory extends Model
{
    protected $fillable = ['key', 'label', 'label_en', 'sort', 'is_system'];

    protected $casts = [
        'is_system' => 'boolean',
        'sort'      => 'integer',
    ];
}
