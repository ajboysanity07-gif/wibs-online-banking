<?php

use App\LoanRequestDocumentKey;
use App\Services\LoanRequests\LoanRequestDocumentCatalog;

test('affidavit undertaking template version is bumped to v6 for the shrink-to-fit artwork rebuild', function (): void {
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->templateVersionFor(LoanRequestDocumentKey::AffidavitUndertaking))
        ->toBe('affidavit-undertaking-v6');
});

test('affidavit undertaking no longer regenerates on the dropped payout_account_name field', function (): void {
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->usesChangedFields(
        LoanRequestDocumentKey::AffidavitUndertaking,
        ['payout_account_name'],
    ))->toBeFalse();
});

test('affidavit undertaking no longer regenerates on the org-address-sourced or removed notarization fields', function (string $fieldKey): void {
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->usesChangedFields(
        LoanRequestDocumentKey::AffidavitUndertaking,
        [$fieldKey],
    ))->toBeFalse();
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
