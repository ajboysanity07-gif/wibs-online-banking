<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class AppUser extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\AppUserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $table = 'appusers';

    protected $primaryKey = 'user_id';

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * @var list<string>
     */
    protected $appends = ['id', 'name', 'display_code'];

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

    public function wmaster(): BelongsTo
    {
        return $this->belongsTo(Wmaster::class, 'acctno', 'acctno');
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

    public function getDisplayCodeAttribute(): string
    {
        $prefix = $this->isAdmin() ? 'ADM' : 'USR';
        $id = (int) ($this->getKey() ?? 0);

        return sprintf('%s-%06d', $prefix, $id);
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
