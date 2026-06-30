<?php

use App\LoanRequestDocumentKey;
use App\LoanRequestDocumentReadinessStatus;
use App\LoanRequestStatus;
use App\LoanRequestWorkflowVersion;
use App\Models\AppUser;
use App\Models\LoanRequest;
use App\Models\LoanRequestDocument;
use App\Models\Role;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

beforeEach(function (): void {
    Role::ensureWorkflowDefaults();
});

test('assigned loan processor can preview print and download generated pdf documents', function (): void {
    $processor = createGeneratedDocumentStaffUser(Role::LOAN_PROCESSOR);
    $loanRequest = createGeneratedDocumentLoanRequest($processor);
    $document = createGeneratedPdfDocument(
        $loanRequest,
        LoanRequestDocumentKey::LoanInformation,
    );

    $previewResponse = $this
        ->actingAs($processor)
        ->get(
            route('staff.loan-requests.documents.generated', [
                'loanRequest' => $loanRequest,
                'documentKey' => $document->document_key,
                'preview' => 1,
            ]),
        );

    $previewResponse
        ->assertOk()
        ->assertHeaderContains('content-type', 'application/pdf');

    $printResponse = $this
        ->actingAs($processor)
        ->get(
            route('staff.loan-requests.documents.generated', [
                'loanRequest' => $loanRequest,
                'documentKey' => $document->document_key,
                'print' => 1,
            ]),
        );

    $printResponse
        ->assertOk()
        ->assertHeaderContains('content-type', 'application/pdf');

    $downloadResponse = $this
        ->actingAs($processor)
        ->get(
            route('staff.loan-requests.documents.generated', [
                'loanRequest' => $loanRequest,
                'documentKey' => $document->document_key,
                'download' => 1,
            ]),
        );

    $downloadResponse
        ->assertOk()
        ->assertDownload();
});

test('loan manager can preview an assigned processors generated pdf package', function (): void {
    $processor = createGeneratedDocumentStaffUser(Role::LOAN_PROCESSOR);
    $manager = createGeneratedDocumentStaffUser(Role::LOAN_MANAGER);
    $loanRequest = createGeneratedDocumentLoanRequest($processor);
    $document = createGeneratedPdfDocument(
        $loanRequest,
        LoanRequestDocumentKey::DisclosureStatement,
    );

    $this
        ->actingAs($manager)
        ->get(
            route('staff.loan-requests.documents.generated', [
                'loanRequest' => $loanRequest,
                'documentKey' => $document->document_key,
                'preview' => 1,
            ]),
        )
        ->assertOk()
        ->assertHeaderContains('content-type', 'application/pdf');
});

test('unassigned loan processor cannot access another processors generated workbook package', function (): void {
    $assignedProcessor = createGeneratedDocumentStaffUser(Role::LOAN_PROCESSOR);
    $otherProcessor = createGeneratedDocumentStaffUser(Role::LOAN_PROCESSOR);
    $loanRequest = createGeneratedDocumentLoanRequest($assignedProcessor);

    createGeneratedWorkbookDocument(
        $loanRequest,
        LoanRequestDocumentKey::DisclosureStatement,
        'Restricted Preview',
        'Disclosure Statement',
    );

    $this
        ->actingAs($otherProcessor)
        ->get(
            route('staff.loan-requests.documents.generated', [
                'loanRequest' => $loanRequest,
                'documentKey' => LoanRequestDocumentKey::DisclosureStatement->value,
                'preview' => 1,
            ]),
        )
        ->assertForbidden();
});

function createGeneratedDocumentStaffUser(string $roleName): AppUser
{
    $user = AppUser::factory()->create([
        'email_verified_at' => now(),
    ]);

    Role::attachNamedRole($user, $roleName);

    if (in_array($roleName, [Role::SUPERADMIN, Role::LOAN_MANAGER], true)) {
        $user->forceFill(['two_factor_secret' => 'fakesecret', 'two_factor_confirmed_at' => now()])->save();
    }

    return $user->fresh(['roles.permissions', 'staffAccessControl']);
}

function createGeneratedDocumentLoanRequest(AppUser $processor): LoanRequest
{
    $member = AppUser::factory()->create([
        'acctno' => '900001',
        'email_verified_at' => now(),
    ]);
    Role::attachNamedRole($member, Role::MEMBER);

    return LoanRequest::factory()
        ->forUser($member)
        ->create([
            'status' => LoanRequestStatus::UnderReview,
            'workflow_version' => LoanRequestWorkflowVersion::DocumentWorkflowV2,
            'submitted_at' => now(),
            'assigned_officer_id' => $processor->user_id,
        ]);
}

function createGeneratedPdfDocument(
    LoanRequest $loanRequest,
    LoanRequestDocumentKey $documentKey,
): LoanRequestDocument {
    $relativePath = sprintf(
        'loan-request-documents/%d/%s/test-preview.pdf',
        $loanRequest->id,
        $documentKey->value,
    );
    $absolutePath = Storage::disk('local')->path($relativePath);

    File::ensureDirectoryExists(dirname($absolutePath));
    File::put($absolutePath, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R>>endobj\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n0\n%%EOF");

    return LoanRequestDocument::query()->create([
        'loan_request_id' => $loanRequest->id,
        'document_key' => $documentKey->value,
        'is_applicable' => true,
        'readiness_status' => LoanRequestDocumentReadinessStatus::GeneratedCurrent,
        'template_version' => 'test-preview-v1',
        'source_hash' => sha1($documentKey->value.'-'.$loanRequest->id),
        'source_version' => 1,
        'generated_version' => 1,
        'generated_disk' => 'local',
        'generated_path' => $relativePath,
        'generated_filename' => $documentKey->value.'.pdf',
        'generated_mime_type' => 'application/pdf',
        'generated_size_bytes' => File::size($absolutePath),
        'generated_by' => $loanRequest->assigned_officer_id,
        'generated_at' => now(),
    ]);
}

function createGeneratedWorkbookDocument(
    LoanRequest $loanRequest,
    LoanRequestDocumentKey $documentKey,
    string $cellValue,
    string $sheetTitle = 'Loan Information',
): LoanRequestDocument {
    $relativePath = sprintf(
        'loan-request-documents/%d/%s/test-preview.xlsx',
        $loanRequest->id,
        $documentKey->value,
    );
    $absolutePath = Storage::disk('local')->path($relativePath);

    File::ensureDirectoryExists(dirname($absolutePath));

    $spreadsheet = new Spreadsheet;
    $worksheet = $spreadsheet->getActiveSheet();
    $worksheet->setTitle($sheetTitle);
    $worksheet->setCellValue('A1', $cellValue);
    $worksheet->setCellValue('B2', $sheetTitle);
    IOFactory::createWriter($spreadsheet, 'Xlsx')->save($absolutePath);
    $spreadsheet->disconnectWorksheets();

    return LoanRequestDocument::query()->create([
        'loan_request_id' => $loanRequest->id,
        'document_key' => $documentKey->value,
        'is_applicable' => true,
        'readiness_status' => LoanRequestDocumentReadinessStatus::GeneratedCurrent,
        'template_version' => 'test-preview-v1',
        'source_hash' => sha1($documentKey->value.'-'.$loanRequest->id),
        'source_version' => 1,
        'generated_version' => 1,
        'generated_disk' => 'local',
        'generated_path' => $relativePath,
        'generated_filename' => $documentKey->value.'.xlsx',
        'generated_mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'generated_size_bytes' => File::size($absolutePath),
        'generated_by' => $loanRequest->assigned_officer_id,
        'generated_at' => now(),
    ]);
}
