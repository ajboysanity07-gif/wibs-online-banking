<?php

use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\Role;
use App\Models\StaffAccessControl;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Role::ensureWorkflowDefaults();

    if (! Schema::hasTable('wlnmaster')) {
        Schema::create('wlnmaster', function (Blueprint $table) {
            $table->string('acctno');
            $table->string('lnnumber')->unique();
            $table->string('typecode')->nullable();
            $table->string('lntype')->nullable();
            $table->string('lnstatus')->nullable();
            $table->decimal('principal', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->dateTime('lastmove')->nullable();
            $table->dateTime('date_in')->nullable();
            $table->dateTime('date_start')->nullable();
            $table->dateTime('date_mat')->nullable();
            $table->dateTime('date_rel')->nullable();
            $table->decimal('int_rate', 12, 4)->default(0);
            $table->integer('term_mons')->nullable();
            $table->decimal('amortization', 12, 2)->default(0);
            $table->string('installment')->nullable();
            $table->string('purpose')->nullable();
            $table->string('remarks')->nullable();
        });
    }

    if (! Schema::hasTable('wlnled')) {
        Schema::create('wlnled', function (Blueprint $table) {
            $table->string('lnstatus')->nullable();
            $table->string('acctno');
            $table->string('lnnumber');
            $table->string('bname')->nullable();
            $table->string('typecode')->nullable();
            $table->string('lntype')->nullable();
            $table->dateTime('date_in')->nullable();
            $table->string('mreference')->nullable();
            $table->string('cs_ck')->nullable();
            $table->string('lncode')->nullable();
            $table->decimal('principal', 12, 2)->default(0);
            $table->decimal('payments', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->decimal('unsettled', 12, 2)->default(0);
            $table->string('transno')->nullable();
            $table->string('controlno')->nullable();
            $table->string('initial')->nullable();
        });
    }

    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table) {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
        });
    }
});

test('members can create and only view or resubmit their own eligible loan requests', function () {
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100001',
    );
    $otherMember = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100002',
    );

    $ownNeedsRevision = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::NeedsRevision,
    ]);
    $ownUnderReview = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);
    $otherLoanRequest = LoanRequest::factory()->forUser($otherMember)->create([
        'status' => LoanRequestStatus::NeedsRevision,
    ]);

    expect(Gate::forUser($member)->allows('create', LoanRequest::class))->toBeTrue();
    expect(Gate::forUser($member)->allows('view', $ownNeedsRevision))->toBeTrue();
    expect(Gate::forUser($member)->allows('view', $otherLoanRequest))->toBeFalse();
    expect(Gate::forUser($member)->allows('resubmit', $ownNeedsRevision))->toBeTrue();
    expect(Gate::forUser($member)->allows('update', $ownNeedsRevision))->toBeTrue();
    expect(Gate::forUser($member)->allows('resubmit', $ownUnderReview))->toBeFalse();
});

test('loan processors are limited to review-stage workflow actions', function () {
    $loanOfficer = createWorkflowAuthorizationActor([Role::LOAN_PROCESSOR]);
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100003',
    );

    $pendingReview = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);
    $underReview = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);
    $recommended = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);

    expect(Gate::forUser($loanOfficer)->allows('startReview', $pendingReview))->toBeTrue();
    expect(Gate::forUser($loanOfficer)->allows('requestRevision', $pendingReview))->toBeTrue();
    expect(Gate::forUser($loanOfficer)->allows('requestRevision', $underReview))->toBeTrue();
    expect(Gate::forUser($loanOfficer)->allows('reject', $underReview))->toBeTrue();
    expect(Gate::forUser($loanOfficer)->allows('recommendApproval', $underReview))->toBeTrue();
    expect(Gate::forUser($loanOfficer)->allows('approve', $recommended))->toBeFalse();
    expect(Gate::forUser($loanOfficer)->allows('decline', $recommended))->toBeFalse();
});

test('loan managers are limited to approval and decline stages', function () {
    $loanManager = createWorkflowAuthorizationActor([Role::LOAN_MANAGER]);
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100004',
    );

    $pendingReview = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
    ]);
    $recommended = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
    ]);

    expect(Gate::forUser($loanManager)->allows('startReview', $pendingReview))->toBeFalse();
    expect(Gate::forUser($loanManager)->allows('requestRevision', $pendingReview))->toBeFalse();
    expect(Gate::forUser($loanManager)->allows('reject', $pendingReview))->toBeFalse();
    expect(Gate::forUser($loanManager)->allows('recommendApproval', $recommended))->toBeFalse();
    expect(Gate::forUser($loanManager)->allows('approve', $recommended))->toBeTrue();
    expect(Gate::forUser($loanManager)->allows('decline', $recommended))->toBeTrue();
});

