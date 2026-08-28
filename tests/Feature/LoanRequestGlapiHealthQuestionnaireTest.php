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
    $definitions = app(LoanRequestDataService::class)->sectionDefinitions();

    expect($definitions)->toHaveKey('health_glapi');

    $fields = $definitions['health_glapi']['fields'];

    // Reused GREPALIFE boolean gets a new companion details field.
    expect($fields)->toHaveKey('health_hypertension_details');
    expect($fields['health_hypertension_details']['detail_of'])
        ->toBe('health_hypertension');

    // Item #11 (smoking) was merged into the 'health' section's
    // health_smoking_status field; only its details companion still lives here.
    expect($fields)->not->toHaveKey('gl_health_q11_smoker');
    expect($fields)->toHaveKey('health_smoking_status_details');
    expect($fields['health_smoking_status_details']['detail_of'])
        ->toBe('health_smoking_status');

    // Item #5 stays distinct from the GREPALIFE boolean it overlaps with.
    expect($fields)->toHaveKey('gl_health_q05_confinement_5yr');

    // Item #2e's diabetes/kidney/liver/urinary cluster shares one details field.
    expect($fields)->toHaveKey('gl_health_q02e_diabetes');
    expect($fields)->toHaveKey('gl_health_q02e_kidney');
    expect($fields)->toHaveKey('gl_health_q02e_liver');
    expect($fields)->toHaveKey('gl_health_q02e_urinary');
    expect($fields['gl_health_q02e_diabetes_renal_details']['detail_of'])
        ->toBe([
            'gl_health_q02e_diabetes',
            'gl_health_q02e_kidney',
            'gl_health_q02e_liver',
            'gl_health_q02e_urinary',
        ]);

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

test('recent hospitalization is its own item positioned immediately after item #5', function (): void {
    $definitions = app(LoanRequestDataService::class)->sectionDefinitions();
    $fields = $definitions['health_glapi']['fields'];

    expect($fields)->toHaveKey('health_recent_hospitalization');
    expect($fields['health_recent_hospitalization']['detail_of'])->toBeNull();

    // Field-definition insertion order drives the wizard's displayed
    // sequence (getGlapiItemGroups/buildGlapiSequence derive it purely
    // from this order), so the new item must sit directly between item
    // #5's group (including its _details companion) and item #6.
    $rootKeys = array_values(array_filter(
        array_keys($fields),
        static fn (string $key): bool => ! isset($fields[$key]['detail_of']),
    ));

    $hospitalizationIndex = array_search('health_recent_hospitalization', $rootKeys, true);
    $item5Index = array_search('gl_health_q05_confinement_5yr', $rootKeys, true);
    $item6Index = array_search('gl_health_q06_abnormal_labs', $rootKeys, true);

    expect($hospitalizationIndex)->toBe($item5Index + 1);
    expect($item6Index)->toBe($hospitalizationIndex + 1);
});

test('smoking status and hypertension render as proper first-person questions, not raw labels', function (): void {
    $definitions = app(LoanRequestDataService::class)->sectionDefinitions();
    $fields = $definitions['health']['fields'];

    expect($fields['health_smoking_status']['label'])
        ->toBe('Do you currently smoke cigarettes?');

    expect($fields['health_hypertension']['label'])
        ->toBe('Have you ever been diagnosed with or treated for hypertension (high blood pressure)?');

    expect($definitions['health_glapi']['fields']['health_recent_hospitalization']['label'])
        ->toBe('Have you been confined in a hospital or undergone surgery recently?');
});

test('none of the health_glapi fields block member submission yet', function (): void {
    $member = createGlapiTestMember('002100');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $missing = app(LoanRequestDataService::class)->missingRequiredMemberFields($loanRequest);

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
            'health' => [
                'health_smoking_status' => 'light',
            ],
            'health_glapi' => [
                'gl_health_q01_weight_change' => true,
                'gl_health_q01_weight_change_details' => 'Lost 6 lbs after illness.',
                'health_smoking_status_details' => 'About 5 sticks a day.',
                'gl_health_q02e_kidney' => true,
                'gl_health_q02e_liver' => true,
                'gl_health_q02e_diabetes_renal_details' => 'Kidney stones (2023), mild liver enzymes.',
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
    expect($values->get('health_smoking_status'))->toBe('light');
    expect($values->get('health_smoking_status_details'))
        ->toBe('About 5 sticks a day.');
    expect($values->get('gl_health_q02e_kidney'))->toBeTrue();
    expect($values->get('gl_health_q02e_liver'))->toBeTrue();
    expect($values->get('gl_health_q02e_diabetes'))->not->toBeTrue();
    expect($values->get('gl_health_q02e_diabetes_renal_details'))
        ->toBe('Kidney stones (2023), mild liver enzymes.');
});

test('save-draft rejects an invalid health_smoking_status value', function (): void {
    $member = createGlapiTestMember('002105');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'health' => [
                'health_smoking_status' => 'sometimes',
            ],
        ])
        ->assertUnprocessable();
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
