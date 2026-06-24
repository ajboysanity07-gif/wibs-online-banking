<?php

namespace Database\Factories;

use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanRequestChange>
 */
class LoanRequestChangeFactory extends Factory
{
    protected $model = LoanRequestChange::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loan_request_id' => LoanRequest::factory(),
            'changed_by' => AppUser::factory(),
            'action' => LoanRequestChange::ACTION_START_REVIEW,
            'from_status' => 'pending_review',
            'to_status' => 'under_review',
            'reason' => fake()->sentence(),
            'before_json' => [],
            'after_json' => [],
            'changed_fields_json' => null,
            'metadata_json' => null,
        ];
    }
}
