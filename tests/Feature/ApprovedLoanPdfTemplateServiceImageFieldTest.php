<?php

use App\Services\LoanRequests\ApprovedLoanPdfTemplateService;
use App\Services\LoanRequests\PdfFieldMaps\ApprovedLoanPdfFieldMap;
use Illuminate\Support\Facades\Storage;

function approvedLoanPdfTemplateServiceImageFieldMap(array $field): ApprovedLoanPdfFieldMap
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

test('image type field renders image data into the generated pdf', function () {
    Storage::fake('public');
    Storage::disk('public')->put(
        'test-assets/image-field.png',
        testPngSignatureBinary('one'),
    );

    $fieldMap = approvedLoanPdfTemplateServiceImageFieldMap([
        'page' => 1,
        'type' => 'image',
        'x' => 10,
        'y' => 10,
        'width' => 30,
        'height' => 15,
        'value' => 'header.image_path',
    ]);

    $service = app(ApprovedLoanPdfTemplateService::class);

    $content = $service->renderContent(
        'authorization.pdf',
        ['header' => ['image_path' => 'test-assets/image-field.png']],
        $fieldMap,
    );

    expect($content)->toStartWith('%PDF')
        ->toContain('/Subtype /Image');
});

test('image type field with an unresolvable path renders no image and does not error', function () {
    Storage::fake('public');

    $fieldMap = approvedLoanPdfTemplateServiceImageFieldMap([
        'page' => 1,
        'type' => 'image',
        'x' => 10,
        'y' => 10,
        'width' => 30,
        'height' => 15,
        'value' => 'header.image_path',
    ]);

    $service = app(ApprovedLoanPdfTemplateService::class);

    $content = $service->renderContent(
        'authorization.pdf',
        ['header' => ['image_path' => null]],
        $fieldMap,
    );

    expect($content)->toStartWith('%PDF')
        ->not->toContain('/Subtype /Image');
});
