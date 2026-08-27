<?php

use App\Models\AppUser;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use App\Services\LoanRequests\LoanRequestService;
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
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('address3')->nullable();
            $table->string('address4')->nullable();
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

function createAddressPrefillTestMember(string $acctno, array $wmasterOverrides, array $profileOverrides = []): AppUser
{
    $member = AppUser::factory()->create([
        'acctno' => $acctno,
        'email_verified_at' => now(),
    ]);

    $member->roles()->sync(
        Role::query()->where('name', Role::MEMBER)->pluck('id')->all(),
    );

    UserProfile::factory()->approved()->create(['user_id' => $member->user_id]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create(array_merge([
        'user_id' => $member->user_id,
        'home_address1' => 'Profile Street',
        'home_address_barangay' => 'Profile Barangay',
        'home_address2' => 'Profile City',
        'home_address3' => 'Profile Province',
    ], $profileOverrides));

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        array_merge([
            'fname' => 'Applicant',
            'lname' => 'Address',
            'birthday' => '1990-01-01',
        ], $wmasterOverrides),
    );

    return $member->fresh(['roles.permissions', 'userProfile', 'memberApplicationProfile']);
}

test('applicant address comes entirely from wmaster when wmaster has city/province', function (): void {
    $member = createAddressPrefillTestMember('970001', [
        'address1' => 'Wmaster Street',
        'address2' => 'Wmaster Barangay',
        'address3' => 'Wmaster City',
        'address4' => 'Wmaster Province',
    ]);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['applicant']['address1'])->toBe('Wmaster Street')
        ->and($formData['applicant']['address_barangay'])->toBe('Wmaster Barangay')
        ->and($formData['applicant']['address2'])->toBe('Wmaster City')
        ->and($formData['applicant']['address3'])->toBe('Wmaster Province');
});

test('applicant address comes entirely from the profile when wmaster has no city/province', function (): void {
    $member = createAddressPrefillTestMember('970002', []);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['applicant']['address1'])->toBe('Profile Street')
        ->and($formData['applicant']['address_barangay'])->toBe('Profile Barangay')
        ->and($formData['applicant']['address2'])->toBe('Profile City')
        ->and($formData['applicant']['address3'])->toBe('Profile Province');
});

test('a stale wmaster barangay does not get paired with profile city/province', function (): void {
    // Regression case: wmaster only has a legacy barangay value, with no
    // city/province of its own -- previously this stale barangay would be
    // paired with the profile's city/province, producing a mismatch the
    // barangay dropdown could not resolve/preselect.
    $member = createAddressPrefillTestMember('970003', [
        'address2' => 'Stale Wmaster Barangay',
    ]);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['applicant']['address_barangay'])->toBe('Profile Barangay')
        ->and($formData['applicant']['address2'])->toBe('Profile City')
        ->and($formData['applicant']['address3'])->toBe('Profile Province');
});

test('a non-canonical legacy city name is normalized against the PSGC dataset', function (): void {
    $member = createAddressPrefillTestMember('970004', [], [
        'home_address2' => 'Cebu City',
        'home_address3' => 'Cebu',
    ]);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['applicant']['address2'])->toBe('City of Cebu');
});
