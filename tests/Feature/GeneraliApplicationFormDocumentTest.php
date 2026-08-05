<?php

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
use App\Services\LoanRequests\PdfFieldMaps\GeneraliApplicationFormPdfFieldMap;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * @return array<string, mixed>
 */
function generaliApplicationFormCreateApprovedLoanRequestWithApplicant(
    array $applicantAttributes = [],
    array $memberApplicationProfileAttributes = [],
): LoanRequest {
    $user = User::factory()->create();
    MemberApplicationProfile::factory()->create(array_merge(
        ['user_id' => $user->user_id],
        $memberApplicationProfileAttributes,
    ));

    $loanRequest = LoanRequest::factory()
        ->forUser($user)
        ->create([
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
            'civil_status' => 'Married',
            'spouse_name' => 'Spouse Member',
            'spouse_age' => 30,
        ], $applicantAttributes));

    return $loanRequest;
}

function generaliApplicationFormPersistDataEntry(
    LoanRequest $loanRequest,
    string $sectionKey,
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
            'section_key' => $sectionKey,
            'owner_type' => $sectionKey === 'processing' ? 'staff' : 'member',
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
function generaliApplicationFormBuildDocumentData(LoanRequest $loanRequest): array
{
    $service = app(ApprovedLoanDocumentService::class);
    $buildDocumentData = Closure::bind(
        fn (LoanRequest $record): array => $this->buildDocumentData($record),
        $service,
        ApprovedLoanDocumentService::class,
    );

    return $buildDocumentData($loanRequest);
}

function generaliApplicationFormReadDownloadedFileContent(\Illuminate\Testing\TestResponse $response): string
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
function generaliApplicationFormOpenZipEntries(\Illuminate\Testing\TestResponse $response): array
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

function generaliApplicationFormCreateTemplatePdf(string $path, string $title): void
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
    $pdf->AddPage();
    $pdf->AddPage();
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
    generaliApplicationFormCreateTemplatePdf(
        $pdfDirectory.DIRECTORY_SEPARATOR.'generali-application-form.pdf',
        'Generali Individual Application Form',
    );
});

test('generali application form field map declares identity and static-path fields', function () {
    $fields = collect((new GeneraliApplicationFormPdfFieldMap)->fields());

    foreach ([
        'applicant.last_name',
        'applicant.first_name',
        'applicant.middle_name',
        'applicant.birthdate',
        'applicant.employer_or_business',
        'application_form.pep_status_details',
        'application_form.source_of_fund_wealth',
        'application_form.employer_date_employed',
        'notarial.signing_place',
    ] as $expectedValue) {
        expect($fields->contains(
            fn (array $field): bool => ($field['value'] ?? null) === $expectedValue,
        ))->toBeTrue("Expected field map to contain a field for {$expectedValue}");
    }

    // 13 rows (spouse + 11 category slots) x 5 fields each (name, birthdate, age, 2
    // cycle checkboxes) accounts for a large share of the map's "check" fields.
    $checkFields = $fields->filter(fn (array $field): bool => ($field['type'] ?? null) === 'check');
    expect($checkFields->count())->toBeGreaterThan(20);
});

test('generali application form field map resolves dependent rows via closures', function () {
    $fields = (new GeneraliApplicationFormPdfFieldMap)->fields();

    $documentData = [
        'dependents' => [
            'spouse' => ['name' => 'Spouse Member', 'cycle_status' => 'Old'],
            'children' => [['name' => 'Junior Member', 'cycle_status' => 'New']],
            'siblings' => [],
            'parents' => [],
            'extended' => [],
        ],
    ];

    $resolvedValues = collect($fields)
        ->filter(fn (array $field): bool => ($field['type'] ?? null) !== 'check')
        ->map(function (array $field) use ($documentData) {
            $value = $field['value'] ?? null;

            return is_callable($value) ? $value($documentData) : data_get($documentData, (string) $value);
        });

    expect($resolvedValues)->toContain('Spouse Member')
        ->and($resolvedValues)->toContain('Junior Member');
});

