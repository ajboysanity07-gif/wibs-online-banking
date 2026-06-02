<?php

namespace App\Http\Requests\Client;

use App\LoanRequestStatus;
use App\Models\LoanRequest;
use App\Support\LocationComposer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LoanRequestStoreRequest extends FormRequest
{
    private const HOUSING_STATUS_OPTIONS = ['OWNED', 'RENT'];

    private const CIVIL_STATUS_OPTIONS = [
        'Single',
        'Married',
        'Separated',
        'Widowed',
    ];

    private const PAYDAY_OPTIONS = [
        'Weekly',
        '15th',
        '30th',
        '15th & 30th',
        'Bi-Weekly',
        'Monthly',
    ];

    protected function prepareForValidation(): void
    {
        $payload = $this->all();

        foreach (['applicant', 'co_maker_1', 'co_maker_2'] as $key) {
            $person = $this->input($key);

            if (! is_array($person)) {
                continue;
            }

            $payload[$key] = $this->normalizePersonLocationFields($person);
        }

        $payload['applicant_signature_data'] = $this->normalizeSignatureData(
            $this->input('applicant_signature_data'),
        );
        $payload['co_maker_1_signature_data'] = $this->normalizeSignatureData(
            $this->input('co_maker_1_signature_data')
                ?? $this->input('co_maker_one_signature_data'),
        );
        $payload['co_maker_2_signature_data'] = $this->normalizeSignatureData(
            $this->input('co_maker_2_signature_data')
                ?? $this->input('co_maker_two_signature_data'),
        );

        $this->merge($payload);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasMemberAccess();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $loanTypeRules = ['required', 'string', 'max:255'];

        if (Schema::hasTable('wlntype')) {
            if (Schema::hasColumn('wlntype', 'typecode')) {
                $loanTypeRules[] = Rule::exists('wlntype', 'typecode');
            } elseif (Schema::hasColumn('wlntype', 'lntype')) {
                $loanTypeRules[] = Rule::exists('wlntype', 'lntype');
            }
        }

        return [
            'typecode' => $loanTypeRules,
            'requested_amount' => ['required', 'numeric', 'min:1'],
            'requested_term' => ['required', 'integer', 'min:1', 'max:360'],
            'loan_purpose' => ['required', 'string', 'max:255'],
            'availment_status' => [
                'required',
                'string',
                Rule::in(['New', 'Re-Loan', 'Restructured']),
            ],
            'applicant_signature_data' => $this->signatureDataRules(),
            'co_maker_1_signature_data' => $this->signatureDataRules(),
            'co_maker_2_signature_data' => $this->signatureDataRules(),
            'undertaking_accepted' => ['accepted'],
            ...$this->personRules('applicant', true, true),
            ...$this->personRules('co_maker_1', false, false),
            ...$this->personRules('co_maker_2', false, false),
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function messages(): array
    {
        return [
            'applicant_signature_data.starts_with' => 'Please provide a valid PNG signature.',
            'co_maker_1_signature_data.starts_with' => 'Co-maker 1 signature is optional online, but if provided it must be a valid PNG signature.',
            'co_maker_2_signature_data.starts_with' => 'Co-maker 2 signature is optional online, but if provided it must be a valid PNG signature.',
            'undertaking_accepted.accepted' => 'Please confirm the undertaking.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->applicantSignatureExists()) {
                $validator->errors()->add(
                    'applicant_signature_data',
                    'Please draw your member / applicant signature before submitting.',
                );
            }

            $this->validateSignatureField(
                $validator,
                'applicant_signature_data',
                'Please provide a valid PNG signature.',
            );
            $this->validateSignatureField(
                $validator,
                'co_maker_1_signature_data',
                'Co-maker 1 signature is optional online, but if provided it must be a valid PNG signature.',
            );
            $this->validateSignatureField(
                $validator,
                'co_maker_2_signature_data',
                'Co-maker 2 signature is optional online, but if provided it must be a valid PNG signature.',
            );
        });
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function personRules(
        string $prefix,
        bool $includeSpouse,
        bool $includeChildren,
    ): array {
        $rules = [
            "{$prefix}.first_name" => ['required', 'string', 'max:255'],
            "{$prefix}.last_name" => ['required', 'string', 'max:255'],
            "{$prefix}.middle_name" => ['nullable', 'string', 'max:255'],
            "{$prefix}.nickname" => ['nullable', 'string', 'max:255'],
            "{$prefix}.birthdate" => ['required', 'date'],
            "{$prefix}.birthplace_city" => ['required', 'string', 'max:255'],
            "{$prefix}.birthplace_province" => ['required', 'string', 'max:255'],
            "{$prefix}.address1" => ['required', 'string', 'max:255'],
            "{$prefix}.address2" => ['required', 'string', 'max:255'],
            "{$prefix}.address3" => ['required', 'string', 'max:255'],
            "{$prefix}.length_of_stay" => ['required', 'string', 'max:255'],
            "{$prefix}.housing_status" => [
                'required',
                'string',
                Rule::in(self::HOUSING_STATUS_OPTIONS),
            ],
            "{$prefix}.cell_no" => ['required', 'string', 'digits:11'],
            "{$prefix}.civil_status" => [
                'required',
                'string',
                Rule::in(self::CIVIL_STATUS_OPTIONS),
            ],
            "{$prefix}.educational_attainment" => ['required', 'string', 'max:255'],
            "{$prefix}.employment_type" => ['required', 'string', 'max:255'],
            "{$prefix}.employer_business_name" => ['required', 'string', 'max:255'],
            "{$prefix}.employer_business_address1" => ['required', 'string', 'max:255'],
            "{$prefix}.employer_business_address2" => ['required', 'string', 'max:255'],
            "{$prefix}.employer_business_address3" => ['required', 'string', 'max:255'],
            "{$prefix}.telephone_no" => ['nullable', 'string', 'max:20'],
            "{$prefix}.current_position" => ['required', 'string', 'max:255'],
            "{$prefix}.nature_of_business" => ['required', 'string', 'max:255'],
            "{$prefix}.years_in_work_business" => ['required', 'string', 'max:255'],
            "{$prefix}.gross_monthly_income" => ['required', 'numeric', 'min:0'],
            "{$prefix}.payday" => [
                'required',
                'string',
                Rule::in(self::PAYDAY_OPTIONS),
            ],
        ];

        if ($includeChildren) {
            $rules["{$prefix}.number_of_children"] = [
                'required',
                'integer',
                'min:0',
            ];
        }

        if ($includeSpouse) {
            $rules["{$prefix}.spouse_name"] = ['nullable', 'string', 'max:255'];
            $rules["{$prefix}.spouse_age"] = ['nullable', 'integer', 'min:18', 'max:120'];
            $rules["{$prefix}.spouse_cell_no"] = ['nullable', 'string', 'digits:11'];
        }

        return $rules;
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    private function signatureDataRules(): array
    {
        return [
            'nullable',
            'string',
            'starts_with:data:image/png;base64,',
            'max:2800000',
        ];
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    private function normalizePersonLocationFields(array $person): array
    {
        $birthplaceCity = $this->normalizeOptionalString(
            $person['birthplace_city'] ?? null,
        );
        $birthplaceProvince = $this->normalizeOptionalString(
            $person['birthplace_province'] ?? null,
        );
        $legacyBirthplace = $this->normalizeOptionalString(
            $person['birthplace'] ?? null,
        );

        if ($birthplaceCity === null && $birthplaceProvince === null && $legacyBirthplace !== null) {
            $parsed = LocationComposer::parseLegacyBirthplace($legacyBirthplace);
            $birthplaceCity = $parsed['city'];
            $birthplaceProvince = $parsed['province'];
        }

        $person['birthplace_city'] = $birthplaceCity;
        $person['birthplace_province'] = $birthplaceProvince;

        $address1 = $this->normalizeOptionalString($person['address1'] ?? null);
        $address2 = $this->normalizeOptionalString($person['address2'] ?? null);
        $address3 = $this->normalizeOptionalString($person['address3'] ?? null);
        $legacyAddress = $this->normalizeOptionalString($person['address'] ?? null);

        if ($address1 === null && $address2 === null && $address3 === null && $legacyAddress !== null) {
            $parsed = LocationComposer::parseLegacyAddress($legacyAddress);
            $address1 = $parsed['address1'];
            $address2 = $parsed['address2'];
            $address3 = $parsed['address3'];
        }

        $person['address1'] = $address1;
        $person['address2'] = $address2;
        $person['address3'] = $address3;

        $employerAddress1 = $this->normalizeOptionalString(
            $person['employer_business_address1'] ?? null,
        );
        $employerAddress2 = $this->normalizeOptionalString(
            $person['employer_business_address2'] ?? null,
        );
        $employerAddress3 = $this->normalizeOptionalString(
            $person['employer_business_address3'] ?? null,
        );
        $legacyEmployerAddress = $this->normalizeOptionalString(
            $person['employer_business_address'] ?? null,
        );

        if (
            $employerAddress1 === null
            && $employerAddress2 === null
            && $employerAddress3 === null
            && $legacyEmployerAddress !== null
        ) {
            $parsed = LocationComposer::parseLegacyAddress($legacyEmployerAddress);
            $employerAddress1 = $parsed['address1'];
            $employerAddress2 = $parsed['address2'];
            $employerAddress3 = $parsed['address3'];
        }

        $person['employer_business_address1'] = $employerAddress1;
        $person['employer_business_address2'] = $employerAddress2;
        $person['employer_business_address3'] = $employerAddress3;

        return $person;
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeSignatureData(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function applicantSignatureExists(): bool
    {
        if ($this->normalizeOptionalString($this->input('applicant_signature_data')) !== null) {
            return true;
        }

        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $loanRequest = LoanRequest::query()
            ->where('user_id', $user->user_id)
            ->whereIn('status', [
                LoanRequestStatus::Draft->value,
                LoanRequestStatus::PendingCoMakerSignatures->value,
            ])
            ->with('applicant')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->first();

        return $loanRequest !== null
            && $this->normalizeOptionalString($loanRequest->applicant?->signature_path) !== null;
    }

    private function validateSignatureField(
        Validator $validator,
        string $field,
        string $message,
    ): void {
        $signatureData = $this->input($field);

        if (! is_string($signatureData) || trim($signatureData) === '') {
            return;
        }

        $encoded = substr($signatureData, strlen('data:image/png;base64,'));
        $decoded = base64_decode($encoded, true);

        if ($decoded === false || $decoded === '') {
            $validator->errors()->add($field, $message);

            return;
        }

        $imageMetadata = @getimagesizefromstring($decoded);

        if (
            ! is_array($imageMetadata)
            || ($imageMetadata['mime'] ?? null) !== 'image/png'
        ) {
            $validator->errors()->add($field, $message);
        }
    }
}
