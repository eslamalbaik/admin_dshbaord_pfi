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
        'membership_number', 'name', 'authorized_person', 'authorized_person_title', 'commercial_register',
        'license_number', 'trade', 'classification', 'established_year', 'owner_name',
        'email', 'phone', 'phone_verified_at', 'city', 'governorate_id', 'city_id', 'address',
        'classification_decision_number', 'classification_decision_date',
        'status', 'is_frozen', 'profile_completed', 'profile_approved_by',
        'cr_file', 'id_file', 'notes',
        'password', 'remember_token', 'last_login_at', 'fcm_token',
        // New Extended Fields
        'partners', 'fax', 'building', 'floor', 'capital', 'registration_date',
        'legal_form', 'company_purposes',
        // New Files
        'authorized_signature', 'logo', 'lease_or_ownership_contract', 'company_approval_letter',
        'municipal_license', 'company_register', 'articles_of_association',
        'internal_bylaws', 'bank_dealing_letter', 'secretary_contract',
        'full_time_engineer_certificate', 'partners_ids', 'authorization_letter',
        // Auto-fields and specialized types
        'field_lk_type', 'specialization_lk_type', 'established_date', 'specialties', 'terms_accepted_at',
        'equipment_disclaimer_accepted_at', 'equipment_banned_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** أسماء أعمدة الملفات المخزّنة على القرص — تُستخدم لبناء رابط `<field>_url` كامل تلقائيًا. */
    public const FILE_FIELDS = [
        'cr_file', 'id_file', 'authorized_signature', 'logo',
        'lease_or_ownership_contract', 'company_approval_letter', 'municipal_license',
        'company_register', 'articles_of_association', 'internal_bylaws',
        'bank_dealing_letter', 'secretary_contract', 'full_time_engineer_certificate',
        'partners_ids', 'authorization_letter',
    ];

    protected $appends = ['has_app_account'];

    /** يبني تلقائيًا `<field>_url` (رابط كامل عبر Storage::url) لأي حقل ملف عند الوصول له كمفتاح ديناميكي. */
    public function getAttribute($key)
    {
        if (str_ends_with($key, '_url')) {
            $field = substr($key, 0, -4);
            if (in_array($field, self::FILE_FIELDS, true)) {
                $path = $this->getAttributeFromArray($field);

                return $path ? url(\Illuminate\Support\Facades\Storage::url($path)) : null;
            }
        }

        return parent::getAttribute($key);
    }

    /** يُفعَّل بصفحة show/update فقط — تفاديًا لحساب 15 رابط لكل صف بقوائم/pagination. */
    public bool $withFileUrls = false;

    /** append() لا يصلح مع getAttribute() الديناميكي لأن Eloquent يطلب method get{Field}Attribute() فعلي — فبنحقن الروابط يدويًا بعد toArray(). */
    public function toArray()
    {
        $array = parent::toArray();

        if ($this->withFileUrls) {
            foreach (self::FILE_FIELDS as $field) {
                $array["{$field}_url"] = $this->getAttribute("{$field}_url");
            }
        }

        return $array;
    }

    protected $casts = [
        'established_year'       => 'integer',
        'field_lk_type'          => 'integer',
        'specialization_lk_type' => 'integer',
        'established_date'       => 'date',
        'classification_decision_date' => 'date',
        'specialties'            => 'array',
        'last_login_at'          => 'datetime',
        'phone_verified_at'      => 'datetime',
        'terms_accepted_at'      => 'datetime',
        'is_frozen'              => 'boolean',
        'profile_completed'      => 'boolean',
        'equipment_disclaimer_accepted_at' => 'datetime',
        'equipment_banned_at'    => 'datetime',
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

    public function nameChangeRequests()
    {
        return $this->hasMany(ContractorNameChangeRequest::class);
    }

    public function profileUpdateRequests()
    {
        return $this->hasMany(ProfileUpdateRequest::class);
    }

    public function dues()
    {
        return $this->hasMany(ContractorDue::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function equipment()
    {
        return $this->hasMany(Equipment::class);
    }

    public function equipmentSubscriptions()
    {
        return $this->hasMany(ContractorEquipmentSubscription::class);
    }

    public function activeEquipmentSubscription()
    {
        return $this->hasOne(ContractorEquipmentSubscription::class)
            ->where('expires_at', '>=', now())
            ->latestOfMany('expires_at');
    }

    /** وصول مجاني مؤقت لسوق الآليات (لم تنتهِ equipment_marketplace_free_until) أو اشتراك مدفوع ساري */
    public function hasActiveEquipmentMarketplaceAccess(): bool
    {
        $freeUntil = Setting::get('equipment_marketplace_free_until');
        $hasFreeTrialAccess = ! $freeUntil || now()->lte(\Carbon\Carbon::parse($freeUntil));

        return $hasFreeTrialAccess || (bool) $this->activeEquipmentSubscription;
    }

    public function bookmarkedTenders()
    {
        return $this->belongsToMany(Tender::class, 'tender_bookmarks');
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

    /** الحقول والملفات المطلوبة في نموذج تسجيل المقاول بلوحة الأدمن — نفس القائمة تُستخدم هنا لبناء "اكتمال الملف". */
    private const REQUIRED_PROFILE_FIELDS = [
        'owner_name'        => 'اسم صاحب المنشأة',
        'authorized_person' => 'اسم المفوض بالتوقيع',
        'phone'             => 'رقم الهاتف',
        'address'           => 'العنوان التفصيلي',
        'license_number'    => 'رقم رخصة البلدية',
        'established_date'  => 'تاريخ التأسيس',
        'capital'           => 'رأس المال',
        'legal_form'        => 'الشكل القانوني',
        'registration_date' => 'تاريخ التسجيل',
        'company_purposes'  => 'غايات الشركة',
    ];

    private const REQUIRED_PROFILE_FILES = [
        'cr_file'                       => 'السجل التجاري',
        'company_register'              => 'مستخرج سجل الشركة',
        'municipal_license'             => 'رخصة المهن (البلدية)',
        'bank_dealing_letter'           => 'شهادة تعامل بنكي',
        'articles_of_association'       => 'عقد التأسيس',
        'internal_bylaws'               => 'النظام الداخلي',
        'lease_or_ownership_contract'   => 'عقد الإيجار / الملكية',
        'partners_ids'                  => 'صور هويات الشركاء',
        'authorization_letter'          => 'كتاب تفويض المفوّض',
        'company_approval_letter'       => 'كتاب موافقة الشركة',
        'full_time_engineer_certificate'=> 'شهادة مهندس متفرغ',
        'secretary_contract'            => 'عقد سكرتير',
    ];

    /** أسماء الحقول والملفات الناقصة لإكمال الملف الشخصي (فارغة يعني الملف مكتمل). */
    public function getMissingProfileFieldsAttribute(): array
    {
        $missing = [];

        foreach (self::REQUIRED_PROFILE_FIELDS as $field => $label) {
            if (empty($this->$field)) {
                $missing[] = $label;
            }
        }

        if (empty($this->governorate_id) && empty($this->city_id) && empty($this->city)) {
            $missing[] = 'المحافظة / المدينة';
        }

        foreach (self::REQUIRED_PROFILE_FILES as $field => $label) {
            if (empty($this->$field)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    public function getProfileDataCompleteAttribute(): bool
    {
        return count($this->missing_profile_fields) === 0;
    }

    /** فتح حساب على التطبيق = أكمل التحقق من الهوية وضبط كلمة مرور (نفس شرط تسجيل الدخول). */
    public function getHasAppAccountAttribute(): bool
    {
        return (bool) $this->password && (bool) $this->phone_verified_at;
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
     * هل المقاول مؤهل لتسجيل الدخول؟
     * مصدر واحد لشروط الحظر — أي تغيير مستقبلي يحصل هنا فقط.
     *
     * @return array{eligible: bool, reason: ?string, error_key: ?string, http_code: ?int}
     */
    public function loginEligibility(): array
    {
        if ($this->is_frozen) {
            return [
                'eligible'  => false,
                'reason'    => \App\Support\ApiMessages::ACCOUNT_FROZEN,
                'error_key' => 'account_frozen',
                'http_code' => 403,
            ];
        }

        if ($this->status === 'suspended') {
            return [
                'eligible'  => false,
                'reason'    => \App\Support\ApiMessages::ACCOUNT_SUSPENDED,
                'error_key' => 'account_suspended',
                'http_code' => 403,
            ];
        }

        if (! $this->phone_verified_at) {
            return [
                'eligible'  => false,
                'reason'    => \App\Support\ApiMessages::PHONE_NOT_VERIFIED,
                'error_key' => 'phone_not_verified',
                'http_code' => 403,
            ];
        }

        return ['eligible' => true, 'reason' => null, 'error_key' => null, 'http_code' => null];
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
