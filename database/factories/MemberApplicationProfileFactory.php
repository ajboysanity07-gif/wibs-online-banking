<?php

namespace Database\Factories;

use App\LoanInstitutionalEmployerCategory;
use App\LoanPaymentOption;
use App\LoanReleaseMethod;
use App\Models\AppUser;
use App\Models\MemberApplicationProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberApplicationProfile>
 */
class MemberApplicationProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => AppUser::factory(),
            'nickname' => null,
            'birthplace' => null,
            'birthplace_city' => null,
            'birthplace_province' => null,
            'educational_attainment' => null,
            'length_of_stay' => null,
            'number_of_children' => null,
            'spouse_name' => null,
            'spouse_age' => null,
            'spouse_cell_no' => null,
            'employment_type' => null,
            'employer_business_name' => null,
            'employer_business_address' => null,
            'employer_business_address1' => null,
            'employer_business_address2' => null,
            'employer_business_address3' => null,
            'telephone_no' => null,
            'current_position' => null,
            'nature_of_business' => null,
            'years_in_work_business' => null,
            'gross_monthly_income' => null,
            'payday' => null,
            'profile_completed_at' => null,
        ];
    }

    public function withLoanPrerequisites(): static
    {
        return $this
            ->state(fn () => [
                'release_method' => LoanReleaseMethod::BankTransfer->value,
                'source_of_fund_wealth' => 'Salary',
                'id_type' => 'TIN',
                'id_type_other' => null,
                'id_number' => fake()->numerify('###-###-###'),
                'height_cm' => (string) fake()->numberBetween(150, 190),
                'weight_kg' => (string) fake()->numberBetween(45, 95),
                // Harmless for exempt employment types (Pensioner/Self
                // Employed/OFW); satisfies the requirement whenever
                // completed()'s randomly-picked employment_type is Private
                // or Government.
                'institutional_employer_category' => LoanInstitutionalEmployerCategory::Lgu->value,
            ])
            ->afterCreating(fn (MemberApplicationProfile $profile) => $this->attachSavedAccount($profile));
    }

    public function completed(): static
    {
        return $this
            ->state(function () {
                $birthplaceCity = fake()->city();
                $birthplaceProvince = fake()->state();

                return [
                    'birthplace' => sprintf('%s, %s', $birthplaceCity, $birthplaceProvince),
                    'birthplace_city' => $birthplaceCity,
                    'birthplace_province' => $birthplaceProvince,
                    'educational_attainment' => fake()->randomElement(['High School', 'College', 'Vocational']),
                    'length_of_stay' => fake()->randomElement(['1 year', '2 years', '5 years']),
                    'home_address1' => fake()->streetAddress(),
                    'home_address_barangay' => fake()->city(),
                    'home_address2' => fake()->city(),
                    'home_address3' => fake()->state(),
                    'civil_status' => 'Married',
                    'housing_status' => 'OWNED',
                    'spouse_name' => fake()->name(),
                    'spouse_birthdate' => fake()->date(),
                    'employment_type' => fake()->randomElement(['Private', 'Government', 'Self Employed']),
                    // Harmless when the randomly-picked employment_type above
                    // is exempt (Self Employed/Pensioner/OFW); satisfies the
                    // requirement whenever it lands on Private or Government.
                    'institutional_employer_category' => LoanInstitutionalEmployerCategory::Lgu->value,
                    'employer_business_name' => fake()->company(),
                    'employer_business_address_barangay' => fake()->city(),
                    'current_position' => fake()->jobTitle(),
                    'gross_monthly_income' => fake()->randomFloat(2, 1000, 50000),
                    'payday' => fake()->randomElement(['15', '30', '15/30']),
                    'release_method' => LoanReleaseMethod::BankTransfer->value,
                    'profile_completed_at' => now(),
                ];
            })
            ->afterCreating(fn (MemberApplicationProfile $profile) => $this->attachSavedAccount($profile));
    }

    private function attachSavedAccount(MemberApplicationProfile $profile): void
    {
        $needsAccount = in_array($profile->release_method, [
            LoanReleaseMethod::Atm->value,
            LoanReleaseMethod::BankTransfer->value,
        ], true) || $profile->payment_option === LoanPaymentOption::AtmDeduction->value;

        if (! $needsAccount) {
            return;
        }

        $account = $profile->paymentAccounts()->create([
            'label' => 'Primary',
            'bank_name' => 'BDO',
            'account_name' => fake()->name(),
            'account_number' => fake()->numerify('##########'),
            'account_type' => 'Savings',
            'atm_number' => fake()->numerify('############'),
            'bank_branch' => null,
            'atm_holder_name' => null,
        ]);

        if ($profile->release_saved_account_id === null) {
            $profile->forceFill(['release_saved_account_id' => $account->id])->save();
        }

        if ($profile->payment_saved_account_id === null) {
            $profile->forceFill(['payment_saved_account_id' => $account->id])->save();
        }
    }
}
