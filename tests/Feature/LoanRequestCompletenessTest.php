<?php

use App\LoanRequestDocumentKey;
use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDocument;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use App\Services\LoanRequests\LoanRequestCompletenessService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();

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
        });
    }

    if (! Schema::hasTable('wlntype')) {
        Schema::create('wlntype', function (Blueprint $table): void {
            $table->string('typecode')->primary();
            $table->string('lntype');
        });
    }

    if (! Schema::hasTable('wlnled')) {
        Schema::create('wlnled', function (Blueprint $table): void {
            $table->string('acctno');
            $table->string('lnnumber')->nullable();
        });
    }
});

test('completeness has missing sections when no applicant person exists', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Draft,
    ]);

    $service = app(LoanRequestCompletenessService::class);
    $result = $service->computeFor($loanRequest);

    expect($result['missing'])->toContain('Personal info')
        ->and($result['missing'])->toContain('Employment')
        ->and($result['percentage'])->toBeLessThan(100);
});

test('completeness marks personal info complete when all required fields are present', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Draft,
    ]);

    LoanRequestPerson::factory()->create([
        'loan_request_id' => $loanRequest->id,
        'role' => LoanRequestPersonRole::Applicant,
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'cell_no' => '09123456789',
        'civil_status' => 'Single',
        'birthdate' => '1990-04-10',
        'address1' => '123 Main St',
        'address2' => null,
        'address3' => null,
        'address' => null,
        'employment_type' => null,
        'employer_business_name' => null,
        'gross_monthly_income' => null,
    ]);

    $service = app(LoanRequestCompletenessService::class);
    $result = $service->computeFor($loanRequest);

    expect($result['completed'])->toContain('Personal info')
        ->and($result['missing'])->not->toContain('Personal info');
});

test('completeness marks employment complete when all required fields are present', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Draft,
    ]);

    LoanRequestPerson::factory()->create([
        'loan_request_id' => $loanRequest->id,
        'role' => LoanRequestPersonRole::Applicant,
        'employment_type' => 'Private',
        'employer_business_name' => 'Acme Corp',
        'gross_monthly_income' => 30000,
    ]);

    $service = app(LoanRequestCompletenessService::class);
    $result = $service->computeFor($loanRequest);

    expect($result['completed'])->toContain('Employment')
        ->and($result['missing'])->not->toContain('Employment');
});

test('completeness marks loan details complete when required fields are set', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Draft,
        'requested_amount' => 50000,
        'requested_term' => 24,
        'loan_purpose' => 'Home improvement',
    ]);

    $service = app(LoanRequestCompletenessService::class);
    $result = $service->computeFor($loanRequest);

    expect($result['completed'])->toContain('Loan details')
        ->and($result['missing'])->not->toContain('Loan details');
});

test('completeness identifies missing documents when no document records exist', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Draft,
    ]);

    $service = app(LoanRequestCompletenessService::class);
    $result = $service->computeFor($loanRequest);

    expect($result['missing_documents'])
        ->toContain(LoanRequestDocumentKey::ApplicationForm->value)
        ->toContain(LoanRequestDocumentKey::Grepalife->value);
});

test('completeness marks a document complete when it is not applicable', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Draft,
    ]);

    LoanRequestDocument::factory()->create([
        'loan_request_id' => $loanRequest->id,
        'document_key' => LoanRequestDocumentKey::ApplicationForm->value,
        'is_applicable' => false,
        'readiness_status' => LoanRequestDocumentReadinessStatus::NotStarted,
    ]);

    $service = app(LoanRequestCompletenessService::class);
    $result = $service->computeFor($loanRequest);

    expect($result['missing_documents'])
        ->not->toContain(LoanRequestDocumentKey::ApplicationForm->value);
});

test('completeness marks a document complete when generated and current', function (): void {
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Draft,
    ]);

    LoanRequestDocument::factory()->create([
        'loan_request_id' => $loanRequest->id,
        'document_key' => LoanRequestDocumentKey::ApplicationForm->value,
        'is_applicable' => true,
        'readiness_status' => LoanRequestDocumentReadinessStatus::GeneratedCurrent,
    ]);

    $service = app(LoanRequestCompletenessService::class);
    $result = $service->computeFor($loanRequest);

    expect($result['missing_documents'])
        ->not->toContain(LoanRequestDocumentKey::ApplicationForm->value);
});

test('profile autofill populates correct fields on draft creation', function (): void {
    $member = AppUser::factory()->create([
        'acctno' => '001002',
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $member->user_id,
        'employment_type' => 'Private',
        'employer_business_name' => 'Autofill Corp',
        'gross_monthly_income' => 45000,
    ]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => '001002'],
        ['fname' => 'Juan', 'lname' => 'Cruz', 'birthday' => '1990-06-01', 'address' => '123 Main St'],
    );

    $this->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('applicant.first_name', 'Juan')
            ->where('applicant.last_name', 'Cruz')
            ->where('applicant.employment_type', 'Private')
            ->where('applicant.employer_business_name', 'Autofill Corp')
        );
});

test('completeness is included in the serialized loan request on the client show page', function (): void {
    $member = AppUser::factory()->create([
        'acctno' => '001001',
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->create(['user_id' => $member->user_id]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => '001001'],
        ['fname' => 'Test', 'lname' => 'Member', 'birthday' => '1990-01-01', 'address' => 'Test St'],
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'acctno' => $member->acctno,
        'submitted_at' => now(),
    ]);

    $this->actingAs($member)
        ->get(route('client.loan-requests.show', $loanRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('loanRequest.completeness')
            ->has('loanRequest.completeness.percentage')
            ->has('loanRequest.completeness.completed')
            ->has('loanRequest.completeness.missing')
            ->has('loanRequest.completeness.missing_documents')
        );
});
