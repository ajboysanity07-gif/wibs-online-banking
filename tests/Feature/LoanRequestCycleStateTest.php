<?php

use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Services\LoanRequests\LoanRequestCycleStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();

    if (! Schema::hasTable('wlnmaster')) {
        Schema::create('wlnmaster', function ($table) {
            $table->string('lnnumber')->primary();
            $table->string('acctno');
            $table->string('lntype')->nullable();
            $table->string('lnstatus')->nullable();
            $table->decimal('principal', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->date('date_rel')->nullable();
            $table->date('date_mat')->nullable();
            $table->timestamp('lastmove')->nullable();
            $table->decimal('initial', 12, 2)->default(0);
        });
    } else {
        DB::table('wlnmaster')->truncate();
    }
});

/**
 * A brand-new member with no wlnmaster rows is auto-computed as New/1
 * (first enrollment cycle) for the applicant, and only the applicant is
 * returned/locked -- spouse and dependent cycles are manually entered by
 * the processor and never auto-computed.
 */
test('no wlnmaster rows resolves to New/1 locked for the applicant only', function (): void {
    $member = createCycleStateActor([Role::MEMBER], '970001');
    MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    /** @var LoanRequestCycleStateService $cycleStateService */
    $cycleStateService = app(LoanRequestCycleStateService::class);
    $state = $cycleStateService->resolveState($loanRequest);

    expect($state)->toHaveKey('applicant')
        ->and($state)->not->toHaveKey('spouse')
        ->and($state)->not->toHaveKey('child_1')
        ->and($state['applicant']['locked'])->toBeTrue()
        ->and($state['applicant']['cycle_status'])->toBe('New')
        ->and($state['applicant']['cycle_number'])->toBe(1);
});

/**
 * Member with a missing acctno defaults to New/1.
 */
test('member with no acctno resolves to default New/1', function (): void {
    $member = createCycleStateActor([Role::MEMBER], null);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    /** @var LoanRequestCycleStateService $cycleStateService */
    $cycleStateService = app(LoanRequestCycleStateService::class);
    $state = $cycleStateService->resolveState($loanRequest);

    expect($state['applicant']['locked'])->toBeTrue()
        ->and($state['applicant']['cycle_status'])->toBe('New')
        ->and($state['applicant']['cycle_number'])->toBe(1);
});

/**
 * One insured loan in wlnmaster → cycle 2 (New/2).
 */
