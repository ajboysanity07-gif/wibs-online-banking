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
        'processor' => [
            'name' => 'PATRICIA LOAN PROCESSOR',
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
        ->toContain('class="section-group section-group--signature"')
        ->toContain('class="signature-table"')
        ->toContain('class="signature-signing-space"')
        ->toContain('height: 10px;')
        ->toContain('class="signature-name"')
        ->toContain('PATRICIA LOAN PROCESSOR')
        ->toContain('Loan Processor / Recommended By')
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
        'processor' => [
            'name' => 'PATRICIA LOAN PROCESSOR',
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
        ->toContain('class="signature-name signature-name--tight"')
        ->toContain('JUAN SANTOS DELA CRUZ')
        ->toContain('MARIA LOPEZ REYES')
        ->toContain('PEDRO SANTOS CRUZ')
        ->toContain('PATRICIA LOAN PROCESSOR')
        ->toContain('<div class="signature-label">Loan Processor / Recommended By</div>')
        ->toContain('<div class="signature-label">Member / Applicant</div>')
        ->toContain('<div class="signature-label">Co-maker 1</div>')
        ->toContain('<div class="signature-label">Co-maker 2</div>')
        ->toContain('<div class="signature-line"></div>')
        ->not->toContain('ANNABELLE MONGADO AMORA')
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
        'processor' => [
            'name' => 'PATRICIA LOAN PROCESSOR',
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
        ->toContain('class="signature-name signature-name--tight"')
        ->toContain('JUAN SANTOS DELA CRUZ')
        ->toContain('MARIA LOPEZ REYES')
        ->toContain('PEDRO SANTOS CRUZ')
        ->toContain('<td class="label">Recommended By:</td>')
        ->toContain('<td class="field field--tight">Patricia Loan Processor</td>')
        ->toContain('ANNABELLE M. AMORA')
        ->toContain('<div class="signature-label">Member / Applicant</div>')
        ->toContain('<div class="signature-label">Co-maker 1</div>')
        ->toContain('<div class="signature-label">Co-maker 2</div>')
        ->toContain('<div class="signature-label">Loan Manager / Approved By</div>')
        ->not->toContain('ANNABELLE MONGADO AMORA')
        ->not->toContain('alt="Applicant signature"')
        ->not->toContain('alt="Co-maker 1 signature"')
        ->not->toContain('alt="Co-maker 2 signature"')
        ->not->toContain('alt="Loan manager signature"')
        ->not->toContain('data:image/png;base64,');

    $signatureSection = strstr($html, '<table class="signature-table">');

    expect($signatureSection)->not->toBeFalse();
    expect(substr_count((string) $signatureSection, 'class="signature-line"'))->toBe(4);
});

test('loan request report keeps long signature names on one line with shrink classes', function () {
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::Approved,
    ]);

    $html = view('reports.loan-request', [
        'loanRequest' => $loanRequest,
        'applicant' => [
            'first_name' => 'JULIUS',
            'middle_name' => 'CARLO G.',
            'last_name' => 'DE GRACIA',
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
        ->toContain('white-space: nowrap;')
        ->toContain('word-break: normal;')
        ->toContain('overflow-wrap: normal;')
        ->toContain('.signature-name--tight {')
        ->toContain('.signature-name--tighter {')
        ->toContain('.signature-name--tightest {')
        ->toContain('class="signature-name signature-name--tighter"')
        ->toContain('JULIUS CARLO G. DE GRACIA');
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
        ->toContain('size: 8.5in 13in;')
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

test('blade reports use calibri as the font family by default', function () {
    $branding = app(OrganizationSettingsService::class)->branding();

    $lsaHtml = view('reports.loan-security-agreement', [
        'organization' => ['company_name' => 'Acme Cooperative'],
        'loan' => ['type' => 'SALARY LOAN', 'approved_amount' => '25,000.00', 'approved_date' => 'May 22, 2026', 'approved_term_label' => '12 months'],
        'applicant' => ['full_name' => 'Loan Member', 'address' => 'Sample Address', 'signature_data' => null],
        'reviewer' => ['name' => 'Annabelle M. Amora', 'position' => 'Authorized Representative'],
        'reportHeader' => ['designData' => null],
        'reportTypography' => $branding['reportTypography'],
        'organizationLogoDataUri' => null,
        'placeOfSigning' => 'Sample City',
    ])->render();

    $pnHtml = view('reports.promissory-note', [
        'organization' => ['company_name' => 'Acme Cooperative'],
        'loan' => [],
        'applicant' => [],
        'co_maker_one' => [],
        'co_maker_two' => [],
        'reviewer' => [],
        'reportHeader' => ['designData' => null],
        'reportTypography' => $branding['reportTypography'],
        'organizationLogoDataUri' => null,
    ])->render();

    $ppHtml = view('reports.plan-of-payment', [
        'organization' => ['company_name' => 'Acme Cooperative'],
        'loan' => ['amortization_principal_raw' => null],
        'applicant' => [],
        'reviewer' => [],
        'reportHeader' => ['designData' => null],
        'reportTypography' => $branding['reportTypography'],
        'organizationLogoDataUri' => null,
    ])->render();

    $dsHtml = view('reports.disclosure-statement', [
        'organization' => ['company_name' => 'Acme Cooperative'],
        'loan' => [],
        'applicant' => [],
        'reportHeader' => ['designData' => null],
        'reportTypography' => $branding['reportTypography'],
        'organizationLogoDataUri' => null,
    ])->render();

    expect($lsaHtml)->toContain('font-family: "Calibri", Arial, sans-serif;');
    expect($pnHtml)->toContain('font-family: "Calibri", Arial, sans-serif;');
    expect($ppHtml)->toContain('font-family: "Calibri", Arial, sans-serif;');
    expect($dsHtml)->toContain('font-family: "Calibri", Arial, sans-serif;');

    // The disclosure statement's checkbox brackets are deliberately fixed-width
    // monospace (a real design need, not an oversight) -- confirmed unchanged.
    expect($dsHtml)->toContain('.ds-opt .box { font-family: "Courier New", monospace; }');

    // The workbook grid is fixed-layout so the A-O column proportions from the
    // source Excel sheet are authoritative and nothing wraps or misaligns.
    expect($dsHtml)->toContain('table-layout: fixed;');

    // The statutory reference tags and small labels must never wrap.
    expect($dsHtml)
        ->toContain('nw">( A )</td>')
        ->toContain('nw">( B )</td>')
        ->toContain('nw">( C )</td>')
        ->toContain('nw">( D )</td>')
        ->toContain('nw">( E )</td>')
        ->toContain('nw">(Php)</td>');

    // Row-7/8 labels end at the colon and the value cells open with a
    // non-breaking space, so the printed line always reads
    // "NAME OF BORROWER: <name>", "ADDRESS: <address>", and
    // "LOAN NUMBER: <reference>" with a visible gap in both PDF engines --
    // never a collapsed trailing space. LOAN NUMBER spans L+M so its label
    // never overflows into the reference value.
    expect($dsHtml)
        ->toContain('>NAME OF BORROWER:</td>')
        ->toContain('>ADDRESS:</td>')
        ->toContain('nw" colspan="2">LOAN NUMBER:</td>')
        ->toContain('class="u" colspan="8">&nbsp;</td>')
        ->toContain('class="u" colspan="12">&nbsp;</td>')
        ->toContain('LOAN NUMBER:</td>'."\n".'                <td class="u">&nbsp;</td>');

    // Empty value cells render blank. The old Blade-escaped '&nbsp;' fallbacks
    // emitted "&amp;nbsp;" -- literal text in the PDF -- which must never
    // appear again.
    expect($dsHtml)->not->toContain('&amp;nbsp;');

    // Both signature labels share the workbook's L..N (3-column) span so the
    // lines line up: bookkeeper L51:N51, borrower L58:N58.
    expect($dsHtml)
        ->toContain('c nw" colspan="3">Signature Over Printed Name</td>')
        ->toContain('c nw ut" colspan="3">Signature of Borrower Over Printed Name</td>');
});

test('promissory note addresses render on one line and shrink to fit the column', function (): void {
    $branding = app(OrganizationSettingsService::class)->branding();

    $shortAddress = 'Sample Address';
    $mediumAddress = 'POBLACION, LIANGA, SURIGAO DEL SUR';
    $longAddress = 'POBLACION BARANGAY SAN ISIDRO LIANGA SURIGAO DEL SUR ZONE 4 BLOCK 12';

    // Mirrors the blade's character-width-aware stepped shrink_to_fit
    // approach, replicating the Affidavit of Undertaking method.
    $CHAR_WIDTH = [
        'W' => 0.75, 'M' => 0.75, 'm' => 0.75,
        'A' => 0.63, 'B' => 0.63, 'C' => 0.63, 'D' => 0.63, 'E' => 0.63,
        'F' => 0.63, 'G' => 0.63, 'H' => 0.63, 'I' => 0.38, 'J' => 0.38,
        'K' => 0.63, 'L' => 0.63, 'N' => 0.63, 'O' => 0.63, 'P' => 0.63,
        'Q' => 0.63, 'R' => 0.63, 'S' => 0.63, 'T' => 0.63, 'U' => 0.63,
        'V' => 0.63, 'X' => 0.63, 'Y' => 0.63, 'Z' => 0.63,
        'a' => 0.48, 'b' => 0.48, 'c' => 0.38, 'd' => 0.48, 'e' => 0.48,
        'f' => 0.38, 'g' => 0.48, 'h' => 0.48, 'i' => 0.38, 'j' => 0.38,
        'k' => 0.48, 'l' => 0.38, 'n' => 0.48, 'o' => 0.48, 'p' => 0.48,
        'q' => 0.48, 'r' => 0.38, 's' => 0.38, 't' => 0.38, 'u' => 0.48,
        'v' => 0.48, 'w' => 0.68, 'x' => 0.48, 'y' => 0.48, 'z' => 0.48,
        '0' => 0.52, '1' => 0.52, '2' => 0.52, '3' => 0.52, '4' => 0.52,
        '5' => 0.52, '6' => 0.52, '7' => 0.52, '8' => 0.52, '9' => 0.52,
        ' ' => 0.27,
        ',' => 0.28, '.' => 0.28, ':' => 0.28, ';' => 0.28,
        "'" => 0.28, '"' => 0.28, '!' => 0.28, '?' => 0.28,
        '-' => 0.40, '/' => 0.40, '(' => 0.40, ')' => 0.40,
        '@' => 0.40, '#' => 0.40, '$' => 0.40,
    ];

    $fitSize = static function (string $value) use ($CHAR_WIDTH): string {
        $length = mb_strlen(trim($value));

        if ($length === 0) {
            return '';
        }

        $totalWidthAt1pt = 0.0;
        foreach (mb_str_split($value) as $char) {
            $totalWidthAt1pt += $CHAR_WIDTH[$char] ?? 0.48;
        }

        $availableWidth = 155.0;
        $maxSize = 10.0;
        $minSize = 4.0;

        for ($size = $maxSize; $size >= $minSize; $size -= 0.5) {
            if ($totalWidthAt1pt * $size <= $availableWidth) {
                return 'font-size: '.number_format($size, 1).'pt;';
            }
        }

        return 'font-size: '.number_format($minSize, 1).'pt;';
    };

    $html = view('reports.promissory-note', [
        'organization' => ['company_name' => 'Acme Cooperative'],
        'loan' => [],
        'applicant' => ['address' => $mediumAddress],
        'co_maker_one' => ['address' => $shortAddress],
        'co_maker_two' => ['address' => $longAddress],
        'reviewer' => [],
        'reportHeader' => ['designData' => null],
        'reportTypography' => $branding['reportTypography'],
        'organizationLogoDataUri' => null,
    ])->render();

    expect($html)
        ->toContain('.address-value--fit')
        ->toContain('white-space: nowrap;')
        ->toContain('word-break: normal;')
        ->toContain('overflow: hidden;')
        ->toContain(
            'class="address-value address-value--fit" style="'
            .$fitSize($mediumAddress).'">'.$mediumAddress,
        )
        ->toContain(
            'class="address-value address-value--fit" style="'
            .$fitSize($shortAddress).'">'.$shortAddress,
        )
        ->toContain(
            'class="address-value address-value--fit" style="'
            .$fitSize($longAddress).'">'.$longAddress,
        );

    expect($fitSize($mediumAddress))->toBe('font-size: 8.0pt;');
    expect($fitSize($shortAddress))->toBe('font-size: 10.0pt;');
    expect($fitSize($longAddress))->toBe('font-size: 4.0pt;');
});

test('loan security agreement page size is 8.5in by 13in, not letter', function () {
    $html = view('reports.loan-security-agreement', [
        'organization' => ['company_name' => 'Acme Cooperative'],
        'loan' => ['type' => 'SALARY LOAN', 'approved_amount' => '25,000.00', 'approved_date' => 'May 22, 2026', 'approved_term_label' => '12 months'],
        'applicant' => ['full_name' => 'Loan Member', 'address' => 'Sample Address', 'signature_data' => null],
        'reviewer' => ['name' => 'Annabelle M. Amora', 'position' => 'Authorized Representative'],
        'reportHeader' => ['designData' => null],
        'reportTypography' => [],
        'organizationLogoDataUri' => null,
        'placeOfSigning' => 'Sample City',
    ])->render();

    expect($html)
        ->toContain('size: 8.5in 13in;')
        ->not->toContain('size: 8.5in 11in;');
});

test('application form report resolves calibri as the css custom-property default', function () {
    OrganizationSetting::factory()->create(['company_name' => 'Acme Corp']);
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

    expect($html)
        ->toContain('--report-font-value-family: "Calibri", sans-serif;')
        ->toContain('--report-font-label-family: "Calibri", sans-serif;');
});

test('blade reports embed the real calibri font program as a standalone style tag, not nested', function () {
    $branding = app(OrganizationSettingsService::class)->branding();

    expect($branding['reportTypography']['fontFaceCss'])
        ->not->toBeNull()
        ->toContain("font-family: 'Calibri'")
        ->not->toContain('<style>');

    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);

    $afHtml = view('reports.loan-request', [
        'loanRequest' => $loanRequest,
        'applicant' => [],
        'coMakerOne' => [],
        'coMakerTwo' => [],
        'companyName' => $branding['companyName'],
        'reportHeader' => $branding['reportHeader'],
        'reportTypography' => $branding['reportTypography'],
        'generatedAt' => Carbon::now(),
    ])->render();

    // The font-face embed is included INSIDE loan-request's existing <style>
    // block (via the report-typography partial) -- it must never bring its own
    // <style> wrapper here, or Chromium silently drops the whole block and
    // falls back to a generic serif font (regressed once, see git history).
    expect($afHtml)->toContain('@font-face')
        ->not->toContain('<style><style>');

    $lsaHtml = view('reports.loan-security-agreement', [
        'organization' => ['company_name' => 'Acme Cooperative'],
        'loan' => ['type' => 'SALARY LOAN', 'approved_amount' => '25,000.00', 'approved_date' => 'May 22, 2026', 'approved_term_label' => '12 months'],
        'applicant' => ['full_name' => 'Loan Member', 'address' => 'Sample Address', 'signature_data' => null],
        'reviewer' => ['name' => 'Annabelle M. Amora', 'position' => 'Authorized Representative'],
        'reportHeader' => ['designData' => null],
        'reportTypography' => $branding['reportTypography'],
        'organizationLogoDataUri' => null,
        'placeOfSigning' => 'Sample City',
    ])->render();

    // LSA embeds the font-face as a sibling <style> tag in <head>, since it
    // does not include the report-typography partial -- it DOES need its own
    // wrapper here.
    expect($lsaHtml)->toContain('<style>@font-face');
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
