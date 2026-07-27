<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Client\LoanRequestStoreRequest;
use App\LoanReleaseMethod;
use Illuminate\Validation\Rule;

class LoanRequestCorrectionRequest extends LoanRequestStoreRequest
{
    /**
     * @var list<string>
     */
    private const FORBIDDEN_FIELDS = [
        'status',
        'approved_amount',
        'approved_term',
        'decision_notes',
        'reviewed_by',
        'reviewed_at',
        'submitted_at',
        'user_id',
        'acctno',
        'reference',
        'undertaking_accepted',
        'loan_type_label_snapshot',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->adminProfile !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['undertaking_accepted']);

        $rules['insurance'] = ['sometimes', 'array:beneficiary_primary_name,beneficiary_primary_relationship,beneficiary_primary_birthdate,beneficiary_secondary_name,beneficiary_secondary_relationship,beneficiary_secondary_birthdate'];
        $rules['insurance.beneficiary_primary_name'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['insurance.beneficiary_primary_relationship'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['insurance.beneficiary_primary_birthdate'] = ['sometimes', 'nullable', 'date'];
        $rules['insurance.beneficiary_secondary_name'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['insurance.beneficiary_secondary_relationship'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['insurance.beneficiary_secondary_birthdate'] = ['sometimes', 'nullable', 'date'];
        $rules['health'] = ['sometimes', 'array:health_smoker,health_hypertension,health_diabetes,health_recent_hospitalization,health_declaration_notes'];
        $rules['health.health_smoker'] = ['sometimes', 'boolean'];
        $rules['health.health_hypertension'] = ['sometimes', 'boolean'];
        $rules['health.health_diabetes'] = ['sometimes', 'boolean'];
        $rules['health.health_recent_hospitalization'] = ['sometimes', 'boolean'];
        $rules['health.health_declaration_notes'] = ['sometimes', 'nullable', 'string', 'max:1000'];
        $rules['banking'] = ['sometimes', 'array:payout_bank_name,payout_account_name,payout_account_number,payout_account_type,release_method,payout_atm_number'];
        $rules['banking.release_method'] = ['sometimes', 'nullable', 'string', 'max:255', Rule::in(array_column(LoanReleaseMethod::cases(), 'value'))];
        $rules['banking.payout_bank_name'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['banking.payout_account_name'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['banking.payout_account_number'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['banking.payout_account_type'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['banking.payout_atm_number'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['barangay'] = ['sometimes', 'array:barangay_official_designation,barangay_agency_name,barangay_agency_address'];
        $rules['barangay.barangay_official_designation'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['barangay.barangay_agency_name'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['barangay.barangay_agency_address'] = ['sometimes', 'nullable', 'string', 'max:255'];
        $rules['declarations'] = ['sometimes', 'array:declaration_existing_loans,declaration_pending_cases,declaration_truth_confirmation,declaration_data_privacy_consent'];
        $rules['declarations.declaration_existing_loans'] = ['sometimes', 'boolean'];
        $rules['declarations.declaration_pending_cases'] = ['sometimes', 'boolean'];
        $rules['declarations.declaration_truth_confirmation'] = ['sometimes', 'boolean'];
        $rules['declarations.declaration_data_privacy_consent'] = ['sometimes', 'boolean'];
        $rules['change_reason'] = ['required', 'string', 'max:1000'];

        foreach (self::FORBIDDEN_FIELDS as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
