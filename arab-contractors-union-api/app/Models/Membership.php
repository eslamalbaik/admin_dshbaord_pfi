<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    protected $fillable = [
        'contractor_id', 'type', 'status',
        'starts_at', 'expires_at', 'amount',
        'document_url', 'notes', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'starts_at'   => 'date',
        'expires_at'  => 'date',
        'reviewed_at' => 'datetime',
        'amount'      => 'decimal:2',
    ];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // هل العضوية تنتهي خلال 30 يوم؟
    public function getExpiringSoonAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isFuture()
            && $this->expires_at->diffInDays(now(), absolute: true) <= 30;
    }
}
