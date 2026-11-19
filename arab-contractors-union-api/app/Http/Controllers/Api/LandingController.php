<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Contractor;
use App\Models\News;
use App\Models\Setting;
use App\Models\Tender;
use App\Rules\MembershipNumber;
use App\Support\ContractorLookups;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LandingController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/landing/home
     * كل بيانات الصفحة الرئيسية في استجابة واحدة (كاش 10 دقائق).
     */
    public function home(Request $request)
    {
        $items = Cache::remember('landing_home', 600, function () {
            return [
                'maintenance' => [
                    'enabled' => (bool) Setting::get('maintenance_mode', '0'),
                    'message' => Setting::get('maintenance_message', 'الموقع قيد الإنشاء حالياً — نعود إليكم قريباً.'),
                ],
                'union' => [
                    'name'     => Setting::get('union_name', 'اتحاد المقاولين الفلسطينيين — غزة'),
                    'name_en'  => Setting::get('union_name_en', ''),
                    'about'    => Setting::get('union_about', ''),
                    'address'  => Setting::get('union_address', ''),
                    'phone'    => Setting::get('union_phone', ''),
                    'phone2'   => Setting::get('union_phone2', ''),
                    'email'    => Setting::get('union_email', ''),
                    'logo_url' => ($logo = Setting::get('union_logo', ''))
                        ? Storage::disk('public')->url($logo)
                        : null,
                    'social'   => (object) array_filter([
                        'facebook'  => Setting::get('social_facebook', ''),
                        'instagram' => Setting::get('social_instagram', ''),
                        'twitter'   => Setting::get('social_twitter', ''),
                        'linkedin'  => Setting::get('social_linkedin', ''),
                        'youtube'   => Setting::get('social_youtube', ''),
                        'website'   => Setting::get('social_website', ''),
                    ]),
                ],
                'contact' => [
                    'support_whatsapp' => Setting::get('support_whatsapp', ''),
                    'support_email'    => Setting::get('support_email', ''),
                    'support_phone'    => Setting::get('support_phone', ''),
                ],
                'stats' => [
                    // عدّادات حقيقية من قاعدة البيانات
                    'members_count' => Contractor::where('status', 'active')->count(),
                    'tenders_open'  => Tender::where('status', 'open')->count(),
                    'news_count'    => News::published()->count(),
                    'specializations_count' => \App\Models\ContractorSpecialization::where('is_active', true)->count(),
                    // أرقام تسويقية يحدّثها الأدمن من الإعدادات
                    'years'         => (int) Setting::get('stat_years', '30'),
                    'projects'      => (int) Setting::get('stat_projects', '5000'),
                    'branches'      => (int) Setting::get('stat_branches', '11'),
                ],
                'latest_news' => News::published()
                    ->latest('published_at')
                    ->take(6)
                    ->get()
                    ->map(fn ($n) => [
                        'id'           => $n->id,
                        'title'        => $n->title,
                        'slug'         => $n->slug,
                        'excerpt'      => $n->excerpt,
                        'category'     => $n->category,
                        // $n->image مُحلَّل مسبقاً لرابط كامل عبر accessor بـHasPublicMediaUrls —
                        // تمريره مجدداً عبر Storage::url() كان ينتج رابطاً مضاعفاً مكسوراً
                        'image_url'    => $n->image,
                        'published_at' => $n->published_at,
                    ])
                    ->values(),
            ];
        });

        // البيئات التجريبية: آخر الأخبار من الإنتاج (قراءة فقط) بدل قاعدة هالبيئة
        if ($source = NewsController::sourceUrl($request)) {
            $remote = Cache::remember('landing_news_remote_' . md5($source), 300, function () use ($source) {
                try {
                    $r = \Illuminate\Support\Facades\Http::timeout(10)->acceptJson()->get("$source/api/v1/news", ['per_page' => 6]);
                } catch (\Throwable) {
                    return null;
                }

                return $r->ok() ? collect($r->json('items') ?? [])->map(fn ($n) => [
                    'id'           => $n['id'] ?? null,
                    'title'        => $n['title'] ?? '',
                    'slug'         => $n['slug'] ?? null,
                    'excerpt'      => $n['excerpt'] ?? '',
                    'category'     => $n['category'] ?? null,
                    'image_url'    => $n['image'] ?? null,
                    'published_at' => $n['published_at'] ?? null,
                ])->values()->all() : null;
            });
            if ($remote) {
                $items['latest_news'] = $remote;
            }
        }

        return $this->success($items);
    }

    /** سجلات تجريبية ما بتظهر بالدليل العام. */
    private const HIDDEN_FROM_DIRECTORY = ['936_g'];

    /** رابط دليل الإنتاج العام للبيئات التجريبية (DIRECTORY_SOURCE_URL، أو تلقائياً على api.pcuorg.cloud)؛ null = قاعدة هالبيئة. */
    private function directorySource(Request $request): ?string
    {
        $url = env('DIRECTORY_SOURCE_URL') ?: ($request->getHost() === 'api.pcuorg.cloud' ? 'https://api-production.pcuorg.cloud' : null);

        return $url ? rtrim($url, '/') : null;
    }

    /** تطبيع نص للبحث: أ/إ/آ=ا، ة=ه، ى=ي، بدون تشكيل أو تطويل، أرقام هندية=لاتينية، وكل فاصل = مسافة. */
    private static function normalizeSearch(string $s): string
    {
        $s = preg_replace('/[\x{0640}\x{064B}-\x{0652}]/u', '', $s);
        $s = str_replace(
            ['أ', 'إ', 'آ', 'ٱ', 'ة', 'ى', 'ؤ', 'ئ', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['ا', 'ا', 'ا', 'ا', 'ه', 'ي', 'و', 'ي', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            mb_strtolower($s),
        );

        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s));
    }

    /**
     * هل الشركة تطابق نص البحث؟ كل كلمة بالبحث لازم تنوجد (بأي ترتيب) بأي حقل: اسم الشركة، رقم العضوية،
     * المدينة، المفوض، الجوال، المجالات، الاختصاصات والدرجات. أرقام قصيرة بتطابق بداية كلمة بس.
     */
    private static function matchesSearch(array $m, string $q): bool
    {
        $norm = self::normalizeSearch($q);
        if ($norm === '') {
            return true;
        }

        $number = (string) ($m['membership_number'] ?? '');
        if ($number !== '' && in_array($number, [$q, MembershipNumber::normalize($q)], true)) {
            return true;
        }

        $phone = (string) ($m['phone'] ?? '');
        $parts = [
            $m['name'] ?? '', $number, $m['city'] ?? '', $m['authorized_person'] ?? '', $phone, ltrim($phone, '0+'),
        ];
        foreach ($m['fields'] ?? [] as $f) {
            $parts[] = $f['name'] ?? '';
            array_push($parts, ...($f['grades'] ?? []));
            foreach ($f['specializations'] ?? [] as $s) {
                $parts[] = $s['name'] ?? '';
            }
        }
        foreach ($m['specializations'] ?? [] as $s) {
            $parts[] = $s['name'] ?? '';
        }

        $hay   = ' ' . self::normalizeSearch(implode(' ', array_map('strval', $parts))) . ' ';
        $local = ltrim($phone, '0+');

        foreach (explode(' ', $norm) as $t) {
            $t = mb_strlen($t) > 3 && str_starts_with($t, 'ال') ? mb_substr($t, 2) : $t;
            $found = ctype_digit($t)
                ? str_contains($hay, ' ' . $t) || ($local !== '' && str_contains(preg_replace('/\D/', '', $local), ltrim($t, '0')) && strlen($t) >= 5)
                : str_contains($hay, $t);
            if (! $found) {
                return false;
            }
        }

        return true;
    }

    /** تطبيع اسم عربي للمقارنة فقط: أسماء المجالات تختلف بحرف بين قاعدة الإنتاج وstaging ("طرق" = "الطرق"، "ابنية" = "الأبنية"). */
    private static function normalizeName(string $s): string
    {
        $s = preg_replace('/[\x{0640}\x{064B}-\x{0652}]/u', '', $s);
        $s = str_replace(['أ', 'إ', 'آ', 'ة', 'ى'], ['ا', 'ا', 'ا', 'ه', 'ي'], $s);
        $words = preg_split('/[\s\/\\\\,،\-–—_()]+/u', $s, -1, PREG_SPLIT_NO_EMPTY);

        return implode('', array_map(
            fn ($w) => mb_strlen($w) > 3 && str_starts_with($w, 'ال') ? mb_substr($w, 2) : $w,
            $words,
        ));
    }

    /**
     * دليل الإنتاج العام (اسم، رقم عضوية، مدينة، حالة، مجالات ودرجات — بدون أي بيانات خاصة)، بنجلبه
     * كامل مرة كل 10 دقائق وبنفلتر محلياً. الإنتاج ما فيه كودات الاختصاصات، فالاختصاص بيفلتر على مجاله.
     */
    private function remoteDirectory(string $source, array $f)
    {
        $all = Cache::remember('directory_remote_' . md5($source), 600, function () use ($source) {
            $url = "$source/api/v1/contractors-directory";
            $first = \Illuminate\Support\Facades\Http::timeout(15)->get($url, ['per_page' => 60, 'page' => 1]);
            if (! $first->ok()) {
                return [];
            }

            $items = $first->json('items') ?? [];
            $last = min((int) $first->json('meta.last_page', 1), 40);
            if ($last > 1) {
                // باقي الصفحات بالتوازي بدل واحدة ورا الثانية (كان أبطأ جزء بتحميل صفحة الأعضاء على staging)
                $responses = \Illuminate\Support\Facades\Http::pool(fn ($pool) => collect(range(2, $last))
                    ->map(fn ($p) => $pool->as((string) $p)->timeout(15)->get($url, ['per_page' => 60, 'page' => $p]))
                    ->all());
                foreach (range(2, $last) as $p) {
                    $r = $responses[(string) $p] ?? null;
                    if ($r instanceof \Illuminate\Http\Client\Response && $r->ok()) {
                        $items = array_merge($items, $r->json('items') ?? []);
                    }
                }
            }

            return $items;
        });

        $fieldCode = $f['field'] ?? null;
        if ($fieldCode === null && $f['spec'] !== null) {
            foreach (ContractorLookups::fieldSpecializationsMap() as $code => $specs) {
                if (in_array($f['spec'], $specs, true)) {
                    $fieldCode = (int) $code;
                    break;
                }
            }
        }
        $fieldName = $fieldCode !== null ? ContractorLookups::fieldName($fieldCode) : null;
        $gradeLabel = $f['grade'] !== '' ? (ContractorLookups::specialtyGradeLabels()[$f['grade']] ?? $f['grade']) : null;
        $q = $f['search'];

        $items = collect($all)
            ->reject(fn ($m) => in_array($m['membership_number'] ?? '', self::HIDDEN_FROM_DIRECTORY, true))
            ->filter(fn ($m) => $f['status'] === '' || ($m['membership_status'] ?? '') === $f['status'])
            ->filter(fn ($m) => $fieldName === null || collect($m['fields'] ?? [])->contains(fn ($x) => self::normalizeName($x['name'] ?? '') === self::normalizeName($fieldName)))
            ->filter(fn ($m) => $gradeLabel === null || collect($m['fields'] ?? [])->contains(fn ($x) => in_array($gradeLabel, $x['grades'] ?? [], true)))
            ->filter(fn ($m) => self::matchesSearch($m, $q))
            ->map(fn ($m) => $m + ['authorized_person' => null, 'phone_is_mobile' => false, 'phone' => null, 'logo_url' => null, 'member_since' => null, 'specializations' => []])
            ->values();

        return $this->paginated(new LengthAwarePaginator(
            $items->forPage($f['page'], $f['perPage'])->values()->all(), $items->count(), $f['perPage'], $f['page'],
        ));
    }

    /**
     * GET /api/v1/contractors-directory?search=&status=&field=&specialization=&grade=&per_page=&page=
     * دليل شركات المقاولات (الصفحة الرئيسية + صفحة "أعضاء الاتحاد") — بيانات عامة فقط
     * (اسم، رقم عضوية، مدينة، شعار، مجالات واختصاصات ودرجات، حالة العضوية، سنة الانتساب) —
     * بدون أي جوال أو بريد أو هوية أو ملفات. المقاولين "قيد المراجعة" ما بيظهروا.
     *
     * الفلاتر:
     *  - status: active | expired | suspended — حالة العضوية المعروضة (نفس صفحة الأرصدة)
     *  - field: كود المجال، specialization: كود الاختصاص، grade: كود الدرجة (اولى أ، ثانية...)
     * عدد الأعضاء بالمئات، فالحالة المحسوبة والفلترة بتصير بذاكرة على كل القائمة ثم تقسيم الصفحات.
     */
    public function directory(Request $request)
    {
        $search  = trim((string) $request->query('search', ''));
        $perPage = min(max((int) $request->query('per_page', 6), 1), 60);
        $page    = max((int) $request->query('page', 1), 1);
        $status  = (string) $request->query('status', '');
        $field   = $request->filled('field') ? (int) $request->query('field') : null;
        $spec    = $request->filled('specialization') ? (int) $request->query('specialization') : null;
        $grade   = trim((string) $request->query('grade', ''));

        // بيئة الستيجنغ قاعدتها تجريبية: بنعرض فيها دليل الإنتاج العام (قراءة فقط) بدل شركاتها التجريبية.
        if ($source = $this->directorySource($request)) {
            return $this->remoteDirectory($source, compact('search', 'perPage', 'page', 'status', 'field', 'spec', 'grade'));
        }

        $query = Contractor::query()
            ->whereIn('status', ['active', 'expired', 'suspended'])
            ->whereNotIn('membership_number', self::HIDDEN_FROM_DIRECTORY)
            ->select(['id', 'name', 'membership_number', 'city', 'phone', 'authorized_person', 'authorized_person_phone', 'status', 'specialties', 'logo', 'registration_date', 'created_at']);

        // البحث بيصير بالذاكرة بعد بناء الصفوف (نفس طريقة الفلاتر) عشان يغطي كل الحقول ويتجاهل الهمزات
        $contractors = $query->orderBy('name')->get();

        // نفس "حالة العضوية" المعروضة بصفحة الأرصدة وقائمة المقاولين
        $balances = app(ContractorBalanceController::class);
        $rows = DB::query()->fromSub($balances->balancesQuery(), 'b')
            ->whereIn('id', $contractors->pluck('id'))->get()->keyBy('id');

        $labels = ['active' => 'عضوية سارية', 'expired' => 'عضوية منتهية', 'suspended' => 'موقوفة'];

        $items = $contractors->map(function (Contractor $c) use ($rows, $balances, $labels) {
            $phone = $c->phone ?: $c->authorized_person_phone ?: null;

            $memberStatus = $c->status === 'suspended' || ! isset($rows[$c->id])
                ? $c->status
                : $balances->membershipStatus($rows[$c->id]);

            $specs = collect($c->specialties ?? []);
            $tree  = ContractorLookups::buildFieldsTree($c->specialties);
            $since = $c->registration_date ?? $c->created_at;

            return [
                'name'              => $c->name,
                'membership_number' => $c->membership_number,
                'city'              => $c->city,
                'authorized_person' => $c->authorized_person ?: null,
                'phone'             => $phone,
                'phone_is_mobile'   => $phone !== null && \App\Support\PhoneNumber::looksLikeMobile($phone),
                'logo_url'          => $c->logo ? $c->logo_url : null,
                'member_since'      => $since ? (int) \Carbon\Carbon::parse($since)->year : null,
                'membership_status' => $memberStatus,
                'status_label'      => $labels[$memberStatus] ?? $memberStatus,
                'fields'            => collect($tree)->map(fn ($f) => [
                    'name'   => $f['field_name'],
                    'grades' => collect($f['specializations'])->pluck('grade_label')->filter()->unique()->values(),
                    // الاختصاصات فرع من المجال: كل اختصاص مع درجته تحت مجاله
                    'specializations' => collect($f['specializations'])
                        ->map(fn ($s) => ['name' => $s['spec_name'], 'grade' => $s['grade_label']])
                        ->filter(fn ($s) => $s['name'] !== 'غير محدد')->values(),
                ])->values(),
                'specializations'   => collect($tree)->flatMap(fn ($f) => $f['specializations'])
                    ->map(fn ($s) => ['name' => $s['spec_name'], 'grade' => $s['grade_label']])
                    ->filter(fn ($s) => $s['name'] !== 'غير محدد')->values(),
                // للفلترة فقط — ما بترجع بالاستجابة
                '_field_ids' => $specs->pluck('field_lk_type')->filter()->map(fn ($v) => (int) $v)->all(),
                '_spec_ids'  => $specs->pluck('specialization_lk_type')->filter()->map(fn ($v) => (int) $v)->all(),
                '_grades'    => $specs->pluck('classification')->filter()->all(),
            ];
        })->filter(function ($m) use ($status, $field, $spec, $grade, $search) {
            return ($status === '' || $m['membership_status'] === $status)
                && self::matchesSearch($m, $search)
                && ($field === null || in_array($field, $m['_field_ids'], true))
                && ($spec === null || in_array($spec, $m['_spec_ids'], true))
                && ($grade === '' || in_array($grade, $m['_grades'], true));
        })
            // الفعّالة أولاً ثم أبجدياً
            ->sortBy(fn ($m) => ($m['membership_status'] === 'active' ? '0' : '1') . $m['name'])
            ->map(fn ($m) => array_diff_key($m, array_flip(['_field_ids', '_spec_ids', '_grades'])))
            ->values();

        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values()->all(),
            $items->count(),
            $perPage,
            $page,
        );

        return $this->paginated($paginator);
    }

    /**
     * GET /api/v1/contractors-directory/filters
     * خيارات فلاتر صفحة "أعضاء الاتحاد": المجالات واختصاصاتها، الدرجات، وحالات العضوية.
     */
    public function directoryFilters()
    {
        $specNames = ContractorLookups::specializations();
        $map       = ContractorLookups::fieldSpecializationsMap();

        $fields = collect(ContractorLookups::fields())
            ->reject(fn ($name, $code) => (int) $code === 10) // "غير محدد"
            ->map(fn ($name, $code) => [
                'value'           => (int) $code,
                'label'           => $name,
                'specializations' => collect($map[$code] ?? [])
                    ->filter(fn ($s) => isset($specNames[$s]))
                    ->map(fn ($s) => ['value' => (int) $s, 'label' => $specNames[$s]])->values(),
            ])->values();

        return $this->success([
            'fields'   => $fields,
            'grades'   => collect(ContractorLookups::gradesList())
                ->map(fn ($g) => ['value' => $g['value'], 'label' => $g['label']])->values(),
            'statuses' => [
                ['value' => 'active',    'label' => 'فعّالة'],
                ['value' => 'expired',   'label' => 'منتهية'],
                ['value' => 'suspended', 'label' => 'موقوفة'],
            ],
        ]);
    }
}
