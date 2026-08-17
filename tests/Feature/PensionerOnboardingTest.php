<?php

use App\Models\AppUser as User;
use App\Models\MemberApplicationProfile;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table) {
            $table->string('acctno')->primary();
            $table->string('lname')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('bname')->nullable();
            $table->date('birthday')->nullable();
            $table->string('birthplace')->nullable();
            $table->string('address')->nullable();
            $table->string('civilstat')->nullable();
            $table->string('occupation')->nullable();
            $table->string('spouse')->nullable();
            $table->string('restype')->nullable();
            $table->string('dependent')->nullable();
        });
    }
});

// ---------------------------------------------------------------------------
// Unit: constant consistency
// ---------------------------------------------------------------------------

test('PENSIONER_EMPLOYMENT_TYPE constant has the expected value', function () {
    expect(MemberApplicationProfile::PENSIONER_EMPLOYMENT_TYPE)->toBe('Pensioner');
});

test('pensionerOptionalFields returns the four employer fields', function () {
    expect(MemberApplicationProfile::pensionerOptionalFields())
        ->toBe(['employer_business_name', 'employer_business_address_barangay', 'current_position', 'payday']);
});

// ---------------------------------------------------------------------------
// Unit: missingRequiredFields() — employment-type awareness
// ---------------------------------------------------------------------------

test('missingRequiredFields excludes employer fields for a pensioner with all other required fields filled', function () {
    $profile = MemberApplicationProfile::factory()->make([
        'employment_type' => MemberApplicationProfile::PENSIONER_EMPLOYMENT_TYPE,
        'birthplace_city' => 'Cebu City',
        'educational_attainment' => 'College',
        'length_of_stay' => '10 years',
        'home_address1' => 'Street',
        'home_address_barangay' => 'Barangay',
        'home_address2' => 'City',
        'home_address3' => 'Province',
        'civil_status' => 'Single',
        'housing_status' => 'OWNED',
        'release_method' => 'Cash',
        'gross_monthly_income' => '15000.00',
        // employer_business_name, current_position, payday intentionally blank
        'employer_business_name' => null,
        'employer_business_address_barangay' => null,
        'current_position' => null,
        'payday' => null,
    ]);

    expect($profile->missingRequiredFields())->toBe([]);
});

test('missingRequiredFields flags employer fields as missing for a Regular member without them', function () {
    $profile = MemberApplicationProfile::factory()->make([
        'employment_type' => 'Regular',
        'birthplace_city' => 'Cebu City',
        'educational_attainment' => 'College',
        'length_of_stay' => '10 years',
        'gross_monthly_income' => '15000.00',
        'employer_business_name' => null,
        'current_position' => null,
        'payday' => null,
    ]);

    $missing = $profile->missingRequiredFields();

    expect($missing)->toContain('employer_business_name');
    expect($missing)->toContain('current_position');
    expect($missing)->toContain('payday');
});

test('missingRequiredFields still requires gross_monthly_income for a pensioner', function () {
    $profile = MemberApplicationProfile::factory()->make([
        'employment_type' => MemberApplicationProfile::PENSIONER_EMPLOYMENT_TYPE,
        'birthplace_city' => 'Cebu City',
        'educational_attainment' => 'College',
        'length_of_stay' => '10 years',
        'gross_monthly_income' => null, // missing — pension is their income
        'employer_business_name' => null,
        'current_position' => null,
        'payday' => null,
    ]);

    expect($profile->missingRequiredFields())->toContain('gross_monthly_income');
});

// ---------------------------------------------------------------------------
// Feature: profile update endpoint
// ---------------------------------------------------------------------------

