<?php

namespace App\Services\LoanRequests;

use App\LoanRequestDocumentKey;
use App\Models\LoanRequest;

class LoanRequestDocumentCatalog
{
    /**
     * @var array<string, array{
     *     template_version:string,
     *     applicability:string,
     *     required_fields:list<string>,
     *     source_fields:list<string>,
     *     source_paths:list<string>,
     *     template_files:list<array{path:string, description:string}>,
     *     requires_financials:bool
     * }>
     */
    private const DEFINITIONS = [
        'application_form' => [
            'template_version' => 'application-form-v2',
            'applicability' => 'always',
            'required_fields' => [],
            'source_fields' => [],
            'source_paths' => [
                'loan_request.typecode',
                'loan_request.requested_amount',
                'loan_request.requested_term',
                'loan_request.loan_purpose',
                'loan_request.availment_status',
                'loan_request.recommended_amount',
                'loan_request.recommended_term',
                'loan_request.recommended_interest_rate',
                'loan_request.recommended_payment_frequency',
                'loan_request.recommendation_remarks',
                'loan_request.approved_amount',
                'loan_request.approved_term',
                'loan_request.approved_interest_rate',
                'applicant.',
                'co_maker_1.',
                'co_maker_2.',
            ],
            'template_files' => [
                [
                    'path' => 'public/APPLICATION FORM-1.pdf',
                    'description' => 'Official Application Form reference PDF',
                ],
                [
                    'path' => 'resources/views/reports/partials/loan-request-document.blade.php',
                    'description' => 'Application Form blade template',
                ],
                [
                    'path' => 'resources/views/reports/partials/loan-request-styles.blade.php',
                    'description' => 'Application Form blade stylesheet',
                ],
            ],
            'requires_financials' => false,
        ],
        'grepalife' => [
            'template_version' => 'grepalife-v2',
            'applicability' => 'insurance',
            'required_fields' => [
                'beneficiary_primary_name',
                'beneficiary_primary_relationship',
                'beneficiary_primary_birthdate',
                'health_smoker',
                'health_hypertension',
                'health_diabetes',
                'health_recent_hospitalization',
            ],
            'source_fields' => [
                'beneficiary_primary_name',
                'beneficiary_primary_relationship',
                'beneficiary_primary_birthdate',
                'beneficiary_secondary_name',
                'beneficiary_secondary_relationship',
                'beneficiary_secondary_birthdate',
                'health_smoker',
                'health_hypertension',
                'health_diabetes',
                'health_recent_hospitalization',
                'health_declaration_notes',
            ],
            'source_paths' => [
                'loan_request.recommended_amount',
                'loan_request.recommended_term',
                'applicant.',
            ],
            'template_files' => [
                [
                    'path' => 'resources/templates/approved-loan-documents/images/grepalife-page-1.png',
                    'description' => 'GREPALIFE page 1 image template',
                ],
                [
                    'path' => 'resources/templates/approved-loan-documents/images/grepalife-page-2.png',
                    'description' => 'GREPALIFE page 2 image template',
                ],
            ],
            'requires_financials' => false,
        ],
        'affidavit_undertaking' => [
            'template_version' => 'affidavit-undertaking-v2',
            'applicability' => 'always',
            'required_fields' => [],
            'source_fields' => [
                'payout_bank_name',
                'payout_account_name',
                'payout_account_number',
                'payout_atm_number',
                'payout_bank_branch',
                'guaranteed_net_take_home_pay',
            ],
            'source_paths' => [
                'loan_request.recommended_amount',
                'loan_request.recommended_term',
                'applicant.',
            ],
            'template_files' => [
                [
                    'path' => 'storage/app/templates/approved-loan-documents/pdf/affidavit-undertaking.pdf',
                    'description' => 'Affidavit of Undertaking PDF template',
                ],
            ],
            'requires_financials' => false,
        ],
        'authorization' => [
            'template_version' => 'authorization-v2',
            'applicability' => 'authorization',
            'required_fields' => [],
            'source_fields' => [
                'payout_bank_name',
                'payout_account_number',
                'payout_bank_branch',
                'payout_atm_holder_name',
                'release_method',
                'authorized_recipient_name',
                'authorized_recipient_relationship',
            ],
            'source_paths' => [
                'loan_request.recommended_amount',
                'applicant.',
            ],
            'template_files' => [
                [
                    'path' => 'storage/app/templates/approved-loan-documents/pdf/authorization.pdf',
                    'description' => 'Authorization PDF template',
                ],
            ],
            'requires_financials' => false,
        ],
        'loan_information' => [
            'template_version' => 'loan-information-workbook-v2',
            'applicability' => 'always',
            'required_fields' => [
                'service_charge_rate',
                'insurance_rate',
                'insurance_term',
                'loan_security_rate',
                'documentary_stamp_rate',
                'notarial_fee',
                'penalty_rate_per_month',
                'witness_one_name',
                'witness_two_name',
            ],
            'source_fields' => [
                'service_charge_rate',
                'insurance_rate',
                'insurance_required',
                'insurance_term',
                'loan_security_rate',
                'documentary_stamp_rate',
                'notarial_fee',
                'penalty_rate_per_month',
                'witness_one_name',
                'witness_two_name',
            ],
            'source_paths' => [
                'loan_request.recommended_amount',
                'loan_request.recommended_term',
                'loan_request.recommended_interest_rate',
                'loan_request.recommended_payment_frequency',
                'applicant.',
                'co_maker_1.',
                'co_maker_2.',
            ],
            'template_files' => [
                [
                    'path' => 'storage/app/templates/approved-loan-documents/excel/plan-of-payment-disclosure-promissory-note.xlsx',
                    'description' => 'Workbook template for loan information',
                ],
            ],
            'requires_financials' => true,
        ],
        'plan_of_payment' => [
            'template_version' => 'plan-of-payment-workbook-v2',
            'applicability' => 'always',
            'required_fields' => [
                'service_charge_rate',
                'insurance_rate',
                'insurance_term',
                'loan_security_rate',
                'documentary_stamp_rate',
                'notarial_fee',
                'penalty_rate_per_month',
                'witness_one_name',
                'witness_two_name',
            ],
            'source_fields' => [
                'service_charge_rate',
                'insurance_rate',
                'insurance_required',
                'insurance_term',
                'loan_security_rate',
                'documentary_stamp_rate',
                'notarial_fee',
                'penalty_rate_per_month',
                'witness_one_name',
                'witness_two_name',
            ],
            'source_paths' => [
                'loan_request.recommended_amount',
                'loan_request.recommended_term',
                'loan_request.recommended_interest_rate',
                'loan_request.recommended_payment_frequency',
                'applicant.',
                'co_maker_1.',
                'co_maker_2.',
            ],
            'template_files' => [
                [
                    'path' => 'storage/app/templates/approved-loan-documents/excel/plan-of-payment-disclosure-promissory-note.xlsx',
                    'description' => 'Workbook template for plan of payment',
                ],
            ],
            'requires_financials' => true,
        ],
        'disclosure_statement' => [
            'template_version' => 'disclosure-statement-workbook-v2',
            'applicability' => 'always',
            'required_fields' => [
                'service_charge_rate',
                'insurance_rate',
                'insurance_term',
                'loan_security_rate',
                'documentary_stamp_rate',
                'notarial_fee',
                'penalty_rate_per_month',
            ],
            'source_fields' => [
                'service_charge_rate',
                'insurance_rate',
                'insurance_required',
                'insurance_term',
                'loan_security_rate',
                'documentary_stamp_rate',
                'notarial_fee',
                'penalty_rate_per_month',
            ],
            'source_paths' => [
                'loan_request.recommended_amount',
                'loan_request.recommended_term',
                'loan_request.recommended_interest_rate',
                'loan_request.recommended_payment_frequency',
                'applicant.',
            ],
            'template_files' => [
                [
                    'path' => 'storage/app/templates/approved-loan-documents/excel/plan-of-payment-disclosure-promissory-note.xlsx',
                    'description' => 'Workbook template for disclosure statement',
                ],
            ],
            'requires_financials' => true,
        ],
        'promissory_note' => [
            'template_version' => 'promissory-note-workbook-v2',
            'applicability' => 'always',
            'required_fields' => [
                'service_charge_rate',
                'insurance_rate',
                'insurance_term',
                'loan_security_rate',
                'documentary_stamp_rate',
                'notarial_fee',
                'penalty_rate_per_month',
                'witness_one_name',
                'witness_two_name',
            ],
            'source_fields' => [
                'service_charge_rate',
                'insurance_rate',
                'insurance_required',
                'insurance_term',
                'loan_security_rate',
                'documentary_stamp_rate',
                'notarial_fee',
                'penalty_rate_per_month',
                'witness_one_name',
                'witness_two_name',
            ],
            'source_paths' => [
                'loan_request.recommended_amount',
                'loan_request.recommended_term',
                'loan_request.recommended_interest_rate',
                'loan_request.recommended_payment_frequency',
                'applicant.',
                'co_maker_1.',
                'co_maker_2.',
            ],
            'template_files' => [
                [
                    'path' => 'storage/app/templates/approved-loan-documents/excel/plan-of-payment-disclosure-promissory-note.xlsx',
                    'description' => 'Workbook template for promissory note',
                ],
            ],
            'requires_financials' => true,
        ],
        'undertaking_barangay' => [
            'template_version' => 'undertaking-barangay-v2',
            'applicability' => 'barangay',
            'required_fields' => [],
            'source_fields' => [
                'barangay_name',
                'barangay_clearance_reference',
                'barangay_locality',
                'barangay_official_designation',
                'barangay_agency_name',
                'barangay_agency_address',
                'guaranteed_net_take_home_pay',
            ],
            'source_paths' => [
                'loan_request.recommended_amount',
                'applicant.',
            ],
            'template_files' => [
                [
                    'path' => 'storage/app/templates/approved-loan-documents/pdf/undertaking-barangay-officials.pdf',
                    'description' => 'Undertaking - Barangay PDF template',
                ],
            ],
            'requires_financials' => false,
        ],
        'loan_security_agreement' => [
            'template_version' => 'loan-security-agreement-v2',
            'applicability' => 'security',
            'required_fields' => [
                'notarial_venue',
            ],
            'source_fields' => [
                'notarial_venue',
                'security_required',
            ],
            'source_paths' => [
                'loan_request.recommended_amount',
                'loan_request.recommended_term',
                'applicant.',
            ],
            'template_files' => [
                [
                    'path' => 'resources/views/reports/loan-security-agreement.blade.php',
                    'description' => 'Loan Security Agreement blade template',
                ],
            ],
            'requires_financials' => false,
        ],
    ];

