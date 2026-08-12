<?php

namespace App\Services;

use App\Models\Contractor;
use App\Services\Push\PushSenderInterface;
use Illuminate\Support\Collection;

/**
 * إرسال Push مباشر خارج سياق Notification classes — للبث الجماعي/المفلتر
 * (تعميم جديد، فعالية جديدة، إشعارات مستهدفة من لوحة التحكم).
 */
class PushNotificationService
{
    public function __construct(private PushSenderInterface $sender)
    {
    }

    public function sendToContractor(Contractor $contractor, string $title, string $body, array $data = []): bool
    {
        if (! $contractor->fcm_token) {
            return false;
        }

        return $this->sender->send($contractor->fcm_token, $title, $body, $data);
    }

    /** @param iterable<Contractor> $contractors */
    public function sendToMany(iterable $contractors, string $title, string $body, array $data = []): int
    {
        $sent = 0;

        foreach ($contractors as $contractor) {
            if ($this->sendToContractor($contractor, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * استعلام المقاولين المطابقين لفلاتر البث المستهدَف (تخصصات/تصنيف/محافظة) —
     * مستخدَم هنا وبـ NotificationController@broadcast (REQ-22) لبناء نفس قائمة المستهدَفين
     * سواء للـpush المباشر أو لإشعار Notification كامل (database + push).
     *
     * @param array{specialties?: array, classification?: array, governorate_id?: int} $filters
     */
    public function filteredContractorsQuery(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $query = Contractor::query();

        if (! empty($filters['specialties'])) {
            // LIKE على تمثيل JSON النصي بدل whereJsonContains — JSON_CONTAINS بـMariaDB لا يطابق
            // نصوصاً عربية (تحقّقنا ميدانياً: يرجع 0 رغم تطابق البيانات). عمود JSON بـMariaDB
            // يخزّن غير ASCII كـ\uXXXX حرفياً، فلازم نطابق نفس الترميز + مضاعفة الـ backslash
            // لأن MySQL/MariaDB LIKE تعامل \ كحرف escape افتراضياً.
            $query->where(function ($q) use ($filters) {
                foreach ((array) $filters['specialties'] as $specialty) {
                    $encoded  = trim(json_encode($specialty), '"');
                    $likeSafe = str_replace('\\', '\\\\', $encoded);
                    $q->orWhere('specialties', 'like', '%' . $likeSafe . '%');
                }
            });
        }

        if (! empty($filters['classification'])) {
            $query->whereIn('classification', (array) $filters['classification']);
        }

        if (! empty($filters['governorate_id'])) {
            $query->where('governorate_id', $filters['governorate_id']);
        }

        return $query;
    }

    /**
     * بث Push مباشر مفلتر (بلا سجل Notification/قاعدة بيانات) — للاستخدام الداخلي
     * (تعميم جديد، فعالية جديدة). للبث الإداري المُركَّب من لوحة التحكم راجع
     * NotificationController@broadcast الذي يستخدم filteredContractorsQuery() + Notification::send().
     *
     * @param array{specialties?: array, classification?: array, governorate_id?: int} $filters
     */
    public function sendToFiltered(array $filters, string $title, string $body, array $data = []): int
    {
        $contractors = $this->filteredContractorsQuery($filters)->whereNotNull('fcm_token')->get();

        return $this->sendToMany($contractors, $title, $body, $data);
    }
}
