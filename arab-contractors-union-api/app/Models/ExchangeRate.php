<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'currency', 'rate_to_jod', 'fetched_at', 'source',
    ];

    protected $casts = [
        'rate_to_jod' => 'decimal:6',
        'fetched_at'  => 'datetime',
    ];
}
