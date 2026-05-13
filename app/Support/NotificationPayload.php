<?php

namespace App\Support;

use App\Models\AppUser;
use Illuminate\Support\Facades\Schema;

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
            'member_name' => self::memberDisplayName($user),
            'member_acctno' => $user?->acctno,
        ];
    }

    public static function memberDisplayName(
        ?AppUser $user,
        bool $allowEmailFallback = true,
    ): ?string {
        if ($user === null) {
            return null;
        }

        $name = null;

        if (Schema::hasTable('wmaster')) {
            $user->loadMissing('wmaster');
            $name = $user->wmaster?->displayName();
        }

        if (! is_string($name) || trim($name) === '') {
            $name = $user->username;
        }

        if (
            $allowEmailFallback &&
            (! is_string($name) || trim($name) === '')
        ) {
            $name = $user->email;
        }

        if (! is_string($name)) {
            return null;
        }

        $name = trim($name);

        return $name !== '' ? $name : null;
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
