<?php

namespace App\Http\Requests;

use App\Models\AppUser;
use App\Services\LoanRequests\LoanWorkflowWorkspaceService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkspaceSwitchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof AppUser;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'workspace' => [
                'required',
                'string',
                Rule::in([
                    LoanWorkflowWorkspaceService::WORKSPACE_MEMBER,
                    LoanWorkflowWorkspaceService::WORKSPACE_STAFF,
                ]),
            ],
        ];
    }
}
