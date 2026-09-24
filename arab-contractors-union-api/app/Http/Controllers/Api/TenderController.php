<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Contractor;
use App\Models\Tender;
use App\Models\TenderBookmark;
use App\Models\TenderCategory;
use App\Notifications\NewTenderPublishedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TenderController extends Controller
{
    use ApiResponseTrait;

    /** [اسم التصنيف => رابط صورته] — يُحمَّل مرة واحدة لكل طلب عند تنسيق قائمة عطاءات */
    private ?array $categoryImages = null;

    // GET /api/tenders
    public function index(Request $request)
    {
        $query = $this->applyFilters(Tender::query(), $request);

        return $this->paginated($query->paginate($this->perPage($request)));
    }

    // GET /api/v1/tenders-public — قائمة عامة بدون بيانات إدارية
    public function publicIndex(Request $request)
    {
        $query = $this->applyFilters(Tender::query(), $request);

        $paginator = $query->paginate($this->perPage($request))
            ->through(fn ($t) => $this->formatPublic($t));

        return $this->paginated($paginator);
    }

    // GET /api/v1/tenders-public/{tender}
    public function publicShow(Tender $tender)
    {
        return $this->success($this->formatPublic($tender));
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Contractor Mobile App — تصفح + حفظ (Bookmark) — REQ-09/11/13
    // ═════════════════════════════════════════════════════════════════════════

    // GET /api/v1/contractor/tenders
    public function contractorIndex(Request $request)
    {
        $contractor = $request->user();
        $query      = $this->applyFilters(Tender::query(), $request);

        $bookmarkedIds = $contractor->bookmarkedTenders()->pluck('tenders.id');

        $paginator = $query->paginate($this->perPage($request))
            ->through(fn ($t) => $this->formatPublic($t, $bookmarkedIds, forContractor: true));

        return $this->paginated($paginator);
    }

    // GET /api/v1/contractor/tenders/{tender}
    public function contractorShow(Request $request, Tender $tender)
    {
        $bookmarkedIds = $request->user()->bookmarkedTenders()->pluck('tenders.id');

        return $this->success($this->formatPublic($tender, $bookmarkedIds, forContractor: true));
    }

    // GET /api/v1/contractor/tenders/bookmarked
    public function bookmarked(Request $request)
    {
        $query = $this->applyFilters(
            $request->user()->bookmarkedTenders()->getQuery(),
            $request,
        );

        $paginator = $query->paginate($this->perPage($request))
            ->through(fn ($t) => $this->formatPublic($t, collect([$t->id]), forContractor: true));

        return $this->paginated($paginator);
    }

    // POST /api/v1/contractor/tenders/{tender}/bookmark
    public function bookmark(Request $request, Tender $tender)
    {
        TenderBookmark::firstOrCreate([
            'contractor_id' => $request->user()->id,
            'tender_id'     => $tender->id,
        ]);

        return $this->success(message: 'تم حفظ العطاء بالمفضلة.');
    }

    // DELETE /api/v1/contractor/tenders/{tender}/bookmark
    public function unbookmark(Request $request, Tender $tender)
    {
        TenderBookmark::where('contractor_id', $request->user()->id)
            ->where('tender_id', $tender->id)
            ->delete();

        return $this->success(message: 'تمت إزالة العطاء من المفضلة.');
    }

    // POST /api/tenders
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'              => 'required|string|max:255',
            'issuing_entity'     => 'nullable|string|max:255',
            'description'        => 'nullable|string',
            'union_notes'        => 'nullable|string',
            'category'           => ['nullable', Rule::in(TenderCategory::activeNames())],
            // بتاريخ ووقت (ساعة ودقيقة) معاً، وأقرب موعد مسموح هو بداية الغد بالتوقيت المحلي
            // (تاريخ اليوم + 1). التعديل لا يفرضه إلا إذا تغيّر الموعد فعلاً — انظر update().
            'deadline'           => ['nullable', 'date', 'after_or_equal:' . self::minDeadline()->toDateTimeString()],
            'status'             => 'nullable|in:open,closed,cancelled',
            'submission_types'   => 'nullable|array',
            'submission_types.*' => 'in:email,phone,file',
            'submission_email'   => 'nullable|email|max:255',
            'submission_phone'   => 'nullable|string|max:20',
            'submission_file'    => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'external_url'       => 'nullable|url|max:500',
        ]);

        if (! empty($validated['deadline'])) {
            $validated['deadline'] = $this->normalizeDeadline($validated['deadline']);
        }

        if ($request->hasFile('submission_file')) {
            $validated['submission_file'] = $request->file('submission_file')
                ->store('tenders/files', 'public');
        }

        if (isset($validated['submission_types'])) {
            $validated['submission_types'] = array_values(array_filter($validated['submission_types']));
        }

        $validated['created_by'] = Auth::id();
        $tender = Tender::create($validated);
        $tender->update(['reference_number' => $this->generateReferenceNumber($tender)]);

        $this->notifyContractorsOfNewTender($tender);

        $tender->refresh();

        return $this->success($tender->toArray(), 'تم إضافة العطاء بنجاح.', 201);
    }

    /**
     * إشعار المقاولين بعطاء جديد — database + push معاً.
     *
     * يُستدعى بعد توليد الرقم المرجعي لا قبله، لأن generateReferenceNumber() يحتاج
     * $tender->id فلا يتوفّر إلا بعد الحفظ؛ الاستدعاء المبكر يُنزِل reference_number
     * فارغاً في حمولة الإشعار.
     *
     * المحظورون مستثنون: EnsureContractorIsActive يردّهم 403 ويُلغي توكناتهم عند أي
     * طلب، فالإشعار إليهم إزعاج بلا فائدة. لا نفلتر على fcm_token عمداً — المقاول بلا
     * توكن يجب أن يبقى له سجل database يراه داخل التطبيق، وFcmChannel يتخطّاه بصمت.
     */
    private function notifyContractorsOfNewTender(Tender $tender): void
    {
        // عطاء يُنشأ مباشرة كمغلق/ملغى ليس "عطاءً جديداً" يستحق إشعاراً.
        //
        // نقرأ الحالة بـfresh() لا من الكائن في الذاكرة: Tender::create() لا يحمّل قيم
        // DB الافتراضية، فـ$tender->status يبقى null عند عدم إرسال status في الطلب رغم
        // أن العمود يُكتب 'open' افتراضياً — ومقارنته مباشرة كانت تُسقط كل إشعار.
        if ($tender->fresh()?->status !== 'open') {
            return;
        }

        Contractor::where('is_frozen', false)
            ->chunkById(500, fn ($contractors) => NotificationFacade::send(
                $contractors,
                new NewTenderPublishedNotification($tender),
            ));
    }

    /**
     * أقرب موعد نهائي مسموح: بداية الغد بالتوقيت المحلي (app.local_timezone) محوَّلاً لتوقيت
     * التطبيق (UTC) — "بداية الغد" بتوقيت UTC وحده كانت سترفض عطاءً ينتهي غداً فجراً محلياً.
     */
    public static function minDeadline(): Carbon
    {
        return now(config('app.local_timezone'))->addDay()->startOfDay()->setTimezone(config('app.timezone'));
    }

    /**
     * الواجهة ترسل الموعد بصيغة ISO مع المنطقة الزمنية (…Z). عمود datetime يحفظ الساعة كما هي
     * ويُسقط الإزاحة، فيُحوَّل أولاً لتوقيت التطبيق وإلا انزاحت الساعة المحفوظة بفرق التوقيت.
     */
    private function normalizeDeadline(string $value): Carbon
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }

    /** رقم مرجعي بصيغة TND-<سنة>-<رقم العطاء بـ3 خانات> — يُولَّد مرة واحدة عند الإنشاء */
    private function generateReferenceNumber(Tender $tender): string
    {
        return 'TND-' . $tender->created_at->year . '-' . str_pad((string) $tender->id, 3, '0', STR_PAD_LEFT);
    }

    // GET /api/tenders/{id}
    public function show(Tender $tender)
    {
        return $this->success($tender->load('attachments')->toArray());
    }

    // PATCH /api/tenders/{id}
    public function update(Request $request, Tender $tender)
    {
        $validated = $request->validate([
            'title'              => 'sometimes|string|max:255',
            'issuing_entity'     => 'nullable|string|max:255',
            'description'        => 'nullable|string',
            'union_notes'        => 'nullable|string',
            // التصنيف الحالي للعطاء مقبول حتى لو عُطِّل لاحقاً — وإلا تعذّر حفظ أي حقل آخر فيه
            'category'           => ['nullable', Rule::in(array_filter([...TenderCategory::activeNames(), $tender->category]))],
            'deadline'           => 'nullable|date',
            'status'             => 'nullable|in:open,closed,cancelled',
            'submission_types'   => 'nullable|array',
            'submission_types.*' => 'in:email,phone,file',
            'submission_email'   => 'nullable|email|max:255',
            'submission_phone'   => 'nullable|string|max:20',
            'submission_file'    => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            'external_url'       => 'nullable|url|max:500',
        ]);

        // موعد جديد فعلاً (لا مجرد إعادة إرسال القيمة المحفوظة) يخضع لنفس حدّ الإنشاء
        if (! empty($validated['deadline'])) {
            $validated['deadline'] = $this->normalizeDeadline($validated['deadline']);
        }
        if (! empty($validated['deadline'])
            && ! ($tender->deadline && $validated['deadline']->equalTo($tender->deadline))
            && $validated['deadline']->lt(self::minDeadline())) {
            throw ValidationException::withMessages([
                'deadline' => 'يجب أن يكون آخر موعد للتقديم بتاريخ الغد أو بعده.',
            ]);
        }

        // الملف المحفوظ لا يُمسّ ما لم يُرفع بديل — غياب submission_file بالطلب يعني "أبقِه"
        $oldSubmissionFile = $tender->getRawOriginal('submission_file');
        unset($validated['submission_file']);

        if ($request->hasFile('submission_file')) {
            $validated['submission_file'] = $request->file('submission_file')
                ->store('tenders/files', 'public');
        }

        if (isset($validated['submission_types'])) {
            $validated['submission_types'] = array_values(array_filter($validated['submission_types']));
        }

        $tender->update($validated);

        if (isset($validated['submission_file']) && $oldSubmissionFile && ! str_starts_with($oldSubmissionFile, 'http')) {
            Storage::disk('public')->delete($oldSubmissionFile);
        }

        return $this->success($tender->fresh()->toArray(), 'تم تحديث العطاء بنجاح.');
    }

    // DELETE /api/tenders/{id}
    public function destroy(Tender $tender)
    {
        $tender->delete();

        return $this->success(message: 'تم حذف العطاء بنجاح.');
    }

    /**
     * فلاتر مشتركة بين القائمة الإدارية والعامة:
     * search, status, category, updated_from, updated_to, sort
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('updated_from')) {
            $query->whereDate('updated_at', '>=', $request->date('updated_from'));
        }
        if ($request->filled('updated_to')) {
            $query->whereDate('updated_at', '<=', $request->date('updated_to'));
        }
        // "تاريخ نشر العطاء" بمودال التصفية (شاشة العطاءات، تطبيق المقاول)
        if ($request->filled('created_from')) {
            $query->whereDate('published_at', '>=', $request->date('created_from'));
        }
        if ($request->filled('created_to')) {
            $query->whereDate('published_at', '<=', $request->date('created_to'));
        }
        if ($request->filled('deadline_from')) {
            $query->whereDate('deadline', '>=', $request->date('deadline_from'));
        }
        if ($request->filled('deadline_to')) {
            $query->whereDate('deadline', '<=', $request->date('deadline_to'));
        }

        // فلتر "حالة العطاء" بمودال التصفية — أي عطاء يطابق واحدة من الحالات المفعّلة (new/updated/closing_soon)
        if ($request->filled('states')) {
            $states = array_intersect((array) $request->input('states'), ['new', 'updated', 'closing_soon']);
            if ($states) {
                $query->where(function (Builder $q) use ($states) {
                    if (in_array('new', $states, true)) {
                        $q->orWhere('created_at', '>=', now()->subHours(48));
                    }
                    if (in_array('updated', $states, true)) {
                        $q->orWhere(function (Builder $qu) {
                            $qu->where('updated_at', '>=', now()->subHours(48))
                                ->whereColumn('updated_at', '>', 'created_at');
                        });
                    }
                    if (in_array('closing_soon', $states, true)) {
                        // endOfDay() على الحد الأعلى لازم بعد صيرورة deadline وقتاً كاملاً (REQ-07 #2)
                        // وإلا عطاء بآخر يوم بالنافذة بعد منتصف الليل يسقط خارجها خطأً.
                        $q->orWhereBetween('deadline', [now(), now()->addDays(4)->endOfDay()]);
                    }
                });
            }
        }

        // فعّالة/مؤرشفة (REQ-09) — archived_at يُضبط تلقائياً بأمر tenders:archive المجدول يومياً
        if ($request->input('scope') === 'active') {
            $query->whereNull('archived_at');
        } elseif ($request->input('scope') === 'archived') {
            $query->whereNotNull('archived_at');
        }

        // فلترة "مجالاتي" (REQ-13) — فقط ضمن سياق مقاول موثَّق
        if ($request->input('scope') === 'my_specialties' && $request->user() instanceof Contractor) {
            $specialties = $request->user()->specialties ?? [];
            $query->whereIn('category', $specialties ?: ['__none__']);
        }

        // toggle "العطاءات المهتم بها فقط" بمودال التصفية — بديل داخل applyFilters لمسار bookmarked المخصَّص
        if ($request->boolean('bookmarked') && $request->user() instanceof Contractor) {
            $bookmarkedIds = $request->user()->bookmarkedTenders()->pluck('tenders.id');
            $query->whereIn('tenders.id', $bookmarkedIds->isNotEmpty() ? $bookmarkedIds : ['__none__']);
        }

        return match ($request->input('sort')) {
            'oldest'        => $query->oldest(),
            'deadline_asc'  => $query->orderByRaw('deadline IS NULL, deadline asc'),
            'deadline_desc' => $query->orderByRaw('deadline IS NULL, deadline desc'),
            'updated_asc'   => $query->orderBy('updated_at'),
            'updated_desc'  => $query->orderByDesc('updated_at'),
            'budget_asc'    => $query->orderByRaw('budget IS NULL, budget asc'),
            'budget_desc'   => $query->orderByRaw('budget IS NULL, budget desc'),
            default         => $query->latest(),
        };
    }

    private function perPage(Request $request): int
    {
        // سقف أعلى (بدل 100) يسمح بتصدير كشف Excel كامل من لوحة التحكم بدون تقسيم صفحات
        return min($request->integer('per_page', 15), 1000);
    }

    // شكل العطاء المعروض للعامة/للمقاول — بدون created_by
    // union_notes ملاحظات داخلية موجّهة للمقاولين تحديداً (شاشة تفاصيل العطاء بالتطبيق) —
    // ما بتظهر بـ tenders-public (زوار الموقع غير المسجّلين)
    // $bookmarkedIds: قائمة IDs عطاءات المقاول المحفوظة (لتعليم is_bookmarked) — اختياري خارج سياق المقاول
    private function formatPublic(Tender $t, ?\Illuminate\Support\Collection $bookmarkedIds = null, bool $forContractor = false): array
    {
        return [
            'id'                  => $t->id,
            'reference_number'    => $t->reference_number,
            'title'               => $t->title,
            'issuing_entity'      => $t->issuing_entity,
            'description'         => $t->description,
            'union_notes'         => $forContractor ? $t->union_notes : null,
            'category'            => $t->category,
            // العطاء نفسه بلا صورة خاصة به — صورة تصنيفه الافتراضية (REQ-07 #7) إن وُجدت
            'category_image'      => $t->category
                ? ($this->categoryImages ??= TenderCategory::imageMap())[$t->category] ?? null
                : null,
            'budget'              => $t->budget,
            'deadline'            => $t->deadline?->toIso8601String(),
            'published_at'        => $t->published_at?->toDateString(),
            'status'              => $t->status,
            'display_status'      => $t->display_status,
            'display_status_label' => $t->display_status_label,
            'is_active'           => $t->is_active,
            'is_new'              => $t->is_new,
            'is_updated'          => $t->is_updated,
            'closing_soon'        => $t->closing_soon,
            'archived_at'         => $t->archived_at?->toDateString(),
            'submission_types'    => $t->submission_types,
            'submission_email'    => $t->submission_email,
            'submission_phone'    => $t->submission_phone,
            // $t->submission_file مُحلَّل مسبقاً لرابط كامل عبر getSubmissionFileAttribute() —
            // تمريره مجدداً عبر Storage::url() كان ينتج رابط تحميل مضاعفاً مكسوراً
            'submission_file_url' => $t->submission_file,
            'attachments'         => $t->attachments->map(fn ($a) => [
                'id'       => $a->id,
                'label'    => $a->label,
                'url'      => $a->file_url,
                'is_image' => $a->is_image,
            ])->values(),
            'external_url'        => $t->external_url,
            'is_bookmarked'       => $bookmarkedIds?->contains($t->id) ?? false,
            'created_at'          => $t->created_at,
            'updated_at'          => $t->updated_at,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  Admin — مرفقات العطاء المتعددة
    // ═════════════════════════════════════════════════════════════════════════

    // POST /api/v1/dashboard/tenders/{tender}/attachments
    public function storeAttachment(Request $request, Tender $tender)
    {
        $data = $request->validate([
            'file'  => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png,webp|max:10240',
            'label' => 'nullable|string|max:255',
        ]);

        $path = $request->file('file')->store('tenders/attachments', 'public');

        $attachment = $tender->attachments()->create([
            'file_path' => $path,
            'label'     => $data['label'] ?? null,
        ]);

        return $this->success([
            'id'       => $attachment->id,
            'label'    => $attachment->label,
            'url'      => $attachment->file_url,
            'is_image' => $attachment->is_image,
        ], 'تمت إضافة المرفق بنجاح.', 201);
    }

    // DELETE /api/v1/dashboard/tenders/{tender}/attachments/{attachment}
    public function destroyAttachment(Tender $tender, \App\Models\TenderAttachment $attachment)
    {
        if ($attachment->tender_id !== $tender->id) {
            return $this->error('المرفق لا يعود لهذا العطاء.', 422);
        }

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return $this->success(message: 'تم حذف المرفق.');
    }
}