test('application_form data block resolves pep, cycle, and id fields from processing and member profile', function () {
    $loanRequest = generaliApplicationFormCreateApprovedLoanRequestWithApplicant(
        memberApplicationProfileAttributes: [
            'source_of_fund_wealth' => 'Salary',
            'id_type' => 'TIN',
            'id_type_other' => null,
            'id_number' => '123-456-789',
        ],
    );
    generaliApplicationFormPersistDataEntry($loanRequest, 'health_glapi', 'applicant_pep_status', 'boolean', true);
    generaliApplicationFormPersistDataEntry($loanRequest, 'health_glapi', 'applicant_pep_status_details', 'string', 'Barangay Councilor, since 2020');
    generaliApplicationFormPersistDataEntry($loanRequest, 'dependents', 'applicant_cycle_status', 'string', 'New');
    generaliApplicationFormPersistDataEntry($loanRequest, 'processing', 'employer_date_employed', 'string', '2019-06-01');

    $documentData = generaliApplicationFormBuildDocumentData($loanRequest->fresh());

    expect($documentData['application_form']['pep_status'])->toBeTrue()
        ->and($documentData['application_form']['pep_status_details'])->toBe('Barangay Councilor, since 2020')
        ->and($documentData['application_form']['cycle_status'])->toBe('New')
        ->and($documentData['application_form']['source_of_fund_wealth'])->toBe('Salary')
        ->and($documentData['application_form']['id_type'])->toBe('TIN')
        ->and($documentData['application_form']['id_number'])->toBe('123-456-789');
});

test('applicant address line folds in barangay for documents with separate city/province boxes', function () {
    $loanRequest = generaliApplicationFormCreateApprovedLoanRequestWithApplicant([
        'address1' => '123 Loan Street',
        'address_barangay' => 'Barangay Uno',
        'address2' => 'Loan City',
        'address3' => 'Loan Province',
        'employer_business_address1' => 'Office Plaza',
        'employer_business_address_barangay' => 'Barangay Dos',
        'employer_business_address2' => 'Office City',
        'employer_business_address3' => 'Office Province',
    ]);

    $documentData = generaliApplicationFormBuildDocumentData($loanRequest->fresh());

    expect($documentData['applicant']['address_line'])->toBe('123 Loan Street, Barangay Uno')
        ->and($documentData['applicant']['address_city'])->toBe('Loan City')
        ->and($documentData['applicant']['address_province'])->toBe('Loan Province')
        ->and($documentData['applicant']['office_address_line'])->toBe('Office Plaza, Barangay Dos');
});

test('dependents data block resolves spouse and category rows with computed age', function () {
    $loanRequest = generaliApplicationFormCreateApprovedLoanRequestWithApplicant();
    generaliApplicationFormPersistDataEntry($loanRequest, 'dependents', 'dependent_spouse_cycle_status', 'string', 'Old');
    generaliApplicationFormPersistDataEntry($loanRequest, 'dependents', 'dependent_spouse_cycle_number', 'number', 3);
    generaliApplicationFormPersistDataEntry($loanRequest, 'dependents', 'dependent_child_1_name', 'string', 'Junior Member');
    generaliApplicationFormPersistDataEntry($loanRequest, 'dependents', 'dependent_child_1_birthdate', 'string', now()->subYears(10)->toDateString());
    generaliApplicationFormPersistDataEntry($loanRequest, 'dependents', 'dependent_child_1_cycle_status', 'string', 'New');

    $documentData = generaliApplicationFormBuildDocumentData($loanRequest->fresh());

    expect($documentData['dependents']['spouse']['name'])->toBe('Spouse Member')
        ->and($documentData['dependents']['spouse']['cycle_status'])->toBe('Old')
        ->and($documentData['dependents']['spouse']['cycle_number'])->toBe('3')
        ->and($documentData['dependents']['children'])->toHaveCount(1)
        ->and($documentData['dependents']['children'][0]['name'])->toBe('Junior Member')
        ->and($documentData['dependents']['children'][0]['age'])->toBe('10')
        ->and($documentData['dependents']['children'][0]['cycle_status'])->toBe('New')
        ->and($documentData['dependents']['siblings'])->toBe([])
        ->and($documentData['dependents']['parents'])->toBe([])
        ->and($documentData['dependents']['extended'])->toBe([]);
});

test('generali application form downloads as a real pdf', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = generaliApplicationFormCreateApprovedLoanRequestWithApplicant();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.generali-application-form', $loanRequest));

    $content = generaliApplicationFormReadDownloadedFileContent($response);

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'application/pdf');
    expect($content)->toStartWith('%PDF');
});

test('approved documents zip always includes the generali application form', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = generaliApplicationFormCreateApprovedLoanRequestWithApplicant();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.approved-documents', $loanRequest));

    $response->assertOk();
    $entries = generaliApplicationFormOpenZipEntries($response);

    expect($entries)->toHaveKey('14-Generali-Application-Form.pdf');
});
