<?php

use App\LoanRequestPersonRole;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function backfillZipEnsureWmasterTable(): void
{
    if (Schema::hasTable('wmaster')) {
        if (! Schema::hasColumn('wmaster', 'zone_number')) {
            Schema::table('wmaster', function (Blueprint $table): void {
                $table->string('zone_number')->nullable();
            });
        }

        return;
    }

    Schema::create('wmaster', function (Blueprint $table): void {
        $table->string('acctno')->primary();
        $table->string('zone_number')->nullable();
    });
}

function backfillZipCreateMemberWithRequest(?string $zoneNumber = '8307'): LoanRequest
{
    $user = AppUser::factory()->create();

    $loanRequest = LoanRequest::factory()->forUser($user)->create();

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $user->acctno],
        ['zone_number' => $zoneNumber],
    );

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['address_zip' => null]);

    return $loanRequest;
}

beforeEach(function (): void {
    backfillZipEnsureWmasterTable();
});

test('dry run reports candidate rows without writing address_zip', function (): void {
    $loanRequest = backfillZipCreateMemberWithRequest();

    $this->artisan('loan-requests:backfill-zip-codes')
        ->expectsOutputToContain('Dry run')
        ->assertExitCode(0);

    $applicant = LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('role', LoanRequestPersonRole::Applicant)
        ->sole();

    expect($applicant->address_zip)->toBeNull();
});

test('fix mode writes address_zip from the member wmaster zone_number', function (): void {
    $loanRequest = backfillZipCreateMemberWithRequest('8311');

    $this->artisan('loan-requests:backfill-zip-codes --fix')
        ->assertExitCode(0);

    $applicant = LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('role', LoanRequestPersonRole::Applicant)
        ->sole();

    expect($applicant->address_zip)->toBe('8311');
});

test('fix mode trims whitespace from the zone_number value', function (): void {
    $user = AppUser::factory()->create();

    $loanRequest = LoanRequest::factory()->forUser($user)->create();

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $user->acctno],
        ['zone_number' => ' 8307 '],
    );

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['address_zip' => null]);

    $this->artisan('loan-requests:backfill-zip-codes --fix')
        ->assertExitCode(0);

    $applicant = LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('role', LoanRequestPersonRole::Applicant)
        ->sole();

    expect($applicant->address_zip)->toBe('8307');
});

test('fix mode does not overwrite an applicant that already has address_zip set', function (): void {
    $user = AppUser::factory()->create();

    $loanRequest = LoanRequest::factory()->forUser($user)->create();

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $user->acctno],
        ['zone_number' => '8311'],
    );

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['address_zip' => '8000']);

    $this->artisan('loan-requests:backfill-zip-codes --fix')
        ->assertExitCode(0);

    $applicant = LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('role', LoanRequestPersonRole::Applicant)
        ->sole();

    expect($applicant->address_zip)->toBe('8000');
});

test('only applicant rows are considered for the backfill', function (): void {
    $user = AppUser::factory()->create();

    $loanRequest = LoanRequest::factory()->forUser($user)->create();

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $user->acctno],
        ['zone_number' => '8311'],
    );

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerOne)
        ->create(['address_zip' => null]);

    $this->artisan('loan-requests:backfill-zip-codes --fix')
        ->assertExitCode(0);

    $coMaker = LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('role', LoanRequestPersonRole::CoMakerOne)
        ->sole();

    expect($coMaker->address_zip)->toBeNull();
});

test('rows without a matching wmaster record are reported and skipped', function (): void {
    $user = AppUser::factory()->create();

    $loanRequest = LoanRequest::factory()->forUser($user)->create();

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['address_zip' => null]);

    $this->artisan('loan-requests:backfill-zip-codes --fix')
        ->expectsOutputToContain('No wmaster record')
        ->assertExitCode(0);

    $applicant = LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('role', LoanRequestPersonRole::Applicant)
        ->sole();

    expect($applicant->address_zip)->toBeNull();
});

test('rows whose wmaster has no zone_number are reported and skipped', function (): void {
    $loanRequest = backfillZipCreateMemberWithRequest(null);

    $this->artisan('loan-requests:backfill-zip-codes --fix')
        ->expectsOutputToContain('No zone_number')
        ->assertExitCode(0);

    $applicant = LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('role', LoanRequestPersonRole::Applicant)
        ->sole();

    expect($applicant->address_zip)->toBeNull();
});

test('fix mode resolves the wmaster through the loan_request acctno when the user has no wmaster relation', function (): void {
    $user = AppUser::factory()->create(['acctno' => null]);

    $loanRequest = LoanRequest::factory()->forUser($user)->create(['acctno' => '554321']);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => '554321'],
        ['zone_number' => '8306'],
    );

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['address_zip' => null]);

    $this->artisan('loan-requests:backfill-zip-codes --fix')
        ->assertExitCode(0);

    $applicant = LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('role', LoanRequestPersonRole::Applicant)
        ->sole();

    expect($applicant->address_zip)->toBe('8306');
});

test('limit option caps the number of applicant rows scanned', function (): void {
    $first = backfillZipCreateMemberWithRequest();
    $second = backfillZipCreateMemberWithRequest();

    $this->artisan('loan-requests:backfill-zip-codes --fix --limit=1')
        ->assertExitCode(0);

    $backfilledCount = LoanRequestPerson::query()
        ->whereIn('loan_request_id', [$first->id, $second->id])
        ->where('role', LoanRequestPersonRole::Applicant)
        ->whereNotNull('address_zip')
        ->count();

    expect($backfilledCount)->toBe(1);
});
