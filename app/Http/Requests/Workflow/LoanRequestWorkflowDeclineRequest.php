<?php

namespace App\Http\Requests\Workflow;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoanRequestWorkflowDeclineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && $this->loanRequest instanceof LoanRequest
            && $user->can('decline', $this->loanRequest)
            && $this->statusValue($this->loanRequest) === LoanRequestStatus::RecommendedForApproval->value;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decline_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    private function statusValue(LoanRequest $loanRequest): string
    {
        return $loanRequest->status instanceof LoanRequestStatus
            ? $loanRequest->status->value
            : (string) $loanRequest->status;
    }
}
