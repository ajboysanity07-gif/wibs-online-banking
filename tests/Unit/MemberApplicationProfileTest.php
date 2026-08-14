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
        ->not->toContain('payout_bank_name')
        ->not->toContain('payout_account_name')
        ->not->toContain('payout_account_number')
        ->not->toContain('payout_account_type');
});

test('release method is required before it is set', function () use ($baseAttributes) {
    $profile = new MemberApplicationProfile($baseAttributes);

    expect($profile->missingRequiredFields())->toContain('release_method');
});

test('base bank and payout fields are not required for cash or check', function () use ($baseAttributes) {
    foreach (['Cash', 'Check'] as $releaseMethod) {
        $profile = new MemberApplicationProfile([
            ...$baseAttributes,
            'release_method' => $releaseMethod,
        ]);

        expect($profile->missingRequiredFields())
            ->not->toContain('payout_bank_name')
            ->not->toContain('payout_account_name')
            ->not->toContain('payout_account_number')
            ->not->toContain('payout_account_type');
    }
});

test('base bank and payout fields are required for atm and bank transfer', function () use ($baseAttributes) {
    foreach (['ATM', 'Bank Transfer'] as $releaseMethod) {
        $profile = new MemberApplicationProfile([
            ...$baseAttributes,
            'release_method' => $releaseMethod,
        ]);

        expect($profile->missingRequiredFields())
            ->toContain('payout_bank_name')
            ->toContain('payout_account_name')
            ->toContain('payout_account_number')
            ->toContain('payout_account_type');

        $profile = new MemberApplicationProfile([
            ...$baseAttributes,
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'payout_atm_number' => '5555444433332222',
            'payout_atm_holder_name' => 'Test User',
            'release_method' => $releaseMethod,
        ]);

        expect($profile->missingRequiredFields())
            ->not->toContain('payout_bank_name')
            ->not->toContain('payout_account_name')
            ->not->toContain('payout_account_number')
            ->not->toContain('payout_account_type');
    }
});

test('payment bank and atm fields are only required when payment option is atm deduction', function () use ($baseAttributes) {
    $salaryDeduction = new MemberApplicationProfile([
        ...$baseAttributes,
        'payment_option' => 'Salary Deduction',
    ]);

    expect($salaryDeduction->missingRequiredFields())
        ->not->toContain('payment_bank_name')
        ->not->toContain('payment_account_name')
        ->not->toContain('payment_account_number')
        ->not->toContain('payment_account_type')
        ->not->toContain('payment_atm_number')
        ->not->toContain('payment_atm_holder_name');

    $atmDeduction = new MemberApplicationProfile([
        ...$baseAttributes,
        'payment_option' => 'ATM Deduction',
    ]);

    expect($atmDeduction->missingRequiredFields())
        ->toContain('payment_bank_name')
        ->toContain('payment_account_name')
        ->toContain('payment_account_number')
        ->toContain('payment_account_type')
        ->toContain('payment_atm_number')
        ->toContain('payment_atm_holder_name');

    $atmDeduction->payment_bank_name = 'BDO';
    $atmDeduction->payment_account_name = 'Test User';
    $atmDeduction->payment_account_number = '1234567890';
    $atmDeduction->payment_account_type = 'Savings';
    $atmDeduction->payment_atm_number = '5555444433332222';
    $atmDeduction->payment_atm_holder_name = 'Test User';

    expect($atmDeduction->missingRequiredFields())
        ->not->toContain('payment_bank_name')
        ->not->toContain('payment_account_name')
        ->not->toContain('payment_account_number')
        ->not->toContain('payment_account_type')
        ->not->toContain('payment_atm_number')
        ->not->toContain('payment_atm_holder_name');
});

