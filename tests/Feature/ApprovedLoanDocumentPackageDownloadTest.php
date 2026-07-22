<?php

use App\LoanRequestPersonRole;
use App\LoanRequestStatus;
use App\Models\AdminProfile;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\LoanRequestDataEntry;
use App\Models\LoanRequestPerson;
use App\Models\MemberApplicationProfile;
use App\Models\OrganizationSetting;
use App\Models\UserProfile;
use App\Services\LoanRequests\ApprovedLoanDocumentService;
use App\Services\LoanRequests\ApprovedLoanImageTemplatePdfService;
use App\Services\LoanRequests\PdfFieldMaps\AffidavitUndertakingPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\AuthorizationPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\GrepalifePdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\LoanInformationPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\UndertakingBarangayPdfFieldMap;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as SharedDrawing;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing as WorksheetDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use setasign\Fpdi\Fpdi;

beforeEach(function () {
    config()->set('reports.pdf_driver', 'dompdf');
    approvedLoanDocumentsEnsureWmasterTable();
    approvedLoanDocumentsSeedTemplateFilesForTests();
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
        'business_address' => 'Poblacion, Tagum City, Davao del Norte',
        'business_address1' => 'Poblacion',
        'business_address2' => 'Tagum City',
        'business_address3' => 'Davao del Norte',
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
        ->toContain('TAGUMCITY,DAVAODELNORTE')
        ->toContain('SALARYLOAN')
        ->toContain('22DAYOFMAY,2026')
        ->not->toContain('25,000.00');
});

test('promissory note route returns a pdf not xlsx', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.promissory-note', $loanRequest));

    $content = approvedLoanDocumentsReadDownloadedFileContent($response);

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'application/pdf');
    $response->assertDownload('promissory-note-'.$loanRequest->reference.'.pdf');
    expect($content)->toStartWith('%PDF')->not->toStartWith('PK');
});

test('promissory note pdf includes borrower co-makers witnesses and amounts', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
        'fullname' => 'Annabelle M. Amora',
    ]);

    OrganizationSetting::factory()->create([
        'company_name' => 'Acme Cooperative',
        'business_address' => 'Poblacion, Tagum City, Davao del Norte',
    ]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();
    $loanRequest->update([
        'approved_amount' => 25000,
        'approved_term' => 12,
        'approved_interest_rate' => 0.36,
        'recommended_payment_frequency' => '15th & 30th',
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
        ->get(route('admin.requests.documents.promissory-note', $loanRequest));

    $response->assertOk();

    $text = approvedLoanDocumentsExtractPdfText($response);
    $searchable = strtoupper(str_replace(' ', '', $text));

    expect($searchable)
        ->toContain('PROMISSORYNOTE')
        ->toContain('HELARIOB.TEJERO')
        ->toContain('COA.MAKERONE')
        ->toContain('COB.MAKERTWO')
        ->toContain('ANNABELLEM.AMORA')
        ->toContain('ACMECOOPERATIVE');
});

test('promissory note pdf prints the staff-entered penalty rate, not a hardcoded 5%', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'penalty_rate_per_month', 'numeric', 0.07);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.promissory-note', $loanRequest));

    $response->assertOk();

    $text = approvedLoanDocumentsExtractPdfText($response);

    expect($text)
        ->toContain('7.00%')
        ->not->toContain('5.00%')
        ->not->toContain('a penalty of 5%');
});

test('plan of payment route returns a pdf not xlsx', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.plan-of-payment', $loanRequest));

    $content = approvedLoanDocumentsReadDownloadedFileContent($response);

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'application/pdf');
    $response->assertDownload('plan-of-payment-'.$loanRequest->reference.'.pdf');
    expect($content)->toStartWith('%PDF')->not->toStartWith('PK');
});

test('plan of payment pdf includes borrower and amortization values', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
        'fullname' => 'Annabelle M. Amora',
    ]);

    OrganizationSetting::factory()->create([
        'company_name' => 'Acme Cooperative',
    ]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();
    $loanRequest->update([
        'approved_amount' => 25000,
        'approved_term' => 12,
        'approved_interest_rate' => 0.36,
        'recommended_payment_frequency' => 'SEMI-MONTHLY',
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
        ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.plan-of-payment', $loanRequest));

    $response->assertOk();

    $text = approvedLoanDocumentsExtractPdfText($response);
    $searchable = strtoupper(str_replace(' ', '', $text));

    expect($searchable)
        ->toContain('PLANOFPAYMENT')
        ->toContain('HELARIOB.TEJERO')
        ->toContain('ANNABELLEM.AMORA')
        ->toContain('ACMECOOPERATIVE');
});

test('loan information route returns a pdf not xlsx', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.loan-information', $loanRequest));

    $content = approvedLoanDocumentsReadDownloadedFileContent($response);

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'application/pdf');
    $response->assertDownload('loan-information-'.$loanRequest->reference.'.pdf');
    expect($content)->toStartWith('%PDF')->not->toStartWith('PK');
});

test('loan information pdf includes borrower financial and approval values', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
        'fullname' => 'Annabelle M. Amora',
    ]);

    OrganizationSetting::factory()->create([
        'company_name' => 'Acme Cooperative',
    ]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();
    $loanRequest->update([
        'approved_amount' => 25000,
        'approved_term' => 12,
        'approved_interest_rate' => 0.36,
        'recommended_payment_frequency' => 'SEMI-MONTHLY',
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
        ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.loan-information', $loanRequest));

    $response->assertOk();

    $text = approvedLoanDocumentsExtractPdfText($response);
    $searchable = strtoupper(str_replace(' ', '', $text));

    // Title text is baked into the base artwork PDF, not stamped by the field map (and, in
    // tests, into the synthetic bold-title placeholder template -- see
    // approvedLoanDocumentsCreateTemplatePdf()). It's not something this pipeline controls,
    // so it isn't asserted here; only field-map-stamped values are.
    //
    // The organization/company name is likewise not asserted: like
    // AffidavitUndertakingPdfFieldMap (the closest structural analog -- both artworks have a
    // header logo *image* box), LoanInformationPdfFieldMap only stamps the header image, with
    // no text fallback when no header design is configured. The old Blade pipeline printed
    // the company name as a text fallback in that case, but the FPDI pattern doesn't
    // replicate that anywhere in this codebase (see LOAN_INFORMATION_FPDI_CONVERSION_PLAN.md).
    // "Certified Correct" is deliberately hardcoded to match Disclosure Statement's
    // precedent (commit 0d9d2a3), not sourced from reviewer.name -- see
    // LOAN_INFORMATION_FPDI_CONVERSION_PLAN.md §3. Role is appended inline on the same
    // line ("Name, BOOKKEEPER"), not a stacked second line like DS -- see the field-map
    // comment on this entry.
    expect($searchable)
        ->toContain('HELARIOB.TEJERO')
        ->toContain('ANNABELLEM.AMORA')
        ->toContain('25,000.00')
        ->toContain('VELINAP.GAMUTAN')
        ->toContain('BOOKKEEPER');
});

test('disclosure statement route returns a pdf not xlsx', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.disclosure-statement', $loanRequest));

    $content = approvedLoanDocumentsReadDownloadedFileContent($response);

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'application/pdf');
    $response->assertDownload('disclosure-statement-'.$loanRequest->reference.'.pdf');
    expect($content)->toStartWith('%PDF')->not->toStartWith('PK');
});