test('users with multiple workflow roles receive the combined permissions', function () {
    $actor = createWorkflowAuthorizationActor([
        Role::LOAN_PROCESSOR,
        Role::LOAN_MANAGER,
    ]);
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100005',
    );

    $pendingReview = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
    ]);
    $recommended = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
    ]);

    expect(Gate::forUser($actor)->allows('startReview', $pendingReview))->toBeTrue();
    expect(Gate::forUser($actor)->allows('approve', $recommended))->toBeTrue();
    expect(Gate::forUser($actor)->allows('decline', $recommended))->toBeTrue();
});

test('loan managers can approve recommended requests through the existing admin endpoint', function () {
    Queue::fake();

    $loanManager = createWorkflowAuthorizationActor(
        [Role::LOAN_MANAGER],
        withAdminProfile: true,
        acctno: null,
    );
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100006',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
    ]);

    $response = $this
        ->actingAs($loanManager)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/approve", [
            'approved_amount' => 15000,
            'approved_term' => 12,
            'decision_notes' => 'Manager approval.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Approved->value);

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Approved);
    expect($loanRequest->reviewed_by)->toBe($loanManager->user_id);
});

test('loan managers can decline recommended requests through the existing admin endpoint', function () {
    Queue::fake();

    $loanManager = createWorkflowAuthorizationActor(
        [Role::LOAN_MANAGER],
        withAdminProfile: true,
        acctno: null,
    );
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100007',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
    ]);

    $response = $this
        ->actingAs($loanManager)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/decline", [
            'decision_notes' => 'Manager decline.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Declined->value);

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Declined);
    expect($loanRequest->reviewed_by)->toBe($loanManager->user_id);
});

test('loan processors cannot approve recommended requests through the existing admin endpoint', function () {
    Queue::fake();

    $loanOfficer = createWorkflowAuthorizationActor(
        [Role::LOAN_PROCESSOR],
        withAdminProfile: true,
        acctno: null,
    );
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100008',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($loanOfficer)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/approve", [
            'approved_amount' => 15000,
            'approved_term' => 12,
        ])
        ->assertForbidden();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::RecommendedForApproval);
});

test('loan managers cannot approve under review requests through the existing admin endpoint', function () {
    Queue::fake();

    $loanManager = createWorkflowAuthorizationActor(
        [Role::LOAN_MANAGER],
        withAdminProfile: true,
        acctno: null,
    );
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100009',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($loanManager)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/approve", [
            'approved_amount' => 15000,
            'approved_term' => 12,
        ])
        ->assertForbidden();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
});

test('legacy admins cannot bypass recommendation through the existing admin endpoints', function () {
    Queue::fake();

    $admin = createWorkflowAuthorizationActor(
        [Role::ADMIN],
        withAdminProfile: true,
        acctno: null,
    );
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100009A',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/approve", [
            'approved_amount' => 15000,
            'approved_term' => 12,
        ])
        ->assertForbidden();

    $this
        ->actingAs($admin)
        ->patchJson("/spa/admin/requests/{$loanRequest->id}/decline", [
            'decision_notes' => 'Legacy admin decline.',
        ])
        ->assertForbidden();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
    Queue::assertNothingPushed();
});

test('superadmins can monitor requests but cannot act on workflow decisions without explicit workflow roles', function () {
    $superadmin = createWorkflowAuthorizationActor(
        [Role::SUPERADMIN],
        withAdminProfile: true,
        acctno: null,
    );
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100009B',
    );

    $pendingReview = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'submitted_at' => now(),
    ]);
    $recommended = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
    ]);

    expect(Gate::forUser($superadmin)->allows('view', $pendingReview))->toBeTrue();
    expect(Gate::forUser($superadmin)->allows('startReview', $pendingReview))->toBeFalse();
    expect(Gate::forUser($superadmin)->allows('approve', $recommended))->toBeFalse();
    expect(Gate::forUser($superadmin)->allows('decline', $recommended))->toBeFalse();
});

test('suspended staff cannot monitor or act on workflow requests', function () {
    $loanOfficer = createWorkflowAuthorizationActor([Role::LOAN_PROCESSOR]);
    StaffAccessControl::factory()->suspended()->create([
        'user_id' => $loanOfficer->user_id,
    ]);
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100009C',
    );

    $pendingReview = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'submitted_at' => now(),
    ]);

    expect(Gate::forUser($loanOfficer->fresh())->allows('view', $pendingReview))->toBeFalse();

    $this
        ->actingAs($loanOfficer->fresh())
        ->patchJson(route('spa.workflow.loan-requests.start-review', $pendingReview), [
            'remarks' => 'Suspended staff cannot start review.',
        ])
        ->assertForbidden();
});

