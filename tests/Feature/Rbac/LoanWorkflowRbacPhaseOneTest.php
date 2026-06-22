<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LoanWorkflowRbacSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('phase one rbac schema is present', function () {
    expect(Schema::hasTable('roles'))->toBeTrue();
    expect(Schema::hasTable('permissions'))->toBeTrue();
    expect(Schema::hasTable('role_permissions'))->toBeTrue();
    expect(Schema::hasTable('user_roles'))->toBeTrue();

    $workflowColumns = [
        'assigned_officer_id',
        'reviewed_by',
        'reviewed_at',
        'review_decision',
        'review_remarks',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'approval_remarks',
        'approved_amount',
        'approved_term',
        'approved_interest_rate',
        'declined_by',
        'declined_at',
        'decline_reason',
    ];

    foreach ($workflowColumns as $column) {
        expect(Schema::hasColumn('loan_requests', $column))->toBeTrue();
    }

    foreach ([
        'from_status',
        'to_status',
        'metadata_json',
    ] as $column) {
        expect(Schema::hasColumn('loan_request_changes', $column))->toBeTrue();
    }
});

test('phase one workflow field migration rolls back and reapplies all workflow columns', function () {
    $workflowColumns = [
        'assigned_officer_id',
        'reviewed_by',
        'reviewed_at',
        'review_decision',
        'review_remarks',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'approved_by',
        'approved_at',
        'approval_remarks',
        'approved_amount',
        'approved_term',
        'approved_interest_rate',
        'declined_by',
        'declined_at',
        'decline_reason',
    ];

    /** @var \Illuminate\Database\Migrations\Migration $migration */
    $migration = require database_path('migrations/2026_06_09_114848_add_phase_one_workflow_fields_to_loan_requests_table.php');

    foreach ($workflowColumns as $column) {
        expect(Schema::hasColumn('loan_requests', $column))->toBeTrue();
    }

    $migration->down();

    foreach ($workflowColumns as $column) {
        expect(Schema::hasColumn('loan_requests', $column))->toBeFalse();
    }

    $migration->up();

    foreach ($workflowColumns as $column) {
        expect(Schema::hasColumn('loan_requests', $column))->toBeTrue();
    }
});

