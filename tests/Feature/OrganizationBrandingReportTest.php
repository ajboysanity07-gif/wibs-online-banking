<?php

use App\LoanRequestStatus;
use App\Models\AppUser as User;
use App\Models\LoanRequest;
use App\Models\OrganizationSetting;
use App\Services\Admin\MemberLoans\MemberLoanExportService;
use App\Services\OrganizationSettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\mock;

test('loan request report renders uploaded header design when available', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/report-headers/header.png', 'header');

    OrganizationSetting::factory()->create([
        'company_name' => 'Acme Cooperative',
        'report_header_design_path' => 'branding/report-headers/header.png',
    ]);

    $branding = app(OrganizationSettingsService::class)->branding();
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);

    $html = view('reports.loan-request', [
        'loanRequest' => $loanRequest,
        'applicant' => [],
        'coMakerOne' => [],
        'coMakerTwo' => [],
        'companyName' => $branding['companyName'],
        'reportHeader' => $branding['reportHeader'],
        'reportTypography' => $branding['reportTypography'],
        'generatedAt' => Carbon::now(),
    ])->render();

    expect($branding['reportHeader']['designData'])->not->toBeNull();
    expect($html)->toContain('class="report-header-design"');
    expect($html)->toContain('src="data:image/png;base64,'.base64_encode('header').'"');
});

test('loan request report reserves physical signature areas without digital images', function () {
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);

    $html = view('reports.loan-request', [
        'loanRequest' => $loanRequest,
        'applicant' => [
            'signatureData' => null,
        ],
        'coMakerOne' => [
            'signatureData' => null,
        ],
        'coMakerTwo' => [
            'signatureData' => null,
        ],
        'reviewer' => [
            'signatureData' => null,
        ],
        'companyName' => 'Acme Cooperative',
        'reportHeader' => [
            'companyName' => 'Acme Cooperative',
            'designData' => null,
        ],
        'reportTypography' => [],
        'generatedAt' => Carbon::now(),
    ])->render();

    expect($html)
        ->toContain('class="signature-art"')
        ->toContain('class="signature-signing-area"')
        ->toContain('min-height: 108px;')
        ->toContain('bottom: 20px;')
        ->toContain('height: 72px;')
        ->toContain('max-width: 126%;')
        ->toContain('max-height: 72px;')
        ->not->toContain('alt="Applicant signature"')
        ->not->toContain('alt="Co-maker 1 signature"')
        ->not->toContain('alt="Co-maker 2 signature"')
        ->not->toContain('alt="Loan manager signature"');

    expect(substr_count($html, 'class="signature-line"'))->toBe(4);
});

test('loan request report keeps printed names and blank signature lines when signatures are collected physically', function () {
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);

    $html = view('reports.loan-request', [
        'loanRequest' => $loanRequest,
        'applicant' => [
            'first_name' => 'JUAN',
            'middle_name' => 'SANTOS',
            'last_name' => 'DELA CRUZ',
            'signatureData' => null,
        ],
        'coMakerOne' => [
            'first_name' => 'MARIA',
            'middle_name' => 'LOPEZ',
            'last_name' => 'REYES',
            'signatureData' => null,
        ],
        'coMakerTwo' => [
            'first_name' => 'PEDRO',
            'middle_name' => 'SANTOS',
            'last_name' => 'CRUZ',
            'signatureData' => null,
        ],
        'reviewer' => [
            'name' => 'ANNABELLE M. AMORA',
        ],
        'reviewerSignatureData' => null,
        'companyName' => 'Acme Cooperative',
        'reportHeader' => [
            'companyName' => 'Acme Cooperative',
            'designData' => null,
        ],
        'reportTypography' => [],
        'generatedAt' => Carbon::now(),
    ])->render();

    expect($html)
        ->toContain('<div class="signature-name">Juan Santos Dela Cruz</div>')
        ->toContain('<div class="signature-name">Maria Lopez Reyes</div>')
        ->toContain('<div class="signature-name">Pedro Santos Cruz</div>')
        ->toContain('<div class="signature-name">Annabelle M. Amora</div>')
        ->toContain('<div class="signature-label">Member / Applicant</div>')
        ->toContain('<div class="signature-label">Co-maker 1</div>')
        ->toContain('<div class="signature-label">Co-maker 2</div>')
        ->toContain('<div class="signature-line"></div>')
        ->not->toContain('alt="Applicant signature"')
        ->not->toContain('alt="Co-maker 1 signature"')
        ->not->toContain('alt="Co-maker 2 signature"')
        ->not->toContain('alt="Loan manager signature"');
});

