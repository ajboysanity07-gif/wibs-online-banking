<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\MemberApplicationProfile;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

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
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('address3')->nullable();
            $table->string('address4')->nullable();
            $table->string('zone_number')->nullable();
            $table->string('civilstat')->nullable();
            $table->string('occupation')->nullable();
            $table->string('spouse')->nullable();
            $table->string('restype')->nullable();
            $table->string('dependent')->nullable();
        });
    }
});

test('profile page is displayed', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('initialTab', 'profile')
            ->where('adminProfile', null)
        );
});

test('profile page includes completion details for incomplete profiles', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit', ['onboarding' => 1]));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('onboarding', true)
            ->where('profileCompletion.isComplete', false)
            ->where(
                'profileCompletion.missingFields',
                fn ($value) => collect($value)->contains('Birthplace city')
                    && collect($value)->contains('Payday'),
            ));
});

test('incomplete profile updates stay on onboarding with missing fields', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit', ['onboarding' => 1]))
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'Cebu City',
        ]);

    $response
        ->assertSessionHasErrors()
        ->assertRedirect(route('profile.edit', ['onboarding' => 1]));

    $page = $this
        ->actingAs($user)
        ->get(route('profile.edit', ['onboarding' => 1]));

    $page->assertInertia(fn (Assert $page) => $page
        ->component('settings/profile')
        ->where('profileCompletion.isComplete', false)
        ->where(
            'profileCompletion.missingFields',
            fn ($value) => collect($value)->contains('Educational attainment')
                && collect($value)->contains('Payday'),
        ));
});

test('profile update rejects a fabricated birthplace city and province', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'Not A Real City',
            'birthplace_province' => 'Not A Real Province',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Barangay Poblacion',
            'home_address2' => 'Tagum City',
            'home_address3' => 'Davao del Norte',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
        ]);

    $response->assertSessionHasErrors(['birthplace_city', 'birthplace_province']);

    expect($user->refresh()->memberApplicationProfile)->toBeNull();
});

test('profile update accepts a real birthplace city and province', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'City of Batac',
            'birthplace_province' => 'Ilocos Norte',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response->assertSessionHasNoErrors();

    $memberProfile = $user->refresh()->memberApplicationProfile;

    expect($memberProfile)->not->toBeNull();
    expect($memberProfile->birthplace_city)->toBe('City of Batac');
    expect($memberProfile->birthplace_province)->toBe('Ilocos Norte');
});

test('profile update rejects salary deduction for a non-institutional employer', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'City of Batac',
            'birthplace_province' => 'Ilocos Norte',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'release_method' => 'Cash',
            'payment_option' => 'Salary Deduction',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response->assertSessionHasErrors(['payment_option']);
});

test('profile update accepts salary deduction for an institutional (MRDINC) employer', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'City of Batac',
            'birthplace_province' => 'Ilocos Norte',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'MRDINC Head Office',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'release_method' => 'Cash',
            'payment_option' => 'Salary Deduction',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response->assertSessionDoesntHaveErrors(['payment_option']);
});

test('profile update accepts an optional real birthplace barangay', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'City of Batac',
            'birthplace_province' => 'Ilocos Norte',
            'birthplace_barangay' => 'Aglipay',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response->assertSessionHasNoErrors();

    $memberProfile = $user->refresh()->memberApplicationProfile;

    expect($memberProfile)->not->toBeNull();
    expect($memberProfile->birthplace_barangay)->toBe('Aglipay');
});

test('profile update rejects a fabricated birthplace barangay', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'City of Batac',
            'birthplace_province' => 'Ilocos Norte',
            'birthplace_barangay' => 'Not A Real Barangay',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
        ]);

    $response->assertSessionHasErrors(['birthplace_barangay']);

    expect($user->refresh()->memberApplicationProfile)->toBeNull();
});

test('profile update leaves birthplace barangay blank without error', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'City of Batac',
            'birthplace_province' => 'Ilocos Norte',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response->assertSessionHasNoErrors();

    $memberProfile = $user->refresh()->memberApplicationProfile;

    expect($memberProfile)->not->toBeNull();
    expect($memberProfile->birthplace_barangay)->toBeNull();
});

