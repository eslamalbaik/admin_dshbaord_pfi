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
    public function home()
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
                        // $n->image مُحلَّل مسبقاً لرابط كامل عبر accessor بـHasPublicMediaUrls —
                        // تمريره مجدداً عبر Storage::url() كان ينتج رابطاً مضاعفاً مكسوراً
                        'image_url'    => $n->image,
                        'published_at' => $n->published_at,
                    ])
                    ->values(),
            ];
        });

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

    /**
     * دليل الإنتاج العام (اسم، رقم عضوية، مدينة، حالة، مجالات ودرجات — بدون أي بيانات خاصة)، بنجلبه
     * كامل مرة كل 10 دقائق وبنفلتر محلياً. الإنتاج ما فيه كودات الاختصاصات، فالاختصاص بيفلتر على مجاله.
     */
    private function remoteDirectory(string $source, array $f)
    {
        $all = Cache::remember('directory_remote_' . md5($source), 600, function () use ($source) {
            $items = [];
            for ($p = 1; $p <= 40; $p++) {
                $r = \Illuminate\Support\Facades\Http::timeout(15)->get("$source/api/v1/contractors-directory", ['per_page' => 24, 'page' => $p]);
                if (! $r->ok()) {
                    break;
                }
                $items = array_merge($items, $r->json('items') ?? []);
                if ($p >= (int) $r->json('meta.last_page', 1)) {
                    break;
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
            ->filter(fn ($m) => $fieldName === null || collect($m['fields'] ?? [])->contains(fn ($x) => ($x['name'] ?? '') === $fieldName))
            ->filter(fn ($m) => $gradeLabel === null || collect($m['fields'] ?? [])->contains(fn ($x) => in_array($gradeLabel, $x['grades'] ?? [], true)))
            ->filter(fn ($m) => $q === '' || str_contains($m['name'] ?? '', $q)
                || in_array($m['membership_number'] ?? '', [$q, MembershipNumber::normalize($q)], true))
            ->map(fn ($m) => $m + ['logo_url' => null, 'member_since' => null, 'specializations' => []])
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
            ->select(['id', 'name', 'membership_number', 'city', 'status', 'specialties', 'logo', 'registration_date', 'created_at']);

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(function ($q) use ($search, $like) {
                $q->where('name', 'like', $like)
                  // "184" أو "184_g" — نفس قاعدة تسجيل الدخول
                  ->orWhere('membership_number', MembershipNumber::normalize($search))
                  ->orWhere('membership_number', $search);
            });
        }

        $contractors = $query->orderBy('name')->get();

        // نفس "حالة العضوية" المعروضة بصفحة الأرصدة وقائمة المقاولين
        $balances = app(ContractorBalanceController::class);
        $rows = DB::query()->fromSub($balances->balancesQuery(), 'b')
            ->whereIn('id', $contractors->pluck('id'))->get()->keyBy('id');

        $labels = ['active' => 'عضوية سارية', 'expired' => 'عضوية منتهية', 'suspended' => 'موقوفة'];

        $items = $contractors->map(function (Contractor $c) use ($rows, $balances, $labels) {
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
        })->filter(function ($m) use ($status, $field, $spec, $grade) {
            return ($status === '' || $m['membership_status'] === $status)
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
