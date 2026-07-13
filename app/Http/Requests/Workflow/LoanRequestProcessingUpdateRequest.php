<?php

namespace App\Http\Requests\Workflow;

use App\Models\AppUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanRequestProcessingUpdateRequest extends FormRequest
{
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
            'reason' => ['required', 'string', 'max:1000'],
            'information_source' => ['required', 'string', 'max:255'],
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
            'applicant.address2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.address3' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.cell_no' => ['sometimes', 'nullable', 'string', 'max:20'],
            'applicant.employment_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.employer_business_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.employer_business_address1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.employer_business_address2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'applicant.employer_business_address3' => ['sometimes', 'nullable', 'string', 'max:255'],
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
            'co_maker_1.address2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.address3' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.cell_no' => ['sometimes', 'nullable', 'string', 'max:20'],
            'co_maker_1.employment_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.employer_business_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.employer_business_address1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.employer_business_address2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_1.employer_business_address3' => ['sometimes', 'nullable', 'string', 'max:255'],
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
            'co_maker_2.address2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.address3' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.cell_no' => ['sometimes', 'nullable', 'string', 'max:20'],
            'co_maker_2.employment_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.employer_business_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.employer_business_address1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.employer_business_address2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.employer_business_address3' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.current_position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.nature_of_business' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.years_in_work_business' => ['sometimes', 'nullable', 'string', 'max:255'],
            'co_maker_2.gross_monthly_income' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'co_maker_2.payday' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing' => ['sometimes', 'array:service_charge_rate,insurance_rate,insurance_term,loan_security_rate,documentary_stamp_rate,notarial_fee,penalty_rate_per_month,notarial_venue,witness_one_name,witness_two_name,barangay_official_name,barangay_official_title,guaranteed_net_take_home_pay'],
            'processing.service_charge_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processing.insurance_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processing.insurance_term' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:360'],
            'processing.loan_security_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processing.documentary_stamp_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processing.notarial_fee' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processing.penalty_rate_per_month' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'processing.notarial_venue' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.witness_one_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.witness_two_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.barangay_official_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.barangay_official_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'processing.guaranteed_net_take_home_pay' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'recommended_amount' => ['sometimes', 'nullable', 'numeric', 'min:1'],
            'recommended_term' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:360'],
            'recommended_interest_rate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'recommended_payment_frequency' => ['sometimes', 'nullable', 'string', Rule::in(self::PAYDAY_OPTIONS)],
            'recommendation_remarks' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
