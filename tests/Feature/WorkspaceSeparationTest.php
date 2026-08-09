<?php

use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;
use App\Models\StaffAccessControl;
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
});

test('member only users resolve only the member workspace', function (): void {
    $member = createWorkspaceMemberUser();

    $this
        ->actingAs($member)
        ->get(route('client.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.availableWorkspaces', ['member'])
            ->where('auth.activeWorkspace', 'member')
            ->where('auth.hasMultipleWorkspaces', false)
            ->where('auth.isHybrid', false));
});

test('staff only workflow users resolve only the staff workspace', function (): void {
    $loanOfficer = createStaffWorkspaceUser([Role::LOAN_PROCESSOR]);

    $this
        ->actingAs($loanOfficer)
        ->get(route('staff.loan-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.availableWorkspaces', ['staff'])
            ->where('auth.activeWorkspace', 'staff')
            ->where('auth.hasMultipleWorkspaces', false)
            ->where('auth.canAccessLoanWorkflow', true));
});

test('rbac hybrid users expose both workspaces', function (string $role): void {
    $hybrid = createWorkspaceMemberUser([
        $role,
    ]);

    $this
        ->actingAs($hybrid)
        ->get(route('client.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.availableWorkspaces', ['member', 'staff'])
            ->where('auth.activeWorkspace', 'member')
            ->where('auth.hasMultipleWorkspaces', true)
            ->where('auth.isHybrid', true));
})->with([
    'loan processor hybrid' => Role::LOAN_PROCESSOR,
    'loan manager hybrid' => Role::LOAN_MANAGER,
    'superadmin hybrid' => Role::SUPERADMIN,
]);

test('legacy superadmins continue to resolve the staff workspace', function (): void {
    $legacySuperadmin = createStaffWorkspaceUser(
        [],
        withSuperadminProfile: true,
    );

    $this
        ->actingAs($legacySuperadmin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.availableWorkspaces', ['staff'])
            ->where('auth.activeWorkspace', 'staff')
            ->where('auth.hasMultipleWorkspaces', false));
});

test('an unmigrated legacy admin profile with no RBAC role cannot resolve the staff workspace', function (): void {
    $unmigratedLegacyAdmin = createStaffWorkspaceUser(
        [],
        withAdminProfile: true,
    );

    $this
        ->actingAs($unmigratedLegacyAdmin)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('suspended hybrid staff retain member access only', function (): void {
    $hybrid = createWorkspaceMemberUser([
        Role::LOAN_PROCESSOR,
    ]);

    StaffAccessControl::factory()->suspended()->create([
        'user_id' => $hybrid->user_id,
    ]);

    $this
        ->actingAs($hybrid->fresh())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.availableWorkspaces', ['member'])
            ->where('auth.activeWorkspace', 'member')
            ->where('auth.hasMultipleWorkspaces', false)
            ->where('auth.hasMemberAccess', true));

    $this
        ->actingAs($hybrid->fresh())
        ->get(route('staff.loan-requests.index'))
        ->assertForbidden();

    $this
        ->actingAs($hybrid->fresh())
        ->post(route('workspace.switch'), [
            'workspace' => 'staff',
        ])
        ->assertForbidden();
});

test('workspace switching validates, persists, and updates shared context', function (): void {
    $hybrid = createWorkspaceMemberUser([
        Role::LOAN_PROCESSOR,
    ]);

    $this
        ->actingAs($hybrid)
        ->post(route('workspace.switch'), [
            'workspace' => 'staff',
        ])
        ->assertRedirect(route('staff.loan-requests.index'))
        ->assertSessionHas('active_workspace', 'staff');

    $this
        ->actingAs($hybrid)
        ->withSession(['active_workspace' => 'staff'])
        ->get(route('dashboard'))
        ->assertRedirect(route('staff.loan-requests.index'));

    $this
        ->actingAs($hybrid)
        ->withSession(['active_workspace' => 'staff'])
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.activeWorkspace', 'staff'));

    $this
        ->actingAs($hybrid)
        ->withSession(['active_workspace' => 'staff'])
        ->post(route('workspace.switch'), [
            'workspace' => 'member',
        ])
        ->assertRedirect(route('client.dashboard'))
        ->assertSessionHas('active_workspace', 'member');
});

test('workspace switching rejects invalid and unavailable values', function (): void {
    $hybrid = createWorkspaceMemberUser([
        Role::LOAN_PROCESSOR,
    ]);
    $memberOnly = createWorkspaceMemberUser();

    $this
        ->actingAs($hybrid)
        ->post(route('workspace.switch'), [
            'workspace' => 'invalid',
        ])
        ->assertInvalid(['workspace']);

    $this
        ->actingAs($memberOnly)
        ->post(route('workspace.switch'), [
            'workspace' => 'staff',
        ])
        ->assertForbidden();
});

test('invalid stored workspaces are cleared and normalized', function (): void {
    $member = createWorkspaceMemberUser();

    $this
        ->actingAs($member)
        ->withSession(['active_workspace' => 'staff'])
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSessionMissing('active_workspace')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.availableWorkspaces', ['member'])
            ->where('auth.activeWorkspace', 'member'));
});

