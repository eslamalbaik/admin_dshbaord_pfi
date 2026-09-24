<?php

namespace App\Services;

use App\Models\City;
use App\Models\Contractor;
use App\Support\ContractorLookups;
use App\Support\ContractorRequirements;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * خدمة الملف الشخصي للمقاول — تجمع تنسيق الاستجابات ومنطق الأعمال المتعلق بالملف الشخصي.
 */
class ContractorProfileService
{
    public function __construct(
        private ContractorFileService $fileService,
        private MembershipStatusService $membershipStatusService,
        private ContractorFinancialService $financialService
    ) {
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  الاستجابة الخفيفة (Login) — البيانات الأساسية فقط (REQ-02)
    // ─────────────────────────────────────────────────────────────────────────

    public function liteResource(Contractor $contractor): array
    {
        $membership = $contractor->activeMembership;

        return [
            'id'                   => $contractor->id,
            'membership_number'    => $contractor->membership_number,
            'commercial_register'  => $contractor->commercial_register,
            'license_number'       => $contractor->license_number,
            'name'                 => $contractor->name,
            'logo_url'             => $contractor->logo ? Storage::disk('public')->url($contractor->logo) : null,
            'authorized_person'    => $contractor->authorized_person,
            'authorized_person_id_number' => $contractor->authorized_person_id_number,
            'authorized_person_phone'     => $contractor->authorized_person_phone,
            'authorized_person_whatsapp'  => $contractor->authorized_person_whatsapp,
            'classification_label' => $contractor->classification_label,
            'email'                => $contractor->email,
            'phone'                => $contractor->phone,

            // المحافظة والمدينة كأوبجكت id + name (البيانات القديمة النصية تُرجَع بـ id = null)
            'governorate' => $contractor->governorate ? [
                'id'   => $contractor->governorate->id,
                'name' => $contractor->governorate->name,
            ] : null,
            'city' => $contractor->cityModel ? [
                'id'   => $contractor->cityModel->id,
                'name' => $contractor->cityModel->name,
            ] : ($contractor->city ? ['name' => $contractor->city] : null),

            'status'               => $contractor->status,
            'is_frozen'            => $contractor->is_frozen,
            'profile_completed'    => $contractor->profile_completed,
            'last_login_at'        => $contractor->last_login_at?->toISOString(),

            'membership' => $membership ? [
                'id'         => $membership->id,
                'type'       => $membership->type,
                'status'     => $membership->status,
                'expires_at' => $membership->expires_at?->toDateString(),
                'is_expired' => $membership->expires_at?->isPast() ?? false,
            ] : null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  الاستجابة الكاملة (Get Profile) — تفاصيل وملفات (REQ-03)
    // ─────────────────────────────────────────────────────────────────────────

    public function fullResource(Contractor $contractor): array
    {
        return array_merge($this->liteResource($contractor), [
            // بطاقة "حسابي" — حالة العضوية/الذمم/الشهادات/اشتراك سوق الآليات في نداء واحد
            'account_status'    => $this->accountStatus($contractor),

            'established_year'  => $contractor->established_year,
            'owner_name'        => $contractor->owner_name,
            'address'           => $contractor->address,
            'notes'             => $contractor->notes,

            // اكتمال الملف الشخصي — مطلوب قبل السماح بطلب شهادة العضوية
            'profile_data_complete' => $contractor->profile_data_complete,
            'missing_profile_fields' => $contractor->missing_profile_fields,

            // المجالات والاختصاصات والدرجات — كل مجال يضم اختصاصاته وكل اختصاص درجته
            'fields'            => ContractorLookups::buildFieldsTree($contractor->specialties),

            // Extended Fields
            'partners'          => $contractor->partners,
            'specialties'       => $contractor->specialties,
            'fax'               => $contractor->fax,
            'district'          => $contractor->district,
            'building'          => $contractor->building,
            'floor'             => $contractor->floor,
            'capital'           => $contractor->capital,
            'registration_date' => $contractor->registration_date,
            'legal_form'        => $contractor->legal_form,
            'company_purposes'  => $contractor->company_purposes,
        ], $this->fileService->fileUrls($contractor));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  بطاقة "حسابي": بانر واحد بالأولوية (عضوية منتهية > ذمم مستحقة >
    //  عضوية سارية)، حالة قفل الشهادات، وبادج اشتراك سوق الآليات
    // ─────────────────────────────────────────────────────────────────────────

    public function accountStatus(Contractor $contractor): array
    {
        $membership = $this->membershipStatusService->getSubscriptionStatus($contractor);
        // "ما عليه" الكامل (ذمم + غرامات غير مسدَّدة + دفعات معلّقة) — نفس حسبة الشاشة الرئيسية
        $duesAmount = $this->financialService->totalObligations($contractor);
        $hasPendingDues = $duesAmount > 0;
        $certificatesLocked = count(ContractorRequirements::issues($contractor)) > 0;

        $banner = match (true) {
            $membership['badge'] === 'expired' || ! $membership['id'] => [
                'type'       => 'membership_expired',
                'severity'   => 'error',
                'expires_at' => $membership['expires_at'],
                'message'    => 'العضوية منتهية (' . $this->arabicDate($membership['expires_at']) . ')',
                'cta'        => 'renew_membership',
                'cta_label'  => 'السداد وتجديد الآن',
            ],
            $hasPendingDues => [
                'type'               => 'dues_overdue',
                'severity'           => 'warning',
                'outstanding_amount' => $duesAmount,
                'message'            => 'يوجد ذمم مالية مستحقة بقيمة ' . number_format($duesAmount, 2) . ' دينار — يرجى السداد لتجنب تجميد الخدمات والشهادات',
                'cta'                => 'pay_dues',
                'cta_label'          => 'السداد الآن',
            ],
            $membership['badge'] === 'active' => [
                'type'       => 'membership_active',
                'severity'   => 'success',
                'expires_at' => $membership['expires_at'],
                'message'    => 'العضوية سارية حتى تاريخ ' . $this->arabicDate($membership['expires_at']),
                'cta'        => null,
                'cta_label'  => null,
            ],
            default => null,
        };

        $equipmentSubscription = $contractor->activeEquipmentSubscription;
        $equipmentActive = $contractor->hasActiveEquipmentMarketplaceAccess();

        return [
            'banner' => $banner,

            'dues' => [
                'has_pending'        => $hasPendingDues,
                'outstanding_amount' => $duesAmount,
                'badge'              => $hasPendingDues ? 'مستحقات' : null,
            ],

            'certificates' => [
                'locked'      => $certificatesLocked,
                'lock_reason' => $certificatesLocked ? 'يلزم تجديد العضوية' : null,
            ],

            'equipment_subscription' => [
                'active'      => $equipmentActive,
                'badge'       => $equipmentActive ? 'نشط' : 'تجديد الاشتراك',
                'expires_at'  => $equipmentSubscription?->expires_at?->toDateString(),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  التحقق من توافق المحافظة والمدينة ومزامنة العمود النصي القديم
    // ─────────────────────────────────────────────────────────────────────────

    public function applyLocation(array &$validated, Contractor $contractor): void
    {
        if (! array_key_exists('city_id', $validated) && ! array_key_exists('governorate_id', $validated)) {
            return;
        }

        $city = ! empty($validated['city_id']) ? City::find($validated['city_id']) : null;

        if ($city) {
            // المدينة يجب أن تتبع المحافظة المُرسلة (إن أُرسلت فعليًا كتعديل جديد)
            if (! empty($validated['governorate_id']) && (int) $validated['governorate_id'] !== (int) $city->governorate_id) {
                // المدينة المرسلة نفسها المحفوظة أصلاً (لم يغيّرها المستخدم) — يقصد تغيير
                // المحافظة فقط، فنُفرّغ المدينة القديمة غير المتوافقة بدل رفض الطلب بالكامل.
                if ((int) $validated['city_id'] === (int) $contractor->city_id) {
                    $validated['city_id'] = null;
                    $validated['city'] = null;

                    return;
                }

                throw ValidationException::withMessages([
                    'city_id' => ['المدينة المختارة لا تتبع المحافظة المختارة.'],
                ]);
            }

            $validated['governorate_id'] = $city->governorate_id;
            // مزامنة العمود النصي القديم — يبقى الداشبورد وتصدير الـ PDF يعملان كما هما
            $validated['city'] = $city->name;
        } elseif (array_key_exists('city_id', $validated)) {
            $validated['city'] = null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  "31 ديسمبر 2025" من تاريخ Y-m-d — لرسائل بانر حسابي فقط
    // ─────────────────────────────────────────────────────────────────────────

    private function arabicDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        static $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
        ];

        $carbon = \Carbon\Carbon::parse($date);

        return $carbon->day . ' ' . $months[$carbon->month] . ' ' . $carbon->year;
    }
}
