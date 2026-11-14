<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractorField extends Model
{
    protected $fillable = ['code', 'name', 'name_en', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function specializations()
    {
        return $this->hasMany(ContractorSpecialization::class);
    }
}
