<?php

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Models\OrganizationSetting;
use App\Models\UserProfile;
use App\Services\LoanRequests\PdfFieldMaps\GrepalifePdfFieldMap;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as SharedDrawing;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing as WorksheetDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use setasign\Fpdi\Fpdi;

beforeEach(function () {
    config()->set('reports.pdf_driver', 'dompdf');
    approvedLoanDocumentsEnsureWmasterTable();
    approvedLoanDocumentsBackupTemplateFilesForTests();
    approvedLoanDocumentsSeedTemplateFilesForTests();
});

afterEach(function () {
    approvedLoanDocumentsRestoreTemplateFilesAfterTests();
});

function approvedLoanDocumentsEnsureWmasterTable(): void
{
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

        return;
    }

    $missingStringColumns = collect([
        'fname',
        'mname',
        'lname',
        'bname',
        'beneficiary1',
        'beneficiary2',
        'beneficiary3',
        'ben1_acctno',
        'ben2_acctno',
        'ben3_acctno',
    ])->reject(fn (string $column): bool => Schema::hasColumn('wmaster', $column))->values()->all();

    $missingDateColumns = collect([
        'birthday',
        'ben1_bday',
        'ben2_bday',
        'ben3_bday',
    ])->reject(fn (string $column): bool => Schema::hasColumn('wmaster', $column))->values()->all();

    if ($missingStringColumns === [] && $missingDateColumns === []) {
        return;
    }

    Schema::table('wmaster', function (Blueprint $table) use (
        $missingStringColumns,
        $missingDateColumns,
    ) {
        foreach ($missingStringColumns as $column) {
            $table->string($column)->nullable();
        }

        foreach ($missingDateColumns as $column) {
            $table->date($column)->nullable();
        }
    });
}

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

        if ($document['route'] === 'admin.requests.documents.grepalife') {
            expect($content)->toContain('/Subtype /Image');
        }

        expect(approvedLoanDocumentsPdfPageCount($response))
            ->toBe($document['page_count']);
    }
});

test('loan security agreement pdf includes borrower and agreement details', function () {
    $admin = User::factory()->create([
        'username' => 'loan.manager',
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
        'fullname' => 'Annabelle M. Amora',
    ]);
    OrganizationSetting::factory()->create([
        'company_name' => 'Acme Cooperative',
        'support_contact_name' => 'Annabelle M. Amora',
    ]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();
    $loanRequest->update([
        'loan_type_label_snapshot' => 'SALARY LOAN',
        'approved_amount' => 25000,
        'approved_term' => 12,
        'reviewed_by' => $admin->user_id,
        'reviewed_at' => '2026-05-22 10:00:00',
    ]);

    LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('role', LoanRequestPersonRole::Applicant)
        ->firstOrFail()
        ->update([
            'first_name' => 'Helario',
            'middle_name' => 'B.',
            'last_name' => 'Tejero',
            'address1' => 'Banahao',
            'address2' => 'Lianga',
            'address3' => 'Surigao del Sur',
        ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.loan-security-agreement', $loanRequest));

    $response->assertOk();
    $response->assertDownload(
        $loanRequest->reference.' Loan Request Agreement.pdf',
    );

    $text = approvedLoanDocumentsExtractPdfText($response);
    $searchableText = strtoupper(str_replace(' ', '', $text));

    expect($searchableText)
        ->toContain('LOANSECURITYAGREEMENT')
        ->toContain('ACMECOOPERATIVE')
        ->toContain('HELARIOB.TEJERO')
        ->toContain('SALARYLOAN')
        ->toContain('22DAYOFMAY,2026')
        ->not->toContain('25,000.00');
});

test('grepalife pdf includes structured applicant fields when available', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);
    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $loanRequest->update([
        'reviewed_at' => '2026-05-22 10:00:00',
        'loan_type_label_snapshot' => 'SALARY LOAN',
    ]);

    $applicant = LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('role', LoanRequestPersonRole::Applicant)
        ->firstOrFail();

    $applicant->update([
        'birthplace_city' => 'BIRTH CITY',
        'birthplace_province' => 'BIRTH PROVINCE',
        'address1' => '18 SAMPLE STREET',
        'address2' => 'SAMPLE CITY',
        'address3' => 'SAMPLE PROVINCE',
        'employer_business_name' => 'SAMPLE ENTERPRISE',
        'nature_of_business' => 'TRANSPORT SERVICES',
        'current_position' => 'OPERATIONS SUPERVISOR',
        'years_in_work_business' => '7 YEARS',
        'employer_business_address1' => '88 WORK AVENUE',
        'employer_business_address2' => 'WORK CITY',
        'employer_business_address3' => 'WORK PROVINCE',
        'telephone_no' => '02-123-4567',
        'cell_no' => '09179990000',
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.grepalife', $loanRequest));

    $content = approvedLoanDocumentsReadDownloadedFileContent($response);

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'application/pdf');
    expect($content)
        ->toContain('BIRTH CITY, BIRTH PROVINCE')
        ->toContain('18 SAMPLE STREET')
        ->toContain('SAMPLE CITY')
        ->toContain('SAMPLE PROVINCE')
        ->toContain('88 WORK AVENUE')
        ->toContain('WORK CITY')
        ->toContain('WORK PROVINCE')
        ->toContain('02-123-4567')
        ->toContain('TRANSPORT SERVICES')
        ->toContain('7 YEARS')
        ->toContain('SALARY LOAN')
        ->toContain('05/22/2026')
        ->toContain('25,000.00');
});

test('grepalife field map keeps applicant values aligned with label padding', function () {
    $fields = collect((new GrepalifePdfFieldMap)->fields());

    $lastNameField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 1
            && ($field['value'] ?? null) === 'applicant.last_name',
    );
    $firstNameField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 1
            && ($field['value'] ?? null) === 'applicant.first_name',
    );
    $middleNameField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 1
            && ($field['value'] ?? null) === 'applicant.middle_name',
    );
    $nationalityField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 1
            && ($field['value'] ?? null) === 'applicant.nationality',
    );
    $birthdateField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 1
            && is_callable($field['value'] ?? null)
            && ($field['y'] ?? null) === 71.1
            && ($field['width'] ?? null) === 74,
    );
    $natureOfBusinessField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 1
            && ($field['value'] ?? null) === 'applicant.nature_of_business',
    );
    $yearsInWorkField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 1
            && ($field['value'] ?? null) === 'applicant.years_in_work_business',
    );
    $workPhoneField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 1
            && ($field['value'] ?? null) === 'applicant.work_phone',
    );
    $mobileField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 1
            && ($field['value'] ?? null) === 'applicant.mobile',
    );
    $emailField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 1
            && ($field['value'] ?? null) === 'applicant.email',
    );
    $termField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 1
            && ($field['value'] ?? null) === 'loan.approved_term_label',
    );
    $amountField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 1
            && ($field['value'] ?? null) === 'loan.approved_amount'
            && ($field['y'] ?? null) === 117.2,
    );
    $existingLoanYesField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 1
            && ($field['type'] ?? null) === 'check'
            && ($field['y'] ?? null) === 125.0,
    );
    $existingLoanDateField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 1
            && ($field['value'] ?? null) === 'loan.approved_date_short'
            && ($field['y'] ?? null) === 134.1,
    );
    $beneficiaryNameField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 1
            && ($field['value'] ?? null) === 'beneficiaries.0.name',
    );
    $beneficiaryBirthdateField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 1
            && ($field['value'] ?? null) === 'beneficiaries.0.birthdate',
    );
    $pageTwoCompanyField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 2
            && ($field['value'] ?? null) === 'organization.company_name',
    );
    $pageTwoDateField = $fields->first(
        fn (array $field): bool => ($field['page'] ?? null) === 2
            && ($field['value'] ?? null) === 'loan.approved_date_short',
    );

    expect($lastNameField['x'])->toBe(15.8);
    expect($firstNameField['x'])->toBe(15.8);
    expect($middleNameField['x'])->toBe(15.8);
    expect($nationalityField['x'])->toBe(15.0);
    expect($birthdateField['x'])->toBe(126.0);
    expect($birthdateField['align'] ?? 'L')->toBe('C');
    expect($natureOfBusinessField['y'])->toBe(92.8);
    expect($yearsInWorkField['x'])->toBe(148.0);
    expect($yearsInWorkField['y'])->toBe(92.8);
    expect($workPhoneField['y'])->toBe(110.8);
    expect($mobileField['y'])->toBe(110.8);
    expect($emailField['y'])->toBe(110.8);
    expect($termField['y'])->toBe(117.2);
    expect($termField['align'] ?? 'L')->toBe('C');
    expect($amountField['y'])->toBe(117.2);
    expect($amountField['align'] ?? 'L')->toBe('C');
    expect($existingLoanYesField['x'])->toBe(68.8);
    expect($existingLoanDateField['align'] ?? 'L')->toBe('C');
    expect($beneficiaryNameField['x'])->toBe(15.0);
    expect($beneficiaryBirthdateField['align'] ?? 'L')->toBe('C');
    expect($pageTwoCompanyField['x'])->toBe(126.8);
    expect($pageTwoCompanyField['y'])->toBe(88.6);
    expect($pageTwoCompanyField['align'] ?? 'L')->toBe('L');
    expect($pageTwoDateField['x'])->toBe(103.8);
    expect($pageTwoDateField['align'] ?? 'L')->toBe('L');
});

