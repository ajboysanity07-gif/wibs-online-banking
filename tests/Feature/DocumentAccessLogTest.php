<?php

use App\Models\AdminProfile;
use App\Models\AppUser;
use App\Models\DocumentAccessLog;
use App\Models\LoanRequest;
use App\Models\Role;
use App\Models\StaffAccessControl;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('document view access is logged', function (): void {
    $user = AppUser::factory()->create();

    DocumentAccessLog::record(
        $user->user_id,
        null,
        'application_form',
        DocumentAccessLog::ACTION_VIEW,
    );

    $log = DocumentAccessLog::query()->where('user_id', $user->user_id)->first();
    expect($log)->not->toBeNull();
    expect($log->action)->toBe(DocumentAccessLog::ACTION_VIEW);
    expect($log->document_key)->toBe('application_form');
});

test('document download access is logged', function (): void {
    $user = AppUser::factory()->create();

    DocumentAccessLog::record(
        $user->user_id,
        null,
        'promissory_note',
        DocumentAccessLog::ACTION_DOWNLOAD,
    );

    $log = DocumentAccessLog::query()->where('user_id', $user->user_id)->first();
    expect($log->action)->toBe(DocumentAccessLog::ACTION_DOWNLOAD);
    expect($log->document_key)->toBe('promissory_note');
});

test('document access log links loan request', function (): void {
    $user = AppUser::factory()->create();
    $loanRequest = LoanRequest::factory()->forUser($user)->create();

    DocumentAccessLog::record(
        $user->user_id,
        $loanRequest->id,
        'loan_information',
        DocumentAccessLog::ACTION_VIEW,
    );

    $log = DocumentAccessLog::query()->where('loan_request_id', $loanRequest->id)->first();
    expect($log)->not->toBeNull();
    expect($log->loan_request_id)->toBe($loanRequest->id);
});

test('document access log records accessed_at timestamp', function (): void {
    $user = AppUser::factory()->create();

    DocumentAccessLog::record($user->user_id, null, 'grepalife', DocumentAccessLog::ACTION_VIEW);

    $log = DocumentAccessLog::query()->where('user_id', $user->user_id)->first();
    expect($log->accessed_at)->not->toBeNull();
});

test('superadmin can query document access logs via audit endpoint', function (): void {
    $superadmin = AppUser::factory()->create(['email_verified_at' => now()]);
    AdminProfile::factory()->create([
        'user_id' => $superadmin->user_id,
        'access_level' => \App\Models\AdminProfile::ACCESS_LEVEL_SUPERADMIN,
    ]);
    Role::attachNamedRole($superadmin, Role::SUPERADMIN);
    StaffAccessControl::factory()->create(['user_id' => $superadmin->user_id]);
    $superadmin->forceFill(['two_factor_secret' => 'fakesecret', 'two_factor_confirmed_at' => now()])->save();

    $superadmin = $superadmin->fresh(['roles.permissions']);

    $user = AppUser::factory()->create();
    DocumentAccessLog::factory()->count(3)->create(['user_id' => $user->user_id]);

    $this->actingAs($superadmin)
        ->getJson('/spa/superadmin/audit-log?type=document_access')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonStructure(['data' => ['items', 'meta']]);
});
