<?php

namespace App\Services;

use App\Models\AssistantConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * بيحفظ رسائل المساعد الافتراضي بسجل المحادثات. الـIP بيتحفظ مشفّر (hash) بس، ولا بنحفظ أي بيانات تعريف ثانية.
 * أي خطأ بالحفظ ما بيوقف رد المساعد.
 */
class AssistantLogService
{
    /** @param array<int, array{role: string, text: string, source?: ?string}> $messages */
    public static function record(Request $request, ?string $sessionKey, array $messages, ?string $page = null): void
    {
        if (! $sessionKey || ! $messages) {
            return;
        }

        try {
            DB::transaction(function () use ($request, $sessionKey, $messages, $page) {
                $conv = AssistantConversation::firstOrCreate(['session_key' => $sessionKey], [
                    'ip_hash'    => $request->ip() ? hash('sha256', $request->ip() . config('app.key')) : null,
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
                    'page'       => $page ? mb_substr($page, 0, 255) : null,
                ]);

                foreach ($messages as $m) {
                    $conv->messages()->create([
                        'role'   => $m['role'] === 'user' ? 'user' : 'bot',
                        'text'   => mb_substr($m['text'], 0, 4000),
                        'source' => $m['source'] ?? null,
                    ]);
                }

                $conv->forceFill([
                    'messages_count'  => $conv->messages_count + count($messages),
                    'last_message_at' => now(),
                ])->save();
            });
        } catch (\Throwable $e) {
            Log::error('assistant: log failed', ['error' => $e->getMessage()]);
        }
    }
}
