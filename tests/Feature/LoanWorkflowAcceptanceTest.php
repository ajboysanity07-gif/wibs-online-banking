<?php

use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestChange;
use App\Models\LoanRequestDataChange;
use App\Models\LoanRequestDocument;
use App\Models\MemberApplicationProfile;
use App\Models\Role;
use App\Models\UserProfile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Process\Process;

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

test('v2 workflow reaches recommended-for-approval with witness_two_name omitted, and witness_one_name auto-fills to the processor\'s own name', function (): void {
    config()->set('mail.default', 'array');

    $member = createAcceptanceMember('940002', 'Witness', 'Member');
    $processor = createAcceptanceActor([Role::LOAN_PROCESSOR]);

    $submitResponse = $this
        ->actingAs($member)
        ->post(route('client.loan-requests.store'), acceptanceLoanRequestPayload());

    $loanRequest = LoanRequest::query()->sole();

    $submitResponse->assertRedirect(route('client.loan-requests.show', $loanRequest));

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.claim', $loanRequest))
        ->assertOk();

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.start-review', $loanRequest), [
            'remarks' => 'Starting review without a second witness.',
        ])
        ->assertOk();

    // witness_two_name is never part of the fixture payload — the field is
    // staff-disabled and always deferred to approval time.
    $processingPayload = acceptanceProcessingPayload();
    unset($processingPayload['processing']['witness_one_name']);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), $processingPayload)
        ->assertOk();

    $this
        ->actingAs($processor)
        ->postJson(route('spa.workflow.loan-requests.documents.generate', $loanRequest))
        ->assertOk();

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.recommend-approval', $loanRequest), [
            'review_remarks' => 'Ready for manager review without Witness 2.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::RecommendedForApproval->value);

    $expectedProcessorDisplayName = $processor->adminProfile?->fullname
        ?? $processor->name
        ?? $processor->username;

    $witnessOneEntry = $loanRequest->fresh()->dataEntries()
        ->where('field_key', 'witness_one_name')
        ->sole();

    expect($witnessOneEntry->value_json['value'])->toBe($expectedProcessorDisplayName);
});

test('witness_one_name auto-fills from the linked wmaster record when the processor has no AdminProfile', function (): void {
    config()->set('mail.default', 'array');

    $member = createAcceptanceMember('940005', 'Wmaster', 'Fallback');
    $processor = createAcceptanceActor([Role::LOAN_PROCESSOR], '900321');

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => '900321'],
        [
            'fname' => 'Emma',
            'mname' => 'Alcantara',
            'lname' => 'Requilme',
            'bname' => 'Requilme, Emma Alcantara',
        ],
    );

    expect($processor->fresh('adminProfile')->adminProfile)->toBeNull();

    $submitResponse = $this
        ->actingAs($member)
        ->post(route('client.loan-requests.store'), acceptanceLoanRequestPayload());

    $loanRequest = LoanRequest::query()->sole();

    $submitResponse->assertRedirect(route('client.loan-requests.show', $loanRequest));

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.claim', $loanRequest))
        ->assertOk();

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.start-review', $loanRequest), [
            'remarks' => 'Starting review without a second witness.',
        ])
        ->assertOk();

    $processingPayload = acceptanceProcessingPayload();
    unset($processingPayload['processing']['witness_one_name']);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), $processingPayload)
        ->assertOk();

    $witnessOneEntry = $loanRequest->fresh()->dataEntries()
        ->where('field_key', 'witness_one_name')
        ->sole();

    expect($witnessOneEntry->value_json['value'])->toBe('Emma A. Requilme');
});

test('assigned_processor payload name matches the value the server would auto-fill for witness_one_name', function (): void {
    $member = createAcceptanceMember('940003', 'Prefill', 'Member');
    $processor = createAcceptanceActor([Role::LOAN_PROCESSOR]);

    $loanRequest = createAcceptanceWorkflowRequest($member, $processor);

    $showResponse = $this
        ->actingAs($processor)
        ->get(route('staff.loan-requests.show', $loanRequest))
        ->assertOk();

    $expectedProcessorDisplayName = $processor->adminProfile?->fullname
        ?? $processor->name
        ?? $processor->username;

    $showResponse->assertInertia(fn (Assert $page) => $page
        ->component('staff/loan-request-show')
        ->where('loanRequest.assigned_processor.name', $expectedProcessorDisplayName));
});