test('grepalife pdf includes beneficiaries from direct wmaster beneficiary columns', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);
    $member = User::factory()->create(['acctno' => '120001']);
    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople($member);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => '120001'],
        [
            'fname' => 'SAMPLE',
            'lname' => 'MEMBER',
            'bname' => 'SAMPLE MEMBER',
            'birthday' => '1990-01-01',
            'beneficiary1' => 'BENEFICIARY ONE',
            'beneficiary2' => 'BENEFICIARY TWO',
            'beneficiary3' => null,
            'ben1_bday' => '2001-02-03',
            'ben2_bday' => '2004-05-06',
            'ben3_bday' => null,
        ],
    );

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.grepalife', $loanRequest));

    $content = approvedLoanDocumentsReadDownloadedFileContent($response);

    $response->assertOk();
    expect($content)
        ->toContain('BENEFICIARY ONE')
        ->toContain('02/03/2001')
        ->toContain('BENEFICIARY TWO')
        ->toContain('05/06/2004');
});

test('grepalife pdf falls back to linked wmaster beneficiary account numbers', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);
    $member = User::factory()->create(['acctno' => '120002']);
    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople($member);

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => '120002'],
        [
            'fname' => 'SAMPLE',
            'lname' => 'MEMBER',
            'bname' => 'SAMPLE MEMBER',
            'birthday' => '1990-01-01',
            'ben1_acctno' => '220001',
            'ben2_acctno' => '220002',
            'ben3_acctno' => null,
        ],
    );

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => '220001'],
        [
            'fname' => 'BENEFICIARY',
            'lname' => 'ONE',
            'bname' => 'BENEFICIARY ONE',
            'birthday' => '1999-04-05',
        ],
    );

    DB::table('wmaster')->updateOrInsert(
        ['acctno' => '220002'],
        [
            'fname' => 'BENEFICIARY',
            'lname' => 'TWO',
            'bname' => 'BENEFICIARY TWO',
            'birthday' => '2000-06-07',
        ],
    );

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.grepalife', $loanRequest));

    $content = approvedLoanDocumentsReadDownloadedFileContent($response);

    $response->assertOk();
    expect($content)
        ->toContain('BENEFICIARY ONE')
        ->toContain('04/05/1999')
        ->toContain('BENEFICIARY TWO')
        ->toContain('06/07/2000');
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
        ->assertDownload($loanRequest->reference.' Loan Request Agreement.pdf');
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

