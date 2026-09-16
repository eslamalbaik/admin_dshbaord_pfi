<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Channel auth route lives under /api/broadcasting/auth, guarded the same way as the
    // rest of the API (auth:sanctum) — the default "web"-middleware route relies on session
    // auth, which doesn't apply here since the frontend authenticates with a bearer token.
    ->withBroadcasting(
        channels: __DIR__.'/../routes/channels.php',
        attributes: ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API-only app — never redirect unauthenticated requests, always return 401 JSON
        $middleware->redirectGuestsTo(fn() => null);

        // Security headers for all responses
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->validateCsrfTokens(except: [
            'login',
            'register',
            'logout',
            'api/v1/auth/forgot-password',
            'api/v1/auth/reset-password',
            'api/auth/google/exchange',
            'auth/google/redirect',
            'auth/google/callback',
        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'tenant' => \App\Http\Middleware\TenantMiddleware::class,
            'contractor.active' => \App\Http\Middleware\EnsureContractorIsActive::class,
        ]);
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\NoCacheHeaders::class,
            \App\Http\Middleware\SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // ─────────────────────────────────────────────────────────────────
        //  الاستجابة الموحّدة لكل أخطاء الـ API (REQ-06):
        //  { status, status_code, message, errors? } — حتى أخطاء 401/404/500
        // ─────────────────────────────────────────────────────────────────
        $unified = function (string $message, int $code, ?array $errors = null) {
            $body = [
                'status'      => false,
                'message'     => $message,
                'status_code' => $code,
            ];

            if ($errors) {
                $body['errors'] = $errors;
            }

            return response()->json($body, $code);
        };

        // 401 — غير مسجّل دخول / توكن منتهي
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) use ($unified) {
            if ($request->is('api/*')) {
                return $unified(\App\Support\ApiMessages::UNAUTHENTICATED, 401);
            }
        });

        // 422 — أخطاء التحقق: رسالة أول خطأ محدد + كل الأخطاء مفصلة (REQ-04)
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) use ($unified) {
            if ($request->is('api/*')) {
                $errors = $e->errors();
                $firstMessage = collect($errors)->flatten()->first()
                    ?? \App\Support\ApiMessages::VALIDATION_ERROR;

                return $unified($firstMessage, 422, $errors);
            }
        });

        // 404 — مسار أو سجل غير موجود
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) use ($unified) {
            if ($request->is('api/*')) {
                return $unified(\App\Support\ApiMessages::NOT_FOUND, 404);
            }
        });

        // 429 — تجاوز حد المحاولات (throttle)
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, \Illuminate\Http\Request $request) use ($unified) {
            if ($request->is('api/*')) {
                return $unified(\App\Support\ApiMessages::TOO_MANY_ATTEMPTS, 429);
            }
        });

        // أي خطأ HTTP آخر (403، 405...) — نفس الهيكل الموحد
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, \Illuminate\Http\Request $request) use ($unified) {
            if ($request->is('api/*')) {
                $message = $e->getMessage() !== ''
                    ? $e->getMessage()
                    : \App\Support\ApiMessages::SERVER_ERROR;

                return $unified($message, $e->getStatusCode());
            }
        });

        // 500 — أي خطأ غير متوقع: نغلّفه بالهيكل الموحد ونسجّله كاملاً في اللوج
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) use ($unified) {
            if ($request->is('api/*') && ! $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                \Illuminate\Support\Facades\Log::error('Unhandled API exception: ' . $e->getMessage(), [
                    'exception' => get_class($e),
                    'url'       => $request->fullUrl(),
                    'trace'     => $e->getTraceAsString(),
                ]);

                $message = config('app.debug')
                    ? $e->getMessage()
                    : \App\Support\ApiMessages::SERVER_ERROR;

                return $unified($message, 500);
            }
        });
    })->create();