test('pensioner member completes onboarding without employer name, position, or payday', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'pensioner_juan',
            'email' => 'pensioner@example.com',
            'phoneno' => '09171234567',
            'birthplace_city' => 'Cebu City',
            'birthplace_province' => 'Cebu',
            'educational_attainment' => 'College',
            'length_of_stay' => '20 years',
            'home_address1' => 'Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'release_method' => 'Cash',
            'employment_type' => MemberApplicationProfile::PENSIONER_EMPLOYMENT_TYPE,
            'gross_monthly_income' => '12000',
            // employer_business_name, current_position, payday intentionally omitted
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('client.dashboard'));

    $profile = $user->fresh()->memberApplicationProfile;

    expect($profile)->not->toBeNull();
    expect($profile->employment_type)->toBe(MemberApplicationProfile::PENSIONER_EMPLOYMENT_TYPE);
    expect($profile->profile_completed_at)->not->toBeNull();
    expect($profile->missingRequiredFields())->toBe([]);
});

test('regular member still requires employer name, current position, and payday', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'regular_juan',
            'email' => 'regular@example.com',
            'phoneno' => '09171234568',
            'birthplace_city' => 'Cebu City',
            'birthplace_province' => 'Cebu',
            'educational_attainment' => 'College',
            'length_of_stay' => '5 years',
            'employment_type' => 'Regular',
            'gross_monthly_income' => '25000',
            // employer_business_name, current_position, payday intentionally omitted
        ]);

    $response->assertSessionHasErrors([
        'employer_business_name',
        'current_position',
        'payday',
    ]);
});

test('contract member still requires employer name, current position, and payday', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'contract_juan',
            'email' => 'contract@example.com',
            'phoneno' => '09171234569',
            'birthplace_city' => 'Cebu City',
            'birthplace_province' => 'Cebu',
            'educational_attainment' => 'College',
            'length_of_stay' => '3 years',
            'employment_type' => 'Contract',
            'gross_monthly_income' => '18000',
        ]);

    $response->assertSessionHasErrors([
        'employer_business_name',
        'current_position',
        'payday',
    ]);
});

test('pensioner profile page shows no employer fields in missing fields list', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);

    MemberApplicationProfile::factory()->create([
        'user_id' => $user->user_id,
        'employment_type' => MemberApplicationProfile::PENSIONER_EMPLOYMENT_TYPE,
        'birthplace_city' => 'Cebu City',
        'educational_attainment' => 'College',
        'length_of_stay' => '20 years',
        'home_address1' => 'Street',
        'home_address_barangay' => 'Barangay',
        'home_address2' => 'City',
        'home_address3' => 'Province',
        'civil_status' => 'Single',
        'housing_status' => 'OWNED',
        'release_method' => 'Cash',
        'gross_monthly_income' => '12000.00',
        'employer_business_name' => null,
        'employer_business_address_barangay' => null,
        'current_position' => null,
        'payday' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit', ['onboarding' => 1]));

    $response
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/profile')
            ->where('profileCompletion.isComplete', true)
            ->where('profileCompletion.missingFields', [])
        );
});

// ---------------------------------------------------------------------------
// Unit: AppUser — dashboard-unlock / profile-complete flag
// ---------------------------------------------------------------------------

test('memberApplicationProfileIsComplete returns true for pensioner with only the 4 required fields', function () {
    $user = User::factory()->create();
    $profile = MemberApplicationProfile::factory()->create([
        'user_id' => $user->user_id,
        'employment_type' => MemberApplicationProfile::PENSIONER_EMPLOYMENT_TYPE,
        'birthplace_city' => 'Cebu City',
        'educational_attainment' => 'College',
        'length_of_stay' => '10 years',
        'home_address1' => 'Street',
        'home_address_barangay' => 'Barangay',
        'home_address2' => 'City',
        'home_address3' => 'Province',
        'civil_status' => 'Single',
        'housing_status' => 'OWNED',
        'release_method' => 'Cash',
        'gross_monthly_income' => '15000.00',
        'employer_business_name' => null,
        'employer_business_address_barangay' => null,
        'current_position' => null,
        'payday' => null,
    ]);

    $user->setRelation('memberApplicationProfile', $profile);

    expect($user->memberApplicationProfileIsComplete())->toBeTrue();
    expect($user->missingMemberApplicationProfileFields())->toBe([]);
    expect($user->missingMemberApplicationProfileFieldLabels())->toBe([]);
});