test('loan workflow rbac seeder is idempotent and backfills admin, superadmin, and member roles safely', function () {
    $adminOnly = AppUser::factory()->create(['acctno' => null]);
    AdminProfile::factory()->admin()->create([
        'user_id' => $adminOnly->user_id,
    ]);

    $hybridAdmin = AppUser::factory()->create([
        'acctno' => '123456',
    ]);
    AdminProfile::factory()->admin()->create([
        'user_id' => $hybridAdmin->user_id,
    ]);

    $member = AppUser::factory()->create([
        'acctno' => '654321',
    ]);

    $legacySuperadmin = AppUser::factory()->create([
        'acctno' => null,
    ]);
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $legacySuperadmin->user_id,
    ]);

    $unknownUser = AppUser::factory()->create([
        'acctno' => null,
    ]);

    $this->seed(LoanWorkflowRbacSeeder::class);
    $this->seed(LoanWorkflowRbacSeeder::class);

    expect(Role::query()->pluck('name')->sort()->values()->all())->toBe([
        Role::ADMIN,
        Role::LOAN_MANAGER,
        Role::LOAN_PROCESSOR,
        Role::MEMBER,
        Role::SUPERADMIN,
    ]);
    expect(Permission::query()->count())->toBe(count(Permission::defaults()));

    $adminRole = Role::query()->where('name', Role::ADMIN)->firstOrFail();
    $memberRole = Role::query()->where('name', Role::MEMBER)->firstOrFail();
    $loanOfficerRole = Role::query()->where('name', Role::LOAN_PROCESSOR)->firstOrFail();
    $loanManagerRole = Role::query()->where('name', Role::LOAN_MANAGER)->firstOrFail();
    $superadminRole = Role::query()->where('name', Role::SUPERADMIN)->firstOrFail();

    expect($adminRole->permissions()->pluck('name')->sort()->values()->all())->toBe([
        Permission::LOAN_VIEW,
        Permission::MEMBER_CREATE,
        Permission::MEMBER_UPDATE,
        Permission::MEMBER_VIEW,
        Permission::PAYMENT_CREATE,
    ]);
    expect($memberRole->permissions()->pluck('name')->sort()->values()->all())->toBe([
        Permission::LOAN_CREATE,
        Permission::LOAN_VIEW,
    ]);
    expect($superadminRole->permissions()->pluck('name')->sort()->values()->all())->toBe([
        Permission::LOAN_MANAGE_ASSIGNMENT,
        Permission::LOAN_VIEW,
        Permission::MEMBER_CREATE,
        Permission::MEMBER_UPDATE,
        Permission::MEMBER_VIEW,
        Permission::PAYMENT_CREATE,
        Permission::REPORT_EXPORT,
        Permission::REPORT_VIEW_ALL,
        Permission::STAFF_MANAGE,
        Permission::STAFF_VIEW,
    ]);
    expect($loanOfficerRole->permissions()->pluck('name')->sort()->values()->all())->toBe([
        Permission::LOAN_CLAIM,
        Permission::LOAN_RECOMMEND_APPROVAL,
        Permission::LOAN_REJECT,
        Permission::LOAN_REQUEST_REVISION,
        Permission::LOAN_RETURN_TO_QUEUE,
        Permission::LOAN_REVIEW,
        Permission::LOAN_VIEW,
        Permission::REPORT_VIEW_OWN,
    ]);
    expect($loanManagerRole->permissions()->pluck('name')->sort()->values()->all())->toBe([
        Permission::LOAN_APPROVE,
        Permission::LOAN_DECLINE,
        Permission::LOAN_MANAGE_ASSIGNMENT,
        Permission::LOAN_VIEW,
        Permission::LOAN_WIBS_ENCODE,
        Permission::REPORT_EXPORT,
        Permission::REPORT_VIEW_ALL,
    ]);

    $legacyConversionPermission = Permission::query()->firstOrCreate(
        ['name' => Permission::LOAN_CONVERT_TO_LOAN],
        ['display_name' => 'Convert approved requests to loans'],
    );

    $adminRole->permissions()->syncWithoutDetaching([$legacyConversionPermission->id]);
    $loanManagerRole->permissions()->syncWithoutDetaching([$legacyConversionPermission->id]);

    $this->seed(LoanWorkflowRbacSeeder::class);

    expect($adminRole->fresh()->permissions()->pluck('name')->all())
        ->not->toContain(Permission::LOAN_CONVERT_TO_LOAN);
    expect($loanManagerRole->fresh()->permissions()->pluck('name')->all())
        ->not->toContain(Permission::LOAN_CONVERT_TO_LOAN);

    $adminOnly->load('roles.permissions');
    $hybridAdmin->load('roles.permissions');
    $member->load('roles.permissions');
    $legacySuperadmin->load('roles.permissions', 'adminProfile');
    $unknownUser->load('roles.permissions');

    expect($adminOnly->hasRole(Role::ADMIN))->toBeTrue();
    expect($adminOnly->hasRole(Role::MEMBER))->toBeFalse();
    expect($adminOnly->hasPermission(Permission::PAYMENT_CREATE))->toBeTrue();
    expect($adminOnly->hasPermission(Permission::LOAN_CONVERT_TO_LOAN))->toBeFalse();
    expect($adminOnly->hasPermission(Permission::LOAN_REVIEW))->toBeFalse();
    expect($adminOnly->hasPermission(Permission::LOAN_APPROVE))->toBeFalse();
    expect($adminOnly->roles()->count())->toBe(1);

    expect($hybridAdmin->hasRole(Role::ADMIN))->toBeTrue();
    expect($hybridAdmin->hasRole(Role::MEMBER))->toBeTrue();
    expect($hybridAdmin->hasAnyRole([Role::LOAN_MANAGER, Role::MEMBER]))->toBeTrue();
    expect($hybridAdmin->hasPermission(Permission::LOAN_CREATE))->toBeTrue();
    expect($hybridAdmin->hasPermission(Permission::LOAN_CONVERT_TO_LOAN))->toBeFalse();
    expect($hybridAdmin->hasPermission(Permission::MEMBER_UPDATE))->toBeTrue();

    expect($member->hasRole(Role::MEMBER))->toBeTrue();
    expect($member->hasRole(Role::ADMIN))->toBeFalse();
    expect($member->hasPermission(Permission::LOAN_VIEW))->toBeTrue();
    expect($member->hasPermission(Permission::PAYMENT_CREATE))->toBeFalse();

    expect($legacySuperadmin->hasRole(Role::SUPERADMIN))->toBeTrue();
    expect($legacySuperadmin->isSuperadmin())->toBeTrue();
    expect($legacySuperadmin->hasPermission(Permission::STAFF_MANAGE))->toBeTrue();
    expect($legacySuperadmin->hasPermission(Permission::REPORT_VIEW_ALL))->toBeTrue();
    expect($legacySuperadmin->hasPermission(Permission::LOAN_REVIEW))->toBeFalse();
    expect($legacySuperadmin->hasPermission(Permission::LOAN_APPROVE))->toBeFalse();

    expect($unknownUser->roles()->count())->toBe(0);
    expect($unknownUser->hasAnyRole([Role::ADMIN, Role::MEMBER]))->toBeFalse();
});

