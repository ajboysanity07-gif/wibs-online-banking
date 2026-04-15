<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\Controller;
use App\Http\Resources\Spa\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationsController extends Controller
{
    private const DEFAULT_LIMIT = 10;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $notifications = $user->notifications()
            ->latest()
            ->limit(self::DEFAULT_LIMIT)
            ->get();

        $items = NotificationResource::collection($notifications)->resolve();

        return response()->json([
            'ok' => true,
            'data' => [
                'items' => $items,
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'unreadCount' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $notificationModel = $user->notifications()
            ->whereKey($notification)
            ->first();

        if ($notificationModel === null) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($notificationModel->read_at === null) {
            $notificationModel->markAsRead();
        }

        $notificationModel->refresh();

        return response()->json([
            'ok' => true,
            'data' => [
                'notification' => (new NotificationResource($notificationModel))->resolve(),
                'unreadCount' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $readAt = now();

        $user->unreadNotifications()->update([
            'read_at' => $readAt,
        ]);

        return response()->json([
            'ok' => true,
            'data' => [
                'unreadCount' => 0,
                'readAt' => $readAt->toDateTimeString(),
            ],
        ]);
    }
}
