<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Announcement;
use App\Models\AnnouncementAcknowledgement;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\LegalFile;
use App\Models\LegalFileCategory;
use App\Models\News;
use App\Models\Tender;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * الشاشة الرئيسية لتطبيق المقاول (الموبايل) — نداء واحد يجمع حالة العضوية،
 * الذمم المالية، الإحصائيات، وآخر التحديثات. الخدمات السريعة أزرار تنقّل ثابتة
 * داخل التطبيق نفسه، لا تحتاج API (تأكيد م. مؤمن عياض).
 * لا يوجد جدول "updates" منفصل — كل شيء يُقرأ حياً من الجداول الأساسية
 * (عطاءات/فعاليات/أخبار/تعاميم/مكتبة قانونية) في كل طلب.
 */
class ContractorHomeController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private \App\Services\MembershipStatusService $membershipStatusService
    ) {}

    private const NEW_TENDERS_WINDOW_DAYS = 7;
    private const NEW_BADGE_HOURS         = 24;
    private const FEED_POOL_LIMIT         = 30; // عدد السجلات المجلوبة من كل مصدر قبل الدمج والترتيب
    private const HOME_UPDATES_LIMIT      = 5;
    private const UPDATES_PER_PAGE        = 20;
    private const CIRCULAR_SUBTITLE_LIMIT = 150; // طول سطر نص التعميم بالتغذية

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/v1/contractor/home
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $contractor = $request->user();

        // يُبنى مرة واحدة — النسخة السابقة كانت تستدعي buildFeed() داخل take() مباشرة،
        // فإضافة العدّ الكلي كانت ستعيد بناء الخلاصة (7 استعلامات) مرة ثانية بلا داعٍ.
        $feed = $this->buildFeed($contractor);

        // يُحسب مرة واحدة ويُمرَّر لكلا البطاقتين. الرصيد الصافي (نفس صفحة الأرصدة و
        // contractor/balance): الذمم والغرامات ناقص الأرصدة والدفعات السابقة، مش إجمالي الذمة.
        $balance = app(ContractorBalanceController::class)->snapshot($contractor->id);

        return $this->success([
            'contractor'                 => $this->contractorCard($contractor),
            'membership'                 => $this->membershipStatus($contractor),
            'financial'                  => $this->financialSummary($contractor, $balance),
            'stats'                      => $this->statsCard($contractor),
            'cta_certificate'            => $this->ctaCertificate($contractor, $balance),
            'unread_notifications_count' => $contractor->unreadNotifications()->count(),
            'latest_updates'             => $this->presentFeed($feed->take(self::HOME_UPDATES_LIMIT)),
            // إشارة زر "عرض المزيد" — التطبيق يحوّل بها إلى contractor/home/updates.
            // ملاحظة: buildFeed() محدودة بـ FEED_POOL_LIMIT لكل مصدر، فالعدّ سقفه العملي
            // هو حجم التجمّع لا العدد الكلي في قاعدة البيانات — وهو ما تعرضه شاشة "عرض الكل" نفسها.
            'latest_updates_total'       => $feed->count(),
            'has_more'                   => $feed->count() > self::HOME_UPDATES_LIMIT,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  GET /api/v1/contractor/home/updates — سجل كامل بالتحديثات (شاشة "عرض الكل")
    // ─────────────────────────────────────────────────────────────────────────
    public function updates(Request $request)
    {
        $contractor = $request->user();
        $page       = max(1, $request->integer('page', 1));

        $feed  = $this->buildFeed($contractor);
        $items = $this->presentFeed($feed->forPage($page, self::UPDATES_PER_PAGE));

        $paginator = new LengthAwarePaginator(
            $items,
            $feed->count(),
            self::UPDATES_PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return $this->paginated($paginator);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  بطاقة المقاول (اسم، شعار، رقم عضوية)
    // ─────────────────────────────────────────────────────────────────────────
    private function contractorCard(Contractor $contractor): array
    {
        return [
            'id'                     => $contractor->id,
            'name'                   => $contractor->name,
            'membership_number'      => $contractor->membership_number,
            'logo'                   => $contractor->logo
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($contractor->logo)
                : null,
            'profile_data_complete'  => $contractor->profile_data_complete,
            'missing_profile_fields' => $contractor->missing_profile_fields,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  حالة العضوية — badge أخضر فقط إن كانت نشطة ومسدَّدة الاشتراك السنوي
    // ─────────────────────────────────────────────────────────────────────────
    private function membershipStatus(Contractor $contractor): array
    {
        $status = $this->membershipStatusService->getSubscriptionStatus($contractor);

        return [
            'status'         => $contractor->status,
            'badge'          => $status['badge'],
            'expires_at'     => $status['expires_at'],
            'expiring_soon'  => $status['expiring_soon'],
            'days_remaining' => $status['days_remaining'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  الذمم المالية — نفس حسبة "ما عليه" المستخدمة في شاشة الملف المالي
    // ─────────────────────────────────────────────────────────────────────────
    private function financialSummary(Contractor $contractor, array $balance): array
    {
        $hasOverdue = $contractor->dues()
            ->outstanding()
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->exists();

        // قيد جبر الكسور مش فاتورة — ما يطلع كـ"آخر فاتورة" بالرئيسية
        $lastDue = $contractor->dues()->where(fn ($q) => $q->whereNull('notes')->orWhere('notes', '!=', \App\Services\BalanceRoundingService::TAG))
            ->latest('created_at')->first();

        return [
            // المتبقي المطلوب بعد خصم الرصيد والدفعات السابقة (صفر لو ما عليه شي) — كان إجمالي الذمم
            'balance'         => number_format($balance['amount_due_jod'], 2, '.', ''),
            'amount_due_jod'  => $balance['amount_due_jod'],
            // الصافي بإشارته: موجب = له رصيد، سالب = عليه
            'net_jod'         => $balance['net_jod'],
            'credit_jod'      => $balance['credit_jod'],
            'dues_jod'        => $balance['dues_jod'],
            'penalties_jod'   => $balance['penalties_jod'],
            'position'        => $balance['position'],
            'currency'        => 'JOD',
            'pending_payments_jod' => round((float) $contractor->payments()->where('status', 'pending')->sum('amount'), 2),
            'has_overdue'     => $hasOverdue,
            'last_invoice'    => $lastDue ? [
                'id'          => $lastDue->id,
                'description' => $lastDue->title . ($lastDue->year ? " ({$lastDue->year})" : ''),
                'amount_jod'  => $lastDue->amount_jod,
                'status'      => $lastDue->status,
                'due_date'    => $lastDue->due_date?->toDateString(),
            ] : null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  الإحصائيات الثلاثة: إعلانات منشورة / آليات مضافة / عطاءات جديدة
    // ─────────────────────────────────────────────────────────────────────────
    private function statsCard(Contractor $contractor): array
    {
        return [
            'events_count'         => Event::published()
                ->where('event_date', '>=', now())
                ->count(),
            'announcements_count' => Announcement::published()->count(),
            'machinery_count'     => $contractor->equipment()->count(),
            'new_tenders_count'   => Tender::where('status', 'open')
                ->where('created_at', '>=', now()->subDays(self::NEW_TENDERS_WINDOW_DAYS))
                ->count(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  زر "طلب شهادة انتساب" — يظهر دائماً، لكن يُنبَّه المستخدم بالذمم قبل المتابعة
    // ─────────────────────────────────────────────────────────────────────────
    private function ctaCertificate(Contractor $contractor, array $balance): array
    {
        $issues = \App\Support\ContractorRequirements::issues($contractor);

        return [
            'show'               => true,
            'has_pending_dues'   => count($issues) > 0,
            'outstanding_amount' => $balance['amount_due_jod'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  "آخر التحديثات" = محتوى الاتحاد العام فقط: عطاءات، فعاليات، أخبار، تعاميم،
    //  مكتبة قانونية — مرتّبة بالأحدث. الأمور المالية (ذمم/غرامات/دفعات) وشهادات
    //  العضوية والتصنيف ما بتطلع هون (قرار م. إسلام 2026-10-13)؛ بتوصل كإشعارات وبشاشاتها.
    // ─────────────────────────────────────────────────────────────────────────
    private function buildFeed(Contractor $contractor): Collection
    {
        return $this->tenderUpdates()
            ->concat($this->eventUpdates())
            ->concat($this->newsUpdates())
            ->concat($this->announcementUpdates($contractor))
            ->concat($this->legalFileUpdates())
            // created_at وحده غير كافٍ كمفتاح ترتيب — سجلات كتيرة بنفس الثانية ممكنة (استيراد جماعي مثلاً)
            ->sort(fn ($a, $b) => [$b['created_at'], $b['reference_id']] <=> [$a['created_at'], $a['reference_id']])
            ->values();
    }

    // يحوّل عناصر التغذية الخام إلى الشكل النهائي (badges + timestamp) قبل إرجاعها
    private function presentFeed(Collection $items): Collection
    {
        return $items->map(function (array $item) {
            $badges = [];
            if ($item['has_attachment'] ?? false) $badges[] = 'ملحق';
            if ($item['is_new'] ?? false)          $badges[] = 'جديد';

            return [
                'type'         => $item['type'],
                'reference_id' => $item['reference_id'],
                'title'        => $item['title'],
                'subtitle'     => $item['subtitle'],
                'badges'       => $badges,
                'priority'     => $item['priority'],
                'created_at'   => $item['created_at']->toIso8601String(),
                'url'          => $item['url'] ?? null,
            ] + (isset($item['details']) ? ['details' => $item['details']] : []);
        })->values();
    }

    private function tenderUpdates(): Collection
    {
        return Tender::where('status', 'open')
            ->select(['id', 'title', 'category', 'submission_file', 'created_at'])
            ->latest()
            ->limit(self::FEED_POOL_LIMIT)
            ->get()
            ->map(fn (Tender $t) => [
                'type'         => 'tender',
                'reference_id' => $t->id,
                'title'        => $t->title,
                'subtitle'     => $t->category,
                'has_attachment' => (bool) $t->submission_file,
                'is_new'       => $t->created_at->gt(now()->subHours(self::NEW_BADGE_HOURS)),
                'priority'     => 'normal',
                'created_at'   => $t->created_at,
            ]);
    }

    private function newsUpdates(): Collection
    {
        return News::published()
            ->select(['id', 'title', 'excerpt', 'image', 'published_at'])
            ->latest('published_at')
            ->limit(self::FEED_POOL_LIMIT)
            ->get()
            ->map(fn (News $n) => [
                'type'         => 'news',
                'reference_id' => $n->id,
                'title'        => $n->title,
                // category صار محذوفاً من الأخبار (REQ-10 #1) — كانت قيمته 'news' دائماً
                // بلا فائدة أصلاً؛ المقتطف أكثر فائدة كعنوان فرعي بعنصر التغذية
                'subtitle'     => $n->excerpt,
                'has_attachment' => (bool) $n->image,
                'is_new'       => $n->published_at->gt(now()->subHours(self::NEW_BADGE_HOURS)),
                'priority'     => 'normal',
                'created_at'   => $n->published_at,
            ]);
    }

    // كل التعاميم المنشورة؛ العاجلة (is_pinned) عليها بادج "جديد" وأولوية عالية حتى يُقرّها المقاول (REQ-20)
    private function announcementUpdates(Contractor $contractor): Collection
    {
        $acknowledgedIds = AnnouncementAcknowledgement::where('contractor_id', $contractor->id)->pluck('announcement_id');

        return Announcement::published()
            ->select(['id', 'title', 'number', 'category', 'body', 'image', 'attachment', 'external_url', 'is_pinned', 'published_at'])
            ->latest('published_at')
            ->limit(self::FEED_POOL_LIMIT)
            ->get()
            ->map(function (Announcement $a) use ($acknowledgedIds) {
                $awaitingAck = $a->is_pinned && ! $acknowledgedIds->contains($a->id);

                return [
                    'type'         => 'circular',
                    'reference_id' => $a->id,
                    'title'        => $a->title,
                    // نص التعميم نفسه كسطر فرعي (طلب eslam 2026-10-25) بدل "بانتظار الإقرار بالقراءة"؛
                    // حالة الإقرار باقية بـ priority و details.requires_acknowledgement
                    'subtitle'     => $this->circularSubtitle($a->body),
                    'has_attachment' => (bool) $a->image,
                    'is_new'       => $awaitingAck || $a->published_at->gt(now()->subHours(self::NEW_BADGE_HOURS)),
                    'priority'     => $awaitingAck ? 'high' : 'normal',
                    'created_at'   => $a->published_at,
                    // التطبيق يعرض نافذة التعميم (Pop-up) من هذا العنصر مباشرة، فيحتاج النص كاملاً
                    // مش العنوان وحده — نفس حقول contractor/circulars/pending و announcements/{id}
                    'details'      => [
                        'number'                   => $a->number,
                        'category'                 => $a->category,
                        'body'                     => $a->body,
                        'image'                    => $a->image,
                        'attachment'               => $a->attachment,
                        'external_url'             => $a->external_url,
                        'is_pinned'                => $a->is_pinned,
                        'requires_acknowledgement' => $awaitingAck,
                        'acknowledged'             => $acknowledgedIds->contains($a->id),
                        'published_at'             => $a->published_at->toIso8601String(),
                    ],
                ];
            });
    }

    // نص عادي بلا HTML وبسطر واحد، مقصوص على CIRCULAR_SUBTITLE_LIMIT حرف؛ النص الكامل بـ details.body
    private function circularSubtitle(?string $body): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', (string) $body)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $text === '' ? null : Str::limit($text, self::CIRCULAR_SUBTITLE_LIMIT, '…');
    }

    private function eventUpdates(): Collection
    {
        return Event::published()
            ->select(['id', 'title', 'image', 'event_date', 'event_location', 'event_format', 'published_at'])
            ->latest('published_at')
            ->limit(self::FEED_POOL_LIMIT)
            ->get()
            ->map(fn (Event $e) => [
                'type'         => 'event',
                'reference_id' => $e->id,
                'title'        => $e->title,
                'subtitle'     => collect([
                    $e->event_date?->format('Y-m-d'),
                    $e->event_format === 'online' ? null : $e->event_location,
                ])->filter()->implode(' — ') ?: null,
                'has_attachment' => (bool) $e->image,
                'is_new'       => $e->published_at->gt(now()->subHours(self::NEW_BADGE_HOURS)),
                'priority'     => 'normal',
                'created_at'   => $e->published_at,
            ]);
    }

    // ملفات المكتبة القانونية المفعّلة — العنوان الفرعي اسم التصنيف
    private function legalFileUpdates(): Collection
    {
        $categoryLabels = LegalFileCategory::pluck('label', 'key');

        return LegalFile::where('is_active', true)
            ->select(['id', 'title', 'category', 'file_path', 'disk', 'created_at'])
            ->latest('created_at')
            ->limit(self::FEED_POOL_LIMIT)
            ->get()
            ->map(fn (LegalFile $f) => [
                'type'         => 'legal_file',
                'reference_id' => $f->id,
                'title'        => $f->title,
                'subtitle'     => $categoryLabels[$f->category] ?? null,
                'has_attachment' => (bool) $f->file_path,
                'is_new'       => $f->created_at->gt(now()->subHours(self::NEW_BADGE_HOURS)),
                'priority'     => 'normal',
                'created_at'   => $f->created_at,
                // ما في endpoint تفاصيل لملف قانوني بتطبيق المقاول — التطبيق يفتح الملف مباشرة
                'url'          => $f->file_path ? $f->url : null,
            ]);
    }
}
