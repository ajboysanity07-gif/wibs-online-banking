<?php

use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\LoanRequestNote;
use App\Models\Role;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

/**
 * @param  list<string>  $roles
 */
function notesActor(array $roles, ?string $acctno = null): AppUser
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

function notesRequest(AppUser $member, AppUser $processor): LoanRequest
{
    return LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
    ]);
}

test('staff can add an internal note; it is listed newest first with an audit entry that omits the text', function (): void {
    $processor = notesActor([Role::LOAN_PROCESSOR]);
    $manager = notesActor([Role::LOAN_MANAGER]);
    $member = notesActor([Role::MEMBER], '970001');
    $loanRequest = notesRequest($member, $processor);
    $url = route('spa.workflow.loan-requests.notes.store', $loanRequest);

    $this->actingAs($processor)
        ->postJson($url, ['body' => '  Called member about employer.  '])
        ->assertCreated()
        ->assertJsonPath('data.notes.available', true)
        ->assertJsonPath('data.notes.items.0.body', 'Called member about employer.');

    $this->travel(1)->minutes();

    $this->actingAs($manager)
        ->postJson($url, ['body' => 'Awaiting callback.'])
        ->assertCreated();

    $this->actingAs($processor)
        ->getJson(route('spa.workflow.loan-requests.notes.index', $loanRequest))
        ->assertOk()
        ->assertJsonCount(2, 'data.notes.items')
        ->assertJsonPath('data.notes.items.0.body', 'Awaiting callback.')
        ->assertJsonPath('data.notes.items.1.body', 'Called member about employer.');

    $changes = LoanRequestChange::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('action', LoanRequestChange::ACTION_INTERNAL_NOTE_ADDED)
        ->get();

    expect($changes)->toHaveCount(2)
        ->and($changes->pluck('reason')->unique()->all())->toBe(['Internal note added'])
        ->and(LoanRequestNote::query()->where('user_id', $processor->user_id)->count())->toBe(1);
});

test('members and the staff applicant can neither read nor add notes', function (): void {
    $processor = notesActor([Role::LOAN_PROCESSOR]);
    $member = notesActor([Role::MEMBER], '970002');
    $loanRequest = notesRequest($member, $processor);

    $this->actingAs($member)
        ->postJson(route('spa.workflow.loan-requests.notes.store', $loanRequest), ['body' => 'x'])
        ->assertForbidden();
    $this->actingAs($member)
        ->getJson(route('spa.workflow.loan-requests.notes.index', $loanRequest))
        ->assertForbidden();

    $staffApplicant = notesActor([Role::LOAN_PROCESSOR, Role::MEMBER], '970003');
    $ownRequest = notesRequest($staffApplicant, $processor);

    $this->actingAs($staffApplicant)
        ->getJson(route('spa.workflow.loan-requests.notes.index', $ownRequest))
        ->assertForbidden();

    expect(LoanRequestNote::query()->count())->toBe(0)
        ->and(LoanRequestChange::query()->count())->toBe(0);
});

test('note body is required and capped', function (): void {
    $processor = notesActor([Role::LOAN_PROCESSOR]);
    $member = notesActor([Role::MEMBER], '970004');
    $loanRequest = notesRequest($member, $processor);
    $url = route('spa.workflow.loan-requests.notes.store', $loanRequest);

    $this->actingAs($processor)->postJson($url, ['body' => '   '])->assertUnprocessable();
    $this->actingAs($processor)->postJson($url, [])->assertUnprocessable();
    $this->actingAs($processor)
        ->postJson($url, ['body' => str_repeat('a', 2001)])
        ->assertUnprocessable();

    expect(LoanRequestNote::query()->count())->toBe(0);
});

test('staff show page shares notes with staff', function (): void {
    $processor = notesActor([Role::LOAN_PROCESSOR]);
    $member = notesActor([Role::MEMBER], '970005');
    $loanRequest = notesRequest($member, $processor);

    $this->actingAs($processor)
        ->postJson(route('spa.workflow.loan-requests.notes.store', $loanRequest), ['body' => 'First note'])
        ->assertCreated();

    $this->actingAs($processor)
        ->get(route('staff.loan-requests.show', $loanRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('internalNotes.available', true)
            ->where('internalNotes.can_add', true)
            ->where('internalNotes.items.0.body', 'First note'));
});

test('staff show page still loads and reports notes unavailable before the migration runs', function (): void {
    $processor = notesActor([Role::LOAN_PROCESSOR]);
    $member = notesActor([Role::MEMBER], '970006');
    $loanRequest = notesRequest($member, $processor);

    Schema::drop('loan_request_notes');

    $this->actingAs($processor)
        ->get(route('staff.loan-requests.show', $loanRequest))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('internalNotes.available', false)
            ->where('internalNotes.items', []));
});
