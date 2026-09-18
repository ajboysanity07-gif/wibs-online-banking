<?php

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table): void {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
            $table->date('birthday')->nullable();
            $table->string('address')->nullable();
            $table->string('civilstat')->nullable();
            $table->string('occupation')->nullable();
            $table->string('telephone')->nullable();
        });
    }
});

/**
 * @return array<string, mixed>
 */
function contactNumberBuildDocumentData(LoanRequest $loanRequest): array
{
    $service = app(ApprovedLoanDocumentService::class);
    $buildDocumentData = Closure::bind(
        fn (LoanRequest $record): array => $this->buildDocumentData($record),
        $service,
        ApprovedLoanDocumentService::class,
    );

    return $buildDocumentData($loanRequest);
}

test('applicant mobile on approved documents uses wmaster telephone over the wizard-entered cell no', function (): void {
    $user = User::factory()->create();

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $user->acctno],
        [
            'fname' => 'Sample',
            'lname' => 'Member',
            'birthday' => '1990-01-01',
            'telephone' => '09170000001',
        ],
    );

    MemberApplicationProfile::factory()->create(['user_id' => $user->user_id]);

    $loanRequest = LoanRequest::factory()
        ->forUser($user)
        ->create([
            'status' => LoanRequestStatus::Approved,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
            'approved_amount' => 25000,
            'approved_term' => 12,
            'approved_interest_rate' => 0.36,
            'recommended_payment_frequency' => '15th & 30th',
        ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            // The applicant's own wizard-entered cell no. -- deliberately
            // different from wmaster.telephone so the test fails if the
            // document falls back to it instead of the wmaster record.
            'cell_no' => '09189999999',
        ]);

    $documentData = contactNumberBuildDocumentData($loanRequest->fresh());

    expect($documentData['applicant']['mobile'])->toBe('09170000001');
});

test('applicant mobile falls back to the wizard-entered cell no when no wmaster record exists', function (): void {
    $user = User::factory()->create();

    MemberApplicationProfile::factory()->create(['user_id' => $user->user_id]);

    $loanRequest = LoanRequest::factory()
        ->forUser($user)
        ->create([
            'status' => LoanRequestStatus::Approved,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
            'approved_amount' => 25000,
            'approved_term' => 12,
            'approved_interest_rate' => 0.36,
            'recommended_payment_frequency' => '15th & 30th',
        ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['cell_no' => '09189999999']);

    $documentData = contactNumberBuildDocumentData($loanRequest->fresh());

    expect($documentData['applicant']['mobile'])->toBe('09189999999');
});
