<?php

namespace App\Services\Notifications;

use App\Models\AdminProfile;
use App\Models\AppUser;
use Illuminate\Database\Eloquent\Collection;

class NotificationRecipientService
{
    /**
     * @return Collection<int, AppUser>
     */
    public function adminsAndSuperadmins(): Collection
    {
        return AppUser::query()
            ->whereHas('adminProfile')
            ->get();
    }

    /**
     * @return Collection<int, AppUser>
     */
    public function superadmins(): Collection
    {
        return AppUser::query()
            ->whereHas('adminProfile', function ($query): void {
                $query->where('access_level', AdminProfile::ACCESS_LEVEL_SUPERADMIN);
            })
            ->get();
    }
}