test('manager approval writes witness_two_name as the manager\'s own name and regenerates only loan_information and promissory_note', function (): void {
    config()->set('mail.default', 'array');

    $member = createAcceptanceMember('940003', 'Approval', 'Witness');
    $processor = createAcceptanceActor([Role::LOAN_PROCESSOR]);
    $manager = createAcceptanceActor([Role::LOAN_MANAGER]);

    $this
        ->actingAs($member)
        ->post(route('client.loan-requests.store'), acceptanceLoanRequestPayload());

    $loanRequest = LoanRequest::query()->sole();

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.claim', $loanRequest))
        ->assertOk();

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.start-review', $loanRequest), [
            'remarks' => 'Starting full acceptance review.',
        ])
        ->assertOk();

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), acceptanceProcessingPayload())
        ->assertOk();

    $this
        ->actingAs($processor)
        ->postJson(route('spa.workflow.loan-requests.documents.generate', $loanRequest))
        ->assertOk();

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.recommend-approval', $loanRequest), [
            'review_remarks' => 'Ready for manager review.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::RecommendedForApproval->value);

    $preApprovalVersions = LoanRequestDocument::query()
        ->where('loan_request_id', $loanRequest->id)
        ->whereIn('document_key', ['loan_information', 'plan_of_payment', 'promissory_note'])
        ->pluck('generated_version', 'document_key');

    $this
        ->actingAs($manager)
        ->patchJson(route('spa.workflow.loan-requests.approve', $loanRequest), [
            'approved_amount' => 25000,
            'approved_term' => 12,
            'approved_interest_rate' => 1.5,
            'approved_payment_frequency' => '15th & 30th',
            'approval_remarks' => 'Approved as recommended.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Approved->value);

    $expectedManagerDisplayName = $manager->adminProfile?->fullname
        ?? $manager->name
        ?? $manager->username;

    $dataChange = LoanRequestDataChange::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('field_key', 'witness_two_name')
        ->sole();

    expect($dataChange->after_value_json['value'])->toBe($expectedManagerDisplayName)
        ->and($dataChange->reason)->toBe('Witness 2 recorded upon approval');

    $documentsAfter = LoanRequestDocument::query()
        ->where('loan_request_id', $loanRequest->id)
        ->whereIn('document_key', ['loan_information', 'plan_of_payment', 'promissory_note'])
        ->get()
        ->keyBy('document_key');

    expect($documentsAfter['loan_information']->generated_version)
        ->toBe($preApprovalVersions['loan_information'] + 1)
        ->and($documentsAfter['promissory_note']->generated_version)
        ->toBe($preApprovalVersions['promissory_note'] + 1)
        ->and($documentsAfter['plan_of_payment']->generated_version)
        ->toBe($preApprovalVersions['plan_of_payment']);

    $loanInformationText = acceptancePdfText(
        Storage::disk($documentsAfter['loan_information']->generated_disk)
            ->path($documentsAfter['loan_information']->generated_path),
    );
    $promissoryNoteText = acceptancePdfText(
        Storage::disk($documentsAfter['promissory_note']->generated_disk)
            ->path($documentsAfter['promissory_note']->generated_path),
    );

    expect(mb_strtoupper($loanInformationText))->toContain(mb_strtoupper($expectedManagerDisplayName))
        ->and(mb_strtoupper($promissoryNoteText))->toContain(mb_strtoupper($expectedManagerDisplayName));
});

test('manager approval rolls back completely when document regeneration fails', function (): void {
    config()->set('mail.default', 'array');

    $member = createAcceptanceMember('940004', 'Rollback', 'Witness');
    $processor = createAcceptanceActor([Role::LOAN_PROCESSOR]);
    $manager = createAcceptanceActor([Role::LOAN_MANAGER]);

    $this
        ->actingAs($member)
        ->post(route('client.loan-requests.store'), acceptanceLoanRequestPayload());

    $loanRequest = LoanRequest::query()->sole();

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.claim', $loanRequest))
        ->assertOk();

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.start-review', $loanRequest), [
            'remarks' => 'Starting full acceptance review.',
        ])
        ->assertOk();

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.processing-details', $loanRequest), acceptanceProcessingPayload())
        ->assertOk();

    $this
        ->actingAs($processor)
        ->postJson(route('spa.workflow.loan-requests.documents.generate', $loanRequest))
        ->assertOk();

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.recommend-approval', $loanRequest), [
            'review_remarks' => 'Ready for manager review.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::RecommendedForApproval->value);

    $preApprovalVersions = LoanRequestDocument::query()
        ->where('loan_request_id', $loanRequest->id)
        ->whereIn('document_key', ['loan_information', 'promissory_note'])
        ->pluck('generated_version', 'document_key');

    // Injects a document-regeneration failure via promissory_note's Blade view rather than
    // loan_information's -- loan-information.blade.php is no longer read by the pipeline
    // since the FPDI conversion (see LOAN_INFORMATION_FPDI_CONVERSION_PLAN.md), and its FPDI
    // replacement fails a different way: a missing base artwork file is caught by a
    // preflight template-existence check and returns a controlled 422, not the 500 this test
    // needs to exercise the transactional-rollback path. promissory_note is still
    // Blade-rendered, so the same "move the view away" injection still produces a raw
    // rendering exception.
    $viewPath = resource_path('views/reports/promissory-note.blade.php');
    $brokenViewPath = resource_path('views/reports/promissory-note.blade.php.broken-for-test');

    File::move($viewPath, $brokenViewPath);

    try {
        $this
            ->actingAs($manager)
            ->patchJson(route('spa.workflow.loan-requests.approve', $loanRequest), [
                'approved_amount' => 25000,
                'approved_term' => 12,
                'approved_interest_rate' => 1.5,
                'approved_payment_frequency' => '15th & 30th',
                'approval_remarks' => 'Approved as recommended.',
            ])
            ->assertStatus(500);
    } finally {
        File::move($brokenViewPath, $viewPath);
    }

    $loanRequest->refresh();

    expect($loanRequest->status)->toBe(LoanRequestStatus::RecommendedForApproval)
        ->and($loanRequest->approved_by)->toBeNull()
        ->and($loanRequest->approved_at)->toBeNull();

    expect(
        LoanRequestChange::query()
            ->where('loan_request_id', $loanRequest->id)
            ->where('action', LoanRequestChange::ACTION_APPROVE)
            ->exists(),
    )->toBeFalse();

    expect(
        LoanRequestDataChange::query()
            ->where('loan_request_id', $loanRequest->id)
            ->where('field_key', 'witness_two_name')
            ->exists(),
    )->toBeFalse();

    $documentsAfter = LoanRequestDocument::query()
        ->where('loan_request_id', $loanRequest->id)
        ->whereIn('document_key', ['loan_information', 'promissory_note'])
        ->get()
        ->keyBy('document_key');

    expect($documentsAfter['loan_information']->generated_version)
        ->toBe($preApprovalVersions['loan_information'])
        ->and($documentsAfter['promissory_note']->generated_version)
        ->toBe($preApprovalVersions['promissory_note']);
});

