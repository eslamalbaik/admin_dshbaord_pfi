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
}
