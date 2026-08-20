<?php

use App\LoanRequestDocumentKey;
use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Models\LoanRequestPerson;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
use App\Services\LoanRequests\LoanRequestDataService;
use App\Services\LoanRequests\LoanRequestDocumentCatalog;
use App\Services\LoanRequests\LoanRequestDocumentWorkflowService;
use App\Services\LoanRequests\PdfFieldMaps\DepedSalaryDeductionWaiverPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\PensionDeductionWaiverPdfFieldMap;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * @return array<string, mixed>
 */
function waiverDocumentsCreateApprovedLoanRequestWithApplicant(array $applicantAttributes = []): LoanRequest
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
        ->create(array_merge([
            'first_name' => 'Sample',
            'middle_name' => 'Q',
            'last_name' => 'Member',
            'address1' => '123 Loan Street',
            'address2' => 'Loan City',
            'address3' => 'Loan Province',
        ], $applicantAttributes));

    return $loanRequest;
}

function waiverDocumentsPersistDataEntry(
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

/**
 * @return array<string, mixed>
 */
function waiverDocumentsBuildDocumentData(LoanRequest $loanRequest): array
{
    $service = app(ApprovedLoanDocumentService::class);
    $buildDocumentData = Closure::bind(
        fn (LoanRequest $record): array => $this->buildDocumentData($record),
        $service,
        ApprovedLoanDocumentService::class,
    );

    return $buildDocumentData($loanRequest);
}

function waiverDocumentsReadDownloadedFileContent(\Illuminate\Testing\TestResponse $response): string
{
    $baseResponse = $response->baseResponse;
    $path = $baseResponse->getFile()->getPathname();
    $content = file_get_contents($path);

    if (! is_string($content)) {
        throw new RuntimeException('Unable to read downloaded file content.');
    }

    return $content;
}

/**
 * @return array<string, string>
 */
function waiverDocumentsOpenZipEntries(\Illuminate\Testing\TestResponse $response): array
{
    $baseResponse = $response->baseResponse;
    $path = $baseResponse->getFile()->getPathname();

    $archive = new ZipArchive;
    if ($archive->open($path) !== true) {
        throw new RuntimeException('Unable to open generated ZIP archive.');
    }

    $entries = [];
    for ($index = 0; $index < $archive->numFiles; $index++) {
        $name = $archive->getNameIndex($index);
        if ($name !== false) {
            $entries[$name] = $archive->getFromName($name);
        }
    }
    $archive->close();

    return $entries;
}

function waiverDocumentsCreateTemplatePdf(string $path, string $title): void
{
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0, 0, 0, true);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->SetCompression(false);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Text(8, 11, $title);
    $pdf->Output($path, 'F');
}

beforeEach(function () {
    config()->set('reports.pdf_driver', 'dompdf');

    if (! Schema::hasTable('wmaster')) {
        Schema::create('wmaster', function (Blueprint $table) {
            $table->string('acctno')->primary();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->string('lname')->nullable();
            $table->string('bname')->nullable();
            $table->date('birthday')->nullable();
            $table->string('beneficiary1')->nullable();
            $table->string('beneficiary2')->nullable();
            $table->string('beneficiary3')->nullable();
            $table->date('ben1_bday')->nullable();
            $table->date('ben2_bday')->nullable();
            $table->date('ben3_bday')->nullable();
            $table->string('ben1_acctno')->nullable();
            $table->string('ben2_acctno')->nullable();
            $table->string('ben3_acctno')->nullable();
        });
    }

    $pdfDirectory = storage_path('app/templates/approved-loan-documents/pdf');
    File::ensureDirectoryExists($pdfDirectory);
    waiverDocumentsCreateTemplatePdf(
        $pdfDirectory.DIRECTORY_SEPARATOR.'deped-salary-deduction-waiver.pdf',
        'DepEd Salary Deduction Waiver',
    );
    waiverDocumentsCreateTemplatePdf(
        $pdfDirectory.DIRECTORY_SEPARATOR.'pension-deduction-waiver.pdf',
        'Pension Deduction Waiver',
    );
});