test('admin profile page is displayed', function () {
    $user = User::factory()->create([
        'acctno' => null,
    ]);
    AdminProfile::factory()->create([
        'user_id' => $user->user_id,
        'fullname' => 'Admin Account',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('initialTab', 'profile')
            ->where('adminProfile.fullname', 'Admin Account')
            ->where('memberRecord', null)
        );
});

test('profile page includes member data for admin members', function () {
    $user = User::factory()->create([
        'acctno' => '001201',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);
    AdminProfile::factory()->create([
        'user_id' => $user->user_id,
        'fullname' => 'Hybrid Admin',
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Member, Hybrid',
        'fname' => 'Hybrid',
        'lname' => 'Member',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('adminProfile.fullname', 'Hybrid Admin')
            ->where('memberRecord.bname', 'Member, Hybrid')
        );
});

test('profile page loads member record information from wmaster', function () {
    $user = User::factory()->create([
        'acctno' => '000901',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Santos, Maria',
        'fname' => 'Maria',
        'lname' => 'Santos',
        'mname' => 'L',
        'birthday' => '1991-04-12',
        'birthplace' => 'Quezon City',
        'address' => 'Legacy Address',
        'address1' => '123 Mabini Street',
        'address3' => 'Manila',
        'address4' => 'Metro Manila',
        'zone_number' => '1000',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
        'spouse' => 'Miguel Santos',
        'restype' => 'Owned',
        'dependent' => '2',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('memberRecord.bname', 'Santos, Maria')
            ->where('memberRecord.fname', 'Maria')
            ->where('memberRecord.lname', 'Santos')
            ->where('memberRecord.mname', 'L')
            ->where('memberRecord.birthplace', 'Quezon City')
            ->where('memberRecord.birthplace_city', 'City of Quezon')
            ->where('memberRecord.birthplace_province', null)
            ->where('memberRecord.birthday', '1991-04-12')
            ->where('memberRecord.address', 'Legacy Address')
            ->where('memberRecord.address1', '123 Mabini Street')
            ->where('memberRecord.address2', 'City of Manila')
            ->where('memberRecord.address3', 'Metro Manila')
            ->where('memberRecord.zip_code', '1000')
            ->where(
                'memberRecord.display_address',
                '123 Mabini Street, City of Manila, Metro Manila',
            )
            ->where('memberRecord.civilstat', 'Single')
            ->where('memberRecord.occupation', 'Analyst')
            ->where('memberRecord.spouse_name', 'Miguel Santos')
            ->where('memberRecord.housing_status', 'Owned')
            ->where('memberRecord.number_of_children', '2')
            ->where('memberRecord.hasStructuredName', true)
        );
});

test('profile page normalizes ALL CAPS and abbreviated wmaster address into canonical PSGC form', function () {
    $user = User::factory()->create([
        'acctno' => '000908',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Reyes, Carlo',
        'birthplace' => 'TAGUM CITY, DDN',
        'address' => 'LEGACY',
        'address2' => 'MAGUGPO POBLACION',
        'address3' => 'TAGUM CITY',
        'address4' => 'DDN',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('memberRecord.barangay', 'Magugpo Poblacion')
            ->where('memberRecord.barangay_raw', 'MAGUGPO POBLACION')
            ->where('memberRecord.address2', 'City of Tagum')
            ->where('memberRecord.address2_raw', 'TAGUM CITY')
            ->where('memberRecord.address3', 'Davao del Norte')
            ->where('memberRecord.address3_raw', 'DDN')
            ->where('memberRecord.birthplace_city', 'City of Tagum')
            ->where('memberRecord.birthplace_province', 'Davao del Norte')
        );
});

test('profile page best-effort title-cases an unrecognized wmaster province code', function () {
    $user = User::factory()->create([
        'acctno' => '000909',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Reyes, Nina',
        'address' => 'LEGACY',
        'address3' => 'SOME CITY',
        'address4' => 'XYZ',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('memberRecord.address3', 'Xyz')
        );
});

test('profile page credits home address, spouse name, and birthplace city fields sourced from wmaster', function () {
    $user = User::factory()->create([
        'acctno' => '000910',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Reyes, Elena',
        'birthplace' => 'TAGUM CITY, DDN',
        'address' => 'LEGACY',
        'address2' => 'MAGUGPO POBLACION',
        'address3' => 'TAGUM CITY',
        'address4' => 'DDN',
        'civilstat' => 'Married',
        'restype' => 'OWNED',
        'spouse' => 'Renee Santos',
    ]);

    MemberApplicationProfile::factory()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where(
                'profileCompletion.missingFieldKeys',
                fn ($keys) => ! collect($keys)->contains('civil_status')
                    && ! collect($keys)->contains('housing_status')
                    && ! collect($keys)->contains('spouse_name')
                    && ! collect($keys)->contains('birthplace_city')
                    && ! collect($keys)->contains('home_address_barangay')
                    && ! collect($keys)->contains('home_address2')
                    && ! collect($keys)->contains('home_address3')
                    && collect($keys)->contains('spouse_birthdate'),
            )
        );
});

test('profile can be saved when spouse name is locked by wmaster and civil status is not single', function () {
    $user = User::factory()->create([
        'acctno' => '000911',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Santos, Renee',
        'civilstat' => 'Married',
        'restype' => 'OWNED',
        'spouse' => 'Miguel Santos',
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'City of Batac',
            'birthplace_province' => 'Ilocos Norte',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'spouse_birthdate' => '1992-05-14',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('client.dashboard'));

    $memberProfile = $user->refresh()->memberApplicationProfile;

    expect($memberProfile->spouse_name)->toBeNull();
    expect($memberProfile->spouse_birthdate->toDateString())->toBe('1992-05-14');
});

test('profile page falls back to legacy address when structured address is missing', function () {
    $user = User::factory()->create([
        'acctno' => '000907',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Dela Cruz, Tina',
        'address' => 'Legacy Address Only',
        'address2' => null,
        'address3' => null,
        'address4' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('memberRecord.address1', null)
            ->where('memberRecord.address2', null)
            ->where('memberRecord.address3', null)
            ->where('memberRecord.display_address', 'Legacy Address Only')
        );
});

test('profile page exposes wmaster birthplace data', function () {
    $user = User::factory()->create([
        'acctno' => '000903',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Lopez, Jana',
        'fname' => 'Jana',
        'lname' => 'Lopez',
        'birthday' => '1993-09-14',
        'birthplace' => 'Bacolod City',
        'address' => '789 Mabini Street',
        'civilstat' => 'Single',
        'occupation' => 'Clerk',
        'spouse' => null,
        'restype' => null,
        'dependent' => null,
    ]);

    MemberApplicationProfile::factory()->create([
        'user_id' => $user->user_id,
        'birthplace' => 'Davao City',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('memberApplicationProfile.birthplace', 'Davao City')
            ->where('memberApplicationProfile.birthplace_city', 'Davao City')
            ->where('memberApplicationProfile.birthplace_province', null)
            ->where('memberRecord.birthplace', 'Bacolod City')
            ->where('memberRecord.birthplace_city', 'City of Bacolod')
            ->where('memberRecord.birthplace_province', null)
        );
});

test('profile page exposes editable spouse name when wmaster spouse is missing', function () {
    $user = User::factory()->create([
        'acctno' => '000905',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Lopez, Anne',
        'fname' => 'Anne',
        'lname' => 'Lopez',
        'birthday' => '1992-06-08',
        'birthplace' => null,
        'address' => 'Main Street',
        'civilstat' => 'Married',
        'occupation' => 'Clerk',
        'spouse' => null,
    ]);

    MemberApplicationProfile::factory()->create([
        'user_id' => $user->user_id,
        'spouse_name' => 'Alex Lopez',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('memberRecord.spouse_name', null)
            ->where('memberApplicationProfile.spouse_name', 'Alex Lopez')
        );
});

test('profile page prefers profile birthplace when wmaster birthplace is missing', function () {
    $user = User::factory()->create([
        'acctno' => '000906',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Santos, Lea',
        'fname' => 'Lea',
        'lname' => 'Santos',
        'birthday' => '1991-11-02',
        'birthplace' => null,
        'address' => 'First Street',
        'civilstat' => 'Single',
        'occupation' => 'Clerk',
        'spouse' => null,
    ]);

    MemberApplicationProfile::factory()->create([
        'user_id' => $user->user_id,
        'birthplace' => 'Cagayan de Oro',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('memberRecord.birthplace', null)
            ->where('memberApplicationProfile.birthplace', 'Cagayan de Oro')
            ->where('memberApplicationProfile.birthplace_city', 'Cagayan de Oro')
            ->where('memberApplicationProfile.birthplace_province', null)
        );
});

test('profile page hides structured member name fields when only full name is available', function () {
    $user = User::factory()->create([
        'acctno' => '000902',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Garcia, Liza',
        'fname' => null,
        'lname' => null,
        'mname' => null,
        'birthday' => '1992-07-08',
        'address' => '456 Mabini Street',
        'civilstat' => 'Single',
        'occupation' => 'Clerk',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('memberRecord.bname', 'Garcia, Liza')
            ->where('memberRecord.fname', null)
            ->where('memberRecord.mname', null)
            ->where('memberRecord.lname', null)
            ->where('memberRecord.hasStructuredName', false)
        );
});

test('profile page exposes payout bank fields', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    MemberApplicationProfile::factory()->create([
        'user_id' => $user->user_id,
        'payout_bank_name' => 'BDO',
        'payout_account_name' => 'Renee Santos',
        'payout_account_number' => '1234567890',
        'payout_account_type' => 'Savings',
        'release_method' => 'Bank deposit',
        'payout_atm_number' => '5555444433332222',
        'payout_bank_branch' => 'Tagum City',
        'payout_atm_holder_name' => null,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('memberApplicationProfile.payout_bank_name', 'BDO')
            ->where('memberApplicationProfile.payout_account_name', 'Renee Santos')
            ->where('memberApplicationProfile.payout_account_number', '1234567890')
            ->where('memberApplicationProfile.payout_account_type', 'Savings')
            ->where('memberApplicationProfile.release_method', 'Bank deposit')
            ->where('memberApplicationProfile.payout_atm_number', '5555444433332222')
            ->where('memberApplicationProfile.payout_bank_branch', 'Tagum City')
            ->where('memberApplicationProfile.payout_atm_holder_name', null)
        );
});

test('profile information can be updated with payout bank details', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'Cebu City',
            'birthplace_province' => 'Cebu',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Bank Transfer',
            'payout_atm_number' => '5555444433332222',
            'payout_bank_branch' => 'Tagum City',
            'payout_atm_holder_name' => 'Test User',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('client.dashboard'));

    $memberProfile = $user->refresh()->memberApplicationProfile;

    expect($memberProfile)->not->toBeNull();
    expect($memberProfile->payout_bank_name)->toBe('BDO');
    expect($memberProfile->payout_account_name)->toBe('Test User');
    expect($memberProfile->payout_account_number)->toBe('1234567890');
    expect($memberProfile->payout_account_type)->toBe('Savings');
    expect($memberProfile->release_method)->toBe('Bank Transfer');
    expect($memberProfile->payout_atm_number)->toBe('5555444433332222');
    expect($memberProfile->payout_bank_branch)->toBe('Tagum City');
    expect($memberProfile->payout_atm_holder_name)->toBe('Test User');
});

test('optional bank details can be saved even when release method is not bank transfer', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'Cebu City',
            'birthplace_province' => 'Cebu',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'release_method' => 'ATM',
            'payout_atm_number' => '5555444433332222',
            'payout_atm_holder_name' => 'Test User',
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response->assertSessionHasNoErrors();

    $memberProfile = $user->refresh()->memberApplicationProfile;

    expect($memberProfile)->not->toBeNull();
    expect($memberProfile->release_method)->toBe('ATM');
    expect($memberProfile->payout_bank_name)->toBe('BDO');
    expect($memberProfile->payout_account_name)->toBe('Test User');
    expect($memberProfile->payout_account_number)->toBe('1234567890');
    expect($memberProfile->payout_account_type)->toBe('Savings');
});

test('profile information can be updated with height and weight', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'Cebu City',
            'birthplace_province' => 'Cebu',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address1' => 'Acme Street',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('client.dashboard'));

    $memberProfile = $user->refresh()->memberApplicationProfile;

    expect($memberProfile)->not->toBeNull();
    expect($memberProfile->height_cm)->toBe('165');
    expect($memberProfile->weight_kg)->toBe('68');
});

test('profile page exposes source of fund and government id fields', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    MemberApplicationProfile::factory()->create([
        'user_id' => $user->user_id,
        'source_of_fund_wealth' => 'Salary',
        'id_type' => 'TIN',
        'id_type_other' => null,
        'id_number' => '123-456-789',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('memberApplicationProfile.source_of_fund_wealth', 'Salary')
            ->where('memberApplicationProfile.id_type', 'TIN')
            ->where('memberApplicationProfile.id_type_other', null)
            ->where('memberApplicationProfile.id_number', '123-456-789')
        );
});

test('profile information can be updated with source of fund and government id details', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'Cebu City',
            'birthplace_province' => 'Cebu',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Business income',
            'id_type' => 'Others',
            'id_type_other' => 'Voter\'s ID',
            'id_number' => '987-654-321',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('client.dashboard'));

    $memberProfile = $user->refresh()->memberApplicationProfile;

    expect($memberProfile)->not->toBeNull();
    expect($memberProfile->source_of_fund_wealth)->toBe('Business income');
    expect($memberProfile->id_type)->toBe('Others');
    expect($memberProfile->id_type_other)->toBe('Voter\'s ID');
    expect($memberProfile->id_number)->toBe('987-654-321');
});

test('id type other is required when id type is Others', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'id_type' => 'Others',
            'id_type_other' => '',
        ]);

    $response->assertSessionHasErrors(['id_type_other']);
});

test('onboarding is not blocked by empty source of fund or government id fields', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'Cebu City',
            'birthplace_province' => 'Cebu',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Cash',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('client.dashboard'));

    $user->refresh();
    expect($user->memberApplicationProfileIsComplete())->toBeTrue();
});

test('profile page exposes saved dependents but not cycle status/number', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $memberProfile = MemberApplicationProfile::factory()->create([
        'user_id' => $user->user_id,
    ]);

    $dependentProfile = \App\Models\MemberDependentProfile::query()->create([
        'member_application_profile_id' => $memberProfile->id,
    ]);

    \App\Models\MemberDependent::query()->create([
        'member_dependent_profile_id' => $dependentProfile->id,
        'category' => 'sibling',
        'slot' => 1,
        'name' => 'Settings Sibling',
        'birthdate' => '1996-02-14',
        'cycle_status' => 'Old',
        'cycle_number' => 3,
    ]);

    $dependentProfile->update([
        'spouse_cycle_status' => 'New',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('dependents.dependent_sibling_1_name', 'Settings Sibling')
            ->where('dependents.dependent_sibling_1_birthdate', '1996-02-14')
            ->missing('dependents.dependent_sibling_1_cycle_status')
            ->missing('dependents.dependent_sibling_1_cycle_number')
            ->missing('dependents.dependent_spouse_cycle_status')
            ->missing('dependents.dependent_spouse_cycle_number')
            ->where('dependents.dependent_child_1_name', null)
        );
});

test('profile information can be updated with dependent name and birthdate only', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'Cebu City',
            'birthplace_province' => 'Cebu',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
            'dependent_sibling_1_name' => 'Updated Sibling',
            'dependent_sibling_1_birthdate' => '1998-05-20',
            'dependent_parent_1_name' => 'Updated Parent',
        ]);

    $response->assertSessionHasNoErrors();

    $memberProfile = $user->refresh()->memberApplicationProfile;
    $dependentProfile = \App\Models\MemberDependentProfile::query()
        ->where('member_application_profile_id', $memberProfile->id)
        ->first();

    expect($dependentProfile)->not->toBeNull();

    $sibling = \App\Models\MemberDependent::query()
        ->where('member_dependent_profile_id', $dependentProfile->id)
        ->where('category', 'sibling')
        ->where('slot', 1)
        ->first();

    expect($sibling)->not->toBeNull();
    expect($sibling->name)->toBe('Updated Sibling');
    expect($sibling->birthdate->toDateString())->toBe('1998-05-20');

    $parent = \App\Models\MemberDependent::query()
        ->where('member_dependent_profile_id', $dependentProfile->id)
        ->where('category', 'parent')
        ->where('slot', 1)
        ->first();

    expect($parent)->not->toBeNull();
    expect($parent->name)->toBe('Updated Parent');

    $child = \App\Models\MemberDependent::query()
        ->where('member_dependent_profile_id', $dependentProfile->id)
        ->where('category', 'child')
        ->first();

    expect($child)->toBeNull();
});