test('loan processors can start review through the workflow route and create an audit row', function () {
    $loanOfficer = createWorkflowAuthorizationActor([Role::LOAN_PROCESSOR]);
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100010',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'submitted_at' => now(),
    ]);

    $response = $this
        ->actingAs($loanOfficer)
        ->patchJson(route('spa.workflow.loan-requests.start-review', $loanRequest), [
            'remarks' => 'Initial review started.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::UnderReview->value)
        ->assertJsonPath('data.loanRequest.assigned_officer_id', $loanOfficer->user_id);

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
    expect($loanRequest->assigned_officer_id)->toBe($loanOfficer->user_id);

    $change = LoanRequestChange::query()->sole();

    expect($change->action)->toBe(LoanRequestChange::ACTION_START_REVIEW);
    expect($change->changed_by)->toBe($loanOfficer->user_id);
    expect($change->from_status)->toBe(LoanRequestStatus::PendingReview->value);
    expect($change->to_status)->toBe(LoanRequestStatus::UnderReview->value);
    expect($change->reason)->toBe('Initial review started.');
});

test('loan processors cannot start review once a request is already under review', function () {
    $loanOfficer = createWorkflowAuthorizationActor([Role::LOAN_PROCESSOR]);
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100010A',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
        'assigned_officer_id' => $loanOfficer->user_id,
        'workflow_version' => LoanRequestWorkflowVersion::LegacyV1,
    ]);

    $this
        ->actingAs($loanOfficer)
        ->patchJson(route('spa.workflow.loan-requests.start-review', $loanRequest), [
            'remarks' => 'Attempted duplicate review start.',
        ])
        ->assertForbidden();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::UnderReview);
    expect($loanRequest->assigned_officer_id)->toBe($loanOfficer->user_id);
    expect(LoanRequestChange::query()->count())->toBe(0);
});

test('loan processors can request revision through the workflow route and create an audit row', function () {
    $loanOfficer = createWorkflowAuthorizationActor([Role::LOAN_PROCESSOR]);
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100011',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
        'assigned_officer_id' => $loanOfficer->user_id,
        'workflow_version' => LoanRequestWorkflowVersion::LegacyV1,
    ]);

    $response = $this
        ->actingAs($loanOfficer)
        ->patchJson(route('spa.workflow.loan-requests.request-revision', $loanRequest), [
            'remarks' => 'Please correct the employer address.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::NeedsRevision->value)
        ->assertJsonPath('data.loanRequest.review_decision', LoanRequestStatus::NeedsRevision->value)
        ->assertJsonPath('data.loanRequest.review_remarks', 'Please correct the employer address.');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::NeedsRevision);
    expect($loanRequest->reviewed_by)->toBe($loanOfficer->user_id);
    expect($loanRequest->review_decision)->toBe(LoanRequestStatus::NeedsRevision->value);
    expect($loanRequest->review_remarks)->toBe('Please correct the employer address.');

    $change = LoanRequestChange::query()->sole();

    expect($change->action)->toBe(LoanRequestChange::ACTION_REQUEST_REVISION);
    expect($change->from_status)->toBe(LoanRequestStatus::UnderReview->value);
    expect($change->to_status)->toBe(LoanRequestStatus::NeedsRevision->value);
    expect($change->reason)->toBe('Please correct the employer address.');
});

