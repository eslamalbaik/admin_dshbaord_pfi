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
    ];

    /** بادج "جديدة" — نُشرت خلال آخر 72 ساعة */
    public function getIsNewAttribute(): bool
    {
        return $this->created_at && $this->created_at->gt(now()->subHours(self::NEW_WINDOW_HOURS));
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
     * أو تغيير is_hidden (يبقى ظاهر لصاحبه بشاشة "آلياتي" فقط).
     */
    public function scopeVisibleInMarketplace($query)
    {
        $freeUntil          = Setting::get('equipment_marketplace_free_until');
        $hasGlobalFreeTrial = ! $freeUntil || now()->lte(\Carbon\Carbon::parse($freeUntil));

        return $query->where('is_hidden', false)
            ->where('status', 'visible')
            ->where('needs_maintenance', false)
            ->when(! $hasGlobalFreeTrial, function ($q) {
                $q->whereHas('contractor.activeEquipmentSubscription');
            });
    }
}
