<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProfileUpdateRequest extends Model
{
    protected $fillable = [
        'contractor_id', 'proposed_data', 'attachment', 'phone_otp_verified_at',
        'status', 'reject_reason', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'proposed_data'         => 'array',
        'phone_otp_verified_at' => 'datetime',
        'reviewed_at'           => 'datetime',
    ];

    public const STATUS_LABELS = [
        'pending'  => 'قيد المراجعة',
        'approved' => 'مقبول',
        'rejected' => 'مرفوض',
    ];

    /**
     * الحقول القابلة للتعديل عبر طلب — whitelist صريح. الاسم/رقم العضوية/رقم المشتغل
     * مقفلة تماماً ولا تظهر هنا إطلاقاً (REQ-26).
     */
    public const ALLOWED_FIELDS = [
        'authorized_person', 'authorized_person_title', 'phone', 'email', 'address',
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

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment ? Storage::disk('public')->url($this->attachment) : null;
    }
}
