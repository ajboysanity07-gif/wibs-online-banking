<?php

namespace App\Services\LoanRequests\PdfFieldMaps;

interface ApprovedLoanPdfFieldMap
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function fields(): array;
}
