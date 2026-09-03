<?php

namespace App\Http\Requests\Workflow;

use App\LoanPaydayOption;
use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanRequestWorkflowApproveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && $this->loanRequest instanceof LoanRequest
            && $user->can('approve', $this->loanRequest)
            && $this->statusValue($this->loanRequest) === LoanRequestStatus::RecommendedForApproval->value;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'approved_amount' => ['required', 'numeric', 'min:1'],
            'approved_term' => ['required', 'integer', 'min:1', 'max:360'],
            'approved_interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            // Not constrained to Rule::in(LoanPaydayOption) -- confirmed by an
            // existing test that a manager can approve with a free-text
            // custom schedule ("15th & 30th") overriding the recommended
            // frequency, which the fixed enum doesn't cover.
            'approved_payment_frequency' => ['required', 'string', 'max:255'],
            'approval_remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function statusValue(LoanRequest $loanRequest): string
    {
        return $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;
    }
}
