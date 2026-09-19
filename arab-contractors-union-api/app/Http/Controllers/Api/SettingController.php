<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\BankAccount;
use App\Models\Governorate;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    use ApiResponseTrait;

    /** روابط التواصل الاجتماعي (تُهمل الفارغة) */
    private function socialLinks(): array
    {
        return array_filter([
            'facebook'  => Setting::get('social_facebook', ''),
            'instagram' => Setting::get('social_instagram', ''),
            'twitter'   => Setting::get('social_twitter', ''),
            'linkedin'  => Setting::get('social_linkedin', ''),
            'youtube'   => Setting::get('social_youtube', ''),
            'website'   => Setting::get('social_website', ''),
        ]);
    }

    /** رابط شعار الاتحاد الكامل */
    private function logoUrl(): ?string
    {
        $path = Setting::get('union_logo', '');

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /** رابط صورة الغلاف (شاشة "عن الاتحاد") */
    private function coverImageUrl(): ?string
    {
        $path = Setting::get('union_cover_image', '');

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /** الخدمات الرئيسية — مخزَّنة كـJSON بإعداد واحد union_services، كل عنصر {title, description, icon} */
    private function services(): array
    {
        $raw = Setting::get('union_services', '');
        $decoded = $raw ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * GET /api/v1/app/maintenance
     * حالة وضع الصيانة للواجهة العامة — لا يكشف الـ slug السري أبداً.
     */
    public function maintenance()
    {
        return $this->success([
            'maintenance' => (bool) Setting::get('maintenance_mode', '0'),
            'message'     => Setting::get('maintenance_message', 'الموقع قيد الإنشاء حالياً — نعود إليكم قريباً.'),
        ]);
    }

    /**
     * GET /api/v1/app/maintenance/preview/{slug}
     * التحقق من صلاحية slug المعاينة السري لعرض الصفحة الحقيقية أثناء الصيانة.
     */
    public function maintenancePreview(string $slug)
    {
        $expected = mb_strtolower(trim((string) Setting::get('maintenance_preview_slug', 'testing')));
        $slug     = mb_strtolower(trim($slug));

        return $this->success([
            'valid' => $expected !== '' && hash_equals($expected, $slug),
        ]);
    }

    /**
     * GET /api/v1/app/contact
     * بيانات التواصل الظاهرة في شاشة الدعم الفني (رقم واتساب، بريد، هاتف).
     */
    public function contact()
    {
        return $this->success([
            'support_whatsapp' => Setting::get('support_whatsapp', ''),
            'support_email'    => Setting::get('support_email', ''),
            'support_phone'    => Setting::get('support_phone', ''),
        ]);
    }

    /**
     * GET /api/v1/app/about
     * شاشة "عن الاتحاد": الشعار، نبذة، العنوان، أرقام التواصل والبريد.
     */
    public function about()
    {
        return $this->success([
            'name'            => Setting::get('union_name', 'اتحاد المقاولين الفلسطينيين — غزة'),
            'name_en'         => Setting::get('union_name_en', ''),
            'about'           => Setting::get('union_about', ''),
            'address'         => Setting::get('union_address', ''),
            'phone'           => Setting::get('union_phone', ''),
            'phone2'          => Setting::get('union_phone2', ''),
            'email'           => Setting::get('union_email', ''),
            'logo_url'        => $this->logoUrl(),
            'cover_image_url' => $this->coverImageUrl(),
            'badge_label'     => Setting::get('union_badge_label', 'الجهة الرسمية المعتمدة'),
            'vision'          => Setting::get('union_vision', ''),
            'mission'         => Setting::get('union_mission', ''),
            'stats'           => [
                'members_count'   => Setting::get('union_members_count', ''),
                'founding_year'   => Setting::get('union_founding_year', ''),
                'official_label'  => Setting::get('union_official_label', 'معتمدة'),
                'branches_count'  => Setting::get('union_branches_count', ''),
            ],
            'services' => $this->services(),
            // cast لكائن حتى يبقى النوع {} في JSON حتى لو كانت الروابط كلها فارغة
            'social'   => (object) $this->socialLinks(),
        ]);
    }

    /**
     * GET /api/v1/app/settings
     * General Settings: معلومات الدفع (الحسابات البنكية)، رقم التواصل، روابط التواصل الاجتماعي.
     */
    public function general()
    {
        $bankAccounts = BankAccount::active()
            ->orderBy('sort')->orderBy('id')
            ->get()
            ->map(fn ($b) => [
                'id'             => $b->id,
                'bank_name'      => $b->bank_name,
                'bank_name_en'   => $b->bank_name_en,
                'logo_url'       => $b->logo_url,
                'iban'           => $b->iban,
                'account_number' => $b->account_number,
                'account_holder' => $b->account_holder,
                'swift'          => $b->swift,
                'notes'          => $b->notes,
            ]);

        return $this->success([
            'payment_info' => ['bank_accounts' => $bankAccounts->values()],
            'contact'      => [
                'support_whatsapp' => Setting::get('support_whatsapp', ''),
                'support_email'    => Setting::get('support_email', ''),
                'support_phone'    => Setting::get('support_phone', ''),
            ],
            'social_media'   => (object) $this->socialLinks(),
            // أسعار الصرف الحالية (1 وحدة = كم دينار) — لمعاينة التحويل قبل رفع إشعار الدفع
            // (شاشة "التفاصيل البنكية")، نفس المصدر المعتمد من سلطة النقد المستخدَم عند تأكيد المحاسب
            'exchange_rates' => (object) $this->exchangeRates(),
        ]);
    }

    /** أسعار صرف ILS/USD إلى الدينار — من آخر جلب معتمد (ExchangeRateService، كاش ساعة) */
    private function exchangeRates(): array
    {
        $service = app(\App\Services\ExchangeRateService::class);
        $rates = [];

        foreach (\App\Services\ExchangeRateService::CURRENCIES as $currency) {
            $latest = $service->latest($currency);
            if ($latest) {
                $rates[$currency] = [
                    'rate_to_jod' => (float) $latest->rate_to_jod,
                    'source'      => $latest->source,
                    'fetched_at'  => $latest->fetched_at,
                ];
            }
        }

        return $rates;
    }

    /**
     * GET /api/v1/app/governorates
     * المحافظات وبداخل كل محافظة قائمة المدن — لشاشات الاختيار في التطبيق.
     */
    public function governorates()
    {
        $governorates = Governorate::with('cities')
            ->orderBy('sort')->orderBy('id')
            ->get()
            ->map(fn ($governorate) => [
                'id'     => $governorate->id,
                'name'   => $governorate->name,
                'cities' => $governorate->cities->map(fn ($city) => [
                    'id'   => $city->id,
                    'name' => $city->name,
                ])->values(),
            ]);

        return $this->success(['governorates' => $governorates->values()], 'تم جلب المحافظات والمدن بنجاح');
    }

    /**
     * GET /api/v1/app/specialties-catalog
     * كتالوج المجالات والاختصاصات والدرجات — لتعبئة محرّر التخصصات في ملف المقاول.
     */
    public function specialtiesCatalog()
    {
        $mapToList = fn (array $map) => collect($map)
            ->map(fn ($name, $id) => ['id' => (int) $id, 'name' => $name])
            ->values();

        // التصنيف العام لعمود contractors.classification المنفصل — يبقى بصيغته القديمة عمداً (لم يُشمل بالتطبيع)
        $overallGrades = collect(\App\Models\Contractor::CLASSIFICATION_LABELS)
            ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
            ->values();

        return $this->success([
            'fields'                => $mapToList(\App\Support\ContractorLookups::fields()),
            'specializations'       => $mapToList(\App\Support\ContractorLookups::specializations()),
            'field_specializations' => \App\Support\ContractorLookups::fieldSpecializationsMap(),
            'grades'                => \App\Support\ContractorLookups::gradesList(),
            'overall_grades'        => $overallGrades,
        ], 'تم جلب كتالوج التخصصات بنجاح');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Admin Dashboard
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v1/dashboard/settings */
    public function index()
    {
        return $this->success(['settings' => Setting::all(['key', 'value', 'group'])]);
    }

    /**
     * PUT /api/v1/dashboard/settings
     * تحديث مجموعة إعدادات (key => value).
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'settings'               => 'required|array',
            'settings.*.key'         => 'required|string|max:100',
            'settings.*.value'       => 'nullable|string|max:2000',
            'settings.*.group'       => 'nullable|string|max:50',
        ]);

        foreach ($data['settings'] as $item) {
            Setting::set($item['key'], $item['value'] ?? '', $item['group'] ?? 'general');
        }

        // الإعدادات تدخل في استجابة الصفحة الرئيسية المخزّنة
        \Illuminate\Support\Facades\Cache::forget('landing_home');

        return $this->success(['settings' => Setting::all(['key', 'value', 'group'])], 'تم حفظ الإعدادات بنجاح.');
    }

    /**
     * POST /api/v1/dashboard/settings/logo
     * رفع شعار الاتحاد (شاشة عن الاتحاد).
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        $old = Setting::get('union_logo', '');
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $path = $request->file('logo')->store('union', 'public');
        Setting::set('union_logo', $path, 'about');

        return $this->success(['logo_url' => Storage::disk('public')->url($path)], 'تم رفع الشعار بنجاح.');
    }

    /**
     * POST /api/v1/dashboard/settings/cover-image
     * رفع صورة غلاف شاشة "عن الاتحاد".
     */
    public function uploadCoverImage(Request $request)
    {
        $request->validate([
            'cover_image' => 'required|image|mimes:png,jpg,jpeg,webp|max:4096',
        ]);

        $old = Setting::get('union_cover_image', '');
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $path = $request->file('cover_image')->store('union', 'public');
        Setting::set('union_cover_image', $path, 'about');

        return $this->success(['cover_image_url' => Storage::disk('public')->url($path)], 'تم رفع صورة الغلاف بنجاح.');
    }
}
