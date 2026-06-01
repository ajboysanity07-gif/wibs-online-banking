<?php

namespace App\Services\LoanRequests\ExcelCellMaps;

class PlanOfPaymentDisclosurePromissoryNoteExcelCellMap
{
    /**
     * @return array<int, list<array{cell: string, value: mixed, type?: string}>>
     */
    public function cells(): array
    {
        return [
            0 => [
                $this->upperText('C7', 'applicant.full_name'),
                $this->text('F7', 'applicant.employer_or_business'),
                $this->text('C8', 'applicant.address'),
                $this->numeric('C9', 'loan.approved_amount_raw'),
                $this->numeric('C10', 'loan.interest_rate_raw'),
                $this->numeric('C11', 'loan.approved_term_raw'),
                $this->numeric('C12', 'loan.service_charge_rate_raw'),
                $this->numeric('C13', 'loan.interest_not_deducted_raw'),
                $this->upperText('C14', 'reviewer.name'),
                $this->text('C15', 'reviewer.position'),
                $this->text('C16', 'loan.type'),
                $this->text('C17', 'loan.payment_mode_workbook'),
                $this->numeric('E17', 'loan.amortization_count'),
                $this->upperText('C18', 'reviewer.name'),
                $this->numeric('C19', 'loan.insurance_term'),
                $this->numeric('C20', 'loan.insurance_rate_raw'),
                $this->numeric('C21', 'loan.service_charge_amount_raw'),
                $this->numeric('C22', 'loan.insurance_premium_raw'),
                $this->numeric('C23', 'loan.loan_security_amount_raw'),
                $this->numeric('C24', 'loan.documentary_stamp_amount_raw'),
                $this->numeric('C25', 'loan.notarial_fee_raw'),
                $this->numeric('C27', 'loan.amortization_principal_raw'),
                $this->numeric('C28', 'loan.amortization_interest_raw'),
                $this->numeric('C29', 'loan.amortization_loan_security_raw'),
                $this->numeric('C30', 'loan.amortization_total_raw'),
                $this->upperText('C32', 'co_maker_one.full_name'),
                $this->upperText('C33', 'co_maker_two.full_name'),
                $this->text('C34', 'co_maker_one.address'),
                $this->text('C35', 'co_maker_two.address'),
                $this->numeric('C36', 'loan.term_days'),
                $this->text('C37', 'loan.approved_amount_words'),
                $this->text('C38', 'loan.interest_rate_words'),
                $this->text('C39', 'loan.payment_mode_workbook'),
                $this->numeric('C40', 'loan.amortization_count'),
                $this->numeric('C41', 'loan.penalty_rate_raw'),
                $this->upperText('C42', 'reviewer.witness_one_name'),
                $this->upperText('C43', 'reviewer.witness_two_name'),
            ],
            1 => [
                $this->upperText('D9', 'applicant.full_name'),
                $this->text('D10', 'applicant.address'),
                $this->numeric('D11', 'loan.approved_amount_raw'),
                $this->text('D12', 'loan.type'),
                $this->text('B15', 'loan.payment_mode_workbook'),
                $this->numeric('D17', 'loan.amortization_principal_raw'),
                $this->numeric('D18', 'loan.amortization_interest_raw'),
                $this->numeric('D19', 'loan.amortization_loan_security_raw'),
                $this->numeric('D20', 'loan.amortization_total_raw'),
                $this->text('C22', 'loan.approved_date_short'),
                $this->text('G22', 'loan.maturity_date_short'),
                $this->upperText('B27', 'applicant.full_name'),
                $this->upperText('G27', 'reviewer.name'),
                $this->upperText('D41', 'applicant.full_name'),
                $this->text('D42', 'applicant.address'),
                $this->numeric('D43', 'loan.approved_amount_raw'),
                $this->text('D44', 'loan.type'),
                $this->text('B47', 'loan.payment_mode_workbook'),
                $this->numeric('D49', 'loan.amortization_principal_raw'),
                $this->numeric('D50', 'loan.amortization_interest_raw'),
                $this->numeric('D51', 'loan.amortization_loan_security_raw'),
                $this->numeric('D52', 'loan.amortization_total_raw'),
                $this->text('C54', 'loan.approved_date_short'),
                $this->text('G54', 'loan.maturity_date_short'),
                $this->upperText('B59', 'applicant.full_name'),
                $this->upperText('G59', 'reviewer.name'),
            ],
            2 => [
                $this->upperText('D7', 'applicant.full_name'),
                $this->text('C8', 'applicant.address'),
                $this->numeric('N9', 'loan.approved_amount_raw'),
                $this->text('M7', 'loan.reference'),
                $this->numeric('D14', 'loan.interest_rate_raw'),
                $this->text('F14', 'loan.approved_date_short'),
                $this->text('H14', 'loan.maturity_date_short'),
                $this->numeric('F23', 'loan.service_charge_rate_raw'),
                $this->numeric('L23', 'loan.service_charge_amount_raw'),
                $this->numeric('F28', 'loan.insurance_premium_raw'),
                $this->numeric('F29', 'loan.loan_security_amount_raw'),
                $this->numeric('F30', 'loan.documentary_stamp_amount_raw'),
                $this->numeric('F31', 'loan.notarial_fee_raw'),
                $this->numeric('F41', 'loan.amortization_total_raw'),
                $this->numeric('D42', 'loan.approved_term_raw'),
                $this->upperText('L50', 'reviewer.name'),
                $this->text('L52', 'reviewer.position'),
                $this->upperText('L57', 'applicant.full_name'),
                $this->text('F40', 'loan.maturity_date_short'),
            ],
            3 => [
                $this->text('I8', 'loan.approved_date_short'),
                $this->text('I9', 'loan.maturity_date_short'),
                $this->numeric('J10', 'loan.approved_amount_raw'),
                $this->numeric('A12', 'loan.term_days'),
                $this->text('D13', 'loan.approved_amount_words'),
                $this->text('E14', 'loan.interest_rate_words'),
                $this->numeric('A15', 'loan.amortization_total_raw'),
                $this->text('E15', 'loan.payment_mode_workbook'),
                $this->text('G15', 'loan.approved_date_short'),
                $this->text('J15', 'loan.maturity_date_short'),
                $this->upperText('B50', 'applicant.full_name'),
                $this->upperText('E50', 'co_maker_one.full_name'),
                $this->upperText('I50', 'co_maker_two.full_name'),
                $this->text('C53', 'applicant.address'),
                $this->text('E53', 'co_maker_one.address'),
                $this->text('I53', 'co_maker_two.address'),
                $this->upperText('B58', 'reviewer.witness_one_name'),
                $this->upperText('H58', 'reviewer.witness_two_name'),
            ],
        ];
    }

    /**
     * @return array{cell: string, value: string, type: string}
     */
    private function numeric(string $cell, string $value): array
    {
        return [
            'cell' => $cell,
            'value' => $value,
            'type' => 'numeric',
        ];
    }

    /**
     * @return array{cell: string, value: string, type: string}
     */
    private function text(string $cell, string $value): array
    {
        return [
            'cell' => $cell,
            'value' => $value,
            'type' => 'string',
        ];
    }

    /**
     * @return array{cell: string, value: callable, type: string}
     */
    private function upperText(string $cell, string $value): array
    {
        return [
            'cell' => $cell,
            'value' => $this->upperTransform($value),
            'type' => 'string',
        ];
    }

    private function upper(?string $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    private function upperTransform(string $path): callable
    {
        return function (array $documentData) use ($path): string {
            $value = data_get($documentData, $path);

            return $this->upper(is_scalar($value) ? (string) $value : null);
        };
    }
}