    /**
     * @return list<string>
     */
    public function requiredFieldKeys(LoanRequestDocumentKey $documentKey): array
    {
        return self::DEFINITIONS[$documentKey->value]['required_fields'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function sourceFieldKeys(LoanRequestDocumentKey $documentKey): array
    {
        return self::DEFINITIONS[$documentKey->value]['source_fields'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function sourceSnapshotPaths(LoanRequestDocumentKey $documentKey): array
    {
        return self::DEFINITIONS[$documentKey->value]['source_paths'] ?? [];
    }

    public function templateVersionFor(LoanRequestDocumentKey $documentKey): string
    {
        return self::DEFINITIONS[$documentKey->value]['template_version']
            ?? $documentKey->value.'-v1';
    }

    public function requiresFinancialRules(LoanRequestDocumentKey $documentKey): bool
    {
        return (bool) (self::DEFINITIONS[$documentKey->value]['requires_financials'] ?? false);
    }

    public function isApplicable(
        LoanRequestDocumentKey $documentKey,
        LoanRequest $loanRequest,
        array $flatValues,
    ): bool {
        $rule = self::DEFINITIONS[$documentKey->value]['applicability'] ?? 'always';

        return match ($rule) {
            'insurance' => $this->insuranceApplicable($flatValues),
            'authorization' => $this->authorizationApplicable($flatValues),
            'barangay' => $this->barangayApplicable($flatValues),
            'security' => $this->securityApplicable($flatValues),
            default => true,
        };
    }

    /**
     * @return list<string>
     */
    public function templateBlockers(LoanRequestDocumentKey $documentKey): array
    {
        $blockers = [];

        foreach (self::DEFINITIONS[$documentKey->value]['template_files'] ?? [] as $templateFile) {
            $path = base_path($templateFile['path']);

            if (is_file($path)) {
                continue;
            }

            $blockers[] = sprintf(
                'Missing official template: %s (%s).',
                $templateFile['description'],
                $templateFile['path'],
            );
        }

        return $blockers;
    }

    /**
     * @return list<string>
     */
    public function templateAvailabilityIssues(): array
    {
        $issues = [];

        foreach (LoanRequestDocumentKey::cases() as $documentKey) {
            foreach ($this->templateBlockers($documentKey) as $blocker) {
                $issues[] = sprintf(
                    '%s: %s',
                    $documentKey->label(),
                    $blocker,
                );
            }
        }

        return array_values(array_unique($issues));
    }

    /**
     * @param  list<string>  $changedFields
     */
    public function usesChangedFields(
        LoanRequestDocumentKey $documentKey,
        array $changedFields,
    ): bool {
        $sourceFields = $this->sourceFieldKeys($documentKey);
        $sourcePaths = $this->sourceSnapshotPaths($documentKey);

        foreach ($changedFields as $changedField) {
            foreach ($sourceFields as $fieldKey) {
                if ($changedField === $fieldKey || str_ends_with($changedField, '.'.$fieldKey)) {
                    return true;
                }
            }

            foreach ($sourcePaths as $sourcePath) {
                if (
                    $changedField === $sourcePath
                    || str_starts_with($changedField, $sourcePath)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function insuranceApplicable(array $flatValues): bool
    {
        if (($flatValues['insurance_required'] ?? null) === false) {
            return false;
        }

        return true;
    }

    private function authorizationApplicable(array $flatValues): bool
    {
        if (($flatValues['authorization_required'] ?? null) === false) {
            return false;
        }

        return $this->hasAnyValue($flatValues, [
            'release_method',
            'authorized_recipient_name',
            'authorized_recipient_relationship',
            'payout_bank_name',
            'payout_account_number',
        ]);
    }

    private function barangayApplicable(array $flatValues): bool
    {
        if (($flatValues['barangay_required'] ?? null) === false) {
            return false;
        }

        return $this->hasAnyValue($flatValues, [
            'barangay_name',
            'barangay_clearance_reference',
            'barangay_locality',
        ]);
    }

    private function securityApplicable(array $flatValues): bool
    {
        if (($flatValues['security_required'] ?? null) === false) {
            return false;
        }

        return $this->hasAnyValue($flatValues, [
            'loan_security_details',
            'loan_security_rate',
        ]);
    }

    /**
     * @param  list<string>  $fieldKeys
     */
    private function hasAnyValue(array $flatValues, array $fieldKeys): bool
    {
        foreach ($fieldKeys as $fieldKey) {
            $value = $flatValues[$fieldKey] ?? null;

            if (is_bool($value)) {
                return true;
            }

            if ($value !== null && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }
}
