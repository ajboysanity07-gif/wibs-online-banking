<?php

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Models\UserProfile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use setasign\Fpdi\Fpdi;

beforeEach(function () {
    config()->set('reports.pdf_driver', 'dompdf');
    approvedLoanDocumentsBackupTemplateFilesForTests();
    approvedLoanDocumentsSeedTemplateFilesForTests();
});

afterEach(function () {
    approvedLoanDocumentsRestoreTemplateFilesAfterTests();
});

test('approved loan can access each approved loan document separately', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();
    $this->actingAs($admin);

    foreach (approvedLoanDocumentsRouteDefinitions($loanRequest) as $document) {
        $response = $this->get(route($document['route'], $loanRequest));

        $response->assertOk();
        $response->assertHeaderContains(
            'content-disposition',
            $document['disposition'],
        );
        $response->assertHeaderContains(
            'content-disposition',
            $document['filename'],
        );

        if ($document['disposition'] === 'attachment') {
            $response->assertDownload($document['filename']);
        }
    }
});

test('non-approved loan cannot download approved-only documents', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);
    approvedLoanDocumentsCreateLoanRequestPeopleSnapshots($loanRequest);

    $this->actingAs($admin);

    foreach (approvedLoanDocumentsApprovedOnlyRouteNames() as $routeName) {
        $this->get(route($routeName, $loanRequest))->assertNotFound();
    }
});

test('each approved loan document pdf route returns a pdf response', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();
    $this->actingAs($admin);

    foreach (approvedLoanDocumentsPdfRouteDefinitions($loanRequest) as $document) {
        $response = $this->get(route($document['route'], $loanRequest));
        $content = approvedLoanDocumentsReadDownloadedFileContent($response);

        $response->assertOk();
        $response->assertHeaderContains('content-type', 'application/pdf');
        $response->assertHeaderContains(
            'content-disposition',
            $document['disposition'],
        );
        expect($content)->toStartWith('%PDF')
            ->not->toContain('LibreOffice')
            ->not->toContain('soffice')
            ->not->toContain('file://');
    }
});

test('approved template-backed pdf routes preserve page counts', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();
    $this->actingAs($admin);

    foreach (approvedLoanDocumentsTemplateBackedPdfRouteDefinitions($loanRequest) as $document) {
        $response = $this->get(route($document['route'], $loanRequest));
        $content = approvedLoanDocumentsReadDownloadedFileContent($response);

        $response->assertOk();
        expect($content)
            ->toStartWith('%PDF')
            ->not->toContain('file://');
        expect(approvedLoanDocumentsPdfPageCount($response))
            ->toBe($document['page_count']);
    }
});

test('approved member can download approved loan documents for owned request', function () {
    $member = approvedLoanDocumentsCreateApprovedMember();
    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople($member);

    $this->actingAs($member);

    $applicationFormResponse = $this->get(
        route('client.loan-requests.documents.application-form', $loanRequest),
    );
    $loanSecurityAgreementResponse = $this->get(
        route('client.loan-requests.documents.loan-security-agreement', $loanRequest),
    );
    $packageResponse = $this->get(
        route('client.loan-requests.approved-documents', $loanRequest),
    );

    $applicationFormResponse
        ->assertOk()
        ->assertDownload('application-form-'.$loanRequest->reference.'.pdf');
    $loanSecurityAgreementResponse
        ->assertOk()
        ->assertDownload('loan-security-agreement-'.$loanRequest->reference.'.pdf');
    $packageResponse
        ->assertOk()
        ->assertDownload('approved-loan-documents-'.$loanRequest->reference.'.zip');
});

