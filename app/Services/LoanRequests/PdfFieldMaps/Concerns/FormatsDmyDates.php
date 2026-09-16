<?php

namespace App\Services\LoanRequests\PdfFieldMaps\Concerns;

use Carbon\Carbon;

trait FormatsDmyDates
{
    /**
     * Re-parses an upstream-formatted date string and re-emits it as DD/MM/YYYY.
     * Falls back to the original value on parse failure -- these documents are
     * printed legal forms, so a mis-parsed date must never silently vanish.
     */
    private static function toDmy(mixed $value, string $sourceFormat): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat($sourceFormat, $value)->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }
}