test('updating dependent name via settings preserves cycle status/number set by the wizard', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $memberProfile = MemberApplicationProfile::factory()->create([
        'user_id' => $user->user_id,
    ]);

    $dependentProfile = \App\Models\MemberDependentProfile::query()->create([
        'member_application_profile_id' => $memberProfile->id,
        'spouse_cycle_status' => 'Old',
        'spouse_cycle_number' => 4,
    ]);

    \App\Models\MemberDependent::query()->create([
        'member_dependent_profile_id' => $dependentProfile->id,
        'category' => 'sibling',
        'slot' => 1,
        'name' => 'Wizard Sibling',
        'birthdate' => '1996-02-14',
        'cycle_status' => 'Old',
        'cycle_number' => 3,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'Cebu City',
            'birthplace_province' => 'Cebu',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
            'dependent_sibling_1_name' => 'Settings-Renamed Sibling',
            'dependent_sibling_1_birthdate' => '1996-02-14',
        ]);

    $response->assertSessionHasNoErrors();

    $sibling = \App\Models\MemberDependent::query()
        ->where('member_dependent_profile_id', $dependentProfile->id)
        ->where('category', 'sibling')
        ->where('slot', 1)
        ->first();

    expect($sibling)->not->toBeNull();
    expect($sibling->name)->toBe('Settings-Renamed Sibling');
    expect($sibling->cycle_status)->toBe('Old');
    expect($sibling->cycle_number)->toBe(3);

    expect($dependentProfile->refresh()->spouse_cycle_status)->toBe('Old');
    expect($dependentProfile->spouse_cycle_number)->toBe(4);
});

