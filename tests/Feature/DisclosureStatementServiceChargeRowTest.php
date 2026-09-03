<?php

function renderDisclosureStatement(array $loanOverrides): string
{
    $loan = array_merge([
        'reference' => 'LR-0001',
        'approved_amount_raw' => 10000.0,
        'interest_rate_raw' => 0.02,
        'interest_not_deducted_raw' => 200.0,
        'service_charge_rate_raw' => 0.01,
        'service_charge_amount_raw' => 100.0,
        'finance_charge_total_raw' => 300.0,
        'insurance_premium_raw' => null,
        'loan_security_amount_raw' => null,
        'documentary_stamp_amount_raw' => null,
        'notarial_fee_raw' => null,
        'other_charges_amount_raw' => null,
        'other_charges_description' => null,
        'non_finance_charge_total_raw' => null,
        'deductions_total_raw' => null,
        'net_proceeds_raw' => null,
        'amortization_total_raw' => null,
        'payment_mode_workbook' => 'DUE-DATE',
        'kind_of_loan' => 'Regular',
        'approved_term_raw' => 1,
    ], $loanOverrides);

    return view('reports.disclosure-statement', [
        'organization' => [],
        'reportHeader' => [],
        'organizationLogoDataUri' => null,
        'reportTypography' => [],
        'applicant' => ['full_name' => 'Test Borrower', 'address' => 'Test Address'],
        'loan' => $loan,
    ])->render();
}

function serviceChargeAtRowHtml(string $html): string
{
    preg_match('/<tr>\s*<td><\/td>\s*<td class="nw" colspan="2">\(Specify\)<\/td>.*?<\/tr>/s', $html, $matches);

    expect($matches)->not->toBeEmpty();

    return $matches[0];
}

test('lumpsum disclosure statement service charge row adds deducted interest into the third column', function () {
    $html = renderDisclosureStatement([
        'payment_mode_workbook' => 'DUE-DATE',
        'interest_not_deducted_raw' => 200.0,
        'service_charge_amount_raw' => 100.0,
        'finance_charge_total_raw' => 300.0,
    ]);

    $row = serviceChargeAtRowHtml($html);

    // Deducted-from-proceeds column still shows the service charge alone.
    expect($row)->toContain('class="b9 r u">100.00<');

    // Third/rightmost column on the Service Charge At row now shows service charge + deducted interest.
    expect($row)->toContain('class="b9 bold r u">300.00<');
});

test('non-lumpsum disclosure statement service charge row leaves the third column as the service charge alone', function () {
    $html = renderDisclosureStatement([
        'payment_mode_workbook' => 'Monthly',
        'interest_not_deducted_raw' => null,
        'service_charge_amount_raw' => 100.0,
        'finance_charge_total_raw' => 100.0,
    ]);

    $row = serviceChargeAtRowHtml($html);

    expect($row)->toContain('class="b9 r u">100.00<');
    expect($row)->toContain('class="b9 bold r u">100.00<');
});
