<?php

use App\LoanPaymentOption;
use App\LoanRequestDocumentKey;
use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Models\LoanRequestPerson;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
use App\Services\LoanRequests\LoanRequestDocumentCatalog;
use App\Services\LoanRequests\PdcSchedulePdfService;

function pdcScheduleCreateApprovedLoanRequest(): LoanRequest
{
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now()->subDay(),
        'reviewed_at' => now(),
        'approved_amount' => 24000,
        'approved_term' => 12,
        'approved_interest_rate' => 0.36,
        'recommended_interest_rate' => 0.36,
        'recommended_payment_frequency' => '15th & 30th',
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Sample',
            'middle_name' => 'Q',
            'last_name' => 'Member',
            'address1' => '123 Loan Street',
            'address2' => 'Loan City',
            'address3' => 'Loan Province',
        ]);

    return $loanRequest;
}

function pdcSchedulePersistDataEntry(LoanRequest $loanRequest, string $fieldKey, mixed $value): void
{
    LoanRequestDataEntry::query()->updateOrCreate(
        [
            'loan_request_id' => $loanRequest->id,
            'field_key' => $fieldKey,
        ],
        [
            'section_key' => 'processing',
            'owner_type' => 'staff',
            'value_type' => 'string',
            'value_json' => ['value' => $value],
            'is_sensitive' => false,
            'confirmed_by_member' => false,
            'confirmed_by_member_at' => null,
        ],
    );
}

beforeEach(function () {
    config()->set('reports.pdf_driver', 'dompdf');
});

test('pdc schedule is applicable only when payment option is check', function () {
    $loanRequest = pdcScheduleCreateApprovedLoanRequest();
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(
        LoanRequestDocumentKey::PdcSchedule,
        $loanRequest->fresh(),
        ['payment_option' => LoanPaymentOption::Check->value],
    ))->toBeTrue();

    expect($catalog->isApplicable(
        LoanRequestDocumentKey::PdcSchedule,
        $loanRequest->fresh(),
        ['payment_option' => LoanPaymentOption::AtmDeduction->value],
    ))->toBeFalse();
})->skip('Temporarily disabled - see LoanRequestDocumentKey::temporarilyDisabled()');

test('pdc schedule has no missing official template blockers', function () {
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->templateBlockers(LoanRequestDocumentKey::PdcSchedule))->toBe([]);
});

test('pdc schedule generates a real pdf with per-check principal, interest, loan security, and total', function () {
    $loanRequest = pdcScheduleCreateApprovedLoanRequest();
    pdcSchedulePersistDataEntry($loanRequest, 'payment_option', LoanPaymentOption::Check->value);
    pdcSchedulePersistDataEntry($loanRequest, 'pdc_drawee_bank', 'Sample Bank of the Philippines');

    $service = app(ApprovedLoanDocumentService::class);

    $outputPath = storage_path('app/testing/pdc-schedule-'.$loanRequest->id.'.pdf');

    $result = $service->generateToPathForKey(
        $loanRequest->fresh(),
        LoanRequestDocumentKey::PdcSchedule,
        $outputPath,
    );

    expect($result['mime_type'])->toBe('application/pdf');
    expect(is_file($outputPath))->toBeTrue();

    $content = file_get_contents($outputPath);
    expect($content)->toStartWith('%PDF');

    @unlink($outputPath);
});

test('pdc schedule uses declining-balance amortization, not the flat add-on figures', function () {
    $loan = [
        'approved_amount_raw' => 24000.0,
        'interest_rate_recommended_raw' => 0.36,
        'amortization_count' => 24,
        'payment_mode_workbook' => 'QUINCENAL',
        'lumpsum_months' => null,
        // The LS column is driven by loan_security_rate_standard_raw, not
        // savings_rate_raw -- confirmed against a real, borrower-signed
        // Annex A (Loan Security computed independently of Service Charge
        // and of the UI's mirrored "savings" field). It always uses the
        // standard typecode-based rate, ignoring any staff override.
        'loan_security_rate_standard_raw' => 0.02,
    ];

    $service = app(PdcSchedulePdfService::class);
    $method = (new ReflectionClass($service))->getMethod('buildAmortizationSchedule');
    $method->setAccessible(true);

    $schedule = $method->invoke($service, $loan);
    $rows = $schedule['rows'];

    expect($rows)->toHaveCount(24);

    // Periodic rate = 36% / 24 quincenal periods per year = 1.5% per period.
    $periodicRate = 0.36 / 24;
    $factor = (1 + $periodicRate) ** 24;
    $expectedPayment = 24000.0 * $periodicRate * $factor / ($factor - 1);

    // Checks are for whole-peso amounts - every figure is rounded to the
    // nearest peso, not centavos.
    foreach ($rows as $row) {
        foreach (['principal', 'interest', 'loan_security', 'total'] as $field) {
            expect($row[$field])->toBe(round($row[$field]));
        }
    }

    // Payment (principal + interest) stays within a peso of the theoretical
    // constant payment every period, unlike the flat add-on method where
    // principal is flat but interest is also flat.
    foreach (array_slice($rows, 0, 23) as $row) {
        expect(abs(($row['principal'] + $row['interest']) - $expectedPayment))->toBeLessThanOrEqual(1.0);
    }

    // Interest declines and principal grows as the balance amortizes.
    expect($rows[0]['interest'])->toBeGreaterThan($rows[1]['interest']);
    expect($rows[0]['principal'])->toBeLessThan($rows[1]['principal']);

    // The flat/add-on method divides interest evenly across every row (360
    // each); declining-balance must diverge from that flat figure by the last row.
    expect($rows[23]['interest'])->not->toBe(360.0);

    // Total principal across all checks must reconcile to the loan amount.
    expect($schedule['totals']['principal'])->toBe(24000.0);

    // Loan security is spread evenly across every check (like the flat/
    // add-on figures elsewhere) — it must NOT scale with that row's rising
    // principal share, or it would grow check-to-check instead of staying flat.
    $flatLoanSecurity = round(24000.0 * 0.02 / 24);
    foreach (array_slice($rows, 0, 23) as $row) {
        expect($row['loan_security'])->toBe($flatLoanSecurity);
    }
    expect($schedule['totals']['loan_security'])->toBe(round(24000.0 * 0.02));
});

