<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('admin loan request detail page includes the full audit trail payload', function (): void {
    $admin = createAuditTrailActor(
        [Role::ADMIN],
        withAdminProfile: true,
        username: 'Workflow Admin',
        fullname: 'Workflow Admin',
    );
    $member = createAuditTrailActor(
        [Role::MEMBER],
        acctno: '300001',
        username: 'Member Applicant',
    );
    $loanOfficer = createAuditTrailActor(
        [Role::LOAN_OFFICER],
        username: 'Loan Officer One',
    );
    $loanManager = createAuditTrailActor(
        [Role::LOAN_MANAGER],
        username: 'Loan Manager Two',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'acctno' => $member->acctno,
        'status' => LoanRequestStatus::ConvertedToLoan,
        'submitted_at' => Carbon::parse('2026-06-01 08:00:00'),
        'reviewed_by' => $loanOfficer->user_id,
        'reviewed_at' => Carbon::parse('2026-06-02 09:00:00'),
        'review_decision' => LoanRequestStatus::RecommendedForApproval->value,
        'review_remarks' => 'Ready for manager approval.',
        'approved_by' => $loanManager->user_id,
        'approved_at' => Carbon::parse('2026-06-03 10:00:00'),
        'approved_amount' => 24000,
        'approved_term' => 18,
        'approved_interest_rate' => 1.25,
        'decision_notes' => 'Released to accounting.',
    ]);

    createAuditTrailChange(
        $loanRequest,
        $loanOfficer,
        LoanRequestChange::ACTION_START_REVIEW,
        LoanRequestStatus::PendingReview->value,
        LoanRequestStatus::UnderReview->value,
        'Initial review started.',
        Carbon::parse('2026-06-02 08:30:00'),
    );
    createAuditTrailChange(
        $loanRequest,
        $loanOfficer,
        LoanRequestChange::ACTION_RECOMMEND_APPROVAL,
        LoanRequestStatus::UnderReview->value,
        LoanRequestStatus::RecommendedForApproval->value,
        'Ready for manager approval.',
        Carbon::parse('2026-06-02 09:00:00'),
    );
    createAuditTrailChange(
        $loanRequest,
        $loanManager,
        LoanRequestChange::ACTION_APPROVE,
        LoanRequestStatus::RecommendedForApproval->value,
        LoanRequestStatus::Approved->value,
        'Approved by manager.',
        Carbon::parse('2026-06-03 10:00:00'),
    );
    createAuditTrailChange(
        $loanRequest,
        $loanManager,
        LoanRequestChange::ACTION_CONVERT_TO_LOAN,
        LoanRequestStatus::Approved->value,
        LoanRequestStatus::ConvertedToLoan->value,
        'Released to accounting.',
        Carbon::parse('2026-06-04 11:00:00'),
        [
            'loan_number' => '0102-240001',
            'loan_status' => 'ACT',
            'ledger_control_no' => '1',
            'ledger_trans_no' => '1',
        ],
    );

    $this
        ->actingAs($admin)
        ->get(route('admin.requests.show', $loanRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/loan-request-show')
            ->has('auditTrail', 5)
            ->where('auditTrail.0.action', 'submitted')
            ->where('auditTrail.1.action', LoanRequestChange::ACTION_START_REVIEW)
            ->where('auditTrail.1.actor.name', 'Loan Officer One')
            ->where('auditTrail.1.from_status', LoanRequestStatus::PendingReview->value)
            ->where('auditTrail.1.to_status', LoanRequestStatus::UnderReview->value)
            ->where(
                'auditTrail.4.metadata',
                fn ($metadata): bool => collect($metadata)->contains(
                    fn ($item): bool => ($item['key'] ?? null) === 'loan_number'
                        && ($item['value'] ?? null) === '0102-240001',
                ),
            ));
});

test('staff loan request detail page includes the audit trail payload', function (): void {
    $loanManager = createAuditTrailActor(
        [Role::LOAN_MANAGER],
        username: 'Staff Manager',
    );
    $member = createAuditTrailActor(
        [Role::MEMBER],
        acctno: '300002',
        username: 'Member Owner',
    );
    $loanOfficer = createAuditTrailActor(
        [Role::LOAN_OFFICER],
        username: 'Queue Officer',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::RecommendedForApproval,
        'submitted_at' => Carbon::parse('2026-06-05 08:00:00'),
        'reviewed_by' => $loanOfficer->user_id,
        'reviewed_at' => Carbon::parse('2026-06-06 09:00:00'),
        'review_decision' => LoanRequestStatus::RecommendedForApproval->value,
        'review_remarks' => 'Queue ready for manager approval.',
    ]);

    createAuditTrailChange(
        $loanRequest,
        $loanOfficer,
        LoanRequestChange::ACTION_RECOMMEND_APPROVAL,
        LoanRequestStatus::UnderReview->value,
        LoanRequestStatus::RecommendedForApproval->value,
        'Queue ready for manager approval.',
        Carbon::parse('2026-06-06 09:00:00'),
    );

    $this
        ->actingAs($loanManager)
        ->get(route('staff.loan-requests.show', $loanRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/loan-request-show')
            ->has('auditTrail', 2)
            ->where('auditTrail.1.action', LoanRequestChange::ACTION_RECOMMEND_APPROVAL)
            ->where('auditTrail.1.to_status', LoanRequestStatus::RecommendedForApproval->value)
            ->where('auditTrail.1.actor.name', 'Queue Officer'));
});

