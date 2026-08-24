<?php

namespace App\Services\LoanRequests;

use App\LoanRequestPersonRole;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Models\Wlnmaster;
use App\Models\Wmaster;
use App\Services\OrganizationSettingsService;
use App\Support\PersonName;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use NumberFormatter;
use Throwable;

/**
 * Assembles the flat field-map array consumed by every approved-loan
 * document template (PDF field maps, workbook exports). Split out of
 * ApprovedLoanDocumentService, which owns file generation/download, so the
 * two concerns are independently testable.
 */
class ApprovedLoanDocumentDataBuilder
{
    // Documentary stamp tax under TRAIN is ₱1.50 for every ₱200 (or fractional
    // part thereof) of the loan amount; 1.5/200 = 0.75% is the institutional
    // constant recorded on the request, but the amount must follow the banding
    // rule rather than a flat percentage.
    private const DOCUMENTARY_STAMP_INSTITUTIONAL_RATE = 0.0075;

    private const DOCUMENTARY_STAMP_PESO_PER_BAND = 1.5;

    private const DOCUMENTARY_STAMP_BAND_SIZE = 200;

    public function __construct(
        private LoanRequestDataService $loanRequestDataService,
        private OrganizationSettingsService $organizationSettingsService,
        private OfficialLoanManagerResolver $officialLoanManagerResolver,
    ) {}

