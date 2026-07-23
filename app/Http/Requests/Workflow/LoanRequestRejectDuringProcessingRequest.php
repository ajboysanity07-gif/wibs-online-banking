<?php

namespace App\Http\Requests\Workflow;

use App\Models\AppUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanRequestRejectDuringProcessingRequest extends FormRequest
{
    public const REJECTION_CATEGORIES = [
        'Incomplete documentation',
        'Insufficient income / GNTHP shortfall',
        'Failed credit/background check',
        'Applicant withdrew',
        'Ineligible loan security',
        'Duplicate application',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof AppUser
            && $this->loanRequest !== null
            && $user->can('rejectDuringProcessing', $this->loanRequest);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rejection_category' => ['required', 'string', Rule::in([...self::REJECTION_CATEGORIES, 'Other'])],
            'rejection_category_other' => ['required_if:rejection_category,Other', 'nullable', 'string', 'max:255'],
            'member_visible_reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * Resolve the category value to persist -- the free-text value when
     * "Other" is selected, otherwise the chosen fixed category.
     */
    public function resolvedRejectionCategory(): string
    {
        $category = $this->validated('rejection_category');

        return $category === 'Other'
            ? trim($this->validated('rejection_category_other'))
            : $category;
    }
}
