<?php

namespace App\Services\LoanRequests;

/**
 * The one loan-charge / net-proceeds / amortization formula. Every caller
 * resolves its own rates (staff overrides, saved processing values, or
 * institutional defaults) and hands them in here, so the staff processing
 * preview, the generated documents and the member's loan calculator can
 * never disagree on the math.
 */
class LoanFiguresCalculator
{
    // Documentary stamp tax under TRAIN is ₱1.50 for every ₱200 (or fractional
    // part thereof) of the loan amount; 1.5/200 = 0.75% is the institutional
    // constant recorded on the request, but the amount must follow the banding
    // rule rather than a flat percentage.
    public const DOCUMENTARY_STAMP_INSTITUTIONAL_RATE = 0.0075;

    private const DOCUMENTARY_STAMP_PESO_PER_BAND = 1.5;

    private const DOCUMENTARY_STAMP_BAND_SIZE = 200;

    public const NOTARIAL_FEE_DEFAULT = 100.0;

    // Mirrors WIBS desktop's loanpay.SCT: loansec = IIF(typc='01', prn*.02, prn*.05).
    // Typecode '01' ("Other Loan") carries a 2% Loan Security rate; every other
    // loan type (Micro Business, Micro Buko, Buko Unlad, ...) carries 5%. This
    // is a hardcoded WIBS rule, not a configurable rate, so it's the default
    // here too -- only an explicit override should ever change it per loan.
    private const OTHER_LOAN_TYPECODE = '01';

    private const LOAN_SECURITY_RATE_OTHER_LOAN = 0.02;

    private const LOAN_SECURITY_RATE_DEFAULT = 0.05;

    public function defaultLoanSecurityRate(?string $typecode): float
    {
        return $typecode === self::OTHER_LOAN_TYPECODE
            ? self::LOAN_SECURITY_RATE_OTHER_LOAN
            : self::LOAN_SECURITY_RATE_DEFAULT;
    }

