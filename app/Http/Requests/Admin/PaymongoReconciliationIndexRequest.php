<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymongoLoanPayment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymongoReconciliationIndexRequest extends FormRequest
{
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'all',
                    PaymongoLoanPayment::STATUS_PENDING,
                    PaymongoLoanPayment::STATUS_PAID,
                    PaymongoLoanPayment::STATUS_FAILED,
                    PaymongoLoanPayment::STATUS_CANCELLED,
                    PaymongoLoanPayment::STATUS_EXPIRED,
                ]),
            ],
            'reconciliation_status' => [
                'nullable',
                'string',
                Rule::in([
                    'all',
                    PaymongoLoanPayment::RECONCILIATION_UNRECONCILED,
                    PaymongoLoanPayment::RECONCILIATION_RECONCILED,
                ]),
            ],
            'search' => ['nullable', 'string', 'max:255'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->normalizeNullableString($this->input('search')),
        ]);
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
