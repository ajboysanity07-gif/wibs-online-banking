<?php

use App\Models\MemberApplicationProfile;

$baseAttributes = [
    'birthplace_city' => 'City of Batac',
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
    'current_position' => 'Analyst',
    'gross_monthly_income' => '35000.00',
    'payday' => '15th',
];

test('spouse name and birthdate are not required when civil status is Single, Widowed, or Separated', function () use ($baseAttributes) {
    foreach (['Single', 'Widowed', 'Separated'] as $civilStatus) {
        $profile = new MemberApplicationProfile([
            ...$baseAttributes,
            'civil_status' => $civilStatus,
        ]);

        expect($profile->missingRequiredFields())
            ->not->toContain('spouse_name')
            ->not->toContain('spouse_birthdate');
    }
});

test('spouse name and birthdate are required when civil status is Married', function () use ($baseAttributes) {
    $profile = new MemberApplicationProfile([
        ...$baseAttributes,
        'civil_status' => 'Married',
    ]);

    expect($profile->missingRequiredFields())
        ->toContain('spouse_name')
        ->toContain('spouse_birthdate');
});

test('only release method is unconditionally included in completion required fields', function () {
    expect(MemberApplicationProfile::completionRequiredFields())
        ->toContain('release_method')
        ->not->toContain('release_saved_account_id')
        ->not->toContain('payment_saved_account_id');
});

test('release method is required before it is set', function () use ($baseAttributes) {
    $profile = new MemberApplicationProfile($baseAttributes);

    expect($profile->missingRequiredFields())->toContain('release_method');
});

test('a saved release account is not required for cash or check', function () use ($baseAttributes) {
    foreach (['Cash', 'Check'] as $releaseMethod) {
        $profile = new MemberApplicationProfile([
            ...$baseAttributes,
            'release_method' => $releaseMethod,
        ]);

        expect($profile->missingRequiredFields())->not->toContain('release_saved_account_id');
    }
});

test('a saved release account is required for atm and bank transfer', function () use ($baseAttributes) {
    foreach (['ATM', 'Bank Transfer'] as $releaseMethod) {
        $profile = new MemberApplicationProfile([
            ...$baseAttributes,
            'release_method' => $releaseMethod,
        ]);

        expect($profile->missingRequiredFields())->toContain('release_saved_account_id');

        $profile = new MemberApplicationProfile([
            ...$baseAttributes,
            'release_saved_account_id' => 1,
            'release_method' => $releaseMethod,
        ]);

        expect($profile->missingRequiredFields())->not->toContain('release_saved_account_id');
    }
});

test('a saved payment account is only required when payment option is atm deduction', function () use ($baseAttributes) {
    $salaryDeduction = new MemberApplicationProfile([
        ...$baseAttributes,
        'payment_option' => 'Salary Deduction',
    ]);

    expect($salaryDeduction->missingRequiredFields())->not->toContain('payment_saved_account_id');

    $atmDeduction = new MemberApplicationProfile([
        ...$baseAttributes,
        'payment_option' => 'ATM Deduction',
    ]);

    expect($atmDeduction->missingRequiredFields())->toContain('payment_saved_account_id');

    $atmDeduction->payment_saved_account_id = 1;

    expect($atmDeduction->missingRequiredFields())->not->toContain('payment_saved_account_id');
});

test('loan prerequisite fields mirror the same release-method conditionality', function () use ($baseAttributes) {
    $cash = new MemberApplicationProfile([
        ...$baseAttributes,
        'release_method' => 'Cash',
        'source_of_fund_wealth' => 'Salary',
        'id_type' => 'SSS',
        'id_number' => '01-2345678-9',
        'height_cm' => '165',
        'weight_kg' => '65',
    ]);

    expect($cash->missingLoanPrerequisiteFields())->not->toContain('release_saved_account_id');

    $bankTransfer = new MemberApplicationProfile([
        ...$baseAttributes,
        'release_method' => 'Bank Transfer',
        'source_of_fund_wealth' => 'Salary',
        'id_type' => 'SSS',
        'id_number' => '01-2345678-9',
        'height_cm' => '165',
        'weight_kg' => '65',
    ]);

    expect($bankTransfer->missingLoanPrerequisiteFields())->toContain('release_saved_account_id');
});

test('employmentTypeMatches recognizes real-world casing/hyphen variants of the canonical value', function () {
    expect(MemberApplicationProfile::employmentTypeMatches('Self Employed', MemberApplicationProfile::SELF_EMPLOYED_EMPLOYMENT_TYPE))->toBeTrue();
    expect(MemberApplicationProfile::employmentTypeMatches('Self-Employed', MemberApplicationProfile::SELF_EMPLOYED_EMPLOYMENT_TYPE))->toBeTrue();
    expect(MemberApplicationProfile::employmentTypeMatches('  self   employed ', MemberApplicationProfile::SELF_EMPLOYED_EMPLOYMENT_TYPE))->toBeTrue();
    expect(MemberApplicationProfile::employmentTypeMatches('Private', MemberApplicationProfile::SELF_EMPLOYED_EMPLOYMENT_TYPE))->toBeFalse();
    expect(MemberApplicationProfile::employmentTypeMatches(null, MemberApplicationProfile::SELF_EMPLOYED_EMPLOYMENT_TYPE))->toBeFalse();
});
