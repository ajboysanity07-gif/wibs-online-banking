<?php

use App\LoanRequestDocumentKey;
use App\Services\LoanRequests\LoanRequestDocumentCatalog;

test('affidavit undertaking regenerates when a notarization field changes', function (string $fieldKey): void {
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->usesChangedFields(
        LoanRequestDocumentKey::AffidavitUndertaking,
        [$fieldKey],
    ))->toBeTrue();
})->with([
    'signing_place',
    'notarial_province',
    'valid_id_number',
    'valid_id_issued_at',
    'doc_number',
    'page_number',
    'book_number',
    'series_year',
]);

test('affidavit undertaking no longer regenerates on the dropped payout_account_name field', function (): void {
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->usesChangedFields(
        LoanRequestDocumentKey::AffidavitUndertaking,
        ['payout_account_name'],
    ))->toBeFalse();
});