test('loan processors can reject through the workflow route and create an audit row', function () {
    $loanOfficer = createWorkflowAuthorizationActor([Role::LOAN_PROCESSOR]);
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100012',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);

    $response = $this
        ->actingAs($loanOfficer)
        ->patchJson(route('spa.workflow.loan-requests.reject', $loanRequest), [
            'rejection_reason' => 'Income documents are insufficient.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Rejected->value)
        ->assertJsonPath('data.loanRequest.review_decision', LoanRequestStatus::Rejected->value)
        ->assertJsonPath('data.loanRequest.rejection_reason', 'Income documents are insufficient.');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Rejected);
    expect($loanRequest->reviewed_by)->toBe($loanOfficer->user_id);
    expect($loanRequest->rejected_by)->toBe($loanOfficer->user_id);
    expect($loanRequest->rejection_reason)->toBe('Income documents are insufficient.');

    $change = LoanRequestChange::query()->sole();

    expect($change->action)->toBe(LoanRequestChange::ACTION_REJECT);
    expect($change->from_status)->toBe(LoanRequestStatus::UnderReview->value);
    expect($change->to_status)->toBe(LoanRequestStatus::Rejected->value);
    expect($change->reason)->toBe('Income documents are insufficient.');
});

test('loan processors can recommend approval through the workflow route and create an audit row', function () {
    $loanOfficer = createWorkflowAuthorizationActor([Role::LOAN_PROCESSOR]);
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100013',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'submitted_at' => now(),
        'assigned_officer_id' => $loanOfficer->user_id,
    ]);

    $response = $this
        ->actingAs($loanOfficer)
        ->patchJson(route('spa.workflow.loan-requests.recommend-approval', $loanRequest), [
            'review_remarks' => 'Ready for manager approval.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::RecommendedForApproval->value)
        ->assertJsonPath('data.loanRequest.review_decision', LoanRequestStatus::RecommendedForApproval->value)
        ->assertJsonPath('data.loanRequest.review_remarks', 'Ready for manager approval.');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::RecommendedForApproval);
    expect($loanRequest->reviewed_by)->toBe($loanOfficer->user_id);
    expect($loanRequest->review_decision)->toBe(LoanRequestStatus::RecommendedForApproval->value);
    expect($loanRequest->review_remarks)->toBe('Ready for manager approval.');

    $change = LoanRequestChange::query()->sole();

    expect($change->action)->toBe(LoanRequestChange::ACTION_RECOMMEND_APPROVAL);
    expect($change->from_status)->toBe(LoanRequestStatus::UnderReview->value);
    expect($change->to_status)->toBe(LoanRequestStatus::RecommendedForApproval->value);
    expect($change->reason)->toBe('Ready for manager approval.');
});

test('loan processors cannot approve through the workflow route', function () {
    Queue::fake();

    $loanOfficer = createWorkflowAuthorizationActor([Role::LOAN_PROCESSOR]);
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100014',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($loanOfficer)
        ->patchJson(route('spa.workflow.loan-requests.approve', $loanRequest), [
            'approved_amount' => 18000,
            'approved_term' => 12,
        ])
        ->assertForbidden();

    expect(LoanRequestChange::query()->count())->toBe(0);
});

test('loan managers can approve recommended requests through the workflow route and create an audit row', function () {
    Queue::fake();

    $loanManager = createWorkflowAuthorizationActor([Role::LOAN_MANAGER]);
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100015',
    );
    $reviewingOfficer = createWorkflowAuthorizationActor([Role::LOAN_PROCESSOR]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
        'reviewed_by' => $reviewingOfficer->user_id,
        'reviewed_at' => now()->subMinute(),
        'review_decision' => LoanRequestStatus::RecommendedForApproval->value,
        'review_remarks' => 'Officer recommendation.',
        'workflow_version' => LoanRequestWorkflowVersion::LegacyV1,
    ]);

    $response = $this
        ->actingAs($loanManager)
        ->patchJson(route('spa.workflow.loan-requests.approve', $loanRequest), [
            'approved_amount' => 22000,
            'approved_term' => 18,
            'approved_interest_rate' => 1.25,
            'approval_remarks' => 'Approved by manager.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Approved->value)
        ->assertJsonPath('data.loanRequest.approval_remarks', 'Approved by manager.')
        ->assertJsonPath('data.loanRequest.decision_notes', 'Approved by manager.');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Approved);
    expect($loanRequest->approved_by)->toBe($loanManager->user_id);
    expect($loanRequest->approved_amount)->toBe('22000.00');
    expect($loanRequest->approved_term)->toBe(18);
    expect($loanRequest->approved_interest_rate)->toBe('1.2500');
    expect($loanRequest->approval_remarks)->toBe('Approved by manager.');
    expect($loanRequest->decision_notes)->toBe('Approved by manager.');
    expect(DB::table('wlnmaster')->count())->toBe(0);
    expect(DB::table('wlnled')->count())->toBe(0);

    $change = LoanRequestChange::query()->sole();

    expect($change->action)->toBe(LoanRequestChange::ACTION_APPROVE);
    expect($change->from_status)->toBe(LoanRequestStatus::RecommendedForApproval->value);
    expect($change->to_status)->toBe(LoanRequestStatus::Approved->value);
    expect($change->reason)->toBe('Approved by manager.');
});

