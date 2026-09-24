<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProfileUpdateRequest extends Model
{
    protected $fillable = [
        'contractor_id', 'proposed_data', 'proposed_files', 'attachment', 'phone_otp_verified_at',
        'status', 'reject_reason', 'reviewed_by', 'reviewed_at', 'superseded_at',
    ];

    protected $casts = [
        'proposed_data'         => 'array',
        'proposed_files'        => 'array',
        'phone_otp_verified_at' => 'datetime',
        'reviewed_at'           => 'datetime',
        'superseded_at'         => 'datetime',
    ];

    public const STATUS_LABELS = [
        'pending'    => 'قيد المراجعة',
        'approved'   => 'مقبول',
        'rejected'   => 'مرفوض',
        'superseded' => 'ألغاه طلب أحدث',
    ];

    /**
     * الحقول القابلة للتعديل عبر طلب — whitelist صريح. الاسم/رقم العضوية/رقم المشتغل
     * مقفلة تماماً ولا تظهر هنا إطلاقاً (REQ-26).
     * الجوال مسموح هنا أيضاً (مسار بديل يمر بموافقة الإدارة)، لكن يتطلب تحقق OTP مسبق
     * عبر send-phone-otp قبل submit — منفصل عن المسار الفوري بدون موافقة في
     * ContractorAuthController::requestPhoneChangeOtp/verifyPhoneChangeOtp + updateFullProfile.
     */
    public const ALLOWED_FIELDS = [
        'authorized_person', 'authorized_person_title', 'email', 'address', 'phone',
    ];

    /**
     * الطبقة (أ) — هوية الشركة وبياناتها القانونية. تعديلها يمسّ ما تقوم عليه العضوية
     * والشهادات، فيمرّ بالمراجعة دائماً (TASK-17 US11 / D1).
     *
     * classification و specialties **ليسا هنا وليسا قابلين للتعديل من البوابة إطلاقاً**:
     * هما مُدخَلا احتساب الرسوم، وقُفلا كلياً على مستوى UpdateFullProfileRequest. تعديلهما
     * صلاحية لوحة الأدمن وحدها.
     */
    public const REVIEWED_IDENTITY_FIELDS = [
        'established_year', 'established_date', 'owner_name', 'partners',
        'capital', 'registration_date', 'legal_form', 'company_purposes',
        'authorized_person', 'authorized_person_title', 'authorized_person_id_number',
    ];

    /**
     * الطبقة (ب) — بيانات تواصل ومراسلة. تمرّ بالمراجعة كما هي اليوم (هي أصل ALLOWED_FIELDS)،
     * وهي أول ما يُخفَّف إن وجدت الإدارة المراجعة بطيئة.
     */
    public const REVIEWED_CONTACT_FIELDS = [
        'email', 'address', 'fax', 'district', 'building', 'floor',
        'authorized_person_phone', 'authorized_person_whatsapp',
    ];

    /**
     * الطبقة (ج) — تبقى فورية بلا مراجعة، ولكل واحد سببه:
     *  phone          — محميّ بـOTP إلزامي، وهو إثبات أقوى من نظرة مراجِع
     *  logo           — مساره الخاص، تجميلي
     *  governorate_id
     *  city_id        — يغذّيان applyLocation() وعمود city المشتق؛ مراجعتهما تعني أن عنوان
     *                   المقاول الظاهر له نفسه يتأخّر أياماً عن الواقع
     *  fcm_token      — رمز جهاز لا بيانات ملف. تمريره عبر المراجعة يوقف إشعارات هواتف
     *                   المقاولين **بصمت** — وهو نوع الفشل الذي كُتب PushChannelWiringTest
     *                   لاقتناصه. لا تُضفه للمراجعة بأي حال.
     */
    public const INSTANT_FIELDS = ['phone', 'logo', 'governorate_id', 'city_id', 'fcm_token'];

    /** كل الحقول النصية التي تمرّ بالمراجعة عبر المسار الكامل. */
    public static function reviewedFields(): array
    {
        return array_merge(self::REVIEWED_IDENTITY_FIELDS, self::REVIEWED_CONTACT_FIELDS);
    }

    /** حقول المستندات ومساراتها — نفس مصدر ContractorFileService حتى لا تتفرّق القائمتان. */
    public static function documentFields(): array
    {
        return \App\Services\ContractorFileService::FILE_FIELDS;
    }

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

    /** روابط المستندات المرحَّلة بانتظار الموافقة — المراجِع يجب أن يفتح الملف لا اسمه. */
    public function getProposedFileUrlsAttribute(): array
    {
        return collect($this->proposed_files ?? [])
            ->map(fn ($path) => Storage::disk('public')->url($path))
            ->all();
    }

    /** حذف الملفات المرحَّلة — يُستدعى عند الرفض والإلغاء وبعد نقلها عند الموافقة. */
    public function deleteStagedFiles(): void
    {
        foreach ($this->proposed_files ?? [] as $path) {
            Storage::disk('public')->delete($path);
        }
    }
}