test('one wlnmaster row resolves to New/2', function (): void {
    $member = createCycleStateActor([Role::MEMBER], '970002');
    MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    DB::table('wlnmaster')->insert([
        'lnnumber' => 'LN-970002-001',
        'acctno' => '970002',
        'principal' => 10000,
        'balance' => 5000,
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    $cycleStateService = app(LoanRequestCycleStateService::class);
    $state = $cycleStateService->resolveState($loanRequest);

    expect($state['applicant']['locked'])->toBeTrue()
        ->and($state['applicant']['cycle_status'])->toBe('New')
        ->and($state['applicant']['cycle_number'])->toBe(2);
});

/**
 * Two insured loans in wlnmaster → cycle 3 (Old/3).
 */
test('two wlnmaster rows resolves to Old/3', function (): void {
    $member = createCycleStateActor([Role::MEMBER], '970003');
    MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    DB::table('wlnmaster')->insert([
        'lnnumber' => 'LN-970003-001',
        'acctno' => '970003',
        'principal' => 10000,
        'balance' => 5000,
    ]);
    DB::table('wlnmaster')->insert([
        'lnnumber' => 'LN-970003-002',
        'acctno' => '970003',
        'principal' => 15000,
        'balance' => 10000,
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    $cycleStateService = app(LoanRequestCycleStateService::class);
    $state = $cycleStateService->resolveState($loanRequest);

    expect($state['applicant']['locked'])->toBeTrue()
        ->and($state['applicant']['cycle_status'])->toBe('Old')
        ->and($state['applicant']['cycle_number'])->toBe(3);
});

/**
 * Five insured loans → cycle 6 (Old/6).
 */
test('five wlnmaster rows resolves to Old/6', function (): void {
    $member = createCycleStateActor([Role::MEMBER], '970004');
    MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    for ($i = 1; $i <= 5; $i++) {
        DB::table('wlnmaster')->insert([
            'lnnumber' => "LN-970004-{$i}",
            'acctno' => '970004',
            'principal' => 10000 * $i,
            'balance' => 5000 * $i,
        ]);
    }

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    $cycleStateService = app(LoanRequestCycleStateService::class);
    $state = $cycleStateService->resolveState($loanRequest);

    expect($state['applicant']['locked'])->toBeTrue()
        ->and($state['applicant']['cycle_status'])->toBe('Old')
        ->and($state['applicant']['cycle_number'])->toBe(6);
});

/**
 * Due date (term=1) loan requests are subtracted from the total since
 * they carry no insurance.
 */
test('due date term-1 loans are excluded from insured count', function (): void {
    $member = createCycleStateActor([Role::MEMBER], '970005');
    MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    // 2 wlnmaster rows total, but 1 Due date/term-1 loan request → 1 insured
    DB::table('wlnmaster')->insert([
        'lnnumber' => 'LN-970005-001',
        'acctno' => '970005',
        'principal' => 10000,
        'balance' => 5000,
    ]);
    DB::table('wlnmaster')->insert([
        'lnnumber' => 'LN-970005-002',
        'acctno' => '970005',
        'principal' => 5000,
        'balance' => 0,
    ]);

    // A prior loan request that was Due date / term 1 (no insurance)
    LoanRequest::factory()->forUser($member)->create([
        'requested_payment_frequency' => 'Due date',
        'requested_term' => 1,
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    $cycleStateService = app(LoanRequestCycleStateService::class);
    $state = $cycleStateService->resolveState($loanRequest);

    // 2 wlnmaster - 1 lumpsum = 1 insured loan → cycle number 2 → New/2
    expect($state['applicant']['cycle_status'])->toBe('New')
        ->and($state['applicant']['cycle_number'])->toBe(2);
});

/**
 * resolveState() returns exactly one slot -- the applicant -- regardless of
 * how many dependents a request has. Spouse/dependent cycle data lives
 * entirely in the manually-entered EAV fields (LoanRequestDataService),
 * not in this service.
 */
test('resolveState returns only the applicant slot', function (): void {
    $member = createCycleStateActor([Role::MEMBER], '970006');
    MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    for ($i = 1; $i <= 3; $i++) {
        DB::table('wlnmaster')->insert([
            'lnnumber' => "LN-970006-{$i}",
            'acctno' => '970006',
            'principal' => 10000,
            'balance' => 5000,
        ]);
    }

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'submitted_at' => now(),
    ]);

    $cycleStateService = app(LoanRequestCycleStateService::class);
    $state = $cycleStateService->resolveState($loanRequest);

    // 3 wlnmaster rows → Old/4
    expect($state)->toHaveKeys(['applicant'])
        ->and(array_keys($state))->toBe(['applicant'])
        ->and($state['applicant']['locked'])->toBeTrue()
        ->and($state['applicant']['cycle_status'])->toBe('Old')
        ->and($state['applicant']['cycle_number'])->toBe(4);
});

/**
 * A processor can save spouse/dependent cycle data independently of the
 * applicant's auto-computed value, and re-saving with only an applicant
 * change no longer clobbers the previously-saved dependent values (the
 * regression the old uniform-lock behavior caused).
 */
test('processor can save dependent cycle data without it being clobbered by later saves', function (): void {
    $processor = createCycleStateActor([Role::LOAN_PROCESSOR]);
    $member = createCycleStateActor([Role::MEMBER], '970012');
    MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), [
            'reason' => '',
            'loan_request' => [],
            'processing' => [
                'dependent_spouse_cycle_status' => 'Old',
                'dependent_spouse_cycle_number' => 3,
                'dependent_child_1_cycle_status' => 'New',
            ],
        ])
        ->assertOk();

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), [
            'reason' => 'Correcting applicant PEP status',
            'loan_request' => [],
            'processing' => [
                'applicant_pep_status' => false,
            ],
        ])
        ->assertOk();

    $loanRequest->refresh();
    $flatValues = app(\App\Services\LoanRequests\LoanRequestDataService::class)
        ->loadFlatValues($loanRequest);

    expect($flatValues['dependent_spouse_cycle_status'])->toBe('Old')
        ->and((int) $flatValues['dependent_spouse_cycle_number'])->toBe(3)
        ->and($flatValues['dependent_child_1_cycle_status'])->toBe('New');
});

/**
 * Dependent/spouse cycle_number is optional metadata: a null number
 * alongside status 'Old' is accepted, unlike the applicant's number which
 * is still required once its status is set.
 */
test('dependent cycle_number is optional even when status is Old', function (): void {
    $processor = createCycleStateActor([Role::LOAN_PROCESSOR]);
    $member = createCycleStateActor([Role::MEMBER], '970013');
    MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), [
            'reason' => '',
            'loan_request' => [],
            'processing' => [
                'dependent_spouse_cycle_status' => 'Old',
                'dependent_spouse_cycle_number' => null,
            ],
        ])
        ->assertOk();
});

/**
 * Cycle number is always required when status is present -- the Generali
 * form labels "New (1st-2nd)" and "Old (3rd cycle & up ___)", so both
 * require a number.
 */
test('submitting Old status with a missing cycle number is rejected', function (): void {
    $processor = createCycleStateActor([Role::LOAN_PROCESSOR]);
    $member = createCycleStateActor([Role::MEMBER], '970010');
    MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), [
            'reason' => '',
            'loan_request' => [],
            'processing' => [
                'applicant_cycle_status' => 'Old',
                'applicant_cycle_number' => null,
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['processing.applicant_cycle_number']);
});

test('submitting New status with a missing cycle number is also rejected', function (): void {
    $processor = createCycleStateActor([Role::LOAN_PROCESSOR]);
    $member = createCycleStateActor([Role::MEMBER], '970011');
    MemberApplicationProfile::factory()->create(['user_id' => $member->user_id]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), [
            'reason' => '',
            'loan_request' => [],
            'processing' => [
                'applicant_cycle_status' => 'New',
                'applicant_cycle_number' => null,
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['processing.applicant_cycle_number']);
});

/**
 * @param  list<string>  $roles
 */
function createCycleStateActor(array $roles, ?string $acctno = null): AppUser
{
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
        'phoneno' => null,
        'email_verified_at' => now(),
    ]);

    $user->roles()->sync(
        Role::query()
            ->whereIn('name', $roles)
            ->pluck('id')
            ->all(),
    );

    return $user->fresh(['roles.permissions', 'staffAccessControl']);
}