test('disclosure statement pdf includes statutory labels and computed totals', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
        'fullname' => 'Annabelle M. Amora',
    ]);

    OrganizationSetting::factory()->create([
        'company_name' => 'Acme Cooperative',
    ]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();
    $loanRequest->update([
        'approved_amount' => 25000,
        'approved_term' => 12,
        'approved_interest_rate' => 0.36,
        'recommended_payment_frequency' => 'SEMI-MONTHLY',
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
        ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.disclosure-statement', $loanRequest));

    $response->assertOk();

    $text = approvedLoanDocumentsExtractPdfText($response);
    $searchable = strtoupper(str_replace(' ', '', $text));

    expect($searchable)
        ->toContain('DISCLOSURESTATEMENT')
        ->toContain('TRUTHINLENDING')
        ->toContain('TOTALFINANCECHARGES')
        ->toContain('NETPROCEEDSOFLOAN')
        ->toContain('EFFECTIVEINTERESTRATE')
        ->toContain('HELARIOB.TEJERO')
        ->toContain('25,000.00')
        ->toContain('VELINAP.GAMUTAN')
        ->toContain('BOOKKEEPER');
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
    $pdfText = approvedLoanDocumentsExtractPdfText($response);

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'application/pdf');
    expect($content)
        ->toStartWith('%PDF');
    expect($pdfText)
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

test('grepalife signature section keeps printed names and blank signature areas on main', function () {
    Storage::fake('public');

    $admin = User::factory()->create([
        'acctno' => null,
    ]);
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
        'fullname' => 'Maria Loan Processor',
    ]);

    OrganizationSetting::factory()->create([
        'company_name' => 'Wibs Cooperative',
        'business_address1' => '123 Main Street',
        'business_address2' => 'Tagum City',
        'business_address3' => 'Davao del Norte',
        'business_address' => '123 Main Street, Tagum City, Davao del Norte',
        'support_contact_name' => 'Support Contact Name',
    ]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('role', LoanRequestPersonRole::Applicant)
        ->firstOrFail()
        ->update([
            'first_name' => 'Juan',
            'middle_name' => 'Paulo',
            'last_name' => 'Cruz',
        ]);

    $loanRequest->update([
        'reviewed_by' => $admin->user_id,
        'reviewed_at' => '2026-05-22 10:00:00',
    ]);

    $documentData = approvedLoanDocumentsBuildDocumentData($loanRequest->fresh());
    $fieldMap = new GrepalifePdfFieldMap;

    expect(data_get($documentData, 'applicant.signature_path'))->toBeNull();
    expect($loanRequest->fresh()->reviewed_by)->toBe($admin->user_id);
    expect(data_get($documentData, 'reviewer.signature_path'))->toBeNull();
    expect(data_get($documentData, 'reviewer.name'))->toBe('Annabelle M. Amora');
    expect(data_get($documentData, 'reviewer.position'))->toBe('Loan Manager');
    expect(data_get($documentData, 'reviewer.witness_one_name'))->toBe('Annabelle M. Amora');
    expect(data_get($documentData, 'reviewer.witness_two_name'))->toBe('Annabelle M. Amora');
    expect(collect($fieldMap->fields())->contains(
        fn (array $field): bool => ($field['type'] ?? null) === 'signature',
    ))->toBeFalse();
    expect(approvedLoanDocumentsResolveImageTemplateFieldValue(
        approvedLoanDocumentsFindGrepalifeField(
            $fieldMap,
            2,
            'applicant.full_name',
        ),
        $documentData,
    ))->toBe('JUAN P. CRUZ');
    expect(approvedLoanDocumentsResolveImageTemplateFieldValue(
        approvedLoanDocumentsFindGrepalifeField(
            $fieldMap,
            2,
            'reviewer.name',
        ),
        $documentData,
    ))->toBe('ANNABELLE M. AMORA');
    expect(approvedLoanDocumentsResolveImageTemplateFieldValue(
        approvedLoanDocumentsFindGrepalifeField(
            $fieldMap,
            2,
            'organization.company_name',
        ),
        $documentData,
    ))->toBe('WIBS COOPERATIVE');
    expect(approvedLoanDocumentsResolveImageTemplateFieldValue(
        approvedLoanDocumentsFindGrepalifeField(
            $fieldMap,
            2,
            'organization.business_address',
        ),
        $documentData,
    ))->toBe('123 MAIN STREET, TAGUM CITY, DAVAO DEL NORTE');
    expect(approvedLoanDocumentsResolveImageTemplateFieldValue(
        approvedLoanDocumentsFindGrepalifeField(
            $fieldMap,
            2,
            'loan.approved_date_short',
        ),
        $documentData,
    ))->toBe('05/22/2026');

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.grepalife', $loanRequest));

    $response->assertOk();
    $pdfText = approvedLoanDocumentsExtractPdfText($response);

    expect($pdfText)
        ->toContain('JUAN P. CRUZ')
        ->toContain('ANNABELLE M. AMORA')
        ->not->toContain('ANNABELLE MONGADO AMORA')
        ->not->toContain('N/A')
        ->not->toContain('MARIA LOAN OFFICER')
        ->toContain('WIBS COOPERATIVE')
        ->toContain('123 MAIN STREET, TAGUM CITY, DAVAO DEL NORTE')
        ->toContain('05/22/2026')
        ->not->toContain('Input Data')
        ->not->toContain('No Input Data');
});

test('grepalife signature section keeps the official loan manager name when approval metadata is unavailable', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $admin->user_id,
    ]);

    OrganizationSetting::factory()->create([
        'company_name' => 'Wibs Cooperative',
        'business_address' => null,
        'business_address1' => null,
        'business_address2' => null,
        'business_address3' => null,
    ]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    LoanRequestPerson::query()
        ->where('loan_request_id', $loanRequest->id)
        ->where('role', LoanRequestPersonRole::Applicant)
        ->firstOrFail()
        ->update([
            'first_name' => 'Helario',
            'middle_name' => 'Bonifacio',
            'last_name' => 'Tejero',
        ]);

    $loanRequest->update([
        'reviewed_by' => null,
        'reviewed_at' => null,
    ]);

    $documentData = approvedLoanDocumentsBuildDocumentData($loanRequest->fresh());
    $fieldMap = new GrepalifePdfFieldMap;

    expect(data_get($documentData, 'applicant.signature_path'))->toBeNull();
    expect(data_get($documentData, 'reviewer.signature_path'))->toBeNull();
    expect(approvedLoanDocumentsResolveImageTemplateFieldValue(
        approvedLoanDocumentsFindGrepalifeField(
            $fieldMap,
            2,
            'applicant.full_name',
        ),
        $documentData,
    ))->toBe('HELARIO B. TEJERO');
    expect(approvedLoanDocumentsResolveImageTemplateFieldValue(
        approvedLoanDocumentsFindGrepalifeField(
            $fieldMap,
            2,
            'reviewer.name',
        ),
        $documentData,
    ))->toBe('ANNABELLE M. AMORA');
    expect(approvedLoanDocumentsResolveImageTemplateFieldValue(
        approvedLoanDocumentsFindGrepalifeField(
            $fieldMap,
            2,
            'organization.business_address',
        ),
        $documentData,
    ))->toBe('');
    expect(approvedLoanDocumentsResolveImageTemplateFieldValue(
        approvedLoanDocumentsFindGrepalifeField(
            $fieldMap,
            2,
            'loan.approved_date_short',
        ),
        $documentData,
    ))->toBeNull();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.grepalife', $loanRequest));

    $response->assertOk();
    $pdfText = approvedLoanDocumentsExtractPdfText($response);

    expect($pdfText)
        ->toContain('HELARIO B. TEJERO')
        ->toContain('ANNABELLE M. AMORA')
        ->not->toContain('ANNABELLE MONGADO AMORA')
        ->not->toContain('N/A')
        ->toContain('WIBS COOPERATIVE')
        ->not->toContain('Input Data')
        ->not->toContain('No Input Data');
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
            && ($field['y'] ?? null) === 119.8,
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

    expect((float) $lastNameField['x'])->toBe(11.8);
    expect((float) $firstNameField['x'])->toBe(11.8);
    expect((float) $middleNameField['x'])->toBe(11.8);
    expect((float) $nationalityField['x'])->toBe(11.8);
    expect((float) $birthdateField['x'])->toBe(100.0);
    expect($birthdateField['align'] ?? 'L')->toBe('C');
    expect((float) $natureOfBusinessField['y'])->toBe(94.0);
    expect((float) $yearsInWorkField['x'])->toBe(155.5);
    expect((float) $yearsInWorkField['y'])->toBe(94.0);
    expect((float) $workPhoneField['y'])->toBe(111.5);
    expect((float) $mobileField['y'])->toBe(111.5);
    expect((float) $emailField['y'])->toBe(111.5);
    expect((float) $termField['y'])->toBe(119.8);
    expect($termField['align'] ?? 'L')->toBe('C');
    expect((float) $amountField['y'])->toBe(119.8);
    expect($amountField['align'] ?? 'L')->toBe('C');
    expect((float) $existingLoanYesField['x'])->toBe(68.5);
    expect($existingLoanDateField['align'] ?? 'L')->toBe('C');
    expect((float) $beneficiaryNameField['x'])->toBe(15.0);
    expect($beneficiaryBirthdateField['align'] ?? 'L')->toBe('C');
    expect((float) $pageTwoCompanyField['x'])->toBe(141.5);
    expect((float) $pageTwoCompanyField['y'])->toBe(91.5);
    expect($pageTwoCompanyField['align'] ?? 'L')->toBe('L');
    expect((float) $pageTwoDateField['x'])->toBe(108.8);
    expect($pageTwoDateField['align'] ?? 'L')->toBe('L');
});

test('affidavit undertaking field map pins all field coordinates to calibrated values', function () {
    $fields = collect((new AffidavitUndertakingPdfFieldMap)->fields());

    $find = fn (string $value): array => $fields->first(
        fn (array $f): bool => ($f['value'] ?? null) === $value,
    );

    // Fields dropped in the Phase 1/2 rebuild: the real AU reference document has no
    // loan amount, loan type, or reviewer line, and no separate "account name" blank.
    // Doc/Page/Book No. are the notary's own register counters — unknowable to WIBS
    // staff, left blank for the notary to fill by hand rather than stamped from app
    // data. notarial.valid_id_number / valid_id_issued_at (dropped in the visual
    // fidelity pass) have no reference-document equivalent either.
    foreach ([
        'loan.approved_amount', 'loan.type', 'reviewer.name', 'authorization.payout_account_name',
        'notarial.doc_number', 'notarial.page_number', 'notarial.book_number',
        'notarial.valid_id_number', 'notarial.valid_id_issued_at', 'notarial.province',
    ] as $droppedValue) {
        expect($fields->contains(fn (array $f): bool => ($f['value'] ?? null) === $droppedValue))->toBeFalse();
    }

    $header = $fields->first(fn (array $f): bool => ($f['type'] ?? null) === 'image');
    expect($header)->toBeArray();
    expect($header['value'])->toBe('organization.report_header.designPath');
    expect((float) $header['x'])->toBe(18.0);
    expect((float) $header['y'])->toBe(10.0);
    expect((float) $header['width'])->toBe(174.0);
    expect((float) $header['height'])->toBe(18.0);
    // Overrides DocumentSignaturePlacement's default 2x SIGNATURE_SCALE_FACTOR (tuned for
    // small hand-drawn signature stamps) -- the header must fit its declared box as-is.
    expect((float) $header['scale'])->toBe(1.0);

    // Table-borders plan, corrected against the real reference document (ce3e961/1682d97):
    // "Name of Affiant:" sits above the table (unbordered); the table itself is 5 rows,
    // with Age/Marital Status/Nationality sharing one unified cell (no internal column
    // borders) -- confirmed by rendering the actual reference .docx and pixel-scanning it,
    // not inferred from the plan document alone.
    $fullName = $find('applicant.full_name');
    expect((float) $fullName['x'])->toBe(46.5);
    expect((float) $fullName['y'])->toBe(41.5);
    expect((int) $fullName['size'])->toBe(10);

    $age = $find('applicant.age');
    expect((float) $age['x'])->toBe(26.5);
    expect((float) $age['y'])->toBe(49.1);
    expect((int) $age['size'])->toBe(9);

    $civilStatus = $find('applicant.civil_status');
    expect((float) $civilStatus['x'])->toBe(83.5);
    expect((float) $civilStatus['y'])->toBe(49.1);

    $nationality = $find('applicant.nationality');
    expect((float) $nationality['x'])->toBe(141.5);
    expect((float) $nationality['y'])->toBe(49.1);

    $address = $find('applicant.address');
    expect((float) $address['x'])->toBe(78.0);
    expect((float) $address['y'])->toBe(54.5);
    expect((int) $address['size'])->toBe(8);
    expect((float) $address['width'])->toBe(113.0);
    expect((float) $address['line_height'])->toBe(3.0);

    $designation = $find('applicant.position_or_designation');
    expect((float) $designation['x'])->toBe(52.0);
    expect((float) $designation['y'])->toBe(63.1);

    $agency = $find('applicant.employer_or_business');
    expect((float) $agency['x'])->toBe(32.5);
    expect((float) $agency['y'])->toBe(68.1);

    $officeAddress = $find('applicant.office_address');
    expect((float) $officeAddress['x'])->toBe(62.0);
    expect((float) $officeAddress['y'])->toBe(73.5);
    expect((float) $officeAddress['width'])->toBe(129.0);
    expect((float) $officeAddress['line_height'])->toBe(3.0);

    // Baseline correction pass: TCPDF's Text() y is not the drawn underline's y --
    // each field shifts up by its own measured baseline offset (2.11/2.10/1.89/1.89/1.90mm)
    // so the printed value rests on its line instead of floating below/through it,
    // confirmed by rendering against the real production artwork and rasterizing.
    // Inline blank inside paragraph 1's sentence, between "...Pay of " and
    // "(Guaranteed NTHP)" -- shrinks to fit rather than overflowing into the parenthetical
    // when the approved amount is large.
    $gnthp = $find('loan.gnthp');
    expect((float) $gnthp['x'])->toBe(59.0);
    expect((float) $gnthp['y'])->toBe(120.5);
    expect((int) $gnthp['size'])->toBe(11);
    expect((float) $gnthp['width'])->toBe(20.0);
    expect($gnthp['shrink_to_fit'] ?? false)->toBeTrue();
    expect((float) $gnthp['min_size'])->toBe(6.0);

    $accountNumber = $find('authorization.payout_account_number');
    expect((float) $accountNumber['x'])->toBe(82.51);
    expect((float) $accountNumber['y'])->toBe(129.90);

    $atmNumber = $find('authorization.payout_atm_number');
    expect((float) $atmNumber['x'])->toBe(59.14);
    expect((float) $atmNumber['y'])->toBe(138.11);

    $bankName = $find('authorization.payout_bank_name');
    expect((float) $bankName['x'])->toBe(50.91);
    expect((float) $bankName['y'])->toBe(146.11);

    $bankBranch = $find('authorization.payout_bank_branch');
    expect((float) $bankBranch['x'])->toBe(43.66);
    expect((float) $bankBranch['y'])->toBe(154.10);
    expect((int) $bankBranch['size'])->toBe(9);

    // Signature/Date/Place-of-Signing row: three equal ~50mm columns (x=33/83/133, y=250)
    // over the artwork's freeform underlines -- coordinates below are remeasured fresh
    // against the real artwork's content stream, confirmed by rendering and rasterizing.
    $date = $find('loan.approved_date');
    expect((float) $date['x'])->toBe(83.0);
    expect((float) $date['y'])->toBe(250.0);
    expect((int) $date['size'])->toBe(11);
    expect((float) $date['width'])->toBe(50.0);

    // A width so signing_place can carry the full composed org address, not just a short
    // city name -- shrinks to fit this column on one line instead of wrapping (which risked
    // colliding with the "Place of Signing" caption directly beneath it).
    $signingPlace = $find('notarial.signing_place');
    expect((float) $signingPlace['x'])->toBe(133.0);
    expect((float) $signingPlace['y'])->toBe(250.0);
    expect((int) $signingPlace['size'])->toBe(11);
    expect((float) $signingPlace['width'])->toBe(50.0);
    expect($signingPlace['shrink_to_fit'] ?? false)->toBeTrue();
    expect((float) $signingPlace['min_size'])->toBe(6.0);

    // The signature-row applicant.full_name (distinct from the "Name of Affiant:" one
    // above) also shrinks to fit -- a long name shouldn't overflow into the Date column.
    $signatureRowFullName = $fields
        ->filter(fn (array $f): bool => ($f['value'] ?? null) === 'applicant.full_name')
        ->values()
        ->get(1);
    expect($signatureRowFullName)->toBeArray();
    expect((float) $signatureRowFullName['x'])->toBe(33.0);
    expect((float) $signatureRowFullName['y'])->toBe(250.0);
    expect((float) $signatureRowFullName['width'])->toBe(50.0);
    expect($signatureRowFullName['shrink_to_fit'] ?? false)->toBeTrue();
    expect((float) $signatureRowFullName['min_size'])->toBe(7.0);

    $seriesYear = $find('notarial.series_year');
    expect((float) $seriesYear['x'])->toBe(34.9);
    expect((float) $seriesYear['y'])->toBe(321.56);
    expect((int) $seriesYear['size'])->toBe(10);
});

