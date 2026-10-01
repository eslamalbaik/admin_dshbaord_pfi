<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractorGrade extends Model
{
    protected $fillable = ['code', 'label', 'level', 'eligible_field_codes', 'sort_order', 'is_active'];

    protected $casts = [
        'eligible_field_codes' => 'array',
        'is_active'            => 'boolean',
        'level'                => 'integer',
    ];
}