test('member loan request detail page includes only safe audit trail entries', function (): void {
    $member = createAuditTrailActor(
        [Role::MEMBER],
        acctno: '300003',
        username: 'Member Viewer',
    );
    $loanOfficer = createAuditTrailActor(
        [Role::LOAN_OFFICER],
        username: 'Officer Reviewer',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'acctno' => $member->acctno,
        'status' => LoanRequestStatus::NeedsRevision,
        'submitted_at' => Carbon::parse('2026-06-07 08:00:00'),
        'reviewed_by' => $loanOfficer->user_id,
        'reviewed_at' => Carbon::parse('2026-06-08 10:00:00'),
        'review_decision' => LoanRequestStatus::NeedsRevision->value,
        'review_remarks' => 'Please update your employer address.',
    ]);

    createAuditTrailChange(
        $loanRequest,
        $loanOfficer,
        LoanRequestChange::ACTION_START_REVIEW,
        LoanRequestStatus::PendingReview->value,
        LoanRequestStatus::UnderReview->value,
        'Internal officer note.',
        Carbon::parse('2026-06-08 09:00:00'),
    );
    createAuditTrailChange(
        $loanRequest,
        $loanOfficer,
        LoanRequestChange::ACTION_REQUEST_REVISION,
        LoanRequestStatus::UnderReview->value,
        LoanRequestStatus::NeedsRevision->value,
        'Please update your employer address.',
        Carbon::parse('2026-06-08 10:00:00'),
        [
            'loan_number' => '0102-hidden',
        ],
    );

    $this
        ->actingAs($member)
        ->get(route('client.loan-requests.show', $loanRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/loan-request-show')
            ->has('auditTrail', 3)
            ->where('auditTrail.0.action', 'submitted')
            ->where('auditTrail.1.action', LoanRequestChange::ACTION_START_REVIEW)
            ->where('auditTrail.1.reason', null)
            ->where('auditTrail.1.actor', null)
            ->where('auditTrail.2.action', LoanRequestChange::ACTION_REQUEST_REVISION)
            ->where('auditTrail.2.reason', 'Please update your employer address.')
            ->where('auditTrail.2.actor', null)
            ->where('auditTrail.2.metadata', [])
            ->where('auditTrail', function ($entries): bool {
                return collect($entries)->every(function ($entry): bool {
                    return ! array_key_exists('metadata_json', $entry)
                        && ! array_key_exists('before_json', $entry)
                        && ! array_key_exists('after_json', $entry);
                });
            }));
});

test('workflow action responses include audit trail from and to statuses', function (): void {
    $loanOfficer = createAuditTrailActor(
        [Role::LOAN_OFFICER],
        username: 'Workflow Officer',
    );
    $member = createAuditTrailActor(
        [Role::MEMBER],
        acctno: '300004',
        username: 'Workflow Member',
    );

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'submitted_at' => Carbon::parse('2026-06-09 08:00:00'),
    ]);

    $this
        ->actingAs($loanOfficer)
        ->patchJson(route('spa.workflow.loan-requests.start-review', $loanRequest), [
            'remarks' => 'Review begins now.',
        ])
        ->assertOk()
        ->assertJsonPath('data.auditTrail.0.action', 'submitted')
        ->assertJsonPath('data.auditTrail.1.action', LoanRequestChange::ACTION_START_REVIEW)
        ->assertJsonPath('data.auditTrail.1.from_status', LoanRequestStatus::PendingReview->value)
        ->assertJsonPath('data.auditTrail.1.to_status', LoanRequestStatus::UnderReview->value);
});

function createAuditTrailActor(
    array $roles,
    bool $withAdminProfile = false,
    ?string $acctno = null,
    ?string $username = null,
    ?string $fullname = null,
): AppUser {
    $attributes = [];

    if ($acctno !== null) {
        $attributes['acctno'] = $acctno;
    }

    if ($username !== null) {
        $attributes['username'] = $username;
    }

    $user = AppUser::factory()->create($attributes);

    if ($withAdminProfile) {
        AdminProfile::factory()->admin()->create([
            'user_id' => $user->user_id,
            'fullname' => $fullname ?? $username ?? 'Workflow User',
        ]);
    }

    if ($acctno !== null) {
        UserProfile::factory()->approved()->create([
            'user_id' => $user->user_id,
        ]);

        MemberApplicationProfile::factory()->completed()->create([
            'user_id' => $user->user_id,
        ]);
    }

    $roleIds = Role::query()
        ->whereIn('name', $roles)
        ->pluck('id')
        ->all();

    $user->roles()->syncWithoutDetaching($roleIds);

    return $user->load('roles.permissions', 'adminProfile');
}

function createAuditTrailChange(
    LoanRequest $loanRequest,
    AppUser $actor,
    string $action,
    ?string $fromStatus,
    ?string $toStatus,
    ?string $reason,
    Carbon $createdAt,
    array $metadata = [],
): LoanRequestChange {
    $change = new LoanRequestChange([
        'loan_request_id' => $loanRequest->id,
        'changed_by' => $actor->user_id,
        'action' => $action,
        'from_status' => $fromStatus,
        'to_status' => $toStatus,
        'reason' => $reason,
        'before_json' => ['status' => $fromStatus],
        'after_json' => ['status' => $toStatus],
        'changed_fields_json' => ['status'],
        'metadata_json' => $metadata !== [] ? $metadata : null,
    ]);

    $change->created_at = $createdAt;
    $change->updated_at = $createdAt;
    $change->save();

    return $change;
}