test('loan managers can decline recommended requests through the workflow route and create an audit row', function () {
    Queue::fake();

    $loanManager = createWorkflowAuthorizationActor([Role::LOAN_MANAGER]);
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100016',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => now(),
        'workflow_version' => LoanRequestWorkflowVersion::LegacyV1,
    ]);

    $response = $this
        ->actingAs($loanManager)
        ->patchJson(route('spa.workflow.loan-requests.decline', $loanRequest), [
            'decline_category' => 'policy',
            'decline_reason' => 'Debt-to-income ratio is too high.',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Declined->value)
        ->assertJsonPath('data.loanRequest.decline_reason', 'Debt-to-income ratio is too high.')
        ->assertJsonPath('data.loanRequest.decision_notes', 'Debt-to-income ratio is too high.');

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Declined);
    expect($loanRequest->declined_by)->toBe($loanManager->user_id);
    expect($loanRequest->decline_reason)->toBe('Debt-to-income ratio is too high.');
    expect($loanRequest->decision_notes)->toBe('Debt-to-income ratio is too high.');
    expect(DB::table('wlnmaster')->count())->toBe(0);
    expect(DB::table('wlnled')->count())->toBe(0);

    $change = LoanRequestChange::query()->sole();

    expect($change->action)->toBe(LoanRequestChange::ACTION_DECLINE);
    expect($change->from_status)->toBe(LoanRequestStatus::RecommendedForApproval->value);
    expect($change->to_status)->toBe(LoanRequestStatus::Declined->value);
    expect($change->reason)->toBe('Debt-to-income ratio is too high.');
});

test('workflow conversion route is removed and no portal action can call it', function () {
    $loanManager = createWorkflowAuthorizationActor([Role::LOAN_MANAGER]);
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100016A',
    );

    $loanRequest = createApprovedWorkflowLoanRequest($member);

    expect(app('router')->has('spa.workflow.loan-requests.convert-to-loan'))
        ->toBeFalse();

    $this
        ->actingAs($loanManager)
        ->patchJson("/spa/workflow/loan-requests/{$loanRequest->id}/convert-to-loan", [
            'remarks' => 'Should never execute.',
        ])
        ->assertNotFound();

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Approved);
    expect(DB::table('wlnmaster')->count())->toBe(0);
    expect(DB::table('wlnled')->count())->toBe(0);
    expect(LoanRequestChange::query()->count())->toBe(0);
});

test('loan managers cannot approve non recommended requests through the workflow route', function (LoanRequestStatus $status) {
    Queue::fake();

    $loanManager = createWorkflowAuthorizationActor([Role::LOAN_MANAGER]);
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100017',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => $status,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($loanManager)
        ->patchJson(route('spa.workflow.loan-requests.approve', $loanRequest), [
            'approved_amount' => 18000,
            'approved_term' => 12,
        ])
        ->assertForbidden();

    expect(LoanRequestChange::query()->count())->toBe(0);
})->with([
    'pending review' => LoanRequestStatus::PendingReview,
    'under review' => LoanRequestStatus::UnderReview,
]);

test('unauthorized direct workflow route calls are blocked', function () {
    $member = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100018',
    );
    $owner = createWorkflowAuthorizationActor(
        [Role::MEMBER],
        acctno: '100019',
    );

    $loanRequest = LoanRequest::factory()->forUser($owner)->create([
        'status' => LoanRequestStatus::PendingReview,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($member)
        ->patchJson(route('spa.workflow.loan-requests.start-review', $loanRequest), [])
        ->assertForbidden();

    expect(LoanRequestChange::query()->count())->toBe(0);
});

function createWorkflowAuthorizationActor(
    array $roles,
    bool $withAdminProfile = false,
    ?string $acctno = null,
): AppUser {
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
    ]);

    if ($withAdminProfile) {
        AdminProfile::factory()->admin()->create([
            'user_id' => $user->user_id,
        ]);
    }

    return syncWorkflowAuthorizationRoles($user, $roles);
}

function syncWorkflowAuthorizationRoles(AppUser $user, array $roles): AppUser
{
    $roleIds = Role::query()
        ->whereIn('name', $roles)
        ->pluck('id')
        ->all();

    $user->roles()->sync($roleIds);
    $user->unsetRelation('roles');

    return $user->load('roles.permissions');
}

function createApprovedWorkflowLoanRequest(
    AppUser $member,
    array $attributes = [],
): LoanRequest {
    return LoanRequest::factory()->forUser($member)->create(array_merge([
        'acctno' => $member->acctno,
        'typecode' => '02',
        'loan_type_label_snapshot' => 'MICRO BUSINESS LOAN',
        'loan_purpose' => 'Working capital',
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now()->subDay(),
        'approved_at' => now()->subHour(),
        'approved_amount' => 22000,
        'approved_term' => 18,
        'approved_interest_rate' => 1.25,
        'decision_notes' => 'Approved by manager.',
    ], $attributes));
}