test('deped salary deduction waiver is not applicable without a deped employer signal', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employer_business_name' => 'Some Private Company',
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(
        LoanRequestDocumentKey::DepedSalaryDeductionWaiver,
        $loanRequest->fresh(),
        [],
    ))->toBeFalse();
});

test('deped salary deduction waiver becomes applicable when the applicant employer name contains "deped" and payment option is ATM Deduction', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employer_business_name' => 'DepEd Division of Surigao del Sur',
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(
        LoanRequestDocumentKey::DepedSalaryDeductionWaiver,
        $loanRequest->fresh(),
        ['payment_option' => \App\LoanPaymentOption::AtmDeduction->value],
    ))->toBeTrue();
});

test('deped salary deduction waiver is not applicable when payment option is not ATM Deduction', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employer_business_name' => 'DepEd Division of Surigao del Sur',
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(
        LoanRequestDocumentKey::DepedSalaryDeductionWaiver,
        $loanRequest->fresh(),
        ['payment_option' => \App\LoanPaymentOption::SalaryDeduction->value],
    ))->toBeFalse();
});

test('pension deduction waiver is not applicable when the applicant is not a pensioner', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employment_type' => 'Private',
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(
        LoanRequestDocumentKey::PensionDeductionWaiver,
        $loanRequest->fresh(),
        ['payment_option' => \App\LoanPaymentOption::AtmDeduction->value],
    ))->toBeFalse();
});

test('pension deduction waiver becomes applicable when the applicant employment type is pensioner and payment option is ATM Deduction', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employment_type' => 'Pensioner',
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(
        LoanRequestDocumentKey::PensionDeductionWaiver,
        $loanRequest->fresh(),
        ['payment_option' => \App\LoanPaymentOption::AtmDeduction->value],
    ))->toBeTrue();
});

test('pension deduction waiver is not applicable when payment option is not ATM Deduction', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employment_type' => 'Pensioner',
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    expect($catalog->isApplicable(
        LoanRequestDocumentKey::PensionDeductionWaiver,
        $loanRequest->fresh(),
        ['payment_option' => \App\LoanPaymentOption::SalaryDeduction->value],
    ))->toBeFalse();
});

test('deduction block formats the deped amount with words', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant();
    waiverDocumentsPersistDataEntry($loanRequest, 'deped_deduction_amount', 'number', 28000);
    waiverDocumentsPersistDataEntry($loanRequest, 'deped_school_id_number', 'string', '123456');

    $documentData = waiverDocumentsBuildDocumentData($loanRequest->fresh());

    expect($documentData['deduction']['deped_deduction_amount'])->toBe('28,000.00')
        ->and($documentData['deduction']['deped_deduction_amount_words'])->toBe('TWENTY EIGHT THOUSAND PESOS ONLY.')
        ->and($documentData['deduction']['deped_school_id_number'])->toBe('123456');
});

test('deduction block formats the pension amount with words', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant();
    waiverDocumentsPersistDataEntry($loanRequest, 'pension_deduction_amount', 'number', 6618);
    waiverDocumentsPersistDataEntry($loanRequest, 'pension_provider', 'string', 'Social Security System');
    waiverDocumentsPersistDataEntry($loanRequest, 'pension_bank_name', 'string', 'Land Bank');
    waiverDocumentsPersistDataEntry($loanRequest, 'pension_atm_card_number', 'string', '1234-5678-9012');

    $documentData = waiverDocumentsBuildDocumentData($loanRequest->fresh());

    expect($documentData['deduction']['pension_deduction_amount'])->toBe('6,618.00')
        ->and($documentData['deduction']['pension_deduction_amount_words'])->toBe('SIX THOUSAND SIX HUNDRED EIGHTEEN PESOS ONLY.')
        ->and($documentData['deduction']['pension_provider'])->toBe('Social Security System')
        ->and($documentData['deduction']['pension_bank_name'])->toBe('Land Bank')
        ->and($documentData['deduction']['pension_atm_card_number'])->toBe('1234-5678-9012');
});

