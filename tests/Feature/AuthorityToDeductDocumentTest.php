<?php

use App\Services\LoanRequests\AuthorityToDeductPdfService;
use Illuminate\Support\Facades\File;

/**
 * @return array<string, mixed>
 */
function authorityToDeductDocumentData(array $authorityToDeduct = []): array
{
    return [
        'organization' => [
            'company_name' => 'MRDI',
            'report_header' => ['designData' => null],
            'report_typography' => [],
            'logo_data_uri' => null,
        ],
        'applicant' => [
            'full_name' => 'Juan Dela Cruz',
        ],
        'loan' => [
            'amortization_total' => '2,500.00',
            'amortization_total_words' => 'TWO THOUSAND FIVE HUNDRED PESOS ONLY.',
            'deduction_start_date' => 'August 15, 2026',
            'approved_date' => '2026-07-28',
        ],
        'notarial' => [
            'signing_place' => 'Lianga, Surigao del Sur',
        ],
        'authority_to_deduct' => $authorityToDeduct,
    ];
}

/**
 * @return array<string, mixed>
 */
function authorityToDeductBuildViewData(array $authorityToDeduct = []): array
{
    $service = app(AuthorityToDeductPdfService::class);

    return (new ReflectionMethod($service, 'buildViewData'))->invoke(
        $service,
        authorityToDeductDocumentData($authorityToDeduct),
    );
}

it('uses the manually entered institution name and officers', function () {
    $viewData = authorityToDeductBuildViewData([
        'institution_name' => 'Lianga District Hospital',
        'officer_1_name' => 'Cristy S. Samarah',
        'officer_1_title' => 'Administrative 1/Cashier',
    ]);

    expect($viewData['institution']['name'])->toBe('Lianga District Hospital');
    expect($viewData['institution']['officers'])->toBe([
        ['name' => 'Cristy S. Samarah', 'title' => 'Administrative 1/Cashier'],
    ]);
});

it('supports a second signing officer', function () {
    $viewData = authorityToDeductBuildViewData([
        'institution_name' => 'Barangay Banahao',
        'officer_1_name' => 'Eva T. Cabuñas',
        'officer_1_title' => 'Barangay Treasurer',
        'officer_2_name' => 'Zacarias D. Pedrozo',
        'officer_2_title' => 'Barangay Captain',
    ]);

    expect(collect($viewData['institution']['officers'])->pluck('name')->all())
        ->toBe(['Eva T. Cabuñas', 'Zacarias D. Pedrozo']);
});

it('defaults a missing officer title to "Authorized Representative"', function () {
    $viewData = authorityToDeductBuildViewData([
        'institution_name' => 'LGU - Lianga',
        'officer_1_name' => 'Asteria P. Cesar',
    ]);

    expect($viewData['institution']['officers'])->toBe([
        ['name' => 'Asteria P. Cesar', 'title' => 'Authorized Representative'],
    ]);
});

it('skips officer 2 when only officer 1 is filled in', function () {
    $viewData = authorityToDeductBuildViewData([
        'institution_name' => 'MRDInc-Payroll',
        'officer_1_name' => 'Juliet S. Sarausad',
        'officer_1_title' => 'MRDI-Payroll Maker',
        'officer_2_title' => 'Left blank on purpose',
    ]);

    expect($viewData['institution']['officers'])->toBe([
        ['name' => 'Juliet S. Sarausad', 'title' => 'MRDI-Payroll Maker'],
    ]);
});

it('falls back to a blank institution when the fields are not filled in', function () {
    $viewData = authorityToDeductBuildViewData();

    expect($viewData['institution']['name'])->toBeNull();
    expect($viewData['institution']['officers'])->toBe([]);
});

it('renders institution and officer names in the document body when filled in', function () {
    $viewData = authorityToDeductBuildViewData([
        'institution_name' => 'Lianga District Hospital',
        'officer_1_name' => 'Cristy S. Samarah',
        'officer_1_title' => 'Administrative 1/Cashier',
    ]);

    $html = view('reports.authority-to-deduct', $viewData)->render();

    expect($html)->toContain('Lianga District Hospital');
    expect($html)->toContain('Cristy S. Samarah');
});

it('renders blank underline fields instead of institution text when not filled in', function () {
    $viewData = authorityToDeductBuildViewData();

    $html = view('reports.authority-to-deduct', $viewData)->render();

    expect($html)->not->toContain('Cristy S. Samarah');
    expect($html)->not->toContain('Eva T. Cabuñas');
    expect($html)->not->toContain('Juliet S. Sarausad');
    expect($html)->toContain('agreement-blank');
});

it('renders the periodic deduction amount, words, and start date when available', function () {
    $viewData = authorityToDeductBuildViewData([
        'institution_name' => 'Lianga District Hospital',
        'officer_1_name' => 'Cristy S. Samarah',
        'officer_1_title' => 'Administrative 1/Cashier',
    ]);

    $html = view('reports.authority-to-deduct', $viewData)->render();

    expect($html)->toContain('2,500.00');
    expect($html)->toContain('TWO THOUSAND FIVE HUNDRED PESOS ONLY');
    expect($html)->toContain('August 15, 2026');
});

it('leaves the start date blank until the release date is known', function () {
    $service = app(AuthorityToDeductPdfService::class);
    $documentData = authorityToDeductDocumentData([
        'institution_name' => 'Lianga District Hospital',
        'officer_1_name' => 'Cristy S. Samarah',
        'officer_1_title' => 'Administrative 1/Cashier',
    ]);
    $documentData['loan']['deduction_start_date'] = null;

    $viewData = (new ReflectionMethod($service, 'buildViewData'))->invoke($service, $documentData);

    $html = view('reports.authority-to-deduct', $viewData)->render();

    expect($html)->not->toContain('August 15, 2026');
    expect($html)->toContain('agreement-blank');
});

it('generates a real PDF file end to end', function () {
    config()->set('reports.pdf_driver', 'dompdf');

    $service = app(AuthorityToDeductPdfService::class);
    $outputPath = storage_path('app/testing/authority-to-deduct-smoke.pdf');
    File::ensureDirectoryExists(dirname($outputPath));

    try {
        $service->generate($outputPath, authorityToDeductDocumentData([
            'institution_name' => 'Barangay Banahao',
            'officer_1_name' => 'Eva T. Cabuñas',
            'officer_1_title' => 'Barangay Treasurer',
            'officer_2_name' => 'Zacarias D. Pedrozo',
            'officer_2_title' => 'Barangay Captain',
        ]));

        expect(File::exists($outputPath))->toBeTrue();
        expect(substr(File::get($outputPath), 0, 5))->toBe('%PDF-');
    } finally {
        File::delete($outputPath);
    }
});
