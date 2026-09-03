<?php

namespace App\Models;

use App\LoanCivilStatus;
use App\LoanInstitutionalEmployerCategory;
use App\LoanPaymentOption;
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

    public const PENSIONER_EMPLOYMENT_TYPE = 'Pensioner';

    public const SELF_EMPLOYED_EMPLOYMENT_TYPE = 'Self Employed';

    public const ID_TYPE_OPTIONS = ['SSS', 'GSIS', 'TIN', 'Phil ID', 'Others'];

    /**
     * Mirrors EMPLOYMENT_TYPE_OPTIONS in loan-request-fields.tsx /
     * work-tab.tsx -- kept in sync manually since the frontend also needs
     * per-option labels the backend doesn't care about.
     */
    public const EMPLOYMENT_TYPE_OPTIONS = [
        'Private',
        'Government',
        self::SELF_EMPLOYED_EMPLOYMENT_TYPE,
        self::PENSIONER_EMPLOYMENT_TYPE,
        'OFW',
    ];

    /** Mirrors EDUCATIONAL_ATTAINMENT_OPTIONS in loan-request-fields.tsx / personal-tab.tsx. */
    public const EDUCATIONAL_ATTAINMENT_OPTIONS = [
        'Elementary',
        'High School',
        'Vocational',
        'College',
        'Postgraduate',
    ];

    /**
     * Compares an `employment_type` value against a canonical option
     * (self-employed/pensioner) tolerating real-world variants like
     * "Self-Employed" (hyphen) or stray whitespace that a strict `===`
     * check would silently reject.
     */
    public static function employmentTypeMatches(?string $value, string $canonical): bool
    {
        $normalize = static fn (string $text): string => strtolower(
            (string) preg_replace('/[\s-]+/', ' ', trim($text)),
        );

        return $normalize((string) $value) === $normalize($canonical);
    }

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
        'institutional_employer_category',
        'years_in_work_business',
        'employer_date_employed',
        'gross_monthly_income',
        'payday',
        'release_method',
        'release_saved_account_id',
        'payment_option',
        'payment_saved_account_id',
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

    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(MemberPaymentAccount::class);
    }

    public function releaseSavedAccount(): BelongsTo
    {
        return $this->belongsTo(MemberPaymentAccount::class, 'release_saved_account_id');
    }

    public function paymentSavedAccount(): BelongsTo
    {
        return $this->belongsTo(MemberPaymentAccount::class, 'payment_saved_account_id');
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

        return LocationComposer::recomposeLegacyBirthplace($this->birthplace);
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

        return LocationComposer::recomposeLegacyAddress($this->employer_business_address);
    }

    public function composedHomeAddress(): string
    {
        $composed = LocationComposer::composeUnique(
            $this->home_address1,
            $this->home_address2,
            $this->home_address3,
            $this->home_address_barangay,
        );

        if ($composed !== '') {
            return $composed;
        }

        return LocationComposer::recomposeLegacyAddress($this->home_address);
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
            'institutional_employer_category',
            'years_in_work_business',
            'employer_date_employed',
            'gross_monthly_income',
            'payday',
        ];
    }

    /**
     * Release (disbursement) and payment (repayment) fields reused to
     * pre-fill the loan-request wizard's "Loan Disbursement & Repayment"
     * step and written back on validated loan submission. The account
     * details themselves no longer live on the profile -- only the method
     * and the member's chosen saved account (member_payment_accounts) are
     * stored, and resolved to a frozen copy at loan-manager approval.
     *
     * @return list<string>
     */
    public static function payoutBankFields(): array
    {
        return [
            'release_method',
            'release_saved_account_id',
            'payment_option',
            'payment_saved_account_id',
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
     * release_method / payment_option that needs a bank account -- mirrors
     * the Rule::requiredIf conditions already enforced in
     * ProfileUpdateRequest and LoanRequestStoreRequest. A saved
     * member_payment_accounts row is the single source of truth; the old
     * free-text payout/payment columns no longer exist.
     *
     * @return list<string>
     */
    private function conditionallyRequiredBankFields(): array
    {
        $releaseMethod = trim((string) ($this->release_method ?? ''));
        $paymentOption = trim((string) ($this->payment_option ?? ''));
        $required = [];

        if (in_array($releaseMethod, [LoanReleaseMethod::Atm->value, LoanReleaseMethod::BankTransfer->value], true)) {
            $required[] = 'release_saved_account_id';
        }

        if ($paymentOption === LoanPaymentOption::AtmDeduction->value) {
            $required[] = 'payment_saved_account_id';
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
        $isPensioner = self::employmentTypeMatches($this->employment_type, self::PENSIONER_EMPLOYMENT_TYPE);
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
            'release_method' => 'Release method',
            'release_saved_account_id' => 'Saved payout account',
            'payment_option' => 'Payment option',
            'payment_saved_account_id' => 'Saved repayment account',
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
            'employer_date_employed' => 'date',
            'profile_completed_at' => 'datetime',
            'institutional_employer_category' => LoanInstitutionalEmployerCategory::class,
        ];
    }
}
