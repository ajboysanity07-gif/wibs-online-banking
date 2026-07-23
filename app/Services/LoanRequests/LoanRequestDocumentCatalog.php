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
            'applicability' => 'always',
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
            'template_version' => 'affidavit-undertaking-v6',
            'applicability' => 'always',
            'required_fields' => [],
            'source_fields' => [
                'payout_bank_name',
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
        'loan_information' => [
            'template_version' => 'loan-information-pdf-v1',
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
            ],
            'source_fields' => [
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
                    'path' => 'storage/app/templates/approved-loan-documents/pdf/loan information sheet.pdf',
                    'description' => 'Loan Information Sheet PDF template',
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
            ],
            'source_fields' => [
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
            ],
            'source_fields' => [
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
            'template_version' => 'undertaking-barangay-v3',
            'applicability' => 'always',
            'required_fields' => [
                'guaranteed_net_take_home_pay',
            ],
            'source_fields' => [
                // barangay_official_designation/agency_name/agency_address dropped: the
                // field map now sources these from applicant.* (bug fix), already covered
                // by the 'applicant.' source_paths wildcard below -- same pattern AU uses
                // for its own applicant.* fields (no explicit source_fields entries).
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
            'applicability' => 'always',
            'required_fields' => [
                'notarial_venue',
            ],
            'source_fields' => [
                'notarial_venue',
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
        'generali' => [
            'template_version' => 'generali-v1',
            'applicability' => 'always',
            'required_fields' => [],
            'source_fields' => [
                'beneficiary_primary_name',
                'beneficiary_primary_relationship',
                'beneficiary_primary_birthdate',
                'beneficiary_secondary_name',
                'beneficiary_secondary_relationship',
                'beneficiary_secondary_birthdate',
                'health_hypertension',
                'health_hypertension_details',
                'gl_health_q01_weight_change',
                'gl_health_q01_weight_change_details',
                'gl_health_q02a_neuro',
                'gl_health_q02a_neuro_details',
                'gl_health_q02b_respiratory',
                'gl_health_q02b_respiratory_details',
                'gl_health_q02c_cardiac',
                'gl_health_q02c_cardiac_details',
                'gl_health_q02d_digestive',
                'gl_health_q02d_digestive_details',
                'gl_health_q02e_diabetes_renal',
                'gl_health_q02e_diabetes_renal_details',
                'gl_health_q02f_musculoskeletal',
                'gl_health_q02f_musculoskeletal_details',
                'gl_health_q02g_oncology_blood',
                'gl_health_q02g_oncology_blood_details',
                'gl_health_q02h_dermatologic',
                'gl_health_q02h_dermatologic_details',
                'gl_health_q02i_std_viral',
                'gl_health_q02i_std_viral_details',
                'gl_health_q02j_other_illness',
                'gl_health_q02j_other_illness_details',
                'gl_health_q04_prescribed_drugs',
                'gl_health_q04_prescribed_drugs_details',
                'gl_health_q05_confinement_5yr',
                'gl_health_q05_confinement_5yr_details',
                'gl_health_q06_abnormal_labs',
                'gl_health_q06_abnormal_labs_details',
                'gl_health_q07_confinement_contemplated',
                'gl_health_q07_confinement_contemplated_details',
                'gl_health_q08_blood_transfusion',
                'gl_health_q08_blood_transfusion_details',
                'gl_health_q09_other_disease',
                'gl_health_q09_other_disease_details',
                'gl_health_q10_narcotics',
                'gl_health_q10_narcotics_details',
                'gl_health_q11_smoker',
                'gl_health_q11_smoker_details',
                'gl_health_q12_alcohol',
                'gl_health_q12_alcohol_details',
                'gl_health_q13_advised_stop',
                'gl_health_q13_advised_stop_details',
                'gl_health_q14_current_medication',
                'gl_health_q14_current_medication_details',
                'gl_health_q15_pregnancy',
                'gl_health_q15_pregnancy_details',
                'gl_health_q16_relative_pep',
                'gl_health_q16_relative_pep_details',
                'gl_health_q17_pending_reinstatement',
                'gl_health_q17_pending_reinstatement_details',
                'gl_health_q17_with_glapi',
                'gl_health_q17_with_glapi_amount',
                'gl_health_q17_with_other_companies',
                'gl_health_q17_with_other_companies_amount',
            ],
            'source_paths' => [
                'loan_request.recommended_amount',
                'loan_request.recommended_term',
                'applicant.',
            ],
            'template_files' => [
                [
                    'path' => 'storage/app/templates/approved-loan-documents/pdf/generali.pdf',
                    'description' => 'Generali (GLAPI) Individual Application and Health Statement PDF template',
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
        return true;
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
}
