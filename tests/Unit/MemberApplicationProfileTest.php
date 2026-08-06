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

test('bank and payout base fields are included in completion required fields', function () {
    expect(MemberApplicationProfile::completionRequiredFields())
        ->toContain('payout_bank_name')
        ->toContain('payout_account_name')
        ->toContain('payout_account_number')
        ->toContain('payout_account_type')
        ->toContain('release_method');
});

test('base bank and payout fields are missing until all five are set', function () use ($baseAttributes) {
    $profile = new MemberApplicationProfile($baseAttributes);

    expect($profile->missingRequiredFields())
        ->toContain('payout_bank_name')
        ->toContain('payout_account_name')
        ->toContain('payout_account_number')
        ->toContain('payout_account_type')
        ->toContain('release_method');

    $profile = new MemberApplicationProfile([
        ...$baseAttributes,
        'payout_bank_name' => 'BDO',
        'payout_account_name' => 'Test User',
        'payout_account_number' => '1234567890',
        'payout_account_type' => 'Savings',
        'release_method' => 'Cash',
    ]);

    expect($profile->missingRequiredFields())
        ->not->toContain('payout_bank_name')
        ->not->toContain('payout_account_name')
        ->not->toContain('payout_account_number')
        ->not->toContain('payout_account_type')
        ->not->toContain('release_method');
});

test('atm fields are only required when release method is ATM', function () use ($baseAttributes) {
    $bankTransfer = new MemberApplicationProfile([
        ...$baseAttributes,
        'payout_bank_name' => 'BDO',
        'payout_account_name' => 'Test User',
        'payout_account_number' => '1234567890',
        'payout_account_type' => 'Savings',
        'release_method' => 'Bank Transfer',
        'release_uses_payout_account' => true,
    ]);

    expect($bankTransfer->missingRequiredFields())
        ->not->toContain('payout_atm_number')
        ->not->toContain('payout_atm_holder_name');

    $atm = new MemberApplicationProfile([
        ...$baseAttributes,
        'payout_bank_name' => 'BDO',
        'payout_account_name' => 'Test User',
        'payout_account_number' => '1234567890',
        'payout_account_type' => 'Savings',
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

test('release account fields are only required for bank transfer when not reusing the payout account', function () use ($baseAttributes) {
    $reusingPayoutAccount = new MemberApplicationProfile([
        ...$baseAttributes,
        'payout_bank_name' => 'BDO',
        'payout_account_name' => 'Test User',
        'payout_account_number' => '1234567890',
        'payout_account_type' => 'Savings',
        'release_method' => 'Bank Transfer',
        'release_uses_payout_account' => true,
    ]);

    expect($reusingPayoutAccount->missingRequiredFields())
        ->not->toContain('release_bank_name')
        ->not->toContain('release_account_name')
        ->not->toContain('release_account_number')
        ->not->toContain('release_account_type');

    $separateReleaseAccount = new MemberApplicationProfile([
        ...$baseAttributes,
        'payout_bank_name' => 'BDO',
        'payout_account_name' => 'Test User',
        'payout_account_number' => '1234567890',
        'payout_account_type' => 'Savings',
        'release_method' => 'Bank Transfer',
        'release_uses_payout_account' => false,
    ]);

    expect($separateReleaseAccount->missingRequiredFields())
        ->toContain('release_bank_name')
        ->toContain('release_account_name')
        ->toContain('release_account_number')
        ->toContain('release_account_type');

    $atm = new MemberApplicationProfile([
        ...$baseAttributes,
        'payout_bank_name' => 'BDO',
        'payout_account_name' => 'Test User',
        'payout_account_number' => '1234567890',
        'payout_account_type' => 'Savings',
        'release_method' => 'ATM',
        'release_uses_payout_account' => false,
    ]);

    expect($atm->missingRequiredFields())
        ->not->toContain('release_bank_name');
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
