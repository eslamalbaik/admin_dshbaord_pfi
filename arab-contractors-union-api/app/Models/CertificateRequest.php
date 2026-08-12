<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CertificateRequest extends Model
{
    protected $fillable = [
        'contractor_id', 'type', 'status', 'notes', 'attachment',
        'reject_reason', 'certificate_path', 'issued_at', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'issued_at'   => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment
            ? Storage::disk('public')->url($this->attachment)
            : null;
    }

    public function getCertificateUrlAttribute(): ?string
    {
        return $this->certificate_path
            ? Storage::disk('public')->url($this->certificate_path)
            : null;
    }

    public function getTypeLabelAttribute(): string
    {
        return [
            'membership'     => 'شهادة العضوية',
            'good_standing'  => 'شهادة حسن السير والسلوك',
            'classification' => 'شهادة التصنيف',
            'experience'     => 'شهادة الخبرة والمشاريع',
        ][$this->type] ?? $this->type;
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            'pending'  => 'قيد المراجعة',
            'approved' => 'موافق عليه',
            'issued'   => 'تم الإصدار',
            'rejected' => 'مرفوض',
        ][$this->status] ?? $this->status;
    }
}
