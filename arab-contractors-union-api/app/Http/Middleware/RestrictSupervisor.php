<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * حماية افتراضية للمشرفين على كل مسارات لوحة التحكم: المشرف المعطّل بينطرد، والمشرف
 * الفعّال بيوصل فقط للمسارات اللي عليها perm:... (وهي اللي بتفحص صلاحيته) أو لمساراته
 * الشخصية (بياناته، تسجيل الخروج، إشعاراته). أي route جديد بدون perm مقفول على المشرف
 * تلقائياً بدل ما ينفتح بالغلط.
 *
 * الأدوار التانية (أدمن، محاسب...) ما بتتأثر.
 */
class RestrictSupervisor
{
    /** مسارات شخصية مسموحة لأي مشرف فعّال (بدون /api/v1) */
    private const PERSONAL_ROUTES = [
        'user',
        'auth/me',
        'auth/logout',
        'notifications',
        'notifications/unread-count',
        'notifications/read',
        'notifications/{id}/mark-as-read',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isSupervisor()) {
            return $next($request);
        }

        if ($user->is_active === false) {
            $user->tokens()->delete();

            return response()->json([
                'status'       => false,
                'status_code'  => 403,
                'message'      => 'تم تعطيل حسابك. تواصل مع مدير النظام.',
                'error_code'   => 'account_disabled',
                'force_logout' => true,
            ], 403);
        }

        $uri = preg_replace('#^api/v1/#', '', $request->route()?->uri() ?? '');

        if (CheckPermission::routeIsGuarded($request) || in_array($uri, self::PERSONAL_ROUTES, true)) {
            return $next($request);
        }

        return response()->json([
            'status'      => false,
            'status_code' => 403,
            'message'     => 'ليس لديك صلاحية للوصول لهذا القسم.',
            'error_code'  => 'permission_denied',
        ], 403);
    }
}
