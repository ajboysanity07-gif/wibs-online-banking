<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;

class AppUser extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\AppUserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    public const EXPERIENCE_SUPERADMIN = 'superadmin';

    public const EXPERIENCE_USER = 'user';

    public const EXPERIENCE_USER_ADMIN = 'user-admin';

    public const EXPERIENCE_ADMIN_ONLY = 'admin-only';

    protected $table = 'appusers';

    protected $primaryKey = 'user_id';

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * @var list<string>
     */
    protected $appends = ['id', 'name', 'display_code', 'avatar'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'phoneno',
        'password',
        'acctno',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    public function adminProfile(): HasOne
    {
        return $this->hasOne(AdminProfile::class, 'user_id', 'user_id');
    }

    public function userProfile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'user_id', 'user_id');
    }

    public function memberApplicationProfile(): HasOne
    {
        return $this->hasOne(MemberApplicationProfile::class, 'user_id', 'user_id');
    }

    public function wmaster(): BelongsTo
    {
        return $this->belongsTo(Wmaster::class, 'acctno', 'acctno');
    }

    public function hasCanonicalMemberRecord(): bool
    {
        if (! Schema::hasTable('wmaster')) {
            return false;
        }

        $this->loadMissing('wmaster');

        return $this->wmaster?->hasRequiredProfileFields() ?? false;
    }

    public function memberApplicationProfileIsComplete(): bool
    {
        return $this->memberApplicationProfileHasRequiredFields();
    }

    public function memberApplicationProfileHasRequiredFields(
        ?MemberApplicationProfile $memberProfile = null,
    ): bool {
        if ($memberProfile === null) {
            $this->loadMissing('memberApplicationProfile');
            $memberProfile = $this->memberApplicationProfile;
        }

        if ($memberProfile === null) {
            return false;
        }

        return $memberProfile->hasRequiredFields();
    }

    /**
     * @return list<string>
     */
    public function missingMemberApplicationProfileFields(
        ?MemberApplicationProfile $memberProfile = null,
    ): array {
        if ($memberProfile === null) {
            $this->loadMissing('memberApplicationProfile');
            $memberProfile = $this->memberApplicationProfile;
        }

        if ($memberProfile === null) {
            return MemberApplicationProfile::completionRequiredFields();
        }

        return $memberProfile->missingRequiredFields();
    }

    /**
     * @return list<string>
     */
    public function missingMemberApplicationProfileFieldLabels(
        ?MemberApplicationProfile $memberProfile = null,
    ): array {
        $missingFields = $this->missingMemberApplicationProfileFields($memberProfile);

        if ($missingFields === []) {
            return [];
        }

        $labels = MemberApplicationProfile::completionRequiredFieldLabels();

        return array_values(array_map(
            static fn (string $field): string => $labels[$field] ?? $field,
            $missingFields,
        ));
    }

    public function syncMemberApplicationProfileCompletion(?MemberApplicationProfile $memberProfile = null): void
    {
        if ($memberProfile === null) {
            $this->loadMissing('memberApplicationProfile');
            $memberProfile = $this->memberApplicationProfile;
        }

        if ($memberProfile === null) {
            return;
        }

        if ($this->memberApplicationProfileHasRequiredFields($memberProfile)) {
            $memberProfile->profile_completed_at ??= now();
        } else {
            $memberProfile->profile_completed_at = null;
        }

        $memberProfile->save();
    }

    public function getIdAttribute(): int
    {
        return $this->attributes['user_id'];
    }

    public function getNameAttribute(): string
    {
        return $this->attributes['username']
            ?? $this->attributes['email']
            ?? '';
    }

    public function getRoleAttribute(): string
    {
        return $this->adminProfile !== null ? 'admin' : 'client';
    }

    public function isAdmin(): bool
    {
        return $this->adminProfile !== null;
    }

    public function isSuperadmin(): bool
    {
        return $this->adminProfile?->access_level === AdminProfile::ACCESS_LEVEL_SUPERADMIN;
    }

    public function isHybrid(): bool
    {
        return $this->isAdmin() && $this->hasMemberAccess();
    }

    public function experienceType(): string
    {
        if ($this->isSuperadmin()) {
            return self::EXPERIENCE_SUPERADMIN;
        }

        if ($this->isAdminOnly()) {
            return self::EXPERIENCE_ADMIN_ONLY;
        }

        if ($this->isHybrid()) {
            return self::EXPERIENCE_USER_ADMIN;
        }

        return self::EXPERIENCE_USER;
    }

    public function hasMemberAccess(): bool
    {
        $acctno = $this->acctno;

        if ($acctno === null) {
            return false;
        }

        return trim((string) $acctno) !== '';
    }

    public function isAdminOnly(): bool
    {
        return $this->isAdmin() && ! $this->hasMemberAccess();
    }

    public function getDisplayCodeAttribute(): string
    {
        $prefix = $this->isAdmin() ? 'ADM' : 'USR';
        $id = (int) ($this->getKey() ?? 0);

        return sprintf('%s-%06d', $prefix, $id);
    }

    public function getAvatarAttribute(): ?string
    {
        $adminProfilePath = $this->adminProfile?->profile_pic_path;
        $profilePath = is_string($adminProfilePath) && trim($adminProfilePath) !== ''
            ? $adminProfilePath
            : $this->userProfile?->profile_pic_path;

        if (! is_string($profilePath) || $profilePath === '') {
            return null;
        }

        return Storage::disk('public')->url($profilePath);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
