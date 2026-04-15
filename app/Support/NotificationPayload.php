<?php

namespace App\Support;

use App\Models\AppUser;

class NotificationPayload
{
    /**
     * @return array{
     *     actor_id: int|null,
     *     actor_name: string|null,
     *     actor_role: string|null
     * }
     */
    public static function actor(?AppUser $user): array
    {
        return [
            'actor_id' => $user?->user_id,
            'actor_name' => $user?->name,
            'actor_role' => self::resolveRole($user),
        ];
    }

    /**
     * @return array{
     *     member_id: int|null,
     *     member_name: string|null,
     *     member_acctno: string|null
     * }
     */
    public static function member(?AppUser $user): array
    {
        return [
            'member_id' => $user?->user_id,
            'member_name' => $user?->name,
            'member_acctno' => $user?->acctno,
        ];
    }

    /**
     * @param  list<string>  $fields
     * @return list<string>
     */
    public static function changedFields(array $fields): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $field): ?string => is_string($field) && trim($field) !== ''
                ? trim($field)
                : null,
            $fields,
        ))));
    }

    private static function resolveRole(?AppUser $user): ?string
    {
        if ($user === null) {
            return null;
        }

        if ($user->isSuperadmin()) {
            return 'superadmin';
        }

        if ($user->isAdmin()) {
            return 'admin';
        }

        return 'member';
    }
}
