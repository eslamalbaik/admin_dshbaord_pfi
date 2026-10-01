<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeFee extends Model
{
    protected $fillable = [
        'grade_code', 'grade_label', 'sort_order', 'registration_fee_jod', 'annual_fee_jod',
    ];

    protected $casts = [
        'sort_order'           => 'integer',
        'registration_fee_jod' => 'decimal:2',
        'annual_fee_jod'       => 'decimal:2',
    ];

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
