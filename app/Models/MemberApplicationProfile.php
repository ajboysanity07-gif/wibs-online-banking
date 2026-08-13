<?php

namespace App\Models;

use App\LoanCivilStatus;
use App\LoanReleaseMethod;
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
     * Legacy WMASTER placeholder values that mean "no data", not real values.
     */
    private const BLANK_PLACEHOLDER_VALUES = ['na', 'n/a'];

    /**
     * Whether $value should be treated as missing: null, blank, or a legacy
     * WMASTER placeholder like "NA"/"N/A".
     */
    private static function isBlankOrPlaceholder(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        $trimmed = trim($value);

        return $trimmed === '' || in_array(strtolower($trimmed), self::BLANK_PLACEHOLDER_VALUES, true);
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'nickname',
        'birthplace',
        'birthplace_city',
        'birthplace_province',
        'birthplace_barangay',
        'educational_attainment',
        'length_of_stay',
        'home_address',
        'home_address1',
        'home_address_barangay',
        'home_address2',
        'home_address3',
        'home_address_zip',
        'number_of_children',
        'civil_status',
        'housing_status',
        'spouse_name',
        'spouse_age',
        'spouse_birthdate',
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
            'birthplace_barangay',
            'educational_attainment',
            'length_of_stay',
            'home_address',
            'home_address1',
            'home_address_barangay',
            'home_address2',
            'home_address3',
            'home_address_zip',
            'number_of_children',
            'civil_status',
            'housing_status',
            'spouse_name',
            'spouse_age',
            'spouse_birthdate',
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
     * Bank & Payout fields that always gate starting/submitting a loan
     * request, regardless of release_method. Everything else -- the base
     * payout account fields, ATM number, and release account fields -- is
     * only required once it's actually relevant, see
     * conditionallyRequiredBankFields().
     *
     * @return list<string>
     */
    public static function loanPrerequisiteBankFields(): array
    {
        return [
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
            ...$this->conditionallyRequiredBankFields(),
            'source_of_fund_wealth',
            'id_type',
            'id_number',
            'height_cm',
            'weight_kg',
        ] as $field) {
            $value = $this->getAttribute($field);

            if (self::isBlankOrPlaceholder($value)) {
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
            'civil_status',
            'housing_status',
            'spouse_name',
            'spouse_birthdate',
            'employment_type',
            'employer_business_name',
            'employer_business_address_barangay',
            'current_position',
            'gross_monthly_income',
            'payday',
            ...self::loanPrerequisiteBankFields(),
        ];
    }

    /**
     * Bank & Payout fields required only once the member has chosen a
     * release_method that needs them -- mirrors the Rule::requiredIf
     * conditions already enforced in ProfileUpdateRequest/LoanRequestStoreRequest.
     *
     * @return list<string>
     */
    private function conditionallyRequiredBankFields(): array
    {
        $releaseMethod = trim((string) ($this->release_method ?? ''));
        $required = [];

        if ($releaseMethod === LoanReleaseMethod::Atm->value) {
            $required[] = 'payout_atm_number';
            $required[] = 'payout_atm_holder_name';
        }

        if ($releaseMethod === LoanReleaseMethod::BankTransfer->value) {
            array_push(
                $required,
                'payout_bank_name',
                'payout_account_name',
                'payout_account_number',
                'payout_account_type',
            );
        }

        return $required;
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

    /**
     * Spouse fields don't apply to Single members -- skipped in
     * missingRequiredFields() when the effective civil status is Single.
     *
     * @return list<string>
     */
    public static function spouseFieldsOptionalWhenSingle(): array
    {
        return ['spouse_name', 'spouse_birthdate'];
    }

    /**
     * @param  array<string, mixed>  $wmasterOverrides  Values sourced from the
     *                                                  core-banking record (e.g. civil_status, housing_status), used to
     *                                                  credit fields the member hasn't self-reported but wmaster already has.
     */
    public function hasRequiredFields(array $wmasterOverrides = []): bool
    {
        return $this->missingRequiredFields($wmasterOverrides) === [];
    }

    /**
     * @param  array<string, mixed>  $wmasterOverrides
     * @return list<string>
     */
    public function missingRequiredFields(array $wmasterOverrides = []): array
    {
        $missing = [];
        $isPensioner = trim((string) ($this->employment_type ?? '')) === self::PENSIONER_EMPLOYMENT_TYPE;
        $effectiveCivilStatus = LoanCivilStatus::normalize($wmasterOverrides['civil_status'] ?? $this->civil_status ?? '');
        $spouseNotApplicable = $effectiveCivilStatus !== null
            && in_array($effectiveCivilStatus, LoanCivilStatus::spouseNotApplicableValues(), true);

        $optional = [
            ...($isPensioner ? self::pensionerOptionalFields() : []),
            ...($spouseNotApplicable ? self::spouseFieldsOptionalWhenSingle() : []),
        ];

        foreach ([...self::completionRequiredFields(), ...$this->conditionallyRequiredBankFields()] as $field) {
            if (in_array($field, $optional, true)) {
                continue;
            }

            $value = array_key_exists($field, $wmasterOverrides)
                ? $wmasterOverrides[$field]
                : $this->getAttribute($field);

            if (self::isBlankOrPlaceholder($value)) {
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
            'civil_status' => 'Civil status',
            'housing_status' => 'Housing status',
            'spouse_name' => 'Spouse name',
            'spouse_birthdate' => 'Spouse birthdate',
            'employment_type' => 'Employment type',
            'employer_business_name' => 'Employer or business name',
            'employer_business_address_barangay' => 'Employer address barangay',
            'current_position' => 'Current position',
            'gross_monthly_income' => 'Gross monthly income',
            'payday' => 'Payday',
            'payout_bank_name' => 'Bank name',
            'payout_account_name' => 'Account name',
            'payout_account_number' => 'Account number',
            'payout_account_type' => 'Account type',
            'release_method' => 'Release method',
            'payout_atm_number' => 'ATM card number',
            'payout_atm_holder_name' => 'ATM card holder name',
            'source_of_fund_wealth' => 'Source of fund / wealth',
            'id_type' => 'Government ID type',
            'id_type_other' => 'Government ID type (specify)',
            'id_number' => 'ID number',
            'height_cm' => 'Height',
            'weight_kg' => 'Weight',
        ];
    }

    /**
     * @param  array<string, mixed>  $wmasterOverrides
     * @return list<string>
     */
    public function missingRequiredFieldLabels(array $wmasterOverrides = []): array
    {
        $labels = self::completionRequiredFieldLabels();

        return array_values(array_map(
            static fn (string $field): string => $labels[$field] ?? $field,
            $this->missingRequiredFields($wmasterOverrides),
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
            'spouse_birthdate' => 'date',
            'profile_completed_at' => 'datetime',
        ];
    }
}