test('plan of payment workbook includes a centered uploaded report header design on every worksheet', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);
    $headerPath = 'branding/report-headers/header.png';
    $headerImagePath = Storage::disk('public')->path($headerPath);

    File::ensureDirectoryExists(dirname($headerImagePath));
    approvedLoanDocumentsCreateTemplateImage(
        $headerImagePath,
        800,
        300,
        'Report Header',
    );
    OrganizationSetting::factory()->create([
        'company_name' => 'Acme Cooperative',
        'report_header_design_path' => $headerPath,
    ]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.plan-of-payment', $loanRequest));

    $response->assertOk();

    $spreadsheet = IOFactory::load(
        approvedLoanDocumentsDownloadedFilePath($response),
    );
    expect($spreadsheet->getSheetCount())->toBe(4);

    foreach (range(0, $spreadsheet->getSheetCount() - 1) as $sheetIndex) {
        $worksheet = $spreadsheet->getSheet($sheetIndex);
        $drawings = $worksheet->getDrawingCollection();
        $drawing = $drawings[0] ?? null;
        $printAreaRange = approvedLoanDocumentsWorksheetPrintAreaRange($worksheet);
        $expectedPrintAreaRange = approvedLoanDocumentsExpectedWorksheetPrintAreaRange(
            $worksheet->getTitle(),
        ) ?? approvedLoanDocumentsUsedColumnRange($worksheet);
        $headerPlacementRange = approvedLoanDocumentsHeaderPlacementRange(
            $worksheet,
        );
        $drawingWidth = $drawing instanceof WorksheetDrawing
            ? $drawing->getWidth()
            : 0;
        $headerAreaWidth = approvedLoanDocumentsWorksheetWidthInPixels(
            $worksheet,
            $spreadsheet,
            $headerPlacementRange['startColumn'],
            $headerPlacementRange['endColumn'],
        );
        $printableWidth = approvedLoanDocumentsPrintableWidthInPixels($worksheet);
        $centeringWidth = approvedLoanDocumentsExpectedHeaderCenteringWidth(
            $worksheet,
            $headerAreaWidth,
            $printableWidth,
        );
        $expectedOffsetX = max(
            0,
            (int) floor(($centeringWidth - $drawingWidth) / 2)
                + approvedLoanDocumentsExpectedHeaderOffsetXAdjustment(
                    $worksheet,
                ),
        );
        $actualOffsetX = $drawing instanceof WorksheetDrawing
            ? approvedLoanDocumentsDrawingLeftOffsetInPixels(
                $drawing,
                $worksheet,
                $spreadsheet,
                $headerPlacementRange['startColumn'],
            )
            : 0;
        $headerRowCount = approvedLoanDocumentsHeaderRowCount($worksheet);
        $reservedHeaderHeight = approvedLoanDocumentsReservedHeaderHeightInPixels(
            $worksheet,
            $headerRowCount,
        );

        expect($drawings)->toHaveCount(1);
        expect($drawing)->toBeInstanceOf(WorksheetDrawing::class);
        expect($drawing->getName())->toBe('Report Header Design');
        expect(str_ends_with($drawing->getCoordinates(), '1'))->toBeTrue();
        expect($printAreaRange)->not->toBeNull();
        expect($printAreaRange)->toBe($expectedPrintAreaRange);
        expect(abs($actualOffsetX - $expectedOffsetX))->toBeLessThanOrEqual(15);
        expect($actualOffsetX)->toBeGreaterThanOrEqual(0);
        expect($drawing->getHeight())->toBeGreaterThan(0);
        expect($drawing->getHeight())->toBeLessThanOrEqual(
            SharedDrawing::pointsToPixels(70.0),
        );
        expect($drawing->getWidth())->toBeGreaterThan(0);
        expect($drawing->getWidth())->toBeLessThanOrEqual($headerAreaWidth);
        expect($reservedHeaderHeight)->toBeGreaterThanOrEqual(
            $drawing->getHeight() + 20,
        );

        if ($worksheet->getTitle() === 'Promissory Note') {
            expect($worksheet->getPageSetup()->getFitToPage())->toBeTrue();
            expect($worksheet->getPageSetup()->getFitToWidth())->toBe(1);
            expect($worksheet->getPageSetup()->getFitToHeight())->toBe(0);
            expect($worksheet->getColumnDimension('L')->getVisible())->toBeFalse();
            expect($worksheet->getColumnDimension('M')->getVisible())->toBeFalse();
            expect($worksheet->getStyle('C12')->getAlignment()->getWrapText())->toBeTrue();
            expect($worksheet->getStyle('H14')->getAlignment()->getWrapText())->toBeTrue();
            expect($worksheet->getStyle('I50')->getAlignment()->getWrapText())->toBeTrue();
            expect(
                (float) $worksheet->getRowDimension(12)->getRowHeight(),
            )->toBeGreaterThan(15.0);
            expect(
                (float) $worksheet->getRowDimension(14)->getRowHeight(),
            )->toBeGreaterThan(15.0);
            expect(
                (float) $worksheet->getRowDimension(53)->getRowHeight(),
            )->toBeGreaterThan(15.0);
            expect(
                approvedLoanDocumentsMaximumMergedEndColumnIndex($worksheet),
            )->toBeLessThanOrEqual(
                Coordinate::columnIndexFromString('K'),
            );
            expect((string) $worksheet->getCell('K15')->getValue())
                ->toContain('L15');
        }
    }

    $firstSheet = $spreadsheet->getSheet(0);
    expect($firstSheet->getCell('C7')->getValue())->toBe('Sample Q Member');
    expect($spreadsheet->getSheet(2)->getCell('M7')->getValue())->toBe(
        $loanRequest->reference,
    );
    expect($spreadsheet->getSheet(3)->getCell('I8')->getValue())->not->toBe('');

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

test('missing grepalife image template is logged and fails generation', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);
    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    File::delete(
        approvedLoanDocumentsTemplateDirectory()
        .DIRECTORY_SEPARATOR
        .'images'
        .DIRECTORY_SEPARATOR
        .'grepalife-page-1.png',
    );
    File::delete(
        storage_path(
            'app/public/app/templates/approved-loan-documents/images/grepalife-page-1.png',
        ),
    );

    Log::spy();
    $this->actingAs($admin);
    $this->withoutExceptionHandling();

    expect(fn () => $this->get(
        route('admin.requests.documents.grepalife', $loanRequest),
    ))->toThrow(
        \RuntimeException::class,
        'Missing image template file: grepalife-page-1.png',
    );

    Log::shouldHaveReceived('error')
        ->withArgs(function (string $message, array $context): bool {
            $templatePath = str_replace(
                '\\',
                '/',
                (string) ($context['template_path'] ?? ''),
            );
            $fallbackTemplatePath = str_replace(
                '\\',
                '/',
                (string) ($context['fallback_template_path'] ?? ''),
            );

            return $message === 'Missing approved loan image template file.'
                && ($context['template_image'] ?? null) === 'grepalife-page-1.png'
                && (
                    str_contains(
                        $templatePath,
                        'storage/app/templates/approved-loan-documents/images/grepalife-page-1.png',
                    )
                    || str_contains(
                        $fallbackTemplatePath,
                        'storage/app/public/app/templates/approved-loan-documents/images/grepalife-page-1.png',
                    )
                );
        })
        ->once();
});

