<?php

namespace App\Models;

use App\Support\ProfileReview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تعديل حقل واحد من الملف التعريفي أرسله المقاول من التطبيق — راجع ProfileChangeService.
 */
class ProfileChange extends Model
{
    public const MODE_PRE  = 'pre_approval';
    public const MODE_POST = 'post_review';

    public const STATUS_PENDING    = 'pending';
    public const STATUS_APPROVED   = 'approved';
    public const STATUS_REJECTED   = 'rejected';
    public const STATUS_WITHDRAWN  = 'withdrawn';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_CONFIRMED  = 'confirmed';
    public const STATUS_REVERTED   = 'reverted';

    public const STATUS_LABELS = [
        self::STATUS_PENDING    => 'قيد المراجعة',
        self::STATUS_APPROVED   => 'معتمد',
        self::STATUS_REJECTED   => 'مرفوض',
        self::STATUS_WITHDRAWN  => 'سحبه المقاول',
        self::STATUS_SUPERSEDED => 'استُبدل بتعديل أحدث',
        self::STATUS_CONFIRMED  => 'مؤكَّد',
        self::STATUS_REVERTED   => 'تم التراجع عنه',
    ];

    protected $fillable = [
        'contractor_id', 'field', 'kind', 'mode', 'old_value', 'new_value', 'status',
        'reject_reason', 'reviewed_by', 'reviewed_at', 'dismissed_at',
    ];

    protected $casts = [
        'reviewed_at'  => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class)->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isFile(): bool
    {
        return $this->kind === 'file';
    }

    /** القيمة الرسمية تغيّرت (من لوحة التحكم مثلاً) بعد ما المقاول بعت الطلب. */
    public function isStale(?Contractor $contractor = null): bool
    {
        if ($this->status !== self::STATUS_PENDING || $this->mode !== self::MODE_PRE) {
            return false;
        }

        $contractor ??= $this->contractor;

        return ProfileReview::normalize($this->field, $contractor?->getAttribute($this->field)) !== $this->old_value;
    }

    /** شكل موحّد للتطبيق ولوحة التحكم. */
    public function present(?Contractor $contractor = null): array
    {
        return [
            'id'            => $this->id,
            'field'         => $this->field,
            'label'         => ProfileReview::label($this->field),
            'group'         => ProfileReview::group($this->field),
            'kind'          => $this->kind,
            'mode'          => $this->mode,
            'status'        => $this->status,
            'status_label'  => self::STATUS_LABELS[$this->status] ?? $this->status,
            'old_value'     => $this->isFile() ? null : $this->old_value,
            'new_value'     => $this->isFile() ? null : $this->new_value,
            'old_display'   => ProfileReview::display($this->field, $this->old_value),
            'new_display'   => ProfileReview::display($this->field, $this->new_value),
            'old_file_url'  => $this->isFile() ? ProfileReview::fileUrl($this->old_value) : null,
            'new_file_url'  => $this->isFile() ? ProfileReview::fileUrl($this->new_value) : null,
            'is_stale'      => $this->isStale($contractor),
            'reject_reason' => $this->reject_reason,
            'reviewed_by'   => $this->reviewer?->name,
            'reviewed_at'   => $this->reviewed_at?->toISOString(),
            'submitted_at'  => $this->created_at?->toISOString(),
        ];
    }
}