test('plan of payment route returns an xlsx response and generated workbook opens', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.plan-of-payment', $loanRequest));

    $response->assertOk();
    $response->assertDownload(
        'plan-of-payment-disclosure-promissory-note-'.$loanRequest->reference.'.xlsx',
    );
    $response->assertHeaderContains(
        'content-type',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );

    $spreadsheet = IOFactory::load(
        approvedLoanDocumentsDownloadedFilePath($response),
    );

    expect($spreadsheet->getSheetCount())->toBe(4);
    expect($spreadsheet->getSheet(0)->getTitle())->toBe('Loan Information');
    expect($spreadsheet->getSheet(1)->getTitle())->toBe('Plan of Payment');
    expect($spreadsheet->getSheet(2)->getTitle())->toBe('Disclosure Statement');
    expect($spreadsheet->getSheet(3)->getTitle())->toBe('Promissory Note');
    expect($spreadsheet->getSheet(0)->getCell('C7')->getValue())->toBe('Sample Q Member');
    expect($spreadsheet->getSheet(0)->getCell('F7')->getValue())->toBe('Sample Enterprise');
    expect($spreadsheet->getSheet(0)->getCell('C9')->getValue())->toBe(25000.0);
    expect($spreadsheet->getSheet(0)->getCell('C10')->getValue())->toBeNull();
    expect($spreadsheet->getSheet(0)->getCell('C17')->getValue())->toBe('SEMI-MONTHLY');
    expect($spreadsheet->getSheet(0)->getCell('E17')->getValue())->toBe(24.0);
    expect($spreadsheet->getSheet(0)->getCell('C32')->getValue())->toBe('Co A MakerOne');
    expect($spreadsheet->getSheet(2)->getCell('M7')->getValue())->toBe($loanRequest->reference);
    expect($spreadsheet->getSheet(3)->getCell('I8')->getValue())->not->toBe('');
    expect((string) file_get_contents(approvedLoanDocumentsDownloadedFilePath($response)))
        ->not->toContain('LibreOffice')
        ->not->toContain('soffice');

    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
});

test('missing optional fields do not break approved document generation', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'approved_amount' => null,
        'decision_notes' => null,
    ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'middle_name' => null,
            'birthdate' => null,
            'address' => null,
            'address1' => null,
            'address2' => null,
            'address3' => null,
            'employer_business_name' => null,
            'employer_business_address' => null,
            'employer_business_address1' => null,
            'employer_business_address2' => null,
            'employer_business_address3' => null,
            'current_position' => null,
            'cell_no' => null,
            'civil_status' => null,
        ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerOne)
        ->create([
            'middle_name' => null,
            'birthdate' => null,
            'address' => null,
            'address1' => null,
            'address2' => null,
            'address3' => null,
            'signature_path' => null,
        ]);
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerTwo)
        ->create([
            'middle_name' => null,
            'birthdate' => null,
            'address' => null,
            'address1' => null,
            'address2' => null,
            'address3' => null,
            'signature_path' => null,
        ]);

    $this->actingAs($admin);

    foreach (approvedLoanDocumentsPdfRouteDefinitions($loanRequest) as $document) {
        $response = $this->get(route($document['route'], $loanRequest));

        $response->assertOk();
        expect(approvedLoanDocumentsReadDownloadedFileContent($response))
            ->toStartWith('%PDF');
    }

    $xlsxResponse = $this->get(route('admin.requests.documents.plan-of-payment', $loanRequest));
    $xlsxResponse->assertOk();

    $spreadsheet = IOFactory::load(
        approvedLoanDocumentsDownloadedFilePath($xlsxResponse),
    );
    expect($spreadsheet->getSheetCount())->toBe(4);
    expect($spreadsheet->getSheet(0)->getCell('F7')->getValue())->toBeNull();
    expect($spreadsheet->getSheet(0)->getCell('C9')->getValue())->toBeNull();
    expect($spreadsheet->getSheet(0)->getCell('C34')->getValue())->toBeNull();
    expect($spreadsheet->getSheet(0)->getCell('C37')->getValue())->toBeNull();
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
});

test('approved loan can still download approved loan documents zip package', function () {
    if (! class_exists(\ZipArchive::class)) {
        $this->markTestSkipped('ZIP extension is required for this test.');
    }

    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.approved-documents', $loanRequest));

    $response->assertOk();
    $response->assertDownload(
        'approved-loan-documents-'.$loanRequest->reference.'.zip',
    );
});