    public function buildDocumentData(
        LoanRequest $loanRequest,
        array $overrides = [],
        bool $allowDefaultFinancialValues = false,
    ): array {
        $loanRequest->loadMissing(
            'people',
            'assignedProcessor.adminProfile',
            'reviewedBy.adminProfile',
            'approvedBy.adminProfile',
            'user.memberApplicationProfile',
        );

        $applicant = $this->resolvePerson($loanRequest, LoanRequestPersonRole::Applicant);
        $coMakerOne = $this->resolvePerson($loanRequest, LoanRequestPersonRole::CoMakerOne);
        $coMakerTwo = $this->resolvePerson($loanRequest, LoanRequestPersonRole::CoMakerTwo);
        $memberRecord = $this->resolveMemberWmaster($loanRequest);
        $memberApplicationProfile = $loanRequest->user?->memberApplicationProfile;
        $flatValues = $this->loanRequestDataService->loadFlatValues($loanRequest);
        $branding = $this->organizationSettingsService->branding();
        $documentDate = $this->resolveDocumentDate($loanRequest);
        $approvedTerm = $this->normalizeIntegerValue($loanRequest->approved_term);
        $approvedAmountRaw = $this->normalizeNumericValue($loanRequest->approved_amount);
        $paymentMode = $this->resolveWorkbookPaymentModeValue(
            $this->normalizeText(
                is_array($overrides['loan'] ?? null)
                    ? ($overrides['loan']['payment_mode_workbook'] ?? null)
                    : null,
            ) ?? $this->normalizeText($loanRequest->recommended_payment_frequency),
        );
        $isLumpsum = $paymentMode === 'LUMPSUM';
        // The approved/recommended term is the sole source of truth for how
        // many months a Lumpsum payment covers -- it can no longer disagree
        // with the amortization count or maturity date, which both derive
        // from the same $approvedTerm.
        $lumpsumMonths = $isLumpsum
            ? $this->resolveIntegerOverride(
                (is_array($overrides['loan'] ?? null) ? $overrides['loan']['lumpsum_months'] ?? null : null),
                $approvedTerm,
            )
            : null;
        // Only a 1-month Lumpsum skips insurance entirely (mirrors
        // LoanRequestDecisionService::isOneMonthLumpsum(), which exempts the
        // member from the insurance/health wizard on submission for the same
        // case). A 2-month-or-longer Lumpsum still carries an insurance
        // premium and requires the insurance document.
        $isOneMonthLumpsum = $isLumpsum && $lumpsumMonths === 1;
        $officialLoanManager = $this->officialLoanManagerResolver->documentData();
        $overrideLoan = is_array($overrides['loan'] ?? null)
            ? $overrides['loan']
            : [];
        $overrideReviewer = is_array($overrides['reviewer'] ?? null)
            ? $overrides['reviewer']
            : [];
        $overrideProcessing = is_array($overrides['processing'] ?? null)
            ? $overrides['processing']
            : [];
        $approverDisplayName = $loanRequest->approvedBy?->adminProfile?->fullname
            ?? $loanRequest->approvedBy?->name
            ?? $loanRequest->approvedBy?->username;
        $approverName = $this->normalizeText($approverDisplayName);
        $reviewerName = $this->normalizeText($overrideReviewer['name'] ?? null)
            ?? $approverName
            ?? $this->normalizeText($officialLoanManager['name']);
        $reviewerPosition = $this->normalizeText($overrideReviewer['position'] ?? null)
            ?? ($approverName !== null ? $this->officialLoanManagerResolver->position() : null)
            ?? $this->normalizeText($officialLoanManager['position']);
        $processorName = $loanRequest->assignedProcessor?->adminProfile?->fullname
            ?? $loanRequest->assignedProcessor?->name
            ?? $loanRequest->assignedProcessor?->username;
        $processorDisplayName = $this->normalizeText($processorName);
        $amortizationCount = $this->resolveAmortizationCount(
            $approvedTerm,
            $paymentMode,
            $lumpsumMonths,
        );
        $maturityDate = $this->resolveMaturityDate($documentDate, $approvedTerm);
        $deductionStartDate = $this->resolveDeductionStartDate($loanRequest, $paymentMode, $lumpsumMonths);
        $interestRateRaw = $this->resolveNumericOverride(
            $overrideLoan['interest_rate_raw'] ?? null,
            $this->normalizeNumericValue($loanRequest->approved_interest_rate),
        );
        $serviceChargeRateRaw = $this->resolveNumericOverride(
            $overrideLoan['service_charge_rate_raw']
                ?? $flatValues['service_charge_rate']
                ?? null,
            null,
        );
        $insuranceRateRaw = $isOneMonthLumpsum ? 0.0 : $this->resolveNumericOverride(
            $overrideLoan['insurance_rate_raw']
                ?? $flatValues['insurance_rate']
                ?? null,
            0.0,
        );
        $insuranceTerm = $isOneMonthLumpsum ? 0 : $this->resolveIntegerOverride(
            $overrideLoan['insurance_term']
                ?? $flatValues['insurance_term']
                ?? null,
            0,
        );
        $interestNotDeductedRaw = $this->roundCurrency(
            $approvedAmountRaw !== null && $approvedTerm !== null && $interestRateRaw !== null
                ? ($approvedAmountRaw * $interestRateRaw / 12) * $approvedTerm
                : null,
        );
        $serviceChargeAmountRaw = $this->roundCurrency(
            $approvedAmountRaw !== null && $serviceChargeRateRaw !== null
                ? $approvedAmountRaw * $serviceChargeRateRaw
                : null,
        );
        $insurancePremiumRaw = $this->roundCurrency(
            $approvedAmountRaw !== null && $insuranceTerm !== null && $insuranceRateRaw !== null
                ? ($approvedAmountRaw / 1000) * $insuranceTerm * $insuranceRateRaw
                : null,
        );
        $loanSecurityRateRaw = $isLumpsum ? 0.0 : $this->resolveNumericOverride(
            $overrideLoan['loan_security_rate_raw']
                ?? $flatValues['loan_security_rate']
                ?? null,
            0.02,
        );
        $loanSecurityAmountRaw = $this->roundCurrency(
            $approvedAmountRaw !== null && $loanSecurityRateRaw !== null
                ? $approvedAmountRaw * $loanSecurityRateRaw
                : null,
        );
        $savingsRateRaw = $isLumpsum ? 0.0 : $this->resolveNumericOverride(
            $overrideLoan['savings_rate_raw']
                ?? $flatValues['savings_rate']
                ?? null,
            0.02,
        );
        $documentaryStampRateRaw = $this->resolveNumericOverride(
            $overrideLoan['documentary_stamp_rate_raw']
                ?? $flatValues['documentary_stamp_rate']
                ?? null,
            self::DOCUMENTARY_STAMP_INSTITUTIONAL_RATE,
        );
        $documentaryStampAmountRaw = $this->roundCurrency(
            $approvedAmountRaw !== null && $documentaryStampRateRaw !== null
                ? $this->resolveDocumentaryStampAmount($approvedAmountRaw, $documentaryStampRateRaw)
                : null,
        );
        $notarialFeeRaw = $this->resolveNumericOverride(
            $overrideLoan['notarial_fee_raw']
                ?? $flatValues['notarial_fee']
                ?? null,
            100.0,
        );
        $otherChargesAmountRaw = $this->resolveNumericOverride(
            $overrideLoan['other_charges_amount_raw']
                ?? $flatValues['other_charges_amount']
                ?? null,
            null,
        );
        $otherChargesDescription = $this->normalizeText(
            $overrideLoan['other_charges_description']
                ?? $flatValues['other_charges_description']
                ?? null,
        );
        $principalAmortizationRaw = $this->roundCurrency(
            $approvedAmountRaw !== null && $amortizationCount !== null && $amortizationCount > 0
                ? $approvedAmountRaw / $amortizationCount
                : null,
        );
        $interestAmortizationRaw = $this->roundCurrency(
            $interestNotDeductedRaw !== null && $amortizationCount !== null && $amortizationCount > 0
                ? $interestNotDeductedRaw / $amortizationCount
                : null,
        );
        $loanSecurityAmortizationRaw = $this->roundCurrency(
            $principalAmortizationRaw !== null && $savingsRateRaw !== null
                ? $principalAmortizationRaw * $savingsRateRaw
                : null,
        );
        $amortizationTotalRaw = $this->roundCurrency(
            $this->sumAmounts(
                $principalAmortizationRaw,
                $interestAmortizationRaw,
                $loanSecurityAmortizationRaw,
            ),
        );
        // Net proceeds follows the Disclosure Statement workbook (R.A. 3765):
        // interest is disclosed under "Not Deducted From Proceeds of Loan"
        // (it is amortized into the payment schedule instead), so only the
        // service charge counts as a deducted finance charge here. The
        // workbook's own TOTAL FINANCE CHARGES cell (N25 = N23 = L23 + L14)
        // sums the service charge with the interest "Deducted" column (L14),
        // which stays empty because interest lives in the "Not Deducted"
        // column (J14) -- so matching it means excluding interest.
        $financeChargeTotalRaw = $this->roundCurrency(
            $this->sumAmounts($serviceChargeAmountRaw),
        );
        $nonFinanceChargeTotalRaw = $this->roundCurrency(
            $this->sumAmounts(
                $insurancePremiumRaw,
                $loanSecurityAmountRaw,
                $documentaryStampAmountRaw,
                $notarialFeeRaw,
                $otherChargesAmountRaw,
            ),
        );
        $deductionsTotalRaw = $this->roundCurrency(
            $this->sumAmounts($financeChargeTotalRaw, $nonFinanceChargeTotalRaw),
        );
        $netProceedsRaw = $this->roundCurrency(
            $approvedAmountRaw !== null && $deductionsTotalRaw !== null
                ? $approvedAmountRaw - $deductionsTotalRaw
                : null,
        );
        $penaltyRateRaw = $this->resolveNumericOverride(
            $overrideLoan['penalty_rate_raw']
                ?? $flatValues['penalty_rate_per_month']
                ?? null,
            null,
        );
        $gnthpRaw = $this->normalizeNumericValue(
            $overrideProcessing['guaranteed_net_take_home_pay'] ?? $flatValues['guaranteed_net_take_home_pay'] ?? null,
        );
        $gnthpFormatted = $this->formatCurrencyValue($gnthpRaw);
        $gnthp = $gnthpFormatted !== null ? '₱'.$gnthpFormatted : null;
        $witnessOneName = $this->normalizeText(
            $overrideReviewer['witness_one_name'] ?? null,
        ) ?? $this->normalizeText($flatValues['witness_one_name'] ?? null);
        $witnessTwoName = $this->normalizeText(
            $overrideReviewer['witness_two_name'] ?? null,
        ) ?? $this->normalizeText($flatValues['witness_two_name'] ?? null);
        $depedDeductionAmountRaw = $this->normalizeNumericValue(
            $overrideProcessing['deped_deduction_amount'] ?? $flatValues['deped_deduction_amount'] ?? null,
        );
        $depedDeductionAmountWords = $this->formatCurrencyWords($depedDeductionAmountRaw);
        $pensionDeductionAmountRaw = $this->normalizeNumericValue(
            $overrideProcessing['pension_deduction_amount'] ?? $flatValues['pension_deduction_amount'] ?? null,
        );
        $authorityToDeductOfficersUnknown = (bool) (
            $overrideProcessing['authority_to_deduct_officers_unknown'] ?? $flatValues['authority_to_deduct_officers_unknown'] ?? false
        );
        $existingLoans = $this->existingLoansDocumentData(
            $flatValues,
            $this->resolveMemberAcctno($loanRequest),
        );

        $documentData = [
            'organization' => [
                'company_name' => $this->normalizeText($branding['companyName'] ?? null),
                'business_address' => $this->normalizeText(
                    $branding['businessAddress'] ?? null,
                ),
                'business_address1' => $this->normalizeText(
                    $branding['businessAddress1'] ?? null,
                ),
                'business_address2' => $this->normalizeText(
                    $branding['businessAddress2'] ?? null,
                ),
                'business_address3' => $this->normalizeText(
                    $branding['businessAddress3'] ?? null,
                ),
                'support_contact_name' => $this->normalizeText(
                    $branding['supportContactName'] ?? null,
                ),
                'logo_data_uri' => $this->organizationSettingsService->logoDataUri(),
                'report_header' => is_array($branding['reportHeader'] ?? null)
                    ? $branding['reportHeader']
                    : [],
                'report_typography' => is_array(
                    $branding['reportTypography'] ?? null,
                )
                    ? $branding['reportTypography']
                    : [],
            ],
            'processor' => [
                'name' => $processorDisplayName,
                'position' => $processorDisplayName !== null
                    ? 'Loan Processor'
                    : null,
            ],
            'reviewer' => [
                'name' => $reviewerName,
                'position' => $reviewerPosition,
                'signature_path' => null,
                'signature_data' => null,
                'witness_one_name' => $witnessOneName,
                'witness_two_name' => $witnessTwoName,
            ],
            'loan' => [
                'reference' => $loanRequest->reference,
                'type' => $this->normalizeText($loanRequest->loan_type_label_snapshot)
                    ?? $this->normalizeText($loanRequest->typecode),
                'requested_amount' => $this->formatCurrencyValue($loanRequest->requested_amount),
                'requested_amount_raw' => $this->normalizeNumericValue($loanRequest->requested_amount),
                'approved_amount' => $this->formatCurrencyValue($loanRequest->approved_amount),
                'approved_amount_raw' => $approvedAmountRaw,
                'approved_amount_words' => $this->formatCurrencyWords($approvedAmountRaw),
                'approved_term' => $loanRequest->approved_term,
                'approved_term_raw' => $approvedTerm,
                'approved_term_label' => $loanRequest->approved_term !== null
                    ? $loanRequest->approved_term.' months'
                    : null,
                'amortization_count' => $amortizationCount,
                'payment_mode_workbook' => $paymentMode,
                'lumpsum_months' => $lumpsumMonths,
                'purpose' => $this->normalizeText($loanRequest->loan_purpose),
                'kind_of_loan' => $this->normalizeText($loanRequest->kind_of_loan),
                'approved_date' => $documentDate?->format('F d, Y'),
                'approved_date_short' => $documentDate?->format('m/d/Y'),
                'approved_date_day' => $documentDate?->format('d'),
                'approved_date_month_year' => $documentDate?->format('F, Y'),
                'maturity_date_short' => $maturityDate?->format('m/d/Y'),
                'term_days' => $approvedTerm !== null ? $approvedTerm * 25 : null,
                'recommended_by' => $processorDisplayName,
                'insurance_term' => $insuranceTerm,
                'interest_rate_raw' => $interestRateRaw,
                'service_charge_rate_raw' => $serviceChargeRateRaw,
                'insurance_rate_raw' => $insuranceRateRaw,
                'loan_security_rate_raw' => $loanSecurityRateRaw,
                'savings_rate_raw' => $savingsRateRaw,
                'documentary_stamp_rate_raw' => $documentaryStampRateRaw,
                'interest_rate_words' => $this->formatPercentWords($interestRateRaw),
                'interest_not_deducted_raw' => $interestNotDeductedRaw,
                'service_charge_amount_raw' => $serviceChargeAmountRaw,
                'insurance_premium_raw' => $insurancePremiumRaw,
                'loan_security_amount_raw' => $loanSecurityAmountRaw,
                'documentary_stamp_amount_raw' => $documentaryStampAmountRaw,
                'notarial_fee_raw' => $notarialFeeRaw,
                'other_charges_amount_raw' => $otherChargesAmountRaw,
                'other_charges_description' => $otherChargesDescription,
                'finance_charge_total_raw' => $financeChargeTotalRaw,
                'non_finance_charge_total_raw' => $nonFinanceChargeTotalRaw,
                'deductions_total_raw' => $deductionsTotalRaw,
                'net_proceeds_raw' => $netProceedsRaw,
                'amortization_principal_raw' => $principalAmortizationRaw,
                'amortization_interest_raw' => $interestAmortizationRaw,
                'amortization_loan_security_raw' => $loanSecurityAmortizationRaw,
                'amortization_total_raw' => $amortizationTotalRaw,
                'amortization_total' => $this->formatCurrencyValue($amortizationTotalRaw),
                'amortization_total_words' => $this->formatCurrencyWords($amortizationTotalRaw),
                'deduction_start_date' => $deductionStartDate?->format('F d, Y'),
                'penalty_rate_raw' => $penaltyRateRaw,
                'gnthp_raw' => $gnthpRaw,
                'gnthp' => $gnthp,
            ],
            'authorization' => [
                'release_method' => $this->normalizeText(
                    $overrideProcessing['release_method'] ?? $flatValues['release_method'] ?? null,
                ),
                'payment_option' => $this->normalizeText(
                    $overrideProcessing['payment_option'] ?? $flatValues['payment_option'] ?? null,
                ),
                'payout_bank_name' => $this->normalizeText(
                    $overrideProcessing['payout_bank_name'] ?? $flatValues['payout_bank_name'] ?? null,
                ),
                'payout_account_name' => $this->normalizeText(
                    $overrideProcessing['payout_account_name'] ?? $flatValues['payout_account_name'] ?? null,
                ),
                'payout_account_number' => $this->normalizeText(
                    $overrideProcessing['payout_account_number'] ?? $flatValues['payout_account_number'] ?? null,
                ),
                'payout_account_type' => $this->normalizeText(
                    $overrideProcessing['payout_account_type'] ?? $flatValues['payout_account_type'] ?? null,
                ),
                'payout_atm_number' => $this->normalizeText(
                    $overrideProcessing['payout_atm_number'] ?? $flatValues['payout_atm_number'] ?? null,
                ),
                'payout_bank_branch' => $this->normalizeText(
                    $overrideProcessing['payout_bank_branch'] ?? $flatValues['payout_bank_branch'] ?? null,
                ),
            ],
            'barangay' => [
                'official_name' => $this->normalizeText(
                    $overrideProcessing['barangay_official_name'] ?? $flatValues['barangay_official_name'] ?? null,
                ),
                'official_title' => $this->normalizeText(
                    $overrideProcessing['barangay_official_title'] ?? $flatValues['barangay_official_title'] ?? null,
                ),
                'official_designation' => $this->normalizeText(
                    $overrideProcessing['barangay_official_designation'] ?? $flatValues['barangay_official_designation'] ?? null,
                ),
                'agency_name' => $this->normalizeText(
                    $overrideProcessing['barangay_agency_name'] ?? $flatValues['barangay_agency_name'] ?? null,
                ),
                'agency_address' => $this->normalizeText(
                    $overrideProcessing['barangay_agency_address'] ?? $flatValues['barangay_agency_address'] ?? null,
                ),
            ],
            'authority_to_deduct' => [
                'institution_name' => $this->normalizeText(
                    $overrideProcessing['authority_to_deduct_institution_name'] ?? $flatValues['authority_to_deduct_institution_name'] ?? null,
                ),
                // When staff has checked "officers not yet known," the generated
                // document should show blank officer fields rather than whatever
                // was previously typed and left uncleared.
                'officer_1_name' => $authorityToDeductOfficersUnknown ? null : $this->normalizeText(
                    $overrideProcessing['authority_to_deduct_officer_1_name'] ?? $flatValues['authority_to_deduct_officer_1_name'] ?? null,
                ),
                'officer_1_title' => $authorityToDeductOfficersUnknown ? null : $this->normalizeText(
                    $overrideProcessing['authority_to_deduct_officer_1_title'] ?? $flatValues['authority_to_deduct_officer_1_title'] ?? null,
                ),
                'officer_2_name' => $authorityToDeductOfficersUnknown ? null : $this->normalizeText(
                    $overrideProcessing['authority_to_deduct_officer_2_name'] ?? $flatValues['authority_to_deduct_officer_2_name'] ?? null,
                ),
                'officer_2_title' => $authorityToDeductOfficersUnknown ? null : $this->normalizeText(
                    $overrideProcessing['authority_to_deduct_officer_2_title'] ?? $flatValues['authority_to_deduct_officer_2_title'] ?? null,
                ),
            ],
            'deduction' => [
                'deped_school_id_number' => $this->normalizeText(
                    $overrideProcessing['deped_school_id_number'] ?? $flatValues['deped_school_id_number'] ?? null,
                ),
                'deped_deduction_amount_raw' => $depedDeductionAmountRaw,
                'deped_deduction_amount' => $this->formatCurrencyValue($depedDeductionAmountRaw),
                'deped_deduction_amount_words' => $depedDeductionAmountWords,
                'pension_provider' => $this->normalizeText(
                    $overrideProcessing['pension_provider'] ?? $flatValues['pension_provider'] ?? null,
                ),
                'pension_bank_name' => $this->normalizeText(
                    $overrideProcessing['pension_bank_name'] ?? $flatValues['pension_bank_name'] ?? null,
                ),
                'pension_atm_card_number' => $this->normalizeText(
                    $overrideProcessing['pension_atm_card_number'] ?? $flatValues['pension_atm_card_number'] ?? null,
                ),
                'pension_deduction_amount_raw' => $pensionDeductionAmountRaw,
                'pension_deduction_amount' => $this->formatCurrencyValue($pensionDeductionAmountRaw),
                'pension_deduction_amount_words' => $this->formatCurrencyWords($pensionDeductionAmountRaw),
            ],
            'notarial' => [
                // Doc/Page/Book No., signing date/place, and valid ID number/issuance
                // location have no reference-document equivalent on the approved loan
                // documents — left blank on the printed forms for the notary to fill by
                // hand. notarial_province (the separate "for and in ___" blank) is
                // likewise left unwired for the same reason.
                'series_year' => $documentDate?->format('Y'),
            ],
            'health' => [
                'health_smoking_status' => $overrideProcessing['health_smoking_status'] ?? $flatValues['health_smoking_status'] ?? null,
                'health_hypertension' => $overrideProcessing['health_hypertension'] ?? $flatValues['health_hypertension'] ?? null,
            ],
            'declarations' => [
                'declaration_existing_loans' => $overrideProcessing['declaration_existing_loans']
                    ?? ($existingLoans !== [] ? true : ($flatValues['declaration_existing_loans'] ?? null)),
                'declaration_pending_cases' => $overrideProcessing['declaration_pending_cases'] ?? $flatValues['declaration_pending_cases'] ?? null,
            ],
            'applicant' => $this->personDocumentData($applicant, $loanRequest, $memberRecord),
            'co_maker_one' => $this->personDocumentData($coMakerOne, $loanRequest),
            'co_maker_two' => $this->personDocumentData($coMakerTwo, $loanRequest),
            'beneficiaries' => $this->beneficiaryDocumentData($flatValues, $memberRecord),
            'health_glapi' => $this->healthGlapiDocumentData($flatValues),
            'existing_loans' => $existingLoans,
            'application_form' => $this->generaliApplicationFormDocumentData(
                $overrideProcessing,
                $flatValues,
                $memberApplicationProfile,
            ),
            'dependents' => $this->dependentsDocumentData($flatValues, $applicant),
        ];

        return $overrides !== []
            ? array_replace_recursive($documentData, $overrides)
            : $documentData;
    }

