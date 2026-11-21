<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cloudflare Turnstile — حماية نماذج الدخول والتفعيل من البوتات.
 *
 * - بدون TURNSTILE_SECRET_KEY: الـmiddleware معطّل (كل شي بيمر) فالنشر ما بيكسر شي قبل ضبط المفاتيح.
 * - تطبيق الجوال (Native) ما بيبعت هيدر Origin ولا بيقدر يحل Turnstile، فالطلبات بدون Origin بتمر
 *   إلا لو TURNSTILE_STRICT=true (يُفعَّل بعد ما التطبيق يدعم التوكن).
 * - لو Cloudflare نفسه ما ردّ (انقطاع) منسمح بالطلب ونسجّل تحذير، بدل ما نقفل الناس برا حساباتهم.
 */
class VerifyTurnstile
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.turnstile.secret');
        if ($secret === '') {
            return $next($request);
        }

        if (! config('services.turnstile.strict') && ! $request->headers->has('Origin')) {
            return $next($request);
        }

        $token = (string) $request->input('cf_turnstile_token', '');
        if ($token === '') {
            return $this->reject('turnstile_required');
        }

        try {
            $res = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Turnstile siteverify unreachable — request allowed', ['error' => $e->getMessage()]);

            return $next($request);
        }

        if (! $res->ok()) {
            Log::warning('Turnstile siteverify HTTP ' . $res->status() . ' — request allowed');

            return $next($request);
        }

        if (! $res->json('success')) {
            return $this->reject('turnstile_failed', $res->json('error-codes') ?? []);
        }

        return $next($request);
    }

    private function reject(string $error, array $codes = []): Response
    {
        return response()->json([
            'status'  => false,
            'error'   => $error,
            'message' => 'تعذّر التحقق الأمني. حدّث الصفحة وحاول مرة أخرى.',
            'codes'   => $codes,
        ], 422);
    }
}
