<?php

use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

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

    if (! Schema::hasTable('wlntype')) {
        Schema::create('wlntype', function (Blueprint $table): void {
            $table->string('typecode')->primary();
            $table->string('lntype');
        });
    }
});

function createAutoFillMember(string $acctno): AppUser
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
        ['fname' => 'Auto', 'lname' => 'Fill', 'bname' => 'Auto Fill Member', 'birthday' => '1990-01-01', 'address' => 'Auto St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile']);
}

function createWlnmasterFeatureTable(bool $withLnstatus = true, bool $withDateRel = true): void
{
    if (Schema::hasTable('wlnmaster')) {
        return;
    }

    Schema::create('wlnmaster', function (Blueprint $table) use ($withLnstatus, $withDateRel): void {
        $table->string('acctno');
        $table->string('lnnumber');
        $table->string('lntype')->nullable();
        if ($withLnstatus) {
            $table->string('lnstatus')->nullable();
        }
        $table->decimal('principal', 12, 2)->default(0);
        $table->decimal('balance', 12, 2)->default(0);
        if ($withDateRel) {
            $table->date('date_rel')->nullable();
            $table->date('date_mat')->nullable();
        }
        $table->dateTime('lastmove')->nullable();
    });
}

function createAutoFillLoan(array $attributes): void
{
    $defaults = [
        'acctno' => '000001',
        'lnnumber' => 'LN-001',
        'lntype' => 'Personal',
        'lnstatus' => 'ACT',
        'principal' => 10000,
        'balance' => 5000,
        'date_rel' => '2024-01-15',
        'date_mat' => '2025-01-15',
        'lastmove' => '2024-06-01',
    ];

    $values = array_merge($defaults, $attributes);

    if (! Schema::hasColumn('wlnmaster', 'lnstatus')) {
        unset($values['lnstatus']);
    }

    if (! Schema::hasColumn('wlnmaster', 'date_rel')) {
        unset($values['date_rel'], $values['date_mat']);
    }

    DB::table('wlnmaster')->insert($values);
}

test('no wlnmaster rows -> auto-filled declarations stay empty', function (): void {
    createWlnmasterFeatureTable();
    $member = createAutoFillMember('000001');

    $this
        ->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('autoFilledDeclarations.declaration_existing_loans', false)
            ->where('autoFilledDeclarations.declaration_pending_cases', false)
            ->where('autoFilledDeclarations.existing_loan_1_date', null)
            ->where('autoFilledDeclarations.existing_loan_1_type', null)
            ->where('autoFilledDeclarations.existing_loan_1_amount', null)
        );
});

test('active loan only -> existing flagged but no pending cases', function (): void {
    createWlnmasterFeatureTable();
    $member = createAutoFillMember('000001');

    createAutoFillLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-ACT',
        'lntype' => 'Salary loan',
        'lnstatus' => 'ACT',
        'principal' => 15000,
        'date_rel' => '2024-03-01',
    ]);

    $this
        ->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('autoFilledDeclarations.declaration_existing_loans', true)
            ->where('autoFilledDeclarations.declaration_pending_cases', false)
            ->where('autoFilledDeclarations.existing_loan_1_date', '2024-03-01')
            ->where('autoFilledDeclarations.existing_loan_1_type', 'Salary loan')
            ->where('autoFilledDeclarations.existing_loan_1_amount', 15000)
        );
});

test('IIL loan sets pending cases to true', function (): void {
    createWlnmasterFeatureTable();
    $member = createAutoFillMember('000001');

    createAutoFillLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-IIL',
        'lntype' => 'Emergency loan',
        'lnstatus' => 'IIL',
        'principal' => 20000,
        'date_rel' => '2024-05-10',
    ]);

    $this
        ->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('autoFilledDeclarations.declaration_existing_loans', true)
            ->where('autoFilledDeclarations.declaration_pending_cases', true)
        );
});

test('PDL loan does not flag pending cases', function (): void {
    createWlnmasterFeatureTable();
    $member = createAutoFillMember('000001');

    createAutoFillLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-PDL',
        'lntype' => 'Personal',
        'lnstatus' => 'PDL',
        'principal' => 8000,
        'date_rel' => '2024-02-20',
    ]);

    $this
        ->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('autoFilledDeclarations.declaration_existing_loans', true)
            ->where('autoFilledDeclarations.declaration_pending_cases', false)
        );
});

test('most recent loan by date_rel lands in slot 1', function (): void {
    createWlnmasterFeatureTable();
    $member = createAutoFillMember('000001');

    createAutoFillLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-OLD',
        'lntype' => 'Old loan',
        'lnstatus' => 'ACT',
        'principal' => 5000,
        'date_rel' => '2023-01-01',
    ]);
    createAutoFillLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-RECENT',
        'lntype' => 'Recent loan',
        'lnstatus' => 'ACT',
        'principal' => 30000,
        'date_rel' => '2024-12-25',
    ]);

    $this
        ->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('autoFilledDeclarations.existing_loan_1_date', '2024-12-25')
            ->where('autoFilledDeclarations.existing_loan_1_type', 'Recent loan')
            ->where('autoFilledDeclarations.existing_loan_1_amount', 30000)
        );
});

test('draft exists -> declarations are still recomputed from account records', function (): void {
    createWlnmasterFeatureTable();
    $member = createAutoFillMember('000001');

    createAutoFillLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-DRAFT',
        'lntype' => 'Salary loan',
        'lnstatus' => 'ACT',
        'principal' => 25000,
        'date_rel' => '2024-04-01',
    ]);

    LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::Draft,
        'acctno' => $member->acctno,
    ]);

    $this
        ->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('autoFilledDeclarations.declaration_existing_loans', true)
            ->where('autoFilledDeclarations.declaration_pending_cases', false)
            ->where('draft.status', LoanRequestStatus::Draft->value)
        );
});

test('wlnmaster table missing -> auto-fill defaults returned', function (): void {
    $member = createAutoFillMember('000001');

    $this
        ->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('autoFilledDeclarations.declaration_existing_loans', false)
            ->where('autoFilledDeclarations.declaration_pending_cases', false)
            ->where('autoFilledDeclarations.existing_loan_1_date', null)
        );
});

test('lnstatus column missing -> pending stays false but existing loan still filled', function (): void {
    createWlnmasterFeatureTable(withLnstatus: false);
    $member = createAutoFillMember('000001');

    createAutoFillLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-NO-STATUS',
        'lntype' => 'Personal',
        'lnstatus' => 'IIL',
        'principal' => 12000,
        'date_rel' => '2024-03-15',
    ]);

    $this
        ->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('autoFilledDeclarations.declaration_existing_loans', true)
            ->where('autoFilledDeclarations.declaration_pending_cases', false)
            ->where('autoFilledDeclarations.existing_loan_1_amount', 12000)
        );
});
