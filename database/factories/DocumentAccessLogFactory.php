<?php

namespace Database\Factories;

use App\Models\AppUser;
use App\Models\DocumentAccessLog;
use App\Models\LoanRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentAccessLog>
 */
class DocumentAccessLogFactory extends Factory
{
    protected $model = DocumentAccessLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => AppUser::factory(),
            'loan_request_id' => LoanRequest::factory(),
            'document_key' => fake()->randomElement([
                'application_form', 'grepalife', 'loan_information',
                'plan_of_payment', 'promissory_note',
            ]),
            'action' => fake()->randomElement([DocumentAccessLog::ACTION_VIEW, DocumentAccessLog::ACTION_DOWNLOAD]),
            'accessed_at' => now()->subMinutes(fake()->numberBetween(1, 10080)),
        ];
    }
}