test('loan request report keeps approved details and blank signature lines on approved requests', function () {
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
        'approved_term' => 6,
    ]);

    $html = view('reports.loan-request', [
        'loanRequest' => $loanRequest,
        'applicant' => [
            'first_name' => 'JUAN',
            'middle_name' => 'SANTOS',
            'last_name' => 'DELA CRUZ',
            'signatureData' => null,
        ],
        'coMakerOne' => [
            'first_name' => 'MARIA',
            'middle_name' => 'LOPEZ',
            'last_name' => 'REYES',
            'signatureData' => null,
        ],
        'coMakerTwo' => [
            'first_name' => 'PEDRO',
            'middle_name' => 'SANTOS',
            'last_name' => 'CRUZ',
            'signatureData' => null,
        ],
        'reviewer' => [
            'name' => 'ANNABELLE M. AMORA',
        ],
        'reviewerSignatureData' => null,
        'companyName' => 'Acme Cooperative',
        'reportHeader' => [
            'companyName' => 'Acme Cooperative',
            'designData' => null,
        ],
        'reportTypography' => [],
        'generatedAt' => Carbon::now(),
    ])->render();

    expect($html)
        ->toContain('<td class="field">6 months</td>')
        ->toContain('<div class="signature-name">Juan Santos Dela Cruz</div>')
        ->toContain('<div class="signature-name">Maria Lopez Reyes</div>')
        ->toContain('<div class="signature-name">Pedro Santos Cruz</div>')
        ->toContain('<div class="signature-name">Annabelle M. Amora</div>')
        ->toContain('<div class="signature-label">Member / Applicant</div>')
        ->toContain('<div class="signature-label">Co-maker 1</div>')
        ->toContain('<div class="signature-label">Co-maker 2</div>')
        ->toContain('<div class="signature-label">Loan Manager / Approved By</div>')
        ->not->toContain('alt="Applicant signature"')
        ->not->toContain('alt="Co-maker 1 signature"')
        ->not->toContain('alt="Co-maker 2 signature"')
        ->not->toContain('alt="Loan manager signature"')
        ->not->toContain('data:image/png;base64,');

    $signatureSection = strstr($html, '<div class="signature-row">');

    expect($signatureSection)->not->toBeFalse();
    expect(substr_count((string) $signatureSection, 'class="signature-line"'))->toBe(4);
});

test('loan security agreement report renders uploaded header design when available', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/report-headers/header.png', 'header');

    OrganizationSetting::factory()->create([
        'company_name' => 'Acme Cooperative',
        'report_header_design_path' => 'branding/report-headers/header.png',
    ]);

    $branding = app(OrganizationSettingsService::class)->branding();

    $html = view('reports.loan-security-agreement', [
        'organization' => [
            'company_name' => $branding['companyName'],
        ],
        'loan' => [
            'type' => 'SALARY LOAN',
            'approved_amount' => '25,000.00',
            'approved_date' => 'May 22, 2026',
            'approved_term_label' => '12 months',
        ],
        'applicant' => [
            'full_name' => 'Loan Member',
            'address' => 'Sample Street, Sample City, Sample Province',
            'signature_data' => null,
        ],
        'reviewer' => [
            'name' => 'Annabelle M. Amora',
            'position' => 'Authorized Representative',
        ],
        'reportHeader' => $branding['reportHeader'],
        'reportTypography' => $branding['reportTypography'],
        'organizationLogoDataUri' => null,
        'placeOfSigning' => 'Sample City, Sample Province',
    ])->render();

    expect($html)->toContain('class="report-header-design"');
    expect($html)->toContain('src="data:image/png;base64,'.base64_encode('header').'"');
    expect($html)->toContain('Loan Security Agreement');
});

