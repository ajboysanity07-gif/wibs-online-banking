<?php

use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\LoanRequestCondition;
use App\Models\Role;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

/**
 * @param  list<string>  $roles
 */
function conditionsActor(array $roles, ?string $acctno = null): AppUser
{
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
        'phoneno' => null,
        'email_verified_at' => now(),
    ]);

    $user->roles()->sync(
        Role::query()->whereIn('name', $roles)->pluck('id')->all(),
    );

    return $user->fresh(['roles.permissions', 'staffAccessControl']);
}

function conditionsRequest(AppUser $member, AppUser $processor): LoanRequest
{
    return LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);
}

test('assigned processor can verify and unverify a condition with an audit entry each time', function (): void {
    $processor = conditionsActor([Role::LOAN_PROCESSOR]);
    $member = conditionsActor([Role::MEMBER], '960001');
    $loanRequest = conditionsRequest($member, $processor);
    $url = route('spa.workflow.loan-requests.conditions.update', [$loanRequest, 'employment']);

    $this->actingAs($processor)
        ->patchJson($url, ['verified' => true])
        ->assertOk()
        ->assertJsonPath('data.conditions.available', true)
        ->assertJsonPath('data.conditions.items.1.key', 'employment')
        ->assertJsonPath('data.conditions.items.1.verified', true)
        ->assertJsonPath('data.conditions.items.0.verified', false);

    $row = LoanRequestCondition::query()->where('loan_request_id', $loanRequest->id)->sole();

    expect($row->condition_key)->toBe('employment')
        ->and($row->verified_by)->toBe($processor->user_id)
        ->and($row->verified_at)->not->toBeNull()
        ->and(LoanRequestChange::query()
            ->where('loan_request_id', $loanRequest->id)
            ->where('action', LoanRequestChange::ACTION_CONDITION_VERIFIED)
            ->where('changed_by', $processor->user_id)
            ->count())->toBe(1);

    $this->actingAs($processor)
        ->patchJson($url, ['verified' => false])
        ->assertOk()
        ->assertJsonPath('data.conditions.items.1.verified', false);

    expect(LoanRequestCondition::query()->count())->toBe(0)
        ->and(LoanRequestChange::query()
            ->where('action', LoanRequestChange::ACTION_CONDITION_UNVERIFIED)
            ->count())->toBe(1);
});

test('re-sending the current state is idempotent and writes no extra audit entry', function (): void {
    $processor = conditionsActor([Role::LOAN_PROCESSOR]);
    $member = conditionsActor([Role::MEMBER], '960002');
    $loanRequest = conditionsRequest($member, $processor);
    $url = route('spa.workflow.loan-requests.conditions.update', [$loanRequest, 'identity_membership']);

    $this->actingAs($processor)->patchJson($url, ['verified' => true])->assertOk();
    $this->actingAs($processor)->patchJson($url, ['verified' => true])->assertOk();

    expect(LoanRequestCondition::query()->count())->toBe(1)
        ->and(LoanRequestChange::query()
            ->where('action', LoanRequestChange::ACTION_CONDITION_VERIFIED)
            ->count())->toBe(1);

    $other = route('spa.workflow.loan-requests.conditions.update', [$loanRequest, 'employment']);
    $this->actingAs($processor)->patchJson($other, ['verified' => false])->assertOk();

    expect(LoanRequestChange::query()
        ->where('action', LoanRequestChange::ACTION_CONDITION_UNVERIFIED)
        ->count())->toBe(0);
});

test('only the assigned processor may verify conditions', function (): void {
    $processor = conditionsActor([Role::LOAN_PROCESSOR]);
    $otherProcessor = conditionsActor([Role::LOAN_PROCESSOR]);
    $manager = conditionsActor([Role::LOAN_MANAGER]);
    $member = conditionsActor([Role::MEMBER], '960003');
    $loanRequest = conditionsRequest($member, $processor);
    $url = route('spa.workflow.loan-requests.conditions.update', [$loanRequest, 'employment']);

    foreach ([$otherProcessor, $manager, $member] as $actor) {
        $this->actingAs($actor)->patchJson($url, ['verified' => true])->assertForbidden();
    }

    expect(LoanRequestCondition::query()->count())->toBe(0)
        ->and(LoanRequestChange::query()->count())->toBe(0);
});

test('a processor cannot verify conditions on their own application', function (): void {
    $processor = conditionsActor([Role::LOAN_PROCESSOR, Role::MEMBER], '960004');
    $loanRequest = conditionsRequest($processor, $processor);
    $url = route('spa.workflow.loan-requests.conditions.update', [$loanRequest, 'employment']);

    $this->actingAs($processor)->patchJson($url, ['verified' => true])->assertForbidden();
});

test('conditions are locked once the request leaves processing', function (): void {
    $processor = conditionsActor([Role::LOAN_PROCESSOR]);
    $member = conditionsActor([Role::MEMBER], '960005');
    $loanRequest = conditionsRequest($member, $processor);
    $loanRequest->update(['status' => LoanRequestStatus::RecommendedForApproval]);
    $url = route('spa.workflow.loan-requests.conditions.update', [$loanRequest, 'employment']);

    $this->actingAs($processor)->patchJson($url, ['verified' => true])->assertForbidden();
});

test('unknown conditions and missing flags fail validation', function (): void {
    $processor = conditionsActor([Role::LOAN_PROCESSOR]);
    $member = conditionsActor([Role::MEMBER], '960006');
    $loanRequest = conditionsRequest($member, $processor);

    $this->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.conditions.update', [$loanRequest, 'bogus']), ['verified' => true])
        ->assertUnprocessable();

    $this->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.conditions.update', [$loanRequest, 'employment']), [])
        ->assertUnprocessable();
});

test('staff show page exposes the conditions and who may verify them', function (): void {
    $processor = conditionsActor([Role::LOAN_PROCESSOR]);
    $member = conditionsActor([Role::MEMBER], '960007');
    $loanRequest = conditionsRequest($member, $processor);

    $this->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.conditions.update', [$loanRequest, 'employment']), ['verified' => true])
        ->assertOk();

    $this->actingAs($processor)
        ->get(route('staff.loan-requests.show', $loanRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/loan-request-show')
            ->where('conditions.available', true)
            ->where('conditions.can_verify', true)
            ->where('conditions.items.1.verified', true)
            ->where('conditions.items.0.verified', false));
});

test('staff show page still loads and reports conditions unavailable before the migration runs', function (): void {
    $processor = conditionsActor([Role::LOAN_PROCESSOR]);
    $member = conditionsActor([Role::MEMBER], '960008');
    $loanRequest = conditionsRequest($member, $processor);

    Schema::drop('loan_request_conditions');

    $this->actingAs($processor)
        ->get(route('staff.loan-requests.show', $loanRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conditions.available', false)
            ->where('conditions.items', []));
});
