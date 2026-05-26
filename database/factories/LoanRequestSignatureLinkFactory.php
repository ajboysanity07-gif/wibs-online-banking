<?php

namespace Database\Factories;

use App\LoanRequestPersonRole;
use App\Models\LoanRequestPerson;
use App\Models\LoanRequestSignatureLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LoanRequestSignatureLink>
 */
class LoanRequestSignatureLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loan_request_person_id' => LoanRequestPerson::factory()
                ->role(LoanRequestPersonRole::CoMakerOne),
            'loan_request_id' => static fn (array $attributes): ?int => LoanRequestPerson::query()
                ->whereKey($attributes['loan_request_person_id'] ?? null)
                ->value('loan_request_id'),
            'role' => LoanRequestPersonRole::CoMakerOne,
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addHours(72),
            'signed_at' => null,
            'revoked_at' => null,
            'ip_address' => null,
            'user_agent' => null,
        ];
    }

    public function forPerson(LoanRequestPerson $person): static
    {
        return $this->state(fn (): array => [
            'loan_request_person_id' => $person->id,
            'loan_request_id' => $person->loan_request_id,
            'role' => $person->role,
        ]);
    }

    public function role(LoanRequestPersonRole $role): static
    {
        return $this->state(fn (): array => [
            'role' => $role,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subHour(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'revoked_at' => now(),
        ]);
    }

    public function signed(): static
    {
        return $this->state(fn (): array => [
            'signed_at' => now(),
        ]);
    }
}