    /**
     * @return array<string, mixed>
     */
    private function personDocumentData(
        ?LoanRequestPerson $person,
        LoanRequest $loanRequest,
        ?Wmaster $memberRecord = null,
    ): array {
        $composedBirthplace = $this->normalizeText($person?->composedBirthplace());
        $composedAddress = $this->normalizeText($person?->composedAddress());
        $composedOfficeAddress = $this->normalizeText(
            $person?->composedEmployerBusinessAddress(),
        );
        $addressLine = $this->composeAddressLine(
            $person?->address1,
            $person?->address_barangay,
        ) ?? $composedAddress;
        $officeAddressLine = $this->composeAddressLine(
            $person?->employer_business_address1,
            $person?->employer_business_address_barangay,
        ) ?? $composedOfficeAddress;

        return [
            'full_name' => $this->personFullName($person),
            'first_name' => $this->normalizeText($person?->first_name),
            'middle_name' => $this->normalizeText($person?->middle_name),
            'last_name' => $this->normalizeText($person?->last_name),
            'birthdate' => $this->formatBirthdate($person),
            'age' => $this->formatAge($person),
            'civil_status' => $this->normalizeText($person?->civil_status),
            'sex' => $this->normalizeText($person?->sex),
            'nationality' => 'FILIPINO',
            'place_of_birth' => $composedBirthplace,
            'place_of_birth_city' => $this->normalizeText($person?->birthplace_city),
            'place_of_birth_province' => $this->normalizeText(
                $person?->birthplace_province,
            ),
            'address' => $composedAddress,
            'address_line' => $addressLine,
            // Split out from address_line for templates (GA/GE) with a
            // dedicated "(Street No.)" / "(Brgy.)" column pair -- address1 is
            // almost always a Purok name in practice, not a numbered street.
            'address_street' => $this->normalizeText($person?->address1),
            'address_barangay' => $this->normalizeText($person?->address_barangay),
            'address_city' => $this->normalizeText($person?->address2),
            'address_province' => $this->normalizeText($person?->address3),
            'address_country' => null,
            'address_zip' => $this->normalizeText(
                $person?->address_zip ?? $memberRecord?->zone_number,
            ),
            'contact_number' => $this->normalizeText($person?->cell_no),
            'mobile' => $this->normalizeText($person?->cell_no),
            'home_phone' => null,
            'work_phone' => $this->normalizeText($person?->telephone_no),
            'email' => $this->normalizeText($loanRequest->user?->email),
            'employer_or_business' => $this->normalizeText($person?->employer_business_name),
            'office_address' => $composedOfficeAddress,
            'office_address_line' => $officeAddressLine,
            'office_city' => $this->normalizeText($person?->employer_business_address2),
            'office_province' => $this->normalizeText(
                $person?->employer_business_address3,
            ),
            'office_country' => null,
            'office_zip' => $this->normalizeText(
                $person?->employer_business_address_zip
                    ?? $loanRequest->user?->memberApplicationProfile?->employer_business_address_zip,
            ),
            'position_or_designation' => $this->normalizeText($person?->current_position),
            'nature_of_business' => $this->normalizeText($person?->nature_of_business),
            'employer_date_employed' => $this->formatShortDateValue($person?->employer_date_employed),
            'years_in_work_business' => $this->normalizeText(
                $person?->years_in_work_business,
            ),
            'payday' => $this->normalizePaydayValue($person?->payday),
            'signature_path' => null,
        ];
    }

