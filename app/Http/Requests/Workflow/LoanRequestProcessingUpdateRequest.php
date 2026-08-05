<?php

namespace App\Http\Requests\Workflow;

use App\Models\AppUser;
use App\Models\LoanRequestChange;
use App\Rules\ValidPostalCode;
use App\Rules\ValidPsgcBarangay;
use App\Rules\ValidPsgcLocality;
use App\Rules\ValidPsgcProvince;
use App\Services\LoanRequests\LoanManagerWitnessResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanRequestProcessingUpdateRequest extends FormRequest
{
    public function __construct(
        private readonly LoanManagerWitnessResolver $loanManagerWitnessResolver,
        array $query = [],
        array $request = [],
        array $attributes = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        $content = null,
    ) {
        parent::__construct(
            $query,
            $request,
            $attributes,
            $cookies,
            $files,
            $server,
            $content,
        );
    }

    /**
     * Canonical payment-frequency values, mirrors the frontend's
     * PAYDAY_OPTIONS in resources/js/components/loan-request/loan-request-fields.tsx.
     *
     * @var array<int, string>
     */
    private const PAYDAY_OPTIONS = [
        'Weekly',
        '15th',
        '30th',
        '15th & 30th',
        'Bi-Weekly',
        'Monthly',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && $this->loanRequest !== null
            && $user->can('updateProcessingDetails', $this->loanRequest);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => [
                Rule::requiredIf(fn (): bool => $this->loanRequest !== null
                    && LoanRequestChange::hasProcessingUpdate($this->loanRequest)),
                'nullable', 'string', 'max:1000',
            ],
            'loan_request' => ['sometimes', 'array:typecode,requested_amount,requested_term,loan_purpose,availment_status'],
            'loan_request.typecode' => ['sometimes', 'string', 'max:255'],
            'loan_request.requested_amount' => ['sometimes', 'numeric', 'min:1'],
            'loan_request.requested_term' => ['sometimes', 'integer', 'min:1', 'max:360'],
            'loan_request.loan_purpose' => ['sometimes', 'string', 'max:255'],
            'loan_request.availment_status' => [
                'sometimes',
                'string',
                Rule::in(['New', 'Re-Loan', 'Restructured']),
            ],
            'applicant' => ['sometimes', 'array'],
            'co_maker_1' => ['sometimes', 'array'],
            'co_maker_2' => ['sometimes', 'array'],
            'applicant.first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.middle_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.birthdate' => ['sometimes', 'nullable', 'date'],
            'applicant.birthplace_city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.birthplace_province' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.address1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.address_barangay' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcBarangay($this->input('applicant.address2'), $this->input('applicant.address3'))],
            'applicant.address2' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcLocality],
            'applicant.address3' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcProvince],
            'applicant.address_zip' => ['sometimes', 'nullable', 'string', 'max:20', new ValidPostalCode],
            'applicant.cell_no' => ['sometimes', 'nullable', 'string', 'max:20'],
            'applicant.employment_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.employer_business_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.employer_business_address1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.employer_business_address_barangay' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcBarangay($this->input('applicant.employer_business_address2'), $this->input('applicant.employer_business_address3'))],
            'applicant.employer_business_address2' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcLocality],
            'applicant.employer_business_address3' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcProvince],
            'applicant.employer_business_address_zip' => ['sometimes', 'nullable', 'string', 'max:20', new ValidPostalCode],
            'applicant.current_position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.nature_of_business' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.years_in_work_business' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.gross_monthly_income' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'applicant.payday' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.middle_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.birthdate' => ['sometimes', 'nullable', 'date'],
            'co_maker_1.birthplace_city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.birthplace_province' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.address1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.address_barangay' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcBarangay($this->input('co_maker_1.address2'), $this->input('co_maker_1.address3'))],
            'co_maker_1.address2' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcLocality],
            'co_maker_1.address3' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcProvince],
            'co_maker_1.address_zip' => ['sometimes', 'nullable', 'string', 'max:20', new ValidPostalCode],
            'co_maker_1.cell_no' => ['sometimes', 'nullable', 'string', 'max:20'],
            'co_maker_1.employment_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.employer_business_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.employer_business_address1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.employer_business_address_barangay' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcBarangay($this->input('co_maker_1.employer_business_address2'), $this->input('co_maker_1.employer_business_address3'))],
            'co_maker_1.employer_business_address2' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcLocality],
            'co_maker_1.employer_business_address3' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcProvince],
            'co_maker_1.employer_business_address_zip' => ['sometimes', 'nullable', 'string', 'max:20', new ValidPostalCode],
            'co_maker_1.current_position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.nature_of_business' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.years_in_work_business' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.gross_monthly_income' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'co_maker_1.payday' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.middle_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.birthdate' => ['sometimes', 'nullable', 'date'],
            'co_maker_2.birthplace_city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.birthplace_province' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.address1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.address_barangay' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcBarangay($this->input('co_maker_2.address2'), $this->input('co_maker_2.address3'))],
            'co_maker_2.address2' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcLocality],
            'co_maker_2.address3' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcProvince],
            'co_maker_2.address_zip' => ['sometimes', 'nullable', 'string', 'max:20', new ValidPostalCode],
            'co_maker_2.cell_no' => ['sometimes', 'nullable', 'string', 'max:20'],
            'co_maker_2.employment_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.employer_business_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.employer_business_address1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.employer_business_address_barangay' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcBarangay($this->input('co_maker_2.employer_business_address2'), $this->input('co_maker_2.employer_business_address3'))],
            'co_maker_2.employer_business_address2' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcLocality],
            'co_maker_2.employer_business_address3' => ['sometimes', 'nullable', 'string', 'max:255', new ValidPsgcProvince],
            'co_maker_2.employer_business_address_zip' => ['sometimes', 'nullable', 'string', 'max:20', new ValidPostalCode],
            'co_maker_2.current_position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.nature_of_business' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.years_in_work_business' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.gross_monthly_income' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'co_maker_2.payday' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing' => ['sometimes', 'array:service_charge_rate,insurance_rate,insurance_term,loan_security_rate,savings_rate,documentary_stamp_rate,notarial_fee,other_charges_amount,other_charges_description,penalty_rate_per_month,witness_one_name,witness_two_name,witness_two_id,barangay_official_name,barangay_official_title,authority_to_deduct_institution_name,authority_to_deduct_officer_1_name,authority_to_deduct_officer_1_title,authority_to_deduct_officer_2_name,authority_to_deduct_officer_2_title,authority_to_deduct_officers_unknown,guaranteed_net_take_home_pay,deped_school_id_number,deped_deduction_amount,pension_provider,pension_bank_name,pension_atm_card_number,pension_deduction_amount,atm_salary_deduction_bank_name,atm_salary_deduction_card_number,atm_salary_deduction_amount,employer_date_employed,applicant_pep_status,applicant_pep_status_details,applicant_cycle_status,applicant_cycle_number'],
            'processing.service_charge_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processing.insurance_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processing.insurance_term' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:360'],
            'processing.loan_security_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processing.savings_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processing.documentary_stamp_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processing.notarial_fee' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processing.other_charges_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processing.other_charges_description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.penalty_rate_per_month' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processing.witness_one_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.witness_two_name' => $this->witnessTwoNameRules(),
            'processing.witness_two_id' => $this->witnessTwoIdRules(),
            'processing.barangay_official_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.barangay_official_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.authority_to_deduct_institution_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.authority_to_deduct_officer_1_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.authority_to_deduct_officer_1_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.authority_to_deduct_officer_2_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.authority_to_deduct_officer_2_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.authority_to_deduct_officers_unknown' => ['sometimes', 'nullable', 'boolean'],
            // System-computed (gross income minus amortization); can legitimately
            // go negative, and the submitted value is discarded/recomputed
            // server-side regardless — see LoanRequestProcessingService::processProcessingUpdate().
            'processing.guaranteed_net_take_home_pay' => ['sometimes', 'nullable', 'numeric'],
            'processing.deped_school_id_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.deped_deduction_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processing.pension_provider' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.pension_bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.pension_atm_card_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.pension_deduction_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processing.atm_salary_deduction_bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.atm_salary_deduction_card_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.atm_salary_deduction_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processing.employer_date_employed' => ['sometimes', 'nullable', 'date'],
            'processing.applicant_pep_status' => ['sometimes', 'nullable', 'boolean'],
            'processing.applicant_pep_status_details' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'processing.applicant_cycle_status' => ['sometimes', 'nullable', 'string', Rule::in(['New', 'Old'])],
            'processing.applicant_cycle_number' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'recommended_amount' => ['sometimes', 'nullable', 'numeric', 'min:1'],
            'recommended_term' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:360'],
            'recommended_interest_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'recommended_payment_frequency' => ['sometimes', 'nullable', 'string', Rule::in(self::PAYDAY_OPTIONS)],
        ];
    }

    /**
     * Witness 2 is the loan manager who will witness the documents. With
     * several active loan managers the processor must choose one from the
     * dropdown; with one or none the name is either auto-assigned server-side
     * or deferred to the approval fallback.
     *
     * @return array<mixed>
     */
    private function witnessTwoNameRules(): array
    {
        $managerNames = array_column(
            $this->loanManagerWitnessResolver->options(),
            'name',
        );

        if (count($managerNames) > 1) {
            return ['required', 'string', Rule::in($managerNames)];
        }

        return ['sometimes', 'nullable', 'string', 'max:255'];
    }

    /**
     * Witness 2 ID must be a valid active loan manager ID when there are
     * multiple managers to choose from.
     *
     * @return array<mixed>
     */
    private function witnessTwoIdRules(): array
    {
        $managerIds = array_column(
            $this->loanManagerWitnessResolver->options(),
            'id',
        );

        if (count($managerIds) > 1) {
            return ['required', 'integer', Rule::in($managerIds)];
        }

        return ['sometimes', 'nullable', 'integer'];
    }
}
