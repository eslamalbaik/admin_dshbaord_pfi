<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractorSpecialization extends Model
{
    protected $fillable = ['code', 'contractor_field_id', 'name', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function field()
    {
        return $this->belongsTo(ContractorField::class, 'contractor_field_id');
    }
}