test('removing a dependent in settings deletes the saved row and its cycle data', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $memberProfile = MemberApplicationProfile::factory()->create([
        'user_id' => $user->user_id,
    ]);

    $dependentProfile = \App\Models\MemberDependentProfile::query()->create([
        'member_application_profile_id' => $memberProfile->id,
    ]);

    \App\Models\MemberDependent::query()->create([
        'member_dependent_profile_id' => $dependentProfile->id,
        'category' => 'sibling',
        'slot' => 1,
        'name' => 'To Be Removed',
        'cycle_status' => 'New',
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'Cebu City',
            'birthplace_province' => 'Cebu',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
            'dependent_sibling_1_name' => null,
        ]);

    $response->assertSessionHasNoErrors();

    $sibling = \App\Models\MemberDependent::query()
        ->where('member_dependent_profile_id', $dependentProfile->id)
        ->where('category', 'sibling')
        ->where('slot', 1)
        ->first();

    expect($sibling)->toBeNull();
});

test('profile page exposes admin profile photo url for preview', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'acctno' => null,
    ]);
    $adminProfile = AdminProfile::factory()->create([
        'user_id' => $user->user_id,
        'profile_pic_path' => "profile-photos/admin/{$user->user_id}/avatar.jpg",
    ]);

    Storage::disk('public')->put($adminProfile->profile_pic_path, 'avatar');

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where(
                'adminProfile.profilePicUrl',
                Storage::disk('public')->url($adminProfile->profile_pic_path),
            )
            ->where(
                'auth.user.avatar',
                Storage::disk('public')->url($adminProfile->profile_pic_path),
            )
        );
});

