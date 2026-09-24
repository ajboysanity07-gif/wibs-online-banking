<?php

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\LoanRequestPerson;
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

function createDiscardDraftMember(string $acctno): AppUser
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
        ['fname' => 'Discard', 'lname' => 'Member', 'birthday' => '1990-01-01', 'address' => 'Discard St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile']);
}

test('owner can discard their own draft', function (): void {
    $member = createDiscardDraftMember('003001');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    LoanRequestPerson::query()->create([
        'loan_request_id' => $loanRequest->id,
        'role' => LoanRequestPersonRole::Applicant->value,
        'first_name' => 'Discard',
        'last_name' => 'Member',
    ]);

    $loanRequest->dataEntries()->create([
        'field_key' => 'loan_purpose',
        'section_key' => 'system',
        'owner_type' => 'member',
        'is_sensitive' => false,
        'confirmed_by_member' => false,
        'confirmed_by_member_at' => null,
        'value_json' => ['value' => 'Education'],
        'metadata_json' => ['label' => 'Loan purpose', 'type' => 'string'],
    ]);

    $this->actingAs($member)
        ->deleteJson(route('client.loan-requests.discard-draft', $loanRequest))
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(LoanRequest::query()->whereKey($loanRequest->id)->exists())->toBeFalse();
    expect(LoanRequestPerson::query()->where('loan_request_id', $loanRequest->id)->exists())->toBeFalse();
    expect(DB::table('loan_request_data_entries')->where('loan_request_id', $loanRequest->id)->exists())->toBeFalse();
});

test('discard draft returns 404 when the request belongs to a different user', function (): void {
    $owner = createDiscardDraftMember('003002');
    $other = createDiscardDraftMember('003003');

    $loanRequest = LoanRequest::factory()->forUser($owner)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $owner->acctno,
    ]);

    $this->actingAs($other)
        ->deleteJson(route('client.loan-requests.discard-draft', $loanRequest))
        ->assertNotFound();

    expect(LoanRequest::query()->whereKey($loanRequest->id)->exists())->toBeTrue();
});

test('discard draft rejects non-draft statuses', function (): void {
    $member = createDiscardDraftMember('003004');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::PendingReview,
        'acctno' => $member->acctno,
        'submitted_at' => now(),
    ]);

    $this->actingAs($member)
        ->deleteJson(route('client.loan-requests.discard-draft', $loanRequest))
        ->assertStatus(422);

    expect(LoanRequest::query()->whereKey($loanRequest->id)->exists())->toBeTrue();
});

test('discard draft creates no LoanRequestChange audit entry', function (): void {
    $member = createDiscardDraftMember('003005');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->deleteJson(route('client.loan-requests.discard-draft', $loanRequest))
        ->assertOk();

    expect(LoanRequestChange::query()->where('loan_request_id', $loanRequest->id)->count())->toBe(0);
});

test('discard draft never touches MemberApplicationProfile', function (): void {
    $member = createDiscardDraftMember('003006');

    $profileBefore = MemberApplicationProfile::query()
        ->where('user_id', $member->user_id)
        ->first()
        ->getAttributes();

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->deleteJson(route('client.loan-requests.discard-draft', $loanRequest))
        ->assertOk();

    $profileAfter = MemberApplicationProfile::query()
        ->where('user_id', $member->user_id)
        ->first()
        ->getAttributes();

    unset($profileBefore['updated_at'], $profileAfter['updated_at']);

    expect($profileAfter)->toBe($profileBefore);
});