    private function resolveMemberWmaster(LoanRequest $loanRequest): ?Wmaster
    {
        if (! Schema::hasTable('wmaster')) {
            return null;
        }

        $loanRequest->loadMissing('user');

        $user = $loanRequest->user;

        if ($user !== null) {
            $user->loadMissing('wmaster');

            if ($user->wmaster instanceof Wmaster) {
                return $user->wmaster;
            }
        }

        $acctno = trim((string) ($loanRequest->acctno ?? $user?->acctno ?? ''));

        if ($acctno === '') {
            return null;
        }

        return Wmaster::query()->where('acctno', $acctno)->first();
    }

    /**
     * @param  array<string, mixed>  $flatValues
     * @return array<int, array{name: string, birthdate: string|null, relationship: string|null}>
     */
    private function beneficiaryDocumentData(array $flatValues, ?Wmaster $memberRecord): array
    {
        $beneficiariesFromEntries = array_values(array_filter([
            ! $this->isBlankString($flatValues['beneficiary_primary_name'] ?? null)
                ? [
                    'name' => $this->normalizeText((string) $flatValues['beneficiary_primary_name']),
                    'birthdate' => $this->formatShortDateValue(
                        $flatValues['beneficiary_primary_birthdate'] ?? null,
                    ),
                    'relationship' => $this->normalizeText(
                        is_scalar($flatValues['beneficiary_primary_relationship'] ?? null)
                            ? (string) $flatValues['beneficiary_primary_relationship']
                            : null,
                    ),
                ]
                : null,
            ! $this->isBlankString($flatValues['beneficiary_secondary_name'] ?? null)
                ? [
                    'name' => $this->normalizeText((string) $flatValues['beneficiary_secondary_name']),
                    'birthdate' => $this->formatShortDateValue(
                        $flatValues['beneficiary_secondary_birthdate'] ?? null,
                    ),
                    'relationship' => $this->normalizeText(
                        is_scalar($flatValues['beneficiary_secondary_relationship'] ?? null)
                            ? (string) $flatValues['beneficiary_secondary_relationship']
                            : null,
                    ),
                ]
                : null,
        ]));

        if ($beneficiariesFromEntries !== []) {
            return $beneficiariesFromEntries;
        }

        if (! $memberRecord instanceof Wmaster) {
            return [];
        }

        $linkedBeneficiaries = $this->resolveLinkedBeneficiaryMembers($memberRecord);
        $beneficiaries = [];

        for ($slot = 1; $slot <= 3; $slot++) {
            $directName = $this->normalizeText(
                $this->stringAttribute($memberRecord, 'beneficiary'.$slot),
            );

            if ($directName !== null) {
                $beneficiaries[] = [
                    'name' => $directName,
                    'birthdate' => $this->formatShortDateValue(
                        $memberRecord->getAttribute('ben'.$slot.'_bday'),
                    ),
                    'relationship' => null,
                ];

                continue;
            }

            $linkedAcctno = trim(
                (string) $memberRecord->getAttribute('ben'.$slot.'_acctno'),
            );

            if ($linkedAcctno === '') {
                continue;
            }

            $linkedBeneficiary = $linkedBeneficiaries[$linkedAcctno] ?? null;

            if (! $linkedBeneficiary instanceof Wmaster) {
                continue;
            }

            $linkedName = $this->normalizeText($linkedBeneficiary->displayName());

            if ($linkedName === null) {
                continue;
            }

            $beneficiaries[] = [
                'name' => $linkedName,
                'birthdate' => $this->formatShortDateValue($linkedBeneficiary->birthday),
                'relationship' => null,
            ];
        }

        return array_slice($beneficiaries, 0, 3);
    }

