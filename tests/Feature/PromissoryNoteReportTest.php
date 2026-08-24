<?php

use App\Services\OrganizationSettingsService;

test('promissory note leaves the amortization amount hand-fill but prints the payment frequency for installment loans', function () {
    $branding = app(OrganizationSettingsService::class)->branding();

    $html = view('reports.promissory-note', [
        'organization' => ['company_name' => 'Acme Cooperative'],
        'loan' => [
            'approved_amount_raw' => 25000,
            'amortization_total_raw' => 2083.33,
            'payment_mode_workbook' => 'SEMI-MONTHLY',
            'amortization_count' => 24,
        ],
        'applicant' => [],
        'co_maker_one' => [],
        'co_maker_two' => [],
        'reviewer' => [],
        'reportHeader' => ['designData' => null],
        'reportTypography' => $branding['reportTypography'],
        'organizationLogoDataUri' => null,
    ])->render();

    // Same convention as the Disclosure Statement's blanked "Total Installment
    // Payment" figure: the per-installment amount is filled in by hand -- only
    // lumpsum loans print the computed value. The payment-frequency label
    // always prints, regardless of lumpsum, in lowercase to match the
    // surrounding prose sentence.
    expect($html)
        ->toContain('Amortization/Installment payment of')
        ->toContain('note-blank')
        ->not->toContain('note-fill">2,083.33</span>')
        ->toContain('note-fill">semi-monthly</span>');
});

test('promissory note prints the computed single-payment value for lumpsum loans', function () {
    $branding = app(OrganizationSettingsService::class)->branding();

    $html = view('reports.promissory-note', [
        'organization' => ['company_name' => 'Acme Cooperative'],
        'loan' => [
            'approved_amount_raw' => 50000,
            'amortization_total_raw' => 51500,
            'payment_mode_workbook' => 'LUMPSUM',
            'amortization_count' => 1,
        ],
        'applicant' => [],
        'co_maker_one' => [],
        'co_maker_two' => [],
        'reviewer' => [],
        'reportHeader' => ['designData' => null],
        'reportTypography' => $branding['reportTypography'],
        'organizationLogoDataUri' => null,
    ])->render();

    expect($html)
        ->toContain('note-fill">51,500.00</span>')
        ->toContain('note-fill">lump sum</span>');
});

test('promissory note prints a lowercase payment frequency label for monthly loans', function () {
    $branding = app(OrganizationSettingsService::class)->branding();

    $html = view('reports.promissory-note', [
        'organization' => ['company_name' => 'Acme Cooperative'],
        'loan' => [
            'approved_amount_raw' => 25000,
            'amortization_total_raw' => 2083.33,
            'payment_mode_workbook' => 'MONTHLY',
            'amortization_count' => 12,
        ],
        'applicant' => [],
        'co_maker_one' => [],
        'co_maker_two' => [],
        'reviewer' => [],
        'reportHeader' => ['designData' => null],
        'reportTypography' => $branding['reportTypography'],
        'organizationLogoDataUri' => null,
    ])->render();

    expect($html)->toContain('note-fill">monthly</span>');
});
