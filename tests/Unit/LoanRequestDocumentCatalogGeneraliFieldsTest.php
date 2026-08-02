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
    'health_smoking_status',
    'gl_health_q17_with_glapi_amount',
    'source_of_fund_wealth',
    'id_type',
    'id_type_other',
    'id_number',
    'height_cm',
    'weight_kg',
]);
