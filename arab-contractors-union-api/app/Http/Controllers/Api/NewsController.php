<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Traits\HandlesMediaUploads;
use App\Models\News;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class NewsController extends Controller
{
    use ApiResponseTrait, HandlesMediaUploads;

    /** مصدر أخبار خارجي اختياري (NEWS_SOURCE_URL)؛ بدونه (الافتراضي، ومنه staging) الأخبار من قاعدة هالبيئة نفسها. */
    public static function sourceUrl(Request $request): ?string
    {
        $url = env('NEWS_SOURCE_URL');

        return $url ? rtrim($url, '/') : null;
    }

    /**
     * قراءة أخبار الإنتاج العامة (للقراءة فقط) وتمريرها كما هي. null = فشل الاتصال، فنرجع لقاعدة هالبيئة.
     */
    private function fromSource(Request $request, string $path)
    {
        $source = self::sourceUrl($request);
        if (! $source) {
            return null;
        }

        $query = $request->query();
        ksort($query);
        $key = 'news_remote_' . md5($source . $path . json_encode($query));

        $res = Cache::remember($key, 300, function () use ($source, $path, $query) {
            try {
                $r = Http::timeout(10)->acceptJson()->get("$source/api/v1/$path", $query);
            } catch (\Throwable) {
                return null;
            }

            return $r->ok() || $r->status() === 404 ? ['status' => $r->status(), 'body' => $r->json()] : null;
        });

        return $res ? response()->json($res['body'], $res['status']) : null;
    }

    // GET /api/v1/news
    public function index(Request $request)
    {
        if ($remote = $this->fromSource($request, 'news')) {
            return $remote;
        }

        $query = News::published();
        $terms = self::searchTerms((string) $request->query('search', ''));

        if ($terms) {
            // كل كلمة لازم تكون موجودة بالعنوان أو المقدمة أو نص الخبر (بغض النظر عن الهمزات والتاء المربوطة والياء)
            foreach ($terms as $t) {
                $p = self::termRegex($t);
                $query->where(fn ($q) => $q->whereRaw('title REGEXP ?', [$p])
                    ->orWhereRaw('excerpt REGEXP ?', [$p])
                    ->orWhereRaw('body REGEXP ?', [$p]));
            }
            // الأخبار اللي العنوان فيها بيطابق أولاً، وبعدها الأحدث
            $query->orderByRaw(
                '(' . implode(' + ', array_fill(0, count($terms), '(title REGEXP ?)')) . ') DESC',
                array_map([self::class, 'termRegex'], $terms),
            );
        }

        $query->latest('published_at');

        if ($request->filled('category') && in_array($request->category, News::CATEGORIES, true))
            $query->where('category', $request->category);

        $columns = ['id', 'title', 'slug', 'excerpt', 'category', 'image', 'video_url', 'external_url', 'gallery', 'convened_at', 'published_at'];
        $paginator = $query
            ->select($terms ? [...$columns, 'body'] : $columns)
            ->paginate($request->integer('per_page', 9));

        if ($terms) {
            // مقتطف من نص الخبر حول أول كلمة مطابقة (للعرض بنتائج البحث)، والنص الكامل ما بيطلع بالقائمة
            $paginator->getCollection()->transform(function (News $n) use ($terms) {
                $n->setAttribute('snippet', self::snippet((string) $n->body, $terms));
                $n->makeHidden('body');

                return $n;
            });
        }

        return $this->paginated($paginator);
    }

    /** كلمات البحث (حتى 6، بطول حرفين فأكثر). */
    private static function searchTerms(string $q): array
    {
        $parts = preg_split('/[\s\p{P}]+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values(array_unique(array_filter($parts, fn ($t) => mb_strlen($t) >= 2))), 0, 6);
    }

    /** تعبير نمطي للكلمة بيتجاهل فروقات الهمزات (أإآا) والتاء المربوطة (ةه) والياء (يى) والتشكيل. */
    private static function termRegex(string $term, ?string $delim = null): string
    {
        $term = preg_replace('/[\x{0640}\x{064B}-\x{0652}]/u', '', $term);
        $out = '';
        foreach (mb_str_split($term) as $c) {
            $out .= match (true) {
                in_array($c, ['أ', 'إ', 'آ', 'ا', 'ٱ'], true) => '[أإآاٱ]',
                in_array($c, ['ة', 'ه'], true) => '[ةه]',
                in_array($c, ['ى', 'ي'], true) => '[ىي]',
                default => preg_quote($c, $delim),
            };
        }

        return $out;
    }

    /** مقتطف نظيف (~180 حرف) من نص الخبر حول أول مطابقة؛ null إن ما في مطابقة بالنص. */
    private static function snippet(string $html, array $terms): ?string
    {
        $spaced = preg_replace('/<\/(p|div|li|h\d)>|<br\s*\/?>/i', ' ', $html);
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $pos = null;
        foreach ($terms as $t) {
            if (preg_match('/' . self::termRegex($t, '/') . '/iu', $text, $m, PREG_OFFSET_CAPTURE)) {
                $at = mb_strlen(substr($text, 0, $m[0][1]));
                $pos = $pos === null ? $at : min($pos, $at);
            }
        }
        if ($pos === null) {
            return null;
        }
        $start = max(0, $pos - 70);
        $piece = mb_substr($text, $start, 180);

        return ($start > 0 ? '… ' : '') . $piece . ($start + 180 < mb_strlen($text) ? ' …' : '');
    }

    // GET /api/v1/news/latest
    public function latest(Request $request)
    {
        if ($remote = $this->fromSource($request, 'news/latest')) {
            return $remote;
        }

        $news = News::published()
            ->latest('published_at')
            ->select(['id', 'title', 'slug', 'excerpt', 'image', 'video_url', 'external_url', 'gallery', 'convened_at', 'published_at'])
            ->limit(3)
            ->get();

        return $this->success($news->toArray());
    }

    // GET /api/v1/news/{id}  — also accepts the slug for backwards compatibility
    public function show(Request $request, string $identifier)
    {
        if ($remote = $this->fromSource($request, 'news/' . rawurlencode($identifier))) {
            return $remote;
        }

        $news = News::published()
            ->with('author:id,name')
            ->when(
                ctype_digit($identifier),
                fn ($q) => $q->where('id', (int) $identifier),
                fn ($q) => $q->where('slug', $identifier),
            )
            ->firstOrFail();

        // الخبر السابق (الأقدم) والتالي (الأحدث) لأزرار التنقل أسفل الخبر
        $pick = ['id', 'title', 'slug'];
        $data = $news->toArray();
        $data['previous'] = News::published()->where('published_at', '<', $news->published_at)->latest('published_at')->first($pick);
        $data['next'] = News::published()->where('published_at', '>', $news->published_at)->oldest('published_at')->first($pick);

        return $this->success($data);
    }

    // GET /api/v1/admin/news
    public function adminIndex(Request $request)
    {
        $query = News::with('author:id,name')->latest();

        if ($request->filled('search'))
            $query->where('title', 'like', '%' . $request->search . '%');

        if ($request->filled('is_published'))
            $query->where('is_published', (bool) $request->is_published);

        return $this->paginated($query->paginate(15));
    }

    // POST /api/v1/admin/news
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'excerpt'      => 'nullable|string|max:500',
            'body'         => 'required|string',
            'image'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'video_url'    => 'nullable|url|max:500',
            'external_url' => 'nullable|url|max:500',
            'gallery'      => 'nullable|array|max:5',
            'gallery.*'    => 'image|mimes:jpg,jpeg,png,webp|max:10240',
            'is_published' => 'boolean',
            // تاريخ الانعقاد (تاريخ الحدث/الاجتماع الذي يتناوله الخبر) — اختياري وقد يكون بالماضي
            'convened_at'  => 'nullable|date',
            'category'     => ['nullable', Rule::in(News::CATEGORIES)],
            // لازم اليوم أو بعده عند الإنشاء (REQ-10 #2) — التعديل يبقى بلا قيد (نفس نمط
            // Tenders/Announcement/Event) حتى لا يُمنع تصحيح خبر قديم تاريخ نشره بالماضي فعلياً.
            'published_at' => 'nullable|date|after_or_equal:today',
        ], [
            'image.max'          => 'حجم الصورة الرئيسية يتجاوز الحد الأقصى 10 ميجابايت.',
            'gallery.max'        => 'الحد الأقصى 5 صور للمعرض.',
            'gallery.*.max'      => 'حجم إحدى صور المعرض يتجاوز الحد الأقصى 10 ميجابايت.',
            'published_at.after_or_equal' => 'يجب أن يكون تاريخ النشر اليوم أو بعده.',
        ]);

        $this->handleMediaUploads($request, $validated);

        $validated['slug']       = News::generateSlug($validated['title']);
        $validated['created_by'] = $request->user()->id;

        if (($validated['is_published'] ?? false) && empty($validated['published_at']))
            $validated['published_at'] = now();

        $news = News::create($validated);

        AuditLogService::record($request->user(), 'news.created', $news, ['title' => $news->title]);

        return $this->success($news->toArray(), 'تم نشر الخبر بنجاح.', 201);
    }

    // PUT /api/v1/admin/news/{id}
    public function update(Request $request, News $news)
    {
        $validated = $request->validate([
            'title'            => 'sometimes|string|max:255',
            'excerpt'          => 'nullable|string|max:500',
            'body'             => 'sometimes|string',
            'image'            => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'video_url'        => 'nullable|url|max:500',
            'external_url'     => 'nullable|url|max:500',
            'gallery'          => 'nullable|array',
            'gallery.*'        => 'image|mimes:jpg,jpeg,png,webp|max:10240',
            'remove_gallery'   => 'nullable|array',
            'remove_gallery.*' => 'string',
            'is_published'     => 'boolean',
            'convened_at'      => 'nullable|date',
            'category'         => ['nullable', Rule::in(News::CATEGORIES)],
            'publish_now'      => 'boolean',
            // تاريخ جديد لازم يكون اليوم أو بعده (بالتوقيت المحلي) — نفس قيد الإنشاء. التاريخ المحفوظ
            // أصلاً يمرّ حتى لو أقدم، حتى ما ينمنع حفظ تعديل على خبر قديم بدون ما يتغيّر تاريخه.
            'published_at'     => ['nullable', 'date', function ($attribute, $value, $fail) use ($news) {
                $date = Carbon::parse($value)->toDateString();
                if ($news->published_at && $date === $news->published_at->toDateString())
                    return;
                if ($date < now(config('app.local_timezone'))->toDateString())
                    $fail('يجب أن يكون تاريخ النشر اليوم أو بعده.');
            }],
        ], [
            'image.max'     => 'حجم الصورة الرئيسية يتجاوز الحد الأقصى 10 ميجابايت.',
            'gallery.*.max' => 'حجم إحدى صور المعرض يتجاوز الحد الأقصى 10 ميجابايت.',
        ]);

        // "نشر مباشرة" لخبر كان مسودة أو مجدول لتاريخ لاحق: تاريخ النشر يصير الآن
        $publishNow = $request->boolean('publish_now');
        unset($validated['publish_now']);

        $this->handleMediaUploads($request, $validated, $news);

        // handleMediaUploads يدمج الصور الجديدة فوق الموجودة (بعد استبعاد remove_gallery) — الحد
        // الأقصى 5 لازم يُتحقّق على الإجمالي النهائي هون، مش على عدد الملفات المرفوعة لوحدها
        // (اللي كانت validate بأعلى تتحقق منه لو كان array rule عادي).
        if (isset($validated['gallery']) && count($validated['gallery']) > 5) {
            return $this->error('لا يمكن أن يتجاوز عدد صور المعرض 5 صور.', 422, ['gallery' => ['الحد الأقصى 5 صور.']]);
        }

        if (isset($validated['title']))
            $validated['slug'] = News::generateSlug($validated['title'], $news->id);

        $newlyPublished = ($validated['is_published'] ?? false) && ! $news->published_at;
        if (($publishNow || $newlyPublished) && empty($validated['published_at']))
            $validated['published_at'] = now();

        $news->update($validated);

        AuditLogService::record($request->user(), 'news.updated', $news, ['title' => $news->title]);

        return $this->success($news->fresh()->toArray(), 'تم تحديث الخبر بنجاح.');
    }

    // DELETE /api/v1/admin/news/{id}
    public function destroy(Request $request, News $news)
    {
        AuditLogService::record($request->user(), 'news.deleted', $news, ['title' => $news->title]);

        $news->delete();

        return $this->success(message: 'تم حذف الخبر بنجاح.');
    }

    // DELETE /api/v1/admin/news/{news}/gallery-image — حذف فوري لصورة واحدة من المعرض (بديل نمط "علّم ثم احفظ")
    public function destroyGalleryImage(Request $request, News $news)
    {
        $data = $request->validate(['url' => 'required|string']);

        $gallery = $news->getRawOriginal('gallery');
        $gallery = $gallery ? (json_decode($gallery, true) ?? []) : [];

        $path = $this->urlToRelativePath($data['url']);

        if (! in_array($path, $gallery, true))
            return $this->error('الصورة غير موجودة في معرض هذا الخبر.', 404);

        $this->deletePublicFile($path);
        $news->update(['gallery' => array_values(array_diff($gallery, [$path]))]);

        return $this->success(message: 'تم حذف الصورة.');
    }
}