test('pdc schedule ignores a staff-overridden loan security rate and uses the standard typecode rate', function () {
    $loan = [
        'approved_amount_raw' => 24000.0,
        'interest_rate_recommended_raw' => 0.36,
        'amortization_count' => 24,
        'payment_mode_workbook' => 'QUINCENAL',
        'lumpsum_months' => null,
        // Simulates a staff override entered in loan processing -- the
        // Annex A schedule must not use this and must fall back to the
        // standard rate instead.
        'loan_security_rate_raw' => 0.10,
        'loan_security_rate_standard_raw' => 0.05,
    ];

    $service = app(PdcSchedulePdfService::class);
    $method = (new ReflectionClass($service))->getMethod('buildAmortizationSchedule');
    $method->setAccessible(true);

    $schedule = $method->invoke($service, $loan);

    expect($schedule['totals']['loan_security'])->toBe(round(24000.0 * 0.05));
});

test('pdc schedule ignores the approved interest rate and uses the loan processor\'s recommended rate', function () {
    $loan = [
        'approved_amount_raw' => 24000.0,
        // Simulates a manager-overridden approved rate at final approval --
        // the Annex A schedule must not use this and must fall back to the
        // loan processor's recommended rate instead.
        'interest_rate_raw' => 0.60,
        'interest_rate_recommended_raw' => 0.36,
        'amortization_count' => 24,
        'payment_mode_workbook' => 'QUINCENAL',
        'lumpsum_months' => null,
        'loan_security_rate_standard_raw' => 0.0,
    ];

    $service = app(PdcSchedulePdfService::class);
    $method = (new ReflectionClass($service))->getMethod('buildAmortizationSchedule');
    $method->setAccessible(true);

    $rows = $method->invoke($service, $loan)['rows'];

    $periodicRate = 0.36 / 24;
    $expectedFirstInterest = round(24000.0 * $periodicRate);

    expect($rows[0]['interest'])->toBe($expectedFirstInterest);
});

test('pdc schedule matches the official Annex A reference schedule for a 100,000 loan at 44% over 12 monthly checks', function () {
    $loan = [
        'approved_amount_raw' => 100000.0,
        'interest_rate_recommended_raw' => 0.44,
        'amortization_count' => 12,
        'payment_mode_workbook' => null,
        'lumpsum_months' => null,
        'loan_security_rate_standard_raw' => 0.0,
    ];

    $service = app(PdcSchedulePdfService::class);
    $method = (new ReflectionClass($service))->getMethod('buildAmortizationSchedule');
    $method->setAccessible(true);

    $rows = $method->invoke($service, $loan)['rows'];

    // Reference: Annex A schedule prepared for Ruth R. Miranda's ₱100,000
    // loan — the fixed installment is rounded UP to a whole peso (10,451,
    // not the raw annuity payment's 10,450.16), so every regular check
    // still fully covers its due interest; the final check absorbs the
    // resulting shortfall as a smaller balloon payment.
    $expected = [
        [6784.0, 3667.0], [7033.0, 3418.0], [7291.0, 3160.0], [7558.0, 2893.0],
        [7835.0, 2616.0], [8123.0, 2328.0], [8421.0, 2030.0], [8729.0, 1722.0],
        [9049.0, 1402.0], [9381.0, 1070.0], [9725.0, 726.0], [10071.0, 369.0],
    ];

    foreach ($expected as $i => [$principal, $interest]) {
        expect($rows[$i]['principal'])->toBe($principal);
        expect($rows[$i]['interest'])->toBe($interest);
    }
});
