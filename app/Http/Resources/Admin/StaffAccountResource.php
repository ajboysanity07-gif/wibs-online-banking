<?php

namespace App\Http\Resources\Admin;

use App\Models\AppUser;
use App\Models\Role;
use App\Models\StaffAccessControl;
use App\Models\UserRoleChange;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffAccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AppUser $user */
        $user = $this->resource;
        $lastChange = $user->latestRoleChange;

        return [
            'user_id' => $user->user_id,
            'display_code' => $user->display_code,
            'display_name' => $this->resolveDisplayName($user),
            'username' => $this->resolveString($user->username),
            'email' => $this->resolveString($user->email),
            'phoneno' => $this->resolveString($user->phoneno),
            'acctno' => $this->resolveString($user->acctno),
            'has_member_access' => $user->hasMemberAccess(),
            'roles' => $this->resolveRoles($user),
            'staff_access_status' => $this->resolveStaffAccessStatus($user),
            'last_change' => $lastChange instanceof UserRoleChange
                ? [
                    'action' => $lastChange->action,
                    'action_label' => $this->actionLabel($lastChange->action),
                    'created_at' => $this->formatDateValue($lastChange->created_at),
                    'actor_name' => $lastChange->actor?->name,
                    'actor_display_code' => $lastChange->actor?->display_code,
                ]
                : null,
            'created_at' => $this->formatDateValue($user->created_at),
        ];
    }

    /**
     * @return array<int, array{name: string, label: string, editable: bool}>
     */
    private function resolveRoles(AppUser $user): array
    {
        $roles = [];

        if ($user->isSuperadmin()) {
            $roles[] = [
                'name' => Role::SUPERADMIN,
                'label' => 'Superadmin',
                'editable' => true,
            ];
        }

        if ($user->hasRole(Role::LOAN_PROCESSOR)) {
            $roles[] = [
                'name' => Role::LOAN_PROCESSOR,
                'label' => 'Loan Processor',
                'editable' => true,
            ];
        }

        if ($user->hasRole(Role::LOAN_MANAGER)) {
            $roles[] = [
                'name' => Role::LOAN_MANAGER,
                'label' => 'Loan Manager',
                'editable' => true,
            ];
        }

        if ($user->hasMemberAccess() || $user->hasRole(Role::MEMBER)) {
            $roles[] = [
                'name' => Role::MEMBER,
                'label' => 'Member',
                'editable' => false,
            ];
        }

        return $roles;
    }

    private function resolveStaffAccessStatus(AppUser $user): string
    {
        if (! $user->hasAnyRole(Role::editableStaffNames()) && ! $user->isLegacySuperadmin()) {
            return 'not_staff';
        }

        return $user->isStaffAccessSuspended()
            ? StaffAccessControl::STATUS_SUSPENDED
            : StaffAccessControl::STATUS_ACTIVE;
    }

    private function resolveDisplayName(AppUser $user): string
    {
        $wmasterName = $user->relationLoaded('wmaster')
            ? $user->wmaster?->displayName()
            : null;

        if (is_string($wmasterName) && trim($wmasterName) !== '') {
            return $wmasterName;
        }

        $adminName = $user->adminProfile?->fullname;

        if (is_string($adminName) && trim($adminName) !== '') {
            return trim($adminName);
        }

        return $this->resolveString($user->username)
            ?? $this->resolveString($user->email)
            ?? $user->display_code;
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            UserRoleChange::ACTION_STAFF_CREATED => 'Staff created',
            UserRoleChange::ACTION_ROLE_ASSIGNED => 'Role assigned',
            UserRoleChange::ACTION_ROLE_REMOVED => 'Role removed',
            UserRoleChange::ACTION_STAFF_SUSPENDED => 'Staff suspended',
            UserRoleChange::ACTION_STAFF_REACTIVATED => 'Staff reactivated',
            default => 'Staff change',
        };
    }

    private function resolveString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function formatDateValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) && trim($value) !== ''
            ? $value
            : null;
    }
}
