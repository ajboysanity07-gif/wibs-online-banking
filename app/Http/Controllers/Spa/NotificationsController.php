<?php

namespace App\Http\Controllers\Spa;

use App\Http\Controllers\Controller;
use App\Http\Resources\Spa\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        $items = [];

        foreach ($notifications as $notification) {
            try {
                $items[] = $this->serializeNotification($notification);
            } catch (Throwable $exception) {
                $this->logNotificationSerializationFailure(
                    $notification,
                    $exception,
                    'skipped',
                );
            }
        }

        return response()->json(
            [
                'ok' => true,
                'data' => [
                    'items' => $items,
                ],
            ],
            options: JSON_INVALID_UTF8_SUBSTITUTE,
        );
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

        return response()->json(
            [
                'ok' => true,
                'data' => [
                    'notification' => $this->serializeNotificationOrFallback($notificationModel),
                    'unreadCount' => $user->unreadNotifications()->count(),
                ],
            ],
            options: JSON_INVALID_UTF8_SUBSTITUTE,
        );
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

    /**
     * @return array{
     *     id: string,
     *     data: array<string, mixed>,
     *     read_at: string|null,
     *     created_at: string|null
     * }
     */
    private function serializeNotification(DatabaseNotification $notification): array
    {
        return (new NotificationResource($notification))->resolve();
    }

    /**
     * @return array{
     *     id: string,
     *     data: array<string, mixed>,
     *     read_at: string|null,
     *     created_at: string|null
     * }
     */
    private function serializeNotificationOrFallback(DatabaseNotification $notification): array
    {
        try {
            return (new NotificationResource($notification))->resolve();
        } catch (Throwable $exception) {
            $this->logNotificationSerializationFailure(
                $notification,
                $exception,
                'fallback',
            );

            return [
                'id' => (string) $notification->getKey(),
                'data' => NotificationResource::fallbackPayload(),
                'read_at' => null,
                'created_at' => null,
            ];
        }
    }

    private function logNotificationSerializationFailure(
        DatabaseNotification $notification,
        Throwable $exception,
        string $action,
    ): void {
        $rawPayload = $notification->getRawOriginal('data');

        Log::warning('Notification serialization failed.', [
            'action' => $action,
            'notification_id' => (string) $notification->getKey(),
            'notification_type' => $notification->type,
            'notifiable_type' => $notification->notifiable_type,
            'notifiable_id' => $notification->notifiable_id,
            'raw_payload_type' => gettype($rawPayload),
            'raw_payload_length' => is_string($rawPayload) ? strlen($rawPayload) : null,
            'exception' => $exception::class,
            'exception_message' => $exception->getMessage(),
        ]);
    }
}
