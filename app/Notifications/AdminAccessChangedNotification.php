<?php

namespace App\Notifications;

use App\Models\AppUser;
use App\Support\NotificationPayload;

class AdminAccessChangedNotification extends AbstractDatabaseNotification
{
    public function __construct(
        AppUser $member,
        AppUser $actor,
        string $status,
    ) {
        parent::__construct();

        [$title, $message] = match ($status) {
            'granted' => [
                'Admin access granted',
                'Your account now has admin access.',
            ],
            'revoked' => [
                'Admin access revoked',
                'Your admin access has been revoked.',
            ],
            default => [
                'Admin access updated',
                'Your admin access was updated.',
            ],
        };

        $this->payload = array_merge(
            [
                'type' => 'admin_access_changed',
                'title' => $title,
                'message' => $message,
                'status' => $status,
                'entity_type' => 'admin_access',
                'entity_id' => $member->user_id,
                'reference' => $member->acctno ?: $member->display_code,
                'updated_at' => now()->toDateTimeString(),
            ],
            NotificationPayload::member($member),
            NotificationPayload::actor($actor),
        );
    }
}
