<?php

namespace Database\Factories;

use App\Models\AuthorityToDeductInstitutionContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthorityToDeductInstitutionContact>
 */
class AuthorityToDeductInstitutionContactFactory extends Factory
{
    protected $model = AuthorityToDeductInstitutionContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $institutionName = $this->faker->company();

        return [
            'institution_name' => $institutionName,
            'institution_name_normalized' => AuthorityToDeductInstitutionContact::normalizeInstitutionName($institutionName),
            'officer_1_name' => $this->faker->name(),
            'officer_1_title' => 'HR Manager',
            'officer_2_name' => null,
            'officer_2_title' => null,
        ];
    }
}
