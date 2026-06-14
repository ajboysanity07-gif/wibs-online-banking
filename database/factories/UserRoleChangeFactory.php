<?php

namespace Database\Factories;

use App\Models\AppUser;
use App\Models\UserRoleChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserRoleChange>
 */
class UserRoleChangeFactory extends Factory
{
    protected $model = UserRoleChange::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'target_user_id' => AppUser::factory(),
            'actor_user_id' => AppUser::factory(),
            'action' => UserRoleChange::ACTION_ROLE_ASSIGNED,
            'role_name' => 'loan_officer',
            'before_roles_json' => [],
            'after_roles_json' => ['loan_officer'],
            'before_staff_status' => null,
            'after_staff_status' => 'active',
            'reason' => fake()->sentence(),
            'metadata_json' => null,
        ];
    }
}
