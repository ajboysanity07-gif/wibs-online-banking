<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    /** @use HasFactory<\Database\Factories\PermissionFactory> */
    use HasFactory;

    public const LOAN_VIEW = 'loan.view';

    public const LOAN_CREATE = 'loan.create';

    public const LOAN_REVIEW = 'loan.review';

    public const LOAN_CLAIM = 'loan.claim';

    public const LOAN_RETURN_TO_QUEUE = 'loan.return_to_queue';

    public const LOAN_MANAGE_ASSIGNMENT = 'loan.manage_assignment';

    public const LOAN_REQUEST_REVISION = 'loan.request_revision';

    public const LOAN_REJECT = 'loan.reject';

    public const LOAN_RECOMMEND_APPROVAL = 'loan.recommend_approval';

    public const LOAN_APPROVE = 'loan.approve';

    public const LOAN_DECLINE = 'loan.decline';

    public const LOAN_CONVERT_TO_LOAN = 'loan.convert_to_loan';

    public const STAFF_VIEW = 'staff.view';

    public const STAFF_MANAGE = 'staff.manage';

    public const MEMBER_VIEW = 'member.view';

    public const MEMBER_CREATE = 'member.create';

    public const MEMBER_UPDATE = 'member.update';

    public const PAYMENT_CREATE = 'payment.create';

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
            ['name' => self::LOAN_VIEW, 'display_name' => 'View loans'],
            ['name' => self::LOAN_CREATE, 'display_name' => 'Create loan requests'],
            ['name' => self::LOAN_REVIEW, 'display_name' => 'Review loan requests'],
            ['name' => self::LOAN_CLAIM, 'display_name' => 'Claim loan requests'],
            ['name' => self::LOAN_RETURN_TO_QUEUE, 'display_name' => 'Return loan requests to queue'],
            ['name' => self::LOAN_MANAGE_ASSIGNMENT, 'display_name' => 'Manage loan request assignments'],
            ['name' => self::LOAN_REQUEST_REVISION, 'display_name' => 'Request loan revisions'],
            ['name' => self::LOAN_REJECT, 'display_name' => 'Reject loan requests'],
            ['name' => self::LOAN_RECOMMEND_APPROVAL, 'display_name' => 'Recommend loan approval'],
            ['name' => self::LOAN_APPROVE, 'display_name' => 'Approve loans'],
            ['name' => self::LOAN_DECLINE, 'display_name' => 'Decline loans'],
            ['name' => self::STAFF_VIEW, 'display_name' => 'View staff accounts'],
            ['name' => self::STAFF_MANAGE, 'display_name' => 'Manage staff accounts'],
            ['name' => self::MEMBER_VIEW, 'display_name' => 'View members'],
            ['name' => self::MEMBER_CREATE, 'display_name' => 'Create members'],
            ['name' => self::MEMBER_UPDATE, 'display_name' => 'Update members'],
            ['name' => self::PAYMENT_CREATE, 'display_name' => 'Create payments'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultNames(): array
    {
        return array_map(
            static fn (array $permission): string => $permission['name'],
            self::defaults(),
        );
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')
            ->withTimestamps();
    }
}
