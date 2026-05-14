<?php

namespace Database\Factories;

use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestCorrectionReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanRequestCorrectionReport>
 */
class LoanRequestCorrectionReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loan_request_id' => LoanRequest::factory(),
            'user_id' => AppUser::factory(),
            'issue_description' => fake()->paragraph(),
            'correct_information' => fake()->paragraph(),
            'supporting_note' => fake()->optional()->sentence(),
            'status' => LoanRequestCorrectionReport::STATUS_OPEN,
            'resolved_by' => null,
            'resolved_at' => null,
            'dismissed_by' => null,
            'dismissed_at' => null,
            'admin_notes' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => [
            'status' => LoanRequestCorrectionReport::STATUS_OPEN,
            'resolved_by' => null,
            'resolved_at' => null,
            'dismissed_by' => null,
            'dismissed_at' => null,
            'admin_notes' => null,
        ]);
    }

    public function dismissed(?int $dismissedBy = null): static
    {
        return $this->state(fn (): array => [
            'status' => LoanRequestCorrectionReport::STATUS_DISMISSED,
            'dismissed_by' => $dismissedBy,
            'dismissed_at' => now(),
            'resolved_by' => null,
            'resolved_at' => null,
        ]);
    }
}