test('authorization field map pins all field coordinates to calibrated values', function () {
    $fields = collect((new AuthorizationPdfFieldMap)->fields());

    $find = fn (string $value): array => $fields->first(
        fn (array $f): bool => ($f['value'] ?? null) === $value,
    );
    $findOrNull = fn (string $value): ?array => $fields->first(
        fn (array $f): bool => ($f['value'] ?? null) === $value,
    );

    // Visual-fidelity rebuild against the real reference document: the reference is a
    // single Letter-size (8.5x11in) paragraph letter with no identification table at all
    // (no separate full_name/address/loan.reference/approved_date/company_name lines) --
    // those five fields are gone, not repositioned.
    foreach ([
        'applicant.address', 'loan.reference', 'loan.approved_amount',
        'loan.loan_security_amount', 'loan.approved_date', 'organization.company_name',
        'authorization.release_method', 'authorization.payout_bank_name',
    ] as $droppedValue) {
        expect($findOrNull($droppedValue))->toBeNull();
    }

    $header = $fields->first(fn (array $f): bool => ($f['type'] ?? null) === 'image');
    expect($header)->not->toBeNull();
    expect($header['value'])->toBe('organization.report_header.designPath');
    // Header box is as generous as AU/LI's, so it takes their scale convention rather
    // than UB's 1.5 (tuned for UB's much tighter 14mm box).
    expect((float) $header['scale'])->toBe(1.0);

    // Inline blanks in paragraph 1 -- "Php." is baked as static artwork text, so the
    // stamped value must be the bare formatted number, not the ₱-prefixed string AU/UB
    // use for their own inline blanks.
    $amount = $find('loan.loan_security_amount_plain');
    expect((float) $amount['x'])->toBe(83.18);
    expect((float) $amount['y'])->toBe(80.5);
    // No explicit font -- falls through to ApprovedLoanPdfTemplateService::DEFAULT_FONT
    // (calibri), matching AU/UB/LI's convention.
    expect($amount['font'] ?? null)->toBeNull();
    expect($amount['shrink_to_fit'] ?? false)->toBeTrue();

    $accountHolder = $find('authorization.payout_atm_holder_name');
    // payout_atm_holder_name is wired twice: once as the inline "to the account of ___"
    // blank, once on the ATM Card Holder signature line below -- confirm both exist and
    // are distinct positions, not the same entry appearing once.
    $accountHolderEntries = $fields->filter(
        fn (array $f): bool => ($f['value'] ?? null) === 'authorization.payout_atm_holder_name',
    );
    expect($accountHolderEntries)->toHaveCount(2);
    expect($accountHolderEntries->pluck('y')->unique())->toHaveCount(2);
    expect((float) $accountHolder['x'])->toBe(25.4);
    expect((float) $accountHolder['y'])->toBe(87.0);

    $accountNumber = $find('authorization.payout_account_number');
    expect((float) $accountNumber['x'])->toBe(121.32);
    expect((float) $accountNumber['y'])->toBe(87.0);

    $bankBranch = $find('authorization.payout_bank_branch');
    expect((float) $bankBranch['x'])->toBe(58.75);
    expect((float) $bankBranch['y'])->toBe(93.5);

    // Signature block -- Borrower (applicant.full_name) and ATM Card Holder
    // (authorization.payout_atm_holder_name), distinct centered positions.
    $signatures = $fields->filter(
        fn (array $f): bool => ($f['align'] ?? null) === 'C'
            && in_array($f['value'] ?? null, ['applicant.full_name', 'authorization.payout_atm_holder_name'], true),
    );
    expect($signatures)->toHaveCount(2);
    expect($signatures->pluck('y')->unique())->toHaveCount(2);

    $borrowerSignature = $fields->first(
        fn (array $f): bool => ($f['value'] ?? null) === 'applicant.full_name',
    );
    expect((float) $borrowerSignature['x'])->toBe(45.4);
    expect((float) $borrowerSignature['y'])->toBe(127.0);
});

test('undertaking barangay field map pins all field coordinates to calibrated values', function () {
    $fields = collect((new UndertakingBarangayPdfFieldMap)->fields());

    $find = fn (string $value): array => $fields->first(
        fn (array $f): bool => ($f['value'] ?? null) === $value,
    );
    $findOrNull = fn (string $value): ?array => $fields->first(
        fn (array $f): bool => ($f['value'] ?? null) === $value,
    );

    $fullName = $find('applicant.full_name');
    $address = $find('applicant.address');
    $type = $find('loan.type');
    $amount = $find('loan.approved_amount');
    $date = $find('loan.approved_date');
    $company = $find('organization.company_name');

    expect((float) $fullName['x'])->toBe(27.0);
    expect((float) $fullName['y'])->toBe(42.0);
    expect((int) $fullName['size'])->toBe(10);

    expect((float) $address['x'])->toBe(27.0);
    expect((float) $address['y'])->toBe(50.0);
    expect((int) $address['size'])->toBe(8);
    expect((float) $address['width'])->toBe(160.0);

    expect((float) $type['x'])->toBe(27.0);
    expect((float) $type['y'])->toBe(62.0);
    expect((int) $type['size'])->toBe(9);

    // Collision fix: loan.approved_amount keeps the original (107,62) position;
    // loan.gnthp (below) is the field that moved.
    expect((float) $amount['x'])->toBe(107.0);
    expect((float) $amount['y'])->toBe(62.0);
    expect((int) $amount['size'])->toBe(9);

    expect((float) $date['x'])->toBe(27.0);
    expect((float) $date['y'])->toBe(72.0);
    expect((int) $date['size'])->toBe(9);

    expect((float) $company['x'])->toBe(104.0);
    expect((float) $company['y'])->toBe(72.0);
    expect((int) $company['size'])->toBe(9);

    // Age/Civil Status/Nationality -- new fields, occupying the space vacated by the
    // three removed barangay.name/clearance_reference/locality entries.
    $age = $find('applicant.age');
    expect((float) $age['x'])->toBe(27.0);
    expect((float) $age['y'])->toBe(86.0);
    expect((int) $age['size'])->toBe(9);

    $civilStatus = $find('applicant.civil_status');
    expect((float) $civilStatus['x'])->toBe(88.0);
    expect((float) $civilStatus['y'])->toBe(86.0);

    $nationality = $find('applicant.nationality');
    expect((float) $nationality['x'])->toBe(148.0);
    expect((float) $nationality['y'])->toBe(86.0);

    // Designation/Agency/Agency Address now source from the applicant's own employment
    // record (bug fix) -- position unchanged from the original barangay.* entries.
    $designation = $find('applicant.position_or_designation');
    expect((float) $designation['x'])->toBe(27.0);
    expect((float) $designation['y'])->toBe(106.0);
    expect((int) $designation['size'])->toBe(9);

    $agencyName = $find('applicant.employer_or_business');
    expect((float) $agencyName['x'])->toBe(27.0);
    expect((float) $agencyName['y'])->toBe(114.0);
    expect((int) $agencyName['size'])->toBe(9);

    $agencyAddress = $find('applicant.office_address');
    expect((float) $agencyAddress['x'])->toBe(27.0);
    expect((float) $agencyAddress['y'])->toBe(122.0);
    expect((int) $agencyAddress['size'])->toBe(8);
    expect((float) $agencyAddress['width'])->toBe(160.0);

    // Dead fields confirmed removed.
    expect($findOrNull('barangay.name'))->toBeNull();
    expect($findOrNull('barangay.clearance_reference'))->toBeNull();
    expect($findOrNull('barangay.locality'))->toBeNull();
    expect($findOrNull('barangay.official_designation'))->toBeNull();
    expect($findOrNull('barangay.agency_name'))->toBeNull();
    expect($findOrNull('barangay.agency_address'))->toBeNull();

    // GNTHP moved off the collision -- now inline in paragraph 1, a new position distinct
    // from loan.approved_amount's (107,62).
    $gnthp = $find('loan.gnthp');
    expect((float) $gnthp['x'])->not->toBe(107.0);
    expect((float) $gnthp['y'])->not->toBe(62.0);
    expect((bool) $gnthp['shrink_to_fit'])->toBeTrue();

    // Signature/notarial block -- new fields, new positions.
    $signatureName = $fields->first(
        fn (array $f): bool => ($f['value'] ?? null) === 'applicant.full_name'
            && ($f['align'] ?? null) === 'C',
    );
    expect($signatureName)->not->toBeNull();

    $signingPlace = $find('notarial.signing_place');
    expect($signingPlace)->not->toBeNull();

    $seriesYear = $find('notarial.series_year');
    expect($seriesYear)->not->toBeNull();

    $header = $fields->first(
        fn (array $f): bool => ($f['type'] ?? null) === 'image',
    );
    expect($header)->not->toBeNull();
    expect($header['value'])->toBe('organization.report_header.designPath');
});

