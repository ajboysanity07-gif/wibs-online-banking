<?php

use App\LoanRequestDocumentKey;
use App\Services\LoanRequests\LoanRequestDocumentCatalog;

test('generali template version is v1', function (): void {
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->templateVersionFor(LoanRequestDocumentKey::Generali))
        ->toBe('generali-v1');
});

test('generali regenerates when a GLAPI health answer changes', function (string $fieldKey): void {
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->usesChangedFields(
        LoanRequestDocumentKey::Generali,
        [$fieldKey],
    ))->toBeTrue();
})->with([
    'beneficiary_primary_name',
    'beneficiary_secondary_birthdate',
    'health_hypertension',
    'health_hypertension_details',
    'gl_health_q01_weight_change',
    'gl_health_q11_smoker',
    'gl_health_q17_with_glapi_amount',
]);
