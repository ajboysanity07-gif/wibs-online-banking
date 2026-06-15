<?php

namespace App\Http\Resources\Admin;

use App\Models\Role;
use App\Models\UserRoleChange;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserRoleChangeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UserRoleChange $change */
        $change = $this->resource;

        return [
            'id' => $change->id,
            'action' => $change->action,
            'action_label' => $this->actionLabel($change->action),
            'role_name' => $change->role_name,
            'role_label' => $this->roleLabel($change->role_name),
            'before_roles' => $this->formatRoles($change->before_roles_json ?? []),
            'after_roles' => $this->formatRoles($change->after_roles_json ?? []),
            'before_staff_status' => $change->before_staff_status,
            'after_staff_status' => $change->after_staff_status,
            'reason' => $change->reason,
            'metadata' => $change->metadata_json,
            'created_at' => $this->formatDateValue($change->created_at),
            'actor' => $change->actor
                ? [
                    'display_code' => $change->actor->display_code,
                    'name' => $change->actor->name,
                ]
                : null,
        ];
    }

    /**
     * @param  array<int, string>  $roles
     * @return array<int, array{name: string, label: string}>
     */
    private function formatRoles(array $roles): array
    {
        return array_values(array_map(function (string $roleName): array {
            return [
                'name' => $roleName,
                'label' => $this->roleLabel($roleName) ?? $roleName,
            ];
        }, $roles));
    }

    private function roleLabel(?string $roleName): ?string
    {
        return match ($roleName) {
            Role::SUPERADMIN => 'Superadmin',
            Role::LOAN_PROCESSOR => 'Loan Processor',
            Role::LOAN_MANAGER => 'Loan Manager',
            Role::MEMBER => 'Member',
            null => null,
            default => $roleName,
        };
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