test('grepalife field map pins all field coordinates to calibrated values', function () {
    $fields = collect((new GrepalifePdfFieldMap)->fields());

    $findByPageValue = fn (int $page, string $value, ?float $y = null): array => $fields->first(
        fn (array $f): bool => ($f['page'] ?? null) === $page
            && ($f['value'] ?? null) === $value
            && ($y === null || (float) ($f['y'] ?? -1) === $y),
    );

    $findCallableByPageY = fn (int $page, float $y, float $width): array => $fields->first(
        fn (array $f): bool => ($f['page'] ?? null) === $page
            && is_callable($f['value'] ?? null)
            && ($f['type'] ?? 'text') === 'text'
            && (float) ($f['y'] ?? -1) === $y
            && (float) ($f['width'] ?? -1) === $width,
    );

    $findCheckByPageXY = fn (int $page, float $x, float $y): array => $fields->first(
        fn (array $f): bool => ($f['page'] ?? null) === $page
            && ($f['type'] ?? 'text') === 'check'
            && (float) ($f['x'] ?? -1) === $x
            && (float) ($f['y'] ?? -1) === $y,
    );

    // --- Page 1 string-valued text fields ---
    $lastName = $findByPageValue(1, 'applicant.last_name');
    $firstName = $findByPageValue(1, 'applicant.first_name');
    $middleName = $findByPageValue(1, 'applicant.middle_name');
    $nationality = $findByPageValue(1, 'applicant.nationality');
    $placeOfBirth = $findByPageValue(1, 'applicant.place_of_birth');
    $addressLine = $findByPageValue(1, 'applicant.address_line');
    $addressCity = $findByPageValue(1, 'applicant.address_city');
    $addressProvince = $findByPageValue(1, 'applicant.address_province');
    $addressZip = $findByPageValue(1, 'applicant.address_zip');
    $employer = $findByPageValue(1, 'applicant.employer_or_business');
    $natureOfBusiness = $findByPageValue(1, 'applicant.nature_of_business');
    $position = $findByPageValue(1, 'applicant.position_or_designation');
    $yearsInWork = $findByPageValue(1, 'applicant.years_in_work_business');
    $officeAddress = $findByPageValue(1, 'applicant.office_address_line');
    $officeCity = $findByPageValue(1, 'applicant.office_city');
    $officeProvince = $findByPageValue(1, 'applicant.office_province');
    $officeZip = $findByPageValue(1, 'applicant.office_zip');
    $homePhone = $findByPageValue(1, 'applicant.home_phone');
    $workPhone = $findByPageValue(1, 'applicant.work_phone');
    $mobile = $findByPageValue(1, 'applicant.mobile');
    $email = $findByPageValue(1, 'applicant.email');
    $companyNameP1 = $findByPageValue(1, 'organization.company_name');
    $termLabel = $findByPageValue(1, 'loan.approved_term_label');
    $amountP1Y119 = $findByPageValue(1, 'loan.approved_amount', 119.8);
    $amountP1Y134 = $findByPageValue(1, 'loan.approved_amount', 134.1);
    $existingLoanDate = $findByPageValue(1, 'loan.approved_date_short');
    $existingLoanType = $findByPageValue(1, 'loan.type');
    $ben0Name = $findByPageValue(1, 'beneficiaries.0.name');
    $ben0Bday = $findByPageValue(1, 'beneficiaries.0.birthdate');
    $ben0Rel = $findByPageValue(1, 'beneficiaries.0.relationship');
    $ben1Name = $findByPageValue(1, 'beneficiaries.1.name');
    $ben1Bday = $findByPageValue(1, 'beneficiaries.1.birthdate');
    $ben1Rel = $findByPageValue(1, 'beneficiaries.1.relationship');
    $ben2Name = $findByPageValue(1, 'beneficiaries.2.name');
    $ben2Bday = $findByPageValue(1, 'beneficiaries.2.birthdate');
    $ben2Rel = $findByPageValue(1, 'beneficiaries.2.relationship');

    // --- Page 1 callable fields ---
    $birthdate = $findCallableByPageY(1, 71.1, 74.0);
    $addressCountry = $findCallableByPageY(1, 86.5, 17.0);
    $officeCountry = $findCallableByPageY(1, 102.5, 17.0);

    // --- Page 1 check fields ---
    $checkSingle = $findCheckByPageXY(1, 130.8, 59.9);
    $checkMarried = $findCheckByPageXY(1, 150.5, 59.9);
    $checkWidowed = $findCheckByPageXY(1, 169.0, 59.9);
    $checkSeparated = $findCheckByPageXY(1, 130.8, 63.8);
    $checkExistingLoanYes = $findCheckByPageXY(1, 68.5, 125.0);

    // --- Page 2 fields ---
    $fullNameP2 = $findByPageValue(2, 'applicant.full_name');
    $reviewerP2 = $findByPageValue(2, 'reviewer.name');
    $companyNameP2 = $findByPageValue(2, 'organization.company_name');
    $businessAddress = $findByPageValue(2, 'organization.business_address');
    $dateP2 = $findByPageValue(2, 'loan.approved_date_short');

    // --- Assertions: page 1 text string fields ---
    expect((float) $lastName['x'])->toBe(11.8);
    expect((float) $lastName['y'])->toBe(55.1);
    expect((int) $lastName['size'])->toBe(7);
    expect((float) $lastName['width'])->toBe(96.0);

    expect((float) $firstName['x'])->toBe(11.8);
    expect((float) $firstName['y'])->toBe(62.9);
    expect((int) $firstName['size'])->toBe(7);
    expect((float) $firstName['width'])->toBe(96.0);

    expect((float) $middleName['x'])->toBe(11.8);
    expect((float) $middleName['y'])->toBe(70.6);
    expect((int) $middleName['size'])->toBe(7);
    expect((float) $middleName['width'])->toBe(96.0);

    expect((float) $nationality['x'])->toBe(11.8);
    expect((float) $nationality['y'])->toBe(78.5);
    expect((float) $nationality['width'])->toBe(50.0);

    expect((float) $placeOfBirth['x'])->toBe(110.2);
    expect((float) $placeOfBirth['y'])->toBe(78.5);
    expect((float) $placeOfBirth['width'])->toBe(86.0);

    expect((float) $addressLine['x'])->toBe(11.8);
    expect((float) $addressLine['y'])->toBe(86.5);
    expect((float) $addressLine['width'])->toBe(97.0);

    expect((float) $addressCity['x'])->toBe(118.0);
    expect((float) $addressCity['y'])->toBe(86.5);
    expect((float) $addressCity['width'])->toBe(24.0);

    expect((float) $addressProvince['x'])->toBe(143.3);
    expect((float) $addressProvince['y'])->toBe(86.5);
    expect((float) $addressProvince['width'])->toBe(25.0);

    expect((float) $addressZip['x'])->toBe(191.7);
    expect((float) $addressZip['y'])->toBe(86.5);
    expect((float) $addressZip['width'])->toBe(11.0);

    expect((float) $employer['x'])->toBe(11.8);
    expect((float) $employer['y'])->toBe(94.0);
    expect((float) $employer['width'])->toBe(45.0);

    expect((float) $natureOfBusiness['x'])->toBe(61.0);
    expect((float) $natureOfBusiness['y'])->toBe(94.0);
    expect((float) $natureOfBusiness['width'])->toBe(56.0);

    expect((float) $position['x'])->toBe(118.0);
    expect((float) $position['y'])->toBe(94.0);
    expect((float) $position['width'])->toBe(32.0);

    expect((float) $yearsInWork['x'])->toBe(155.5);
    expect((float) $yearsInWork['y'])->toBe(94.0);
    expect((float) $yearsInWork['width'])->toBe(50.0);

    expect((float) $officeAddress['x'])->toBe(11.8);
    expect((float) $officeAddress['y'])->toBe(102.5);
    expect((float) $officeAddress['width'])->toBe(97.0);

    expect((float) $officeCity['x'])->toBe(118.0);
    expect((float) $officeCity['y'])->toBe(102.5);
    expect((float) $officeCity['width'])->toBe(24.0);

    expect((float) $officeProvince['x'])->toBe(146.3);
    expect((float) $officeProvince['y'])->toBe(102.5);
    expect((float) $officeProvince['width'])->toBe(23.0);

    expect((float) $officeZip['x'])->toBe(191.7);
    expect((float) $officeZip['y'])->toBe(102.5);
    expect((float) $officeZip['width'])->toBe(11.0);

    expect((float) $homePhone['x'])->toBe(11.8);
    expect((float) $homePhone['y'])->toBe(111.5);
    expect((float) $homePhone['width'])->toBe(44.0);

    expect((float) $workPhone['x'])->toBe(60.6);
    expect((float) $workPhone['y'])->toBe(111.5);
    expect((float) $workPhone['width'])->toBe(48.0);

    expect((float) $mobile['x'])->toBe(110.5);
    expect((float) $mobile['y'])->toBe(111.5);
    expect((float) $mobile['width'])->toBe(41.0);

    expect((float) $email['x'])->toBe(159.6);
    expect((float) $email['y'])->toBe(111.5);
    expect((float) $email['width'])->toBe(42.0);

    expect((float) $companyNameP1['x'])->toBe(11.8);
    expect((float) $companyNameP1['y'])->toBe(119.8);
    expect((float) $companyNameP1['width'])->toBe(94.0);

    expect((float) $termLabel['x'])->toBe(97.5);
    expect((float) $termLabel['y'])->toBe(119.8);
    expect((float) $termLabel['width'])->toBe(40.0);
    expect($termLabel['align'] ?? 'L')->toBe('C');

    expect((float) $amountP1Y119['x'])->toBe(145.6);
    expect((float) $amountP1Y119['y'])->toBe(119.8);
    expect((float) $amountP1Y119['width'])->toBe(42.0);
    expect($amountP1Y119['align'] ?? 'L')->toBe('C');

    expect((float) $existingLoanDate['x'])->toBe(71.5);
    expect((float) $existingLoanDate['y'])->toBe(134.1);
    expect((float) $existingLoanDate['width'])->toBe(40.0);
    expect($existingLoanDate['align'] ?? 'L')->toBe('C');

    expect((float) $existingLoanType['x'])->toBe(118.5);
    expect((float) $existingLoanType['y'])->toBe(134.1);
    expect((float) $existingLoanType['width'])->toBe(36.0);
    expect($existingLoanType['align'] ?? 'L')->toBe('C');

    expect((float) $amountP1Y134['x'])->toBe(170.2);
    expect((float) $amountP1Y134['y'])->toBe(134.1);
    expect((float) $amountP1Y134['width'])->toBe(24.0);
    expect($amountP1Y134['align'] ?? 'L')->toBe('C');

    expect((float) $ben0Name['x'])->toBe(15.0);
    expect((float) $ben0Name['y'])->toBe(149.2);
    expect((float) $ben0Bday['x'])->toBe(111.0);
    expect((float) $ben0Bday['y'])->toBe(149.2);
    expect($ben0Bday['align'] ?? 'L')->toBe('C');
    expect((float) $ben0Rel['x'])->toBe(150.0);
    expect((float) $ben0Rel['y'])->toBe(149.2);

    expect((float) $ben1Name['x'])->toBe(15.0);
    expect((float) $ben1Name['y'])->toBe(152.8);
    expect((float) $ben1Bday['x'])->toBe(111.0);
    expect((float) $ben1Bday['y'])->toBe(152.8);
    expect((float) $ben1Rel['x'])->toBe(150.0);
    expect((float) $ben1Rel['y'])->toBe(152.8);

    expect((float) $ben2Name['x'])->toBe(15.0);
    expect((float) $ben2Name['y'])->toBe(156.4);
    expect((float) $ben2Bday['x'])->toBe(111.0);
    expect((float) $ben2Bday['y'])->toBe(156.4);
    expect((float) $ben2Rel['x'])->toBe(150.0);
    expect((float) $ben2Rel['y'])->toBe(156.4);

    // --- Assertions: page 1 callable fields ---
    expect((float) $birthdate['x'])->toBe(100.0);
    expect((float) $birthdate['y'])->toBe(71.1);
    expect((int) $birthdate['size'])->toBe(7);
    expect((float) $birthdate['width'])->toBe(74.0);
    expect($birthdate['align'] ?? 'L')->toBe('C');

    expect((float) $addressCountry['x'])->toBe(171.7);
    expect((float) $addressCountry['y'])->toBe(86.5);
    expect((float) $addressCountry['width'])->toBe(17.0);

    expect((float) $officeCountry['x'])->toBe(171.7);
    expect((float) $officeCountry['y'])->toBe(102.5);
    expect((float) $officeCountry['width'])->toBe(17.0);

    // --- Assertions: page 1 check fields ---
    expect((float) $checkSingle['x'])->toBe(130.8);
    expect((float) $checkSingle['y'])->toBe(59.9);
    expect((float) $checkSingle['size'])->toBe(6.4);

    expect((float) $checkMarried['x'])->toBe(150.5);
    expect((float) $checkMarried['y'])->toBe(59.9);
    expect((float) $checkMarried['size'])->toBe(6.4);

    expect((float) $checkWidowed['x'])->toBe(169.0);
    expect((float) $checkWidowed['y'])->toBe(59.9);
    expect((float) $checkWidowed['size'])->toBe(6.4);

    expect((float) $checkSeparated['x'])->toBe(130.8);
    expect((float) $checkSeparated['y'])->toBe(63.8);
    expect((float) $checkSeparated['size'])->toBe(6.4);

    expect((float) $checkExistingLoanYes['x'])->toBe(68.5);
    expect((float) $checkExistingLoanYes['y'])->toBe(125.0);
    expect((float) $checkExistingLoanYes['size'])->toBe(6.4);

    // --- Assertions: page 2 fields ---
    expect((float) $fullNameP2['x'])->toBe(71.0);
    expect((float) $fullNameP2['y'])->toBe(81.5);
    expect((float) $fullNameP2['size'])->toBe(7.2);
    expect((float) $fullNameP2['width'])->toBe(118.0);

    expect((float) $reviewerP2['x'])->toBe(71.0);
    expect((float) $reviewerP2['y'])->toBe(91.5);
    expect((float) $reviewerP2['width'])->toBe(62.0);
    expect($reviewerP2['align'] ?? 'L')->toBe('L');

    expect((float) $companyNameP2['x'])->toBe(141.5);
    expect((float) $companyNameP2['y'])->toBe(91.5);
    expect((float) $companyNameP2['width'])->toBe(62.0);
    expect($companyNameP2['align'] ?? 'L')->toBe('L');

    expect((float) $businessAddress['x'])->toBe(15.0);
    expect((float) $businessAddress['y'])->toBe(101.5);
    expect((float) $businessAddress['width'])->toBe(86.0);
    expect($businessAddress['align'] ?? 'L')->toBe('L');

    expect((float) $dateP2['x'])->toBe(108.8);
    expect((float) $dateP2['y'])->toBe(101.5);
    expect((float) $dateP2['width'])->toBe(44.0);
    expect($dateP2['align'] ?? 'L')->toBe('L');

    // --- Assertions: page 1 health check fields (Q1-Q4) ---
    // TODO(calibrate-gl): update x/y after confirming with loan-documents:calibrate-fields gl overlay
    $findHealthCheck = fn (float $y): array => $fields->first(
        fn (array $f): bool => ($f['page'] ?? null) === 1
            && ($f['type'] ?? 'text') === 'check'
            && (float) ($f['y'] ?? -1) === $y
            && (float) ($f['x'] ?? -1) === 45.0,
    );

    $healthSmoker = $findHealthCheck(165.0);
    $healthHypertension = $findHealthCheck(171.0);
    $healthDiabetes = $findHealthCheck(177.0);
    $healthHospitalization = $findHealthCheck(183.0);

    expect($healthSmoker)->toBeArray();
    expect((float) $healthSmoker['x'])->toBe(45.0);
    expect((float) $healthSmoker['y'])->toBe(165.0);
    expect((float) $healthSmoker['size'])->toBe(6.4);

    expect($healthHypertension)->toBeArray();
    expect((float) $healthHypertension['x'])->toBe(45.0);
    expect((float) $healthHypertension['y'])->toBe(171.0);

    expect($healthDiabetes)->toBeArray();
    expect((float) $healthDiabetes['x'])->toBe(45.0);
    expect((float) $healthDiabetes['y'])->toBe(177.0);

    expect($healthHospitalization)->toBeArray();
    expect((float) $healthHospitalization['x'])->toBe(45.0);
    expect((float) $healthHospitalization['y'])->toBe(183.0);
});

