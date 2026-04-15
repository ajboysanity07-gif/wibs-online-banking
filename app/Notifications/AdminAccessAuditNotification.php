<?php

namespace App\Notifications;

use App\Models\AppUser;
use App\Support\NotificationPayload;

class AdminAccessAuditNotification extends AbstractDatabaseNotification
{
    public function __construct(
        AppUser $member,
        AppUser $actor,
        string $status,
    ) {
        parent::__construct();

        $memberName = $member->name;
        $actorName = $actor->name;

        [$title, $message] = match ($status) {
            'granted' => [
                'Admin access granted',
                sprintf('%s was granted admin access by %s.', $memberName, $actorName),
            ],
            'revoked' => [
                'Admin access revoked',
                sprintf('%s had admin access revoked by %s.', $memberName, $actorName),
            ],
            default => [
                'Admin access updated',
                sprintf('%s had admin access updated by %s.', $memberName, $actorName),
            ],
        };

        $this->payload = array_merge(
            [
                'type' => 'admin_access_audit',
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
