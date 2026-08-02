<?php

use App\Models\AppUser;
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
});

function createDashboardLoanStatusMember(string $acctno): AppUser
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
        ['fname' => 'Dash', 'lname' => 'Member', 'bname' => 'Dash Member', 'birthday' => '1990-01-01', 'address' => 'Dash St'],
    );

    return $member->fresh(['roles.permissions', 'userProfile']);
}

test('dashboard passes empty loan summary when member has no loans', function (): void {
    if (! Schema::hasTable('wlnmaster')) {
        Schema::create('wlnmaster', function (Blueprint $table): void {
            $table->string('acctno');
            $table->string('lnnumber');
            $table->string('lntype')->nullable();
            $table->string('lnstatus')->nullable();
            $table->decimal('principal', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->date('date_rel')->nullable();
            $table->date('date_mat')->nullable();
            $table->dateTime('lastmove')->nullable();
        });
    }

    $member = createDashboardLoanStatusMember('000001');

    $this
        ->actingAs($member)
        ->get(route('client.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('loanSummary.total_loans', 0)
            ->where('loanSummary.total_balance', 0)
            ->where('loanSummary.active_count', 0)
            ->where('loanSummary.past_due_count', 0)
            ->where('loanSummary.litigation_count', 0)
            ->where('loanSummary.loans', [])
        );
});

test('dashboard loan summary groups active loans with balance totals', function (): void {
    if (! Schema::hasTable('wlnmaster')) {
        Schema::create('wlnmaster', function (Blueprint $table): void {
            $table->string('acctno');
            $table->string('lnnumber');
            $table->string('lntype')->nullable();
            $table->string('lnstatus')->nullable();
            $table->decimal('principal', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->date('date_rel')->nullable();
            $table->date('date_mat')->nullable();
            $table->dateTime('lastmove')->nullable();
        });
    }

    $member = createDashboardLoanStatusMember('000001');

    DB::table('wlnmaster')->insert([
        'acctno' => '000001',
        'lnnumber' => 'LN-D1',
        'lntype' => 'Salary loan',
        'lnstatus' => 'ACT',
        'principal' => 10000,
        'balance' => 7000,
        'date_rel' => '2024-01-15',
        'date_mat' => '2025-01-15',
        'lastmove' => '2024-06-01',
    ]);
    DB::table('wlnmaster')->insert([
        'acctno' => '000001',
        'lnnumber' => 'LN-D2',
        'lntype' => 'Personal',
        'lnstatus' => 'ACT',
        'principal' => 5000,
        'balance' => 3000,
        'date_rel' => '2024-03-01',
        'date_mat' => '2025-03-01',
        'lastmove' => '2024-07-01',
    ]);

    $this
        ->actingAs($member)
        ->get(route('client.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('loanSummary.total_loans', 2)
            ->where('loanSummary.active_count', 2)
            ->where('loanSummary.past_due_count', 0)
            ->where('loanSummary.litigation_count', 0)
            ->where('loanSummary.total_balance', 10000)
            ->where('loanSummary.active_balance', 10000)
            ->where('loanSummary.loans.0.lnstatus', 'ACT')
        );
});

test('dashboard loan summary flags past due and litigation loans', function (): void {
    if (! Schema::hasTable('wlnmaster')) {
        Schema::create('wlnmaster', function (Blueprint $table): void {
            $table->string('acctno');
            $table->string('lnnumber');
            $table->string('lntype')->nullable();
            $table->string('lnstatus')->nullable();
            $table->decimal('principal', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->date('date_rel')->nullable();
            $table->date('date_mat')->nullable();
            $table->dateTime('lastmove')->nullable();
        });
    }

    $member = createDashboardLoanStatusMember('000001');

    DB::table('wlnmaster')->insert([
        'acctno' => '000001',
        'lnnumber' => 'LN-PDL-D',
        'lntype' => 'Personal',
        'lnstatus' => 'PDL',
        'principal' => 12000,
        'balance' => 9000,
        'date_rel' => '2024-02-10',
        'date_mat' => '2025-02-10',
        'lastmove' => '2024-08-01',
    ]);
    DB::table('wlnmaster')->insert([
        'acctno' => '000001',
        'lnnumber' => 'LN-IIL-D',
        'lntype' => 'Emergency loan',
        'lnstatus' => 'IIL',
        'principal' => 20000,
        'balance' => 16000,
        'date_rel' => '2024-05-20',
        'date_mat' => '2025-05-20',
        'lastmove' => '2024-09-01',
    ]);

    $this
        ->actingAs($member)
        ->get(route('client.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('loanSummary.total_loans', 2)
            ->where('loanSummary.past_due_count', 1)
            ->where('loanSummary.litigation_count', 1)
            ->where('loanSummary.past_due_balance', 9000)
            ->where('loanSummary.litigation_balance', 16000)
            ->where('loanSummary.loans.0.lnstatus', 'IIL')
        );
});

test('dashboard loan summary is null when member has no acctno', function (): void {
    $member = AppUser::factory()->create([
        'acctno' => null,
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->create(['user_id' => $member->user_id]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => '000001'],
        ['fname' => 'Dash', 'lname' => 'Member', 'bname' => 'Dash Member', 'birthday' => '1990-01-01', 'address' => 'Dash St'],
    );

    $this
        ->actingAs($member)
        ->get(route('client.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('loanSummary', null)
        );
});
