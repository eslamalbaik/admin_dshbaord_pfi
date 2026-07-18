<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BankAccount extends Model
{
    protected $fillable = [
        'bank_name', 'bank_name_en', 'logo_path', 'iban',
        'account_number', 'account_holder', 'swift', 'notes',
        'is_active', 'sort',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort'      => 'integer',
    ];

    protected $appends = ['logo_url'];

    /** رابط شعار البنك الكامل */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
