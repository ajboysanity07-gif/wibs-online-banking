<?php

use App\Services\LoanRequests\ApprovedLoanPdfTemplateService;
use App\Services\LoanRequests\PdfFieldMaps\ApprovedLoanPdfFieldMap;

function approvedLoanPdfTemplateServiceBoldFieldMap(array $field): ApprovedLoanPdfFieldMap
{
    return new class($field) implements ApprovedLoanPdfFieldMap
    {
        public function __construct(private array $field) {}

        public function fields(): array
        {
            return [$this->field];
        }
    };
}

test('bold style field renders using the registered bold calibri font instead of dying', function () {
    $fieldMap = approvedLoanPdfTemplateServiceBoldFieldMap([
        'page' => 1,
        'x' => 10,
        'y' => 10,
        'size' => 11,
        'style' => 'B',
        'value' => 'sample.text',
    ]);

    $service = app(ApprovedLoanPdfTemplateService::class);

    $content = $service->renderContent(
        'affidavit-undertaking.pdf',
        ['sample' => ['text' => 'Sample Bold Text']],
        $fieldMap,
    );

    expect($content)->toStartWith('%PDF')
        ->toContain('Calibri-Bold');
});

test('regular style field still renders fine now that bold is also registered', function () {
    $fieldMap = approvedLoanPdfTemplateServiceBoldFieldMap([
        'page' => 1,
        'x' => 10,
        'y' => 10,
        'size' => 11,
        'value' => 'sample.text',
    ]);

    $service = app(ApprovedLoanPdfTemplateService::class);

    $content = $service->renderContent(
        'affidavit-undertaking.pdf',
        ['sample' => ['text' => 'Sample Regular Text']],
        $fieldMap,
    );

    expect($content)->toStartWith('%PDF')->toContain('Calibri');
});

test('a style with no registered font definition throws a catchable exception instead of dying', function () {
    $fieldMap = approvedLoanPdfTemplateServiceBoldFieldMap([
        'page' => 1,
        'x' => 10,
        'y' => 10,
        'size' => 11,
        'style' => 'I',
        'value' => 'sample.text',
    ]);

    $service = app(ApprovedLoanPdfTemplateService::class);

    expect(fn () => $service->renderContent(
        'affidavit-undertaking.pdf',
        ['sample' => ['text' => 'Sample Italic Text']],
        $fieldMap,
    ))->toThrow(RuntimeException::class, 'Missing PDF font definition');
});
