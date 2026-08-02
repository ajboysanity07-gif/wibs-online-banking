<?php

uses(Tests\TestCase::class);

use App\Models\AppUser;
use App\Models\Wlnmaster;
use App\Services\LoanRequests\LoanDeclarationAutoFillService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

function createAppusersTestTable(): void
{
    if (Schema::hasTable('appusers')) {
        return;
    }

    Schema::create('appusers', function (Blueprint $table): void {
        $table->id('user_id');
        $table->string('acctno', 6)->nullable();
        $table->string('email')->unique();
        $table->string('username')->unique();
        $table->string('phoneno', 11)->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->rememberToken();
        $table->string('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->timestamps();
    });
}

function createWlnmasterTestTable(bool $withLnstatus = true, bool $withDateRel = true): void
{
    if (Schema::hasTable('wlnmaster')) {
        Schema::drop('wlnmaster');
    }

    Schema::create('wlnmaster', function (Blueprint $table) use ($withLnstatus, $withDateRel): void {
        $table->string('acctno');
        $table->string('lnnumber')->primary();
        $table->string('lntype')->nullable();
        if ($withLnstatus) {
            $table->string('lnstatus')->nullable();
        }
        $table->decimal('principal', 12, 2)->nullable();
        $table->decimal('balance', 12, 2)->nullable();
        if ($withDateRel) {
            $table->date('date_rel')->nullable();
            $table->date('date_mat')->nullable();
        }
        $table->date('lastmove')->nullable();
    });
}

function createWlnmasterTestUser(string $acctno, ?string $withAcctno = null): AppUser
{
    createAppusersTestTable();

    return AppUser::factory()->create([
        'acctno' => $withAcctno ?? $acctno,
    ]);
}

function createWlnmasterTestLoan(array $attributes): Wlnmaster
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

    return Wlnmaster::create($values);
}

function makeLoanDeclarationAutoFillService(): LoanDeclarationAutoFillService
{
    return app(LoanDeclarationAutoFillService::class);
}

test('no loans -> all false/null', function (): void {
    createWlnmasterTestTable();
    $user = createWlnmasterTestUser('000001');

    $data = makeLoanDeclarationAutoFillService()->getDeclarationData($user);

    expect($data['declaration_existing_loans'])->toBeFalse();
    expect($data['declaration_pending_cases'])->toBeFalse();
    expect($data['existing_loan_1_date'])->toBeNull();
    expect($data['existing_loan_1_type'])->toBeNull();
    expect($data['existing_loan_1_amount'])->toBeNull();
});

test('1 ACT loan -> existing=true, cases=false, slot 1 filled', function (): void {
    createWlnmasterTestTable();
    $user = createWlnmasterTestUser('000001');

    createWlnmasterTestLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-ACT-1',
        'lntype' => 'Personal',
        'lnstatus' => 'ACT',
        'principal' => 25000,
        'date_rel' => '2023-05-10',
    ]);

    $data = makeLoanDeclarationAutoFillService()->getDeclarationData($user);

    expect($data['declaration_existing_loans'])->toBeTrue();
    expect($data['declaration_pending_cases'])->toBeFalse();
    expect($data['existing_loan_1_date'])->toBe('2023-05-10');
    expect($data['existing_loan_1_type'])->toBe('Personal');
    expect($data['existing_loan_1_amount'])->toBe(25000.0);
});

test('1 IIL loan -> both true, slot 1 filled', function (): void {
    createWlnmasterTestTable();
    $user = createWlnmasterTestUser('000001');

    createWlnmasterTestLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-IIL-1',
        'lntype' => 'Salary',
        'lnstatus' => 'IIL',
        'principal' => 40000,
        'date_rel' => '2022-02-20',
    ]);

    $data = makeLoanDeclarationAutoFillService()->getDeclarationData($user);

    expect($data['declaration_existing_loans'])->toBeTrue();
    expect($data['declaration_pending_cases'])->toBeTrue();
    expect($data['existing_loan_1_date'])->toBe('2022-02-20');
    expect($data['existing_loan_1_type'])->toBe('Salary');
    expect($data['existing_loan_1_amount'])->toBe(40000.0);
});

test('1 PDL loan -> existing=true, cases=false', function (): void {
    createWlnmasterTestTable();
    $user = createWlnmasterTestUser('000001');

    createWlnmasterTestLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-PDL-1',
        'lnstatus' => 'PDL',
        'principal' => 18000,
        'date_rel' => '2021-08-01',
    ]);

    $data = makeLoanDeclarationAutoFillService()->getDeclarationData($user);

    expect($data['declaration_existing_loans'])->toBeTrue();
    expect($data['declaration_pending_cases'])->toBeFalse();
    expect($data['existing_loan_1_amount'])->toBe(18000.0);
});