test('approved document zip contains all required files and valid generated documents', function () {
    if (! class_exists(\ZipArchive::class)) {
        $this->markTestSkipped('ZIP extension is required for this test.');
    }

    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);
    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.approved-documents', $loanRequest));

    $response->assertOk();

    $entries = approvedLoanDocumentsOpenZipEntriesFromResponse($response);
    $entryNames = array_keys($entries);

    expect($entryNames)->toBe([
        '01-Application-Form.pdf',
        '02-GREPALIFE.pdf',
        '03-Loan-Security-Agreement.pdf',
        '04-Undertaking-Barangay-Officials.pdf',
        '05-Affidavit-of-Undertaking.pdf',
        '06-Authorization.pdf',
        '07-Plan-of-Payment-Disclosure-Promissory-Note.xlsx',
    ]);

    foreach ($entries as $content) {
        expect($content)
            ->not->toContain('LibreOffice')
            ->not->toContain('soffice')
            ->not->toContain('file://');
    }

    foreach (approvedLoanDocumentsTemplateBackedPdfZipEntryNames() as $entryName) {
        expect($entries[$entryName] ?? null)->toBeString();
        expect($entries[$entryName])->toStartWith('%PDF');
    }

    expect($entries['07-Plan-of-Payment-Disclosure-Promissory-Note.xlsx'] ?? null)
        ->toBeString()
        ->toStartWith('PK');
});

test('missing approved pdf template is logged and fails generation', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);
    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    File::delete(
        approvedLoanDocumentsTemplateDirectory()
        .DIRECTORY_SEPARATOR
        .'pdf'
        .DIRECTORY_SEPARATOR
        .'grepalife.pdf',
    );

    Log::spy();
    $this->actingAs($admin);
    $this->withoutExceptionHandling();

    expect(fn () => $this->get(
        route('admin.requests.documents.grepalife', $loanRequest),
    ))->toThrow(\RuntimeException::class, 'Missing PDF template file: grepalife.pdf');

    Log::shouldHaveReceived('error')
        ->withArgs(function (string $message, array $context): bool {
            $templatePath = str_replace('\\', '/', (string) ($context['template_path'] ?? ''));

            return $message === 'Missing approved loan PDF template file.'
                && ($context['template_filename'] ?? null) === 'grepalife.pdf'
                && str_contains(
                    $templatePath,
                    'storage/app/templates/approved-loan-documents/pdf/grepalife.pdf',
                );
        })
        ->once();
});

/**
 * @return array<int, array{route: string, filename: string, disposition: string}>
 */
function approvedLoanDocumentsRouteDefinitions(LoanRequest $loanRequest): array
{
    return [
        ...approvedLoanDocumentsPdfRouteDefinitions($loanRequest),
        [
            'route' => 'admin.requests.documents.plan-of-payment',
            'filename' => 'plan-of-payment-disclosure-promissory-note-'.$loanRequest->reference.'.xlsx',
            'disposition' => 'attachment',
        ],
    ];
}

/**
 * @return array<int, array{route: string, filename: string, disposition: string}>
 */
function approvedLoanDocumentsPdfRouteDefinitions(LoanRequest $loanRequest): array
{
    return [
        [
            'route' => 'admin.requests.documents.application-form',
            'filename' => 'application-form-'.$loanRequest->reference.'.pdf',
            'disposition' => 'attachment',
        ],
        ...approvedLoanDocumentsTemplateBackedPdfRouteDefinitions($loanRequest),
    ];
}

/**
 * @return array<int, array{route: string, filename: string, disposition: string, page_count: int}>
 */
function approvedLoanDocumentsTemplateBackedPdfRouteDefinitions(
    LoanRequest $loanRequest,
): array {
    return [
        [
            'route' => 'admin.requests.documents.grepalife',
            'filename' => 'grepalife-'.$loanRequest->reference.'.pdf',
            'disposition' => 'inline',
            'page_count' => 2,
        ],
        [
            'route' => 'admin.requests.documents.loan-security-agreement',
            'filename' => 'loan-security-agreement-'.$loanRequest->reference.'.pdf',
            'disposition' => 'attachment',
            'page_count' => 1,
        ],
        [
            'route' => 'admin.requests.documents.undertaking-barangay',
            'filename' => 'undertaking-barangay-'.$loanRequest->reference.'.pdf',
            'disposition' => 'attachment',
            'page_count' => 1,
        ],
        [
            'route' => 'admin.requests.documents.affidavit-undertaking',
            'filename' => 'affidavit-undertaking-'.$loanRequest->reference.'.pdf',
            'disposition' => 'attachment',
            'page_count' => 1,
        ],
        [
            'route' => 'admin.requests.documents.authorization',
            'filename' => 'authorization-'.$loanRequest->reference.'.pdf',
            'disposition' => 'attachment',
            'page_count' => 1,
        ],
    ];
}

