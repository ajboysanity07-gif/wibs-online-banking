<?php

namespace App\Services\Admin;

use App\Models\AppUser;
use App\Support\MemberStatus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MemberStatusService
{
    public function approve(AppUser $user, AppUser $actor): AppUser
    {
        $this->guardTargetUser($user);

        $currentStatus = $user->userProfile?->status;

        if ($currentStatus === MemberStatus::Active->value) {
            return $this->loadMember($user);
        }

        if ($currentStatus !== null && $currentStatus !== MemberStatus::Pending->value) {
            throw ValidationException::withMessages([
                'status' => 'Only pending members can be approved.',
            ]);
        }

        return $this->updateStatus($user, $actor, MemberStatus::Active);
    }

    public function suspend(AppUser $user, AppUser $actor): AppUser
    {
        $this->guardTargetUser($user);

        $currentStatus = $user->userProfile?->status;

        if ($currentStatus === MemberStatus::Suspended->value) {
            return $this->loadMember($user);
        }

        if ($currentStatus !== MemberStatus::Active->value) {
            throw ValidationException::withMessages([
                'status' => 'Only active members can be suspended.',
            ]);
        }

        return $this->updateStatus($user, $actor, MemberStatus::Suspended);
    }

    public function reactivate(AppUser $user, AppUser $actor): AppUser
    {
        $this->guardTargetUser($user);

        $currentStatus = $user->userProfile?->status;

        if ($currentStatus === MemberStatus::Active->value) {
            return $this->loadMember($user);
        }

        if ($currentStatus !== MemberStatus::Suspended->value) {
            throw ValidationException::withMessages([
                'status' => 'Only suspended members can be reactivated.',
            ]);
        }

        return $this->updateStatus($user, $actor, MemberStatus::Active);
    }

    private function guardTargetUser(AppUser $user): void
    {
        $user->loadMissing('adminProfile');

        if ($user->adminProfile !== null) {
            throw ValidationException::withMessages([
                'user' => 'Admin accounts cannot be updated.',
            ]);
        }
    }

    private function updateStatus(
        AppUser $user,
        AppUser $actor,
        MemberStatus $status,
    ): AppUser {
        $user->userProfile()->updateOrCreate(
            ['user_id' => $user->user_id],
            [
                'status' => $status->value,
                'reviewed_by' => $actor->user_id,
                'reviewed_at' => now(),
            ],
        );

        return $this->loadMember($user->refresh());
    }

    private function loadMember(AppUser $user): AppUser
    {
        $relations = ['userProfile.reviewedBy'];

        if (Schema::hasTable('wmaster')) {
            $relations[] = 'wmaster:acctno,fname,mname,lname,bname';
        }

        return $user->loadMissing($relations);
    }
}
