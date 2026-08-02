<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
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

    if (! Schema::hasTable('wlnmaster')) {
        Schema::create('wlnmaster', function (Blueprint $table): void {
            $table->string('acctno');
            $table->string('lnnumber');
            $table->string('lntype')->nullable();
            $table->string('lnstatus')->nullable();
            $table->decimal('principal', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->date('date_rel')->nullable();
            $table->date('date_mat')->nullable();
            $table->dateTime('lastmove')->nullable();
        });
    }
});

function createLoanStatusStaffActor(array $roles, ?string $acctno = null): AppUser
{
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
    ]);

    if (in_array(Role::SUPERADMIN, $roles, true)) {
        AdminProfile::factory()->superadmin()->create([
            'user_id' => $user->user_id,
        ]);
    }

    $user->roles()->sync(
        Role::query()->whereIn('name', $roles)->pluck('id')->all(),
    );

    if (array_intersect($roles, [Role::SUPERADMIN, Role::LOAN_MANAGER]) !== []) {
        $user->forceFill(['two_factor_secret' => 'fakesecret', 'two_factor_confirmed_at' => now()])->save();
    }

    return $user->fresh(['roles.permissions', 'adminProfile']);
}

function createLoanStatusFeatureMember(string $acctno): AppUser
{
    $member = AppUser::factory()->create([
        'acctno' => $acctno,
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->create(['user_id' => $member->user_id]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        ['fname' => 'Status', 'lname' => 'Member', 'bname' => 'Status Member', 'birthday' => '1990-01-01', 'address' => 'Status St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile']);
}

function createLoanStatusFeatureLoan(array $attributes): void
{
    $defaults = [
        'acctno' => '000001',
        'lnnumber' => 'LN-001',
        'lntype' => 'Personal',
        'lnstatus' => 'ACT',
        'principal' => 10000,
        'balance' => 5000,
        'date_rel' => '2024-01-15',
        'date_mat' => '2025-01-15',
        'lastmove' => '2024-06-01',
    ];

    DB::table('wlnmaster')->insert(array_merge($defaults, $attributes));
}

test('staff show exposes clean loan status with no problem loans', function (): void {
    $processor = createLoanStatusStaffActor([Role::LOAN_PROCESSOR]);
    $member = createLoanStatusFeatureMember('000001');

    createLoanStatusFeatureLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-ACT',
        'lnstatus' => 'ACT',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'acctno' => $member->acctno,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($processor)
        ->get(route('staff.loan-requests.show', $loanRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('loanRequest.applicant_loan_status.has_active', true)
            ->where('loanRequest.applicant_loan_status.has_past_due', false)
            ->where('loanRequest.applicant_loan_status.has_litigation', false)
            ->where('loanRequest.applicant_loan_status.requires_attention', false)
            ->where('loanRequest.applicant_loan_status.problem_loans', [])
        );
});

test('staff show surfaces PDL loans as problem loans', function (): void {
    $processor = createLoanStatusStaffActor([Role::LOAN_PROCESSOR]);
    $member = createLoanStatusFeatureMember('000001');

    createLoanStatusFeatureLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-PDL-1',
        'lntype' => 'Personal',
        'lnstatus' => 'PDL',
        'principal' => 12000,
        'balance' => 8000,
        'date_rel' => '2024-02-15',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'acctno' => $member->acctno,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($processor)
        ->get(route('staff.loan-requests.show', $loanRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('loanRequest.applicant_loan_status.total_past_due', 1)
            ->where('loanRequest.applicant_loan_status.past_due_balance_total', 8000)
            ->where('loanRequest.applicant_loan_status.requires_attention', true)
            ->where('loanRequest.applicant_loan_status.problem_loans.0.lnnumber', 'LN-PDL-1')
            ->where('loanRequest.applicant_loan_status.problem_loans.0.lnstatus', 'PDL')
        );
});

test('staff show surfaces IIL loans as problem loans', function (): void {
    $processor = createLoanStatusStaffActor([Role::LOAN_PROCESSOR]);
    $member = createLoanStatusFeatureMember('000001');

    createLoanStatusFeatureLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-IIL-1',
        'lntype' => 'Emergency loan',
        'lnstatus' => 'IIL',
        'principal' => 20000,
        'balance' => 15000,
        'date_rel' => '2024-05-20',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'acctno' => $member->acctno,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($processor)
        ->get(route('staff.loan-requests.show', $loanRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('loanRequest.applicant_loan_status.total_litigation', 1)
            ->where('loanRequest.applicant_loan_status.litigation_balance_total', 15000)
            ->where('loanRequest.applicant_loan_status.requires_attention', true)
            ->where('loanRequest.applicant_loan_status.problem_loans.0.lnstatus', 'IIL')
        );
});

test('staff show lists mixed PDL and IIL loans with IIL first', function (): void {
    $processor = createLoanStatusStaffActor([Role::LOAN_PROCESSOR]);
    $member = createLoanStatusFeatureMember('000001');

    createLoanStatusFeatureLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-PDL-2',
        'lnstatus' => 'PDL',
        'principal' => 9000,
        'balance' => 6000,
        'date_rel' => '2024-07-01',
    ]);
    createLoanStatusFeatureLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-IIL-2',
        'lnstatus' => 'IIL',
        'principal' => 15000,
        'balance' => 12000,
        'date_rel' => '2024-08-01',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'acctno' => $member->acctno,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($processor)
        ->get(route('staff.loan-requests.show', $loanRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('loanRequest.applicant_loan_status.total_past_due', 1)
            ->where('loanRequest.applicant_loan_status.total_litigation', 1)
            ->where('loanRequest.applicant_loan_status.problem_loans.0.lnnumber', 'LN-IIL-2')
            ->where('loanRequest.applicant_loan_status.problem_loans.1.lnnumber', 'LN-PDL-2')
        );
});

test('log-warning-viewed creates an audit change when attention is required', function (): void {
    $processor = createLoanStatusStaffActor([Role::LOAN_PROCESSOR]);
    $member = createLoanStatusFeatureMember('000001');

    createLoanStatusFeatureLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-PDL-3',
        'lnstatus' => 'PDL',
        'principal' => 8000,
        'balance' => 4000,
        'date_rel' => '2024-03-10',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'acctno' => $member->acctno,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($processor)
        ->postJson(route('staff.loan-requests.log-warning-viewed', $loanRequest))
        ->assertOk()
        ->assertJson(['ok' => true, 'logged' => true]);

    $change = LoanRequestChange::query()->sole();

    expect($change->action)->toBe(LoanRequestChange::ACTION_LOAN_STATUS_WARNING_VIEWED);
    expect($change->changed_by)->toBe($processor->user_id);
    expect($change->metadata_json['has_past_due'])->toBeTrue();
    expect($change->metadata_json['warning_message'])->toBe('Applicant has 1 past due loan(s)');
});

test('log-warning-viewed no-ops without attention and 404s on drafts', function (): void {
    $processor = createLoanStatusStaffActor([Role::LOAN_PROCESSOR]);
    $member = createLoanStatusFeatureMember('000001');

    createLoanStatusFeatureLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-ACT-2',
        'lnstatus' => 'ACT',
    ]);

    $cleanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'acctno' => $member->acctno,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($processor)
        ->postJson(route('staff.loan-requests.log-warning-viewed', $cleanRequest))
        ->assertOk()
        ->assertJson(['ok' => true, 'logged' => false]);

    expect(LoanRequestChange::query()->count())->toBe(0);

    $draft = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this
        ->actingAs($processor)
        ->postJson(route('staff.loan-requests.log-warning-viewed', $draft))
        ->assertNotFound();
});
