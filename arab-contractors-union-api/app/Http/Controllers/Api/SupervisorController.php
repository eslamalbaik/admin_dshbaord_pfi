<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\DashboardPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * إدارة المشرفين (role = supervisor) وصلاحياتهم حسب أقسام لوحة التحكم — أدمن فقط.
 *
 * بيتعامل مع حسابات المشرفين فقط: ما بيقدر يعدّل أو يعطّل أدمن أو محاسب، ولا يرقّي
 * أي حساب لأدمن. كل إنشاء/تعديل بيتسجّل بسجل المحددات الهامة (CriticalEvents).
 */
class SupervisorController extends Controller
{
    use ApiResponseTrait;

    // GET /api/v1/dashboard/supervisors/permissions-catalog — الأقسام والإجراءات (مصفوفة الصلاحيات)
    public function catalog()
    {
        return $this->success(DashboardPermissions::catalog());
    }

    // GET /api/v1/dashboard/supervisors
    public function index(Request $request)
    {
        $query = User::supervisors()->latest('id');

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(fn ($qb) => $qb->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $paginated = $query->paginate((int) $request->get('per_page', 20));
        $paginated->getCollection()->transform(fn (User $user) => $this->format($user));

        return $this->paginated($paginated);
    }

    // GET /api/v1/dashboard/supervisors/{id}
    public function show(int $id)
    {
        return $this->success($this->format($this->find($id)));
    }

    // POST /api/v1/dashboard/supervisors
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone'         => 'nullable|string|max:30',
            'password'      => 'required|string|min:8|max:100',
            'is_active'     => 'sometimes|boolean',
            'permissions'   => 'present|array',
            'permissions.*' => 'string',
        ], [
            'email.unique' => 'هذا البريد مستخدم لحساب آخر.',
        ]);

        $user = User::create([
            'name'        => $data['name'],
            'email'       => strtolower(trim($data['email'])),
            'phone'       => $data['phone'] ?? null,
            'password'    => $data['password'],
            'role'        => User::ROLE_SUPERVISOR,
            'permissions' => DashboardPermissions::sanitize($data['permissions']),
            'is_active'   => $data['is_active'] ?? true,
            'created_by'  => $request->user()->id,
        ]);

        AuditLogService::recordCritical(
            $request->user(),
            'supervisor.created',
            $user,
            before: [],
            after: $this->auditSnapshot($user),
        );

        return $this->success($this->format($user), 'تم إنشاء المشرف بنجاح', 201);
    }

    // PUT /api/v1/dashboard/supervisors/{id} — البيانات والصلاحيات
    public function update(Request $request, int $id)
    {
        $user = $this->find($id);

        $data = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'email'         => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'         => 'nullable|string|max:30',
            'permissions'   => 'sometimes|array',
            'permissions.*' => 'string',
        ], [
            'email.unique' => 'هذا البريد مستخدم لحساب آخر.',
        ]);

        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }
        if (array_key_exists('permissions', $data)) {
            $data['permissions'] = DashboardPermissions::sanitize($data['permissions']);
        }

        $before = $this->auditSnapshot($user);
        $user->update($data);
        $after = $this->auditSnapshot($user->fresh());

        if ($before != $after) {
            AuditLogService::recordCritical(
                $request->user(),
                'supervisor.updated',
                $user,
                before: array_intersect_key($before, $data),
                after: array_intersect_key($after, $data),
            );
        }

        return $this->success($this->format($user->fresh()), 'تم تحديث بيانات المشرف بنجاح');
    }

    // PATCH /api/v1/dashboard/supervisors/{id}/status — تفعيل/تعطيل
    public function updateStatus(Request $request, int $id)
    {
        $user = $this->find($id);
        $data = $request->validate(['is_active' => 'required|boolean']);

        $wasActive = (bool) $user->is_active;
        $user->update(['is_active' => $data['is_active']]);

        // التعطيل بيطلّعه من كل الأجهزة فوراً
        if (! $data['is_active']) {
            $user->tokens()->delete();
        }

        if ($wasActive !== (bool) $data['is_active']) {
            AuditLogService::recordCritical(
                $request->user(),
                'supervisor.status_changed',
                $user,
                before: ['is_active' => $this->statusLabel($wasActive)],
                after: ['is_active' => $this->statusLabel($data['is_active'])],
            );
        }

        return $this->success(
            $this->format($user->fresh()),
            $data['is_active'] ? 'تم تفعيل حساب المشرف' : 'تم تعطيل حساب المشرف وتسجيل خروجه من كل الأجهزة'
        );
    }

    // POST /api/v1/dashboard/supervisors/{id}/reset-password
    public function resetPassword(Request $request, int $id)
    {
        $user = $this->find($id);
        $data = $request->validate(['password' => 'required|string|min:8|max:100']);

        $user->update(['password' => $data['password']]);
        $user->tokens()->delete();

        AuditLogService::recordCritical(
            $request->user(),
            'supervisor.password_reset',
            $user,
            before: [],
            after: ['password' => 'تمت إعادة التعيين'],
        );

        return $this->success(message: 'تم تعيين كلمة المرور الجديدة وتسجيل خروج المشرف من كل الأجهزة');
    }

    private function find(int $id): User
    {
        return User::supervisors()->findOrFail($id);
    }

    private function format(User $user): array
    {
        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'is_active'   => (bool) ($user->is_active ?? true),
            'permissions' => array_values($user->permissions ?? []),
            'created_at'  => $user->created_at,
            'updated_at'  => $user->updated_at,
        ];
    }

    /** قيم مقروءة لسجل المحددات الهامة (بدون مصفوفات خام) */
    private function auditSnapshot(User $user): array
    {
        return [
            'name'        => $user->name,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'is_active'   => $this->statusLabel((bool) ($user->is_active ?? true)),
            'permissions' => DashboardPermissions::describe($user->permissions),
        ];
    }

    private function statusLabel(bool $active): string
    {
        return $active ? 'فعال' : 'معطل';
    }
}