test('loan information field map pins all field coordinates and resolves the deliberate data-source decisions', function () {
    $fields = collect((new LoanInformationPdfFieldMap)->fields());

    // Every field's y is unique and baseline-corrected against the real artwork (Phase 2
    // calibration) -- find by y rather than by 'value', since several fields use a closure
    // (money/percent/integer formatting) rather than a plain documentData path string.
    $findByY = fn (float $y): array => $fields->first(
        fn (array $f): bool => abs((float) ($f['y'] ?? -1) - $y) < 0.001,
    );

    // Mirrors ApprovedLoanPdfTemplateService::resolveValue() -- a closure is called with
    // documentData, a string is a data_get() path, anything else passes through as-is.
    $resolve = function (mixed $resolver, array $documentData): mixed {
        if (is_callable($resolver)) {
            return $resolver($documentData);
        }

        return is_string($resolver) ? data_get($documentData, $resolver) : $resolver;
    };

    $header = $fields->first(fn (array $f): bool => ($f['type'] ?? null) === 'image');
    expect($header)->toBeArray();
    expect($header['value'])->toBe('organization.report_header.designPath');
    expect((float) $header['x'])->toBe(18.0);
    expect((float) $header['y'])->toBe(10.0);
    expect((float) $header['width'])->toBe(174.0);
    expect((float) $header['height'])->toBe(24.0);

    $fullName = $findByY(55.25);
    expect($fullName['value'])->toBe('applicant.full_name');
    expect((int) $fullName['size'])->toBe(9);
    expect($fullName['shrink_to_fit'] ?? false)->toBeTrue();

    $address = $findByY(61.23);
    expect($address['value'])->toBe('applicant.address');
    expect($address['shrink_to_fit'] ?? false)->toBeTrue();

    $employer = $findByY(67.21);
    expect($employer['value'])->toBe('applicant.employer_or_business');
    expect($employer['shrink_to_fit'] ?? false)->toBeTrue();

    // Resolved decision: reuses the same path AffidavitUndertakingPdfFieldMap already
    // stamps, not a new field -- see LOAN_INFORMATION_FPDI_CONVERSION_PLAN.md §3.
    $position = $findByY(73.19);
    expect($position['value'])->toBe('applicant.position_or_designation');

    $loanManager = $findByY(109.06);
    expect($loanManager['value'])->toBe('reviewer.name');

    // Resolved decision: "Payable Every" (Section 4) intentionally restates "Mode of
    // Payment" (Section 1) -- same source, same literal value, no separate formatting.
    $modeOfPayment = $findByY(103.08);
    $payableEvery = $findByY(263.15);
    expect($modeOfPayment['value'])->toBe('loan.payment_mode_workbook');
    expect($payableEvery['value'])->toBe('loan.payment_mode_workbook');

    $documentData = [
        'loan' => [
            'approved_amount_raw' => 25000.0,
            'interest_rate_raw' => 0.36,
            'amortization_loan_security_raw' => 20.83,
            'savings_rate_raw' => 999999.99,
            'penalty_rate_raw' => 0.05,
        ],
    ];

    // Resolved decision: the "Savings" row is an artwork wording choice, not a genuine
    // relabel -- it must read the SAME figure the current Blade's third amortization row
    // already reads (loan.amortization_loan_security_raw), never the newer, independent
    // loan.savings_rate_raw (commit b3c7e0f). Asserting the resolved VALUE, not just the
    // field's 'value' path, so this test fails loudly if that path is ever swapped.
    $savingsRow = $findByY(197.70);
    expect($resolve($savingsRow['value'], $documentData))->toBe('20.83');

    $loanApproved = $findByY(85.15);
    expect($resolve($loanApproved['value'], $documentData))->toBe('25,000.00');

    $interestRate = $findByY(91.12);
    expect($resolve($interestRate['value'], $documentData))->toBe('36.00%');

    $penaltyPerMonth = $findByY(275.11);
    expect($resolve($penaltyPerMonth['value'], $documentData))->toBe('5.00%');

    // Resolved decision (explicit, deliberate): hardcoded to match Disclosure Statement's
    // commit 0d9d2a3 precedent exactly, even though reviewer.name/position are properly
    // wired here (unlike DS at the time) -- a cross-document consistency choice, not an
    // oversight. Must never be "fixed" to use the live reviewer fields.
    //
    // Role is appended inline ("Name, ROLE"), not stacked on a second line like DS's
    // block -- confirmed (2026-07-20) that a second line collides with the ruled divider
    // above "Witnessed By" in this artwork's tighter row spacing.
    $certifiedCorrect = $findByY(291.78);
    expect($resolve($certifiedCorrect['value'], []))->toBe('VELINA P. GAMUTAN, BOOKKEEPER');
    expect((string) $certifiedCorrect['style'])->toBe('B');

    // Resolved decision: single-source field only -- witness_one_name, never
    // witness_two_name or a concatenation of both.
    $witnessedBy = $findByY(297.76);
    expect($witnessedBy['value'])->toBe('reviewer.witness_one_name');
    expect($fields->contains(fn (array $f): bool => ($f['value'] ?? null) === 'reviewer.witness_two_name'))->toBeFalse();

    $approvedBy = $findByY(303.74);
    expect($approvedBy['value'])->toBe('reviewer.name');

    // The two blank Calibri runs (y=313.11/316.49mm in the artwork) are resolved as having
    // no visual footprint (Phase 2) and must stay permanently unwired.
    expect($fields->contains(fn (array $f): bool => abs((float) ($f['y'] ?? -1) - 313.11) < 1.0))->toBeFalse();
});

test('affidavit undertaking pdf prints payout bank details', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    approvedLoanDocumentsPersistDataEntry($loanRequest, 'payout_bank_name', 'string', 'RURAL SAVINGS BANK');
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'payout_account_number', 'string', '9876543210');
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'payout_atm_number', 'string', '4444-3333-2222-1111');
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'payout_bank_branch', 'string', 'DAVAO BRANCH');

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.affidavit-undertaking', $loanRequest));

    $response->assertOk();
    $text = approvedLoanDocumentsExtractPdfText($response);

    // payout_account_name is intentionally not asserted here — the real AU reference document has
    // no "account name" blank (the deposit account is already the affiant's own, named in the header
    // table), so it is not wired into the AU field map.
    expect($text)
        ->toContain('RURAL SAVINGS BANK')
        ->toContain('9876543210')
        ->toContain('4444-3333-2222-1111')
        ->toContain('DAVAO BRANCH');
});

test('affidavit undertaking pdf prints applicant identity and employment details', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.affidavit-undertaking', $loanRequest));

    $response->assertOk();
    $text = approvedLoanDocumentsExtractPdfText($response);

    expect($text)
        ->toContain('Married')
        ->toContain('FILIPINO')
        ->toContain('Manager')
        ->toContain('Sample Enterprise');
});

test('affidavit undertaking pdf composes the full agency address, not just the street line', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $loanRequest->applicant()->first()->update([
        'employer_business_address1' => 'Poblacion',
        'employer_business_address2' => 'Lianga',
        'employer_business_address3' => 'Surigao del Sur',
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.affidavit-undertaking', $loanRequest));

    $response->assertOk();
    $text = approvedLoanDocumentsExtractPdfText($response);

    expect($text)->toContain('Poblacion, Lianga, Surigao del Sur');
});

test('affidavit undertaking pdf prints notarization details', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    // Place of signing is the notary's own fixed office fact — it comes from the org's
    // full configured business address (street/barangay, city, province), not a per-loan
    // staff input. Doc/Page/Book No. and valid ID number/issuance location have no
    // reference-document equivalent and are no longer wired into AU at all (see
    // AffidavitUndertakingPdfFieldMap) — the notary fills them by hand. The separate
    // notarial_province "for and in ___" blank stays unwired too, unaffected by
    // signing_place now carrying the full composed address (province included).
    OrganizationSetting::factory()->create([
        'business_address1' => 'Purok 1, Barangay Poblacion',
        'business_address2' => 'Tagum City',
        'business_address3' => 'Davao del Norte',
    ]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.affidavit-undertaking', $loanRequest));

    $response->assertOk();
    $text = approvedLoanDocumentsExtractPdfText($response);

    expect($text)
        ->toContain('Purok 1, Barangay Poblacion, Tagum City, Davao del Norte')
        ->toContain((string) now()->year);
});

test('affidavit undertaking pdf composes the full org address for signing place, not just the city', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    // Same "don't silently drop a blank component" precedent as
    // composedEmployerBusinessAddress() -- business_address1 left blank here should not
    // produce a leading ", " before the city.
    OrganizationSetting::factory()->create([
        'business_address1' => null,
        'business_address2' => 'Lianga',
        'business_address3' => 'Surigao del Sur',
    ]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.affidavit-undertaking', $loanRequest));

    $response->assertOk();
    $text = approvedLoanDocumentsExtractPdfText($response);

    expect($text)
        ->toContain('Lianga, Surigao del Sur')
        ->not->toContain(', Lianga, Surigao del Sur, ')
        ->not->toContain(' , Lianga');
});

test('affidavit undertaking pdf prints guaranteed net take-home pay', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    approvedLoanDocumentsPersistDataEntry($loanRequest, 'guaranteed_net_take_home_pay', 'numeric', 32500);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.affidavit-undertaking', $loanRequest));

    $response->assertOk();
    $text = approvedLoanDocumentsExtractPdfText($response);

    expect($text)->toContain('32,500.00');
});

