<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * تسجيل العمليات الحساسة بلوحة التحكم لأغراض المراجعة والمساءلة.
 */
class AuditLogService
{
    public static function record(?Model $actor, string $action, ?Model $subject = null, array $meta = []): ActivityLog
    {
        return ActivityLog::create([
            'actor_id'     => $actor?->getKey(),
            'actor_type'   => $actor ? $actor::class : null,
            'action'       => $action,
            'subject_id'   => $subject?->getKey(),
            'subject_type' => $subject ? $subject::class : null,
            'meta'         => $meta,
            'created_at'   => now(),
        ]);
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
