<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
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

function createExistingLoansTestMember(string $acctno): AppUser
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
        ['fname' => 'EXISTING', 'lname' => 'Loans Member', 'birthday' => '1990-01-01', 'address' => 'Loans St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile']);
}

test('declarations section exposes the 3 existing-loan slots gated by declaration_existing_loans', function (): void {
    $definitions = (new LoanRequestDataService)->sectionDefinitions();

    expect($definitions)->toHaveKey('declarations');

    $fields = $definitions['declarations']['fields'];

    foreach ([1, 2, 3] as $slot) {
        foreach (['date', 'type', 'amount'] as $suffix) {
            $key = "existing_loan_{$slot}_{$suffix}";

            expect($fields)->toHaveKey($key);
            expect($fields[$key]['visible_when'])->toBe([
                'field' => 'declaration_existing_loans',
                'equals' => true,
            ]);
        }
    }

    expect($fields['existing_loan_1_date']['type'])->toBe('date');
    expect($fields['existing_loan_1_type']['type'])->toBe('string');
    expect($fields['existing_loan_1_amount']['type'])->toBe('number');
});

test('draft endpoint persists the 3 existing-loan slots', function (): void {
    $member = createExistingLoansTestMember('003101');

    // declaration_existing_loans is now always system-derived (see
    // LoanRequestService::applySystemDeclarations), so this needs a real
    // wlnmaster row for the flag to persist as true.
    if (! Schema::hasTable('wlnmaster')) {
        Schema::create('wlnmaster', function (Blueprint $table): void {
            $table->string('acctno');
            $table->string('lnnumber');
            $table->string('lntype')->nullable();
            $table->string('lnstatus')->nullable();
            $table->decimal('principal', 15, 2)->nullable();
            $table->decimal('balance', 15, 2)->nullable();
            $table->date('date_rel')->nullable();
            $table->date('date_mat')->nullable();
            $table->date('lastmove')->nullable();
        });
    }

    DB::table('wlnmaster')->insert([
        'acctno' => '003101',
        'lnnumber' => 'LN-003101',
        'lntype' => 'Salary loan',
        'lnstatus' => 'ACT',
        'principal' => 15000.50,
        'balance' => 8000,
        'date_rel' => '2024-01-15',
        'date_mat' => '2025-01-15',
    ]);

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'declarations' => [
                'declaration_existing_loans' => true,
                'existing_loan_1_date' => '2024-01-15',
                'existing_loan_1_type' => 'Salary loan',
                'existing_loan_1_amount' => '15000.50',
                'existing_loan_2_date' => '2023-06-01',
                'existing_loan_2_type' => 'Emergency loan',
                'existing_loan_2_amount' => '5000',
            ],
        ])
        ->assertNoContent();

    $values = collect($loanRequest->fresh()->dataEntries)
        ->mapWithKeys(fn ($entry) => [$entry->field_key => $entry->value_json['value'] ?? null]);

    expect($values->get('declaration_existing_loans'))->toBeTrue();
    expect($values->get('existing_loan_1_date'))->toBe('2024-01-15');
    expect($values->get('existing_loan_1_type'))->toBe('Salary loan');
    expect($values->get('existing_loan_1_amount'))->toBe('15000.50');
    expect($values->get('existing_loan_2_date'))->toBe('2023-06-01');
    expect($values->get('existing_loan_2_type'))->toBe('Emergency loan');
    expect($values->get('existing_loan_2_amount'))->toBe('5000');
    expect($values->get('existing_loan_3_date'))->toBeNull();
});

test('save-draft rejects a non-numeric existing-loan amount', function (): void {
    $member = createExistingLoansTestMember('003102');

    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'declarations' => [
                'existing_loan_1_amount' => 'not-a-number',
            ],
        ])
        ->assertUnprocessable();
});

test('save-draft ignores a client-submitted declaration_existing_loans that disagrees with account records', function (): void {
    $member = createExistingLoansTestMember('003104');

    // No wlnmaster row for this account -> system truth is false, even
    // though the member's payload claims true.
    $loanRequest = LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.save-draft', $loanRequest), [
            'declarations' => [
                'declaration_existing_loans' => true,
                'declaration_pending_cases' => true,
            ],
        ])
        ->assertNoContent();

    $values = collect($loanRequest->fresh()->dataEntries)
        ->mapWithKeys(fn ($entry) => [$entry->field_key => $entry->value_json['value'] ?? null]);

    expect($values->get('declaration_existing_loans'))->toBeFalse();
    expect($values->get('declaration_pending_cases'))->toBeFalse();
});

test('declarations submit request rejects an unknown existing-loan key', function (): void {
    $member = createExistingLoansTestMember('003103');

    $this->actingAs($member)
        ->patchJson(route('client.loan-requests.draft'), [
            'declarations' => [
                'existing_loan_4_date' => '2024-01-01',
            ],
        ])
        ->assertUnprocessable();
});
