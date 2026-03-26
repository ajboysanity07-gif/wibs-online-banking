<?php

namespace App\Http\Requests\Client;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberLoanPaymentsExportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $user->loadMissing('adminProfile');

        return $user->adminProfile === null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', 'string', Rule::in(['pdf', 'csv', 'xlsx'])],
            'download' => ['nullable', 'boolean'],
            'range' => [
                'nullable',
                'string',
                Rule::in([
                    'current_month',
                    'current_year',
                    'last_30_days',
                    'all',
                    'custom',
                ]),
            ],
            'start' => [
                'nullable',
                'date_format:Y-m-d',
                'required_if:range,custom',
            ],
            'end' => [
                'nullable',
                'date_format:Y-m-d',
                'required_if:range,custom',
                'after_or_equal:start',
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'format.in' => 'Choose a valid export format.',
            'start.date_format' => 'Start date must use the YYYY-MM-DD format.',
            'start.required_if' => 'Start date is required for a custom range.',
            'end.date_format' => 'End date must use the YYYY-MM-DD format.',
            'end.required_if' => 'End date is required for a custom range.',
            'end.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }
}
