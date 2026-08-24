<?php

use App\Services\OrganizationSettingsService;

test('plan of payment report shows the static mode-of-payment box instead of computed amortization amounts', function () {
    $branding = app(OrganizationSettingsService::class)->branding();

    $html = view('reports.plan-of-payment', [
        'organization' => ['company_name' => 'Acme Cooperative'],
        'loan' => [
            'approved_amount_raw' => 50000,
            'type' => 'SALARY LOAN',
            'payment_mode_workbook' => 'Monthly',
        ],
        'applicant' => ['full_name' => 'Loan Member', 'address' => 'Sample Address'],
        'reviewer' => ['name' => 'Annabelle M. Amora'],
        'reportHeader' => ['designData' => null],
        'reportTypography' => $branding['reportTypography'],
        'organizationLogoDataUri' => null,
    ])->render();

    expect($html)->toContain('MODE OF PAYMENT')
        ->toContain('MONTHLY')
        ->toContain('PLEASE SEE ATTACHED')
        ->toContain('LOAN AMORTIZATION')
        ->toContain('SCHEDULE')
        ->toContain('Starting:')
        ->toContain('Ending:')
        ->not->toContain('Total Amortization')
        ->not->toContain('Date Granted');

    // The old Blade-escaped {{ ... : '&nbsp;' }} subtitle fallback rendered the
    // literal text "&nbsp;" beneath the MODE OF PAYMENT title. The rendered PDF
    // must never contain that literal text again.
    expect($html)->not->toContain('&amp;nbsp;');
});

test('plan of payment report shows the actual principal amount for lumpsum loans', function () {
    $branding = app(OrganizationSettingsService::class)->branding();

    $html = view('reports.plan-of-payment', [
        'organization' => ['company_name' => 'Acme Cooperative'],
        'loan' => [
            'approved_amount_raw' => 50000,
            'type' => 'SALARY LOAN',
            'payment_mode_workbook' => 'LUMPSUM',
            'amortization_principal_raw' => 50000,
        ],
        'applicant' => ['full_name' => 'Loan Member', 'address' => 'Sample Address'],
        'reviewer' => ['name' => 'Annabelle M. Amora'],
        'reportHeader' => ['designData' => null],
        'reportTypography' => $branding['reportTypography'],
        'organizationLogoDataUri' => null,
    ])->render();

    expect($html)->toContain('MODE OF PAYMENT')
        ->toContain('LUMPSUM')
        ->toContain('&#8369; 50,000.00')
        ->not->toContain('PLEASE SEE ATTACHED')
        ->not->toContain('LOAN AMORTIZATION')
        ->not->toContain('SCHEDULE')
        ->not->toContain('&amp;nbsp;');
});