    /**
     * Existing/previous loan detail rows (GLAPI section 1.1), collected on
     * the wizard's "declarations" data section as 3 fixed slots
     * (existing_loan_{1,2,3}_{date,type,amount}). Only non-blank slots are
     * returned, keyed by row so consumers (e.g. GrepalifePdfFieldMap) can
     * index into them positionally.
     *
     * @param  array<string, mixed>  $flatValues
     * @return array<int, array{date: string|null, type: string|null, amount: string|null}>
     */
    private function existingLoansDocumentData(array $flatValues, ?string $acctno = null): array
    {
        $rows = [];

        for ($slot = 1; $slot <= 3; $slot++) {
            $type = $flatValues['existing_loan_'.$slot.'_type'] ?? null;
            $date = $flatValues['existing_loan_'.$slot.'_date'] ?? null;
            $amount = $flatValues['existing_loan_'.$slot.'_amount'] ?? null;

            if (
                $this->isBlankString($type)
                && $this->isBlankString($date)
                && $this->isBlankString($amount)
            ) {
                continue;
            }

            $rows[] = [
                'date' => $this->formatShortDateValue($date),
                'type' => $this->normalizeText(is_scalar($type) ? (string) $type : null),
                'amount' => ! $this->isBlankString($amount) && is_scalar($amount)
                    ? (string) $amount
                    : null,
            ];
        }

        if ($rows === [] && $acctno !== null) {
            $fallback = $this->mostRecentWlnmasterExistingLoan($acctno);

            if ($fallback !== null) {
                $rows[] = $fallback;
            }
        }

        return $rows;
    }

    /**
     * The member's most recent loan from the wlnmaster ledger. Used as a
     * document-generation fallback when a loan request has no existing-loan
     * rows stored on it (e.g. requests submitted before the declarations
     * auto-fill feature existed) -- wlnmaster is the authoritative record of
     * a member's prior loans regardless of when the request was created.
     *
     * @return array{date: string|null, type: string|null, amount: string|null}|null
     */
    private function mostRecentWlnmasterExistingLoan(string $acctno): ?array
    {
        if (! Schema::hasTable('wlnmaster')) {
            return null;
        }

        $query = Wlnmaster::query()->where('acctno', trim($acctno));

        $query = $this->applyWlnmasterDateOrder($query);

        $loan = $query->first();

        if ($loan === null) {
            return null;
        }

        $date = $this->resolveWlnmasterDateValue($loan);

        return [
            'date' => $this->formatShortDateValue($date),
            'type' => $this->normalizeText(
                $this->normalizeOptionalScalar($loan->lntype),
            ),
            'amount' => $this->normalizeOptionalScalar($loan->principal),
        ];
    }

    private function applyWlnmasterDateOrder(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        if (Schema::hasColumn('wlnmaster', 'date_rel')) {
            return $query->orderByDesc('date_rel');
        }

        if (Schema::hasColumn('wlnmaster', 'lastmove')) {
            return $query->orderByDesc('lastmove');
        }

        return $query->orderByDesc('lnnumber');
    }

    private function resolveWlnmasterDateValue(Wlnmaster $loan): mixed
    {
        if (Schema::hasColumn('wlnmaster', 'date_rel')) {
            return $loan->date_rel;
        }

        return $loan->lastmove ?? $loan->lnnumber;
    }

