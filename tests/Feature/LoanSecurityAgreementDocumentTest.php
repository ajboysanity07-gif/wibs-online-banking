<?php

it('always renders signature-line names in capital letters', function () {
    $html = view('reports.loan-security-agreement', [
        'organization' => ['company_name' => 'Acme Cooperative'],
        'applicant' => ['full_name' => 'Juan Dela Cruz', 'address' => 'Sample Address'],
        'loan' => ['type' => 'SALARY LOAN'],
        'reviewer' => ['name' => 'Annabelle M. Amora', 'position' => 'Loan Manager'],
        'reportHeader' => ['designData' => null],
        'reportTypography' => [],
        'organizationLogoDataUri' => null,
    ])->render();

    preg_match('/\.signature-name\s*\{([^}]*)\}/', $html, $matches);

    expect($matches)->not->toBeEmpty();
    expect($matches[1])->toContain('text-transform: uppercase');
});