test('affidavit undertaking pdf renders an unusually long name, address, and GNTHP amount without failing', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    OrganizationSetting::factory()->create([
        'business_address1' => 'Purok 2, Barangay San Isidro',
        'business_address2' => 'Cagayan de Oro City',
        'business_address3' => 'Misamis Oriental',
    ]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $loanRequest->applicant()->first()->update([
        'first_name' => 'Maria Concepcion',
        'middle_name' => 'Villanueva-Fernandez',
        'last_name' => 'de la Santisima Trinidad',
    ]);

    approvedLoanDocumentsPersistDataEntry($loanRequest, 'guaranteed_net_take_home_pay', 'numeric', 1250000);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.affidavit-undertaking', $loanRequest));

    // The shrink-to-fit fields (applicant.full_name signature row, notarial.signing_place,
    // loan.gnthp) reduce font size rather than wrap or truncate -- the full value should
    // still reach the page and be extractable in full, just rendered smaller. The actual
    // font-size-shrinking behavior itself is covered directly in
    // ApprovedLoanPdfTemplateServiceShrinkToFitTest; this only proves the real document
    // generation path doesn't drop or break on an unusually long real-world value.
    $response->assertOk();
    $text = approvedLoanDocumentsExtractPdfText($response);

    // personFullName() abbreviates the middle name to an initial (PersonName) -- still long
    // enough to force the signature-row shrink-to-fit field to shrink.
    expect($text)
        ->toContain('Maria Concepcion V. de la Santisima Trinidad')
        ->toContain('Purok 2, Barangay San Isidro, Cagayan de Oro City, Misamis Oriental')
        ->toContain('1,250,000.00');
});

test('affidavit undertaking pdf stamps GNTHP and account number inline for paragraph 1', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    approvedLoanDocumentsPersistDataEntry($loanRequest, 'guaranteed_net_take_home_pay', 'numeric', 32500);
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'payout_account_number', 'string', '9876543210');

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.affidavit-undertaking', $loanRequest));

    $response->assertOk();
    $text = approvedLoanDocumentsExtractPdfText($response);

    // The rewritten paragraph 1 sentence itself is baked into the template artwork (an
    // FPDI-imported XObject), which approvedLoanDocumentsExtractPdfText() cannot see --
    // only the overlay values it stamps are extractable here. The field map pinning test
    // covers the inline coordinates directly; this confirms both values that now live in
    // the same sentence actually render through the real HTTP route with persisted data.
    expect($text)
        ->toContain('32,500.00')
        ->toContain('9876543210');

    $fields = collect((new AffidavitUndertakingPdfFieldMap)->fields());
    $gnthp = $fields->first(fn (array $f): bool => ($f['value'] ?? null) === 'loan.gnthp');
    $accountNumber = $fields->first(fn (array $f): bool => ($f['value'] ?? null) === 'authorization.payout_account_number');

    // Both now sit within the same rewritten paragraph-1 block, a few mm apart --
    // not on separate labeled sub-lines many mm apart as before the visual fidelity pass.
    expect(abs((float) $gnthp['y'] - (float) $accountNumber['y']))->toBeLessThan(10.0);
});

test('affidavit undertaking pdf is Legal size, not A4', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.affidavit-undertaking', $loanRequest));

    $response->assertOk();

    $pdf = new Fpdi('P', 'mm');
    $pageCount = $pdf->setSourceFile(approvedLoanDocumentsDownloadedFilePath($response));
    $templateId = $pdf->importPage(1);
    $size = $pdf->getTemplateSize($templateId);

    expect($pageCount)->toBe(1);
    // TCPDF round-trips mm through internal points (AddPage() re-derives the MediaBox from
    // the page-format array), which can drift by a fraction of a mm -- compare with a
    // tolerance rather than exact equality.
    expect(abs((float) $size['width'] - 215.9))->toBeLessThan(0.5);
    expect(abs((float) $size['height'] - 330.2))->toBeLessThan(0.5);
});

test('affidavit undertaking pdf includes the org report header image when configured', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $headerPath = 'branding/report-headers/test-header.png';
    Storage::disk('public')->put($headerPath, testPngSignatureBinary('one'));

    OrganizationSetting::factory()->create([
        'report_header_design_path' => $headerPath,
    ]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.affidavit-undertaking', $loanRequest));

    $response->assertOk();

    expect(approvedLoanDocumentsPdfImageObjectCount($response))->toBeGreaterThan(0);
});

test('affidavit undertaking pdf omits the header image gracefully when unconfigured', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    OrganizationSetting::factory()->create([
        'report_header_design_path' => null,
    ]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.affidavit-undertaking', $loanRequest));

    $response->assertOk();

    expect(approvedLoanDocumentsPdfImageObjectCount($response))->toBe(0);
});

test('loan information pdf includes the org report header image when configured', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $headerPath = 'branding/report-headers/test-header.png';
    Storage::disk('public')->put($headerPath, testPngSignatureBinary('one'));

    OrganizationSetting::factory()->create([
        'report_header_design_path' => $headerPath,
    ]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.loan-information', $loanRequest));

    $response->assertOk();

    expect(approvedLoanDocumentsPdfImageObjectCount($response))->toBeGreaterThan(0);
});

test('loan information pdf omits the header image gracefully when unconfigured', function () {
    Storage::fake('public');

    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    OrganizationSetting::factory()->create([
        'report_header_design_path' => null,
    ]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.loan-information', $loanRequest));

    $response->assertOk();

    expect(approvedLoanDocumentsPdfImageObjectCount($response))->toBe(0);
});

test('authorization pdf prints static bank name and payout details, not release_method or a dynamic bank name', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    // release_method (CONFIRMED DEAD) and payout_bank_name (bank name is now static artwork
    // text, "Enterprise Bank, Inc." -- MRDINC's fixed partner bank) are persisted here to
    // prove they no longer surface on AZ, not because AZ still reads them.
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'release_method', 'string', 'ATM');
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'payout_bank_name', 'string', 'LANDBANK');
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'payout_account_number', 'string', '1122334455');
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'payout_bank_branch', 'string', 'TAGUM BRANCH');
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'payout_atm_holder_name', 'string', 'MARIA B. SANTOS');

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.authorization', $loanRequest));

    $response->assertOk();
    $text = approvedLoanDocumentsExtractPdfText($response);

    // "Enterprise Bank, Inc." is baked into the real production artwork as static text
    // (not stamped by the field map), so it can't be asserted here -- this Feature test
    // suite stamps values onto a synthetic placeholder template
    // (approvedLoanDocumentsSeedTemplateFilesForTests()), not a copy of the real artwork
    // file. What IS testable here is that the dynamic fields this bug fix removed no
    // longer print, regardless of what's persisted for them.
    expect($text)
        ->toContain('1122334455')
        ->toContain('TAGUM BRANCH')
        ->toContain('MARIA B. SANTOS')
        ->not->toContain('LANDBANK')
        ->not->toContain('ATM ');
});

test('authorization pdf prints the loan security amount, not the raw approved amount', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'loan_security_rate', 'number', 0.02);
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'payout_account_number', 'string', '1122334455');

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.authorization', $loanRequest));

    $response->assertOk();
    $text = approvedLoanDocumentsExtractPdfText($response);

    // approved_amount is 25,000 (fixture default); loan_security_rate 0.02 -> 500.00,
    // distinct from the full approved amount -- proves the repointed field, not a stale one.
    expect($text)
        ->toContain('500.00')
        ->not->toContain('25,000.00');
});

test('authorization pdf omits atm card holder name when payout_atm_holder_name is null', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    approvedLoanDocumentsPersistDataEntry($loanRequest, 'release_method', 'string', 'ATM');
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'payout_bank_name', 'string', 'LANDBANK');
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'payout_account_number', 'string', '1122334455');
    // payout_atm_holder_name intentionally omitted — borrower uses their own card

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.authorization', $loanRequest));

    $response->assertOk();
    expect(approvedLoanDocumentsReadDownloadedFileContent($response))->toStartWith('%PDF');
});

test('undertaking barangay pdf prints applicant employment details, not staff barangay overrides', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    // Bug fix under test: Designation/Agency/Agency Address now come from the applicant's
    // own employment record, not a barangay_* staff override.
    $loanRequest->applicant()->first()->update([
        'current_position' => 'Barangay Health Worker',
        'employer_business_name' => 'Barangay San Isidro',
        'employer_business_address1' => 'Purok 3',
        'employer_business_address2' => 'Tagum City',
        'employer_business_address3' => 'Davao del Norte',
    ]);

    approvedLoanDocumentsPersistDataEntry($loanRequest, 'guaranteed_net_take_home_pay', 'number', 15000);
    // Staff barangay_* overrides are persisted too, to prove they no longer surface on UB.
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'barangay_name', 'string', 'BARANGAY SAN PEDRO');
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'barangay_official_designation', 'string', 'PUNONG BARANGAY');
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'barangay_agency_name', 'string', 'BARANGAY HALL');

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.undertaking-barangay', $loanRequest));

    $response->assertOk();
    $text = approvedLoanDocumentsExtractPdfText($response);

    expect($text)
        ->toContain('Barangay Health Worker')
        ->toContain('Barangay San Isidro')
        ->toContain('Purok 3, Tagum City, Davao del Norte')
        ->toContain('15,000.00')
        ->not->toContain('BARANGAY SAN PEDRO')
        ->not->toContain('PUNONG BARANGAY')
        ->not->toContain('BARANGAY HALL');
});

test('undertaking barangay pdf prints age, civil status, and nationality', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'guaranteed_net_take_home_pay', 'number', 15000);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.undertaking-barangay', $loanRequest));

    $response->assertOk();
    $text = approvedLoanDocumentsExtractPdfText($response);

    expect($text)
        ->toContain('Married')
        ->toContain('FILIPINO');
});

test('undertaking barangay pdf spells out the approved amount in words', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();
    $loanRequest->update(['approved_amount' => 50000]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.undertaking-barangay', $loanRequest));

    $response->assertOk();
    $text = approvedLoanDocumentsExtractPdfText($response);

    expect($text)
        ->toContain('FIFTY THOUSAND PESOS ONLY')
        ->toContain('50,000.00');
});

test('grepalife field map checks health answers when affirmative', function () {
    $fieldMap = new GrepalifePdfFieldMap;
    $fields = collect($fieldMap->fields());

    $findHealthCheck = fn (float $y): array => $fields->first(
        fn (array $f): bool => ($f['page'] ?? null) === 1
            && ($f['type'] ?? 'text') === 'check'
            && (float) ($f['y'] ?? -1) === $y
            && (float) ($f['x'] ?? -1) === 45.0,
    );

    $smokerField = $findHealthCheck(165.0);
    $hypertensionField = $findHealthCheck(171.0);
    $diabetesField = $findHealthCheck(177.0);
    $hospitalizationField = $findHealthCheck(183.0);

    $documentDataYes = [
        'health' => [
            'health_smoker' => 'yes',
            'health_hypertension' => true,
            'health_diabetes' => '1',
            'health_recent_hospitalization' => 1,
        ],
    ];

    $documentDataNo = [
        'health' => [
            'health_smoker' => 'no',
            'health_hypertension' => false,
            'health_diabetes' => '0',
            'health_recent_hospitalization' => null,
        ],
    ];

    expect(approvedLoanDocumentsResolveImageTemplateFieldValue($smokerField, $documentDataYes))->toBeTrue();
    expect(approvedLoanDocumentsResolveImageTemplateFieldValue($hypertensionField, $documentDataYes))->toBeTrue();
    expect(approvedLoanDocumentsResolveImageTemplateFieldValue($diabetesField, $documentDataYes))->toBeTrue();
    expect(approvedLoanDocumentsResolveImageTemplateFieldValue($hospitalizationField, $documentDataYes))->toBeTrue();

    expect(approvedLoanDocumentsResolveImageTemplateFieldValue($smokerField, $documentDataNo))->toBeFalse();
    expect(approvedLoanDocumentsResolveImageTemplateFieldValue($hypertensionField, $documentDataNo))->toBeFalse();
    expect(approvedLoanDocumentsResolveImageTemplateFieldValue($diabetesField, $documentDataNo))->toBeFalse();
    expect(approvedLoanDocumentsResolveImageTemplateFieldValue($hospitalizationField, $documentDataNo))->toBeFalse();
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
        ->toContain('Beneficiary One')
        ->toContain('04/05/1999')
        ->toContain('Beneficiary Two')
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
        ]);

    $this->actingAs($admin);

    foreach (approvedLoanDocumentsPdfRouteDefinitions($loanRequest) as $document) {
        $response = $this->get(route($document['route'], $loanRequest));

        $response->assertOk();
        expect(approvedLoanDocumentsReadDownloadedFileContent($response))
            ->toStartWith('%PDF');
    }

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
        '03-Affidavit-of-Undertaking.pdf',
        '04-Authorization.pdf',
        '05-Loan-Information.pdf',
        '06-Plan-of-Payment.pdf',
        '07-Disclosure-Statement.pdf',
        '08-Promissory-Note.pdf',
        '09-Undertaking-Barangay-Officials.pdf',
        '10-Loan-Security-Agreement.pdf',
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

});

