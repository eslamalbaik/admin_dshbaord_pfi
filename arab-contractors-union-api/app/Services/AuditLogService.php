<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * تسجيل العمليات الحساسة بلوحة التحكم لأغراض المراجعة والمساءلة.
 */
class AuditLogService
{
    public static function record(?Model $actor, string $action, ?Model $subject = null, array $meta = [], bool $critical = false): ActivityLog
    {
        return ActivityLog::create([
            'actor_id'     => $actor?->getKey(),
            'actor_type'   => $actor ? $actor::class : null,
            'action'       => $action,
            'is_critical'  => $critical,
            'subject_id'   => $subject?->getKey(),
            'subject_type' => $subject ? $subject::class : null,
            'meta'         => $meta,
            'created_at'   => now(),
        ]);
    }

    /**
     * تسجيل حدث بسجل المحددات الهامة (انظر App\Support\CriticalEvents) مع القيم
     * قبل/بعد التعديل. بيتخزّن فقط الحقول اللي تغيّرت فعلاً، إلا إذا ما تغيّر شي
     * (مثلاً إعادة إصدار بنفس البيانات) فبيتخزّن كل اللي انبعت.
     */
    public static function recordCritical(
        ?Model $actor,
        string $action,
        ?Model $subject,
        array $before,
        array $after,
        ?string $reason = null,
        array $context = [],
    ): ActivityLog {
        $changed = array_filter(
            array_keys($after),
            fn ($key) => ! self::sameValue($before[$key] ?? null, $after[$key] ?? null)
        );

        if ($changed) {
            $before = array_intersect_key($before, array_flip($changed));
            $after  = array_intersect_key($after, array_flip($changed));
        }

        return self::record($actor, $action, $subject, array_merge($context, array_filter([
            'before' => $before,
            'after'  => $after,
            'reason' => filled($reason) ? $reason : null,
        ], fn ($v) => $v !== null)), critical: true);
    }

    /** مقارنة متسامحة: 15 و"15.00" نفس القيمة، و"" نفس null */
    public static function sameValue(mixed $a, mixed $b): bool
    {
        $a = $a === '' ? null : $a;
        $b = $b === '' ? null : $b;

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        return (string) $a === (string) $b;
    }

    /**
     * تسجيل محاولات الوصول غير المصرح بها أو المشبوهة.
     */
    public static function recordFailedAttempt(?Model $actor, string $endpoint, string $reason, array $meta = []): ActivityLog
    {
        return self::record(
            actor: $actor,
            action: 'security.unauthorized_access_attempt',
            meta: array_merge(['endpoint' => $endpoint, 'reason' => $reason], $meta)
        );
    }
}
