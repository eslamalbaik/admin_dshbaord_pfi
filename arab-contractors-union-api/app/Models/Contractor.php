<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Contractor extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * مسميات التصنيف الرسمية (REQ-07) — الحرف المخزّن في قاعدة البيانات
     * يقابله المسمى الرسمي المعتمد في شبكة المدار.
     */
    public const CLASSIFICATION_LABELS = [
        'أ'  => 'درجة أولى',
        'ب'  => 'درجة ثانية',
        'ج'  => 'درجة ثالثة',
        'د'  => 'درجة رابعة',
        'هـ' => 'درجة خامسة',
    ];

    protected $fillable = [
        'membership_number', 'name', 'authorized_person', 'commercial_register',
        'trade', 'classification', 'established_year', 'owner_name',
        'email', 'phone', 'phone_verified_at', 'city', 'governorate_id', 'city_id', 'address',
        'status', 'is_frozen', 'profile_completed', 'profile_approved_by',
        'cr_file', 'id_file', 'notes',
        'password', 'remember_token', 'last_login_at', 'fcm_token',
        // New Extended Fields
        'partners', 'fax', 'building', 'floor', 'capital', 'registration_date',
        'legal_form', 'company_purposes',
        // New Files
        'authorized_signature', 'lease_or_ownership_contract', 'company_approval_letter',
        'municipal_license', 'company_register', 'articles_of_association',
        'internal_bylaws', 'bank_dealing_letter', 'secretary_contract',
        'full_time_engineer_certificate', 'partners_ids', 'authorization_letter',
        // Auto-fields and specialized types
        'field_lk_type', 'specialization_lk_type', 'established_date', 'specialties',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'established_year'       => 'integer',
        'field_lk_type'          => 'integer',
        'specialization_lk_type' => 'integer',
        'established_date'       => 'date',
        'specialties'            => 'array',
        'last_login_at'          => 'datetime',
        'phone_verified_at'      => 'datetime',
        'is_frozen'              => 'boolean',
        'profile_completed'      => 'boolean',
    ];

    // ─── Relations ───────────────────────────────────────────────────────────

    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }

    /**
     * علاقة المدينة — الاسم cityModel لأن العمود النصي القديم `city` ما زال موجوداً
     * (يُحدَّث تلقائياً باسم المدينة للتوافق مع الداشبورد وتصدير الـ PDF).
     */
    public function cityModel()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function activeMembership()
    {
        return $this->hasOne(Membership::class)
            ->where('status', 'active')
            ->latestOfMany();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function penalties()
    {
        return $this->hasMany(Penalty::class);
    }

    public function certificateRequests()
    {
        return $this->hasMany(CertificateRequest::class);
    }

    public function dues()
    {
        return $this->hasMany(ContractorDue::class);
    }

    /** إجمالي الذمم المتبقية بالدينار الأردني */
    public function outstandingDuesTotal(): float
    {
        return round((float) $this->dues()
            ->outstanding()
            ->selectRaw('COALESCE(SUM(amount_jod - paid_jod), 0) as total')
            ->value('total'), 2);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function equipment()
    {
        return $this->hasMany(Equipment::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * المسمى الرسمي للتصنيف (درجة أولى، درجة ثانية...) — يقبل الحرف أو المسمى نفسه.
     */
    public function getClassificationLabelAttribute(): ?string
    {
        if (! $this->classification) {
            return null;
        }

        return self::CLASSIFICATION_LABELS[$this->classification] ?? $this->classification;
    }

    /**
     * توليد رقم العضوية التالي بالهيكلية الجديدة (928_g فما فوق).
     * مصدر واحد يستخدمه الأدمن وأمر إنشاء اليوزر الاختباري.
     */
    public static function nextMembershipNumber(): string
    {
        $suffix = \App\Rules\MembershipNumber::NEW_SUFFIX;
        $maxNum = \App\Rules\MembershipNumber::OLD_MAX;

        self::withTrashed()
            ->where('membership_number', 'like', '%' . $suffix)
            ->pluck('membership_number')
            ->each(function ($number) use (&$maxNum, $suffix) {
                if (preg_match('/^([0-9]+)' . $suffix . '$/', $number, $matches)) {
                    $maxNum = max($maxNum, (int) $matches[1]);
                }
            });

        return ($maxNum + 1) . $suffix;
    }

    /**
     * يسمح بتسجيل الدخول بالبريد الإلكتروني أو رقم الهاتف.
     */
    public function findForPassport(string $identifier): ?self
    {
        return self::where('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();
    }
}
