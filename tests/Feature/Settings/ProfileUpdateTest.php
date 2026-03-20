<?php

use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\UserProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

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

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => 'test@example.com',
            'phoneno' => '09123456789',
            'first_name' => 'Renee',
            'last_name' => 'Santos',
            'birthdate' => '1990-05-12',
            'birthplace' => 'Cebu City',
            'address' => '123 Mabini Street',
            'civil_status' => 'Single',
            'employment_type' => 'Regular',
            'employer_business_name' => 'Acme Corp',
            'current_position' => 'Analyst',
            'gross_monthly_income' => '35000.50',
            'payday' => '15',
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
    expect($memberProfile->first_name)->toBe('Renee');
    expect($memberProfile->last_name)->toBe('Santos');
    expect($memberProfile->birthdate?->toDateString())->toBe('1990-05-12');
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

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'username' => 'TestUser',
            'email' => $user->email,
            'phoneno' => '09123456788',
            'first_name' => 'Renee',
            'last_name' => 'Santos',
            'birthdate' => '1990-05-12',
            'birthplace' => 'Cebu City',
            'address' => '123 Mabini Street',
            'civil_status' => 'Single',
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
