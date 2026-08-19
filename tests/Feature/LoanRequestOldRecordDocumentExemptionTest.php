<?php

use App\LoanRequestDocumentKey;
use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Models\LoanRequestDocument;
use App\Models\LoanRequestPerson;
use App\Models\Role;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
use App\Services\LoanRequests\LoanRequestDocumentWorkflowService;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();

    // Real rendering (TCPDF/FPDI) is out of scope here and, in this
    // environment, a document with bold text hits a pre-existing
    // font-registration gap that makes TCPDF call die() directly instead of
    // throwing. Stub the renderer so these tests exercise the real
    // readiness/blocker logic without touching that gap. Wrap an already
    // constructed instance so buildDocumentData() (called before rendering)
    // keeps working normally.
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

/**
 * @param  array<string, array{0:string,1:mixed}>  $fields
 */
function oldRecordExemptionPersistDataEntries(LoanRequest $loanRequest, array $fields): void
{
    foreach ($fields as $fieldKey => [$valueType, $value]) {
        LoanRequestDataEntry::query()->updateOrCreate(
            [
                'loan_request_id' => $loanRequest->id,
                'field_key' => $fieldKey,
            ],
            [
                'section_key' => 'processing',
                'owner_type' => 'staff',
                'value_type' => $valueType,
                'value_json' => ['value' => $value],
                'is_sensitive' => false,
                'confirmed_by_member' => false,
                'confirmed_by_member_at' => null,
            ],
        );
    }
}

function oldRecordExemptionChecklistEntry(LoanRequest $loanRequest, LoanRequestDocumentKey $documentKey): array
{
    $checklist = app(LoanRequestDocumentWorkflowService::class)->serializeChecklist($loanRequest);

    return collect($checklist)->firstWhere('key', $documentKey->value);
}

/**
 * Builds a document_workflow_v2 request missing only the disclosure statement's
 * notarial_fee required field, with everything else the workbook needs in place.
 *
 * @return array{0: LoanRequest, 1: AppUser}
 */