test('atm fields are only required when release method is ATM', function () use ($baseAttributes) {
    $bankTransfer = new MemberApplicationProfile([
        ...$baseAttributes,
        'payout_bank_name' => 'BDO',
        'payout_account_name' => 'Test User',
        'payout_account_number' => '1234567890',
        'payout_account_type' => 'Savings',
        'release_method' => 'Bank Transfer',
    ]);

    expect($bankTransfer->missingRequiredFields())
        ->not->toContain('payout_atm_number')
        ->not->toContain('payout_atm_holder_name');

    $atm = new MemberApplicationProfile([
        ...$baseAttributes,
        'release_method' => 'ATM',
    ]);

    expect($atm->missingRequiredFields())
        ->toContain('payout_atm_number')
        ->toContain('payout_atm_holder_name');

    $atm->payout_atm_number = '5555444433332222';
    $atm->payout_atm_holder_name = 'Test User';

    expect($atm->missingRequiredFields())
        ->not->toContain('payout_atm_number')
        ->not->toContain('payout_atm_holder_name');
});

test('payout bank branch is never required', function () use ($baseAttributes) {
    foreach (['ATM', 'Bank Transfer', 'Check', 'Cash'] as $releaseMethod) {
        $profile = new MemberApplicationProfile([
            ...$baseAttributes,
            'payout_bank_name' => 'BDO',
            'payout_account_name' => 'Test User',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'release_method' => $releaseMethod,
        ]);

        expect($profile->missingRequiredFields())->not->toContain('payout_bank_branch');
    }
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

    expect($cash->missingLoanPrerequisiteFields())
        ->not->toContain('payout_bank_name')
        ->not->toContain('payout_account_name')
        ->not->toContain('payout_account_number')
        ->not->toContain('payout_account_type');

    $bankTransfer = new MemberApplicationProfile([
        ...$baseAttributes,
        'release_method' => 'Bank Transfer',
        'source_of_fund_wealth' => 'Salary',
        'id_type' => 'SSS',
        'id_number' => '01-2345678-9',
        'height_cm' => '165',
        'weight_kg' => '65',
    ]);

    expect($bankTransfer->missingLoanPrerequisiteFields())
        ->toContain('payout_bank_name')
        ->toContain('payout_account_name')
        ->toContain('payout_account_number')
        ->toContain('payout_account_type');
});

test('legacy WMASTER "NA" placeholder values are treated as missing, not present', function () use ($baseAttributes) {
    $profile = new MemberApplicationProfile([
        ...$baseAttributes,
        'payout_bank_name' => 'NA',
        'payout_account_name' => 'N/A',
        'payout_account_number' => 'na',
        'payout_account_type' => 'n/a',
        'release_method' => 'Bank Transfer',
    ]);

    expect($profile->missingRequiredFields())
        ->toContain('payout_bank_name')
        ->toContain('payout_account_name')
        ->toContain('payout_account_number')
        ->toContain('payout_account_type');

    expect($profile->missingLoanPrerequisiteFields())
        ->toContain('payout_bank_name')
        ->toContain('payout_account_name')
        ->toContain('payout_account_number')
        ->toContain('payout_account_type');
});

test('employmentTypeMatches recognizes real-world casing/hyphen variants of the canonical value', function () {
    expect(MemberApplicationProfile::employmentTypeMatches('Self Employed', MemberApplicationProfile::SELF_EMPLOYED_EMPLOYMENT_TYPE))->toBeTrue();
    expect(MemberApplicationProfile::employmentTypeMatches('Self-Employed', MemberApplicationProfile::SELF_EMPLOYED_EMPLOYMENT_TYPE))->toBeTrue();
    expect(MemberApplicationProfile::employmentTypeMatches('  self   employed ', MemberApplicationProfile::SELF_EMPLOYED_EMPLOYMENT_TYPE))->toBeTrue();
    expect(MemberApplicationProfile::employmentTypeMatches('Private', MemberApplicationProfile::SELF_EMPLOYED_EMPLOYMENT_TYPE))->toBeFalse();
    expect(MemberApplicationProfile::employmentTypeMatches(null, MemberApplicationProfile::SELF_EMPLOYED_EMPLOYMENT_TYPE))->toBeFalse();
});
