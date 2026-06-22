<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
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

/**
 * Create a fully-authorized member AppUser.
 */
function createDraftMember(string $acctno): AppUser
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
        ['fname' => 'Draft', 'lname' => 'Member', 'birthday' => '1990-01-01', 'address' => 'Draft St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile']);
}

test('save draft returns 204 for the request owner', function (): void {
    $member = createDraftMember('002001');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'loan_purpose' => 'Education',
        ])
        ->assertNoContent();
});

test('save draft returns 403 when the request belongs to a different user', function (): void {
    $owner = createDraftMember('002002');
    $other = createDraftMember('002003');

    $loanRequest = LoanRequest::factory()->forUser($owner)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $owner->acctno,
    ]);

    $this->actingAs($other)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [])
        ->assertForbidden();
});

test('save draft returns 403 when the request is not in draft status', function (): void {
    $member = createDraftMember('002004');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'acctno' => $member->acctno,
        'submitted_at' => now(),
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [])
        ->assertForbidden();
});

test('save draft does not create a LoanRequestChange entry', function (): void {
    $member = createDraftMember('002005');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $changesBefore = LoanRequestChange::query()
        ->where('loan_request_id', $loanRequest->id)
        ->count();

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'loan_purpose' => 'Home renovation',
        ])
        ->assertNoContent();

    $changesAfter = LoanRequestChange::query()
        ->where('loan_request_id', $loanRequest->id)
        ->count();

    expect($changesAfter)->toBe($changesBefore);
});

test('save draft accepts partial payload without validation errors', function (): void {
    $member = createDraftMember('002006');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'requested_amount' => '25000',
        ])
        ->assertNoContent();
});