/**
 * @return list<string>
 */
function approvedLoanDocumentsTemplateBackedPdfZipEntryNames(): array
{
    return [
        '02-GREPALIFE.pdf',
        '03-Loan-Security-Agreement.pdf',
        '04-Undertaking-Barangay-Officials.pdf',
        '05-Affidavit-of-Undertaking.pdf',
        '06-Authorization.pdf',
    ];
}

/**
 * @return list<string>
 */
function approvedLoanDocumentsApprovedOnlyRouteNames(): array
{
    return [
        'admin.requests.approved-documents',
        'admin.requests.documents.grepalife',
        'admin.requests.documents.loan-security-agreement',
        'admin.requests.documents.undertaking-barangay',
        'admin.requests.documents.affidavit-undertaking',
        'admin.requests.documents.authorization',
        'admin.requests.documents.plan-of-payment',
    ];
}

function approvedLoanDocumentsReadDownloadedFileContent(
    \Illuminate\Testing\TestResponse $response,
): string {
    $content = file_get_contents(
        approvedLoanDocumentsDownloadedFilePath($response),
    );

    if (! is_string($content)) {
        throw new \RuntimeException('Unable to read downloaded file content.');
    }

    return $content;
}

function approvedLoanDocumentsDownloadedFilePath(
    \Illuminate\Testing\TestResponse $response,
): string {
    $baseResponse = $response->baseResponse;

    if (method_exists($baseResponse, 'getFile')) {
        $path = $baseResponse->getFile()->getPathname();

        if (is_string($path) && $path !== '') {
            return $path;
        }
    }

    $content = $baseResponse->getContent();

    if (! is_string($content)) {
        throw new \RuntimeException('Unable to read downloaded response content.');
    }

    $directory = storage_path('app/testing-downloads');
    File::ensureDirectoryExists($directory);
    $path = tempnam($directory, 'approved-loan-');

    if ($path === false) {
        throw new \RuntimeException('Unable to create a temporary download file.');
    }

    file_put_contents($path, $content);

    return $path;
}

function approvedLoanDocumentsPdfPageCount(
    \Illuminate\Testing\TestResponse $response,
): int {
    $pdf = new Fpdi('P', 'mm');

    return $pdf->setSourceFile(
        approvedLoanDocumentsDownloadedFilePath($response),
    );
}

function approvedLoanDocumentsCreateApprovedLoanRequestWithPeople(
    ?User $user = null,
): LoanRequest {
    $factory = LoanRequest::factory();

    if ($user instanceof User) {
        $factory = $factory->forUser($user);
    }

    $loanRequest = $factory->create([
        'status' => LoanRequestStatus::Approved,
        'submitted_at' => now()->subDay(),
        'reviewed_at' => now(),
        'approved_amount' => 25000,
        'approved_term' => 12,
    ]);

    approvedLoanDocumentsCreateLoanRequestPeopleSnapshots($loanRequest);

    return $loanRequest;
}

function approvedLoanDocumentsCreateApprovedMember(): User
{
    $member = User::factory()->create();

    UserProfile::factory()->approved()->create([
        'user_id' => $member->user_id,
    ]);

    MemberApplicationProfile::factory()->completed()->create([
        'user_id' => $member->user_id,
    ]);

    return $member;
}

