<?php

namespace Database\Factories;

use App\Models\AppUser;
use App\Models\StaffAccessControl;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffAccessControl>
 */
class StaffAccessControlFactory extends Factory
{
    protected $model = StaffAccessControl::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => AppUser::factory(),
            'status' => StaffAccessControl::STATUS_ACTIVE,
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
        ];
    }

    public function suspended(?AppUser $actor = null): static
    {
        return $this->state(fn (): array => [
            'status' => StaffAccessControl::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'suspended_by' => $actor?->user_id,
            'suspension_reason' => 'Suspended for testing.',
        ]);
    }
}
