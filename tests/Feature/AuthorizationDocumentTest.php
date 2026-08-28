<?php

use App\LoanReleaseMethod;
use App\LoanRequestDocumentKey;
use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Models\LoanRequestPerson;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
use App\Services\LoanRequests\LoanRequestDocumentCatalog;
use Illuminate\Support\Facades\File;

function authorizationDocumentCreateApprovedLoanRequestWithApplicant(): LoanRequest
{
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now()->subDay(),
        'reviewed_at' => now(),
        'approved_amount' => 25000,
        'approved_term' => 12,
        'approved_interest_rate' => 0.36,
        'recommended_payment_frequency' => '15th & 30th',
    ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Sample',
            'middle_name' => 'Q',
            'last_name' => 'Member',
            'address1' => '123 Loan Street',
            'address2' => 'Loan City',
            'address3' => 'Loan Province',
        ]);

    return $loanRequest;
}

function authorizationDocumentPersistDataEntry(
    LoanRequest $loanRequest,
    string $fieldKey,
    string $valueType,
    mixed $value,
): void {
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

function authorizationDocumentReadDownloadedFileContent(\Illuminate\Testing\TestResponse $response): string
{
    $baseResponse = $response->baseResponse;
    $path = $baseResponse->getFile()->getPathname();
    $content = file_get_contents($path);

    if (! is_string($content)) {
        throw new RuntimeException('Unable to read downloaded file content.');
    }

    return $content;
}

test('authorization document is not applicable without a bank release method', function () {
    $loanRequest = authorizationDocumentCreateApprovedLoanRequestWithApplicant();
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(
        LoanRequestDocumentKey::Authorization,
        $loanRequest->fresh(),
        [],
    ))->toBeFalse();

    expect($catalog->isApplicable(
        LoanRequestDocumentKey::Authorization,
        $loanRequest->fresh(),
        ['release_method' => LoanReleaseMethod::Atm->value],
    ))->toBeFalse();
});

test('authorization document becomes applicable when the release method is bank transfer', function () {
    $loanRequest = authorizationDocumentCreateApprovedLoanRequestWithApplicant();
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(
        LoanRequestDocumentKey::Authorization,
        $loanRequest->fresh(),
        ['release_method' => LoanReleaseMethod::BankTransfer->value],
    ))->toBeTrue();
});

test('authorization field map prints a dynamic bank name and the loan security amount, never a static bank name or release method', function () {
    $fields = collect((new \App\Services\LoanRequests\PdfFieldMaps\AuthorizationPdfFieldMap)->fields());

    expect($fields->contains(
        fn (array $field): bool => ($field['value'] ?? null) === 'authorization.payout_bank_name',
    ))->toBeTrue('Expected the bank name to be sourced from a dynamic field, not hardcoded.');

    expect($fields->contains(
        fn (array $field): bool => ($field['value'] ?? null) === 'loan.loan_security_amount',
    ))->toBeTrue();

    expect($fields->contains(
        fn (array $field): bool => ($field['value'] ?? null) === 'loan.approved_amount',
    ))->toBeFalse('The AZ paragraph blank is the loan security amount, not the raw approved amount.');

    expect($fields->contains(
        fn (array $field): bool => ($field['value'] ?? null) === 'authorization.release_method',
    ))->toBeFalse('release_method is not printed anywhere in the real AZ paragraph.');
});

test('authorization base template says MRDINC, not MRDIC', function () {
    $templatePath = storage_path('app/templates/approved-loan-documents/pdf/authorization.pdf');

    expect(File::exists($templatePath))->toBeTrue();

    $content = File::get($templatePath);

    expect($content)->toContain('MRDINC')
        ->not->toContain('MRDIC');
});

test('authorization document downloads as a real pdf and prints the borrower\'s actual selected bank, not a hardcoded one', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = authorizationDocumentCreateApprovedLoanRequestWithApplicant();
    authorizationDocumentPersistDataEntry($loanRequest, 'release_method', 'string', LoanReleaseMethod::BankTransfer->value);
    $loanRequest->forceFill([
        'account_snapshot_json' => [
            'release' => [
                'bank_name' => 'Land Bank of the Philippines',
                'account_number' => 'SA-5217-0462-21',
            ],
        ],
    ])->save();

    $service = app(ApprovedLoanDocumentService::class);
    $buildDocumentData = Closure::bind(
        fn (LoanRequest $record): array => $this->buildDocumentData($record),
        $service,
        ApprovedLoanDocumentService::class,
    );
    $documentData = $buildDocumentData($loanRequest->fresh());

    expect($documentData['authorization']['payout_bank_name'])->toBe('Land Bank of the Philippines')
        ->and($documentData['authorization']['payout_bank_name'])->not->toBe('Enterprise Bank, Inc.');

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.authorization', $loanRequest));

    $content = authorizationDocumentReadDownloadedFileContent($response);

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'application/pdf');
    expect($content)->toStartWith('%PDF');
});
