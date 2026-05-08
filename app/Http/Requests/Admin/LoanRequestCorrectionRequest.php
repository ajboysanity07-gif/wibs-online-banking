<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Client\LoanRequestStoreRequest;

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

        $rules['change_reason'] = ['required', 'string', 'max:1000'];

        foreach (self::FORBIDDEN_FIELDS as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }
}