function oldRecordExemptionLoanRequest(string $submittedAt, ?string $acctno = null): array
{
    static $counter = 0;
    $counter += 1;

    $processor = AppUser::factory()->create([
        'email_verified_at' => now(),
    ]);
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);

    $member = AppUser::factory()->create([
        'acctno' => $acctno ?? sprintf('90%05d', 3000 + $counter),
        'email_verified_at' => now(),
    ]);
    Role::attachNamedRole($member, Role::MEMBER);

    $loanRequest = LoanRequest::factory()
        ->forUser($member)
        ->create([
            'status' => LoanRequestStatus::UnderReview,
            'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
            'submitted_at' => $submittedAt,
            'assigned_officer_id' => $processor->user_id,
            'recommended_amount' => 24000,
            'recommended_term' => 10,
            'recommended_interest_rate' => 1.5,
            'recommended_payment_frequency' => 'Monthly',
        ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create(['gross_monthly_income' => 15000]);

    oldRecordExemptionPersistDataEntries($loanRequest, [
        'service_charge_rate' => ['number', 5],
        'insurance_rate' => ['number', 1.5],
        'insurance_term' => ['number', 12],
        'loan_security_rate' => ['number', 0.5],
        'documentary_stamp_rate' => ['number', 1],
        'penalty_rate_per_month' => ['number', 2],
    ]);

    return [$loanRequest, $processor];
}

test('an old record can generate the disclosure statement even when a required field is missing', function (): void {
    [$loanRequest, $processor] = oldRecordExemptionLoanRequest('2026-08-03 00:00:00');

    $entry = oldRecordExemptionChecklistEntry($loanRequest, LoanRequestDocumentKey::DisclosureStatement);

    expect($entry['is_applicable'])->toBeTrue()
        ->and($entry['status'])->toBe(LoanRequestDocumentReadinessStatus::ReadyToGenerate->value)
        ->and($entry['blockers'])->toBe([])
        ->and($entry['is_relaxed_old_record'])->toBeTrue()
        ->and($entry['manual_fill_fields'])->toContain('Notarial fee');

    $service = app(LoanRequestDocumentWorkflowService::class);

    $document = $service->generateDocument(
        $loanRequest,
        LoanRequestDocumentKey::DisclosureStatement,
        $processor,
    );

    expect($document->document_key)->toBe(LoanRequestDocumentKey::DisclosureStatement->value)
        ->and($document->readiness_status)->toBe(LoanRequestDocumentReadinessStatus::GeneratedCurrent);
});

test('a new record is still blocked from generating the disclosure statement when a required field is missing', function (): void {
    [$loanRequest, $processor] = oldRecordExemptionLoanRequest(now());

    $entry = oldRecordExemptionChecklistEntry($loanRequest, LoanRequestDocumentKey::DisclosureStatement);

    expect($entry['is_applicable'])->toBeTrue()
        ->and($entry['status'])->toBe(LoanRequestDocumentReadinessStatus::Incomplete->value)
        ->and($entry['blockers'])->toContain('Notarial fee is required.')
        ->and($entry['is_relaxed_old_record'])->toBeFalse()
        ->and($entry['manual_fill_fields'])->toBe([]);

    $service = app(LoanRequestDocumentWorkflowService::class);

    expect(fn () => $service->generateDocument(
        $loanRequest,
        LoanRequestDocumentKey::DisclosureStatement,
        $processor,
    ))->toThrow(ValidationException::class);
});

test('document-readiness data blockers are lifted for old records but still enforced for new records', function (): void {
    [$oldRecord] = oldRecordExemptionLoanRequest('2026-08-03 00:00:00');
    [$newRecord] = oldRecordExemptionLoanRequest(now());

    $service = app(LoanRequestDocumentWorkflowService::class);

    $oldBlockers = $service->blockersForRecommendation($oldRecord);
    $newBlockers = $service->blockersForRecommendation($newRecord);
    $hasNotarialBlocker = static fn (array $blockers): bool => collect($blockers)->contains(
        static fn (string $message): bool => str_contains($message, 'Notarial fee is required.'),
    );

    expect($hasNotarialBlocker($oldBlockers))->toBeFalse()
        ->and($hasNotarialBlocker($newBlockers))->toBeTrue();
});

test('document applicability is unchanged for old records', function (): void {
    [$loanRequest] = oldRecordExemptionLoanRequest('2026-08-03 00:00:00');

    $entry = oldRecordExemptionChecklistEntry($loanRequest, LoanRequestDocumentKey::UndertakingBarangay);

    expect($entry['is_applicable'])->toBeFalse()
        ->and($entry['status'])->toBe(LoanRequestDocumentReadinessStatus::NotApplicable->value);
});

test('legacy_v1 workflow records keep the legacy data blocker regardless of submission date', function (): void {
    $processor = AppUser::factory()->create(['email_verified_at' => now()]);
    Role::attachNamedRole($processor, Role::LOAN_PROCESSOR);

    $member = AppUser::factory()->create(['acctno' => '900004', 'email_verified_at' => now()]);
    Role::attachNamedRole($member, Role::MEMBER);

    $loanRequest = LoanRequest::factory()
        ->forUser($member)
        ->create([
            'status' => LoanRequestStatus::UnderReview,
            'workflow_version' => LoanRequestWorkflowVersion::LegacyV1,
            'submitted_at' => '2026-08-03 00:00:00',
            'assigned_officer_id' => $processor->user_id,
        ]);

    $entry = oldRecordExemptionChecklistEntry($loanRequest, LoanRequestDocumentKey::DisclosureStatement);

    expect($entry['status'])->toBe(LoanRequestDocumentReadinessStatus::LegacyDataIncomplete->value)
        ->and($entry['blockers'])->toContain('Historical document data unavailable.');
});

test('orphaned document rows for removed keys are ignored by the checklist and recommendation blockers', function (): void {
    [$loanRequest] = oldRecordExemptionLoanRequest('2026-08-03 00:00:00');

    LoanRequestDocument::factory()
        ->create([
            'loan_request_id' => $loanRequest->id,
            'document_key' => 'atm_salary_deduction_waiver',
            'readiness_status' => LoanRequestDocumentReadinessStatus::ReadyToGenerate,
        ]);

    $service = app(LoanRequestDocumentWorkflowService::class);

    expect(fn () => $service->blockersForRecommendation($loanRequest))
        ->not->toThrow(ValueError::class);

    $checklistKeys = collect($service->serializeChecklist($loanRequest))
        ->pluck('key')
        ->all();

    expect($checklistKeys)->not->toContain('atm_salary_deduction_waiver');
});