test('loan security agreement report keeps printed names and blank signature lines without digital images', function () {
    $html = view('reports.loan-security-agreement', [
        'organization' => [
            'company_name' => 'Acme Cooperative',
        ],
        'loan' => [
            'type' => 'SALARY LOAN',
            'approved_amount' => '25,000.00',
            'approved_date' => 'May 22, 2026',
            'approved_term_label' => '12 months',
        ],
        'applicant' => [
            'full_name' => 'Helario B. Tejero',
            'address' => 'Banahao, Lianga, Surigao del Sur',
            'signature_data' => null,
        ],
        'reviewer' => [
            'name' => 'Annabelle M. Amora',
            'position' => 'Authorized Representative',
            'signature_data' => null,
        ],
        'reportHeader' => [
            'designData' => null,
        ],
        'organizationLogoDataUri' => null,
        'placeOfSigning' => 'Lianga, Surigao del Sur',
    ])->render();

    expect($html)
        ->toContain('size: 8.5in 11in;')
        ->toContain('margin: .75in 1in 1in 1in;')
        ->toContain('<span class="agreement-fill">Helario B. Tejero</span>')
        ->toContain('<span class="agreement-fill">Banahao, Lianga, Surigao del Sur</span>')
        ->toContain('<span class="agreement-fill">SALARY LOAN</span>')
        ->toContain('Acme Cooperative')
        ->toContain('Annabelle M. Amora, Authorized Representative')
        ->toContain('this 22 day of')
        ->toContain('May, 2026 at')
        ->toContain('class="signature-layout"')
        ->toContain('width: 76%;')
        ->toContain('margin: 20pt auto 0;')
        ->toContain('class="signature-block signature-block--borrower"')
        ->toContain('class="signature-block signature-block--lender"')
        ->toContain('class="signature-signing-area signature-signing-area--borrower"')
        ->toContain('class="signature-signing-area signature-signing-area--lender"')
        ->toContain('min-height: 72pt;')
        ->toContain('<div class="signature-label">Borrower</div>')
        ->toContain('<div class="signature-label">Lender</div>')
        ->not->toContain('alt="Borrower signature"')
        ->not->toContain('alt="Lender signature"')
        ->not->toContain('data:image/png;base64,')
        ->not->toContain('This Agreement pertains to the Borrower')
        ->not->toContain('approved amount')
        ->not->toContain('payable over')
        ->not->toContain('<span class="agreement-fill">Acme Cooperative</span>')
        ->not->toContain('<span class="agreement-fill">Annabelle M. Amora, Authorized Representative</span>')
        ->not->toContain('class="signature-meta"');

    $signatureSection = strstr($html, '<table class="signature-layout">');

    expect($signatureSection)->not->toBeFalse();
    expect(substr_count((string) $signatureSection, 'class="signature-line"'))->toBe(2);
    expect(strpos($signatureSection, 'Helario B. Tejero'))
        ->toBeLessThan(strpos($signatureSection, '<div class="signature-label">Borrower</div>'));
    expect(strpos($signatureSection, 'Annabelle M. Amora'))
        ->toBeLessThan(strpos($signatureSection, '<div class="signature-label">Lender</div>'));
});