function approvedLoanDocumentsCreateLoanRequestPeopleSnapshots(LoanRequest $loanRequest): void
{
    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::Applicant)
        ->create([
            'first_name' => 'Sample',
            'middle_name' => 'Q',
            'last_name' => 'Member',
            'birthdate' => '1990-01-01',
            'address1' => '123 Loan Street',
            'address2' => 'Loan City',
            'address3' => 'Loan Province',
            'cell_no' => '09171234567',
            'civil_status' => 'Married',
            'employer_business_name' => 'Sample Enterprise',
            'current_position' => 'Manager',
            'payday' => '15/30',
        ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerOne)
        ->create([
            'first_name' => 'Co',
            'middle_name' => 'A',
            'last_name' => 'MakerOne',
            'address1' => '1 CoMaker Street',
            'address2' => 'Loan City',
            'address3' => 'Loan Province',
        ]);

    LoanRequestPerson::factory()
        ->forLoanRequest($loanRequest)
        ->role(LoanRequestPersonRole::CoMakerTwo)
        ->create([
            'first_name' => 'Co',
            'middle_name' => 'B',
            'last_name' => 'MakerTwo',
            'address1' => '2 CoMaker Street',
            'address2' => 'Loan City',
            'address3' => 'Loan Province',
        ]);
}

