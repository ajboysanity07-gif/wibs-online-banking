<?php

namespace App\Notifications;

use App\Models\AppUser;
use App\Support\MemberStatus;
use App\Support\NotificationPayload;

class MemberStatusChangedNotification extends AbstractDatabaseNotification
{
    public function __construct(
        AppUser $member,
        AppUser $actor,
        MemberStatus $status,
    ) {
        parent::__construct();

        [$title, $message] = match ($status) {
            MemberStatus::Suspended => [
                'Account suspended',
                'Your portal access has been suspended.',
            ],
            MemberStatus::Active => [
                'Account reactivated',
                'Your portal access has been reactivated.',
            ],
        };

        $updatedAt = $member->userProfile?->reviewed_at?->toDateTimeString();

        $this->payload = array_merge(
            [
                'type' => 'member_status_changed',
                'title' => $title,
                'message' => $message,
                'status' => $status->value,
                'entity_type' => 'member_status',
                'entity_id' => $member->user_id,
                'reference' => $member->acctno ?: $member->display_code,
                'updated_at' => $updatedAt,
            ],
            NotificationPayload::member($member),
            NotificationPayload::actor($actor),
        );
    }
}
