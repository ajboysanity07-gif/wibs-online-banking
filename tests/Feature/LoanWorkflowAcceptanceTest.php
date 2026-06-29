<?php

use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
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

    DB::table('wlntype')->updateOrInsert(
        ['typecode' => 'LN-P7'],
        ['lntype' => 'Phase Seven Production Loan'],
    );
});

test('v2 workflow happy path reaches final approval after revised terms are accepted and documents regenerated', function (): void {
    config()->set('mail.default', 'array');

    $member = createAcceptanceMember('940001', 'Happy', 'Member');
    $processor = createAcceptanceActor([Role::LOAN_PROCESSOR]);
    $manager = createAcceptanceActor([Role::LOAN_MANAGER]);

    $submitResponse = $this
        ->actingAs($member)
        ->post(route('client.loan-requests.store'), acceptanceLoanRequestPayload());

    $loanRequest = LoanRequest::query()->sole();

    $submitResponse->assertRedirect(route('client.loan-requests.show', $loanRequest));

    expect($loanRequest->status)->toBe(LoanRequestStatus::PendingReview)
        ->and($loanRequest->workflow_version)->toBe(
            LoanRequestWorkflowVersion::DocumentWorkflowV2,
        );

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.claim', $loanRequest))
        ->assertOk()
        ->assertJsonPath('data.loanRequest.assigned_officer_id', $processor->user_id);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.start-review', $loanRequest), [
            'remarks' => 'Starting full acceptance review.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::UnderReview->value);

    $updateResponse = $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), acceptanceProcessingPayload())
        ->assertOk()
        ->assertJsonPath('data.loanRequest.recommended_amount', '25000.00')
        ->assertJsonPath('data.loanRequest.recommended_term', 12)
        ->assertJsonPath('data.loanRequest.recommended_interest_rate', '1.5000')
        ->assertJsonPath('data.loanRequest.recommended_payment_frequency', '15th & 30th');

    $documentGenerationResponse = $this
        ->actingAs($processor)
        ->postJson(route('spa.workflow.loan-requests.documents.generate', $loanRequest))
        ->assertOk();

    $documentStatuses = collect(
        $documentGenerationResponse->json('data.documentChecklist'),
    )
        ->where('is_applicable', true)
        ->pluck('status')
        ->unique()
        ->values()
        ->all();

    expect($documentStatuses)->toBe([
        LoanRequestDocumentReadinessStatus::GeneratedCurrent->value,
    ]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.recommend-approval', $loanRequest), [
            'review_remarks' => 'Initial package is ready for manager review.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::RecommendedForApproval->value);

    $this
        ->actingAs($manager)
        ->patchJson(route('spa.workflow.loan-requests.return-for-processing', $loanRequest), [
            'reason' => 'Refresh the package before final terms review.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::UnderReview->value);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), [
            ...acceptanceProcessingPayload(),
            'reason' => 'Updated after manager return.',
            'processing' => [
                ...acceptanceProcessingPayload()['processing'],
                'notarial_fee' => 275,
            ],
        ])
        ->assertOk();

    $this
        ->actingAs($processor)
        ->postJson(route('spa.workflow.loan-requests.documents.generate', $loanRequest))
        ->assertOk();

    $secondRecommendationResponse = $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.recommend-approval', $loanRequest), [
            'review_remarks' => 'Ready again after manager return.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::RecommendedForApproval->value);

    expect(
        collect($secondRecommendationResponse->json('data.documentChecklist'))
            ->where('is_applicable', true)
            ->pluck('status')
            ->unique()
            ->values()
            ->all(),
    )->toBe([
        LoanRequestDocumentReadinessStatus::GeneratedCurrent->value,
    ]);

    $revisedTermsResponse = $this
        ->actingAs($manager)
        ->patchJson(route('spa.workflow.loan-requests.approve', $loanRequest), [
            'approved_amount' => 26000,
            'approved_term' => 14,
            'approved_interest_rate' => 1.75,
            'approved_payment_frequency' => 'Monthly',
            'approval_remarks' => 'Please review the revised loan terms.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::AwaitingMemberAcceptance->value);

    expect(
        collect($revisedTermsResponse->json('data.documentChecklist'))
            ->where('is_applicable', true)
            ->pluck('status')
            ->contains(LoanRequestDocumentReadinessStatus::GeneratedStale->value),
    )->toBeTrue();

    $this
        ->actingAs($member)
        ->patchJson(route('client.loan-requests.resolve-action', $loanRequest), [
            'decision' => 'accept',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::UnderReview->value);

    $this
        ->actingAs($processor)
        ->postJson(route('spa.workflow.loan-requests.documents.generate', $loanRequest))
        ->assertOk()
        ->assertJsonPath(
            'data.documentChecklist.0.status',
            LoanRequestDocumentReadinessStatus::GeneratedCurrent->value,
        );

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.recommend-approval', $loanRequest), [
            'review_remarks' => 'Ready for final approval on accepted terms.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::RecommendedForApproval->value);

    $finalApprovalResponse = $this
        ->actingAs($manager)
        ->patchJson(route('spa.workflow.loan-requests.approve', $loanRequest), [
            'approved_amount' => 26000,
            'approved_term' => 14,
            'approved_interest_rate' => 1.75,
            'approved_payment_frequency' => 'Monthly',
            'approval_remarks' => 'Final approval after member acceptance.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Approved->value);

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::Approved)
        ->and($loanRequest->approved_amount)->toBe('26000.00')
        ->and($loanRequest->approved_term)->toBe(14)
        ->and($loanRequest->approved_interest_rate)->toBe('1.7500');

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), acceptanceProcessingPayload())
        ->assertForbidden();

    expect(
        collect($finalApprovalResponse->json('data.notificationHistory'))
            ->pluck('event_type')
            ->contains('approved_for_wibs_processing'),
    )->toBeTrue();
});

test('member can decline revised terms and processor rejection can be reopened by an authorized staff user', function (): void {
    config()->set('mail.default', 'array');

    $member = createAcceptanceMember('940101', 'Decline', 'Member');
    $processor = createAcceptanceActor([Role::LOAN_PROCESSOR]);
    $manager = createAcceptanceActor([Role::LOAN_MANAGER]);

    $declinedTermsRequest = createAcceptanceWorkflowRequest($member, $processor, [
        'status' => LoanRequestStatus::AwaitingMemberAcceptance,
        'member_action_type' => 'terms_acceptance',
        'member_action_message' => 'Please review revised terms.',
    ]);

    $this
        ->actingAs($member)
        ->patchJson(route('client.loan-requests.resolve-action', $declinedTermsRequest), [
            'decision' => 'decline',
            'reason' => 'The revised monthly payment does not work for me.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::MemberDeclinedTerms->value);

    $rejectedRequest = createAcceptanceWorkflowRequest($member, $processor, [
        'status' => LoanRequestStatus::UnderReview,
    ]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.reject-during-processing', $rejectedRequest), [
            'rejection_category' => 'Insufficient documents',
            'member_visible_reason' => 'Supporting records were incomplete.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Rejected->value);

    $this
        ->actingAs($manager)
        ->patchJson(route('spa.workflow.loan-requests.reopen', $rejectedRequest), [
            'reason' => 'Additional supporting records are now available.',
            'retain_assignment' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::UnderReview->value)
        ->assertJsonPath('data.loanRequest.assigned_officer_id', $processor->user_id);
});

/**
 * @param  list<string>  $roles
 */
function createAcceptanceActor(array $roles, ?string $acctno = null): AppUser
{
    $user = AppUser::factory()->create([
        'acctno' => $acctno,
        'phoneno' => null,
        'email_verified_at' => now(),
    ]);

    $user->roles()->sync(
        Role::query()
            ->whereIn('name', $roles)
            ->pluck('id')
            ->all(),
    );

    $twoFactorRoles = [Role::SUPERADMIN, Role::LOAN_MANAGER];
    if (! empty(array_intersect($roles, $twoFactorRoles))) {
        $user->forceFill(['two_factor_secret' => 'fakesecret', 'two_factor_confirmed_at' => now()])->save();
    }

    return $user->fresh(['roles.permissions', 'staffAccessControl']);
}

function createAcceptanceMember(
    string $acctno,
    string $firstName,
    string $lastName,
): AppUser {
    $member = createAcceptanceActor([Role::MEMBER], $acctno);

    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $member->user_id,
    ]);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => $acctno],
        [
            'fname' => $firstName,
            'lname' => $lastName,
            'bname' => sprintf('%s %s', $firstName, $lastName),
            'birthday' => '1990-04-10',
            'address' => 'Acceptance Street',
            'civilstat' => 'Single',
            'occupation' => 'Analyst',
        ],
    );

    return $member->fresh(['roles.permissions', 'userProfile']);
}

function createAcceptanceWorkflowRequest(
    AppUser $member,
    AppUser $processor,
    array $attributes = [],
): LoanRequest {
    return LoanRequest::factory()->forUser($member)->create([
        'status' => LoanRequestStatus::UnderReview,
        'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
        'assigned_officer_id' => $processor->user_id,
        'submitted_at' => now(),
        ...$attributes,
    ]);
}

/**
 * @return array<string, mixed>
 */
function acceptanceLoanRequestPayload(): array
{
    return [
        'typecode' => 'LN-P7',
        'requested_amount' => 25000,
        'requested_term' => 12,
        'loan_purpose' => 'Home improvement',
        'availment_status' => 'New',
        'undertaking_accepted' => true,
        'insurance' => [
            'beneficiary_primary_name' => 'Primary Beneficiary',
            'beneficiary_primary_relationship' => 'Sibling',
            'beneficiary_primary_birthdate' => '1995-03-21',
            'beneficiary_secondary_name' => 'Secondary Beneficiary',
            'beneficiary_secondary_relationship' => 'Parent',
            'beneficiary_secondary_birthdate' => '1970-11-04',
        ],
        'health' => [
            'health_smoker' => false,
            'health_hypertension' => false,
            'health_diabetes' => false,
            'health_recent_hospitalization' => false,
            'health_declaration_notes' => null,
        ],
        'authorization' => [
            'authorized_recipient_name' => 'Authorized Recipient',
            'authorized_recipient_relationship' => 'Sibling',
            'release_method' => 'Bank transfer',
        ],
        'banking' => [
            'payout_bank_name' => 'WIBS Cooperative Bank',
            'payout_account_name' => 'Happy Member',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'payout_atm_number' => '9876543210',
        ],
        'barangay' => [
            'barangay_name' => 'Barangay San Isidro',
            'barangay_clearance_reference' => 'BCL-2026-030',
            'barangay_locality' => 'Tagum City, Davao del Norte',
        ],
        'declarations' => [
            'declaration_existing_loans' => false,
            'declaration_pending_cases' => false,
            'declaration_truth_confirmation' => true,
            'declaration_data_privacy_consent' => true,
        ],
        'applicant' => [
            'first_name' => 'Happy',
            'last_name' => 'Member',
            'middle_name' => 'Q',
            'nickname' => null,
            'birthdate' => '1990-04-10',
            'birthplace_city' => 'Manila',
            'birthplace_province' => 'Metro Manila',
            'address1' => 'Loan Street',
            'address2' => 'Manila',
            'address3' => 'Metro Manila',
            'length_of_stay' => '5 years',
            'housing_status' => 'OWNED',
            'cell_no' => '09170000011',
            'civil_status' => 'Single',
            'educational_attainment' => 'College',
            'number_of_children' => 0,
            'spouse_name' => null,
            'spouse_age' => null,
            'spouse_cell_no' => null,
            'employment_type' => 'Private',
            'employer_business_name' => 'Acme Corp',
            'employer_business_address1' => 'Acme Street',
            'employer_business_address2' => 'Manila',
            'employer_business_address3' => 'Metro Manila',
            'telephone_no' => '021234567',
            'current_position' => 'Analyst',
            'nature_of_business' => 'Services',
            'years_in_work_business' => '4 years',
            'gross_monthly_income' => 20000,
            'payday' => '15th',
        ],
        'co_maker_1' => [
            'first_name' => 'Co',
            'last_name' => 'Maker',
            'middle_name' => 'One',
            'nickname' => null,
            'birthdate' => '1989-03-12',
            'birthplace_city' => 'Cebu',
            'birthplace_province' => 'Cebu',
            'address1' => 'Co Maker Street',
            'address2' => 'Cebu City',
            'address3' => 'Cebu',
            'length_of_stay' => '4 years',
            'housing_status' => 'RENT',
            'cell_no' => '09998887777',
            'civil_status' => 'Married',
            'educational_attainment' => 'College',
            'employment_type' => 'Government',
            'employer_business_name' => 'Co Maker Office',
            'employer_business_address1' => 'Co Maker Plaza',
            'employer_business_address2' => 'Cebu City',
            'employer_business_address3' => 'Cebu',
            'telephone_no' => '021234567',
            'current_position' => 'Clerk',
            'nature_of_business' => 'Government',
            'years_in_work_business' => '6 years',
            'gross_monthly_income' => 18000,
            'payday' => '30th',
        ],
        'co_maker_2' => [
            'first_name' => 'Second',
            'last_name' => 'Maker',
            'middle_name' => 'Two',
            'nickname' => null,
            'birthdate' => '1987-02-12',
            'birthplace_city' => 'Davao',
            'birthplace_province' => 'Davao del Sur',
            'address1' => 'Second Street',
            'address2' => 'Davao City',
            'address3' => 'Davao del Sur',
            'length_of_stay' => '2 years',
            'housing_status' => 'OWNED',
            'cell_no' => '09111112222',
            'civil_status' => 'Single',
            'educational_attainment' => 'High School',
            'employment_type' => 'Self Employed',
            'employer_business_name' => 'Second Store',
            'employer_business_address1' => 'Davao Store',
            'employer_business_address2' => 'Davao City',
            'employer_business_address3' => 'Davao del Sur',
            'telephone_no' => '021234567',
            'current_position' => 'Owner',
            'nature_of_business' => 'Retail',
            'years_in_work_business' => '8 years',
            'gross_monthly_income' => 22000,
            'payday' => '15th',
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function acceptanceProcessingPayload(): array
{
    return [
        'reason' => 'Completed underwriting package.',
        'information_source' => 'Verified staff review',
        'processing' => [
            'service_charge_rate' => 1.25,
            'insurance_rate' => 0.75,
            'insurance_required' => true,
            'insurance_term' => 12,
            'loan_security_rate' => 0.5,
            'documentary_stamp_rate' => 0.2,
            'notarial_fee' => 250,
            'penalty_rate_per_month' => 3,
            'authorization_required' => true,
            'barangay_required' => true,
            'security_required' => true,
            'loan_security_details' => 'Salary deduction authority',
            'notarial_venue' => 'Tagum City',
            'witness_one_name' => 'Witness One',
            'witness_two_name' => 'Witness Two',
            'barangay_official_name' => 'Barangay Captain',
            'barangay_official_title' => 'Punong Barangay',
        ],
        'recommended_amount' => 25000,
        'recommended_term' => 12,
        'recommended_interest_rate' => 1.5,
        'recommended_payment_frequency' => '15th & 30th',
        'recommendation_remarks' => 'Recommend approval after full review.',
    ];
}
