<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Announcement;
use App\Models\AnnouncementAcknowledgement;
use App\Models\CertificateRequest;
use App\Models\Contractor;
use App\Models\Event;
use App\Models\Membership;
use App\Models\News;
use App\Models\Tender;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * الشاشة الرئيسية لتطبيق المقاول (الموبايل) — نداء واحد يجمع حالة العضوية،
 * الذمم المالية، الإحصائيات، وآخر التحديثات. الخدمات السريعة أزرار تنقّل ثابتة
 * داخل التطبيق نفسه، لا تحتاج API (تأكيد م. مؤمن عياض).
 * لا يوجد جدول "updates" منفصل — كل شيء يُقرأ حياً من الجداول الأساسية
 * (عطاءات/أخبار/ذمم/عضويات) في كل طلب.
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

        $lastDue = $contractor->dues()->latest('created_at')->first();

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
                'description' => $lastDue->description . ($lastDue->year ? " ({$lastDue->year})" : ''),
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
    //  دمج المصادر الأربعة (عطاءات/أخبار/مالية/عضوية) في تغذية واحدة مرتّبة بالأحدث
    // ─────────────────────────────────────────────────────────────────────────
    private function buildFeed(Contractor $contractor): Collection
    {
        return $this->tenderUpdates()
            ->concat($this->newsUpdates())
            ->concat($this->announcementUpdates($contractor))
            ->concat($this->financeUpdates($contractor))
            ->concat($this->penaltyUpdates($contractor))
            ->concat($this->memberUpdates($contractor))
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
            if ($item['rejected'] ?? false)         $badges[] = 'مرفوض';

            return [
                'type'         => $item['type'],
                'reference_id' => $item['reference_id'],
                'title'        => $item['title'],
                'subtitle'     => $item['subtitle'],
                'badges'       => $badges,
                'priority'     => $item['priority'],
                'created_at'   => $item['created_at']->toIso8601String(),
            ];
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

    // تعميمات مثبّتة (is_pinned) — بادج "غير مقروء" حتى يُقرّها المقاول (REQ-20)
    private function announcementUpdates(Contractor $contractor): Collection
    {
        $acknowledgedIds = AnnouncementAcknowledgement::where('contractor_id', $contractor->id)->pluck('announcement_id');

        return Announcement::published()
            ->where('is_pinned', true)
            ->select(['id', 'title', 'image', 'published_at'])
            ->latest('published_at')
            ->limit(self::FEED_POOL_LIMIT)
            ->get()
            ->map(fn (Announcement $a) => [
                'type'         => 'circular',
                'reference_id' => $a->id,
                'title'        => $a->title,
                'subtitle'     => $acknowledgedIds->contains($a->id) ? null : 'بانتظار الإقرار بالقراءة',
                'has_attachment' => (bool) $a->image,
                'is_new'       => ! $acknowledgedIds->contains($a->id),
                'priority'     => $acknowledgedIds->contains($a->id) ? 'normal' : 'high',
                'created_at'   => $a->published_at,
            ]);
    }

    // نوع "Finance" = فواتير/ذمم المقاول فقط (خاصة به)
    private function financeUpdates(Contractor $contractor): Collection
    {
        return $contractor->dues()
            ->select(['id', 'contractor_id', 'description', 'year', 'amount_jod', 'paid_jod', 'status', 'due_date', 'created_at'])
            ->latest('created_at')
            ->limit(self::FEED_POOL_LIMIT)
            ->get()
            ->map(fn ($due) => [
                'type'         => 'finance',
                'reference_id' => $due->id,
                'title'        => $due->description . ($due->year ? " ({$due->year})" : ''),
                'subtitle'     => $due->status_label,
                'has_attachment' => false,
                'is_new'       => $due->created_at->gt(now()->subHours(self::NEW_BADGE_HOURS)),
                'priority'     => ($due->status !== 'paid' && $due->due_date && $due->due_date->isPast()) ? 'high' : 'normal',
                'created_at'   => $due->created_at,
            ]);
    }

    // نوع "penalty" = غرامات المقاول (كرت مستقل عن الذمم)
    private function penaltyUpdates(Contractor $contractor): Collection
    {
        return $contractor->penalties()
            ->where('status', '!=', 'rejected')
            ->latest('created_at')
            ->limit(self::FEED_POOL_LIMIT)
            ->get()
            ->map(fn ($penalty) => [
                'type'         => 'penalty',
                'reference_id' => $penalty->id,
                'title'        => 'غرامة: ' . ($penalty->reason ?: 'غرامة مالية'),
                'subtitle'     => $penalty->status_label . ($penalty->remaining > 0 ? ' — المتبقي ' . number_format($penalty->remaining, 2, '.', '') . ' د.أ' : ''),
                'has_attachment' => false,
                'is_new'       => $penalty->created_at->gt(now()->subHours(self::NEW_BADGE_HOURS)),
                'priority'     => $penalty->remaining > 0 ? 'high' : 'normal',
                'created_at'   => $penalty->created_at,
            ]);
    }

    // نوع "Member" = تغييرات العضوية + طلبات الشهادات/تغيير الاسم الخاصة بالمقاول
    private function memberUpdates(Contractor $contractor): Collection
    {
        $memberships = $contractor->memberships()
            ->select(['id', 'contractor_id', 'type', 'status', 'document_url', 'expires_at', 'updated_at'])
            ->latest('updated_at')
            ->limit(self::FEED_POOL_LIMIT)
            ->get()
            ->map(fn (Membership $m) => [
                'type'         => 'member',
                'reference_id' => $m->id,
                'title'        => 'عضوية ' . $m->type,
                'subtitle'     => $m->status,
                'has_attachment' => (bool) $m->document_url,
                'is_new'       => $m->updated_at->gt(now()->subHours(self::NEW_BADGE_HOURS)),
                'priority'     => $m->expiring_soon ? 'high' : 'normal',
                'created_at'   => $m->updated_at,
                'rejected'     => false,
            ]);

        $certificates = CertificateRequest::where('contractor_id', $contractor->id)
            ->select(['id', 'contractor_id', 'type', 'status', 'attachment', 'updated_at'])
            ->latest('updated_at')
            ->limit(self::FEED_POOL_LIMIT)
            ->get()
            ->map(fn (CertificateRequest $c) => [
                'type'         => 'member',
                'reference_id' => $c->id,
                'title'        => $c->type_label,
                'subtitle'     => $c->status_label,
                'has_attachment' => (bool) $c->attachment,
                'is_new'       => $c->updated_at->gt(now()->subHours(self::NEW_BADGE_HOURS)),
                'priority'     => $c->status === 'rejected' ? 'high' : 'normal',
                'created_at'   => $c->updated_at,
                'rejected'     => $c->status === 'rejected',
            ]);

        return $memberships->concat($certificates);
    }
}
