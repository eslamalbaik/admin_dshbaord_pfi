<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\DashboardPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * perm:{قسم}[|{قسم بديل}...][,{إجراء}]
 *
 *   perm:finance.dues                 — الإجراء من الـ HTTP method (GET عرض، POST إضافة، PUT/PATCH تعديل، DELETE حذف)
 *   perm:finance.dues,update          — إجراء صريح (مثلاً POST .../settle هو تعديل مش إضافة)
 *   perm:contractors.list|finance.dues,view — بيكفي أي قسم منهم (قوائم بتستخدمها أكثر من صفحة)
 *
 * بينطبق على المشرفين فقط. الأدمن بيمر دايماً، وباقي الأدوار (المحاسب...) بتمر من هون
 * بدون تغيير وبيضل يحكمها role:... الموجود على نفس الـ route.
 */
class CheckPermission
{
    private const METHOD_ACTIONS = [
        'GET'    => DashboardPermissions::VIEW,
        'HEAD'   => DashboardPermissions::VIEW,
        'POST'   => DashboardPermissions::CREATE,
        'PUT'    => DashboardPermissions::UPDATE,
        'PATCH'  => DashboardPermissions::UPDATE,
        'DELETE' => DashboardPermissions::DELETE,
    ];

    public function handle(Request $request, Closure $next, string $sections, ?string $action = null): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isSupervisor()) {
            return $next($request);
        }

        $action ??= self::METHOD_ACTIONS[$request->method()] ?? DashboardPermissions::VIEW;

        foreach (explode('|', $sections) as $section) {
            if ($user->hasDashboardPermission("{$section}.{$action}")) {
                return $next($request);
            }
        }

        \App\Services\AuditLogService::recordFailedAttempt(
            actor: $user,
            endpoint: $request->fullUrl(),
            reason: 'Missing dashboard permission',
            meta: ['required_permission' => "{$sections}.{$action}", 'ip' => $request->ip()]
        );

        return response()->json([
            'status'      => false,
            'status_code' => 403,
            'message'     => 'ليس لديك صلاحية لتنفيذ هذا الإجراء.',
            'error_code'  => 'permission_denied',
        ], 403);
    }

    /** هل الـ route عليه perm:... (يعني صلاحيات المشرف بتتفحص عليه) */
    public static function routeIsGuarded(Request $request): bool
    {
        $route = $request->route();
        if (! $route) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'perm:')) {
                return true;
            }
        }

        return false;
    }
}
