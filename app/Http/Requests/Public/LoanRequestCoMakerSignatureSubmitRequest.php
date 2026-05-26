<?php

namespace App\Http\Requests\Public;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LoanRequestCoMakerSignatureSubmitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'consent' => ['accepted'],
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
            'consent.accepted' => 'Please confirm your consent before signing.',
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

            if ($decoded === false) {
                $validator->errors()->add(
                    'signature_data',
                    'Please provide a valid PNG signature.',
                );
            }
        });
    }
}
