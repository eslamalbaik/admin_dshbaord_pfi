<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractorNameChangeRequest extends Model
{
    protected $fillable = [
        'contractor_id', 'current_name', 'requested_name', 'supporting_document',
        'status', 'reject_reason', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public const STATUS_LABELS = [
        'pending'  => 'قيد المراجعة',
        'approved' => 'مقبول',
        'rejected' => 'مرفوض',
    ];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