test('deped salary deduction waiver field map declares the header image and deduction fields', function () {
    $fields = collect((new DepedSalaryDeductionWaiverPdfFieldMap)->fields());

    $header = $fields->first(fn (array $field): bool => ($field['type'] ?? null) === 'image');
    expect($header)->toBeArray();
    expect($header['value'])->toBe('organization.report_header.designPath');

    foreach ([
        'applicant.full_name',
        'applicant.address',
        'deduction.deped_school_id_number',
        'deduction.deped_deduction_amount_words',
        'deduction.deped_deduction_amount',
        'loan.approved_date_day',
        'loan.approved_date_month_year',
        'notarial.signing_place',
        'reviewer.name',
    ] as $expectedValue) {
        expect($fields->contains(
            fn (array $field): bool => ($field['value'] ?? null) === $expectedValue,
        ))->toBeTrue("Expected field map to contain a field for {$expectedValue}");
    }
});

test('pension deduction waiver field map declares the header image and deduction fields', function () {
    $fields = collect((new PensionDeductionWaiverPdfFieldMap)->fields());

    $header = $fields->first(fn (array $field): bool => ($field['type'] ?? null) === 'image');
    expect($header)->toBeArray();
    expect($header['value'])->toBe('organization.report_header.designPath');

    foreach ([
        'applicant.full_name',
        'applicant.address',
        'deduction.pension_provider',
        'deduction.pension_bank_name',
        'deduction.pension_atm_card_number',
        'deduction.pension_deduction_amount_words',
        'deduction.pension_deduction_amount',
        'loan.approved_date_day',
        'loan.approved_date_month_year',
        'notarial.signing_place',
    ] as $expectedValue) {
        expect($fields->contains(
            fn (array $field): bool => ($field['value'] ?? null) === $expectedValue,
        ))->toBeTrue("Expected field map to contain a field for {$expectedValue}");
    }
});

test('category-specific deduction documents use identifying labels in the checklist', function () {
    expect(LoanRequestDocumentKey::AuthorityToDeduct->label())->toBe('Authority to Deduct (Salary Deduction)')
        ->and(LoanRequestDocumentKey::UndertakingBarangay->label())->toBe('Undertaking (BLGU)')
        ->and(LoanRequestDocumentKey::DepedSalaryDeductionWaiver->label())->toBe('Waiver (DepEd)')
        ->and(LoanRequestDocumentKey::PensionDeductionWaiver->label())->toBe('Waiver (Pensioners)')
        ->and(LoanRequestDocumentKey::AffidavitUndertaking->label())->toBe('Affidavit of Undertaking (ATM Payout)');
});

test('every checklist document belongs to a named group', function () {
    foreach (LoanRequestDocumentKey::cases() as $documentKey) {
        expect($documentKey->group())->toBeString()
            ->and($documentKey->groupLabel())->toBeString()
            ->and($documentKey->groupLabel())->not->toBe('Other');

        expect(in_array($documentKey->group(), LoanRequestDocumentKey::groupOrder(), true))->toBeTrue();
    }
});

test('serialized checklist includes the document group for each entry', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employer_business_name' => 'DepEd Division of Surigao del Sur',
    ]);
    waiverDocumentsPersistDataEntry($loanRequest, 'deped_deduction_amount', 'number', 28000);
    waiverDocumentsPersistDataEntry($loanRequest, 'deped_school_id_number', 'string', '123456');

    $checklist = app(LoanRequestDocumentWorkflowService::class)->serializeChecklist($loanRequest);

    foreach ($checklist as $entry) {
        expect($entry)->toHaveKey('group')
            ->toHaveKey('group_label')
            ->and($entry['group'])->toBeString()
            ->and($entry['group_label'])->toBeString();
    }

    $depedEntry = collect($checklist)->firstWhere('key', LoanRequestDocumentKey::DepedSalaryDeductionWaiver->value);
    expect($depedEntry['label'])->toBe('Waiver (DepEd)')
        ->and($depedEntry['group'])->toBe('repayment_authorization')
        ->and($depedEntry['group_label'])->toBe('Repayment Authorization');
});

test('deped salary deduction waiver downloads as a real pdf for an applicable deped borrower', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employer_business_name' => 'DepEd Division of Surigao del Sur',
    ]);
    waiverDocumentsPersistDataEntry($loanRequest, 'deped_deduction_amount', 'number', 28000);
    waiverDocumentsPersistDataEntry($loanRequest, 'payment_option', 'string', \App\LoanPaymentOption::AtmDeduction->value);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.deped-salary-deduction-waiver', $loanRequest));

    $content = waiverDocumentsReadDownloadedFileContent($response);

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'application/pdf');
    expect($content)->toStartWith('%PDF');
});

