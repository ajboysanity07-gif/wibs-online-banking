<?php

use App\Models\AppUser as User;
use App\Models\MemberApplicationProfile;
use App\Models\UserProfile;
use App\Services\LoanRequests\LoanRequestService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fields a loan submission needs from the profile but that aren't part of
// the account-wide completion gate. The wizard shows them read-only, so they
// must be required in Profile Settings and flagged when the wizard opens.

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

    if (! Schema::hasTable('wlntype')) {
        Schema::create('wlntype', function (Blueprint $table) {
            $table->string('typecode')->primary();
            $table->string('lntype');
        });
    }
});

/**
 * @return array<string, mixed>
 */
function loanRequiredProfilePayload(array $overrides = []): array
{
    return array_merge([
        'username' => 'loan_required_member',
        'email' => 'loan-required@example.com',
        'phoneno' => '09171234560',
        'birthplace_city' => 'Cebu City',
        'birthplace_province' => 'Cebu',
        'educational_attainment' => 'College',
        'length_of_stay' => '5 years',
        'home_address1' => 'Street',
        'home_address_barangay' => 'Aglipay',
        'home_address2' => 'Batac City',
        'home_address3' => 'Ilocos Norte',
        'civil_status' => 'Single',
        'housing_status' => 'OWNED',
        'release_method' => 'Cash',
        'employment_type' => 'Regular',
        'employer_business_name' => 'Acme',
        'employer_business_address1' => 'Purok 2',
        'current_position' => 'Clerk',
        'nature_of_business' => 'Government',
        'years_in_work_business' => '6',
        'number_of_children' => '0',
        'gross_monthly_income' => '15000',
        'payday' => 'Monthly',
    ], $overrides);
}

function loanRequiredProfileMember(): User
{
    $user = User::factory()->create();
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);

    return $user;
}

test('profile settings requires the loan-required applicant fields for an employed member', function () {
    $response = $this
        ->actingAs(loanRequiredProfileMember())
        ->patch(route('profile.update'), loanRequiredProfilePayload([
            'birthplace_province' => '',
            'employer_business_address1' => '',
            'nature_of_business' => '',
            'years_in_work_business' => '',
            'number_of_children' => '',
        ]));

    $response->assertSessionHasErrors([
        'birthplace_province',
        'employer_business_address1',
        'nature_of_business',
        'years_in_work_business',
        'number_of_children' => 'Number of children is required to complete your profile. Enter 0 if none.',
    ]);
});

test('profile settings saves a complete profile without a date employed', function () {
    $user = loanRequiredProfileMember();

    $this
        ->actingAs($user)
        ->patch(route('profile.update'), loanRequiredProfilePayload())
        ->assertSessionHasNoErrors();

    $profile = $user->fresh()->memberApplicationProfile;

    expect($profile->employer_date_employed)->toBeNull()
        ->and($profile->number_of_children)->toBe(0);
});

test('profile settings does not require employer details from a pensioner', function () {
    $response = $this
        ->actingAs(loanRequiredProfileMember())
        ->patch(route('profile.update'), loanRequiredProfilePayload([
            'employment_type' => MemberApplicationProfile::PENSIONER_EMPLOYMENT_TYPE,
            'employer_business_name' => '',
            'employer_business_address1' => '',
            'current_position' => '',
            'nature_of_business' => '',
            'years_in_work_business' => '',
            'number_of_children' => '',
        ]));

    $response
        ->assertSessionDoesntHaveErrors([
            'employer_business_address1',
            'nature_of_business',
            'years_in_work_business',
        ])
        ->assertSessionHasErrors(['number_of_children']);
});

test('profile settings does not require number of children when the core-banking record has it', function () {
    $user = loanRequiredProfileMember();
    DB::table('wmaster')->insert(['acctno' => $user->acctno, 'dependent' => '2']);

    $this
        ->actingAs($user->fresh())
        ->patch(route('profile.update'), loanRequiredProfilePayload(['number_of_children' => '']))
        ->assertSessionDoesntHaveErrors(['number_of_children']);
});

test('the loan wizard flags loan-required fields still blank in the applicant', function () {
    $user = User::factory()->create(['acctno' => '971001']);
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
        'user_id' => $user->user_id,
        'employment_type' => 'Regular',
        'years_in_work_business' => null,
        'number_of_children' => null,
        'employer_date_employed' => null,
    ]);

    $formData = app(LoanRequestService::class)->getFormData($user->fresh());

    expect($formData['missingIdentityPrerequisites'])
        ->toContain('Years in work or business', 'Number of children')
        ->not->toContain('Date employed');
});

test('the loan wizard flags nothing for a complete profile', function () {
    $user = User::factory()->create(['acctno' => '971002']);
    UserProfile::factory()->approved()->create(['user_id' => $user->user_id]);
    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
        'user_id' => $user->user_id,
    ]);

    $formData = app(LoanRequestService::class)->getFormData($user->fresh());

    expect($formData['missingIdentityPrerequisites'])->toBe([]);
});
