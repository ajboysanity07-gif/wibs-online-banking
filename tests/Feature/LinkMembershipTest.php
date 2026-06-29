<?php

use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;
use App\Models\UserProfile;
use App\Models\UserRoleChange;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

function lmCreateStaffOnlyUser(?string $email = null): AppUser
{
    $user = AppUser::factory()->create([
        'acctno' => null,
        'email' => $email ?? fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
    ]);

    $user->roles()->sync(
        Role::query()->whereIn('name', [Role::LOAN_PROCESSOR])->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);

    return $user->fresh(['roles.permissions', 'userProfile']);
}

function lmSeedWmaster(string $acctno, string $fname = 'Juan', string $lname = 'Dela Cruz', ?string $mname = null): void
{
    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        ['fname' => $fname, 'lname' => $lname, 'mname' => $mname, 'birthday' => '1990-01-01', 'address' => 'Test St'],
    );
}

// ---------------------------------------------------------------------------
// Happy path
// ---------------------------------------------------------------------------

test('staff-only user can link a valid unclaimed membership', function (): void {
    $user = lmCreateStaffOnlyUser();
    lmSeedWmaster('100001', 'Maria', 'Santos');

    $countBefore = AppUser::count();

    $this->actingAs($user)
        ->post(route('profile.link-membership'), [
            'accntno' => '100001',
            'last_name' => 'Santos',
            'first_name' => 'Maria',
        ])
        ->assertRedirectToRoute('profile.edit')
        ->assertSessionHas('status', 'membership-linked');

    $user->refresh()->load('roles');

    expect($user->acctno)->toBe('100001')
        ->and($user->hasRole(Role::MEMBER))->toBeTrue()
        ->and($user->hasRole(Role::LOAN_PROCESSOR))->toBeTrue()
        ->and(AppUser::count())->toBe($countBefore);
});

test('user_id is unchanged after linking — no new account created', function (): void {
    $user = lmCreateStaffOnlyUser();
    lmSeedWmaster('100002', 'Pedro', 'Reyes');

    $originalUserId = $user->user_id;

    $this->actingAs($user)
        ->post(route('profile.link-membership'), [
            'accntno' => '100002',
            'last_name' => 'Reyes',
            'first_name' => 'Pedro',
        ])
        ->assertRedirectToRoute('profile.edit');

    $user->refresh();
    expect($user->user_id)->toBe($originalUserId)
        ->and($user->acctno)->toBe('100002');
});

// ---------------------------------------------------------------------------
// GUARD 1: acctno uniqueness
// ---------------------------------------------------------------------------

test('GUARD 1: cannot link an acctno already claimed by another portal account', function (): void {
    $existing = AppUser::factory()->create([
        'acctno' => '200001',
        'email_verified_at' => now(),
    ]);
    UserProfile::factory()->approved()->create(['user_id' => $existing->user_id]);

    $staff = lmCreateStaffOnlyUser();
    lmSeedWmaster('200001', 'Ana', 'Cruz');

    $this->actingAs($staff)
        ->post(route('profile.link-membership'), [
            'accntno' => '200001',
            'last_name' => 'Cruz',
            'first_name' => 'Ana',
        ])
        ->assertSessionHasErrors(['accntno']);

    $staff->refresh();
    expect($staff->acctno)->toBeNull();
});

// ---------------------------------------------------------------------------
// GUARD 2: wmaster verification
// ---------------------------------------------------------------------------

test('GUARD 2: linking fails when acctno does not exist in wmaster', function (): void {
    $user = lmCreateStaffOnlyUser();

    $this->actingAs($user)
        ->post(route('profile.link-membership'), [
            'accntno' => 'NOTEXIST',
            'last_name' => 'Anyone',
            'first_name' => 'Someone',
        ])
        ->assertSessionHasErrors(['accntno']);

    $user->refresh();
    expect($user->acctno)->toBeNull();
});

test('GUARD 2: linking fails when last name does not match wmaster', function (): void {
    $user = lmCreateStaffOnlyUser();
    lmSeedWmaster('300001', 'Rosa', 'Garcia');

    $this->actingAs($user)
        ->post(route('profile.link-membership'), [
            'accntno' => '300001',
            'last_name' => 'WRONG',
            'first_name' => 'Rosa',
        ])
        ->assertSessionHasErrors(['accntno']);

    $user->refresh();
    expect($user->acctno)->toBeNull();
});

