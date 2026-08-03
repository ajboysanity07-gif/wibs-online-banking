<?php

use App\LoanRequestDocumentKey;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\Role;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
use App\Services\LoanRequests\LoanRequestDocumentWorkflowService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();

    // Real rendering (TCPDF/FPDI) is out of scope for locking tests and, in
    // this environment, a document with bold text (affidavit_undertaking)
    // hits a pre-existing font-registration gap that makes TCPDF call
    // die() directly (see project memory) instead of throwing — which would
    // silently kill the whole test process. generateAll() attempts to
    // render every applicable document on each run regardless of setup, so
    // that gap would otherwise be hit incidentally. Stub the actual render
    // so these tests exercise the real locking/business-validation logic
    // without touching that gap.
    // Mockery::mock() on a class name never runs the constructor, leaving
    // this service's typed constructor-injected properties uninitialized —
    // buildDocumentData() (a real, unstubbed method still called before
    // generateToPathForKey()) then fatals on first use. Wrap an
    // already-constructed real instance instead, so only the one rendering
    // method is stubbed and everything else keeps working normally.
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

function generateDocumentsBulkTestLoanRequest(): array
{
    $processor = AppUser::factory()->create([
        'email_verified_at' => now(),
    ]);
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);
    $processor = $processor->fresh(['roles.permissions', 'staffAccessControl']);

    $member = AppUser::factory()->create([
        'acctno' => '900002',
        'email_verified_at' => now(),
    ]);
    Role::attachNamedRole($member, Role::MEMBER);

    $loanRequest = LoanRequest::factory()
        ->forUser($member)
        ->create([
            'status' => LoanRequestStatus::UnderReview,
            'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
            'submitted_at' => now(),
            'assigned_officer_id' => $processor->user_id,
        ]);

    return [$loanRequest, $processor];
}

test('generate documents without a document key triggers bulk generation instead of failing validation', function (): void {
    $processor = AppUser::factory()->create([
        'email_verified_at' => now(),
    ]);
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);
    $processor = $processor->fresh(['roles.permissions', 'staffAccessControl']);

    $member = AppUser::factory()->create([
        'acctno' => '900002',
        'email_verified_at' => now(),
    ]);
    Role::attachNamedRole($member, Role::MEMBER);

    $loanRequest = LoanRequest::factory()
        ->forUser($member)
        ->create([
            'status' => LoanRequestStatus::UnderReview,
            'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
            'submitted_at' => now(),
            'assigned_officer_id' => $processor->user_id,
        ]);

    $response = $this
        ->actingAs($processor)
        ->postJson(
            route('spa.workflow.loan-requests.documents.generate', [
                'loanRequest' => $loanRequest,
            ]),
            [],
        );

    $response->assertOk();
    $response->assertJsonMissingValidationErrors('document_key');
    expect($response->json('data.documentResults'))->toBeArray()->not->toBeEmpty();
});

test('a document with a busy lock on a different key is not blocked', function (): void {
    [$loanRequest, $processor] = generateDocumentsBulkTestLoanRequest();

    $busyLock = Cache::lock(
        sprintf('loan-workflow:documents:%d:%s', $loanRequest->id, LoanRequestDocumentKey::Grepalife->value),
        30,
    );
    expect($busyLock->get())->toBeTrue();

    $service = app(LoanRequestDocumentWorkflowService::class);

    $document = $service->generateDocument(
        $loanRequest,
        LoanRequestDocumentKey::LoanSecurityAgreement,
        $processor,
    );

    expect($document->document_key)->toBe(LoanRequestDocumentKey::LoanSecurityAgreement->value);

    $busyLock->release();
});

test('a document with a busy lock on the same key is blocked', function (): void {
    [$loanRequest, $processor] = generateDocumentsBulkTestLoanRequest();

    $busyLock = Cache::lock(
        sprintf('loan-workflow:documents:%d:%s', $loanRequest->id, LoanRequestDocumentKey::Grepalife->value),
        30,
    );
    expect($busyLock->get())->toBeTrue();

    $service = app(LoanRequestDocumentWorkflowService::class);

    expect(fn () => $service->generateDocument(
        $loanRequest,
        LoanRequestDocumentKey::Grepalife,
        $processor,
    ))->toThrow(ValidationException::class);

    $busyLock->release();
});

test('generateAll takes the umbrella lock and is blocked while it is held', function (): void {
    [$loanRequest, $processor] = generateDocumentsBulkTestLoanRequest();

    $busyLock = Cache::lock(
        sprintf('loan-workflow:documents:%d', $loanRequest->id),
        30,
    );
    expect($busyLock->get())->toBeTrue();

    $service = app(LoanRequestDocumentWorkflowService::class);

    expect(fn () => $service->generateAll($loanRequest, $processor))
        ->toThrow(ValidationException::class);

    $busyLock->release();
});

test('generateAll skips a document that is busy elsewhere without aborting the rest of the batch', function (): void {
    [$loanRequest, $processor] = generateDocumentsBulkTestLoanRequest();

    $busyDocumentKey = LoanRequestDocumentKey::LoanSecurityAgreement;

    $busyLock = Cache::lock(
        sprintf('loan-workflow:documents:%d:%s', $loanRequest->id, $busyDocumentKey->value),
        30,
    );
    expect($busyLock->get())->toBeTrue();

    $service = app(LoanRequestDocumentWorkflowService::class);

    $results = $service->generateAll($loanRequest, $processor);

    $busyLock->release();

    expect($results)->toBeArray()->not->toBeEmpty();

    $resultsByKey = collect($results)->keyBy('key');

    expect($resultsByKey->has($busyDocumentKey->value))->toBeTrue();
    expect($resultsByKey->get($busyDocumentKey->value)['message'])
        ->toBe('Document generation is already running for this document.');

    foreach ($resultsByKey as $key => $result) {
        if ($key === $busyDocumentKey->value) {
            continue;
        }

        expect($result['message'])
            ->not->toBe('Document generation is already running for this document.');
    }
});
