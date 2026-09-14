<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tender extends Model
{
    use SoftDeletes;

    /** سقف 5 مجالات رئيسية (REQ-12) + "عام" كتصنيف احتياطي للمجالات النادرة */
    public const CATEGORIES = ['مباني', 'طرق', 'بنية تحتية', 'قطاع صحي', 'قطاع تعليمي', 'عام'];

    /** نافذة بادج "جديد" — بالساعات؛ "ينتهي قريباً" — عدد الأيام قبل الموعد النهائي */
    private const NEW_WINDOW_HOURS  = 48;
    public const CLOSING_SOON_DAYS  = 4;

    /** القيم الأربع المعروضة للمستخدم — عمود display_status حقيقي، وليس بادج محسوب لحظياً فقط */
    public const DISPLAY_STATUSES = ['new', 'updated', 'closing_soon', 'closed'];

    public const DISPLAY_STATUS_LABELS = [
        'new'          => 'جديد',
        'updated'      => 'محدث',
        'closing_soon' => 'ينتهي قريباً',
        'closed'       => 'مغلق',
    ];

    protected $fillable = [
        'title', 'issuing_entity', 'reference_number', 'description', 'union_notes',
        'category', 'budget', 'deadline', 'published_at',
        'status', 'archived_at', 'created_by',
        'submission_types', 'submission_email', 'submission_phone', 'submission_file',
        'external_url',
    ];

    protected $casts = [
        'deadline'         => 'date',
        'published_at'     => 'datetime',
        'archived_at'      => 'datetime',
        'budget'           => 'decimal:2',
        'submission_types' => 'array',
    ];

    protected $appends = ['is_active', 'is_new', 'is_updated', 'closing_soon', 'display_status_label'];

    protected static function booted(): void
    {
        // تاريخ النشر تلقائي دائماً — لا حقل يدوي بالفورم، يُضبط لحظة الإنشاء بغض النظر عن نقطة الدخول
        static::creating(function (Tender $tender) {
            $tender->published_at ??= now();
            $tender->syncDisplayStatus(false);
        });

        static::updating(function (Tender $tender) {
            // استبعاد التحديثات الداخلية البحتة (توليد الرقم المرجعي عقب الإنشاء مباشرة) من احتساب "محدَّث"
            $isContentUpdate = (bool) array_diff(array_keys($tender->getDirty()), ['reference_number', 'display_status']);
            $tender->syncDisplayStatus($isContentUpdate);
        });
    }

    /**
     * يحدّث display_status وفق: مغلق/ملغى إدارياً > قرب الموعد النهائي > تحديث محتوى فعلي.
     * تقدّم أحادي الاتجاه لحالتي new→updated فقط؛ closed/closing_soon يعكسان الواقع دائماً (غير أحاديي الاتجاه)
     * حتى تنعكس إعادة الفتح أو تمديد الموعد النهائي بشكل صحيح.
     */
    public function syncDisplayStatus(bool $isContentUpdate = false): void
    {
        if (in_array($this->status, ['closed', 'cancelled'], true)) {
            $this->display_status = 'closed';

            return;
        }

        if ($this->deadline && $this->deadline->isFuture()
            && now()->diffInDays($this->deadline, false) <= self::CLOSING_SOON_DAYS) {
            $this->display_status = 'closing_soon';

            return;
        }

        $current = $this->display_status;

        if ($current === null) {
            $this->display_status = 'new';
        } elseif ($current === 'closed') {
            // إعادة فتح عطاء كان مغلقاً — تُعتبر تحديثاً فعلياً بغض النظر عن باقي الحقول
            $this->display_status = 'updated';
        } elseif ($isContentUpdate && $current === 'new') {
            $this->display_status = 'updated';
        }
    }

    public function getDisplayStatusLabelAttribute(): string
    {
        return self::DISPLAY_STATUS_LABELS[$this->display_status] ?? $this->display_status;
    }

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

    /**
     * يُخزَّن مسار نسبي داخل قرص public — الرابط العام يُبنى هنا وقت القراءة عبر asset()
     * بدل تجميده وقت الرفع (نفس نمط News/Event عبر HasPublicMediaUrls، بس لحقل مفرد باسم مختلف).
     */
    public function getSubmissionFileAttribute(?string $value): ?string
    {
        if (! $value)
            return null;

        return str_starts_with($value, 'http') ? $value : asset('storage/' . $value);
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

    /** بادج "ينتهي قريباً" — الموعد النهائي خلال CLOSING_SOON_DAYS أيام القادمة */
    public function getClosingSoonAttribute(): bool
    {
        return (bool) $this->deadline
            && $this->deadline->isFuture()
            && now()->diffInDays($this->deadline, false) <= self::CLOSING_SOON_DAYS;
    }
}
