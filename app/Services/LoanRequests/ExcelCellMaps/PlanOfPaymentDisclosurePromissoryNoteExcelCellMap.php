<?php

namespace App\Services\LoanRequests\ExcelCellMaps;

class PlanOfPaymentDisclosurePromissoryNoteExcelCellMap
{
    /**
     * @return array<int, list<array{cell: string, value: string, type?: string}>>
     */
    public function cells(): array
    {
        return [
            0 => [
                $this->text('C7', 'applicant.full_name'),
                $this->text('F7', 'applicant.employer_or_business'),
                $this->text('C8', 'applicant.address'),
                $this->numeric('C9', 'loan.approved_amount_raw'),
                $this->numeric('C10', 'loan.interest_rate_raw'),
                $this->numeric('C11', 'loan.approved_term_raw'),
                $this->numeric('C12', 'loan.service_charge_rate_raw'),
                $this->text('C14', 'reviewer.name'),
                $this->text('C15', 'reviewer.position'),
                $this->text('C16', 'loan.type'),
                $this->text('C17', 'loan.payment_mode_workbook'),
                $this->numeric('E17', 'loan.amortization_count'),
                $this->text('C18', 'reviewer.name'),
                $this->numeric('C19', 'loan.insurance_term'),
                $this->numeric('C20', 'loan.insurance_rate_raw'),
                $this->text('C32', 'co_maker_one.full_name'),
                $this->text('C33', 'co_maker_two.full_name'),
                $this->text('C34', 'co_maker_one.address'),
                $this->text('C35', 'co_maker_two.address'),
                $this->numeric('C36', 'loan.term_days'),
                $this->text('C37', 'loan.approved_amount_words'),
                $this->text('C38', 'loan.interest_rate_words'),
                $this->text('C39', 'loan.payment_mode_workbook'),
                $this->numeric('C40', 'loan.amortization_count'),
            ],
            1 => [
                $this->text('C22', 'loan.approved_date_short'),
                $this->text('G22', 'loan.maturity_date_short'),
            ],
            2 => [
                $this->text('M7', 'loan.reference'),
                $this->text('F14', 'loan.approved_date_short'),
                $this->text('H14', 'loan.maturity_date_short'),
                $this->text('F40', 'loan.maturity_date_short'),
            ],
            3 => [
                $this->text('I8', 'loan.approved_date_short'),
                $this->text('I9', 'loan.maturity_date_short'),
                $this->text('G15', 'loan.approved_date_short'),
                $this->text('J15', 'loan.maturity_date_short'),
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
}
