<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PaymongoReconciliationUpdateRequest extends FormRequest
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
            'desktop_reference_no' => ['nullable', 'string', 'max:100'],
            'official_receipt_no' => ['nullable', 'string', 'max:100'],
            'reconciliation_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'desktop_reference_no' => $this->normalizeNullableString(
                $this->input('desktop_reference_no'),
            ),
            'official_receipt_no' => $this->normalizeNullableString(
                $this->input('official_receipt_no'),
            ),
            'reconciliation_notes' => $this->normalizeNullableString(
                $this->input('reconciliation_notes'),
            ),
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
