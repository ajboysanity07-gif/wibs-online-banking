<?php

use App\Models\AppUser;
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
        });
    }
});

function createConfirmFlowMember(string $acctno): AppUser
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

    return $member->fresh(['roles.permissions', 'userProfile']);
}

test('applicantPrefilledFromProfile is true when wmaster has the core fields on file', function (): void {
    $member = createConfirmFlowMember('006001');

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $member->acctno],
        [
            'fname' => 'Returning',
            'lname' => 'Member',
            'birthday' => '1990-01-01',
            'address' => '123 Main St, Sample City, Sample Province',
            'civilstat' => 'Single',
        ],
    );

    $this->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertInertia(fn ($page) => $page
            ->component('client/loan-request')
            ->where('applicantPrefilledFromProfile', true),
        );
});

test('applicantPrefilledFromProfile is false when there is no wmaster record on file', function (): void {
    $member = createConfirmFlowMember('006002');

    $this->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertInertia(fn ($page) => $page
            ->component('client/loan-request')
            ->where('applicantPrefilledFromProfile', false),
        );
});

test('applicantWorkIncomePrefilledFromProfile is true when the profile already has income details', function (): void {
    $member = createConfirmFlowMember('006003');

    $this->actingAs($member)
        ->get(route('client.loan-requests.create'))
        ->assertInertia(fn ($page) => $page
            ->component('client/loan-request')
            ->where('applicantWorkIncomePrefilledFromProfile', true),
        );
});