test('app user recognizes explicit and legacy superadmin access', function () {
    Role::ensureWorkflowDefaults();

    $explicitSuperadmin = AppUser::factory()->create([
        'acctno' => null,
    ]);
    $explicitSuperadmin->roles()->sync([
        Role::query()->where('name', Role::SUPERADMIN)->value('id'),
    ]);

    $legacySuperadmin = AppUser::factory()->create([
        'acctno' => null,
    ]);
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $legacySuperadmin->user_id,
    ]);

    $regularAdmin = AppUser::factory()->create([
        'acctno' => null,
    ]);
    AdminProfile::factory()->admin()->create([
        'user_id' => $regularAdmin->user_id,
    ]);

    expect($explicitSuperadmin->fresh()->isSuperadmin())->toBeTrue();
    expect($legacySuperadmin->fresh()->isSuperadmin())->toBeTrue();
    expect($regularAdmin->fresh()->isSuperadmin())->toBeFalse();
});

test('superadmin does not receive loan processor or manager decision permissions unless explicitly assigned', function () {
    Role::ensureWorkflowDefaults();

    $superadmin = AppUser::factory()->create([
        'acctno' => null,
    ]);
    AdminProfile::factory()->superadmin()->create([
        'user_id' => $superadmin->user_id,
    ]);

    $superadmin->load('roles.permissions');

    expect($superadmin->hasPermission(Permission::LOAN_VIEW))->toBeTrue();
    expect($superadmin->hasPermission(Permission::LOAN_REVIEW))->toBeFalse();
    expect($superadmin->hasPermission(Permission::LOAN_RECOMMEND_APPROVAL))->toBeFalse();
    expect($superadmin->hasPermission(Permission::LOAN_APPROVE))->toBeFalse();
    expect($superadmin->hasPermission(Permission::LOAN_DECLINE))->toBeFalse();

    Role::attachNamedRole($superadmin, Role::LOAN_PROCESSOR);
    Role::attachNamedRole($superadmin, Role::LOAN_MANAGER);

    $superadmin->refresh()->load('roles.permissions');

    expect($superadmin->hasPermission(Permission::LOAN_REVIEW))->toBeTrue();
    expect($superadmin->hasPermission(Permission::LOAN_RECOMMEND_APPROVAL))->toBeTrue();
    expect($superadmin->hasPermission(Permission::LOAN_APPROVE))->toBeTrue();
    expect($superadmin->hasPermission(Permission::LOAN_DECLINE))->toBeTrue();
});

test('loan request status enum exposes phase one workflow values without removing legacy ones', function () {
    expect(LoanRequestStatus::tryFrom('draft'))->toBe(LoanRequestStatus::Draft);
    expect(LoanRequestStatus::tryFrom('pending_review'))->toBe(LoanRequestStatus::PendingReview);
    expect(LoanRequestStatus::tryFrom('needs_revision'))->toBe(LoanRequestStatus::NeedsRevision);
    expect(LoanRequestStatus::tryFrom('recommended_for_approval'))->toBe(LoanRequestStatus::RecommendedForApproval);
    expect(LoanRequestStatus::tryFrom('rejected'))->toBe(LoanRequestStatus::Rejected);
    expect(LoanRequestStatus::tryFrom('converted_to_loan'))->toBe(LoanRequestStatus::ConvertedToLoan);
    expect(LoanRequestStatus::requestFilterValues())->toContain(
        LoanRequestStatus::Draft->value,
        LoanRequestStatus::PendingReview->value,
        LoanRequestStatus::NeedsRevision->value,
        LoanRequestStatus::RecommendedForApproval->value,
        LoanRequestStatus::Rejected->value,
        LoanRequestStatus::ConvertedToLoan->value,
    );
});

test('database seeder wires the loan workflow rbac seeder into the default seed flow', function () {
    config([
        'portal.admin_username' => 'phase1-admin',
        'portal.admin_email' => 'phase1-admin@example.com',
        'portal.admin_password' => Str::random(32),
        'portal.admin_phoneno' => '09999999999',
        'portal.admin_fullname' => 'Phase One Administrator',
    ]);

    $this->seed(DatabaseSeeder::class);

    $admin = AppUser::query()
        ->where('email', 'phase1-admin@example.com')
        ->firstOrFail();

    $admin->load('roles.permissions', 'adminProfile');

    expect($admin->adminProfile?->access_level)->toBe(AdminProfile::ACCESS_LEVEL_ADMIN);
    expect($admin->hasRole(Role::ADMIN))->toBeTrue();
    expect($admin->hasPermission(Permission::PAYMENT_CREATE))->toBeTrue();
});
