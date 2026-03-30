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
            $table->string('address2')->nullable();
            $table->string('address3')->nullable();
            $table->string('address4')->nullable();
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

test('admin profile page is displayed', function () {
    $user = User::factory()->create();
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
        'address2' => '123 Mabini Street',
        'address3' => 'Manila',
        'address4' => 'Metro Manila',
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
            ->where('memberRecord.birthday', '1991-04-12')
            ->where('memberRecord.address', 'Legacy Address')
            ->where('memberRecord.address2', '123 Mabini Street')
            ->where('memberRecord.address3', 'Manila')
            ->where('memberRecord.address4', 'Metro Manila')
            ->where(
                'memberRecord.display_address',
                '123 Mabini Street, Manila, Metro Manila',
            )
            ->where('memberRecord.civilstat', 'Single')
            ->where('memberRecord.occupation', 'Analyst')
            ->where('memberRecord.spouse_name', 'Miguel Santos')
            ->where('memberRecord.housing_status', 'Owned')
            ->where('memberRecord.number_of_children', '2')
            ->where('memberRecord.hasStructuredName', true)
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
            ->where('memberRecord.birthplace', 'Bacolod City')
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
            ->where(
                'auth.user.avatar',
                Storage::disk('public')->url($adminProfile->profile_pic_path),
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
            'nickname' => 'Renee',
            'birthplace' => 'Cebu City',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'number_of_children' => 2,
            'spouse_age' => 32,
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address' => 'Acme Plaza, Tagum City, Davao del Norte',
            'telephone_no' => '02-123-4567',
            'current_position' => 'Analyst',
            'nature_of_business' => 'Finance',
            'gross_monthly_income' => 'PHP 35,000.50',
            'payday' => '15th',
            'years_in_work_business' => '5 years',
            'spouse_cell_no' => '09123456780',
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
    expect($memberProfile->educational_attainment)->toBe('High School');
    expect($memberProfile->length_of_stay)->toBe('2 years');
    expect($memberProfile->number_of_children)->toBe(2);
    expect($memberProfile->spouse_age)->toBe(32);
    expect($memberProfile->spouse_cell_no)->toBe('09123456780');
    expect($memberProfile->employment_type)->toBe('Regular');
    expect($memberProfile->employer_business_name)->toBe('Acme Corp');
    expect($memberProfile->employer_business_address)->toBe(
        'Acme Plaza, Tagum City, Davao del Norte',
    );
    expect($memberProfile->telephone_no)->toBe('02-123-4567');
    expect($memberProfile->current_position)->toBe('Analyst');
    expect($memberProfile->nature_of_business)->toBe('Finance');
    expect($memberProfile->years_in_work_business)->toBe('5 years');
    expect($memberProfile->gross_monthly_income)->toBe('35000.50');
    expect($memberProfile->payday)->toBe('15th');
    expect($memberProfile->profile_completed_at)->not->toBeNull();
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
            'birthplace' => 'Cebu City',
            'educational_attainment' => 'College',
            'length_of_stay' => '3 years',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '45000.00',
            'payday' => '15th',
            'nature_of_business' => 'Other',
            'nature_of_business_other' => 'Logistics',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $memberProfile = $user->refresh()->memberApplicationProfile;

    expect($memberProfile)->not->toBeNull();
    expect($memberProfile->nature_of_business)->toBe('Logistics');
});

test('admin profile information can be updated with a profile photo', function () {
    Storage::fake('public');

    $user = User::factory()->create([
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
            'birthplace' => 'Cebu City',
            'educational_attainment' => 'High School',
            'length_of_stay' => '2 years',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.00',
            'payday' => '15th',
            'profile_photo' => UploadedFile::fake()->image('member-avatar.jpg'),
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

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
            'birthplace' => 'Davao City',
            'educational_attainment' => 'College',
            'length_of_stay' => '3 years',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '45000.00',
            'payday' => '30th',
            'profile_photo' => UploadedFile::fake()->image('member-avatar.jpg'),
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

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
            'birthplace' => 'Cebu City',
            'educational_attainment' => 'College',
            'length_of_stay' => '2 years',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.50',
            'payday' => '15th',
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
    expect(Schema::hasColumn('member_application_profiles', 'occupation'))->toBeFalse();
    expect(Schema::hasColumn('member_application_profiles', 'spouse_name'))->toBeFalse();
    expect(Schema::hasColumn('member_application_profiles', 'housing_status'))->toBeFalse();
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