test('memberApplicationProfileIsComplete returns false for pensioner missing gross_monthly_income and excludes employer fields from missing list', function () {
    $user = User::factory()->create();
    $profile = MemberApplicationProfile::factory()->create([
        'user_id' => $user->user_id,
        'employment_type' => MemberApplicationProfile::PENSIONER_EMPLOYMENT_TYPE,
        'birthplace_city' => 'Cebu City',
        'educational_attainment' => 'College',
        'length_of_stay' => '10 years',
        'gross_monthly_income' => null,
        'employer_business_name' => null,
        'current_position' => null,
        'payday' => null,
    ]);

    $user->setRelation('memberApplicationProfile', $profile);

    expect($user->memberApplicationProfileIsComplete())->toBeFalse();

    $missingFields = $user->missingMemberApplicationProfileFields();

    expect($missingFields)->toContain('gross_monthly_income');
    expect($missingFields)->not->toContain('employer_business_name');
    expect($missingFields)->not->toContain('current_position');
    expect($missingFields)->not->toContain('payday');
});

test('memberApplicationProfileIsComplete returns false for Regular member missing employer position and payday', function () {
    $user = User::factory()->create();
    $profile = MemberApplicationProfile::factory()->create([
        'user_id' => $user->user_id,
        'employment_type' => 'Regular',
        'birthplace_city' => 'Cebu City',
        'educational_attainment' => 'College',
        'length_of_stay' => '5 years',
        'gross_monthly_income' => '25000.00',
        'employer_business_name' => null,
        'current_position' => null,
        'payday' => null,
    ]);

    $user->setRelation('memberApplicationProfile', $profile);

    expect($user->memberApplicationProfileIsComplete())->toBeFalse();

    $missingFields = $user->missingMemberApplicationProfileFields();

    expect($missingFields)->toContain('employer_business_name');
    expect($missingFields)->toContain('current_position');
    expect($missingFields)->toContain('payday');
});

// ---------------------------------------------------------------------------
// Feature: serialized profileCompletion payload — incomplete paths
// ---------------------------------------------------------------------------

test('profileCompletion payload marks pensioner with missing gross_monthly_income as incomplete without flagging employer fields', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);

    MemberApplicationProfile::factory()->create([
        'user_id' => $user->user_id,
        'employment_type' => MemberApplicationProfile::PENSIONER_EMPLOYMENT_TYPE,
        'birthplace_city' => 'Cebu City',
        'educational_attainment' => 'College',
        'length_of_stay' => '10 years',
        'home_address1' => 'Street',
        'home_address_barangay' => 'Barangay',
        'home_address2' => 'City',
        'home_address3' => 'Province',
        'civil_status' => 'Single',
        'housing_status' => 'OWNED',
        'release_method' => 'Cash',
        'gross_monthly_income' => null,
        'employer_business_name' => null,
        'employer_business_address_barangay' => null,
        'current_position' => null,
        'payday' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit', ['onboarding' => 1]));

    $response
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/profile')
            ->where('profileCompletion.isComplete', false)
            ->where('profileCompletion.missingFields', ['Gross monthly income'])
        );
});

test('profileCompletion payload marks Regular member as incomplete with employer fields in missing list', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);

    MemberApplicationProfile::factory()->create([
        'user_id' => $user->user_id,
        'employment_type' => 'Regular',
        'birthplace_city' => 'Cebu City',
        'educational_attainment' => 'College',
        'length_of_stay' => '5 years',
        'home_address1' => 'Street',
        'home_address_barangay' => 'Barangay',
        'home_address2' => 'City',
        'home_address3' => 'Province',
        'civil_status' => 'Single',
        'housing_status' => 'OWNED',
        'release_method' => 'Cash',
        'gross_monthly_income' => '25000.00',
        'employer_business_name' => null,
        'employer_business_address_barangay' => null,
        'current_position' => null,
        'payday' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit', ['onboarding' => 1]));

    $response
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/profile')
            ->where('profileCompletion.isComplete', false)
            ->where('profileCompletion.missingFields', [
                'Employer or business name',
                'Employer address barangay',
                'Current position',
                'Payday',
            ])
        );
});
