<?php

namespace App\Http\Resources\Admin;

use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\Wmaster;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = $this->resource;
        $userId = $this->resolveUserId(data_get($resource, 'user_id'));
        $acctno = $this->resolveString(data_get($resource, 'acctno'));
        $memberName = $this->resolveMemberName($resource);
        $username = $this->resolveString(data_get($resource, 'username'));
        $email = $this->resolveString(data_get($resource, 'email'))
            ?? $this->resolveString(data_get($resource, 'email_address'));
        $phoneno = $this->resolveString(data_get($resource, 'phoneno'))
            ?? $this->resolveString(data_get($resource, 'phone'));
        $portalStatus = $this->resolvePortalStatus($resource, $userId);
        $adminAccessLevel = $this->resolveAdminAccessLevel($resource, $userId);
        $isAdmin = $adminAccessLevel !== null && $adminAccessLevel !== 'member';
        $isSuperadmin = $adminAccessLevel === AdminProfile::ACCESS_LEVEL_SUPERADMIN;

        return [
            'member_id' => $this->resolveMemberId($userId, $acctno),
            'user_id' => $userId,
            'member_name' => $memberName,
            'username' => $username,
            'email' => $email,
            'phoneno' => $phoneno,
            'acctno' => $acctno,
            'registration_status' => $userId === null ? 'unregistered' : 'registered',
            'portal_status' => $portalStatus,
            'is_admin' => $isAdmin,
            'is_superadmin' => $isSuperadmin,
            'admin_access_level' => $adminAccessLevel,
            'created_at' => $this->formatDateValue(data_get($resource, 'created_at')),
            'avatar_url' => $resource instanceof AppUser ? $resource->avatar : null,
        ];
    }

    private function resolvePortalStatus(mixed $resource, ?int $userId): ?string
    {
        $portalStatus = $this->resolveString(
            data_get($resource, 'portal_status')
                ?? data_get($resource, 'userProfile.status'),
        );

        if ($userId !== null && $portalStatus === null) {
            return 'active';
        }

        return $portalStatus;
    }

    private function resolveAdminAccessLevel(mixed $resource, ?int $userId): ?string
    {
        $accessLevel = $this->resolveString(
            data_get($resource, 'adminProfile.access_level'),
        );

        if ($accessLevel !== null) {
            return $accessLevel;
        }

        if ($userId === null) {
            return null;
        }

        return 'member';
    }

    private function resolveMemberName(mixed $resource): string
    {
        if ($resource instanceof Wmaster) {
            $name = $resource->displayName();

            if ($name !== '') {
                return $name;
            }
        }

        if ($resource instanceof AppUser) {
            $wmasterName = $resource->relationLoaded('wmaster')
                ? $resource->wmaster?->displayName()
                : null;

            if (is_string($wmasterName) && trim($wmasterName) !== '') {
                return $wmasterName;
            }
        }

        return $this->resolveString(data_get($resource, 'username'))
            ?? $this->resolveString(data_get($resource, 'email'))
            ?? $this->resolveString(data_get($resource, 'acctno'))
            ?? 'Member';
    }

    private function resolveMemberId(?int $userId, ?string $acctno): string
    {
        if ($userId !== null) {
            return (string) $userId;
        }

        if ($acctno !== null && $acctno !== '') {
            return 'acct-'.$acctno;
        }

        return 'acct-unknown';
    }

    private function resolveUserId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
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
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) ? $value : null;
    }
}
