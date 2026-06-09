<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    /** @use HasFactory<\Database\Factories\RoleFactory> */
    use HasFactory;

    public const ADMIN = 'admin';

    public const LOAN_OFFICER = 'loan_officer';

    public const LOAN_MANAGER = 'loan_manager';

    public const MEMBER = 'member';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'display_name',
    ];

    /**
     * @return list<array{name: string, display_name: string|null}>
     */
    public static function defaults(): array
    {
        return [
            ['name' => self::ADMIN, 'display_name' => 'Admin'],
            ['name' => self::LOAN_OFFICER, 'display_name' => 'Loan Officer'],
            ['name' => self::LOAN_MANAGER, 'display_name' => 'Loan Manager'],
            ['name' => self::MEMBER, 'display_name' => 'Member'],
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            AppUser::class,
            'user_roles',
            'role_id',
            'user_id',
            'id',
            'user_id',
        )->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withTimestamps();
    }
}
