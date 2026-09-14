<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user() || ! in_array($request->user()->role, $roles, true)) {
            $user = $request->user();
            
            \App\Services\AuditLogService::recordFailedAttempt(
                actor: $user,
                endpoint: $request->fullUrl(),
                reason: 'Missing required role(s)',
                meta: ['required_roles' => $roles, 'ip' => $request->ip()]
            );

            return response()->json(['message' => 'Forbidden. Access denied.'], 403);
        }

        return $next($request);
    }
}