test('profile page falls back to member profile photo when admin photo is missing', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'acctno' => '001202',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $user->user_id,
        'profile_pic_path' => null,
    ]);
    $memberPath = "profile-photos/client/{$user->user_id}/avatar.jpg";

    Storage::disk('public')->put($memberPath, 'avatar');

    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
        'profile_pic_path' => $memberPath,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('adminProfile.profilePicUrl', null)
            ->where(
                'auth.user.avatar',
                Storage::disk('public')->url($memberPath),
            )
        );
});

test('profile page exposes null avatar when no profile photo is set', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('auth.user.avatar', null)
        );
});

test('profile information can be updated', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'nickname' => 'Renee',
            'birthplace_city' => 'Cebu City',
            'birthplace_province' => 'Cebu',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'number_of_children' => 2,
            'civil_status' => 'Married',
            'housing_status' => 'OWNED',
            'spouse_name' => 'Renee Santos',
            'spouse_birthdate' => '1992-05-14',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address1' => 'Acme Plaza',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'employer_business_address_zip' => '8100',
            'telephone_no' => '02-123-4567',
            'current_position' => 'Analyst',
            'nature_of_business' => 'Finance',
            'gross_monthly_income' => 'PHP 35,000.50',
            'payday' => '15th',
            'years_in_work_business' => '5 years',
            'spouse_cell_no' => '09123456780',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('client.dashboard'));

    $user->refresh();

    expect($user->username)->toBe('TestUser');
    expect($user->email)->toBe('test@example.com');
    expect($user->phoneno)->toBe('09123456789');
    expect($user->email_verified_at)->toBeNull();

    $memberProfile = $user->memberApplicationProfile;

    expect($memberProfile)->not->toBeNull();
    expect($memberProfile->nickname)->toBe('Renee');
    expect($memberProfile->birthplace)->toBe('Cebu City, Cebu');
    expect($memberProfile->birthplace_city)->toBe('Cebu City');
    expect($memberProfile->birthplace_province)->toBe('Cebu');
    expect($memberProfile->educational_attainment)->toBe('High School');
    expect($memberProfile->length_of_stay)->toBe('2 years');
    expect($memberProfile->number_of_children)->toBe(2);
    expect($memberProfile->civil_status)->toBe('Married');
    expect($memberProfile->housing_status)->toBe('OWNED');
    expect($memberProfile->spouse_name)->toBe('Renee Santos');
    expect($memberProfile->spouse_birthdate->toDateString())->toBe('1992-05-14');
    expect($memberProfile->spouse_cell_no)->toBe('09123456780');
    expect($memberProfile->employment_type)->toBe('Regular');
    expect($memberProfile->employer_business_name)->toBe('Acme Corp');
    expect($memberProfile->employer_business_address)->toBe(
        'Acme Plaza, Aglipay, Batac City, Ilocos Norte',
    );
    expect($memberProfile->employer_business_address1)->toBe('Acme Plaza');
    expect($memberProfile->employer_business_address_barangay)->toBe('Aglipay');
    expect($memberProfile->employer_business_address2)->toBe('Batac City');
    expect($memberProfile->employer_business_address3)->toBe('Ilocos Norte');
    expect($memberProfile->employer_business_address_zip)->toBe('8100');
    expect($memberProfile->telephone_no)->toBe('02-123-4567');
    expect($memberProfile->current_position)->toBe('Analyst');
    expect($memberProfile->nature_of_business)->toBe('Finance');
    expect($memberProfile->years_in_work_business)->toBe('5 years');
    expect($memberProfile->gross_monthly_income)->toBe('35000.50');
    expect($memberProfile->payday)->toBe('15th');
    expect($memberProfile->profile_completed_at)->not->toBeNull();
});

