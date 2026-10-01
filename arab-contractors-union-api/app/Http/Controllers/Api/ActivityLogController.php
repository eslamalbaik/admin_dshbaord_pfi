<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    use ApiResponseTrait;

    // GET /api/v1/dashboard/activity-logs — سجل النشاط الإداري (صفحة الإعدادات)
    public function index(Request $request)
    {
        $query = ActivityLog::query()->with('actor')->latest('created_at');

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

        $paginated->getCollection()->transform(fn ($log) => [
            'id'           => $log->id,
            'actor_name'   => $log->actor?->name,
            'actor_email'  => $log->actor?->email,
            'action'       => $log->action,
            'subject_type' => $log->subject_type ? class_basename($log->subject_type) : null,
            'subject_id'   => $log->subject_id,
            'meta'         => $log->meta,
            'created_at'   => $log->created_at,
        ]);

        return $this->paginated($paginated);
    }

    // GET /api/v1/dashboard/activity-logs/actions — قائمة الإجراءات المسجّلة فعلياً (لتعبئة فلتر الإجراء)
    public function actions()
    {
        return $this->success(
            ActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action')
        );
    }
}
