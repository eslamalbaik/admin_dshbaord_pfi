<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penalty extends Model
{
    protected $fillable = [
        'contractor_id', 'reason', 'amount', 'status', 'notes', 'paid_at',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }
}
