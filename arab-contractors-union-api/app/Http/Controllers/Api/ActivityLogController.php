<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ActivityLog;
use App\Models\Contractor;
use App\Support\CriticalEvents;
use App\Support\FinancialEvents;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    use ApiResponseTrait;

    // GET /api/v1/dashboard/activity-logs — سجل النشاط الإداري (صفحة الإعدادات)
    public function index(Request $request)
    {
        $query = ActivityLog::query()->with('actor')->latest('created_at');

        // سجل المحددات الهامة: ?critical=1
        if ($request->boolean('critical'))
            $query->where('is_critical', true);

        // سجل النشاط المالي: ?financial=1
        if ($request->boolean('financial'))
            FinancialEvents::scope($query);

        if ($request->filled('category')) {
            $actions = array_keys(array_filter(CriticalEvents::EVENTS, fn ($e) => $e['category'] === $request->category));
            $query->where('is_critical', true)->whereIn('action', $actions);
        }

        if ($request->filled('action'))
            $query->where('action', $request->action);

        if ($request->filled('actor_id'))
            $query->where('actor_id', $request->actor_id);

        if ($request->filled('from'))
            $query->where('created_at', '>=', $request->from);

        if ($request->filled('to'))
            $query->where('created_at', '<=', $request->to . ' 23:59:59');

        if ($request->filled('search')) {
            // morphTo (actor) ما بيدعم whereHas مباشرة — البحث باسم المستخدم بيصير عبر
            // subquery على جدول users مباشرة، بما إن actor دايماً App\Models\User هون.
            $q = $request->search;
            $query->where(function ($qb) use ($q) {
                $qb->where('action', 'like', "%{$q}%")
                   ->orWhere('subject_type', 'like', "%{$q}%")
                   ->orWhereIn('actor_id', function ($sub) use ($q) {
                       $sub->select('id')->from('users')->where('name', 'like', "%{$q}%");
                   });
            });
        }

        $perPage = (int) $request->get('per_page', 20);

        $paginated = $query->paginate($perPage);

        $contractors = $this->contractorsFor($paginated->getCollection());

        $paginated->getCollection()->transform(fn ($log) => [
            'id'           => $log->id,
            'actor_name'   => $log->actor?->name,
            'actor_email'  => $log->actor?->email,
            'action'       => $log->action,
            'subject_type' => $log->subject_type ? class_basename($log->subject_type) : null,
            'subject_id'   => $log->subject_id,
            'meta'         => $log->meta,
            'is_critical'  => $log->is_critical,
            'is_financial' => FinancialEvents::isFinancial($log->action),
            'contractor'   => $contractors[$this->contractorIdOf($log)] ?? null,
            'critical'     => $log->is_critical ? $this->criticalDetails($log) : null,
            'created_at'   => $log->created_at,
        ]);

        return $this->paginated($paginated);
    }

    // GET /api/v1/dashboard/activity-logs/actions — قائمة الإجراءات المسجّلة فعلياً (لتعبئة فلتر الإجراء)
    // ?critical=1 بيرجّع إجراءات سجل المحددات الهامة فقط
    public function actions(Request $request)
    {
        return $this->success(
            ActivityLog::query()
                ->when($request->boolean('critical'), fn ($q) => $q->where('is_critical', true))
                ->when($request->boolean('financial'), fn ($q) => FinancialEvents::scope($q))
                ->select('action')->distinct()->orderBy('action')->pluck('action')
        );
    }

    // GET /api/v1/dashboard/activity-logs/critical-summary — عدد أحداث المحددات الهامة لكل فئة
    public function criticalSummary()
    {
        $counts = ActivityLog::query()
            ->where('is_critical', true)
            ->selectRaw('action, COUNT(*) as c')
            ->groupBy('action')
            ->pluck('c', 'action');

        $categories = collect(CriticalEvents::CATEGORIES)->map(fn ($label, $key) => [
            'key'   => $key,
            'label' => $label,
            'count' => (int) collect(CriticalEvents::EVENTS)
                ->filter(fn ($e) => $e['category'] === $key)
                ->keys()
                ->sum(fn ($action) => $counts[$action] ?? 0),
        ])->values();

        return $this->success([
            'total'      => (int) $counts->sum(),
            'categories' => $categories,
        ]);
    }

    // GET /api/v1/dashboard/activity-logs/financial-summary — عدد أحداث سجل النشاط المالي (للعدّاد بجانب التبويب)
    public function financialSummary()
    {
        return $this->success([
            'total' => FinancialEvents::scope(ActivityLog::query())->count(),
        ]);
    }

    /**
     * رقم المقاول المرتبط بالحدث: من الـ meta، أو العنصر نفسه لو كان مقاولاً، أو
     * contractor_id على العنصر المتأثر (دفعة، ذمة، غرامة...). الأحداث القديمة ما كانت
     * تخزّن اسم المقاول، فبهيك بيظهر اسمه ورقم عضويته بدل المعرّف الخام.
     */
    private function contractorIdOf(ActivityLog $log): ?int
    {
        $meta = $log->meta ?? [];

        if (! empty($meta['contractor_id']) && is_numeric($meta['contractor_id']))
            return (int) $meta['contractor_id'];

        if ($log->subject_type === Contractor::class && $log->subject_id)
            return (int) $log->subject_id;

        $subjectContractor = $log->relationLoaded('subject') ? $log->subject?->getAttribute('contractor_id') : null;

        return is_numeric($subjectContractor) ? (int) $subjectContractor : null;
    }

    /** @return array<int, array{id: int, name: string|null, membership_number: string|null}> */
    private function contractorsFor($logs): array
    {
        // العناصر المتأثرة اللي بتحمل contractor_id (بدون المقاول نفسه): نحمّلها دفعة وحدة لكل نوع
        $logs->filter(fn ($log) => $log->subject_type && $log->subject_type !== Contractor::class
                && empty(($log->meta ?? [])['contractor_id'])
                && class_exists($log->subject_type))
            ->groupBy('subject_type')
            ->each(function ($group, $type) {
                $model = new $type;
                if (! \Illuminate\Support\Facades\Schema::hasColumn($model->getTable(), 'contractor_id'))
                    return;

                $subjects = $type::query()->whereKey($group->pluck('subject_id')->filter()->unique())
                    ->get([$model->getKeyName(), 'contractor_id'])->keyBy($model->getKeyName());

                $group->each(fn ($log) => $log->setRelation('subject', $subjects[$log->subject_id] ?? null));
            });

        $ids = $logs->map(fn ($log) => $this->contractorIdOf($log))->filter()->unique()->values();
        if ($ids->isEmpty())
            return [];

        return Contractor::query()->whereKey($ids)->get(['id', 'name', 'membership_number'])
            ->mapWithKeys(fn ($c) => [$c->id => ['id' => $c->id, 'name' => $c->name, 'membership_number' => $c->membership_number]])
            ->all();
    }

    private function criticalDetails(ActivityLog $log): array
    {
        $definition = CriticalEvents::definition($log->action);
        $meta       = $log->meta ?? [];

        return [
            'label'          => $definition['label'] ?? $log->action,
            'category'       => $definition['category'] ?? null,
            'category_label' => isset($definition['category']) ? CriticalEvents::CATEGORIES[$definition['category']] : null,
            'changes'        => CriticalEvents::changes($log->action, $meta),
            'reason'         => $meta['reason'] ?? null,
        ];
    }
}