function approvedLoanDocumentsSeedTemplateFilesForTests(): void
{
    $templateDirectory = approvedLoanDocumentsTemplateDirectory();
    $excelDirectory = $templateDirectory.DIRECTORY_SEPARATOR.'excel';
    $pdfDirectory = $templateDirectory.DIRECTORY_SEPARATOR.'pdf';

    File::deleteDirectory($templateDirectory);
    File::ensureDirectoryExists($excelDirectory);
    File::ensureDirectoryExists($pdfDirectory);

    approvedLoanDocumentsCreateTemplatePdf(
        $pdfDirectory.DIRECTORY_SEPARATOR.'grepalife.pdf',
        [
            ['width' => 216.0, 'height' => 279.0, 'title' => 'GREPALIFE Page 1'],
            ['width' => 216.0, 'height' => 279.0, 'title' => 'GREPALIFE Page 2'],
        ],
    );
    approvedLoanDocumentsCreateTemplatePdf(
        $pdfDirectory.DIRECTORY_SEPARATOR.'loan-security-agreement.pdf',
        [
            [
                'width' => 216.0,
                'height' => 330.0,
                'title' => 'Loan Security Agreement',
            ],
        ],
    );
    approvedLoanDocumentsCreateTemplatePdf(
        $pdfDirectory.DIRECTORY_SEPARATOR.'undertaking-barangay-officials.pdf',
        [
            [
                'width' => 216.0,
                'height' => 330.0,
                'title' => 'Undertaking Barangay',
            ],
        ],
    );
    approvedLoanDocumentsCreateTemplatePdf(
        $pdfDirectory.DIRECTORY_SEPARATOR.'affidavit-undertaking.pdf',
        [
            [
                'width' => 216.0,
                'height' => 330.0,
                'title' => 'Affidavit Undertaking',
            ],
        ],
    );
    approvedLoanDocumentsCreateTemplatePdf(
        $pdfDirectory.DIRECTORY_SEPARATOR.'authorization.pdf',
        [
            ['width' => 216.0, 'height' => 330.0, 'title' => 'Authorization'],
        ],
    );

    $spreadsheet = new Spreadsheet;
    $loanInformationSheet = $spreadsheet->getActiveSheet();
    $loanInformationSheet->setTitle('Loan Information');
    $loanInformationSheet->setCellValue('C7', 'SAMPLE BORROWER');
    $loanInformationSheet->setCellValue('F7', 'SAMPLE EMPLOYER');
    $loanInformationSheet->setCellValue('C8', 'SAMPLE ADDRESS');
    $loanInformationSheet->setCellValue('C9', 99999);
    $loanInformationSheet->setCellValue('C10', 0.36);
    $loanInformationSheet->setCellValue('C11', 10);
    $loanInformationSheet->setCellValue('C12', 0.05);
    $loanInformationSheet->setCellValue('C13', '=C9*C10/12*C11');
    $loanInformationSheet->setCellValue('C14', 'SAMPLE CERTIFIER');
    $loanInformationSheet->setCellValue('C15', 'SAMPLE POSITION');
    $loanInformationSheet->setCellValue('C16', 'SAMPLE LOAN');
    $loanInformationSheet->setCellValue('C17', 'MONTHLY');
    $loanInformationSheet->setCellValue('E17', 10);
    $loanInformationSheet->setCellValue('C18', 'SAMPLE MANAGER');
    $loanInformationSheet->setCellValue('C19', 10);
    $loanInformationSheet->setCellValue('C20', 1);
    $loanInformationSheet->setCellValue('C21', '=C9*C12');
    $loanInformationSheet->setCellValue('C22', '=C9/1000*C19*C20');
    $loanInformationSheet->setCellValue('C23', '=C9*2%');
    $loanInformationSheet->setCellValue('C24', '=C9*1.5/200');
    $loanInformationSheet->setCellValue('C25', 100);
    $loanInformationSheet->setCellValue('C27', '=C9/E17');
    $loanInformationSheet->setCellValue('C28', '=C13/E17');
    $loanInformationSheet->setCellValue('C29', '=C27*2%');
    $loanInformationSheet->setCellValue('C30', '=SUM(C27:C29)');
    $loanInformationSheet->setCellValue('C32', 'SAMPLE CO-MAKER 1');
    $loanInformationSheet->setCellValue('C33', 'SAMPLE CO-MAKER 2');
    $loanInformationSheet->setCellValue('C34', 'SAMPLE CO-MAKER 1 ADDRESS');
    $loanInformationSheet->setCellValue('C35', 'SAMPLE CO-MAKER 2 ADDRESS');
    $loanInformationSheet->setCellValue('C36', 300);
    $loanInformationSheet->setCellValue('C37', 'SAMPLE AMOUNT IN WORDS');
    $loanInformationSheet->setCellValue('C38', 'SAMPLE RATE WORDS');
    $loanInformationSheet->setCellValue('C39', 'MONTHLY');
    $loanInformationSheet->setCellValue('C40', '=E17');

    $planSheet = $spreadsheet->createSheet();
    $planSheet->setTitle('Plan of Payment');
    $planSheet->setCellValue('D9', "='Loan Information'!C7");
    $planSheet->setCellValue('D10', "='Loan Information'!C8");
    $planSheet->setCellValue('D11', "='Loan Information'!C9");
    $planSheet->setCellValue('D12', "='Loan Information'!C16");
    $planSheet->setCellValue('D17', "='Loan Information'!C27");
    $planSheet->setCellValue('D18', "='Loan Information'!C28");
    $planSheet->setCellValue('D19', "='Loan Information'!C29");
    $planSheet->setCellValue('D20', "='Loan Information'!C30");
    $planSheet->setCellValue('C22', '01/01/2025');
    $planSheet->setCellValue('G22', '12/31/2025');
    $planSheet->mergeCells('G22:H22');

    $disclosureSheet = $spreadsheet->createSheet();
    $disclosureSheet->setTitle('Disclosure Statement');
    $disclosureSheet->setCellValue('D7', "='Loan Information'!C7");
    $disclosureSheet->setCellValue('C8', "='Loan Information'!C8");
    $disclosureSheet->setCellValue('N9', "='Loan Information'!C9");
    $disclosureSheet->setCellValue('D14', "='Loan Information'!C10");
    $disclosureSheet->setCellValue('F14', '01/01/2025');
    $disclosureSheet->setCellValue('H14', '12/31/2025');
    $disclosureSheet->setCellValue('J14', "='Loan Information'!C13");
    $disclosureSheet->setCellValue('F23', "='Loan Information'!C12");
    $disclosureSheet->setCellValue('L23', "='Loan Information'!C21");
    $disclosureSheet->setCellValue('F28', "='Loan Information'!C22");
    $disclosureSheet->setCellValue('F29', "='Loan Information'!C23");
    $disclosureSheet->setCellValue('F30', "='Loan Information'!C24");
    $disclosureSheet->setCellValue('F31', "='Loan Information'!C25");
    $disclosureSheet->setCellValue('M7', 'SAMPLE-LOAN-REFERENCE');
    $disclosureSheet->setCellValue('F40', '12/31/2025');

    $promissoryNoteSheet = $spreadsheet->createSheet();
    $promissoryNoteSheet->setTitle('Promissory Note');
    $promissoryNoteSheet->setCellValue('J10', "='Loan Information'!C9");
    $promissoryNoteSheet->setCellValue('A12', "='Loan Information'!C36");
    $promissoryNoteSheet->setCellValue('D13', "='Loan Information'!C37");
    $promissoryNoteSheet->setCellValue('J13', "='Loan Information'!C9");
    $promissoryNoteSheet->setCellValue('E14', "='Loan Information'!C38");
    $promissoryNoteSheet->setCellValue('A15', "='Loan Information'!C30");
    $promissoryNoteSheet->setCellValue('E15', "='Loan Information'!C39");
    $promissoryNoteSheet->setCellValue('I8', '01/01/2025');
    $promissoryNoteSheet->setCellValue('I9', '12/31/2025');
    $promissoryNoteSheet->setCellValue('G15', '01/01/2025');
    $promissoryNoteSheet->setCellValue('J15', '12/31/2025');
    $promissoryNoteSheet->setCellValue('L15', "='Loan Information'!C40");
    $promissoryNoteSheet->mergeCells('I8:K8');
    $promissoryNoteSheet->mergeCells('I9:K9');
    $promissoryNoteSheet->mergeCells('G15:H15');

    IOFactory::createWriter($spreadsheet, 'Xlsx')->save(
        $excelDirectory.DIRECTORY_SEPARATOR.'plan-of-payment-disclosure-promissory-note.xlsx',
    );

    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
}

