<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class Adminusers extends Model
{
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $table='adminusers';
    protected $primaryKey = 'admin_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'email',
        'username',
        'password'
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

    protected static function booted(): void
    {
        static::creating(function (Adminusers $admin): void {
            if ($admin->admin_id) {
                return;
            }

            $max = static::max('admin_id') ?? '000000';
            $next = str_pad((int) $max + 1, 6, '0', STR_PAD_LEFT);

            $admin->admin_id = $next;
        });
    }
}
