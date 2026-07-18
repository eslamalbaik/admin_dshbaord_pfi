<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'contractor_id', 'membership_id', 'amount',
        'type', 'status', 'method', 'reference_number',
        'notes', 'paid_at',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }
}
