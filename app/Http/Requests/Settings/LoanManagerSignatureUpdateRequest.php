<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LoanManagerSignatureUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->adminProfile !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'signature_data' => [
                'required',
                'string',
                'starts_with:data:image/png;base64,',
                'max:2800000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'signature_data.required' => 'Please draw your loan manager signature before saving.',
            'signature_data.starts_with' => 'Please provide a valid PNG signature.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $signatureData = $this->input('signature_data');

            if (! is_string($signatureData)) {
                return;
            }

            $encoded = substr($signatureData, strlen('data:image/png;base64,'));
            $decoded = base64_decode($encoded, true);

            if ($decoded === false || $decoded === '') {
                $validator->errors()->add(
                    'signature_data',
                    'Please provide a valid PNG signature.',
                );

                return;
            }

            $imageMetadata = @getimagesizefromstring($decoded);

            if (
                ! is_array($imageMetadata)
                || ($imageMetadata['mime'] ?? null) !== 'image/png'
            ) {
                $validator->errors()->add(
                    'signature_data',
                    'Please provide a valid PNG signature.',
                );
            }
        });
    }
}
