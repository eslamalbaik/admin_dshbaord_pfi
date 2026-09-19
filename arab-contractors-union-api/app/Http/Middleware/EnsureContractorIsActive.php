<?php

namespace App\Http\Middleware;

use App\Models\Contractor;
use App\Support\ApiMessages;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * فحص المستخدم النشط عند كل طلب محمي:
 * إذا جُمّد حساب المقاول من لوحة التحكم بعد تسجيل دخوله، تُحذف كل توكناته
 * فوراً (تسجيل خروج إجباري) ويُرجَع 403 مع force_logout=true حتى يمسح
 * التطبيق الجلسة المحلية ويعيده لشاشة الدخول.
 *
 * status=suspended لا يقفل الحساب — فقط يمنع تجديد العضوية
 * (راجع ContractorRequirements::renewalBlockers). is_frozen وحده يقفل الدخول.
 */
class EnsureContractorIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof Contractor && $user->is_frozen) {
            return $this->forceLogout($user, ApiMessages::ACCOUNT_FROZEN, 'account_frozen');
        }

        return $next($request);
    }

    private function forceLogout(Contractor $contractor, string $message, string $errorKey): Response
    {
        // إبطال كل الجلسات — لن ينفع أي توكن قديم بعد الآن
        $contractor->tokens()->delete();

        return response()->json([
            'status'       => false,
            'message'      => $message,
            'status_code'  => 403,
            'error'        => $errorKey,
            'force_logout' => true,
        ], 403);
    }
}
