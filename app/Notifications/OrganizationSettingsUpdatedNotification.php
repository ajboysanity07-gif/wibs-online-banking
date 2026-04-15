<?php

namespace App\Notifications;

use App\Models\AppUser;
use App\Models\OrganizationSetting;
use App\Support\NotificationPayload;

class OrganizationSettingsUpdatedNotification extends AbstractDatabaseNotification
{
    /**
     * @param  list<string>  $changedFields
     */
    public function __construct(
        OrganizationSetting $setting,
        AppUser $actor,
        array $changedFields,
    ) {
        parent::__construct();

        $this->payload = array_merge(
            [
                'type' => 'organization_settings_updated',
                'title' => 'Organization settings updated',
                'message' => sprintf(
                    'Organization settings were updated by %s.',
                    $actor->name,
                ),
                'status' => 'updated',
                'entity_type' => 'organization_settings',
                'entity_id' => $setting->getKey(),
                'reference' => $setting->company_name,
                'changed_fields' => NotificationPayload::changedFields($changedFields),
                'updated_at' => $setting->updated_at?->toDateTimeString(),
            ],
            NotificationPayload::actor($actor),
        );
    }
}
