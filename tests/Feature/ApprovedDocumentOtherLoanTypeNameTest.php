<?php

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Services\LoanRequests\ApprovedLoanDocumentService;

const OTHER_LOAN_TYPECODE = '01';

/**
 * @return array<string, mixed>
 */
function otherLoanBuildDocumentData(LoanRequest $loanRequest): array
{
    $service = app(ApprovedLoanDocumentService::class);
    $buildDocumentData = Closure::bind(
        fn (LoanRequest $record): array => $this->buildDocumentData($record),
        $service,
        ApprovedLoanDocumentService::class,
    );

    return $buildDocumentData($loanRequest);
}

function otherLoanRequest(array $overrides = []): LoanRequest
{
    $user = User::factory()->create();

    MemberApplicationProfile::factory()->create(['user_id' => $user->user_id]);

    $loanRequest = LoanRequest::factory()
        ->forUser($user)
        ->create(array_merge([
            'status' => LoanRequestStatus::Approved,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
            'approved_amount' => 25000,
            'approved_term' => 12,
            'approved_interest_rate' => 0.36,
            'recommended_payment_frequency' => '15th & 30th',
            'typecode' => OTHER_LOAN_TYPECODE,
            'loan_type_label_snapshot' => 'OTHER LOAN',
        ], $overrides));

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create();

    return $loanRequest->fresh();
}

test('Other Loan documents show the member-named loan name, not just "Other Loan"', function (): void {
    $loanRequest = otherLoanRequest([
        'other_loan_type_name' => 'Year-End Bonus 2026',
    ]);

    $documentData = otherLoanBuildDocumentData($loanRequest);

    expect($documentData['loan']['type'])->toBe('OTHER LOAN - Year-End Bonus 2026');
});

test('Other Loan documents fall back to the bare label when no loan name was given', function (): void {
    $loanRequest = otherLoanRequest([
        'other_loan_type_name' => null,
    ]);

    $documentData = otherLoanBuildDocumentData($loanRequest);

    expect($documentData['loan']['type'])->toBe('OTHER LOAN');
});

test('non-Other-Loan requests are unaffected by an other_loan_type_name value', function (): void {
    $loanRequest = otherLoanRequest([
        'typecode' => '02',
        'loan_type_label_snapshot' => 'SALARY LOAN',
        'other_loan_type_name' => 'Should Not Appear',
    ]);

    $documentData = otherLoanBuildDocumentData($loanRequest);

    expect($documentData['loan']['type'])->toBe('SALARY LOAN');
});
