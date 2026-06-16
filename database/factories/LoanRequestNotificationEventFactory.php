<?php

namespace Database\Factories;

use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestNotificationEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanRequestNotificationEvent>
 */
class LoanRequestNotificationEventFactory extends Factory
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
            'event_type' => 'awaiting_member_information',
            'channel' => 'sms',
            'recipient_user_id' => AppUser::factory(),
            'recipient' => '09170000000',
            'result' => 'queued',
            'queued_at' => now(),
            'attempt_count' => 0,
            'retry_count' => 0,
            'reminder_attempts' => 0,
            'metadata_json' => [
                'title' => 'Loan request update',
                'message' => 'A member action is pending.',
            ],
        ];
    }
}
