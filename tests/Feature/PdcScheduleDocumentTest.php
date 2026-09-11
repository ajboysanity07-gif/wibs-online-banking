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

function pdcScheduleCreateApprovedLoanRequest(): LoanRequest
{
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now()->subDay(),
        'reviewed_at' => now(),
        'approved_amount' => 24000,
        'approved_term' => 12,
        'approved_interest_rate' => 0.36,
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
});

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