    /**
     * @param  array{
     *     amount: float|int|null,
     *     term: int|null,
     *     payment_mode: string|null,
     *     lumpsum_months?: int|null,
     *     interest_rate: float|int|null,
     *     service_charge_rate: float|int|null,
     *     insurance_rate: float|int|null,
     *     insurance_term: int|null,
     *     loan_security_rate: float|int|null,
     *     savings_rate: float|int|null,
     *     documentary_stamp_rate: float|int|null,
     *     notarial_fee: float|int|null,
     *     other_charges_amount?: float|int|null,
     * }  $input  payment_mode is the workbook mode (MONTHLY, WEEKLY, DUE-DATE, ...)
     * @return array<string, float|int|null>
     */
    public function calculate(array $input): array
    {
        $amount = $input['amount'] !== null ? (float) $input['amount'] : null;
        $term = $input['term'];
        $paymentMode = $input['payment_mode'];
        $isLumpsum = $paymentMode === 'DUE-DATE';
        $interestRate = $input['interest_rate'];
        $serviceChargeRate = $input['service_charge_rate'];
        $insuranceRate = $input['insurance_rate'];
        $insuranceTerm = $input['insurance_term'];
        $loanSecurityRate = $input['loan_security_rate'];
        $savingsRate = $input['savings_rate'];
        $documentaryStampRate = $input['documentary_stamp_rate'];
        $amortizationCount = $this->amortizationCount($term, $paymentMode, $input['lumpsum_months'] ?? null);

        $interestNotDeducted = $this->roundCurrency(
            $amount !== null && $term !== null && $interestRate !== null
                ? ($amount * $interestRate / 12) * $term
                : null,
        );
        $serviceChargeAmount = $this->roundCurrency(
            $amount !== null && $serviceChargeRate !== null
                ? $amount * $serviceChargeRate
                : null,
        );
        $insurancePremium = $this->roundCurrency(
            $amount !== null && $insuranceTerm !== null && $insuranceRate !== null
                ? ($amount / 1000) * $insuranceTerm * $insuranceRate
                : null,
        );
        $loanSecurityAmount = $this->roundCurrency(
            $amount !== null && $loanSecurityRate !== null
                ? $amount * $loanSecurityRate
                : null,
        );
        $documentaryStampAmount = $this->roundCurrency(
            $amount !== null && $documentaryStampRate !== null
                ? $this->documentaryStampAmount($amount, $documentaryStampRate)
                : null,
        );
        $principalAmortization = $this->roundCurrency(
            $amount !== null && $amortizationCount !== null && $amortizationCount > 0
                ? $amount / $amortizationCount
                : null,
        );
        $interestAmortization = $this->roundCurrency(
            $interestNotDeducted !== null && $amortizationCount !== null && $amortizationCount > 0
                ? $interestNotDeducted / $amortizationCount
                : null,
        );
        $loanSecurityAmortization = $this->roundCurrency(
            $principalAmortization !== null && $savingsRate !== null
                ? $principalAmortization * $savingsRate
                : null,
        );
        $amortizationTotal = $this->roundCurrency(
            $this->sumAmounts($principalAmortization, $interestAmortization, $loanSecurityAmortization),
        );
        // Net proceeds follows the Disclosure Statement workbook (R.A. 3765):
        //
        // Monthly-amortized loans: interest is disclosed under "Not Deducted
        // From Proceeds of Loan" (it is amortized into the payment schedule
        // instead), so only the service charge counts as a deducted finance
        // charge.
        //
        // Lumpsum / Due-date loans: interest is advance interest — deducted
        // upfront from the loan proceeds because the borrower repays it in
        // full at maturity. This matches the practice of GSIS and Philippine
        // cooperatives (advance interest as a standard deduction).
        $financeChargeTotal = $this->roundCurrency(
            $this->sumAmounts($serviceChargeAmount, $isLumpsum ? $interestNotDeducted : null),
        );
        $nonFinanceChargeTotal = $this->roundCurrency(
            $this->sumAmounts(
                $insurancePremium,
                $loanSecurityAmount,
                $documentaryStampAmount,
                $input['notarial_fee'],
                $input['other_charges_amount'] ?? null,
            ),
        );
        $deductionsTotal = $this->roundCurrency($this->sumAmounts($financeChargeTotal, $nonFinanceChargeTotal));
        $netProceeds = $this->roundCurrency(
            $amount !== null && $deductionsTotal !== null ? $amount - $deductionsTotal : null,
        );

        return [
            'amortization_count' => $amortizationCount,
            'interest_not_deducted_raw' => $interestNotDeducted,
            'service_charge_amount_raw' => $serviceChargeAmount,
            'insurance_premium_raw' => $insurancePremium,
            'loan_security_amount_raw' => $loanSecurityAmount,
            'documentary_stamp_amount_raw' => $documentaryStampAmount,
            'notarial_fee_raw' => $input['notarial_fee'],
            'finance_charge_total_raw' => $financeChargeTotal,
            'non_finance_charge_total_raw' => $nonFinanceChargeTotal,
            'deductions_total_raw' => $deductionsTotal,
            'net_proceeds_raw' => $netProceeds,
            'amortization_principal_raw' => $principalAmortization,
            'amortization_interest_raw' => $interestAmortization,
            'amortization_loan_security_raw' => $loanSecurityAmortization,
            'amortization_total_raw' => $amortizationTotal,
            'monthly_amortization_raw' => $amortizationTotal !== null
                ? $amortizationTotal * $this->monthlyMultiplier($paymentMode)
                : null,
        ];
    }