test('loan payments report renders uploaded header design when available', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/report-headers/header.png', 'header');

    OrganizationSetting::factory()->create([
        'company_name' => 'Acme Cooperative',
        'report_header_design_path' => 'branding/report-headers/header.png',
    ]);

    $branding = app(OrganizationSettingsService::class)->branding();

    $html = view('reports.loan-payments', [
        'companyName' => $branding['companyName'],
        'reportHeader' => $branding['reportHeader'],
        'reportTypography' => $branding['reportTypography'],
        'memberName' => 'Loan Member',
        'memberAccountNo' => '000123',
        'loanNumber' => 'LN-001',
        'reportStart' => Carbon::now()->subDay(),
        'reportEnd' => Carbon::now(),
        'generatedAt' => Carbon::now(),
        'generatedBy' => 'Admin',
        'payments' => Collection::make(),
        'openingBalance' => 0,
        'closingBalance' => 0,
    ])->render();

    expect($html)->toContain('class="report-header-design"');
    expect($html)->toContain('src="data:image/png;base64,'.base64_encode('header').'"');
});

test('reports fall back to a simple header when no uploaded design exists', function () {
    OrganizationSetting::factory()->create([
        'company_name' => 'Acme Cooperative',
    ]);

    $branding = app(OrganizationSettingsService::class)->branding();
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);

    $html = view('reports.loan-request', [
        'loanRequest' => $loanRequest,
        'applicant' => [],
        'coMakerOne' => [],
        'coMakerTwo' => [],
        'companyName' => $branding['companyName'],
        'reportHeader' => $branding['reportHeader'],
        'reportTypography' => $branding['reportTypography'],
        'generatedAt' => Carbon::now(),
    ])->render();

    expect($branding['reportHeader']['designData'])->toBeNull();
    expect($html)->toContain('Acme Cooperative');
});

test('loan payments export uses organization branding values', function () {
    if (! Schema::hasTable('wlnmaster')) {
        Schema::create('wlnmaster', function (Blueprint $table) {
            $table->string('acctno');
            $table->string('lnnumber');
            $table->string('lntype')->nullable();
            $table->decimal('principal', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->dateTime('lastmove')->nullable();
        });
    }

    if (! Schema::hasTable('wlnled')) {
        Schema::create('wlnled', function (Blueprint $table) {
            $table->string('acctno');
            $table->string('lnnumber');
            $table->string('lntype')->nullable();
            $table->dateTime('date_in')->nullable();
            $table->decimal('principal', 12, 2)->default(0);
            $table->decimal('payments', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->decimal('accruedint', 12, 2)->default(0);
            $table->string('lnstatus')->nullable();
            $table->string('controlno')->nullable();
            $table->string('transno')->nullable();
        });
    }

    $member = User::factory()->create([
        'acctno' => '000799',
        'username' => 'Brand Member',
    ]);

    DB::table('wlnmaster')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-799',
        'lntype' => 'Regular',
        'principal' => 1500,
        'balance' => 1100,
    ]);

    DB::table('wlnled')->insert([
        'acctno' => $member->acctno,
        'lnnumber' => 'LN-799',
        'lntype' => 'Regular',
        'date_in' => Carbon::parse('2025-03-25 00:00:00')->toDateTimeString(),
        'principal' => 120,
        'payments' => 120,
        'balance' => 980,
    ]);

    $branding = [
        'companyName' => 'Acme Cooperative',
        'reportHeader' => [
            'designPath' => null,
            'designUrl' => null,
            'designData' => 'data:image/png;base64,header',
        ],
        'reportTypography' => [],
    ];

    mock(OrganizationSettingsService::class, function ($mock) use ($branding) {
        $mock->shouldReceive('branding')->andReturn($branding);
    });

    Pdf::shouldReceive('setOption')
        ->once()
        ->with('isPhpEnabled', true)
        ->andReturnSelf();

    Pdf::shouldReceive('loadView')
        ->once()
        ->with('reports.loan-payments', Mockery::on(function (array $data) use ($branding) {
            $reportHeader = $data['reportHeader'] ?? [];

            return isset($data['companyName'])
                && $data['companyName'] === $branding['companyName']
                && ($reportHeader['companyName'] ?? null) === $branding['companyName']
                && ($reportHeader['designData'] ?? null) === 'data:image/png;base64,header';
        }))
        ->andReturnSelf();

    Pdf::shouldReceive('stream')->once()->andReturn(response('pdf'));

    $response = app(MemberLoanExportService::class)->exportPayments(
        $member,
        'LN-799',
        'pdf',
        null,
        null,
        null,
        false,
    );

    expect($response->getContent())->toBe('pdf');
});