/**
 * @param  list<array{width: float, height: float, title: string}>  $pages
 */
function approvedLoanDocumentsCreateTemplatePdf(
    string $path,
    array $pages,
): void {
    $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0, 0, 0, true);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->SetCompression(false);

    foreach ($pages as $page) {
        $width = $page['width'];
        $height = $page['height'];
        $orientation = $width > $height ? 'L' : 'P';

        $pdf->AddPage($orientation, [$width, $height]);
        $pdf->SetFillColor(238, 244, 255);
        $pdf->Rect(0, 0, $width, $height, 'F');
        $pdf->SetFillColor(34, 104, 170);
        $pdf->Rect(0, 0, $width, 18, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Text(8, 11, $page['title']);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Rect(12, 26, $width - 24, $height - 38);
        $pdf->Text(16, 34, 'Approved loan template background');
        $pdf->Text(16, 41, 'Used by tests for PDF template rendering');
    }

    $pdf->Output($path, 'F');
}

function approvedLoanDocumentsTemplateDirectory(): string
{
    return storage_path('app/templates/approved-loan-documents');
}

function approvedLoanDocumentsTemplateBackupDirectory(): string
{
    return storage_path('app/testing-backups/approved-loan-documents');
}

function approvedLoanDocumentsBackupTemplateFilesForTests(): void
{
    $templateDirectory = approvedLoanDocumentsTemplateDirectory();
    $backupDirectory = approvedLoanDocumentsTemplateBackupDirectory();

    File::deleteDirectory($backupDirectory);

    if (! File::isDirectory($templateDirectory)) {
        File::ensureDirectoryExists($backupDirectory);

        return;
    }

    File::copyDirectory($templateDirectory, $backupDirectory);
}

function approvedLoanDocumentsRestoreTemplateFilesAfterTests(): void
{
    $templateDirectory = approvedLoanDocumentsTemplateDirectory();
    $backupDirectory = approvedLoanDocumentsTemplateBackupDirectory();

    File::deleteDirectory($templateDirectory);

    if (File::isDirectory($backupDirectory)) {
        File::ensureDirectoryExists($templateDirectory);
        File::copyDirectory($backupDirectory, $templateDirectory);
    }

    File::deleteDirectory($backupDirectory);
}

/**
 * @return array<string, string>
 */
function approvedLoanDocumentsOpenZipEntriesFromResponse(
    \Illuminate\Testing\TestResponse $response,
): array {
    $archive = new \ZipArchive;
    $opened = $archive->open(approvedLoanDocumentsDownloadedFilePath($response));

    if ($opened !== true) {
        throw new \RuntimeException('Unable to open generated ZIP archive.');
    }

    $entries = [];

    for ($index = 0; $index < $archive->numFiles; $index++) {
        $name = $archive->getNameIndex($index);
        $content = $name !== false ? $archive->getFromName($name) : false;

        if ($name === false || $content === false) {
            continue;
        }

        $entries[$name] = $content;
    }

    $archive->close();

    return $entries;
}
