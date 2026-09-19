<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penalty extends Model
{
    protected $fillable = [
        'contractor_id', 'reason', 'amount', 'paid_amount', 'status',
        'notes', 'reject_reason', 'paid_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'paid_at'     => 'datetime',
    ];

    /** الحالات الأربع المعتمَدة (REQ-06 #6) — مصدر واحد للتسميات المعروضة بلوحة الأدمن. */
    public const STATUS_LABELS = [
        'unpaid'         => 'غير مسدَّدة',
        'paid'           => 'مسدَّدة',
        'partially_paid' => 'مسدَّدة جزئياً',
        'rejected'       => 'مرفوضة',
    ];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
