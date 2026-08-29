<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tender extends Model
{
    use SoftDeletes;

    /** سقف 5 مجالات رئيسية (REQ-12) + "عام" كتصنيف احتياطي للمجالات النادرة */
    public const CATEGORIES = ['مباني', 'طرق', 'بنية تحتية', 'قطاع صحي', 'قطاع تعليمي', 'عام'];

    /** نافذة بادج "جديد" و"ينتهي قريباً" — بالساعات/الأيام على التوالي */
    private const NEW_WINDOW_HOURS   = 48;
    private const CLOSING_SOON_DAYS  = 7;

    protected $fillable = [
        'title', 'issuing_entity', 'reference_number', 'description', 'union_notes',
        'category', 'budget', 'deadline',
        'status', 'archived_at', 'bids_count', 'created_by',
        'submission_types', 'submission_email', 'submission_phone', 'submission_file',
        'external_url',
    ];

    protected $casts = [
        'deadline'         => 'date',
        'archived_at'      => 'datetime',
        'budget'           => 'decimal:2',
        'bids_count'       => 'integer',
        'submission_types' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments()
    {
        return $this->hasMany(TenderAttachment::class);
    }

    public function bookmarks()
    {
        return $this->hasMany(TenderBookmark::class);
    }

    public function bookmarkedBy()
    {
        return $this->belongsToMany(Contractor::class, 'tender_bookmarks');
    }

    /** فعّال = مفتوح ولم يتجاوز موعد التسليم بعد (REQ-09) */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'open' && (! $this->deadline || $this->deadline->isFuture());
    }

    /** بادج "جديد" — نُشر خلال آخر 48 ساعة */
    public function getIsNewAttribute(): bool
    {
        return $this->created_at && $this->created_at->gt(now()->subHours(self::NEW_WINDOW_HOURS));
    }

    /** بادج "محدّث" — عُدّل بعد النشر وضمن نافذة 48 ساعة، وما عاد "جديد" */
    public function getIsUpdatedAttribute(): bool
    {
        if ($this->is_new || ! $this->updated_at || ! $this->created_at) {
            return false;
        }

        return $this->updated_at->gt($this->created_at->copy()->addMinute())
            && $this->updated_at->gt(now()->subHours(self::NEW_WINDOW_HOURS));
    }

    /** بادج "ينتهي قريباً" — الموعد النهائي خلال 7 أيام القادمة */
    public function getClosingSoonAttribute(): bool
    {
        return (bool) $this->deadline
            && $this->deadline->isFuture()
            && now()->diffInDays($this->deadline, false) <= self::CLOSING_SOON_DAYS;
    }
}
