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
