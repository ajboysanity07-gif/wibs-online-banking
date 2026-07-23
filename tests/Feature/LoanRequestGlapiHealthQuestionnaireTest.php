<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use App\Services\LoanRequests\LoanRequestDataService;
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

function createGlapiTestMember(string $acctno): AppUser
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
        ['fname' => 'GLAPI', 'lname' => 'Member', 'birthday' => '1990-01-01', 'address' => 'GLAPI St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile']);
}

test('health_glapi section exposes detail_of pairings and the sex-conditional item', function (): void {
    $definitions = (new LoanRequestDataService)->sectionDefinitions();

    expect($definitions)->toHaveKey('health_glapi');

    $fields = $definitions['health_glapi']['fields'];

    // Reused GREPALIFE boolean gets a new companion details field.
    expect($fields)->toHaveKey('health_hypertension_details');
    expect($fields['health_hypertension_details']['detail_of'])
        ->toBe('health_hypertension');

    // Item #5 and #11 stay distinct from the GREPALIFE booleans they overlap with.
    expect($fields)->toHaveKey('gl_health_q05_confinement_5yr');
    expect($fields)->toHaveKey('gl_health_q11_smoker');

    // Item #15 (pregnancy) is conditional on applicant.sex === Female.
    expect($fields['gl_health_q15_pregnancy']['visible_when'])->toBe([
        'field' => 'applicant.sex',
        'equals' => 'Female',
    ]);

    // Item #17's nested breakdown chains three levels of detail_of.
    expect($fields['gl_health_q17_with_glapi']['detail_of'])
        ->toBe('gl_health_q17_pending_reinstatement');
    expect($fields['gl_health_q17_with_glapi_amount']['detail_of'])
        ->toBe('gl_health_q17_with_glapi');
});

test('none of the health_glapi fields block member submission yet', function (): void {
    $member = createGlapiTestMember('002100');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $missing = (new LoanRequestDataService)->missingRequiredMemberFields($loanRequest);

    $glapiMissing = array_filter(
        $missing,
        static fn (string $fieldKey): bool => str_starts_with($fieldKey, 'gl_health_')
            || $fieldKey === 'health_hypertension_details',
    );

    expect($glapiMissing)->toBe([]);
});

test('draft endpoint persists health_glapi boolean, detail, and amount fields', function (): void {
    $member = createGlapiTestMember('002101');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'health_glapi' => [
                'gl_health_q01_weight_change' => true,
                'gl_health_q01_weight_change_details' => 'Lost 6 lbs after illness.',
                'gl_health_q17_pending_reinstatement' => true,
                'gl_health_q17_with_glapi' => true,
                'gl_health_q17_with_glapi_amount' => '15000.50',
            ],
        ])
        ->assertNoContent();

    $values = collect($loanRequest->fresh()->dataEntries)
        ->mapWithKeys(fn ($entry) => [$entry->field_key => $entry->value_json['value'] ?? null]);

    expect($values->get('gl_health_q01_weight_change'))->toBeTrue();
    expect($values->get('gl_health_q01_weight_change_details'))
        ->toBe('Lost 6 lbs after illness.');
    expect($values->get('gl_health_q17_with_glapi_amount'))->toBe('15000.50');
});

test('draft endpoint rejects an unknown health_glapi key', function (): void {
    // client.loan-requests.draft (LoanRequestDraftRequest) enforces a closed
    // key allowlist on health_glapi, unlike save-draft (SaveDraftRequest)
    // which -- like its existing 'health'/'insurance' arrays -- does not.
    $member = createGlapiTestMember('002102');

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.draft'), [
            'health_glapi' => [
                'not_a_real_field' => true,
            ],
        ])
        ->assertUnprocessable();
});

test('draft endpoint persists and normalizes applicant sex', function (): void {
    $member = createGlapiTestMember('002103');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'applicant' => ['sex' => 'Female'],
        ])
        ->assertNoContent();

    $person = LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('role', 'applicant')
        ->first();

    expect($person)->not->toBeNull();
    expect($person->sex)->toBe('Female');
});

test('draft endpoint rejects an invalid sex value', function (): void {
    $member = createGlapiTestMember('002104');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'applicant' => ['sex' => 'Other'],
        ])
        ->assertUnprocessable();
});
