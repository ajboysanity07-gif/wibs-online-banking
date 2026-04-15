<?php

namespace App\Services\Admin;

use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Notifications\AdminAccessAuditNotification;
use App\Notifications\AdminAccessChangedNotification;
use App\Services\Notifications\NotificationRecipientService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MemberAdminAccessService
{
    public function __construct(
        private NotificationRecipientService $notificationRecipients,
    ) {}

    public function grant(AppUser $user, AppUser $actor): AppUser
    {
        $this->guardTargetUser($user, $actor);

        $member = DB::transaction(function () use ($user): AppUser {
            $user->loadMissing('adminProfile', 'userProfile');

            if ($user->adminProfile?->access_level === AdminProfile::ACCESS_LEVEL_SUPERADMIN) {
                throw ValidationException::withMessages([
                    'member' => 'Superadmin access cannot be updated from here.',
                ]);
            }

            $fullname = $user->adminProfile?->fullname ?? $this->resolveFullName($user);
            $profilePicPath = $this->resolveProfilePicPath($user);
            $adminProfileData = [
                'fullname' => $fullname,
                'access_level' => AdminProfile::ACCESS_LEVEL_ADMIN,
            ];

            if ($profilePicPath !== null) {
                $adminProfileData['profile_pic_path'] = $profilePicPath;
            }

            AdminProfile::query()->updateOrCreate(
                ['user_id' => $user->user_id],
                $adminProfileData,
            );

            return $this->loadMember($user->refresh());
        });

        $member->notify(new AdminAccessChangedNotification($member, $actor, 'granted'));

        $superadmins = $this->notificationRecipients->superadmins();

        if ($superadmins->isNotEmpty()) {
            Notification::send(
                $superadmins,
                new AdminAccessAuditNotification($member, $actor, 'granted'),
            );
        }

        return $member;
    }

    public function revoke(AppUser $user, AppUser $actor): AppUser
    {
        $this->guardTargetUser($user, $actor);

        $user->loadMissing('adminProfile');

        if ($user->adminProfile?->access_level === AdminProfile::ACCESS_LEVEL_SUPERADMIN) {
            throw ValidationException::withMessages([
                'member' => 'Superadmin access cannot be revoked from here.',
            ]);
        }

        $member = DB::transaction(function () use ($user): AppUser {
            AdminProfile::query()
                ->where('user_id', $user->user_id)
                ->delete();

            return $this->loadMember($user->refresh());
        });

        $member->notify(new AdminAccessChangedNotification($member, $actor, 'revoked'));

        $superadmins = $this->notificationRecipients->superadmins();

        if ($superadmins->isNotEmpty()) {
            Notification::send(
                $superadmins,
                new AdminAccessAuditNotification($member, $actor, 'revoked'),
            );
        }

        return $member;
    }

    private function guardTargetUser(AppUser $user, AppUser $actor): void
    {
        if ($user->user_id === $actor->user_id) {
            throw ValidationException::withMessages([
                'member' => 'You cannot update your own admin access.',
            ]);
        }
    }

    private function resolveFullName(AppUser $user): string
    {
        if (Schema::hasTable('wmaster')) {
            $user->loadMissing('wmaster');
        }

        $name = $user->wmaster?->displayName();

        if (is_string($name) && trim($name) !== '') {
            return $name;
        }

        $username = $user->username;

        if (is_string($username) && trim($username) !== '') {
            return $username;
        }

        $email = $user->email;

        if (is_string($email) && trim($email) !== '') {
            return $email;
        }

        return 'Administrator';
    }

    private function resolveProfilePicPath(AppUser $user): ?string
    {
        $adminPath = $user->adminProfile?->profile_pic_path;

        if (is_string($adminPath) && trim($adminPath) !== '') {
            return $adminPath;
        }

        $userPath = $user->userProfile?->profile_pic_path;

        if (is_string($userPath) && trim($userPath) !== '') {
            return $userPath;
        }

        return null;
    }

    private function loadMember(AppUser $user): AppUser
    {
        $relations = ['adminProfile', 'userProfile'];

        if (Schema::hasTable('wmaster')) {
            $relations[] = 'wmaster';
        }

        return $user->loadMissing($relations);
    }
}
