<?php

use App\LoanRequestDocumentKey;
use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDocument;
use App\Models\Role;
use App\Policies\LoanRequestPolicy;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
use App\Services\LoanRequests\LoanRequestDocumentWorkflowService;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();

    // See LoanRequestGenerateDocumentsBulkTest for why the real renderer is
    // stubbed here -- these tests exercise authorization/business rules
    // around regeneration, not the TCPDF rendering pipeline itself.
    $mock = Mockery::mock(app(ApprovedLoanDocumentService::class))->makePartial();
    $mock->shouldReceive('generateToPathForKey')
        ->andReturnUsing(function (
            LoanRequest $loanRequest,
            LoanRequestDocumentKey $documentKey,
            string $outputPath,
            ?array $documentData = null,
        ): array {
            File::ensureDirectoryExists(dirname($outputPath));
            File::put($outputPath, 'stub-document-content');

            return [
                'mime_type' => 'application/pdf',
                'filename' => basename($outputPath),
                'sheet_name' => null,
            ];
        });
    $this->app->instance(ApprovedLoanDocumentService::class, $mock);
});

function approvedDocumentRegenerationLoanRequest(): array
{
    $assignedProcessor = AppUser::factory()->create([
        'email_verified_at' => now(),
    ]);
    Role::attachNamedRole($assignedProcessor, Role::LOAN_PROCESSOR);
    $assignedProcessor = $assignedProcessor->fresh(['roles.permissions', 'staffAccessControl']);

    $otherProcessor = AppUser::factory()->create([
        'email_verified_at' => now(),
    ]);
    Role::attachNamedRole($otherProcessor, Role::LOAN_PROCESSOR);
    $otherProcessor = $otherProcessor->fresh(['roles.permissions', 'staffAccessControl']);

    $manager = AppUser::factory()->create([
        'email_verified_at' => now(),
    ]);
    Role::attachNamedRole($manager, Role::LOAN_MANAGER);
    $manager = $manager->fresh(['roles.permissions', 'staffAccessControl']);

    $member = AppUser::factory()->create([
        'acctno' => '900003',
        'email_verified_at' => now(),
    ]);
    Role::attachNamedRole($member, Role::MEMBER);

    $loanRequest = LoanRequest::factory()
        ->forUser($member)
        ->create([
            'status' => LoanRequestStatus::UnderReview,
            'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
            'submitted_at' => now(),
            'assigned_officer_id' => $assignedProcessor->user_id,
        ]);

    return [$loanRequest, $assignedProcessor, $otherProcessor, $manager];
}

test('a loan.review holder who is not the assigned processor may generate documents once the request is approved', function (): void {
    [$loanRequest, , $otherProcessor] = approvedDocumentRegenerationLoanRequest();
    $loanRequest->update(['status' => LoanRequestStatus::Approved]);

    $policy = app(LoanRequestPolicy::class);

    expect($policy->generateDocuments($otherProcessor, $loanRequest))->toBeTrue();
});

test('a loan.review holder without assignment cannot generate documents before approval', function (): void {
    [$loanRequest, , $otherProcessor] = approvedDocumentRegenerationLoanRequest();

    $policy = app(LoanRequestPolicy::class);

    expect($policy->generateDocuments($otherProcessor, $loanRequest))->toBeFalse();
});

test('a manager without loan.review permission cannot generate documents even after approval', function (): void {
    [$loanRequest, , , $manager] = approvedDocumentRegenerationLoanRequest();
    $loanRequest->update(['status' => LoanRequestStatus::Approved]);

    $policy = app(LoanRequestPolicy::class);

    expect($policy->generateDocuments($manager, $loanRequest))->toBeFalse();
});

test('a loan.approve holder who is not the request owner may generate documents while recommended for approval', function (): void {
    [$loanRequest, , , $manager] = approvedDocumentRegenerationLoanRequest();
    $loanRequest->update(['status' => LoanRequestStatus::RecommendedForApproval]);

    $policy = app(LoanRequestPolicy::class);

    expect($policy->generateDocuments($manager, $loanRequest))->toBeTrue();
});

test('a loan.approve holder cannot generate documents on their own request while recommended for approval', function (): void {
    [, , , $manager] = approvedDocumentRegenerationLoanRequest();

    $ownRequest = LoanRequest::factory()
        ->forUser($manager)
        ->create([
            'status' => LoanRequestStatus::RecommendedForApproval,
            'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
            'submitted_at' => now(),
        ]);

    $policy = app(LoanRequestPolicy::class);

    expect($policy->generateDocuments($manager, $ownRequest))->toBeFalse();
});

test('a finalized document cannot be regenerated once the request is approved', function (): void {
    [$loanRequest, $assignedProcessor, $otherProcessor] = approvedDocumentRegenerationLoanRequest();

    $service = app(LoanRequestDocumentWorkflowService::class);

    $service->generateDocument(
        $loanRequest,
        LoanRequestDocumentKey::LoanSecurityAgreement,
        $assignedProcessor,
    );

    $loanRequest->update(['status' => LoanRequestStatus::Approved]);

    // Simulate the real-world case: the "regenerate" request is a fresh HTTP
    // request with its own freshly bound model, not the same PHP object that
    // just performed the first generation (whose `documents` relation would
    // otherwise still be cached from before that first save).
    $loanRequest = $loanRequest->fresh();

    expect(fn () => $service->generateDocument(
        $loanRequest,
        LoanRequestDocumentKey::LoanSecurityAgreement,
        $otherProcessor,
    ))->toThrow(ValidationException::class);
});

test('a stale document can still be regenerated once the request is approved', function (): void {
    [$loanRequest, $assignedProcessor, $otherProcessor] = approvedDocumentRegenerationLoanRequest();

    $service = app(LoanRequestDocumentWorkflowService::class);

    $service->generateDocument(
        $loanRequest,
        LoanRequestDocumentKey::LoanSecurityAgreement,
        $assignedProcessor,
    );

    $loanRequest->update(['status' => LoanRequestStatus::Approved]);

    LoanRequestDocument::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('document_key', LoanRequestDocumentKey::LoanSecurityAgreement->value)
        ->update(['readiness_status' => LoanRequestDocumentReadinessStatus::GeneratedStale]);

    $loanRequest = $loanRequest->fresh();

    $document = $service->generateDocument(
        $loanRequest,
        LoanRequestDocumentKey::LoanSecurityAgreement,
        $otherProcessor,
    );

    expect($document->readiness_status)->toBe(LoanRequestDocumentReadinessStatus::GeneratedCurrent);
});