test('template directory backup helpers preserve grepalife public image files', function () {
    $sourceDirectory = storage_path(
        'app/testing-backups/approved-loan-documents-public-source',
    );
    $backupDirectory = storage_path(
        'app/testing-backups/approved-loan-documents-public-source-backup',
    );
    $imagePath = $sourceDirectory.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.'grepalife-page-1.png';

    File::deleteDirectory($sourceDirectory);
    File::deleteDirectory($backupDirectory);
    File::ensureDirectoryExists(dirname($imagePath));
    File::put($imagePath, 'public-grepalife-template-image');

    approvedLoanDocumentsBackupDirectoryForTests(
        $sourceDirectory,
        $backupDirectory,
    );

    File::delete($imagePath);

    approvedLoanDocumentsRestoreDirectoryForTests(
        $sourceDirectory,
        $backupDirectory,
    );

    expect(File::exists($imagePath))->toBeTrue();
    expect(File::get($imagePath))->toBe('public-grepalife-template-image');

    File::deleteDirectory($sourceDirectory);
    File::deleteDirectory($backupDirectory);
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
            'filename' => $loanRequest->reference.' Loan Request Agreement.pdf',
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

function approvedLoanDocumentsExtractPdfText(
    \Illuminate\Testing\TestResponse $response,
): string {
    $content = approvedLoanDocumentsReadDownloadedFileContent($response);
    $text = '';

    if (
        preg_match_all(
            '/stream\\r?\\n(.*?)\\r?\\nendstream/s',
            $content,
            $matches,
        ) !== 1
        && ($matches[1] ?? []) === []
    ) {
        return '';
    }

    foreach ($matches[1] as $stream) {
        $decoded = approvedLoanDocumentsDecodePdfStream($stream);
        $text .= ' '.approvedLoanDocumentsExtractPdfOperators($decoded);
    }

    $text = str_replace(["\x00", "\r", "\n", "\t", "\f"], ' ', $text);
    $normalized = preg_replace('/ {2,}/', ' ', trim($text));

    return is_string($normalized) ? $normalized : trim($text);
}

function approvedLoanDocumentsDecodePdfStream(string $stream): string
{
    $candidate = ltrim($stream, "\r\n");

    $decoded = @gzuncompress($candidate);

    if (! is_string($decoded)) {
        $decoded = @gzuncompress(substr($candidate, 2));
    }

    if (! is_string($decoded)) {
        $decoded = @gzinflate($candidate);
    }

    if (! is_string($decoded) && strlen($candidate) > 6) {
        $decoded = @gzinflate(substr($candidate, 2));
    }

    return is_string($decoded) ? $decoded : $candidate;
}

function approvedLoanDocumentsExtractPdfOperators(string $decoded): string
{
    $text = '';

    if (
        preg_match_all(
            '/\[(.*?)\]\s*TJ/s',
            $decoded,
            $textArrays,
        ) === 1
        || ($textArrays[1] ?? []) !== []
    ) {
        foreach ($textArrays[1] as $arrayBody) {
            if (
                preg_match_all(
                    '/\((?:\\\\.|[^\\\\()])*\)|<[0-9A-Fa-f]+>/',
                    $arrayBody,
                    $segments,
                ) !== 1
                && ($segments[0] ?? []) === []
            ) {
                continue;
            }

            foreach ($segments[0] as $segment) {
                $text .= ' '.approvedLoanDocumentsDecodePdfTextOperand($segment);
            }
        }
    }

    if (
        preg_match_all(
            '/\((?:\\\\.|[^\\\\()])*\)\s*Tj/s',
            $decoded,
            $textMatches,
        ) === 1
        || ($textMatches[0] ?? []) !== []
    ) {
        foreach ($textMatches[0] as $match) {
            if (
                preg_match(
                    '/(\((?:\\\\.|[^\\\\()])*\))\s*Tj/s',
                    $match,
                    $operand,
                ) === 1
            ) {
                $text .= ' '.approvedLoanDocumentsDecodePdfTextOperand(
                    $operand[1],
                );
            }
        }
    }

    if (
        preg_match_all(
            '/<[0-9A-Fa-f]+>\s*Tj/s',
            $decoded,
            $hexMatches,
        ) === 1
        || ($hexMatches[0] ?? []) !== []
    ) {
        foreach ($hexMatches[0] as $match) {
            if (preg_match('/(<[0-9A-Fa-f]+>)\s*Tj/s', $match, $operand) === 1) {
                $text .= ' '.approvedLoanDocumentsDecodePdfTextOperand(
                    $operand[1],
                );
            }
        }
    }

    return $text;
}

function approvedLoanDocumentsDecodePdfTextOperand(string $operand): string
{
    if (str_starts_with($operand, '(')) {
        $text = substr($operand, 1, -1);
        $text = preg_replace_callback(
            '/\\\\([0-7]{1,3})/',
            static function (array $matches): string {
                return chr(octdec($matches[1]));
            },
            $text,
        );
        $text = strtr((string) $text, [
            '\\\\' => '\\',
            '\\(' => '(',
            '\\)' => ')',
            '\\n' => ' ',
            '\\r' => ' ',
            '\\t' => ' ',
            '\\f' => '',
            '\\b' => '',
        ]);

        return trim($text);
    }

    if (! str_starts_with($operand, '<')) {
        return '';
    }

    $hex = substr($operand, 1, -1);
    $binary = hex2bin((strlen($hex) % 2 === 0 ? $hex : $hex.'0'));

    if (! is_string($binary)) {
        return '';
    }

    $looksUtf16Le = str_starts_with($binary, "\xFF\xFE")
        || preg_match('/^(?:[\x00-\x7F]\x00)+[\x00-\x7F]?$/', $binary) === 1;
    $looksUtf16Be = str_starts_with($binary, "\xFE\xFF")
        || preg_match('/^(?:\x00[\x00-\x7F])+\x00?$/', $binary) === 1;

    if ($looksUtf16Le || $looksUtf16Be) {
        $encoding = $looksUtf16Le ? 'UTF-16LE' : 'UTF-16BE';
        $converted = @mb_convert_encoding($binary, 'UTF-8', $encoding);

        return is_string($converted) ? trim($converted) : '';
    }

    return trim($binary);
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

function approvedLoanDocumentsCreateLoanRequestPeopleSnapshots(
    LoanRequest $loanRequest,
): void {
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

function approvedLoanDocumentsTemplateImagesDirectory(): string
{
    return approvedLoanDocumentsTemplateDirectory().DIRECTORY_SEPARATOR.'images';
}

function approvedLoanDocumentsSeedGrepalifeTemplateImagesForTests(): void
{
    $imagesDirectory = approvedLoanDocumentsTemplateImagesDirectory();

    File::ensureDirectoryExists($imagesDirectory);

    approvedLoanDocumentsCreateTemplateImage(
        $imagesDirectory.DIRECTORY_SEPARATOR.'grepalife-page-1.png',
        216,
        279,
        'GREPALIFE Page 1',
    );
    approvedLoanDocumentsCreateTemplateImage(
        $imagesDirectory.DIRECTORY_SEPARATOR.'grepalife-page-2.png',
        216,
        279,
        'GREPALIFE Page 2',
    );
}

function approvedLoanDocumentsCreateTemplateImage(
    string $path,
    int $width,
    int $height,
    string $title,
): void {
    $image = imagecreatetruecolor($width, $height);

    if ($image === false) {
        throw new \RuntimeException('Unable to create template image.');
    }

    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $blue = imagecolorallocate($image, 34, 104, 170);

    imagefilledrectangle($image, 0, 0, $width, $height, $white);
    imagefilledrectangle($image, 0, 0, $width, 34, $blue);
    imagerectangle($image, 12, 48, $width - 12, $height - 12, $black);
    imagestring($image, 5, 16, 10, $title, $white);
    imagestring($image, 4, 18, 56, 'Approved loan image template background', $black);
    imagestring($image, 3, 18, 74, 'Used by tests for GREPALIFE image rendering', $black);

    imagepng($image, $path);
    imagedestroy($image);
}

function approvedLoanDocumentsWorksheetWidthInPixels(
    Worksheet $worksheet,
    Spreadsheet $spreadsheet,
    string $startColumn,
    string $endColumn,
): int {
    $font = $spreadsheet->getDefaultStyle()->getFont();
    $startColumnIndex = Coordinate::columnIndexFromString($startColumn);
    $endColumnIndex = Coordinate::columnIndexFromString($endColumn);
    $totalWidth = 0;

    for ($column = $startColumnIndex; $column <= $endColumnIndex; $column++) {
        $columnLetter = Coordinate::stringFromColumnIndex($column);
        $columnWidth = (float) $worksheet->getColumnDimension($columnLetter)->getWidth();

        if ($columnWidth <= 0) {
            $columnWidth = 8.43;
        }

        $totalWidth += SharedDrawing::cellDimensionToPixels($columnWidth, $font);
    }

    return $totalWidth;
}

function approvedLoanDocumentsPrintableWidthInPixels(
    Worksheet $worksheet,
): int {
    $paperDimensions = match ($worksheet->getPageSetup()->getPaperSize()) {
        PageSetup::PAPERSIZE_LETTER,
        PageSetup::PAPERSIZE_LETTER_SMALL => [8.5, 11.0],
        PageSetup::PAPERSIZE_LEGAL => [8.5, 14.0],
        PageSetup::PAPERSIZE_A4,
        PageSetup::PAPERSIZE_A4_SMALL => [8.27, 11.69],
        PageSetup::PAPERSIZE_FOLIO => [8.5, 13.0],
        default => [8.5, 11.0],
    };
    [$paperWidth, $paperHeight] = $paperDimensions;

    if ($worksheet->getPageSetup()->getOrientation() === PageSetup::ORIENTATION_LANDSCAPE) {
        [$paperWidth, $paperHeight] = [$paperHeight, $paperWidth];
    }

    $printableWidthInches = $paperWidth
        - $worksheet->getPageMargins()->getLeft()
        - $worksheet->getPageMargins()->getRight();

    return max(1, (int) floor($printableWidthInches * 96));
}

/**
 * @return array{startColumn: string, endColumn: string}
 */
function approvedLoanDocumentsHeaderPlacementRange(
    Worksheet $worksheet,
): array {
    return approvedLoanDocumentsWorksheetPrintAreaRange($worksheet)
        ?? approvedLoanDocumentsExpectedWorksheetPrintAreaRange(
            $worksheet->getTitle(),
        )
        ?? approvedLoanDocumentsUsedColumnRange($worksheet)
        ?? [
            'startColumn' => 'A',
            'endColumn' => 'L',
        ];
}

function approvedLoanDocumentsExpectedHeaderCenteringWidth(
    Worksheet $worksheet,
    int $headerAreaWidth,
    int $printableWidth,
): int {
    return match ($worksheet->getTitle()) {
        'Loan Information', 'Plan of Payment' => $headerAreaWidth,
        default => $printableWidth > 0
            ? $printableWidth
            : $headerAreaWidth,
    };
}

function approvedLoanDocumentsExpectedHeaderOffsetXAdjustment(
    Worksheet $worksheet,
): int {
    return match ($worksheet->getTitle()) {
        'Loan Information' => 40,
        'Plan of Payment' => 48,
        default => 0,
    };
}

/**
 * @return array{startColumn: string, endColumn: string}|null
 */
function approvedLoanDocumentsWorksheetPrintAreaRange(
    Worksheet $worksheet,
): ?array {
    $printArea = trim((string) $worksheet->getPageSetup()->getPrintArea());

    if ($printArea === '') {
        return null;
    }

    $firstRange = trim(explode(',', $printArea)[0] ?? '');
    $firstRange = preg_replace('/^[^!]+!/', '', $firstRange) ?? $firstRange;
    $firstRange = str_replace('$', '', $firstRange);

    if ($firstRange === '') {
        return null;
    }

    [$startBoundary, $endBoundary] = Coordinate::rangeBoundaries($firstRange);

    return [
        'startColumn' => Coordinate::stringFromColumnIndex($startBoundary[0]),
        'endColumn' => Coordinate::stringFromColumnIndex($endBoundary[0]),
    ];
}

/**
 * @return array{startColumn: string, endColumn: string}|null
 */
function approvedLoanDocumentsExpectedWorksheetPrintAreaRange(
    string $worksheetTitle,
): ?array {
    return match ($worksheetTitle) {
        'Loan Information' => [
            'startColumn' => 'A',
            'endColumn' => 'H',
        ],
        'Plan of Payment' => [
            'startColumn' => 'A',
            'endColumn' => 'I',
        ],
        'Promissory Note' => [
            'startColumn' => 'A',
            'endColumn' => 'K',
        ],
        default => null,
    };
}

/**
 * @return array{startColumn: string, endColumn: string}|null
 */
function approvedLoanDocumentsUsedColumnRange(
    Worksheet $worksheet,
): ?array {
    $highestRow = $worksheet->getHighestRow();
    $highestColumnIndex = Coordinate::columnIndexFromString(
        $worksheet->getHighestDataColumn(),
    );
    $startColumnIndex = null;
    $endColumnIndex = null;

    for ($row = 1; $row <= $highestRow; $row++) {
        for ($column = 1; $column <= $highestColumnIndex; $column++) {
            $value = $worksheet->getCell(
                Coordinate::stringFromColumnIndex($column).$row,
            )->getValue();

            if ($value === null || $value === '') {
                continue;
            }

            $startColumnIndex = $startColumnIndex === null
                ? $column
                : min($startColumnIndex, $column);
            $endColumnIndex = $endColumnIndex === null
                ? $column
                : max($endColumnIndex, $column);
        }
    }

    foreach ($worksheet->getMergeCells() as $mergedRange) {
        [$startBoundary, $endBoundary] = Coordinate::rangeBoundaries(
            str_replace('$', '', $mergedRange),
        );
        $topLeftCoordinate = Coordinate::stringFromColumnIndex(
            $startBoundary[0],
        ).$startBoundary[1];
        $value = $worksheet->getCell($topLeftCoordinate)->getValue();

        if ($value === null || $value === '') {
            continue;
        }

        $startColumnIndex = $startColumnIndex === null
            ? $startBoundary[0]
            : min($startColumnIndex, $startBoundary[0]);
        $endColumnIndex = $endColumnIndex === null
            ? $endBoundary[0]
            : max($endColumnIndex, $endBoundary[0]);
    }

    if ($startColumnIndex === null || $endColumnIndex === null) {
        return null;
    }

    return [
        'startColumn' => Coordinate::stringFromColumnIndex($startColumnIndex),
        'endColumn' => Coordinate::stringFromColumnIndex($endColumnIndex),
    ];
}

/**
 * @return array{startColumn: string, endColumn: string}|null
 */
function approvedLoanDocumentsMaximumMergedEndColumnIndex(
    Worksheet $worksheet,
): int {
    $maximumColumnIndex = 0;

    foreach ($worksheet->getMergeCells() as $mergedRange) {
        [, $endBoundary] = Coordinate::rangeBoundaries(
            str_replace('$', '', $mergedRange),
        );
        $maximumColumnIndex = max($maximumColumnIndex, $endBoundary[0]);
    }

    return $maximumColumnIndex;
}

function approvedLoanDocumentsHeaderRowCount(
    Worksheet $worksheet,
): int {
    $firstContentRow = approvedLoanDocumentsFirstContentRow($worksheet);

    if ($firstContentRow === null) {
        return 4;
    }

    return $firstContentRow > 1
        ? $firstContentRow - 1
        : 4;
}

function approvedLoanDocumentsReservedHeaderHeightInPixels(
    Worksheet $worksheet,
    int $headerRowCount,
): int {
    $availableHeight = 0;
    $defaultRowHeight = (float) $worksheet->getDefaultRowDimension()->getRowHeight();

    if ($defaultRowHeight <= 0) {
        $defaultRowHeight = 15.0;
    }

    for ($row = 1; $row <= $headerRowCount; $row++) {
        $rowHeight = (float) $worksheet->getRowDimension($row)->getRowHeight();

        if ($rowHeight <= 0) {
            $rowHeight = $defaultRowHeight;
        }

        $availableHeight += SharedDrawing::pointsToPixels($rowHeight);
    }

    return $availableHeight;
}

function approvedLoanDocumentsFirstContentRow(
    Worksheet $worksheet,
): ?int {
    $highestRow = $worksheet->getHighestRow();
    $highestColumnIndex = Coordinate::columnIndexFromString(
        $worksheet->getHighestColumn(),
    );

    for ($row = 1; $row <= $highestRow; $row++) {
        for ($column = 1; $column <= $highestColumnIndex; $column++) {
            $value = $worksheet->getCell(
                Coordinate::stringFromColumnIndex($column).$row,
            )->getValue();

            if ($value !== null && $value !== '') {
                return $row;
            }
        }
    }

    return null;
}

function approvedLoanDocumentsDrawingLeftOffsetInPixels(
    WorksheetDrawing $drawing,
    Worksheet $worksheet,
    Spreadsheet $spreadsheet,
    string $areaStartColumn,
): int {
    [$drawingColumn] = Coordinate::coordinateFromString($drawing->getCoordinates());
    $areaStartColumnIndex = Coordinate::columnIndexFromString($areaStartColumn);
    $drawingColumnIndex = Coordinate::columnIndexFromString($drawingColumn);

    if ($drawingColumnIndex <= $areaStartColumnIndex) {
        return $drawing->getOffsetX();
    }

    return approvedLoanDocumentsWorksheetWidthInPixels(
        $worksheet,
        $spreadsheet,
        $areaStartColumn,
        Coordinate::stringFromColumnIndex($drawingColumnIndex - 1),
    ) + $drawing->getOffsetX();
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

function approvedLoanDocumentsSeedTemplateFilesForTests(): void
{
    $templateDirectory = approvedLoanDocumentsTemplateDirectory();
    $excelDirectory = $templateDirectory.DIRECTORY_SEPARATOR.'excel';
    $pdfDirectory = $templateDirectory.DIRECTORY_SEPARATOR.'pdf';

    File::deleteDirectory($templateDirectory);
    File::ensureDirectoryExists($excelDirectory);
    File::ensureDirectoryExists($pdfDirectory);
    approvedLoanDocumentsSeedGrepalifeTemplateImagesForTests();

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
    $loanInformationSheet->setCellValue('A5', 'LOAN INFORMATION SHEET');
    $loanInformationSheet->mergeCells('A5:I5');
    $loanInformationSheet->setCellValue('A6', 'A. FOR DISCLOSURE STATEMENT');
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
    $planSheet->setCellValue('G6', 'Date');
    $planSheet->mergeCells('G6:I6');
    $planSheet->setCellValue('B8', 'PLAN OF PAYMENT');
    $planSheet->mergeCells('B8:H8');
    $planSheet->setCellValue('A9', 'Name');
    $planSheet->setCellValue('C9', ':');
    $planSheet->mergeCells('D9:G9');
    $planSheet->setCellValue('D9', "='Loan Information'!C7");
    $planSheet->setCellValue('A10', 'Address');
    $planSheet->setCellValue('C10', ':');
    $planSheet->mergeCells('D10:G10');
    $planSheet->setCellValue('D10', "='Loan Information'!C8");
    $planSheet->setCellValue('A11', 'Amount of Loan');
    $planSheet->setCellValue('C11', ':');
    $planSheet->setCellValue('D11', "='Loan Information'!C9");
    $planSheet->setCellValue('A12', 'Kind of Loan');
    $planSheet->setCellValue('C12', ':');
    $planSheet->mergeCells('D12:G12');
    $planSheet->setCellValue('D12', "='Loan Information'!C16");
    $planSheet->setCellValue('B14', 'MODE OF PAYMENT');
    $planSheet->mergeCells('B14:H14');
    $planSheet->setCellValue('B15', "='Loan Information'!C17");
    $planSheet->mergeCells('B15:H15');
    $planSheet->setCellValue('D17', "='Loan Information'!C27");
    $planSheet->setCellValue('D18', "='Loan Information'!C28");
    $planSheet->setCellValue('D19', "='Loan Information'!C29");
    $planSheet->setCellValue('D20', "='Loan Information'!C30");
    $planSheet->setCellValue('C22', '01/01/2025');
    $planSheet->setCellValue('G22', '12/31/2025');
    $planSheet->mergeCells('G22:H22');

    $disclosureSheet = $spreadsheet->createSheet();
    $disclosureSheet->setTitle('Disclosure Statement');
    $disclosureSheet->setCellValue(
        'B4',
        'DISCLOSURE STATEMENT ON LOAN/CREDIT TRANSACTION',
    );
    $disclosureSheet->mergeCells('B4:N4');
    $disclosureSheet->setCellValue(
        'D5',
        '(As Required Under R.A. 3765 Truth In Lending Act)',
    );
    $disclosureSheet->mergeCells('D5:M5');
    $disclosureSheet->setCellValue('A7', 'NAME OF BORROWER:');
    $disclosureSheet->setCellValue('D7', "='Loan Information'!C7");
    $disclosureSheet->setCellValue('L7', 'LOAN NUMBER');
    $disclosureSheet->setCellValue('A8', 'ADDRESS:');
    $disclosureSheet->setCellValue('C8', "='Loan Information'!C8");
    $disclosureSheet->setCellValue('A9', 1);
    $disclosureSheet->setCellValue(
        'B9',
        'LOAN GRANTED (Amount to be financed)',
    );
    $disclosureSheet->setCellValue('M9', '(Php)');
    $disclosureSheet->setCellValue('N9', "='Loan Information'!C9");
    $disclosureSheet->setCellValue('O9', '( A )');
    $disclosureSheet->setCellValue('A10', 2);
    $disclosureSheet->setCellValue('B10', 'FINANCE CHARGES');
    $disclosureSheet->setCellValue('J11', 'Not Deducted');
    $disclosureSheet->setCellValue('L11', 'Deducted');
    $disclosureSheet->setCellValue('J12', 'From');
    $disclosureSheet->setCellValue('L12', 'From');
    $disclosureSheet->setCellValue('J13', 'Proceeds of Loan');
    $disclosureSheet->mergeCells('J13:L13');
    $disclosureSheet->setCellValue('A14', 'a.');
    $disclosureSheet->setCellValue('B14', 'Interest');
    $disclosureSheet->setCellValue('D14', "='Loan Information'!C10");
    $disclosureSheet->setCellValue('F14', '01/01/2025');
    $disclosureSheet->setCellValue('H14', '12/31/2025');
    $disclosureSheet->setCellValue('I14', 'P');
    $disclosureSheet->setCellValue('J14', "='Loan Information'!C13");
    $disclosureSheet->setCellValue('K14', 'P');
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
    $promissoryNoteSheet->setCellValue('D6', 'PROMISSORY NOTE');
    $promissoryNoteSheet->mergeCells('D6:H6');
    $promissoryNoteSheet->setCellValue('H8', 'Date Granted:');
    $promissoryNoteSheet->setCellValue('J10', "='Loan Information'!C9");
    $promissoryNoteSheet->setCellValue('H9', 'Date Due:');
    $promissoryNoteSheet->setCellValue('H10', 'Amount:      ');
    $promissoryNoteSheet->setCellValue('I10', 'P');
    $promissoryNoteSheet->setCellValue('A12', "='Loan Information'!C36");
    $promissoryNoteSheet->mergeCells('A12:B12');
    $promissoryNoteSheet->setCellValue(
        'C12',
        'days after date for value received,   I/we promise to pay jointly and severally to the order of  MICROFINANCE FOR RURAL',
    );
    $promissoryNoteSheet->setCellValue(
        'A13',
        'DEVELOPMENT INC. the sum of',
    );
    $promissoryNoteSheet->mergeCells('D13:H13');
    $promissoryNoteSheet->setCellValue('D13', "='Loan Information'!C37");
    $promissoryNoteSheet->setCellValue('I13', 'P');
    $promissoryNoteSheet->setCellValue('J13', "='Loan Information'!C9");
    $promissoryNoteSheet->setCellValue(
        'A14',
        'Philippine Currency with an interest rate of',
    );
    $promissoryNoteSheet->mergeCells('E14:G14');
    $promissoryNoteSheet->setCellValue('E14', "='Loan Information'!C38");
    $promissoryNoteSheet->setCellValue(
        'H14',
        'per annum. Amortization/Installment payment of',
    );
    $promissoryNoteSheet->setCellValue('A15', "='Loan Information'!C30");
    $promissoryNoteSheet->mergeCells('A15:B15');
    $promissoryNoteSheet->setCellValue('C15', 'inclusive of interest every');
    $promissoryNoteSheet->setCellValue('E15', "='Loan Information'!C39");
    $promissoryNoteSheet->mergeCells('G15:H15');
    $promissoryNoteSheet->setCellValue('F15', 'starting');
    $promissoryNoteSheet->setCellValue('I15', 'to');
    $promissoryNoteSheet->setCellValue('I8', '01/01/2025');
    $promissoryNoteSheet->setCellValue('I9', '12/31/2025');
    $promissoryNoteSheet->setCellValue('G15', '01/01/2025');
    $promissoryNoteSheet->setCellValue('J15', '12/31/2025');
    $promissoryNoteSheet->setCellValue('K15', 'for ');
    $promissoryNoteSheet->setCellValue('L15', "='Loan Information'!C40");
    $promissoryNoteSheet->mergeCells('I8:K8');
    $promissoryNoteSheet->mergeCells('I9:K9');

    IOFactory::createWriter($spreadsheet, 'Xlsx')->save(
        $excelDirectory.DIRECTORY_SEPARATOR.'plan-of-payment-disclosure-promissory-note.xlsx',
    );

    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
}

function approvedLoanDocumentsTemplateDirectory(): string
{
    return storage_path('app/templates/approved-loan-documents');
}

function approvedLoanDocumentsPublicTemplateDirectory(): string
{
    return storage_path('app/public/app/templates/approved-loan-documents');
}

function approvedLoanDocumentsTemplateBackupDirectory(): string
{
    return storage_path('app/testing-backups/approved-loan-documents');
}

function approvedLoanDocumentsPublicTemplateBackupDirectory(): string
{
    return storage_path('app/testing-backups/public-approved-loan-documents');
}

function approvedLoanDocumentsBackupDirectoryForTests(
    string $sourceDirectory,
    string $backupDirectory,
): void {
    File::deleteDirectory($backupDirectory);

    if (! File::isDirectory($sourceDirectory)) {
        File::ensureDirectoryExists($backupDirectory);

        return;
    }

    File::copyDirectory($sourceDirectory, $backupDirectory);
}

function approvedLoanDocumentsRestoreDirectoryForTests(
    string $sourceDirectory,
    string $backupDirectory,
): void {
    File::deleteDirectory($sourceDirectory);

    if (File::isDirectory($backupDirectory)) {
        File::ensureDirectoryExists($sourceDirectory);
        File::copyDirectory($backupDirectory, $sourceDirectory);
    }

    File::deleteDirectory($backupDirectory);
}

function approvedLoanDocumentsBackupTemplateFilesForTests(): void
{
    $templateDirectory = approvedLoanDocumentsTemplateDirectory();
    $backupDirectory = approvedLoanDocumentsTemplateBackupDirectory();
    $publicTemplateDirectory = approvedLoanDocumentsPublicTemplateDirectory();
    $publicBackupDirectory = approvedLoanDocumentsPublicTemplateBackupDirectory();

    approvedLoanDocumentsBackupDirectoryForTests(
        $templateDirectory,
        $backupDirectory,
    );
    approvedLoanDocumentsBackupDirectoryForTests(
        $publicTemplateDirectory,
        $publicBackupDirectory,
    );
}

function approvedLoanDocumentsRestoreTemplateFilesAfterTests(): void
{
    $templateDirectory = approvedLoanDocumentsTemplateDirectory();
    $backupDirectory = approvedLoanDocumentsTemplateBackupDirectory();
    $publicTemplateDirectory = approvedLoanDocumentsPublicTemplateDirectory();
    $publicBackupDirectory = approvedLoanDocumentsPublicTemplateBackupDirectory();

    approvedLoanDocumentsRestoreDirectoryForTests(
        $templateDirectory,
        $backupDirectory,
    );
    approvedLoanDocumentsRestoreDirectoryForTests(
        $publicTemplateDirectory,
        $publicBackupDirectory,
    );
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
