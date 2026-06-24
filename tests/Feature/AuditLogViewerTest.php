<?php

use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\DocumentAccessLog;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\Role;
use App\Models\StaffAccessControl;
use App\Models\UserRoleChange;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

function makeSuperadminForAudit(): AppUser
{
    $user = AppUser::factory()->create(['email_verified_at' => now()]);
    AdminProfile::factory()->create([
        'user_id' => $user->user_id,
        'access_level' => \App\Models\AdminProfile::ACCESS_LEVEL_SUPERADMIN,
    ]);
    Role::attachNamedRole($user, Role::SUPERADMIN);
    StaffAccessControl::factory()->create(['user_id' => $user->user_id]);
    $user->forceFill(['two_factor_secret' => 'fakesecret', 'two_factor_confirmed_at' => now()])->save();

    return $user->fresh(['roles.permissions']);
}

test('audit log index returns paginated results', function (): void {
    $superadmin = makeSuperadminForAudit();
    UserRoleChange::factory()->count(5)->create();

    $this->actingAs($superadmin)
        ->getJson('/spa/superadmin/audit-log')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonStructure([
            'data' => [
                'items',
                'meta' => ['page', 'per_page', 'total', 'last_page'],
            ],
        ]);
});

test('audit log can filter by type role_changes', function (): void {
    $superadmin = makeSuperadminForAudit();
    UserRoleChange::factory()->count(3)->create();

    $response = $this->actingAs($superadmin)
        ->getJson('/spa/superadmin/audit-log?type=role_changes')
        ->assertOk();

    $items = $response->json('data.items');
    collect($items)->each(fn ($item) => expect($item['type'])->toBe('role_change'));
});

test('audit log can filter by type loan_changes', function (): void {
    $superadmin = makeSuperadminForAudit();
    $member = AppUser::factory()->create();
    LoanRequestChange::factory()->count(3)->create([
        'changed_by' => $member->user_id,
    ]);

    $response = $this->actingAs($superadmin)
        ->getJson('/spa/superadmin/audit-log?type=loan_changes')
        ->assertOk();

    $items = $response->json('data.items');
    collect($items)->each(fn ($item) => expect($item['type'])->toBe('loan_change'));
});

test('audit log can filter by type document_access', function (): void {
    $superadmin = makeSuperadminForAudit();
    DocumentAccessLog::factory()->count(3)->create();

    $response = $this->actingAs($superadmin)
        ->getJson('/spa/superadmin/audit-log?type=document_access')
        ->assertOk();

    $items = $response->json('data.items');
    collect($items)->each(fn ($item) => expect($item['type'])->toBe('document_access'));
});

test('audit log can filter by date range', function (): void {
    $superadmin = makeSuperadminForAudit();
    UserRoleChange::factory()->create(['created_at' => now()->subDays(10)]);
    UserRoleChange::factory()->create(['created_at' => now()->subDay()]);

    $response = $this->actingAs($superadmin)
        ->getJson('/spa/superadmin/audit-log?type=role_changes&date_from='.now()->subDays(2)->toDateString().'&date_to='.now()->toDateString())
        ->assertOk();

    $items = $response->json('data.items');
    expect(count($items))->toBe(1);
});

test('audit log can filter by loan reference', function (): void {
    $superadmin = makeSuperadminForAudit();
    $member = AppUser::factory()->create();
    $loanRequest = LoanRequest::factory()->forUser($member)->create();
    LoanRequestChange::factory()->create(['loan_request_id' => $loanRequest->id]);
    LoanRequestChange::factory()->create();

    $response = $this->actingAs($superadmin)
        ->getJson('/spa/superadmin/audit-log?type=loan_changes&loan_reference='.$loanRequest->reference)
        ->assertOk();

    $items = $response->json('data.items');
    expect(count($items))->toBe(1);
    expect($items[0]['loan_reference'])->toBe($loanRequest->reference);
});

test('non-superadmin cannot access audit log', function (): void {
    $member = AppUser::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($member)
        ->getJson('/spa/superadmin/audit-log')
        ->assertForbidden();
});
