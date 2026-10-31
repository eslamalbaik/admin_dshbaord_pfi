<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, \Illuminate\Database\Eloquent\SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'permissions',
        'is_active',
        'created_by',
        'avatar',
        'phone',
        'study_emails_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public const ROLE_SUPERVISOR = 'supervisor';

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSupervisor(): bool
    {
        return $this->role === self::ROLE_SUPERVISOR;
    }

    /**
     * صلاحية على قسم من أقسام لوحة التحكم (انظر App\Support\DashboardPermissions).
     * الأدمن عنده كل شي؛ المشرف حسب قائمته؛ باقي الأدوار ما بتمر من هون أصلاً
     * (المحاسب بيضل على role:... كما هو).
     */
    public function hasDashboardPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->isSupervisor() || $this->is_active === false) {
            return false;
        }

        return in_array($permission, $this->permissions ?? [], true);
    }

    /** الصلاحيات الفعلية للواجهة: الأدمن ['*']، المشرف قائمته، غيرهم null (نظام الأدوار القديم) */
    public function dashboardPermissions(): ?array
    {
        if ($this->isAdmin()) {
            return ['*'];
        }

        return $this->isSupervisor() ? array_values($this->permissions ?? []) : null;
    }

    /**
     * مستلمو إشعارات لوحة التحكم لقسم (أو أكثر): كل أدمن، والأدوار الإضافية الممرّرة
     * (مثلاً المحاسب القديم على إشعارات المدفوعات)، وكل مشرف فعّال عنده «عرض» على أي
     * من الأقسام — يعني حساب "محاسب مالي" المنشأ من شاشة المشرفين بصلاحيات المالية
     * بيوصله إشعار الدفعة الجديدة بدل ما يروح للأدمن بس.
     *
     * @param  string|string[]  $sections  مثل 'finance.payments'
     * @param  string[]  $extraRoles
     */
    public static function dashboardRecipients(string|array $sections, array $extraRoles = []): \Illuminate\Support\Collection
    {
        $roles   = array_values(array_unique(array_merge(['admin'], $extraRoles)));
        $needed  = array_map(fn (string $s) => "{$s}.view", (array) $sections);

        return static::query()
            ->whereIn('role', [...$roles, self::ROLE_SUPERVISOR])
            ->get()
            ->filter(function (User $user) use ($roles, $needed) {
                if (in_array($user->role, $roles, true)) {
                    return true;
                }

                foreach ($needed as $permission) {
                    if ($user->hasDashboardPermission($permission)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    public function scopeSupervisors($query)
    {
        return $query->where('role', self::ROLE_SUPERVISOR);
    }

}
