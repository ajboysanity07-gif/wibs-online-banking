<?php

namespace App\Notifications;

use App\Models\AppUser;
use App\Support\NotificationPayload;

class MemberPasswordResetNotification extends AbstractDatabaseNotification
{
    public function __construct(
        AppUser $member,
        AppUser $actor,
    ) {
        parent::__construct();

        $this->payload = array_merge(
            [
                'type' => 'member_password_reset',
                'title' => 'Password reset',
                'message' => 'Your password was reset by an administrator. Use the temporary password given to you to sign in, then set a new password.',
                'entity_type' => 'member_password_reset',
                'entity_id' => $member->user_id,
                'reference' => $member->acctno ?: $member->display_code,
            ],
            NotificationPayload::member($member),
            NotificationPayload::actor($actor),
        );
    }
}