test('3 loans -> picks most recent by date_rel', function (): void {
    createWlnmasterTestTable();
    $user = createWlnmasterTestUser('000001');

    createWlnmasterTestLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-OLD',
        'lntype' => 'Old Loan',
        'principal' => 1000,
        'date_rel' => '2020-01-01',
    ]);
    createWlnmasterTestLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-MID',
        'lntype' => 'Mid Loan',
        'principal' => 2000,
        'date_rel' => '2022-01-01',
    ]);
    createWlnmasterTestLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-NEW',
        'lntype' => 'New Loan',
        'principal' => 3000,
        'date_rel' => '2024-01-01',
    ]);

    $data = makeLoanDeclarationAutoFillService()->getDeclarationData($user);

    expect($data['existing_loan_1_date'])->toBe('2024-01-01');
    expect($data['existing_loan_1_type'])->toBe('New Loan');
    expect($data['existing_loan_1_amount'])->toBe(3000.0);
});

test('no acctno -> empty data', function (): void {
    createWlnmasterTestTable();
    $user = createWlnmasterTestUser('', null);

    $data = makeLoanDeclarationAutoFillService()->getDeclarationData($user);

    expect($data['declaration_existing_loans'])->toBeFalse();
    expect($data['declaration_pending_cases'])->toBeFalse();
    expect($data['existing_loan_1_date'])->toBeNull();
});

test('wlnmaster table missing -> empty data', function (): void {
    if (Schema::hasTable('wlnmaster')) {
        Schema::drop('wlnmaster');
    }

    $user = createWlnmasterTestUser('000001');

    $data = makeLoanDeclarationAutoFillService()->getDeclarationData($user);

    expect($data['declaration_existing_loans'])->toBeFalse();
    expect($data['declaration_pending_cases'])->toBeFalse();
    expect($data['existing_loan_1_amount'])->toBeNull();
});

test('lnstatus column missing -> pending_cases=false, existing still works', function (): void {
    createWlnmasterTestTable(withLnstatus: false);
    $user = createWlnmasterTestUser('000001');

    createWlnmasterTestLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-NO-STATUS',
        'lntype' => 'Personal',
        'principal' => 15000,
        'date_rel' => '2024-03-01',
    ]);

    $data = makeLoanDeclarationAutoFillService()->getDeclarationData($user);

    expect($data['declaration_existing_loans'])->toBeTrue();
    expect($data['declaration_pending_cases'])->toBeFalse();
    expect($data['existing_loan_1_amount'])->toBe(15000.0);
});

test('date_rel missing -> fallback to lastmove', function (): void {
    createWlnmasterTestTable(withDateRel: false);
    $user = createWlnmasterTestUser('000001');

    createWlnmasterTestLoan([
        'acctno' => '000001',
        'lnnumber' => 'LN-LASTMOVE',
        'lntype' => 'Emergency',
        'principal' => 9000,
        'lastmove' => '2024-09-09',
    ]);

    $data = makeLoanDeclarationAutoFillService()->getDeclarationData($user);

    expect($data['existing_loan_1_date'])->toBe('2024-09-09');
    expect($data['existing_loan_1_type'])->toBe('Emergency');
});

test('staff summary counts ACT/PDL/IIL correctly', function (): void {
    createWlnmasterTestTable();
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'A', 'lnstatus' => 'ACT']);
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'B', 'lnstatus' => 'ACT']);
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'C', 'lnstatus' => 'PDL']);
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'D', 'lnstatus' => 'IIL']);

    $summary = makeLoanDeclarationAutoFillService()->getLoanStatusSummaryForStaff('000001');

    expect($summary['total_active'])->toBe(2);
    expect($summary['total_past_due'])->toBe(1);
    expect($summary['total_litigation'])->toBe(1);
    expect($summary['has_active'])->toBeTrue();
    expect($summary['has_past_due'])->toBeTrue();
    expect($summary['has_litigation'])->toBeTrue();
});

