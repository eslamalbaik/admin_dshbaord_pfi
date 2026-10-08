<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use SoftDeletes;

    protected $table = 'equipment';

    private const NEW_WINDOW_HOURS = 72;

    protected $fillable = [
        'contractor_id',
        'equipment_type_id',
        'name',
        'brand',
        'description',
        'manufacture_year',
        'power',
        'condition',
        'contract_type',
        'governorate',
        'city',
        'owner_phone',
        'status',
        'published_at',
        'is_featured',
        'needs_maintenance',
        'admin_notes',
        'is_hidden',
    ];

    protected $casts = [
        'manufacture_year' => 'integer',
        'is_hidden'        => 'boolean',
        'is_featured'      => 'boolean',
        'needs_maintenance' => 'boolean',
        'published_at'     => 'datetime',
    ];

    /** النص المعروض بدل نوع آلية ملغى (معطَّل أو محذوف) — REQ-08 #21. */
    public const UNDEFINED_TYPE_LABEL = 'غير محدد';

    /** بادج "جديدة" — نُشرت خلال آخر 72 ساعة (للمجدولة: من موعد نشرها، مش من تاريخ إنشائها) */
    public function getIsNewAttribute(): bool
    {
        $publishedAt = $this->published_at ?? $this->created_at;

        return $publishedAt && $publishedAt->lte(now()) && $publishedAt->gt(now()->subHours(self::NEW_WINDOW_HOURS));
    }

    /** مجدولة = إلها موعد نشر لسا ما إجا، فما بتظهر بالسوق قبله */
    public function getIsScheduledAttribute(): bool
    {
        return (bool) $this->published_at?->isFuture();
    }

    /**
     * اسم النوع كما يُعرض بالمعاينة: لو النوع انلغى (تعطّل أو انحذف) بيرجع "غير محدد" بدل
     * اسم نوع مش موجود بالخيارات أو فراغ. يحتاج is_active محمَّل مع العلاقة.
     */
    public function typeLabel(): string
    {
        return $this->type && $this->type->is_active
            ? $this->type->name_ar
            : self::UNDEFINED_TYPE_LABEL;
    }

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function type()
    {
        return $this->belongsTo(EquipmentType::class, 'equipment_type_id');
    }

    public function images()
    {
        return $this->hasMany(EquipmentImage::class)->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(EquipmentImage::class)->where('is_primary', true);
    }

    public function blockedDates()
    {
        return $this->hasMany(EquipmentBlockedDate::class);
    }

    public function reservations()
    {
        return $this->hasMany(EquipmentReservation::class);
    }

    public function reports()
    {
        return $this->hasMany(EquipmentReport::class);
    }

    /**
     * فعّالة بالسوق العام = غير مخفية يدوياً + ظاهرة + بلا صيانة + صاحبها عنده وصول ساري
     * لسوق الآليات (تجربة مجانية عامة أو اشتراك مدفوع). بعد انتهاء التجربة المجانية،
     * آليات المقاولين اللي ما جدّدوا اشتراكهم تختفي من السوق تلقائياً بدون حذفها
     * أو تغيير is_hidden (يبقى ظاهر لصاحبه بشاشة "آلياتي" فقط). الآلية المجدولة
     * (published_at بالمستقبل) ما بتظهر قبل موعدها.
     */
    public function scopeVisibleInMarketplace($query)
    {
        $freeUntil          = Setting::get('equipment_marketplace_free_until');
        $hasGlobalFreeTrial = ! $freeUntil || now()->lte(\Carbon\Carbon::parse($freeUntil));

        return $query->where('is_hidden', false)
            ->where('status', 'visible')
            ->where('needs_maintenance', false)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->when(! $hasGlobalFreeTrial, function ($q) {
                $q->whereHas('contractor.activeEquipmentSubscription');
            });
    }
}
