<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SupportTicket extends Model
{
    protected $fillable = [
        'contractor_id', 'subject', 'category', 'message', 'attachment',
        'status', 'reply', 'replied_by', 'replied_at',
    ];

    protected $casts = [
        'replied_at' => 'datetime',
    ];

    protected $appends = ['attachment_url', 'category_label', 'status_label'];

    /** مسميات التصنيفات */
    public const CATEGORY_LABELS = [
        'technical'  => 'دعم فني',
        'complaint'  => 'شكوى',
        'inquiry'    => 'استفسار',
        'suggestion' => 'اقتراح',
        'other'      => 'أخرى',
    ];

    /** مسميات الحالات */
    public const STATUS_LABELS = [
        'open'        => 'مفتوحة',
        'in_progress' => 'قيد المعالجة',
        'answered'    => 'تم الرد',
        'closed'      => 'مغلقة',
    ];

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment ? Storage::disk('public')->url($this->attachment) : null;
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? $this->category;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function repliedBy()
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