test('reject during processing enforces the fixed rejection category list and resolves the Other free-text value', function (): void {
    config()->set('mail.default', 'array');

    $member = createAcceptanceMember('940102', 'Category', 'Member');
    $processor = createAcceptanceActor([Role::LOAN_PROCESSOR]);

    $freeTextRequest = createAcceptanceWorkflowRequest($member, $processor, [
        'status' => LoanRequestStatus::UnderReview,
    ]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.reject-during-processing', $freeTextRequest), [
            'rejection_category' => 'Some unlisted reason',
            'member_visible_reason' => 'Supporting records were incomplete.',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('rejection_category');

    $otherWithoutTextRequest = createAcceptanceWorkflowRequest($member, $processor, [
        'status' => LoanRequestStatus::UnderReview,
    ]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.reject-during-processing', $otherWithoutTextRequest), [
            'rejection_category' => 'Other',
            'member_visible_reason' => 'Supporting records were incomplete.',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('rejection_category_other');

    $otherWithTextRequest = createAcceptanceWorkflowRequest($member, $processor, [
        'status' => LoanRequestStatus::UnderReview,
    ]);

    $this
        ->actingAs($processor)
        ->patchJson(route('spa.workflow.loan-requests.reject-during-processing', $otherWithTextRequest), [
            'rejection_category' => 'Other',
            'rejection_category_other' => 'Cooperative membership lapsed',
            'member_visible_reason' => 'Supporting records were incomplete.',
        ])
        ->assertOk()
        ->assertJsonPath('data.loanRequest.status', LoanRequestStatus::Rejected->value);

    expect($otherWithTextRequest->fresh()->review_rejection_category)
        ->toBe('Cooperative membership lapsed');
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
            'rejection_category' => 'Incomplete documentation',
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

    MemberApplicationProfile::factory()->completed()->withLoanPrerequisites()->create([
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
            'health_smoking_status' => 'none',
            'health_hypertension' => false,
        ],
        'health_glapi' => [
            'gl_health_q02e_diabetes' => false,
            'health_recent_hospitalization' => false,
        ],
        'banking' => [
            'payout_bank_name' => 'WIBS Cooperative Bank',
            'payout_account_name' => 'Happy Member',
            'payout_account_number' => '1234567890',
            'payout_account_type' => 'Savings',
            'payout_atm_number' => '9876543210',
            'release_method' => 'Bank Transfer',
        ],
        'barangay' => [
            'barangay_official_designation' => null,
            'barangay_agency_name' => null,
            'barangay_agency_address' => null,
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
            'insurance_term' => 12,
            'loan_security_rate' => 0.5,
            'savings_rate' => 0.5,
            'documentary_stamp_rate' => 0.2,
            'notarial_fee' => 250,
            'penalty_rate_per_month' => 3,
            'notarial_venue' => 'Tagum City',
            'witness_one_name' => 'Witness One',
            'barangay_official_name' => 'Barangay Captain',
            'barangay_official_title' => 'Punong Barangay',
            'guaranteed_net_take_home_pay' => 15000,
        ],
        'recommended_amount' => 25000,
        'recommended_term' => 12,
        'recommended_interest_rate' => 1.5,
        'recommended_payment_frequency' => '15th & 30th',
        'recommendation_remarks' => 'Recommend approval after full review.',
    ];
}

function acceptancePdfText(string $absolutePath): string
{
    $process = new Process(['pdftotext', $absolutePath, '-']);
    $process->run();

    return $process->getOutput();
}