test('date employed persists from the work tab', function () {
    $user = User::factory()->create([
        'acctno' => '000920',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'nickname' => 'Renee',
            'birthplace_city' => 'Cebu City',
            'birthplace_province' => 'Cebu',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'number_of_children' => 2,
            'civil_status' => 'Married',
            'housing_status' => 'OWNED',
            'spouse_name' => 'Renee Santos',
            'spouse_birthdate' => '1992-05-14',
            'employment_type' => 'Private',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address1' => 'Acme Plaza',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'employer_business_address_zip' => '8100',
            'telephone_no' => '02-123-4567',
            'current_position' => 'Analyst',
            'nature_of_business' => 'Finance',
            'years_in_work_business' => '5 years',
            'employer_date_employed' => '2019-06-01',
            'gross_monthly_income' => 'PHP 35,000.50',
            'payday' => '15th',
            'spouse_cell_no' => '09123456780',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('client.dashboard'));

    $memberProfile = $user->fresh()->memberApplicationProfile;

    expect($memberProfile)->not->toBeNull();
    expect($memberProfile->employer_date_employed->toDateString())->toBe('2019-06-01');
});

test('profile information can be updated with other nature of business', function () {
    $user = User::factory()->create([
        'acctno' => '000904',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Santos, Ella',
        'fname' => 'Ella',
        'lname' => 'Santos',
        'birthday' => '1994-02-10',
        'address' => '901 Mabini Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'OtherUser',
            'email' => 'other@example.com',
            'phoneno' => '09123456700',
            'birthplace_city' => 'Cebu City',
            'birthplace_province' => 'Cebu',
            'educational_attainment' => 'College',
            'length_of_stay' => '3 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '45000.00',
            'payday' => '15th',
            'nature_of_business' => 'Other',
            'nature_of_business_other' => 'Logistics',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('client.dashboard'));

    $memberProfile = $user->refresh()->memberApplicationProfile;

    expect($memberProfile)->not->toBeNull();
    expect($memberProfile->nature_of_business)->toBe('Logistics');
});

test('admin profile information can be updated with a profile photo', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'acctno' => null,
        'phoneno' => '09123456789',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $user->user_id,
        'fullname' => 'Old Name',
    ]);
    $updatedPhoneNumber = '09123456780';

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => $user->username,
            'email' => $user->email,
            'phoneno' => $updatedPhoneNumber,
            'fullname' => 'Updated Admin Name',
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();
    $adminProfile = $user->adminProfile;

    expect($adminProfile)->not->toBeNull();
    expect($adminProfile->fullname)->toBe('Updated Admin Name');
    expect($adminProfile->profile_pic_path)->not->toBeNull();
    expect($adminProfile->profile_pic_path)->toContain(
        "profile-photos/admin/{$user->user_id}/",
    );
    expect($user->phoneno)->toBe($updatedPhoneNumber);

    Storage::disk('public')->assertExists($adminProfile->profile_pic_path);
});

