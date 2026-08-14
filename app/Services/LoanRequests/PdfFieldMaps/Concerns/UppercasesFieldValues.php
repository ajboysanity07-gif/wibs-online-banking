<?php

namespace App\Services\LoanRequests\PdfFieldMaps\Concerns;

trait UppercasesFieldValues
{
    private function upper(?string $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    private function upperTransform(): callable
    {
        return fn (mixed $value): string => $this->upper(
            is_scalar($value) ? (string) $value : null,
        );
    }
}
