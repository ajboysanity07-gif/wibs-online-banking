<?php

use App\LoanRequestStatus;
use App\Models\LoanRequest;
use App\Models\OrganizationSetting;
use App\Services\OrganizationSettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

test('loan request report hides company name for full logo preset', function () {
    OrganizationSetting::factory()->create([
        'company_name' => 'Acme Cooperative',
        'logo_preset' => OrganizationSettingsService::LOGO_PRESET_FULL,
    ]);

    $branding = app(OrganizationSettingsService::class)->branding();
    $showCompanyName = ! ($branding['logoIsWordmark'] ?? false);
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);

    $html = view('reports.loan-request', [
        'loanRequest' => $loanRequest,
        'applicant' => [],
        'coMakerOne' => [],
        'coMakerTwo' => [],
        'companyName' => $branding['companyName'],
        'logoData' => 'data:image/png;base64,logo',
        'showCompanyName' => $showCompanyName,
        'generatedAt' => Carbon::now(),
    ])->render();

    expect($showCompanyName)->toBeFalse();
    expect($html)->not->toContain($branding['companyName']);
});

test('loan payments report hides company name for full logo preset', function () {
    OrganizationSetting::factory()->create([
        'company_name' => 'Acme Cooperative',
        'logo_preset' => OrganizationSettingsService::LOGO_PRESET_FULL,
    ]);

    $branding = app(OrganizationSettingsService::class)->branding();
    $showCompanyName = ! ($branding['logoIsWordmark'] ?? false);

    $html = view('reports.loan-payments', [
        'logoData' => 'data:image/png;base64,logo',
        'companyName' => $branding['companyName'],
        'showCompanyName' => $showCompanyName,
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

    expect($showCompanyName)->toBeFalse();
    expect($html)->not->toContain($branding['companyName']);
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

test('loan request report shows company name for mark logo preset', function () {
    OrganizationSetting::factory()->create([
        'company_name' => 'Acme Cooperative',
        'logo_preset' => OrganizationSettingsService::LOGO_PRESET_MARK,
    ]);

    $branding = app(OrganizationSettingsService::class)->branding();
    $showCompanyName = ! ($branding['logoIsWordmark'] ?? false);
    $loanRequest = LoanRequest::factory()->create([
        'status' => LoanRequestStatus::UnderReview,
    ]);

    $html = view('reports.loan-request', [
        'loanRequest' => $loanRequest,
        'applicant' => [],
        'coMakerOne' => [],
        'coMakerTwo' => [],
        'companyName' => $branding['companyName'],
        'logoData' => 'data:image/png;base64,logo',
        'showCompanyName' => $showCompanyName,
        'generatedAt' => Carbon::now(),
    ])->render();

    expect($showCompanyName)->toBeTrue();
    expect($html)->toContain($branding['companyName']);
});
