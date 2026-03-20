<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
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
            $table->string('address')->nullable();
            $table->string('civilstat')->nullable();
            $table->string('occupation')->nullable();
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
        'address' => '123 Mabini Street',
        'civilstat' => 'Single',
        'occupation' => 'Analyst',
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
            ->where('memberRecord.birthday', '1991-04-12')
            ->where('memberRecord.address', '123 Mabini Street')
            ->where('memberRecord.civilstat', 'Single')
            ->where('memberRecord.occupation', 'Analyst')
            ->where('memberRecord.hasStructuredName', true)
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

test('profile page exposes admin profile photo url for preview', function () {
    Storage::fake('public');

    $user = User::factory()->create();
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
        );
});

test('profile information can be updated', function () {
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
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'birthplace' => 'Cebu City',
            'length_of_stay' => '2 years',
            'housing_status' => 'Owned',
            'educational_attainment' => 'College',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.50',
            'payday' => '15',
            'years_in_work_business' => '5 years',
            'spouse_cell_no' => '09123456780',
            'nickname' => 'Renee',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->username)->toBe('TestUser');
    expect($user->email)->toBe('test@example.com');
    expect($user->phoneno)->toBe('09123456789');
    expect($user->email_verified_at)->toBeNull();

    $memberProfile = $user->memberApplicationProfile;

    expect($memberProfile)->not->toBeNull();
    expect($memberProfile->nickname)->toBe('Renee');
    expect($memberProfile->birthplace)->toBe('Cebu City');
    expect($memberProfile->length_of_stay)->toBe('2 years');
    expect($memberProfile->housing_status)->toBe('Owned');
    expect($memberProfile->educational_attainment)->toBe('College');
    expect($memberProfile->employment_type)->toBe('Regular');
    expect($memberProfile->gross_monthly_income)->toBe('35000.50');
    expect($memberProfile->profile_completed_at)->not->toBeNull();
});

test('admin profile information can be updated with a profile photo', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $user->user_id,
        'fullname' => 'Old Name',
    ]);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => $user->username,
            'email' => $user->email,
            'phoneno' => $user->phoneno,
            'fullname' => 'Updated Admin Name',
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $adminProfile = $user->refresh()->adminProfile;

    expect($adminProfile)->not->toBeNull();
    expect($adminProfile->fullname)->toBe('Updated Admin Name');
    expect($adminProfile->profile_pic_path)->not->toBeNull();
    expect($adminProfile->profile_pic_path)->toContain(
        "profile-photos/admin/{$user->user_id}/",
    );

    Storage::disk('public')->assertExists($adminProfile->profile_pic_path);
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
            'birthplace' => 'Cebu City',
            'length_of_stay' => '2 years',
            'housing_status' => 'Owned',
            'educational_attainment' => 'College',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.50',
            'payday' => '15',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('member application profile table excludes canonical member fields', function () {
    expect(Schema::hasColumn('member_application_profiles', 'first_name'))->toBeFalse();
    expect(Schema::hasColumn('member_application_profiles', 'last_name'))->toBeFalse();
    expect(Schema::hasColumn('member_application_profiles', 'middle_name'))->toBeFalse();
    expect(Schema::hasColumn('member_application_profiles', 'birthdate'))->toBeFalse();
    expect(Schema::hasColumn('member_application_profiles', 'age'))->toBeFalse();
    expect(Schema::hasColumn('member_application_profiles', 'address'))->toBeFalse();
    expect(Schema::hasColumn('member_application_profiles', 'civil_status'))->toBeFalse();
    expect(Schema::hasColumn('member_application_profiles', 'bname'))->toBeFalse();
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
