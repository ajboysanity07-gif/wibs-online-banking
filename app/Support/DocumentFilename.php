<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Single naming convention for every generated/downloaded document in the
 * application: {REFERENCE}-{DOCUMENT_CODE}-{YYYYMMDD}.{ext}. The reference
 * and document code are the identifiers that already make a document unique
 * and traceable (loan reference, loan number, report type, ...) -- deliberately
 * never a person's name.
 */
class DocumentFilename
{
    public static function build(
        string $reference,
        string $documentCode,
        string $extension,
        ?CarbonInterface $generatedAt = null,
    ): string {
        $reference = self::normalize($reference, 'DOC');
        $documentCode = self::normalize($documentCode, 'DOCUMENT');
        $date = ($generatedAt ?? Carbon::now())->format('Ymd');
        $extension = ltrim($extension, '.');

        return "{$reference}-{$documentCode}-{$date}.{$extension}";
    }

    private static function normalize(string $value, string $fallback): string
    {
        $normalized = strtoupper($value);
        $normalized = preg_replace('/[^A-Z0-9]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : $fallback;
    }
}
