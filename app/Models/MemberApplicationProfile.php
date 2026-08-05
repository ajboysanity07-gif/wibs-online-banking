<?php

namespace App\Models;

use App\Support\LocationComposer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MemberApplicationProfile extends Model
{
    /** @use HasFactory<\Database\Factories\MemberApplicationProfileFactory> */
    use HasFactory;

    public const PENSIONER_EMPLOYMENT_TYPE = 'Pensioner / Retired';

    public const ID_TYPE_OPTIONS = ['SSS', 'GSIS', 'TIN', 'Phil ID', 'Others'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'nickname',
        'birthplace',
        'birthplace_city',
        'birthplace_province',
        'educational_attainment',
        'length_of_stay',
        'home_address',
        'home_address1',
        'home_address_barangay',
        'home_address2',
        'home_address3',
        'home_address_zip',
        'number_of_children',
        'spouse_name',
        'spouse_age',
        'spouse_cell_no',
        'employment_type',
        'employer_business_name',
        'employer_business_address',
        'employer_business_address1',
        'employer_business_address_barangay',
        'employer_business_address2',
        'employer_business_address3',
        'employer_business_address_zip',
        'telephone_no',
        'current_position',
        'nature_of_business',
        'years_in_work_business',
        'gross_monthly_income',
        'payday',
        'payout_bank_name',
        'payout_account_name',
        'payout_account_number',
        'payout_account_type',
        'release_method',
        'payout_atm_number',
        'payout_bank_branch',
        'payout_atm_holder_name',
        'release_uses_payout_account',
        'release_bank_name',
        'release_account_name',
        'release_account_number',
        'release_account_type',
        'beneficiary_primary_name',
        'beneficiary_primary_relationship',
        'beneficiary_primary_birthdate',
        'beneficiary_secondary_name',
        'beneficiary_secondary_relationship',
        'beneficiary_secondary_birthdate',
        'source_of_fund_wealth',
        'id_type',
        'id_type_other',
        'id_number',
        'height_cm',
        'weight_kg',
        'profile_completed_at',
    ];

    public function appUser(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id', 'user_id');
    }

    public function dependentProfile(): HasOne
    {
        return $this->hasOne(MemberDependentProfile::class);
    }

    public function coMakers(): HasMany
    {
        return $this->hasMany(MemberCoMaker::class);
    }

    public function isComplete(): bool
    {
        return $this->profile_completed_at !== null;
    }

    public function composedBirthplace(): string
    {
        $composed = LocationComposer::composeBirthplace(
            $this->birthplace_city,
            $this->birthplace_province,
        );

        if ($composed !== '') {
            return $composed;
        }

        return trim((string) $this->birthplace);
    }

    public function composedEmployerBusinessAddress(): string
    {
        $composed = LocationComposer::compose(
            $this->employer_business_address1,
            $this->employer_business_address2,
            $this->employer_business_address3,
            $this->employer_business_address_barangay,
        );

        if ($composed !== '') {
            return $composed;
        }

        return trim((string) $this->employer_business_address);
    }

    public function composedHomeAddress(): string
    {
        $composed = LocationComposer::compose(
            $this->home_address1,
            $this->home_address2,
            $this->home_address3,
            $this->home_address_barangay,
        );

        if ($composed !== '') {
            return $composed;
        }

        return trim((string) $this->home_address);
    }

    /**
     * @return list<string>
     */
    public static function fields(): array
    {
        return [
            'nickname',
            'birthplace',
            'birthplace_city',
            'birthplace_province',
            'educational_attainment',
            'length_of_stay',
            'home_address',
            'home_address1',
            'home_address_barangay',
            'home_address2',
            'home_address3',
            'home_address_zip',
            'number_of_children',
            'spouse_name',
            'spouse_age',
            'spouse_cell_no',
            'employment_type',
            'employer_business_name',
            'employer_business_address',
            'employer_business_address1',
            'employer_business_address_barangay',
            'employer_business_address2',
            'employer_business_address3',
            'employer_business_address_zip',
            'telephone_no',
            'current_position',
            'nature_of_business',
            'years_in_work_business',
            'gross_monthly_income',
            'payday',
        ];
    }

    /**
     * Payout bank fields reused to pre-fill the loan-request wizard's
     * "Bank & payout" step and written back on validated loan submission.
     *
     * @return list<string>
     */
    public static function payoutBankFields(): array
    {
        return [
            'payout_bank_name',
            'payout_account_name',
            'payout_account_number',
            'payout_account_type',
            'release_method',
            'payout_atm_number',
            'payout_bank_branch',
            'payout_atm_holder_name',
            'release_uses_payout_account',
            'release_bank_name',
            'release_account_name',
            'release_account_number',
            'release_account_type',
        ];
    }

    /**
     * Beneficiary fields reused to pre-fill the loan-request wizard's
     * "Insurance and beneficiaries" step and written back on validated loan
     * submission. Submit-only, mirroring payoutBankFields().
     *
     * @return list<string>
     */
    public static function beneficiaryFields(): array
    {
        return [
            'beneficiary_primary_name',
            'beneficiary_primary_relationship',
            'beneficiary_primary_birthdate',
            'beneficiary_secondary_name',
            'beneficiary_secondary_relationship',
            'beneficiary_secondary_birthdate',
        ];
    }

    /**
     * Source of Fund / Government ID fields reused to pre-fill the loan
     * request's prerequisite checkpoints (entry-point modal and submit-time
     * safety net) and written back on validated loan submission. Optional at
     * onboarding, same as payoutBankFields() -- required only to actually
     * take out a loan, see missingLoanPrerequisiteFields().
     *
     * @return list<string>
     */
    public static function sourceOfFundAndIdFields(): array
    {
        return [
            'source_of_fund_wealth',
            'id_type',
            'id_type_other',
            'id_number',
        ];
    }

    /**
     * Physical details (height/weight) required for the Generali Health
     * Statement, gated the same way as the Bank & Payout / ID checkpoints.
     *
     * @return list<string>
     */
    public static function physicalDetailsFields(): array
    {
        return [
            'height_cm',
            'weight_kg',
        ];
    }

    /**
     * Bank & Payout fields that gate starting/submitting a loan request.
     * Deliberately a subset of payoutBankFields() -- the ATM number, bank
     * branch, and ATM holder name stay optional secondary details even at
     * this checkpoint.
     *
     * @return list<string>
     */
    public static function loanPrerequisiteBankFields(): array
    {
        return [
            'payout_bank_name',
            'payout_account_name',
            'payout_account_number',
            'payout_account_type',
            'release_method',
        ];
    }

    /**
     * Whether this profile has everything required to start or submit a
     * loan request: Bank & Payout essentials plus Source of Fund / Government
     * ID. Deliberately separate from completionRequiredFields() -- those
     * gate onboarding account-wide, these only gate loan requests.
     */
    public function hasLoanPrerequisiteFields(): bool
    {
        return $this->missingLoanPrerequisiteFields() === [];
    }

    /**
     * @return list<string>
     */
    public function missingLoanPrerequisiteFields(): array
    {
        $missing = [];

        foreach ([
            ...self::loanPrerequisiteBankFields(),
            'source_of_fund_wealth',
            'id_type',
            'id_number',
            'height_cm',
            'weight_kg',
        ] as $field) {
            $value = $this->getAttribute($field);

            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missing[] = $field;
            }
        }

        if (
            trim((string) ($this->id_type ?? '')) === 'Others'
            && trim((string) ($this->id_type_other ?? '')) === ''
        ) {
            $missing[] = 'id_type_other';
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    public static function completionRequiredFields(): array
    {
        return [
            'birthplace_city',
            'educational_attainment',
            'length_of_stay',
            'home_address1',
            'home_address_barangay',
            'home_address2',
            'home_address3',
            'employment_type',
            'employer_business_name',
            'employer_business_address_barangay',
            'current_position',
            'gross_monthly_income',
            'payday',
        ];
    }

    /**
     * Fields that are optional when employment_type is PENSIONER_EMPLOYMENT_TYPE.
     *
     * @return list<string>
     */
    public static function pensionerOptionalFields(): array
    {
        return ['employer_business_name', 'employer_business_address_barangay', 'current_position', 'payday'];
    }

    public function hasRequiredFields(): bool
    {
        return $this->missingRequiredFields() === [];
    }

    /**
     * @return list<string>
     */
    public function missingRequiredFields(): array
    {
        $missing = [];
        $isPensioner = trim((string) ($this->employment_type ?? '')) === self::PENSIONER_EMPLOYMENT_TYPE;
        $optionalForPensioner = $isPensioner ? self::pensionerOptionalFields() : [];

        foreach (self::completionRequiredFields() as $field) {
            if (in_array($field, $optionalForPensioner, true)) {
                continue;
            }

            $value = $this->getAttribute($field);

            if ($value === null) {
                $missing[] = $field;

                continue;
            }

            if (is_string($value) && trim($value) === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * @return array<string, string>
     */
    public static function completionRequiredFieldLabels(): array
    {
        return [
            'birthplace_city' => 'Birthplace city',
            'educational_attainment' => 'Educational attainment',
            'length_of_stay' => 'Length of stay',
            'home_address1' => 'Home address (street)',
            'home_address_barangay' => 'Home address barangay',
            'home_address2' => 'Home address city/municipality',
            'home_address3' => 'Home address province',
            'employment_type' => 'Employment type',
            'employer_business_name' => 'Employer or business name',
            'employer_business_address_barangay' => 'Employer address barangay',
            'current_position' => 'Current position',
            'gross_monthly_income' => 'Gross monthly income',
            'payday' => 'Payday',
        ];
    }

    /**
     * @return list<string>
     */
    public function missingRequiredFieldLabels(): array
    {
        $labels = self::completionRequiredFieldLabels();

        return array_values(array_map(
            static fn (string $field): string => $labels[$field] ?? $field,
            $this->missingRequiredFields(),
        ));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gross_monthly_income' => 'decimal:2',
            'beneficiary_primary_birthdate' => 'date',
            'beneficiary_secondary_birthdate' => 'date',
            'profile_completed_at' => 'datetime',
            'release_uses_payout_account' => 'boolean',
        ];
    }
}