test('staff summary sums balances correctly', function (): void {
    createWlnmasterTestTable();
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'A', 'lnstatus' => 'ACT', 'balance' => 1000]);
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'B', 'lnstatus' => 'PDL', 'balance' => 2000.5]);
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'C', 'lnstatus' => 'IIL', 'balance' => 3000.25]);

    $summary = makeLoanDeclarationAutoFillService()->getLoanStatusSummaryForStaff('000001');

    expect($summary['active_balance_total'])->toBe(1000.0);
    expect($summary['past_due_balance_total'])->toBe(2000.5);
    expect($summary['litigation_balance_total'])->toBe(3000.25);
});

test('requires_attention true when PDL or IIL present', function (): void {
    createWlnmasterTestTable();
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'A', 'lnstatus' => 'ACT']);

    $clean = makeLoanDeclarationAutoFillService()->getLoanStatusSummaryForStaff('000001');
    expect($clean['requires_attention'])->toBeFalse();

    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'B', 'lnstatus' => 'PDL']);

    $pastDue = makeLoanDeclarationAutoFillService()->getLoanStatusSummaryForStaff('000001');
    expect($pastDue['requires_attention'])->toBeTrue();
});

test('warning_message formatting', function (): void {
    createWlnmasterTestTable();

    $onlyPastDue = makeLoanDeclarationAutoFillService()->getLoanStatusSummaryForStaff('000000');
    expect($onlyPastDue['warning_message'])->toBeNull();

    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'B', 'lnstatus' => 'PDL']);

    $one = makeLoanDeclarationAutoFillService()->getLoanStatusSummaryForStaff('000001');
    expect($one['warning_message'])->toBe('Applicant has 1 past due loan(s)');

    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'C', 'lnstatus' => 'IIL']);

    $both = makeLoanDeclarationAutoFillService()->getLoanStatusSummaryForStaff('000001');
    expect($both['warning_message'])->toBe(
        'Applicant has 1 past due loan(s) and 1 loan(s) in litigation',
    );
});

test('getProblemLoans returns only PDL/IIL', function (): void {
    createWlnmasterTestTable();
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'A', 'lnstatus' => 'ACT']);
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'B', 'lnstatus' => 'PDL']);
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'C', 'lnstatus' => 'IIL']);

    $loans = makeLoanDeclarationAutoFillService()->getProblemLoans('000001');

    expect($loans)->toHaveCount(2);
    expect(array_column($loans, 'lnstatus'))->toContain('PDL');
    expect(array_column($loans, 'lnstatus'))->toContain('IIL');
    expect(array_column($loans, 'lnstatus'))->not->toContain('ACT');
});

test('getProblemLoans sort order (IIL first, then date_rel DESC)', function (): void {
    createWlnmasterTestTable();
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'PDL-OLD', 'lnstatus' => 'PDL', 'date_rel' => '2021-01-01']);
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'IIL-OLD', 'lnstatus' => 'IIL', 'date_rel' => '2020-01-01']);
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'PDL-NEW', 'lnstatus' => 'PDL', 'date_rel' => '2023-01-01']);

    $loans = makeLoanDeclarationAutoFillService()->getProblemLoans('000001');

    expect(array_column($loans, 'lnnumber'))->toBe(['IIL-OLD', 'PDL-NEW', 'PDL-OLD']);
});

test('member summary complete with all loans', function (): void {
    createWlnmasterTestTable();
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'A', 'lnstatus' => 'ACT', 'balance' => 500]);
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'B', 'lnstatus' => 'PDL', 'balance' => 700]);

    $summary = makeLoanDeclarationAutoFillService()->getLoanStatusSummaryForMember('000001');

    expect($summary['total_loans'])->toBe(2);
    expect($summary['active_count'])->toBe(1);
    expect($summary['past_due_count'])->toBe(1);
    expect($summary['litigation_count'])->toBe(0);
    expect($summary['total_balance'])->toBe(1200.0);
    expect($summary['loans'])->toHaveCount(2);
});

test('member summary categorizes loans by status', function (): void {
    createWlnmasterTestTable();
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'A', 'lnstatus' => 'ACT']);
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'B', 'lnstatus' => 'PDL']);
    createWlnmasterTestLoan(['acctno' => '000001', 'lnnumber' => 'C', 'lnstatus' => 'IIL']);

    $summary = makeLoanDeclarationAutoFillService()->getLoanStatusSummaryForMember('000001');

    expect($summary['active_count'])->toBe(1);
    expect($summary['past_due_count'])->toBe(1);
    expect($summary['litigation_count'])->toBe(1);

    $statuses = array_column($summary['loans'], 'lnstatus');
    expect($statuses)->toContain('ACT');
    expect($statuses)->toContain('PDL');
    expect($statuses)->toContain('IIL');
});
