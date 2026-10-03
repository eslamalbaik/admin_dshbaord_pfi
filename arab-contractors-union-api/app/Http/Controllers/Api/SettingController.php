<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\BankAccount;
use App\Models\Governorate;
use App\Models\Setting;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    /** مجلد صور أيقونات الخدمات على قرص public */
    private const SERVICE_ICONS_DIR = 'union/services';

    /** الحد الأقصى لحجم أيقونة الخدمة (SVG) بالكيلوبايت */
    private const SERVICE_ICON_MAX_KB = 1024;

    /** الخدمات الرئيسية — مخزَّنة كـJSON بإعداد واحد union_services، كل عنصر {title, description, icon} */
    private function rawServices(?string $raw = null): array
    {
        $raw ??= Setting::get('union_services', '');
        $decoded = $raw ? json_decode($raw, true) : null;

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    /** هل القيمة مسار صورة مرفوعة (وليست نصاً قديماً حُفظ بحقل الأيقونة)؟ */
    private function isServiceIconPath(mixed $icon): bool
    {
        return is_string($icon) && str_starts_with($icon, self::SERVICE_ICONS_DIR.'/');
    }

    /** الخدمات مع icon_url كامل — null إن لم تُرفع صورة للخدمة */
    private function services(): array
    {
        return array_map(fn (array $service) => $service + [
            'icon_url' => $this->isServiceIconPath($service['icon'] ?? null)
                ? Storage::disk('public')->url($service['icon'])
                : null,
        ], $this->rawServices());
    }

    /** يحذف صور الأيقونات التي لم تعد مستخدمة بعد حفظ قائمة خدمات جديدة */
    private function deleteOrphanServiceIcons(?string $newRaw): void
    {
        $icons = fn (array $services) => array_filter(
            array_column($services, 'icon'),
            fn ($icon) => $this->isServiceIconPath($icon),
        );

        $orphans = array_diff($icons($this->rawServices()), $icons($this->rawServices($newRaw ?? '')));
        if ($orphans) {
            Storage::disk('public')->delete(array_values($orphans));
        }
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
            'whatsapp'        => Setting::get('support_whatsapp', ''),
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
        $governorates = Governorate::where('is_active', true)
            ->with(['cities' => fn ($query) => $query->where('is_active', true)])
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
            // قيود رفع المستندات (TASK-16 #2) — تُقرأ من ini وقت التشغيل، فتعرض نماذج
            // المقاولين السقف الحقيقي بدل رقم ثابت بالواجهة يفارق إعداد الخادم.
            'upload_limits'         => \App\Support\UploadLimits::toArray(),
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
            // union_services يحمل كل الخدمات كـJSON واحد (مع مسارات الأيقونات) فيتجاوز 2000 بسهولة؛ العمود text
            'settings.*.value'       => 'nullable|string|max:10000',
            'settings.*.group'       => 'nullable|string|max:50',
        ]);

        foreach ($data['settings'] as $item) {
            if ($item['key'] === 'union_services') {
                $this->deleteOrphanServiceIcons($item['value'] ?? '');
            }
        }

        foreach ($data['settings'] as $item) {
            Setting::set($item['key'], $item['value'] ?? '', $item['group'] ?? 'general');
        }

        // الإعدادات تدخل في استجابة الصفحة الرئيسية المخزّنة
        \Illuminate\Support\Facades\Cache::forget('landing_home');

        AuditLogService::record(Auth::user(), 'settings.updated', null, ['keys' => array_column($data['settings'], 'key')]);

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

        AuditLogService::record(Auth::user(), 'settings.logo_uploaded');

        return $this->success(['logo_url' => Storage::disk('public')->url($path)], 'تم رفع الشعار بنجاح.');
    }

    /**
     * POST /api/v1/dashboard/settings/cover-image
     * رفع صورة غلاف شاشة "عن الاتحاد".
     */
    public function uploadCoverImage(Request $request)
    {
        // نفس قيود صور الأخبار والفعاليات (10 ميجا) مع رسائل تذكر السبب والحد بوضوح
        $request->validate([
            'cover_image' => 'required|image|mimes:png,jpg,jpeg,webp|max:10240',
        ], [
            'cover_image.required' => 'لم تصل صورة الغلاف — غالباً حجمها أكبر من المسموح. الحد الأقصى 10 ميجابايت.',
            'cover_image.uploaded' => 'تعذّر رفع صورة الغلاف — غالباً حجمها أكبر من المسموح. الحد الأقصى 10 ميجابايت.',
            'cover_image.image'    => 'صورة الغلاف لازم تكون صورة بصيغة JPG أو PNG أو WEBP.',
            'cover_image.mimes'    => 'صورة الغلاف لازم تكون صورة بصيغة JPG أو PNG أو WEBP.',
            'cover_image.max'      => 'حجم صورة الغلاف أكبر من الحد الأقصى المسموح (10 ميجابايت).',
        ]);

        $old = Setting::get('union_cover_image', '');
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $path = $request->file('cover_image')->store('union', 'public');
        Setting::set('union_cover_image', $path, 'about');

        AuditLogService::record(Auth::user(), 'settings.cover_image_uploaded');

        return $this->success(['cover_image_url' => Storage::disk('public')->url($path)], 'تم رفع صورة الغلاف بنجاح.');
    }

    /**
     * POST /api/v1/dashboard/settings/service-icon
     * رفع صورة أيقونة لخدمة من "الخدمات الرئيسية". لا تُربط بالخدمة هنا: الواجهة تضع
     * المسار المُعاد في حقل icon ثم يُحفظ مع union_services عند "حفظ الإعدادات"،
     * وعندها تُحذف الصورة القديمة التي لم تعد مستخدمة.
     */
    public function uploadServiceIcon(Request $request)
    {
        // SVG فقط: أيقونة متجهة تظهر بنفس الوضوح بأي حجم بالتطبيق. قاعدة image لا تقبل SVG
        // في Laravel 12، فيُفحص الامتداد والمحتوى يدوياً، ويُرفض أي SVG فيه سكربت لأنه يُخدَم من storage العام
        $request->validate([
            'icon' => [
                'required', 'file', 'extensions:svg', 'max:'.self::SERVICE_ICON_MAX_KB,
                function (string $attribute, mixed $file, \Closure $fail) {
                    $content = (string) @file_get_contents($file->getRealPath());
                    if (! preg_match('/<svg[\s>]/i', $content)) {
                        $fail('الملف المختار ليس أيقونة SVG صالحة.');
                    } elseif (preg_match('/<script|<foreignObject|\son\w+\s*=|javascript:/i', $content)) {
                        $fail('أيقونة SVG تحتوي على سكربت أو أكواد غير مسموحة — صدّرها من جديد كـSVG عادي.');
                    }
                },
            ],
        ], [
            'icon.required'   => 'لم تصل الأيقونة — تأكد من اختيار ملف SVG بحجم أقل من 1 ميجابايت.',
            'icon.uploaded'   => 'تعذّر رفع الأيقونة — تأكد من اختيار ملف SVG بحجم أقل من 1 ميجابايت.',
            'icon.file'       => 'تعذّر رفع الأيقونة — تأكد من اختيار ملف SVG بحجم أقل من 1 ميجابايت.',
            'icon.extensions' => 'أيقونة الخدمة لازم تكون ملف SVG فقط.',
            'icon.max'        => 'حجم أيقونة SVG أكبر من الحد الأقصى المسموح (1 ميجابايت).',
        ]);

        // الامتداد ثابت svg: guessExtension قد يعطي xml/txt لملف SVG بدون ترويسة XML
        $path = $request->file('icon')->storeAs(self::SERVICE_ICONS_DIR, \Illuminate\Support\Str::random(40).'.svg', 'public');

        AuditLogService::record(Auth::user(), 'settings.service_icon_uploaded');

        return $this->success([
            'path' => $path,
            'url'  => Storage::disk('public')->url($path),
        ], 'تم رفع الأيقونة بنجاح.');
    }
}
