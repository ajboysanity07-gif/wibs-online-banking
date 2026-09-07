<?php

namespace App\Notifications;

use App\Models\AppUser;
use App\Support\NotificationPayload;

class MemberPasswordResetAuditNotification extends AbstractDatabaseNotification
{
    public function __construct(
        AppUser $member,
        AppUser $actor,
        string $reason,
    ) {
        parent::__construct();

        $memberName = $member->name;
        $actorName = $actor->name;

        $this->payload = array_merge(
            [
                'type' => 'member_password_reset_audit',
                'title' => 'Member password reset',
                'message' => sprintf("%s's password was reset by %s.", $memberName, $actorName),
                'entity_type' => 'member_password_reset',
                'entity_id' => $member->user_id,
                'reference' => $member->acctno ?: $member->display_code,
                'reason' => $reason,
            ],
            NotificationPayload::member($member),
            NotificationPayload::actor($actor),
        );
    }
}