test('hybrid members can update member profile fields', function () {
    $user = User::factory()->create([
        'acctno' => '001203',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $user->user_id,
        'fullname' => 'Hybrid Admin',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'HybridUser',
            'email' => 'hybrid@example.com',
            'phoneno' => '09123456711',
            'fullname' => 'Hybrid Admin',
            'birthplace_city' => 'Cebu City',
            'birthplace_province' => 'Cebu',
            'educational_attainment' => 'College',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '45000.00',
            'payday' => '15th',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('client.dashboard'));

    $memberProfile = $user->refresh()->memberApplicationProfile;

    expect($memberProfile)->not->toBeNull();
    expect($memberProfile->birthplace_city)->toBe('Cebu City');
    expect($memberProfile->educational_attainment)->toBe('College');
});

test('profile page exposes client profile photo url for preview', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $path = "profile-photos/client/{$user->user_id}/avatar.jpg";

    Storage::disk('public')->put($path, 'avatar');

    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
        'profile_pic_path' => $path,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('auth.user.avatar', Storage::disk('public')->url($path))
        );
});

test('member profile information can be updated with a profile photo', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => $user->username,
            'email' => $user->email,
            'phoneno' => $user->phoneno,
            'birthplace_city' => 'Cebu City',
            'birthplace_province' => 'Cebu',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
            'profile_photo' => UploadedFile::fake()->image('member-avatar.jpg'),
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('client.dashboard'));

    $userProfile = $user->refresh()->userProfile;

    expect($userProfile)->not->toBeNull();
    expect($userProfile->profile_pic_path)->not->toBeNull();
    expect($userProfile->profile_pic_path)->toContain(
        "profile-photos/client/{$user->user_id}/",
    );
    expect($user->avatar)->toBe(
        Storage::disk('public')->url($userProfile->profile_pic_path),
    );

    Storage::disk('public')->assertExists($userProfile->profile_pic_path);
});

test('member profile photo replacements remove the old file', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $existingPath = "profile-photos/client/{$user->user_id}/old-avatar.jpg";

    Storage::disk('public')->put($existingPath, 'old-avatar');

    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
        'profile_pic_path' => $existingPath,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => $user->username,
            'email' => $user->email,
            'phoneno' => $user->phoneno,
            'birthplace_city' => 'Davao City',
            'birthplace_province' => 'Davao del Sur',
            'educational_attainment' => 'College',
            'length_of_stay' => '3 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '45000.00',
            'payday' => '30th',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
            'profile_photo' => UploadedFile::fake()->image('member-avatar.jpg'),
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('client.dashboard'));

    $userProfile = $user->refresh()->userProfile;

    expect($userProfile)->not->toBeNull();
    expect($userProfile->profile_pic_path)->not->toBe($existingPath);
    expect($userProfile->profile_pic_path)->toContain(
        "profile-photos/client/{$user->user_id}/",
    );

    Storage::disk('public')->assertMissing($existingPath);
    Storage::disk('public')->assertExists($userProfile->profile_pic_path);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Santos, Renee',
        'fname' => 'Renee',
        'lname' => 'Santos',
        'birthday' => '1990-05-12',
        'address' => '123 Mabini Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => $user->email,
            'phoneno' => '09123456788',
            'birthplace_city' => 'Cebu City',
            'birthplace_province' => 'Cebu',
            'educational_attainment' => 'College',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.50',
            'payday' => '15th',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('client.dashboard'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('member application profile table excludes canonical member fields', function () {
    expect(Schema::hasColumn('member_application_profiles', 'first_name'))->toBeFalse();
    expect(Schema::hasColumn('member_application_profiles', 'last_name'))->toBeFalse();
    expect(Schema::hasColumn('member_application_profiles', 'middle_name'))->toBeFalse();
    expect(Schema::hasColumn('member_application_profiles', 'birthdate'))->toBeFalse();
    expect(Schema::hasColumn('member_application_profiles', 'age'))->toBeFalse();
    expect(Schema::hasColumn('member_application_profiles', 'address'))->toBeFalse();
    expect(Schema::hasColumn('member_application_profiles', 'occupation'))->toBeFalse();
    expect(Schema::hasColumn('member_application_profiles', 'bname'))->toBeFalse();
});

test('member application profile table includes spouse name', function () {
    expect(Schema::hasColumn('member_application_profiles', 'spouse_name'))->toBeTrue();
});

test('member application profile table includes civil status and housing status fallbacks', function () {
    // Deliberate exception to the canonical-fields exclusion above: unlike
    // occupation/address/birthdate, civil_status and housing_status are
    // lifestyle attributes safe for the member to self-report when wmaster
    // has no value, mirroring the spouse_name fallback pattern.
    expect(Schema::hasColumn('member_application_profiles', 'civil_status'))->toBeTrue();
    expect(Schema::hasColumn('member_application_profiles', 'housing_status'))->toBeTrue();
});

test('civil status and housing status are self-reportable when wmaster has no value', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'City of Batac',
            'birthplace_province' => 'Ilocos Norte',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'RENT',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response->assertSessionHasNoErrors();

    $memberProfile = $user->refresh()->memberApplicationProfile;

    expect($memberProfile->civil_status)->toBe('Single');
    expect($memberProfile->housing_status)->toBe('RENT');
});

