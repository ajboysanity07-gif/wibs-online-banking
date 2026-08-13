<?php

namespace App\Services\LoanRequests;

use App\LoanPaymentOption;
use App\LoanRequestDocumentKey;
use App\Models\AuthorityToDeductInstitutionContact;
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
                'health_smoking_status',
                'health_hypertension',
                'gl_health_q02e_diabetes',
                'health_recent_hospitalization',
            ],
            'source_fields' => [
                'beneficiary_primary_name',
                'beneficiary_primary_relationship',
                'beneficiary_primary_birthdate',
                'beneficiary_secondary_name',
                'beneficiary_secondary_relationship',
                'beneficiary_secondary_birthdate',
                'health_smoking_status',
                'health_hypertension',
                'gl_health_q02e_diabetes',
                'health_recent_hospitalization',
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
            'applicability' => 'atm_payment_option',
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
            'applicability' => 'barangay',
            'required_fields' => [
                'guaranteed_net_take_home_pay',
            ],
            'source_fields' => [
                // barangay_official_designation/agency_name/agency_address are staff-entered
                // (processing section) and feed the PDF's official_designation/agency_name/
                // agency_address content directly -- see ApprovedLoanDocumentDataBuilder.
                'guaranteed_net_take_home_pay',
                'barangay_official_designation',
                'barangay_agency_name',
                'barangay_agency_address',
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
            'required_fields' => [],
            'source_fields' => [],
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
                'gl_health_q02e_diabetes',
                'gl_health_q02e_kidney',
                'gl_health_q02e_liver',
                'gl_health_q02e_urinary',
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
                'health_smoking_status',
                'health_smoking_status_details',
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
                'health_recent_hospitalization',
                'source_of_fund_wealth',
                'id_type',
                'id_type_other',
                'id_number',
                'height_cm',
                'weight_kg',
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
        'authority_to_deduct' => [
            'template_version' => 'authority-to-deduct-v1',
            'applicability' => 'institutional_payroll',
            'required_fields' => [],
            'source_fields' => [
                'wibs_release_date',
                'payment_option',
                'authority_to_deduct_institution_name',
                'authority_to_deduct_officer_1_name',
                'authority_to_deduct_officer_1_title',
                'authority_to_deduct_officer_2_name',
                'authority_to_deduct_officer_2_title',
            ],
            'source_paths' => [
                'loan_request.recommended_amount',
                'applicant.',
            ],
            'template_files' => [
                [
                    'path' => 'resources/views/reports/authority-to-deduct.blade.php',
                    'description' => 'Authority to Deduct blade template',
                ],
            ],
            'requires_financials' => false,
        ],
        'deped_salary_deduction_waiver' => [
            'template_version' => 'deped-salary-deduction-waiver-v1',
            'applicability' => 'deped_employee',
            'required_fields' => [
                'deped_deduction_amount',
            ],
            'source_fields' => [
                'deped_school_id_number',
                'deped_deduction_amount',
            ],
            'source_paths' => [
                'applicant.',
            ],
            'template_files' => [
                [
                    'path' => 'storage/app/templates/approved-loan-documents/pdf/deped-salary-deduction-waiver.pdf',
                    'description' => 'DepEd Salary Deduction Waiver PDF template',
                ],
            ],
            'requires_financials' => false,
        ],
        'pension_deduction_waiver' => [
            'template_version' => 'pension-deduction-waiver-v1',
            'applicability' => 'pensioner',
            'required_fields' => [
                'pension_deduction_amount',
            ],
            'source_fields' => [
                'pension_provider',
                'pension_bank_name',
                'pension_atm_card_number',
                'pension_deduction_amount',
            ],
            'source_paths' => [
                'applicant.',
            ],
            'template_files' => [
                [
                    'path' => 'storage/app/templates/approved-loan-documents/pdf/pension-deduction-waiver.pdf',
                    'description' => 'Pension Deduction Waiver PDF template',
                ],
            ],
            'requires_financials' => false,
        ],
        'atm_salary_deduction_waiver' => [
            'template_version' => 'atm-salary-deduction-waiver-v1',
            'applicability' => 'atm_payout_employee',
            'required_fields' => [
                'atm_salary_deduction_amount',
            ],
            'source_fields' => [
                'payment_option',
                'atm_salary_deduction_bank_name',
                'atm_salary_deduction_card_number',
                'atm_salary_deduction_amount',
            ],
            'source_paths' => [
                'applicant.',
            ],
            'template_files' => [
                [
                    'path' => 'storage/app/templates/approved-loan-documents/pdf/atm-salary-deduction-waiver.pdf',
                    'description' => 'ATM Salary Deduction Waiver PDF template',
                ],
            ],
            'requires_financials' => false,
        ],
        'generali_application_form' => [
            'template_version' => 'generali-application-form-v2',
            'applicability' => 'always',
            'required_fields' => [
                'applicant_pep_status',
                'applicant_cycle_status',
            ],
            'source_fields' => [
                'beneficiary_primary_name',
                'beneficiary_primary_relationship',
                'beneficiary_primary_birthdate',
                'beneficiary_secondary_name',
                'beneficiary_secondary_relationship',
                'beneficiary_secondary_birthdate',
                'employer_date_employed',
                'applicant_pep_status',
                'applicant_pep_status_details',
                'applicant_cycle_status',
                'applicant_cycle_number',
                'dependent_spouse_cycle_status',
                'dependent_spouse_cycle_number',
                'dependent_child_1_name',
                'dependent_child_1_birthdate',
                'dependent_child_1_cycle_status',
                'dependent_child_1_cycle_number',
                'dependent_child_2_name',
                'dependent_child_2_birthdate',
                'dependent_child_2_cycle_status',
                'dependent_child_2_cycle_number',
                'dependent_child_3_name',
                'dependent_child_3_birthdate',
                'dependent_child_3_cycle_status',
                'dependent_child_3_cycle_number',
                'dependent_sibling_1_name',
                'dependent_sibling_1_birthdate',
                'dependent_sibling_1_cycle_status',
                'dependent_sibling_1_cycle_number',
                'dependent_sibling_2_name',
                'dependent_sibling_2_birthdate',
                'dependent_sibling_2_cycle_status',
                'dependent_sibling_2_cycle_number',
                'dependent_sibling_3_name',
                'dependent_sibling_3_birthdate',
                'dependent_sibling_3_cycle_status',
                'dependent_sibling_3_cycle_number',
                'dependent_parent_1_name',
                'dependent_parent_1_birthdate',
                'dependent_parent_1_cycle_status',
                'dependent_parent_1_cycle_number',
                'dependent_parent_2_name',
                'dependent_parent_2_birthdate',
                'dependent_parent_2_cycle_status',
                'dependent_parent_2_cycle_number',
                'dependent_extended_1_name',
                'dependent_extended_1_birthdate',
                'dependent_extended_1_cycle_status',
                'dependent_extended_1_cycle_number',
                'dependent_extended_2_name',
                'dependent_extended_2_birthdate',
                'dependent_extended_2_cycle_status',
                'dependent_extended_2_cycle_number',
                'dependent_extended_3_name',
                'dependent_extended_3_birthdate',
                'dependent_extended_3_cycle_status',
                'dependent_extended_3_cycle_number',
            ],
            'source_paths' => [
                'loan_request.recommended_amount',
                'loan_request.recommended_term',
                'applicant.',
            ],
            'template_files' => [
                [
                    'path' => 'storage/app/templates/approved-loan-documents/pdf/generali-application-form.pdf',
                    'description' => 'Generali (GLAPI) Individual Application Form PDF template',
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

    /**
     * Owner decision: loan requests submitted before this date predate the
     * beneficiary/health data model (member_application_profiles beneficiary
     * columns landed 2026-07-23) and have no staff/admin UI to backfill it, so
     * they can never satisfy GREPALIFE and the other document-readiness gates.
     * Only the Application Form remains generatable for these legacy records.
     */
    private const LEGACY_DOCUMENT_EXEMPTION_CUTOFF = '2026-07-28';

    public function isApplicable(
        LoanRequestDocumentKey $documentKey,
        LoanRequest $loanRequest,
        array $flatValues,
    ): bool {
        if ($documentKey !== LoanRequestDocumentKey::ApplicationForm
            && $this->isBeforeLegacyDocumentCutoff($loanRequest)) {
            return false;
        }

        $rule = self::DEFINITIONS[$documentKey->value]['applicability'] ?? 'always';

        return match ($rule) {
            'barangay' => $this->barangayApplicable($loanRequest, $flatValues),
            'deped_employee' => $this->depedEmployeeApplicable($loanRequest, $flatValues),
            'pensioner' => $this->pensionerApplicable($loanRequest, $flatValues),
            'institutional_payroll' => $this->authorityToDeductCategory($loanRequest, $flatValues) !== null
                && ($flatValues['payment_option'] ?? null) === LoanPaymentOption::SalaryDeduction->value,
            'atm_payout_employee' => $this->atmPayoutWaiverApplicable($loanRequest, $flatValues),
            'atm_payment_option' => ($flatValues['payment_option'] ?? null) === LoanPaymentOption::AtmDeduction->value,
            default => true,
        };
    }

    /**
     * Suggests a default "Authority to Deduct: institution name" using the same
     * category detection already used to decide waiver-document applicability
     * (barangay/DepEd/pensioner) -- a display-time default only, staff can still
     * edit or clear it before saving.
     *
     * @param  array<string, mixed>  $flatValues
     */
    public function suggestedAuthorityToDeductInstitutionName(
        LoanRequest $loanRequest,
        array $flatValues,
    ): ?string {
        $employer = $loanRequest->applicant?->employer_business_name;
        $employer = is_string($employer) && trim($employer) !== '' ? trim($employer) : null;

        if ($this->authorityToDeductCategory($loanRequest, $flatValues) === 'blgu') {
            $barangayAgencyName = $flatValues['barangay_agency_name'] ?? null;

            if (is_string($barangayAgencyName) && trim($barangayAgencyName) !== '') {
                return trim($barangayAgencyName);
            }
        }

        return $employer;
    }

    /**
     * Guidance only -- the officer count is never enforced (officer_2_name is
     * always optional, see AuthorityToDeductPdfService::buildInstitution()).
     * Self-employed applicants have no external payroll office to authorize a
     * deduction, so the document isn't applicable at all in that case.
     *
     * @param  array<string, mixed>  $flatValues
     * @return array{
     *     applicable: bool,
     *     category: ?string,
     *     recommended_officers: int,
     *     note: string,
     *     saved_contact: ?array{officer_1_name: ?string, officer_1_title: ?string, officer_2_name: ?string, officer_2_title: ?string}
     * }
     */
    public function authorityToDeductGuidance(LoanRequest $loanRequest, array $flatValues): array
    {
        $category = $this->authorityToDeductCategory($loanRequest, $flatValues);

        if ($category === null) {
            return [
                'applicable' => false,
                'category' => null,
                'recommended_officers' => 0,
                'note' => 'Not applicable — Authority to Deduct only applies to BLGU, LGU, MRDINC, or LDH institutional payroll employees. Other applicants use a Waiver document instead.',
                'saved_contact' => null,
            ];
        }

        if (($flatValues['payment_option'] ?? null) !== LoanPaymentOption::SalaryDeduction->value) {
            return [
                'applicable' => false,
                'category' => $category,
                'recommended_officers' => 0,
                'note' => 'Not applicable — this applicant belongs to an institutional payroll category, but their payment option isn\'t Salary Deduction, so no payroll office needs to be authorized.',
                'saved_contact' => null,
            ];
        }

        $savedContact = $this->savedAuthorityToDeductContact($loanRequest, $flatValues);

        return match ($category) {
            'blgu' => [
                'applicable' => true,
                'category' => 'blgu',
                'recommended_officers' => 2,
                'note' => 'BLGU (Barangay Local Government Unit) institutions typically sign with 2 officers (e.g. treasurer and captain).',
                'saved_contact' => $savedContact,
            ],
            'lgu' => [
                'applicable' => true,
                'category' => 'lgu',
                'recommended_officers' => 2,
                'note' => 'LGU (Local Government Unit) offices typically use 2 signing officers.',
                'saved_contact' => $savedContact,
            ],
            'mrdinc' => [
                'applicable' => true,
                'category' => 'mrdinc',
                'recommended_officers' => 1,
                'note' => 'MRDINC in-house payroll typically requires only 1 authorized officer.',
                'saved_contact' => $savedContact,
            ],
            'ldh' => [
                'applicable' => true,
                'category' => 'ldh',
                'recommended_officers' => 1,
                'note' => 'LDH typically requires only 1 authorized officer.',
                'saved_contact' => $savedContact,
            ],
        };
    }

    /**
     * Mirrors authorityToDeductGuidance()'s applicable/note shape, but for
     * Affidavit of Undertaking: gated purely on payment_option being ATM
     * Deduction (see the 'atm_payment_option' rule in isApplicable()).
     *
     * @param  array<string, mixed>  $flatValues
     * @return array{applicable: bool, note: string}
     */
    public function affidavitUndertakingGuidance(array $flatValues): array
    {
        if (($flatValues['payment_option'] ?? null) !== LoanPaymentOption::AtmDeduction->value) {
            return [
                'applicable' => false,
                'note' => 'Only available for members using ATM as their payment option.',
            ];
        }

        return [
            'applicable' => true,
            'note' => '',
        ];
    }

    /**
     * Single lookup staff-facing UI can call to explain why a not-applicable
     * document is unavailable, without duplicating the per-document
     * applicability rules already encoded in isApplicable()/*Guidance().
     * Returns null for documents with no configured explanation (e.g. the
     * legacy-data cutoff, or documents that are simply always applicable).
     *
     * @param  array<string, mixed>  $flatValues
     */
    public function unavailabilityNote(
        LoanRequestDocumentKey $documentKey,
        LoanRequest $loanRequest,
        array $flatValues,
    ): ?string {
        return match ($documentKey) {
            LoanRequestDocumentKey::AuthorityToDeduct => (function () use ($loanRequest, $flatValues): ?string {
                $guidance = $this->authorityToDeductGuidance($loanRequest, $flatValues);

                return $guidance['applicable'] ? null : $guidance['note'];
            })(),
            LoanRequestDocumentKey::AffidavitUndertaking => (function () use ($flatValues): ?string {
                $guidance = $this->affidavitUndertakingGuidance($flatValues);

                return $guidance['applicable'] ? null : $guidance['note'];
            })(),
            default => null,
        };
    }

    /**
     * Surfaces which Waiver document (if any) the applicant's category calls
     * for, so staff-facing UI can show only the relevant field group instead
     * of all three at once. Mutually exclusive with each other and with
     * Authority to Deduct -- see depedEmployeeApplicable(), pensionerApplicable(),
     * and atmPayoutWaiverApplicable() for the underlying category rules.
     *
     * @param  array<string, mixed>  $flatValues
     * @return array{
     *     deped: array{applicable: bool},
     *     pension: array{applicable: bool},
     *     atm: array{applicable: bool}
     * }
     */
    public function waiverApplicability(LoanRequest $loanRequest, array $flatValues): array
    {
        return [
            'deped' => ['applicable' => $this->depedEmployeeApplicable($loanRequest, $flatValues)],
            'pension' => ['applicable' => $this->pensionerApplicable($loanRequest, $flatValues)],
            'atm' => ['applicable' => $this->atmPayoutWaiverApplicable($loanRequest, $flatValues)],
        ];
    }

    /**
     * Looks up a previously saved officer contact for whatever institution
     * name currently applies to this request (staff-entered, or the
     * category-aware suggestion when nothing has been typed yet). Never
     * itself a source of truth for a specific request -- only surfaced to
     * staff as an explicit "use saved officer(s)" prefill action.
     *
     * @param  array<string, mixed>  $flatValues
     * @return ?array{officer_1_name: ?string, officer_1_title: ?string, officer_2_name: ?string, officer_2_title: ?string}
     */
    private function savedAuthorityToDeductContact(LoanRequest $loanRequest, array $flatValues): ?array
    {
        $institutionName = $flatValues['authority_to_deduct_institution_name'] ?? null;
        $institutionName = is_string($institutionName) && trim($institutionName) !== ''
            ? trim($institutionName)
            : $this->suggestedAuthorityToDeductInstitutionName($loanRequest, $flatValues);

        if ($institutionName === null) {
            return null;
        }

        $contact = AuthorityToDeductInstitutionContact::findForInstitution($institutionName);

        if ($contact === null) {
            return null;
        }

        return [
            'officer_1_name' => $contact->officer_1_name,
            'officer_1_title' => $contact->officer_1_title,
            'officer_2_name' => $contact->officer_2_name,
            'officer_2_title' => $contact->officer_2_title,
        ];
    }

    private function isBeforeLegacyDocumentCutoff(LoanRequest $loanRequest): bool
    {
        $referenceDate = $loanRequest->submitted_at ?? $loanRequest->created_at;

        if ($referenceDate === null) {
            return false;
        }

        return $referenceDate->lt(self::LEGACY_DOCUMENT_EXEMPTION_CUTOFF);
    }

    /**
     * True when the applicant identifies as DepEd (Department of Education)
     * personnel -- the only case the DepEd Salary Deduction Waiver (waiving DepEd
     * Order No. 55's NTHP threshold) applies to. There is no dedicated "DepEd
     * employee" field on the loan request, so this is signalled either by the
     * Employment=Government + Nature of Business=Education combination (public
     * school teachers hold Career Civil Service status under DepEd), or by a
     * substring match for "deped"/"department of education" on the employer name.
     * Like the other waiver documents, only applies when the loan is repaid via
     * ATM Deduction -- DepEd employees on Salary Deduction use Authority to
     * Deduct instead.
     *
     * @param  array<string, mixed>  $flatValues
     */
    private function depedEmployeeApplicable(LoanRequest $loanRequest, array $flatValues): bool
    {
        if (($flatValues['payment_option'] ?? null) !== LoanPaymentOption::AtmDeduction->value) {
            return false;
        }

        $applicant = $loanRequest->applicant;

        if ($applicant?->employment_type === 'Government'
            && $applicant?->nature_of_business === 'Education') {
            return true;
        }

        $employer = $applicant?->employer_business_name;

        if (! is_string($employer)) {
            return false;
        }

        $needle = mb_strtolower($employer);

        return str_contains($needle, 'deped')
            || str_contains($needle, 'department of education');
    }

    /**
     * True when the applicant is a pensioner -- the only case the Pension
     * Deduction Waiver (authorizing MRDINC/LGU-RHU to deduct from the borrower's
     * pension) applies to. Matches the exact "Pensioner" employment_type option
     * already offered on the loan request wizard. Like the other waiver
     * documents, only applies when the loan is repaid via ATM Deduction.
     *
     * @param  array<string, mixed>  $flatValues
     */
    private function pensionerApplicable(LoanRequest $loanRequest, array $flatValues): bool
    {
        return $loanRequest->applicant?->employment_type === 'Pensioner'
            && ($flatValues['payment_option'] ?? null) === LoanPaymentOption::AtmDeduction->value;
    }

    /**
     * True when the borrower's income is disbursed through a barangay LGU -- the
     * only case the Undertaking-Barangay document (MRDINC's guaranteed net
     * take-home-pay commitment for barangay-payroll borrowers) applies to. Signalled
     * by the applicant's employer/business name obviously naming a barangay, or --
     * for the shorter "brgy"/"bgy" abbreviations, which are too ambiguous to trust
     * on their own -- by the same abbreviations once Employment=Government + Nature
     * of Business=Government confirms the applicant works in the government sector.
     */
    private function barangayApplicable(LoanRequest $loanRequest, array $flatValues): bool
    {
        $applicant = $loanRequest->applicant;
        $employer = $applicant?->employer_business_name;

        if (! is_string($employer)) {
            return false;
        }

        $needle = mb_strtolower($employer);

        if (str_contains($needle, 'barangay')) {
            return true;
        }

        $isGovernmentSector = $applicant?->employment_type === 'Government'
            && $applicant?->nature_of_business === 'Government';

        if (! $isGovernmentSector) {
            return false;
        }

        return str_contains($needle, 'brgy') || str_contains($needle, 'bgy');
    }

    /**
     * Identifies which institutional payroll category (if any) the applicant
     * belongs to -- the only categories the Authority to Deduct document applies
     * to. Teachers, pensioners, ATM-payout private employees, and self-employed
     * applicants are excluded here even if they'd otherwise match, since those
     * borrowers use a Waiver document instead (see atmPayoutWaiverApplicable(),
     * depedEmployeeApplicable(), pensionerApplicable()).
     *
     * @param  array<string, mixed>  $flatValues
     */
    private function authorityToDeductCategory(LoanRequest $loanRequest, array $flatValues): ?string
    {
        if ($this->barangayApplicable($loanRequest, $flatValues)) {
            return 'blgu';
        }

        $applicant = $loanRequest->applicant;
        $employer = $applicant?->employer_business_name;
        $needle = is_string($employer) ? mb_strtolower($employer) : '';

        if ($needle !== '' && str_contains($needle, 'mrdinc')) {
            return 'mrdinc';
        }

        if (
            $applicant?->nature_of_business === 'Healthcare'
            || ($needle !== '' && (
                str_contains($needle, 'ldh')
                || str_contains($needle, 'hospital')
                || str_contains($needle, 'medical')
                || str_contains($needle, 'clinic')
            ))
        ) {
            return 'ldh';
        }

        $isGovernmentSector = $applicant?->employment_type === 'Government'
            && $applicant?->nature_of_business === 'Government';

        if (
            $isGovernmentSector
            || ($needle !== '' && (
                str_contains($needle, 'lgu')
                || str_contains($needle, 'municipal government')
                || str_contains($needle, 'city government')
                || str_contains($needle, 'provincial government')
            ))
        ) {
            return 'lgu';
        }

        return null;
    }

    /**
     * True for the "otherwise uncategorized" employee bucket that needs a Waiver
     * (rather than Authority to Deduct) because their loan is repaid via ATM
     * deduction with no institutional payroll office in the loop: a
     * non-pensioner, non-DepEd, non-institutional-payroll applicant whose
     * payment option is ATM Deduction. Self-employed applicants are excluded --
     * like Authority to Deduct, there's no employer to authorize a deduction
     * against.
     *
     * @param  array<string, mixed>  $flatValues
     */
    private function atmPayoutWaiverApplicable(LoanRequest $loanRequest, array $flatValues): bool
    {
        $paymentOption = $flatValues['payment_option'] ?? null;

        if ($paymentOption !== LoanPaymentOption::AtmDeduction->value) {
            return false;
        }

        if ($loanRequest->applicant?->employment_type === 'Self Employed') {
            return false;
        }

        return ! $this->pensionerApplicable($loanRequest, $flatValues)
            && ! $this->depedEmployeeApplicable($loanRequest, $flatValues)
            && $this->authorityToDeductCategory($loanRequest, $flatValues) === null;
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
