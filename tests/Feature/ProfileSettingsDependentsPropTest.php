<?php

use App\Models\AppUser;
use App\Models\MemberApplicationProfile;
use App\Models\MemberDependentProfile;
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
            $table->string('telephone')->nullable();
        });
    }
});

test('profile settings page includes the saved spouse beneficiary flag', function (): void {
    $member = AppUser::factory()->create(['email_verified_at' => now()]);
    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $member->acctno],
        ['fname' => 'Spouse', 'lname' => 'Tester', 'birthday' => '1990-01-01', 'civilstat' => 'Married'],
    );

    $memberProfile = MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
        'user_id' => $member->user_id,
    ]);

    MemberDependentProfile::query()->create([
        'member_application_profile_id' => $memberProfile->id,
        'spouse_is_beneficiary' => true,
    ]);

    $this->actingAs($member->fresh(['roles.permissions', 'userProfile', 'memberApplicationProfile']));

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('dependents.dependent_spouse_is_beneficiary', true)
        );
});

test('profile settings page includes the wmaster telephone in the member record', function (): void {
    $member = AppUser::factory()->create(['email_verified_at' => now()]);
    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $member->acctno],
        [
            'fname' => 'Contact',
            'lname' => 'Tester',
            'birthday' => '1990-01-01',
            'civilstat' => 'Single',
            'telephone' => '09170000001',
        ],
    );

    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
        'user_id' => $member->user_id,
    ]);

    $this->actingAs($member->fresh(['roles.permissions', 'userProfile', 'memberApplicationProfile']));

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('memberRecord.telephone', '09170000001')
        );
});