test('civil status and housing status stay locked once wmaster has a value', function () {
    $user = User::factory()->create([
        'acctno' => '000902',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'Santos, Maria',
        'civilstat' => 'Married',
        'restype' => 'OWNED',
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'City of Batac',
            'birthplace_province' => 'Ilocos Norte',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'spouse_name' => 'Renee Santos',
            'spouse_birthdate' => '1992-05-14',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    // civil_status/housing_status weren't submitted (disabled inputs aren't
    // posted once wmaster already has a value for them), yet the profile is
    // still considered complete because wmaster's value is credited.
    $response->assertSessionHasNoErrors()->assertRedirect(route('client.dashboard'));

    $memberProfile = $user->refresh()->memberApplicationProfile;

    expect($memberProfile->civil_status)->toBeNull();
    expect($memberProfile->housing_status)->toBeNull();
});

test('spouse name and birthdate are not required when civil status is Single', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'City of Batac',
            'birthplace_province' => 'Ilocos Norte',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Single',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('client.dashboard'));

    $memberProfile = $user->refresh()->memberApplicationProfile;

    expect($memberProfile->hasRequiredFields())->toBeTrue();
    expect($memberProfile->spouse_name)->toBeNull();
    expect($memberProfile->spouse_birthdate)->toBeNull();
});

test('spouse name and birthdate validation is not required when civil status is Widowed or Separated', function () {
    foreach (['Widowed', 'Separated'] as $civilStatus) {
        $user = User::factory()->create();
        UserProfile::factory()->approved()->create([
            'user_id' => $user->user_id,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'username' => 'TestUser',
                'email' => 'test@example.com',
                'phoneno' => '09123456789',
                'birthplace_city' => 'City of Batac',
                'birthplace_province' => 'Ilocos Norte',
                'educational_attainment' => 'High School',
                'length_of_stay' => '2 years',
                'home_address1' => '123 Main Street',
                'home_address_barangay' => 'Aglipay',
                'home_address2' => 'Batac City',
                'home_address3' => 'Ilocos Norte',
                'civil_status' => $civilStatus,
                'housing_status' => 'OWNED',
                'employment_type' => 'Regular',
                'employer_business_name' => 'Acme Corp',
                'employer_business_address_barangay' => 'Aglipay',
                'employer_business_address2' => 'Batac City',
                'employer_business_address3' => 'Ilocos Norte',
                'current_position' => 'Analyst',
                'gross_monthly_income' => '35000.00',
                'payday' => '15th',
                'payout_bank_name' => 'BDO',
                'payout_account_name' => 'Test User',
                'payout_account_number' => '1234567890',
                'payout_account_type' => 'Savings',
                'release_method' => 'Cash',
            ]);

        $response->assertSessionDoesntHaveErrors(['spouse_name', 'spouse_birthdate']);
    }
});

test('spouse name and birthdate are not required when wmaster civil status is Widowed but not cleanly cased', function () {
    $user = User::factory()->create([
        'acctno' => '000903',
    ]);
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    // Legacy core-banking data can hold a non-blank but inconsistently
    // cased/whitespaced civil status. It must still be recognized as
    // "Widowed" instead of silently falling through to "spouse required".
    DB::table('wmaster')->insert([
        'acctno' => $user->acctno,
        'bname' => 'De Gracia, Cecilia',
        'civilstat' => '  widowed  ',
        'restype' => 'OWNED',
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'City of Batac',
            'birthplace_province' => 'Ilocos Norte',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Widowed',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response->assertSessionDoesntHaveErrors(['spouse_name', 'spouse_birthdate']);
    $response->assertSessionHasNoErrors()->assertRedirect(route('client.dashboard'));

    $user->refresh();

    expect($user->memberApplicationProfileHasRequiredFields())->toBeTrue();
});

test('spouse name and birthdate are required when civil status is not Single', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'City of Batac',
            'birthplace_province' => 'Ilocos Norte',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Married',
            'housing_status' => 'OWNED',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
        ]);

    $response->assertSessionHasErrors(['spouse_name', 'spouse_birthdate']);
});

test('spouse birthdate persists on the member application profile', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace_city' => 'City of Batac',
            'birthplace_province' => 'Ilocos Norte',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'home_address1' => '123 Main Street',
            'home_address_barangay' => 'Aglipay',
            'home_address2' => 'Batac City',
            'home_address3' => 'Ilocos Norte',
            'civil_status' => 'Married',
            'housing_status' => 'OWNED',
            'spouse_name' => 'Renee Santos',
            'spouse_birthdate' => '1992-05-14',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address_barangay' => 'Aglipay',
            'employer_business_address2' => 'Batac City',
            'employer_business_address3' => 'Ilocos Norte',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => 'Cash',
            'height_cm' => '165',
            'weight_kg' => '68',
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'SSS',
            'id_number' => '1234567890',
        ]);

    $response->assertSessionHasNoErrors();

    $memberProfile = $user->refresh()->memberApplicationProfile;

    expect($memberProfile->spouse_birthdate->toDateString())->toBe('1992-05-14');
});

test('user can delete their account', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create([
        'user_id' => $user->user_id,
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('profile page submits atm card holder name via hidden input when using own card', function () {
    $contents = file_get_contents(
        base_path('resources/js/pages/settings/profile-tabs/bank-tab.tsx'),
    );

    expect($contents)->toContain('name="payout_atm_holder_name"');
    expect($contents)->toContain('{isOwnAtmCard ? (');
});