test('pension deduction waiver downloads as a real pdf for an applicable pensioner borrower', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employment_type' => 'Pensioner',
    ]);
    waiverDocumentsPersistDataEntry($loanRequest, 'pension_deduction_amount', 'number', 6618);
    waiverDocumentsPersistDataEntry($loanRequest, 'payment_option', 'string', \App\LoanPaymentOption::AtmDeduction->value);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.pension-deduction-waiver', $loanRequest));

    $content = waiverDocumentsReadDownloadedFileContent($response);

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'application/pdf');
    expect($content)->toStartWith('%PDF');
});

test('authority to deduct institution name suggests the barangay agency name for a barangay-payroll applicant', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employer_business_name' => 'Barangay Banahao',
    ]);
    waiverDocumentsPersistDataEntry($loanRequest, 'barangay_agency_name', 'string', 'Barangay Banahao LGU');

    $sections = app(LoanRequestDataService::class)->serializeSections($loanRequest->fresh());

    expect($sections['processing']['authority_to_deduct_institution_name'])->toBe('Barangay Banahao LGU');
});

test('authority to deduct institution name suggests the employer name for a deped applicant', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employer_business_name' => 'DepEd Division of Surigao del Sur',
    ]);

    $sections = app(LoanRequestDataService::class)->serializeSections($loanRequest->fresh());

    expect($sections['processing']['authority_to_deduct_institution_name'])->toBe('DepEd Division of Surigao del Sur');
});

test('authority to deduct institution name does not suggest the pension provider for a pensioner applicant (Authority to Deduct no longer applies to pensioners)', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employment_type' => 'Pensioner',
    ]);
    waiverDocumentsPersistDataEntry($loanRequest, 'pension_provider', 'string', 'Social Security System');

    $sections = app(LoanRequestDataService::class)->serializeSections($loanRequest->fresh());

    expect($sections['processing']['authority_to_deduct_institution_name'])->not->toBe('Social Security System');
});

test('authority to deduct institution name suggests the employer name for an ordinary applicant', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employer_business_name' => 'Some Private Company',
    ]);

    $sections = app(LoanRequestDataService::class)->serializeSections($loanRequest->fresh());

    expect($sections['processing']['authority_to_deduct_institution_name'])->toBe('Some Private Company');
});

test('authority to deduct institution name never overwrites a value staff already saved', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employer_business_name' => 'Some Private Company',
    ]);
    waiverDocumentsPersistDataEntry($loanRequest, 'authority_to_deduct_institution_name', 'string', 'Lianga District Hospital');

    $sections = app(LoanRequestDataService::class)->serializeSections($loanRequest->fresh());

    expect($sections['processing']['authority_to_deduct_institution_name'])->toBe('Lianga District Hospital');
});

test('authority to deduct guidance recommends 2 officers for a barangay-payroll applicant', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employer_business_name' => 'Barangay Banahao',
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    $guidance = $catalog->authorityToDeductGuidance(
        $loanRequest->fresh(),
        ['payment_option' => \App\LoanPaymentOption::SalaryDeduction->value],
    );

    expect($guidance['applicable'])->toBeTrue();
    expect($guidance['recommended_officers'])->toBe(2);
});

test('authority to deduct guidance is not applicable for a barangay-payroll applicant whose payment option is not Salary Deduction', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employer_business_name' => 'Barangay Banahao',
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    $guidance = $catalog->authorityToDeductGuidance($loanRequest->fresh(), []);

    expect($guidance['applicable'])->toBeFalse();
    expect($guidance['category'])->toBe('blgu');
});

test('authority to deduct guidance is not applicable for a pensioner applicant (they use the Pension Waiver instead)', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employment_type' => 'Pensioner',
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    $guidance = $catalog->authorityToDeductGuidance($loanRequest->fresh(), []);

    expect($guidance['applicable'])->toBeFalse();
});