test('missing grepalife image template is logged and fails generation', function () {
    Log::spy();
    $service = app(\App\Services\LoanRequests\ApprovedLoanImageTemplatePdfService::class);
    $fieldMap = app(GrepalifePdfFieldMap::class);

    expect(fn () => $service->renderContent(
        [
            [
                'image' => 'missing-grepalife-page-1-test.png',
                'width' => 216.0,
                'height' => 279.0,
            ],
        ],
        [],
        $fieldMap,
    ))->toThrow(
        \RuntimeException::class,
        'Missing image template file: missing-grepalife-page-1-test.png',
    );

    Log::shouldHaveReceived('error')
        ->withArgs(function (string $message, array $context): bool {
            $resourceTemplatePath = str_replace(
                '\\',
                '/',
                (string) ($context['resource_template_path'] ?? ''),
            );
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
            $legacyPublicTemplatePath = str_replace(
                '\\',
                '/',
                (string) ($context['legacy_public_template_path'] ?? ''),
            );

            return $message === 'Missing approved loan image template file.'
                && ($context['template_image'] ?? null) === 'missing-grepalife-page-1-test.png'
                && (
                    str_contains(
                        $resourceTemplatePath,
                        'resources/templates/approved-loan-documents/images/missing-grepalife-page-1-test.png',
                    )
                    || str_contains(
                        $templatePath,
                        'storage/app/templates/approved-loan-documents/images/missing-grepalife-page-1-test.png',
                    )
                    || str_contains(
                        $fallbackTemplatePath,
                        'storage/app/public/app/templates/approved-loan-documents/images/missing-grepalife-page-1-test.png',
                    )
                    || str_contains(
                        $legacyPublicTemplatePath,
                        'storage/app/public/app/templates/approved-loan-documents/missing-grepalife-page-1-test.png',
                    )
                );
        })
        ->once();
});

test('grepalife route uses version controlled resource template images when storage copies are missing', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);
    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();

    File::delete(approvedLoanDocumentsTemplateImagesDirectory().DIRECTORY_SEPARATOR.'grepalife-page-1.png');
    File::delete(approvedLoanDocumentsTemplateImagesDirectory().DIRECTORY_SEPARATOR.'grepalife-page-2.png');

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.grepalife', $loanRequest));

    $content = approvedLoanDocumentsReadDownloadedFileContent($response);

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'application/pdf');
    expect($content)
        ->toStartWith('%PDF')
        ->toContain('/Subtype /Image');
});

test('grepalife image templates fall back to the public template root directory', function () {
    $service = app(\App\Services\LoanRequests\ApprovedLoanImageTemplatePdfService::class);
    $fieldMap = app(GrepalifePdfFieldMap::class);
    $templateImage = 'grepalife-public-root-test-page-1.png';
    $publicFallbackImage = approvedLoanDocumentsPublicTemplateDirectory()
        .DIRECTORY_SEPARATOR
        .$templateImage;

    File::ensureDirectoryExists(dirname($publicFallbackImage));
    approvedLoanDocumentsCreateTemplateImage(
        $publicFallbackImage,
        216,
        279,
        'GREPALIFE Public Root Fallback',
    );

    try {
        $content = $service->renderContent(
            [
                [
                    'image' => $templateImage,
                    'width' => 216.0,
                    'height' => 279.0,
                ],
            ],
            [],
            $fieldMap,
        );

        expect($content)->toBeString()->toStartWith('%PDF');
    } finally {
        File::delete($publicFallbackImage);
    }
});

test('loan manager on document reflects the actual approver name', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $approver = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $approver->user_id,
        'fullname' => 'Rodrigo R. Reyes',
    ]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();
    $loanRequest->update(['approved_by' => $approver->user_id]);

    $documentData = approvedLoanDocumentsBuildDocumentData($loanRequest->fresh());

    expect(data_get($documentData, 'reviewer.name'))->toBe('Rodrigo R. Reyes');
    expect(data_get($documentData, 'reviewer.position'))->toBe('Loan Manager');
    expect(data_get($documentData, 'reviewer.name'))->not->toBe('Annabelle M. Amora');

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.loan-information', $loanRequest));

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'application/pdf');

    $text = approvedLoanDocumentsExtractPdfText($response);
    $searchable = strtoupper(str_replace(' ', '', $text));

    expect($searchable)->toContain('RODRIGOR.REYES');
});

test('loan manager falls back to resolver constant when no approver is set', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();
    $loanRequest->update(['approved_by' => null]);

    $documentData = approvedLoanDocumentsBuildDocumentData($loanRequest->fresh());

    expect(data_get($documentData, 'reviewer.name'))->toBe('Annabelle M. Amora');
    expect(data_get($documentData, 'reviewer.position'))->toBe('Loan Manager');
});

test('witnesses on document use stored data entries rather than manager name', function () {
    $admin = User::factory()->create();
    AdminProfile::factory()->create(['user_id' => $admin->user_id]);

    $approver = User::factory()->create();
    AdminProfile::factory()->create([
        'user_id' => $approver->user_id,
        'fullname' => 'Maria Approving Manager',
    ]);

    $loanRequest = approvedLoanDocumentsCreateApprovedLoanRequestWithPeople();
    $loanRequest->update(['approved_by' => $approver->user_id]);

    approvedLoanDocumentsPersistDataEntry($loanRequest, 'witness_one_name', 'string', 'Witness Alpha');
    approvedLoanDocumentsPersistDataEntry($loanRequest, 'witness_two_name', 'string', 'Witness Beta');

    $documentData = approvedLoanDocumentsBuildDocumentData($loanRequest->fresh());

    expect(data_get($documentData, 'reviewer.name'))->toBe('Maria Approving Manager');
    expect(data_get($documentData, 'reviewer.witness_one_name'))->toBe('Witness Alpha');
    expect(data_get($documentData, 'reviewer.witness_two_name'))->toBe('Witness Beta');

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.requests.documents.loan-information', $loanRequest));

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'application/pdf');

    $text = approvedLoanDocumentsExtractPdfText($response);
    $searchable = strtoupper(str_replace(' ', '', $text));

    // The artwork's "Witnessed By" is a single slot (unlike the old Blade template's two-
    // column witness table) -- LoanInformationPdfFieldMap deliberately stamps only
    // witness_one_name, never witness_two_name or a concatenation of both. See
    // LOAN_INFORMATION_FPDI_CONVERSION_PLAN.md §3.
    expect($searchable)
        ->toContain('MARIAAPPROVINGMANAGER')
        ->toContain('WITNESSALPHA')
        ->not->toContain('WITNESSBETA');
});

/**
 * @return array<int, array{route: string, filename: string, disposition: string}>
 */
function approvedLoanDocumentsRouteDefinitions(LoanRequest $loanRequest): array
{
    return [
        ...approvedLoanDocumentsPdfRouteDefinitions($loanRequest),
        ...approvedLoanDocumentsWorkbookRouteDefinitions($loanRequest),
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
 * @return array<int, array{route: string, filename: string, disposition: string, sheet: string}>
 */
function approvedLoanDocumentsWorkbookRouteDefinitions(LoanRequest $loanRequest): array
{
    return [];
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
            // Was 2 pages at the old (incorrect) 8.5x11in size; the extra 2in of
            // page height at 8.5x13in gives the same content room to fit on 1.
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
        [
            'route' => 'admin.requests.documents.promissory-note',
            'filename' => 'promissory-note-'.$loanRequest->reference.'.pdf',
            'disposition' => 'attachment',
            'page_count' => 2,
        ],
        [
            'route' => 'admin.requests.documents.loan-information',
            'filename' => 'loan-information-'.$loanRequest->reference.'.pdf',
            'disposition' => 'attachment',
            'page_count' => 1,
        ],
        [
            'route' => 'admin.requests.documents.plan-of-payment',
            'filename' => 'plan-of-payment-'.$loanRequest->reference.'.pdf',
            'disposition' => 'attachment',
            'page_count' => 1,
        ],
        [
            'route' => 'admin.requests.documents.disclosure-statement',
            'filename' => 'disclosure-statement-'.$loanRequest->reference.'.pdf',
            'disposition' => 'attachment',
            // This test forces the 'dompdf' driver (see beforeEach); DomPDF's
            // PHP-based font metrics measure Calibri slightly wider than the
            // real Chromium text-shaping engine does at these tight margins,
            // spilling onto a 2nd page. Verified separately with a real
            // Chromium render (the actual production driver) that the same
            // content fits on 1 page -- this is a dompdf-fallback-path
            // artifact, not a real layout regression.
            'page_count' => 2,
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
        '03-Affidavit-of-Undertaking.pdf',
        '04-Authorization.pdf',
        '05-Loan-Information.pdf',
        '06-Plan-of-Payment.pdf',
        '07-Disclosure-Statement.pdf',
        '08-Promissory-Note.pdf',
        '09-Undertaking-Barangay-Officials.pdf',
        '10-Loan-Security-Agreement.pdf',
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
        'admin.requests.documents.loan-information',
        'admin.requests.documents.plan-of-payment',
        'admin.requests.documents.disclosure-statement',
        'admin.requests.documents.promissory-note',
        'admin.requests.documents.undertaking-barangay',
        'admin.requests.documents.affidavit-undertaking',
        'admin.requests.documents.authorization',
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
    $cmap = approvedLoanDocumentsParseToUnicodeCMaps($content);

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
        $text .= ' '.approvedLoanDocumentsExtractPdfOperators($decoded, $cmap);
    }

    $text = str_replace(["\x00", "\r", "\n", "\t", "\f"], ' ', $text);
    $normalized = preg_replace('/ {2,}/', ' ', trim($text));

    return is_string($normalized) ? $normalized : trim($text);
}

/**
 * @return array<string, mixed>
 */
function approvedLoanDocumentsBuildDocumentData(LoanRequest $loanRequest): array
{
    $service = app(ApprovedLoanDocumentService::class);
    $buildDocumentData = \Closure::bind(
        fn (LoanRequest $record): array => $this->buildDocumentData($record),
        $service,
        ApprovedLoanDocumentService::class,
    );

    return $buildDocumentData($loanRequest);
}

/**
 * @return array<string, mixed>
 */
function approvedLoanDocumentsFindGrepalifeField(
    GrepalifePdfFieldMap $fieldMap,
    int $page,
    string $value,
    string $type = 'text',
): array {
    $field = collect($fieldMap->fields())->first(
        fn (array $candidate): bool => ($candidate['page'] ?? null) === $page
            && ($candidate['type'] ?? 'text') === $type
            && ($candidate['value'] ?? null) === $value,
    );

    if (! is_array($field)) {
        throw new RuntimeException('Unable to locate Grepalife field: '.$value);
    }

    return $field;
}

function approvedLoanDocumentsResolveImageTemplateFieldValue(
    array $field,
    array $documentData,
): mixed {
    $resolver = $field['value'] ?? null;
    $value = match (true) {
        is_callable($resolver) => $resolver($documentData),
        is_string($resolver) => data_get($documentData, $resolver),
        default => $resolver,
    };
    $transformer = $field['transform'] ?? null;

    if (is_callable($transformer)) {
        return $transformer($value);
    }

    return $value;
}

function approvedLoanDocumentsResolveSignatureAbsolutePath(
    string $signaturePath,
): ?string {
    $service = app(ApprovedLoanImageTemplatePdfService::class);
    $resolveSignaturePath = \Closure::bind(
        fn (string $path): ?string => $this->resolveSignaturePath($path),
        $service,
        ApprovedLoanImageTemplatePdfService::class,
    );

    return $resolveSignaturePath($signaturePath);
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

/**
 * @param  array<string, string>  $cmap
 */
function approvedLoanDocumentsExtractPdfOperators(string $decoded, array $cmap = []): string
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

            // Segments within one TJ array are one continuous text run split
            // only by kerning/position adjustments (common for CID/Unicode
            // fonts, which emit one hex code per glyph) -- concatenate them
            // directly, don't insert a space between every glyph.
            $run = '';

            foreach ($segments[0] as $segment) {
                $run .= approvedLoanDocumentsDecodePdfTextOperand($segment, $cmap);
            }

            $text .= ' '.$run;
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
                    $cmap,
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
                    $cmap,
                );
            }
        }
    }

    return $text;
}