    /**
     * The member calculator's estimate: the same calculate() call staff
     * preview runs, fed the institutional defaults a processor starts from.
     * Insurance covers the whole term (skipped under two months, like
     * ApprovedLoanDocumentDataBuilder); interest and service charge come from
     * config and stay null (excluded) until configured.
     *
     * @return array<string, float|int|null>
     */
    public function estimate(
        float $amount,
        int $term,
        ?string $typecode,
        ?string $paymentFrequency,
        ?float $insuranceRate,
    ): array {
        $paymentMode = $paymentFrequency !== null
            ? strtoupper(str_replace(' ', '-', $paymentFrequency))
            : 'MONTHLY';
        $isLumpsum = $paymentMode === 'DUE-DATE';
        $skipsInsurance = $term < 2;
        $loanSecurityRate = $isLumpsum ? 0.0 : $this->defaultLoanSecurityRate($typecode);
        $interestRate = config('loan_workflow.estimate.interest_rate');
        $serviceChargeRate = config('loan_workflow.estimate.service_charge_rate');

        return [
            ...$this->calculate([
                'amount' => $amount,
                'term' => $term,
                'payment_mode' => $paymentMode,
                'lumpsum_months' => $isLumpsum ? $term : null,
                'interest_rate' => $interestRate,
                'service_charge_rate' => $serviceChargeRate,
                'insurance_rate' => $skipsInsurance ? 0.0 : ($insuranceRate ?? 1.0),
                'insurance_term' => $skipsInsurance ? 0 : $term,
                'loan_security_rate' => $loanSecurityRate,
                'savings_rate' => $loanSecurityRate,
                'documentary_stamp_rate' => self::DOCUMENTARY_STAMP_INSTITUTIONAL_RATE,
                'notarial_fee' => self::NOTARIAL_FEE_DEFAULT,
            ]),
            'interest_rate' => $interestRate,
            'service_charge_rate' => $serviceChargeRate,
            'loan_security_rate' => $loanSecurityRate,
        ];
    }

    public function amortizationCount(?int $term, ?string $paymentMode, ?int $lumpsumMonths = null): ?int
    {
        if ($paymentMode === 'DUE-DATE') {
            return $lumpsumMonths ?? 1;
        }

        if ($term === null || $term <= 0) {
            return null;
        }

        return match ($paymentMode) {
            'DAILY' => $term * 30,
            'QUINCENAL' => $term * 2,
            'SEMI-ANNUAL' => max(1, (int) round($term / 6)),
            'WEEKLY' => max(1, (int) round(($term * 30) / 7)),
            'YEARLY' => max(1, (int) round($term / 12)),
            default => $term,
        };
    }

    /**
     * Monthly-equivalent multiplier, matching the day-based convention
     * amortizationCount() uses to derive payment counts (round(term*30/7)
     * for WEEKLY, etc.) — not calendar-accurate ratios.
     */
    public function monthlyMultiplier(?string $paymentMode): float
    {
        return match ($paymentMode) {
            'DAILY' => 30.0,
            'WEEKLY' => 30 / 7,
            'QUINCENAL' => 2.0,
            'SEMI-ANNUAL' => 1.0 / 6,
            'YEARLY' => 1.0 / 12,
            default => 1.0, // MONTHLY and null
        };
    }

    /**
     * When the institutional rate is in effect the client's given formula
     * applies — ₱1.50 for every ₱200 of the loan amount, with fractional parts
     * rounded up to a full ₱200 band — so non-multiple-of-₱200 loans still land
     * on the correct BIR figure. An explicit staff-entered rate (legacy data)
     * is honored as a flat percentage.
     */
    private function documentaryStampAmount(float $amount, float|int $rate): float
    {
        if (abs((float) $rate - self::DOCUMENTARY_STAMP_INSTITUTIONAL_RATE) < PHP_FLOAT_EPSILON) {
            return ceil($amount / self::DOCUMENTARY_STAMP_BAND_SIZE) * self::DOCUMENTARY_STAMP_PESO_PER_BAND;
        }

        return $amount * (float) $rate;
    }

    private function sumAmounts(float|int|null ...$values): ?float
    {
        $values = array_filter($values, fn ($value): bool => $value !== null);

        return $values === [] ? null : (float) array_sum($values);
    }

    private function roundCurrency(float|int|null $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }
}