    private function normalizeOptionalScalar(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMemberAcctno(LoanRequest $loanRequest): ?string
    {
        $loanRequest->loadMissing('user');

        $acctno = trim((string) ($loanRequest->acctno ?? $loanRequest->user?->acctno ?? ''));

        return $acctno !== '' ? $acctno : null;
    }

    /**
     * Data specific to the Generali Individual Application Form (as opposed to the
     * Application-and-Health-Statement form's 'deduction'/'health_glapi' blocks):
     * the applicant's own PEP question, enrollment cycle, ID/source-of-funds row,
     * and employer hire date. ID/source-of-funds fields aren't part of the
     * per-loan-request flat EAV system (LoanRequestDataService) -- they live only
     * on the member's MemberApplicationProfile, collected during the
     * loan-prerequisite step -- so they're read directly off that profile, the
     * same way beneficiaryDocumentData() falls back to the Wmaster member record.
     *
     * @param  array<string, mixed>  $overrideProcessing
     * @param  array<string, mixed>  $flatValues
     * @return array<string, mixed>
     */
    private function generaliApplicationFormDocumentData(
        array $overrideProcessing,
        array $flatValues,
        ?MemberApplicationProfile $memberApplicationProfile,
    ): array {
        return [
            'pep_status' => $overrideProcessing['applicant_pep_status'] ?? $flatValues['applicant_pep_status'] ?? null,
            'pep_status_details' => $this->normalizeText(
                $overrideProcessing['applicant_pep_status_details'] ?? $flatValues['applicant_pep_status_details'] ?? null,
            ),
            'cycle_status' => $this->normalizeText(
                $overrideProcessing['applicant_cycle_status'] ?? $flatValues['applicant_cycle_status'] ?? null,
            ),
            'cycle_number' => $this->normalizeText(
                $overrideProcessing['applicant_cycle_number'] ?? $flatValues['applicant_cycle_number'] ?? null,
            ),
            'employer_date_employed' => $this->formatShortDateValue(
                $overrideProcessing['employer_date_employed'] ?? $flatValues['employer_date_employed'] ?? null,
            ),
            'source_of_fund_wealth' => $this->normalizeText($memberApplicationProfile?->source_of_fund_wealth),
            'id_type' => $this->normalizeText($memberApplicationProfile?->id_type),
            'id_type_other' => $this->normalizeText($memberApplicationProfile?->id_type_other),
            'id_number' => $this->normalizeText($memberApplicationProfile?->id_number),
            'height_cm' => $this->normalizeText($memberApplicationProfile?->height_cm),
            'weight_kg' => $this->normalizeText($memberApplicationProfile?->weight_kg),
        ];
    }

    /**
     * Spouse + children/siblings/parents/extended-family rows for the Generali
     * Individual Application Form's "Dependents Information" section (Form B),
     * sourced from the dependent_{category}_{slot}_* flat fields -- collected by
     * the wizard's dependents step but not consumed by any document until now.
     * Ages are computed on the fly from each raw birthdate (no stored age field).
     *
     * @param  array<string, mixed>  $flatValues
     * @return array<string, mixed>
     */
    private function dependentsDocumentData(array $flatValues, ?LoanRequestPerson $applicant): array
    {
        $categorySlots = [
            'child' => ['slots' => 3, 'key' => 'children'],
            'sibling' => ['slots' => 3, 'key' => 'siblings'],
            'parent' => ['slots' => 2, 'key' => 'parents'],
            'extended' => ['slots' => 3, 'key' => 'extended'],
        ];

        $dependents = [
            'spouse' => [
                'name' => $this->normalizeText($applicant?->spouse_name),
                'age' => $this->normalizeText($applicant?->spouse_age),
                'cycle_status' => $this->normalizeText($flatValues['dependent_spouse_cycle_status'] ?? null),
                'cycle_number' => $this->normalizeText($flatValues['dependent_spouse_cycle_number'] ?? null),
            ],
        ];

        foreach ($categorySlots as $category => $categoryConfig) {
            $rows = [];

            for ($slot = 1; $slot <= $categoryConfig['slots']; $slot++) {
                $prefix = "dependent_{$category}_{$slot}_";
                $name = $flatValues[$prefix.'name'] ?? null;
                $birthdate = $flatValues[$prefix.'birthdate'] ?? null;

                if ($this->isBlankString($name)) {
                    continue;
                }

                $rows[] = [
                    'name' => $this->normalizeText($name),
                    'birthdate' => $this->formatShortDateValue($birthdate),
                    'age' => $this->formatAgeFromRawDate($birthdate),
                    'cycle_status' => $this->normalizeText($flatValues[$prefix.'cycle_status'] ?? null),
                    'cycle_number' => $this->normalizeText($flatValues[$prefix.'cycle_number'] ?? null),
                ];
            }

            $dependents[$categoryConfig['key']] = $rows;
        }

        return $dependents;
    }

    /**
     * The GLAPI (Generali) 17-item health questionnaire, collected on the wizard's
     * "health_glapi" data section. Booleans pass through as-is (null when
     * unanswered); "_details"/"_amount" companions are normalized text.
     *
     * @param  array<string, mixed>  $flatValues
     * @return array<string, mixed>
     */
    private function healthGlapiDocumentData(array $flatValues): array
    {
        $booleanFields = [
            'gl_health_q01_weight_change',
            'gl_health_q02a_neuro',
            'gl_health_q02b_respiratory',
            'gl_health_q02c_cardiac',
            'gl_health_q02d_digestive',
            'gl_health_q02e_diabetes',
            'gl_health_q02e_kidney',
            'gl_health_q02e_liver',
            'gl_health_q02e_urinary',
            'gl_health_q02f_musculoskeletal',
            'gl_health_q02g_oncology_blood',
            'gl_health_q02h_dermatologic',
            'gl_health_q02i_std_viral',
            'gl_health_q02j_other_illness',
            'gl_health_q04_prescribed_drugs',
            'gl_health_q05_confinement_5yr',
            'gl_health_q06_abnormal_labs',
            'gl_health_q07_confinement_contemplated',
            'gl_health_q08_blood_transfusion',
            'gl_health_q09_other_disease',
            'gl_health_q10_narcotics',
            'gl_health_q12_alcohol',
            'gl_health_q13_advised_stop',
            'gl_health_q14_current_medication',
            'gl_health_q15_pregnancy',
            'gl_health_q16_relative_pep',
            'gl_health_q17_pending_reinstatement',
            'gl_health_q17_with_glapi',
            'gl_health_q17_with_other_companies',
            'health_recent_hospitalization',
        ];

        $textFields = [
            'health_hypertension_details',
            'gl_health_q01_weight_change_details',
            'gl_health_q02a_neuro_details',
            'gl_health_q02b_respiratory_details',
            'gl_health_q02c_cardiac_details',
            'gl_health_q02d_digestive_details',
            'gl_health_q02e_diabetes_renal_details',
            'gl_health_q02f_musculoskeletal_details',
            'gl_health_q02g_oncology_blood_details',
            'gl_health_q02h_dermatologic_details',
            'gl_health_q02i_std_viral_details',
            'gl_health_q02j_other_illness_details',
            'gl_health_q04_prescribed_drugs_details',
            'gl_health_q05_confinement_5yr_details',
            'gl_health_q06_abnormal_labs_details',
            'gl_health_q07_confinement_contemplated_details',
            'gl_health_q08_blood_transfusion_details',
            'gl_health_q09_other_disease_details',
            'gl_health_q10_narcotics_details',
            'health_smoking_status_details',
            'gl_health_q12_alcohol_details',
            'gl_health_q13_advised_stop_details',
            'gl_health_q14_current_medication_details',
            'gl_health_q15_pregnancy_details',
            'gl_health_q16_relative_pep_details',
            'gl_health_q17_pending_reinstatement_details',
            'gl_health_q17_with_glapi_amount',
            'gl_health_q17_with_other_companies_amount',
        ];

        $data = [];

        foreach ($booleanFields as $field) {
            $value = $flatValues[$field] ?? null;
            $data[$field] = is_bool($value) ? $value : null;
        }

        foreach ($textFields as $field) {
            $data[$field] = $this->normalizeText($flatValues[$field] ?? null);
        }

        return $data;
    }

    /**
     * @return array<string, Wmaster>
     */
    private function resolveLinkedBeneficiaryMembers(Wmaster $memberRecord): array
    {
        $acctnos = [];

        for ($slot = 1; $slot <= 3; $slot++) {
            $acctno = trim((string) $memberRecord->getAttribute('ben'.$slot.'_acctno'));

            if ($acctno !== '') {
                $acctnos[] = $acctno;
            }
        }

        $acctnos = array_values(array_unique($acctnos));

        if ($acctnos === []) {
            return [];
        }

        return Wmaster::query()
            ->whereIn('acctno', $acctnos)
            ->get()
            ->keyBy(fn (Wmaster $beneficiary): string => trim((string) $beneficiary->acctno))
            ->all();
    }

    private function stringAttribute(Wmaster $memberRecord, string $attribute): ?string
    {
        $value = $memberRecord->getAttribute($attribute);

        return is_string($value) ? $value : null;
    }

    private function normalizeNumericValue(float|int|string|null $value): float|int|null
    {
        if ($value === null || ! is_numeric((string) $value)) {
            return null;
        }

        return (float) $value;
    }

    private function normalizeIntegerValue(float|int|string|null $value): ?int
    {
        if ($value === null || ! is_numeric((string) $value)) {
            return null;
        }

        return (int) round((float) $value);
    }

    private function resolveNumericOverride(
        mixed $value,
        float|int|null $default,
    ): float|int|null {
        $normalized = $this->normalizeNumericValue(
            is_array($value) ? null : $value,
        );

        return $normalized ?? $default;
    }

    /**
     * Computes the documentary stamp tax. When the institutional rate is in
     * effect the client's given formula applies — ₱1.50 for every ₱200 of the
     * loan amount, with fractional parts rounded up to a full ₱200 band — so
     * non-multiple-of-₱200 loans still land on the correct BIR figure. An
     * explicit staff-entered rate (legacy data) is honored as a flat percentage.
     */
    private function resolveDocumentaryStampAmount(
        float|int $approvedAmount,
        float|int $documentaryStampRate,
    ): float {
        if (
            abs((float) $documentaryStampRate - self::DOCUMENTARY_STAMP_INSTITUTIONAL_RATE)
            < PHP_FLOAT_EPSILON
        ) {
            return ceil((float) $approvedAmount / self::DOCUMENTARY_STAMP_BAND_SIZE)
                * self::DOCUMENTARY_STAMP_PESO_PER_BAND;
        }

        return (float) $approvedAmount * (float) $documentaryStampRate;
    }

    private function resolveIntegerOverride(
        mixed $value,
        ?int $default,
    ): ?int {
        $normalized = $this->normalizeIntegerValue(
            is_array($value) ? null : $value,
        );

        return $normalized ?? $default;
    }

    private function formatCurrencyValue(float|int|string|null $value): ?string
    {
        if ($value === null || ! is_numeric((string) $value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', ',');
    }

    private function formatBirthdate(?LoanRequestPerson $person): ?string
    {
        if ($person === null || $person->birthdate === null) {
            return null;
        }

        if ($person->birthdate instanceof Carbon) {
            return $person->birthdate->format('F d, Y');
        }

        $date = trim((string) $person->birthdate);

        if ($date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->format('F d, Y');
        } catch (Throwable) {
            return $date;
        }
    }

    private function formatShortDateValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('m/d/Y');
        }

        $date = trim((string) $value);

        if ($date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->format('m/d/Y');
        } catch (Throwable) {
            return $date;
        }
    }

    private function formatAge(?LoanRequestPerson $person): ?string
    {
        if ($person === null || $person->birthdate === null) {
            return null;
        }

        try {
            $birthdate = $person->birthdate instanceof Carbon
                ? $person->birthdate
                : Carbon::parse((string) $person->birthdate);

            return (string) $birthdate->age;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Same computation as formatAge(), for callers that only have a raw
     * birthdate value (e.g. a dependent's flat EAV field) rather than a full
     * LoanRequestPerson.
     */
    private function formatAgeFromRawDate(mixed $value): ?string
    {
        if ($value === null || $this->isBlankString($value)) {
            return null;
        }

        try {
            $birthdate = $value instanceof Carbon ? $value : Carbon::parse((string) $value);

            return (string) $birthdate->age;
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Street line plus barangay for documents (Generali, Grepalife) that
     * render city/province as their own boxes -- there's no dedicated
     * barangay box on those templates, so it rides along with the street
     * line instead of needing new PDF coordinates.
     */
    private function composeAddressLine(?string $street, ?string $barangay): ?string
    {
        $street = $this->normalizeText($street);
        $barangay = $this->normalizeText($barangay);

        if ($street === null) {
            return $barangay;
        }

        return $barangay !== null ? "{$street}, {$barangay}" : $street;
    }

    private function normalizeSignaturePath(?string $value): ?string
    {
        $normalizedPath = $this->normalizeText($value);

        if ($normalizedPath === null) {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $normalizedPath) === 1) {
            $parsedPath = parse_url($normalizedPath, PHP_URL_PATH);
            $normalizedPath = is_string($parsedPath) ? $parsedPath : '';
        }

        $normalizedPath = str_replace('\\', '/', rawurldecode($normalizedPath));
        $normalizedPath = explode('?', $normalizedPath, 2)[0];
        $normalizedPath = explode('#', $normalizedPath, 2)[0];
        $normalizedPath = preg_replace(
            '#^/?(?:storage/app/public/|public/storage/|storage/)#i',
            '',
            $normalizedPath,
        ) ?? $normalizedPath;

        foreach ([
            '/storage/app/public/',
            '/public/storage/',
            '/storage/',
        ] as $marker) {
            $markerPosition = stripos($normalizedPath, $marker);

            if ($markerPosition === false) {
                continue;
            }

            $normalizedPath = substr(
                $normalizedPath,
                $markerPosition + strlen($marker),
            );

            break;
        }

        $normalizedPath = ltrim($normalizedPath, '/');
        $normalizedPath = preg_replace(
            '#^(?:app/public/|public/)+#i',
            '',
            $normalizedPath,
        ) ?? $normalizedPath;
        $normalizedPath = ltrim($normalizedPath, '/');

        return $normalizedPath !== '' ? $normalizedPath : null;
    }

    private function normalizePaydayValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (in_array($trimmed, ['Weekly', '15th', '30th', '15th & 30th', 'Bi-Weekly', 'Monthly', 'Lump sum'], true)) {
            return $trimmed;
        }

        $upper = strtoupper($trimmed);
        $compact = preg_replace('/[^0-9A-Z]/', '', $upper) ?? '';

        return match (true) {
            $upper === 'WEEKLY' => 'Weekly',
            $upper === 'MONTHLY' => 'Monthly',
            $compact === 'BIWEEKLY' => 'Bi-Weekly',
            $compact === '15' => '15th',
            $compact === '30' => '30th',
            str_contains($upper, '15') && str_contains($upper, '30') => '15th & 30th',
            default => null,
        };
    }

    private function resolveWorkbookPaymentModeValue(?string $value): ?string
    {
        $payday = $this->normalizePaydayValue($value);

        return match ($payday) {
            'Weekly' => 'WEEKLY',
            'Bi-Weekly' => 'BI-WEEKLY',
            '15th & 30th' => 'SEMI-MONTHLY',
            '15th',
            '30th',
            'Monthly' => 'MONTHLY',
            'Lump sum' => 'LUMPSUM',
            default => null,
        };
    }

    private function resolveWorkbookPaymentMode(?LoanRequestPerson $applicant): ?string
    {
        return $this->resolveWorkbookPaymentModeValue($applicant?->payday);
    }

    private function resolveAmortizationCount(
        ?int $approvedTerm,
        ?string $paymentMode,
        ?int $lumpsumMonths = null,
    ): ?int {
        if ($paymentMode === 'LUMPSUM') {
            return $lumpsumMonths ?? 1;
        }

        if ($approvedTerm === null || $approvedTerm <= 0) {
            return null;
        }

        return match ($paymentMode) {
            'WEEKLY' => max(1, (int) round(($approvedTerm * 30) / 7)),
            'BI-WEEKLY' => max(1, (int) round(($approvedTerm * 30) / 14)),
            'SEMI-MONTHLY' => $approvedTerm * 2,
            default => $approvedTerm,
        };
    }

    private function resolveMaturityDate(
        ?CarbonInterface $approvedAt,
        ?int $approvedTerm,
    ): ?CarbonInterface {
        if (
            $approvedAt === null
            || $approvedTerm === null
            || $approvedTerm <= 0
        ) {
            return null;
        }

        return $approvedAt->copy()->addMonthsNoOverflow($approvedTerm);
    }

    /**
     * The salary/payroll deduction can only start once the loan is actually disbursed, so
     * this resolves to the next occurrence of the applicant's payday on/after
     * wibs_release_date -- not the approval date. Returns null until release is scheduled.
     */
    private function resolveDeductionStartDate(
        LoanRequest $loanRequest,
        ?string $paymentMode,
        ?int $lumpsumMonths = null,
    ): ?CarbonInterface {
        $releaseDate = $loanRequest->wibs_release_date;

        if (! $releaseDate instanceof CarbonInterface) {
            return null;
        }

        return match ($paymentMode) {
            'WEEKLY' => $releaseDate->copy()->addDays(7),
            'BI-WEEKLY' => $releaseDate->copy()->addDays(14),
            'SEMI-MONTHLY' => $this->resolveNextSemiMonthlyPayday($releaseDate),
            'LUMPSUM' => $releaseDate->copy()->addMonthsNoOverflow($lumpsumMonths ?? 1),
            default => $releaseDate->copy()->addMonthNoOverflow(),
        };
    }

    private function resolveNextSemiMonthlyPayday(CarbonInterface $from): CarbonInterface
    {
        $day = (int) $from->format('j');

        if ($day <= 15) {
            return $from->copy()->day(15);
        }

        return $from->copy()->endOfMonth()->startOfDay();
    }

    private function resolveDocumentDate(LoanRequest $loanRequest): ?CarbonInterface
    {
        if ($loanRequest->approved_at instanceof CarbonInterface) {
            return $loanRequest->approved_at;
        }

        $approvedAt = trim((string) $loanRequest->approved_at);

        if ($approvedAt !== '') {
            try {
                return Carbon::parse($approvedAt);
            } catch (Throwable) {
            }
        }

        if ($loanRequest->reviewed_at instanceof CarbonInterface) {
            return $loanRequest->reviewed_at;
        }

        $reviewedAt = trim((string) $loanRequest->reviewed_at);

        if ($reviewedAt === '') {
            return null;
        }

        try {
            return Carbon::parse($reviewedAt);
        } catch (Throwable) {
            return null;
        }
    }

    private function isBlankString(mixed $value): bool
    {
        if (! is_scalar($value)) {
            return true;
        }

        return trim((string) $value) === '';
    }

    private function formatCurrencyWords(float|int|string|null $value): ?string
    {
        if ($value === null || ! is_numeric((string) $value)) {
            return null;
        }

        if (! class_exists(NumberFormatter::class)) {
            return null;
        }

        try {
            $amount = round((float) $value, 2);
            $wholeNumber = (int) floor($amount);
            $decimalPart = (int) round(($amount - $wholeNumber) * 100);
            $formatter = new NumberFormatter('en', NumberFormatter::SPELLOUT);
            $formatted = $formatter->format($wholeNumber);

            if (! is_string($formatted) || trim($formatted) === '') {
                return null;
            }

            $words = strtoupper(str_replace('-', ' ', $formatted));
            $words = preg_replace('/\s+/', ' ', $words);
            $words = trim((string) $words);

            if ($decimalPart > 0) {
                return sprintf(
                    '%s PESOS AND %02d/100 ONLY.',
                    $words,
                    $decimalPart,
                );
            }

            return $words.' PESOS ONLY.';
        } catch (Throwable) {
            return null;
        }
    }

    private function formatPercentWords(float|int|string|null $value): ?string
    {
        if ($value === null || ! is_numeric((string) $value)) {
            return null;
        }

        $percentage = round((float) $value * 100, 2);
        $percentageLabel = fmod($percentage, 1.0) === 0.0
            ? number_format($percentage, 0, '.', '')
            : number_format($percentage, 2, '.', '');

        if (! class_exists(NumberFormatter::class)) {
            return $percentageLabel.' PERCENT ('.$percentageLabel.'%)';
        }

        try {
            $formatter = new NumberFormatter('en', NumberFormatter::SPELLOUT);
            $formatted = $formatter->format((int) round($percentage));

            if (! is_string($formatted) || trim($formatted) === '') {
                return $percentageLabel.' PERCENT ('.$percentageLabel.'%)';
            }

            $words = strtoupper(str_replace('-', ' ', $formatted));
            $words = preg_replace('/\s+/', ' ', $words);

            return trim((string) $words).' PERCENT ('.$percentageLabel.'%)';
        } catch (Throwable) {
            return $percentageLabel.' PERCENT ('.$percentageLabel.'%)';
        }
    }

    private function sumAmounts(float|int|null ...$values): ?float
    {
        $sum = 0.0;
        $hasValue = false;

        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $sum += (float) $value;
            $hasValue = true;
        }

        return $hasValue ? $sum : null;
    }

    private function roundCurrency(float|int|null $value): ?float
    {
        if ($value === null) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function personFullName(?LoanRequestPerson $person): ?string
    {
        if ($person === null) {
            return null;
        }

        $formatted = PersonName::format($person->first_name, $person->middle_name, $person->last_name);

        return $formatted !== '' ? $formatted : null;
    }

    private function resolvePerson(
        LoanRequest $loanRequest,
        LoanRequestPersonRole $role,
    ): ?LoanRequestPerson {
        if (! $loanRequest->relationLoaded('people')) {
            $loanRequest->loadMissing('people');
        }

        return $loanRequest->people
            ->first(function (LoanRequestPerson $person) use ($role): bool {
                $personRole = $person->role instanceof LoanRequestPersonRole
                    ? $person->role->value
                    : (string) $person->role;

                return $personRole === $role->value;
            });
    }
}