/**
 * Parses every /ToUnicode CMap stream in a raw PDF into a single CID(hex,
 * 4-digit)->Unicode(string) lookup table. Required for text extraction from
 * CID/Unicode fonts (e.g. TCPDF's TrueTypeUnicode-converted fonts): the
 * 2-byte codes in their Tj/TJ operators are glyph indices assigned by the
 * font's own subsetting order, not Unicode code points, so they can only be
 * decoded correctly via the CMap the font itself embeds for this purpose.
 *
 * @return array<string, string>
 */
function approvedLoanDocumentsParseToUnicodeCMaps(string $pdfContent): array
{
    $map = [];

    if (preg_match_all('/\/ToUnicode\s+(\d+)\s+0\s+R/', $pdfContent, $refs) === 0) {
        return $map;
    }

    foreach (array_unique($refs[1]) as $objectNumber) {
        if (
            preg_match(
                '/(?:^|[^0-9])'.$objectNumber.'\s+0\s+obj(.*?)endobj/s',
                $pdfContent,
                $object,
            ) !== 1
        ) {
            continue;
        }

        if (preg_match('/stream\r?\n(.*?)\r?\nendstream/s', $object[1], $stream) !== 1) {
            continue;
        }

        $cmapText = approvedLoanDocumentsDecodePdfStream($stream[1]);

        // bfchar: individual "<src> <dst>" pairs.
        if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $cmapText, $chars, PREG_SET_ORDER) > 0) {
            foreach ($chars as $pair) {
                $map[strtoupper($pair[1])] = approvedLoanDocumentsHexToUnicodeString($pair[2]);
            }
        }

        // bfrange: "<srcLo> <srcHi> <dstLo>" contiguous ranges.
        if (
            preg_match_all(
                '/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/',
                $cmapText,
                $ranges,
                PREG_SET_ORDER,
            ) > 0
        ) {
            foreach ($ranges as $range) {
                $lo = hexdec($range[1]);
                $hi = hexdec($range[2]);
                $dst = hexdec($range[3]);

                // Ranges spanning more than a few hundred code points aren't
                // meaningful for this test suite's assertions and would be
                // wasteful to expand -- cap defensively.
                if ($hi < $lo || ($hi - $lo) > 512) {
                    continue;
                }

                for ($cid = $lo; $cid <= $hi; $cid++) {
                    $key = strtoupper(str_pad(dechex($cid), 4, '0', STR_PAD_LEFT));
                    $map[$key] = approvedLoanDocumentsHexToUnicodeString(
                        str_pad(dechex($dst + ($cid - $lo)), 4, '0', STR_PAD_LEFT),
                    );
                }
            }
        }
    }

    return $map;
}

function approvedLoanDocumentsHexToUnicodeString(string $hex): string
{
    $hex = strlen($hex) % 4 === 0 ? $hex : str_pad($hex, (int) (ceil(strlen($hex) / 4) * 4), '0', STR_PAD_LEFT);
    $binary = hex2bin($hex);

    if (! is_string($binary)) {
        return '';
    }

    $converted = @mb_convert_encoding($binary, 'UTF-8', 'UTF-16BE');

    return is_string($converted) ? $converted : '';
}

/**
 * Attempts to decode a raw byte string as 2-byte-per-character CID/Unicode
 * text -- via the font's own /ToUnicode CMap first (required for TCPDF's
 * TrueTypeUnicode-converted fonts, which use arbitrary glyph indices, not
 * Unicode code points, as their 2-byte codes), then a UTF-16BE heuristic
 * fallback. Returns null if $bytes doesn't look like 2-byte CID text at all,
 * so the caller can fall back to treating it as single-byte content.
 *
 * @param  array<string, string>  $cmap
 */
function approvedLoanDocumentsDecodeCidBytes(string $bytes, array $cmap): ?string
{
    if ($bytes === '' || strlen($bytes) % 2 !== 0) {
        return null;
    }

    $hex = strtoupper(bin2hex($bytes));

    if ($cmap !== []) {
        $mapped = '';
        $allFound = true;

        for ($i = 0; $i < strlen($hex); $i += 4) {
            $code = substr($hex, $i, 4);

            if (! isset($cmap[$code])) {
                $allFound = false;

                break;
            }

            $mapped .= $cmap[$code];
        }

        if ($allFound) {
            return $mapped;
        }
    }

    $looksUtf16Le = str_starts_with($bytes, "\xFF\xFE")
        || preg_match('/^(?:[\x00-\x7F]\x00)+[\x00-\x7F]?$/', $bytes) === 1;
    $looksUtf16Be = str_starts_with($bytes, "\xFE\xFF")
        || preg_match('/^(?:\x00[\x00-\x7F])+\x00?$/', $bytes) === 1;

    if (! $looksUtf16Le && ! $looksUtf16Be) {
        return null;
    }

    $converted = @mb_convert_encoding($bytes, 'UTF-8', $looksUtf16Le ? 'UTF-16LE' : 'UTF-16BE');

    return is_string($converted) ? $converted : null;
}

/**
 * @param  array<string, string>  $cmap
 */
function approvedLoanDocumentsDecodePdfTextOperand(string $operand, array $cmap = []): string
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
        $text = (string) $text;

        // TCPDF writes CID/Unicode-font text as a literal parenthesized
        // string containing raw 2-byte codes (not always hex-wrapped) --
        // try that decoding before falling back to single-byte content.
        // Don't trim on success: a lone space-only glyph is legitimate
        // mid-run content once per-glyph segments are concatenated.
        $decoded = approvedLoanDocumentsDecodeCidBytes($text, $cmap);

        return $decoded ?? trim($text);
    }

    if (! str_starts_with($operand, '<')) {
        return '';
    }

    $hex = strtoupper(substr($operand, 1, -1));
    $hex = strlen($hex) % 2 === 0 ? $hex : $hex.'0';
    $binary = hex2bin($hex);

    if (! is_string($binary)) {
        return '';
    }

    $decoded = approvedLoanDocumentsDecodeCidBytes($binary, $cmap);

    return $decoded ?? $binary;
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

/**
 * @return list<string>
 */
function approvedLoanDocumentsWorkbookStringValues(
    Spreadsheet $spreadsheet,
): array {
    $values = [];

    foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = Coordinate::columnIndexFromString(
            $worksheet->getHighestColumn(),
        );

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($column = 1; $column <= $highestColumn; $column++) {
                $value = $worksheet->getCell(
                    Coordinate::stringFromColumnIndex($column).$row,
                )->getValue();

                if (! is_string($value)) {
                    continue;
                }

                $trimmed = trim($value);

                if ($trimmed === '') {
                    continue;
                }

                $values[] = $trimmed;
            }
        }
    }

    return $values;
}

function approvedLoanDocumentsPdfPageCount(
    \Illuminate\Testing\TestResponse $response,
): int {
    $pdf = new Fpdi('P', 'mm');

    return $pdf->setSourceFile(
        approvedLoanDocumentsDownloadedFilePath($response),
    );
}

function approvedLoanDocumentsPdfImageObjectCount(
    \Illuminate\Testing\TestResponse $response,
): int {
    return substr_count(
        approvedLoanDocumentsReadDownloadedFileContent($response),
        '/Subtype /Image',
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
        'approved_interest_rate' => 0.36,
        'recommended_payment_frequency' => '15th & 30th',
    ]);

    approvedLoanDocumentsCreateLoanRequestPeopleSnapshots($loanRequest);
    approvedLoanDocumentsCreateDataEntries($loanRequest);

    return $loanRequest;
}

function approvedLoanDocumentsCreateDataEntries(LoanRequest $loanRequest): void
{
    foreach ([
        'service_charge_rate' => ['number', 0.05],
        'insurance_rate' => ['number', 1.0],
        'insurance_term' => ['number', 12],
        'loan_security_rate' => ['number', 0.02],
        'savings_rate' => ['number', 0.02],
        'documentary_stamp_rate' => ['number', 0.0075],
        'notarial_fee' => ['number', 100.0],
        'penalty_rate_per_month' => ['number', 0.05],
        'witness_one_name' => ['string', 'Annabelle M. Amora'],
        'witness_two_name' => ['string', 'Annabelle M. Amora'],
        'release_method' => ['string', 'Bank transfer'],
        'payout_bank_name' => ['string', 'WIBS Cooperative Bank'],
        'payout_account_name' => ['string', 'Sample Q Member'],
        'payout_account_number' => ['string', '1234567890'],
        'payout_account_type' => ['string', 'Savings'],
        'payout_atm_number' => ['string', '9876543210'],
        'barangay_name' => ['string', 'Barangay San Isidro'],
        'barangay_clearance_reference' => ['string', 'BCL-2026-030'],
        'barangay_locality' => ['string', 'Tagum City, Davao del Norte'],
        'barangay_official_name' => ['string', 'Hon. Pedro Santos'],
        'barangay_official_title' => ['string', 'Barangay Captain'],
        'notarial_venue' => ['string', 'Tagum City, Davao del Norte'],
    ] as $fieldKey => [$valueType, $value]) {
        approvedLoanDocumentsPersistDataEntry(
            $loanRequest,
            $fieldKey,
            $valueType,
            $value,
        );
    }
}

function approvedLoanDocumentsPersistDataEntry(
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
    $pdfDirectory = $templateDirectory.DIRECTORY_SEPARATOR.'pdf';

    File::ensureDirectoryExists($templateDirectory);
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
            ['width' => 215.9, 'height' => 279.4, 'title' => 'Authorization'],
        ],
    );
    approvedLoanDocumentsCreateTemplatePdf(
        $pdfDirectory.DIRECTORY_SEPARATOR.'loan information sheet.pdf',
        [
            ['width' => 215.9, 'height' => 330.2, 'title' => 'Loan Information Sheet'],
        ],
    );
}

function approvedLoanDocumentsTemplateDirectory(): string
{
    return storage_path('app/templates/approved-loan-documents');
}

function approvedLoanDocumentsPublicTemplateDirectory(): string
{
    return storage_path('app/public/app/templates/approved-loan-documents');
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

/**
 * @param  array<string, mixed>  $field
 * @return array{
 *     scale?: float|int|null,
 *     max_width?: float|int|null,
 *     max_height?: float|int|null,
 *     offset_x?: float|int|null,
 *     offset_y?: float|int|null
 * }
 */
function approvedLoanDocumentsSignaturePlacementOptions(array $field): array
{
    return array_filter([
        'scale' => $field['scale'] ?? null,
        'max_width' => $field['max_width'] ?? null,
        'max_height' => $field['max_height'] ?? null,
        'offset_x' => $field['offset_x'] ?? null,
        'offset_y' => $field['offset_y'] ?? null,
    ], static fn (mixed $value): bool => $value !== null);
}
