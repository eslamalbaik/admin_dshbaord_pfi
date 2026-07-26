<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Payment extends Model
{
    protected $fillable = [
        'contractor_id', 'membership_id', 'bank_account_id', 'amount',
        'currency', 'exchange_rate', 'amount_jod', 'used_amount_jod', 'rate_source',
        'type', 'status', 'method', 'reference_number', 'receipt_image',
        'notes', 'paid_at', 'submitted_at', 'confirmed_by', 'confirmed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'amount_jod'    => 'decimal:2',
        'paid_at'       => 'datetime',
        'submitted_at'  => 'datetime',
        'confirmed_at'  => 'datetime',
    ];

    protected $appends = ['receipt_image_url'];

    /** رابط صورة إشعار التحويل الكامل */
    public function getReceiptImageUrlAttribute(): ?string
    {
        return $this->receipt_image ? Storage::disk('public')->url($this->receipt_image) : null;
    }

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
