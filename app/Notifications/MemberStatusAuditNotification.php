<?php

namespace App\Notifications;

use App\Models\AppUser;
use App\Support\MemberStatus;
use App\Support\NotificationPayload;

class MemberStatusAuditNotification extends AbstractDatabaseNotification
{
    public function __construct(
        AppUser $member,
        AppUser $actor,
        MemberStatus $status,
    ) {
        parent::__construct();

        $memberName = $member->name;
        $actorName = $actor->name;

        [$title, $message] = match ($status) {
            MemberStatus::Suspended => [
                'Member suspended',
                sprintf('%s was suspended by %s.', $memberName, $actorName),
            ],
            MemberStatus::Active => [
                'Member reactivated',
                sprintf('%s was reactivated by %s.', $memberName, $actorName),
            ],
        };

        $updatedAt = $member->userProfile?->reviewed_at?->toDateTimeString();

        $this->payload = array_merge(
            [
                'type' => 'member_status_audit',
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
