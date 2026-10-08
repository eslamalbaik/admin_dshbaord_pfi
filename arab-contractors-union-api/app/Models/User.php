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

    public function scopeSupervisors($query)
    {
        return $query->where('role', self::ROLE_SUPERVISOR);
    }

}