test('spa login redirects respect workspace defaults', function (): void {
    $staffOnlySuperadmin = createStaffWorkspaceUser([
        Role::SUPERADMIN,
    ]);
    $staffOnlyOfficer = createStaffWorkspaceUser([
        Role::LOAN_PROCESSOR,
    ]);
    $hybrid = createWorkspaceMemberUser([
        Role::LOAN_PROCESSOR,
    ]);

    $this->postJson('/spa/auth/login', [
        'email' => $staffOnlySuperadmin->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('redirect_to', route('superadmin.staff.index', absolute: false));

    $this->postJson('/spa/auth/logout')->assertOk();

    $this->postJson('/spa/auth/login', [
        'email' => $staffOnlyOfficer->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('redirect_to', route('staff.loan-requests.index', absolute: false));

    $this->postJson('/spa/auth/logout')->assertOk();

    $this->postJson('/spa/auth/login', [
        'email' => $hybrid->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('redirect_to', route('dashboard', absolute: false));
});

test('direct member and staff pages normalize workspace context', function (): void {
    $hybrid = createWorkspaceMemberUser([
        Role::LOAN_PROCESSOR,
    ]);

    $this
        ->actingAs($hybrid)
        ->get(route('client.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.activeWorkspace', 'member'));

    $this
        ->actingAs($hybrid)
        ->get(route('staff.loan-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.activeWorkspace', 'staff'));
});

test('staff cannot act on their own applications after switching to staff workspace', function (): void {
    $hybrid = createWorkspaceMemberUser([
        Role::LOAN_PROCESSOR,
    ]);
    $ownRequest = LoanRequest::factory()->forUser($hybrid)->create([
        'status' => LoanRequestStatus::PendingReview,
        'submitted_at' => now(),
    ]);

    $this
        ->actingAs($hybrid)
        ->post(route('workspace.switch'), [
            'workspace' => 'staff',
        ])
        ->assertRedirect(route('staff.loan-requests.index'));

    $this
        ->actingAs($hybrid)
        ->withSession(['active_workspace' => 'staff'])
        ->patchJson(route('spa.workflow.loan-requests.start-review', $ownRequest), [
            'remarks' => 'Attempted self review.',
        ])
        ->assertForbidden();
});

function createWorkspaceMemberUser(
    array $roles = [],
    bool $withAdminProfile = false,
    bool $withSuperadminProfile = false,
): AppUser {
    $user = AppUser::factory()->create([
        'acctno' => fake()->unique()->numerify('######'),
    ]);

    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => sprintf('Workspace Member %s', $user->user_id),
        'fname' => 'Workspace',
        'lname' => 'Member',
        'birthday' => '1990-01-01',
        'address' => '123 Workspace Street',
        'civilstat' => 'Single',
        'occupation' => 'Member',
    ]);

    \App\Models\MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $user->user_id,
    ]);

    if ($withAdminProfile || $withSuperadminProfile) {
        $factory = $withSuperadminProfile
            ? AdminProfile::factory()->superadmin()
            : AdminProfile::factory()->admin();

        $factory->create([
            'user_id' => $user->user_id,
        ]);
    }

    return syncWorkspaceRoles($user, $roles);
}

function createStaffWorkspaceUser(
    array $roles = [],
    bool $withAdminProfile = false,
    bool $withSuperadminProfile = false,
): AppUser {
    $user = AppUser::factory()->create([
        'acctno' => null,
    ]);

    if ($withAdminProfile || $withSuperadminProfile) {
        $factory = $withSuperadminProfile
            ? AdminProfile::factory()->superadmin()
            : AdminProfile::factory()->admin();

        $factory->create([
            'user_id' => $user->user_id,
        ]);
    }

    return syncWorkspaceRoles($user, $roles);
}

function syncWorkspaceRoles(AppUser $user, array $roles): AppUser
{
    foreach ($roles as $role) {
        Role::attachNamedRole($user, $role);
    }

    $user->unsetRelation('roles');

    return $user->fresh([
        'adminProfile',
        'memberApplicationProfile',
        'roles.permissions',
        'staffAccessControl',
        'userProfile',
    ]);
}
