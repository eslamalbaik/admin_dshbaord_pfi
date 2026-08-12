<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    use ApiResponseTrait;

    // GET /api/notifications
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->select(['id', 'type', 'data', 'read_at', 'created_at'])
            ->orderByRaw('read_at IS NULL DESC')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return $this->success([
            'notifications' => [
                'data'         => NotificationResource::collection($notifications->items()),
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'total'        => $notifications->total(),
            ],
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    // PATCH /api/notifications/{id}/mark-as-read
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return $this->success(
            ['unread_count' => $request->user()->unreadNotifications()->count()],
            'تم تحديد الإشعار كمقروء.',
        );
    }

    // POST /api/notifications/read
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(
            ['unread_count' => 0],
            'تم تحديد جميع الإشعارات كمقروءة.',
        );
    }

    /**
     * POST /api/v1/dashboard/notifications/broadcast
     * إشعار مستهدَف من لوحة التحكم — فلترة حسب تخصصات/تصنيف/محافظة (REQ-22).
     */
    public function broadcast(Request $request, \App\Services\PushNotificationService $push): JsonResponse
    {
        $data = $request->validate([
            'title'                   => 'required|string|max:255',
            'body'                    => 'required|string|max:1000',
            'filters'                 => 'nullable|array',
            'filters.specialties'     => 'nullable|array',
            'filters.classification'  => 'nullable|array',
            'filters.governorate_id'  => 'nullable|integer',
        ]);

        $filters     = $data['filters'] ?? [];
        $contractors = $push->filteredContractorsQuery($filters)->get();

        \Illuminate\Support\Facades\Notification::send(
            $contractors,
            new \App\Notifications\AdminBroadcastNotification($data['title'], $data['body']),
        );

        \App\Services\AuditLogService::record(
            $request->user(),
            'notifications.broadcast',
            null,
            ['filters' => $filters, 'title' => $data['title'], 'recipients_count' => $contractors->count()],
        );

        return $this->success(
            ['recipients_count' => $contractors->count()],
            'تم إرسال الإشعار لـ' . $contractors->count() . ' مقاول.',
        );
    }
}