test('authority to deduct guidance is not applicable for a deped applicant (they use the DepEd Waiver instead)', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employer_business_name' => 'DepEd Division of Surigao del Sur',
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    $guidance = $catalog->authorityToDeductGuidance($loanRequest->fresh(), []);

    expect($guidance['applicable'])->toBeFalse();
});

test('authority to deduct guidance is not applicable for an ordinary private employer with no institutional payroll category', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employer_business_name' => 'Some Private Company',
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    $guidance = $catalog->authorityToDeductGuidance($loanRequest->fresh(), []);

    expect($guidance['applicable'])->toBeFalse();
});

test('authority to deduct is not applicable for a self-employed applicant', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employment_type' => 'Self Employed',
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    $guidance = $catalog->authorityToDeductGuidance($loanRequest->fresh(), []);

    expect($guidance['applicable'])->toBeFalse();
    expect($guidance['recommended_officers'])->toBe(0);
});

test('authority to deduct guidance has no saved contact when nothing has been saved for the institution yet', function () {
    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employer_business_name' => 'MRDINC Never Seen Before Branch',
    ]);
    $catalog = app(LoanRequestDocumentCatalog::class);

    $guidance = $catalog->authorityToDeductGuidance($loanRequest->fresh(), []);

    expect($guidance['saved_contact'])->toBeNull();
});

test('authority to deduct guidance surfaces a saved contact for a previously recorded institution', function () {
    App\Models\AuthorityToDeductInstitutionContact::query()->create([
        'institution_name' => 'MRDINC Celni Dave Branch',
        'institution_name_normalized' => 'mrdinc celni dave branch',
        'officer_1_name' => 'Juan Dela Cruz',
        'officer_1_title' => 'Store Manager',
    ]);

    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employer_business_name' => 'MRDINC Celni Dave Branch',
    ]);

    $catalog = app(LoanRequestDocumentCatalog::class);
    $guidance = $catalog->authorityToDeductGuidance(
        $loanRequest->fresh(),
        ['payment_option' => \App\LoanPaymentOption::SalaryDeduction->value],
    );

    expect($guidance['saved_contact'])->toBe([
        'officer_1_name' => 'Juan Dela Cruz',
        'officer_1_title' => 'Store Manager',
        'officer_2_name' => null,
        'officer_2_title' => null,
    ]);
});

test('loan request payload includes authority to deduct guidance', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employment_type' => 'Self Employed',
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.show', $loanRequest));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where(
        'loanRequest.authority_to_deduct_guidance.applicable',
        false,
    ));
});

test('approved documents zip includes the pension waiver but not the deped waiver for a pensioner borrower', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employment_type' => 'Pensioner',
    ]);
    waiverDocumentsPersistDataEntry($loanRequest, 'pension_deduction_amount', 'number', 6618);
    waiverDocumentsPersistDataEntry($loanRequest, 'payment_option', 'string', \App\LoanPaymentOption::AtmDeduction->value);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.approved-documents', $loanRequest));

    $response->assertOk();
    $entries = waiverDocumentsOpenZipEntries($response);

    expect($entries)->toHaveKey('13-Pension-Deduction-Waiver.pdf')
        ->not->toHaveKey('12-DepEd-Salary-Deduction-Waiver.pdf');
});

test('approved documents zip omits authority to deduct for a private employer with no institutional payroll category', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employer_business_name' => 'Some Private Company',
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.approved-documents', $loanRequest));

    $response->assertOk();
    $entries = waiverDocumentsOpenZipEntries($response);

    expect($entries)->not->toHaveKey('11-Authority-to-Deduct.pdf');
});

test('approved documents zip includes the affidavit of undertaking for a private employer on ATM Deduction', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = waiverDocumentsCreateApprovedLoanRequestWithApplicant([
        'employer_business_name' => 'Some Private Company',
    ]);
    waiverDocumentsPersistDataEntry($loanRequest, 'payment_option', 'string', \App\LoanPaymentOption::AtmDeduction->value);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.approved-documents', $loanRequest));

    $response->assertOk();
    $entries = waiverDocumentsOpenZipEntries($response);

    expect($entries)->toHaveKey('03-Affidavit-of-Undertaking.pdf');
});
