<?php

namespace App\Http\Requests\Staff;

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Services\LoanRequests\LoanWorkflowWorkspaceService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanWorkflowRequestsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && app(LoanWorkflowWorkspaceService::class)->canAccess($user);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'loanType' => ['nullable', 'string', 'max:255'],
            'status' => [
                'nullable',
                'string',
                Rule::in(LoanRequestStatus::requestFilterValues()),
            ],
            'minAmount' => ['nullable', 'numeric', 'min:0'],
            'maxAmount' => ['nullable', 'numeric', 'min:0'],
            'reported' => ['nullable', 'boolean'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
