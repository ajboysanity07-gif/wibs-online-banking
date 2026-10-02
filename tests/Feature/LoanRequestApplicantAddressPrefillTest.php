<?php

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
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
            $table->string('birthplace')->nullable();
            $table->string('address')->nullable();
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('address3')->nullable();
            $table->string('address4')->nullable();
            $table->string('zone_number')->nullable();
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
        'home_address_zip' => 'Profile Zip',
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

test('a wmaster street that duplicates the barangay/city/province is trimmed down to the street only', function (): void {
    // Regression case: some legacy wmaster records have address1 crammed
    // with the full address (street + barangay + city + province) even
    // though address2/3/4 already store those parts separately and
    // correctly -- the wizard's street field must show only the street,
    // not the whole blob.
    $member = createAddressPrefillTestMember('970005', [
        'address1' => 'Purok 2 Poblacion Lianga, Surigao del Sur',
        'address2' => 'Poblacion',
        'address3' => 'Lianga',
        'address4' => 'Surigao del Sur',
    ]);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['applicant']['address1'])->toBe('Purok 2');
});

test('a blank wmaster street falls back to the profile street even when wmaster has city/province', function (): void {
    // Regression case: wmaster has city/province filled in (so the "entire
    // from wmaster" branch wins) but its own address1/street column is
    // blank -- the wizard's street field must still fall back to the
    // profile's street instead of rendering empty.
    $member = createAddressPrefillTestMember('970006', [
        'address2' => 'Wmaster Barangay',
        'address3' => 'Wmaster City',
        'address4' => 'Wmaster Province',
    ]);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['applicant']['address1'])->toBe('Profile Street')
        ->and($formData['applicant']['address_barangay'])->toBe('Wmaster Barangay')
        ->and($formData['applicant']['address2'])->toBe('Wmaster City')
        ->and($formData['applicant']['address3'])->toBe('Wmaster Province');
});

test('a blank wmaster zip falls back to the profile zip even when wmaster has city/province', function (): void {
    // Regression case: wmaster's zone_number column is an empty string
    // (common for legacy VARCHAR NOT NULL DEFAULT '' columns) rather than
    // null, so a bare `??` against it never falls through to the profile's
    // zip -- normalize both sides to null-or-string before coalescing.
    $member = createAddressPrefillTestMember('970007', [
        'address2' => 'Wmaster Barangay',
        'address3' => 'Wmaster City',
        'address4' => 'Wmaster Province',
        'zone_number' => '',
    ]);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['applicant']['address_zip'])->toBe('Profile Zip');
});

test('a non-canonical legacy city name is normalized against the PSGC dataset', function (): void {
    $member = createAddressPrefillTestMember('970004', [], [
        'home_address2' => 'Cebu City',
        'home_address3' => 'Cebu',
    ]);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['applicant']['address2'])->toBe('City of Cebu');
});

test('applicant birthplace comes from the profile even when wmaster has a legacy birthplace blob', function (): void {
    // Regression case: unlike address, wmaster only ever stores birthplace as
    // a single unstructured string. Previously this legacy blob unconditionally
    // won over the member's own verified, structured profile birthplace,
    // wiping out the city/province the member had carefully filled in on
    // their profile (e.g. wmaster's "Taytay Rizal Manila" with no comma to
    // split on collapsed everything into the city with no province).
    $member = createAddressPrefillTestMember('970006', [
        'birthplace' => 'Taytay Rizal Manila',
    ], [
        'birthplace_city' => 'Taytay',
        'birthplace_province' => 'Rizal',
    ]);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['applicant']['birthplace_city'])->toBe('Taytay')
        ->and($formData['applicant']['birthplace_province'])->toBe('Rizal');
});

test('applicant birthplace falls back to the legacy wmaster blob when the profile has no structured birthplace', function (): void {
    $member = createAddressPrefillTestMember('970007', [
        'birthplace' => 'Taytay, Rizal',
    ], [
        'birthplace_city' => null,
        'birthplace_province' => null,
        'birthplace' => null,
    ]);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['applicant']['birthplace_city'])->toBe('Taytay')
        ->and($formData['applicant']['birthplace_province'])->toBe('Rizal');
});

test('a draft applicant picks up profile fields filled in after the draft was started', function (): void {
    $member = createAddressPrefillTestMember('970010', [], [
        'employer_date_employed' => null,
        'years_in_work_business' => '2',
    ]);

    $draft = LoanRequest::factory()->create([
        'user_id' => $member->user_id,
        'acctno' => $member->acctno,
        'status' => LoanRequestStatus::Draft,
        'submitted_at' => null,
    ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($draft)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'employer_date_employed' => null,
            'years_in_work_business' => '2',
        ]);

    // The member then completes Work & Finances in Profile Settings.
    $member->memberApplicationProfile->forceFill([
        'employer_date_employed' => '2020-11-27',
        'years_in_work_business' => '6',
    ])->save();

    $formData = app(LoanRequestService::class)->getFormData($member->fresh(['memberApplicationProfile']));

    expect($formData['draft']['id'])->toBe($draft->id)
        ->and($formData['applicant']['employer_date_employed'])->toBe('2020-11-27')
        ->and($formData['applicant']['years_in_work_business'])->toBe('6');
});

test('a request returned for revision keeps staff-corrected applicant fields and only fills blanks from the profile', function (): void {
    $member = createAddressPrefillTestMember('970011', [], [
        'employer_business_name' => 'Profile Employer',
        'employer_date_employed' => '2020-11-27',
    ]);

    $request = LoanRequest::factory()->create([
        'user_id' => $member->user_id,
        'acctno' => $member->acctno,
        'status' => LoanRequestStatus::NeedsRevision,
    ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($request)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            // Corrected by the processor during review.
            'employer_business_name' => 'Staff Corrected Employer',
            'employer_date_employed' => null,
        ]);

    $formData = app(LoanRequestService::class)->getFormData($member);

    expect($formData['draft']['id'])->toBe($request->id)
        ->and($formData['applicant']['employer_business_name'])->toBe('Staff Corrected Employer')
        ->and($formData['applicant']['employer_date_employed'])->toBe('2020-11-27');
});