test('GUARD 2: linking fails when first name does not match wmaster', function (): void {
    $user = lmCreateStaffOnlyUser();
    lmSeedWmaster('300002', 'Rosa', 'Garcia');

    $this->actingAs($user)
        ->post(route('profile.link-membership'), [
            'accntno' => '300002',
            'last_name' => 'Garcia',
            'first_name' => 'WRONG',
        ])
        ->assertSessionHasErrors(['accntno']);

    $user->refresh();
    expect($user->acctno)->toBeNull();
});

// ---------------------------------------------------------------------------
// GUARD 3: already-linked users excluded
// ---------------------------------------------------------------------------

test('GUARD 3: user with an existing acctno is rejected with 403', function (): void {
    $member = AppUser::factory()->create([
        'acctno' => '400001',
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    lmSeedWmaster('400002', 'Test', 'User');

    $this->actingAs($member)
        ->post(route('profile.link-membership'), [
            'accntno' => '400002',
            'last_name' => 'User',
            'first_name' => 'Test',
        ])
        ->assertForbidden();

    $member->refresh();
    expect($member->acctno)->toBe('400001');
});

// ---------------------------------------------------------------------------
// GUARD 4: conflict of interest after linking
// ---------------------------------------------------------------------------

test('GUARD 4: hybrid user is detected as owner of their own loan by acctno', function (): void {
    $user = lmCreateStaffOnlyUser();
    lmSeedWmaster('500001', 'Lita', 'Ramos');

    $this->actingAs($user)
        ->post(route('profile.link-membership'), [
            'accntno' => '500001',
            'last_name' => 'Ramos',
            'first_name' => 'Lita',
        ])
        ->assertRedirectToRoute('profile.edit');

    $user->refresh();

    $ownedLoan = LoanRequest::factory()->create([
        'user_id' => $user->user_id,
        'acctno' => '500001',
    ]);

    // isOwnedBy returning true means canActOnAnotherUsersRequest will return
    // false — the existing COI policy already covers hybrid users via acctno match.
    expect($ownedLoan->isOwnedBy($user))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Audit trail
// ---------------------------------------------------------------------------

test('linking creates a UserRoleChange audit entry with correct fields', function (): void {
    $user = lmCreateStaffOnlyUser();
    lmSeedWmaster('600001', 'Carlo', 'Bautista');

    $this->actingAs($user)
        ->post(route('profile.link-membership'), [
            'accntno' => '600001',
            'last_name' => 'Bautista',
            'first_name' => 'Carlo',
        ])
        ->assertRedirectToRoute('profile.edit');

    $audit = UserRoleChange::query()
        ->where('target_user_id', $user->user_id)
        ->where('action', UserRoleChange::ACTION_MEMBERSHIP_LINKED)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->actor_user_id)->toBe($user->user_id)
        ->and($audit->role_name)->toBe(Role::MEMBER)
        ->and($audit->metadata_json['linked_acctno'] ?? null)->toBe('600001')
        ->and($audit->metadata_json['flow'] ?? null)->toBe('staff_self_link');
});

// ---------------------------------------------------------------------------
// Unauthenticated / edge cases
// ---------------------------------------------------------------------------

test('unauthenticated request is redirected to login', function (): void {
    lmSeedWmaster('700001', 'Guest', 'Test');

    $this->post(route('profile.link-membership'), [
        'accntno' => '700001',
        'last_name' => 'Test',
        'first_name' => 'Guest',
    ])->assertRedirectToRoute('login');
});

test('name comparison is case-insensitive', function (): void {
    $user = lmCreateStaffOnlyUser();
    lmSeedWmaster('800001', 'JOSE', 'RIZAL');

    $this->actingAs($user)
        ->post(route('profile.link-membership'), [
            'accntno' => '800001',
            'last_name' => 'rizal',
            'first_name' => 'jose',
        ])
        ->assertRedirectToRoute('profile.edit')
        ->assertSessionHas('status', 'membership-linked');

    $user->refresh();
    expect($user->acctno)->toBe('800001');
});
