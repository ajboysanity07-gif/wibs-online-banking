<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRoleChange extends Model
{
    /** @use HasFactory<\Database\Factories\UserRoleChangeFactory> */
    use HasFactory;

    public const ACTION_STAFF_CREATED = 'staff_created';

    public const ACTION_ROLE_ASSIGNED = 'role_assigned';

    public const ACTION_ROLE_REMOVED = 'role_removed';

    public const ACTION_STAFF_SUSPENDED = 'staff_suspended';

    public const ACTION_STAFF_REACTIVATED = 'staff_reactivated';

    public const ACTION_MEMBERSHIP_LINKED = 'membership_linked';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'target_user_id',
        'actor_user_id',
        'action',
        'role_name',
        'before_roles_json',
        'after_roles_json',
        'before_staff_status',
        'after_staff_status',
        'reason',
        'metadata_json',
    ];

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'target_user_id', 'user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'actor_user_id', 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before_roles_json' => 'array',
            'after_roles_json' => 'array',
            'metadata_json' => 'array',
        ];
    }
}
