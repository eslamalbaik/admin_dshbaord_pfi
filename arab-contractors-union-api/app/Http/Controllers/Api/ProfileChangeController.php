<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Contractor;
use App\Models\ContractorFieldHistory;
use App\Models\ProfileChange;
use App\Models\Setting;
use App\Services\ProfileChangeService;
use App\Support\ProfileReview;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * صفحة «طلبات تعديل الملفات» بلوحة التحكم + «سجل التغييرات» لكل مقاول.
 *
 * التبويبات:
 *   review   — بانتظار الاعتماد (pre_approval / pending)
 *   applied  — انطبّقت فوراً، بانتظار التأكيد (post_review / pending)
 *   done     — منتهية (اعتماد/رفض/تأكيد/تراجع)
 */
class ProfileChangeController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private ProfileChangeService $service)
    {
    }

    // GET dashboard/profile-changes?tab=review|applied|done&search=&page=
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'review');
        $search = trim((string) $request->query('search', ''));

        $query = ProfileChange::query()
            ->with(['contractor:id,name,membership_number,deleted_at', 'reviewer:id,name'])
            ->when($tab === 'review', fn ($q) => $q->pending()->where('mode', ProfileChange::MODE_PRE))
            ->when($tab === 'applied', fn ($q) => $q->pending()->where('mode', ProfileChange::MODE_POST))
            ->when($tab === 'done', fn ($q) => $q->whereIn('status', [
                ProfileChange::STATUS_APPROVED, ProfileChange::STATUS_REJECTED,
                ProfileChange::STATUS_CONFIRMED, ProfileChange::STATUS_REVERTED,
            ]))
            ->when($search !== '', fn ($q) => $q->whereHas('contractor', fn ($c) => $c
                ->where('name', 'like', "%{$search}%")
                ->orWhere('membership_number', 'like', "%{$search}%")));

        if ($tab === 'done') {
            $page = $query->orderByDesc('reviewed_at')->paginate((int) $request->query('per_page', 20));

            return $this->success([
                'data' => collect($page->items())->map(fn (ProfileChange $i) => $this->row($i))->values(),
                'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            ], 'تم جلب الطلبات.');
        }

        if ($tab === 'applied') {
            $items = $query->orderBy('created_at')->limit(500)->get();

            return $this->success([
                'data' => $items->map(fn (ProfileChange $i) => $this->row($i))->values(),
                'meta' => ['total' => $items->count()],
            ], 'تم جلب الطلبات.');
        }

        // review: سطر لكل مقاول (طلب واحد مجمّع)، الأقدم أولاً
        $groups = $query->orderBy('created_at')->get()->groupBy('contractor_id')
            ->map(fn (Collection $items) => $this->group($items))
            ->sortBy('submitted_at')
            ->values();

        return $this->success(['data' => $groups, 'meta' => ['total' => $groups->count()]], 'تم جلب الطلبات.');
    }

    // GET dashboard/profile-changes/summary — عدّادات التبويبات وشارة القائمة الجانبية
    public function summary()
    {
        $review = ProfileChange::pending()->where('mode', ProfileChange::MODE_PRE);
        $sla = (int) config('profile_review.sla_working_days');

        $overdue = (clone $review)->get(['contractor_id', 'created_at'])
            ->groupBy('contractor_id')
            ->filter(fn ($items) => ProfileReview::workingDaysSince($items->min('created_at')) >= $sla)
            ->count();

        return $this->success([
            'review_contractors' => (clone $review)->distinct()->count('contractor_id'),
            'review_fields'      => (clone $review)->count(),
            'applied'            => ProfileChange::pending()->where('mode', ProfileChange::MODE_POST)->count(),
            'overdue'            => $overdue,
            'sla_working_days'   => $sla,
            'enabled'            => ProfileReview::enabled(),
        ], 'تم.');
    }

    // GET dashboard/profile-changes/contractors/{contractor} — المقارنة جنب بعض
    public function show(Contractor $contractor)
    {
        $items = ProfileChange::where('contractor_id', $contractor->id)
            ->pending()
            ->where('mode', ProfileChange::MODE_PRE)
            ->orderBy('created_at')
            ->get();

        $applied = ProfileChange::where('contractor_id', $contractor->id)
            ->pending()
            ->where('mode', ProfileChange::MODE_POST)
            ->get();

        $recent = ProfileChange::where('contractor_id', $contractor->id)
            ->whereIn('status', [ProfileChange::STATUS_APPROVED, ProfileChange::STATUS_REJECTED, ProfileChange::STATUS_REVERTED])
            ->with('reviewer:id,name')
            ->orderByDesc('reviewed_at')
            ->limit(10)
            ->get();

        return $this->success([
            'contractor' => [
                'id'                => $contractor->id,
                'name'              => $contractor->name,
                'membership_number' => $contractor->membership_number,
                'status'            => $contractor->status,
            ],
            'groups'       => $this->grouped($items, $contractor),
            'applied'      => $applied->map(fn ($i) => $i->present($contractor))->values(),
            'recent'       => $recent->map(fn ($i) => $i->present($contractor))->values(),
            'group_labels' => ProfileReview::GROUP_LABELS,
            'reject_reasons' => [
                'المستند غير واضح أو غير مقروء',
                'المستند منتهي الصلاحية',
                'البيانات لا تطابق السجل الرسمي',
                'المستند لا يخص الشركة',
                'يرجى مراجعة الاتحاد لإتمام التعديل',
            ],
        ], 'تم جلب الطلب.');
    }

    // POST dashboard/profile-changes/contractors/{contractor}/decide
    public function decide(Request $request, Contractor $contractor)
    {
        $data = $request->validate([
            'decisions'          => 'required|array|min:1',
            'decisions.*.id'     => 'required|integer',
            'decisions.*.action' => 'required|in:approve,reject',
            'decisions.*.reason' => 'nullable|string|max:500|required_if:decisions.*.action,reject',
        ], [
            'decisions.*.reason.required_if' => 'سبب الرفض مطلوب لكل حقل مرفوض — بيوصل للمقاول عشان يصحّح.',
        ]);

        $result = $this->service->decide($contractor, $data['decisions'], $request->user());

        $message = match (true) {
            $result['approved'] && $result['rejected'] => "تم اعتماد {$result['approved']} ورفض {$result['rejected']}، ووصل المقاول إشعار بالنتيجة.",
            (bool) $result['approved'] => "تم اعتماد {$result['approved']} تعديل، ووصل المقاول إشعار.",
            (bool) $result['rejected'] => "تم رفض {$result['rejected']} تعديل، ووصل المقاول إشعار بالسبب.",
            default => 'لا توجد تعديلات بانتظار القرار — ممكن المقاول سحبها أو عدّلها.',
        };

        return $this->success($result, $message);
    }

    // POST dashboard/profile-changes/confirm {ids: []}
    public function confirm(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $count = $this->service->confirm($data['ids'], $request->user());

        return $this->success(['confirmed' => $count], "تم تأكيد {$count} تعديل.");
    }

    // POST dashboard/profile-changes/{profileChange}/revert {reason}
    public function revert(Request $request, ProfileChange $profileChange)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [
            'reason.required' => 'اكتب سبب التراجع — بيوصل للمقاول.',
        ]);

        $this->service->revert($profileChange, $request->user(), $data['reason']);

        return $this->success(null, 'تم التراجع وإرجاع القيمة السابقة، ووصل المقاول إشعار بالسبب.');
    }

    // PUT dashboard/profile-changes/settings {enabled} — أدمن فقط
    public function updateSettings(Request $request)
    {
        $data = $request->validate(['enabled' => 'required|boolean']);
        $before = ProfileReview::enabled();
        Setting::set(ProfileReview::SETTING_KEY, $data['enabled'] ? '1' : '0', 'contractors');

        \App\Services\AuditLogService::recordCritical(
            $request->user(),
            'settings.profile_review_toggled',
            null,
            ['enabled' => $before],
            ['enabled' => (bool) $data['enabled']],
        );

        return $this->success(['enabled' => ProfileReview::enabled()], $data['enabled']
            ? 'تم تفعيل مراجعة تعديلات الملفات.'
            : 'تم إيقاف المراجعة — تعديلات المقاولين صارت تنحفظ مباشرة.');
    }

    // GET contractors/{contractor}/field-history
    public function history(Request $request, Contractor $contractor)
    {
        $page = ContractorFieldHistory::where('contractor_id', $contractor->id)
            ->orderByDesc('id')
            ->paginate((int) $request->query('per_page', 30));

        $pending = ProfileChange::where('contractor_id', $contractor->id)->pending()->where('mode', ProfileChange::MODE_PRE)->count();

        return $this->success([
            'data'          => collect($page->items())->map(fn (ContractorFieldHistory $h) => $h->present())->values(),
            'meta'          => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            'pending_count' => $pending,
        ], 'تم جلب سجل التغييرات.');
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function row(ProfileChange $item): array
    {
        return array_merge($item->present(), [
            'contractor_id'     => $item->contractor_id,
            'contractor_name'   => $item->contractor?->name,
            'membership_number' => $item->contractor?->membership_number,
        ]);
    }

    private function group(Collection $items): array
    {
        $first = $items->first();
        $since = $items->min('created_at');
        $days = ProfileReview::workingDaysSince($since);

        return [
            'contractor_id'     => $first->contractor_id,
            'contractor_name'   => $first->contractor?->name,
            'membership_number' => $first->contractor?->membership_number,
            'fields_count'      => $items->count(),
            'documents_count'   => $items->where('kind', 'file')->count(),
            'labels'            => $items->map(fn ($i) => ProfileReview::label($i->field))->values(),
            'submitted_at'      => $since?->toISOString(),
            'last_update_at'    => $items->max('updated_at')?->toISOString(),
            'working_days'      => $days,
            'overdue'           => $days >= (int) config('profile_review.sla_working_days'),
        ];
    }

    private function grouped(Collection $items, Contractor $contractor): array
    {
        $out = [];
        foreach (array_keys(ProfileReview::GROUP_LABELS) as $group) {
            $rows = $items->filter(fn ($i) => ProfileReview::group($i->field) === $group)
                ->map(fn ($i) => $i->present($contractor))
                ->values();

            if ($rows->isNotEmpty()) {
                $out[] = ['key' => $group, 'label' => ProfileReview::GROUP_LABELS[$group], 'items' => $rows];
            }
        }

        return $out;
    }
}