test('logo data uri uses full logo asset when preset selected', function () {
    OrganizationSetting::factory()->create([
        'logo_preset' => OrganizationSettingsService::LOGO_PRESET_FULL,
    ]);

    $expectedPath = public_path('mrdinc-logo.png');
    $expectedData = file_get_contents($expectedPath);

    expect($expectedData)->not->toBeFalse();

    $expected = sprintf(
        'data:image/png;base64,%s',
        base64_encode($expectedData),
    );

    $dataUri = app(OrganizationSettingsService::class)->logoDataUri();

    expect($dataUri)->toBe($expected);
});

test('logo data uri uses custom mark override when available', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/logos/mark/custom.png', 'mark');

    OrganizationSetting::factory()->create([
        'logo_preset' => OrganizationSettingsService::LOGO_PRESET_MARK,
        'logo_mark_path' => 'branding/logos/mark/custom.png',
    ]);

    $dataUri = app(OrganizationSettingsService::class)->logoDataUri();

    expect($dataUri)->toBe(sprintf(
        'data:image/png;base64,%s',
        base64_encode('mark'),
    ));
});

test('logo data uri uses custom full override when available', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/logos/full/custom.png', 'full');

    OrganizationSetting::factory()->create([
        'logo_preset' => OrganizationSettingsService::LOGO_PRESET_FULL,
        'logo_full_path' => 'branding/logos/full/custom.png',
    ]);

    $dataUri = app(OrganizationSettingsService::class)->logoDataUri();

    expect($dataUri)->toBe(sprintf(
        'data:image/png;base64,%s',
        base64_encode('full'),
    ));
});

test('logo data uri uses mark logo asset by default', function () {
    OrganizationSetting::factory()->create([
        'logo_preset' => OrganizationSettingsService::LOGO_PRESET_MARK,
    ]);

    $expectedPath = public_path('mrdinc-logo-mark.png');
    $expectedData = file_get_contents($expectedPath);

    expect($expectedData)->not->toBeFalse();

    $expected = sprintf(
        'data:image/png;base64,%s',
        base64_encode($expectedData),
    );

    $dataUri = app(OrganizationSettingsService::class)->logoDataUri();

    expect($dataUri)->toBe($expected);
});

test('loan request report fallback uses application form when company name is missing', function () {
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);

    $html = view('reports.loan-request', [
        'loanRequest' => $loanRequest,
        'applicant' => [],
        'coMakerOne' => [],
        'coMakerTwo' => [],
        'companyName' => '',
        'reportHeader' => [
            'designPath' => null,
            'designUrl' => null,
            'designData' => null,
            'companyName' => '',
        ],
        'reportTypography' => [],
        'generatedAt' => Carbon::now(),
    ])->render();

    expect($html)->toContain('APPLICATION FORM');
});

test('loan payments report fallback header shows company name when design is missing', function () {
    $html = view('reports.loan-payments', [
        'companyName' => 'Acme Cooperative',
        'reportHeader' => [
            'designPath' => null,
            'designUrl' => null,
            'designData' => null,
            'companyName' => 'Acme Cooperative',
        ],
        'reportTypography' => [],
        'memberName' => 'Loan Member',
        'memberAccountNo' => '000123',
        'loanNumber' => 'LN-001',
        'reportStart' => Carbon::now()->subDay(),
        'reportEnd' => Carbon::now(),
        'generatedAt' => Carbon::now(),
        'generatedBy' => 'Admin',
        'payments' => Collection::make(),
        'openingBalance' => 0,
        'closingBalance' => 0,
    ])->render();

    expect($html)->toContain('Acme Cooperative');
});
