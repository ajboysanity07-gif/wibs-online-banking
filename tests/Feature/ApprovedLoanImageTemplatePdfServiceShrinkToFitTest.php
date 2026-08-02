<?php

use App\Services\LoanRequests\ApprovedLoanImageTemplatePdfService;
use App\Services\LoanRequests\PdfFieldMaps\ApprovedLoanPdfFieldMap;

function approvedLoanImageTemplateServiceShrinkFieldMap(array $field): ApprovedLoanPdfFieldMap
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

/**
 * makePdf() disables PDF stream compression (SetCompression(false)), so the font-size
 * operator ("<size> Tf") is readable directly in the raw returned bytes without needing
 * to inflate any content stream -- used here as a cheap, direct way to observe which font
 * size the renderer actually chose, without reaching into private internals. This takes
 * the *last* "Tf" in the byte stream: the image template is a raster PNG (no baked-in
 * text operators), so the overlay field's own stamped value is the only text in the file.
 */
function approvedLoanImageTemplateServiceLastFontSize(string $pdfContent): ?float
{
    if (preg_match_all('/(\d+(?:\.\d+)?)\s+Tf\b/', $pdfContent, $matches) === 0) {
        return null;
    }

    return (float) end($matches[1]);
}

function approvedLoanImageTemplateServiceShrinkPages(): array
{
    return [
        ['image' => 'grepalife-page-1.png', 'width' => 216.0, 'height' => 279.0],
    ];
}

test('image template shrink to fit renders at the declared size when the text already fits the width', function () {
    $fieldMap = approvedLoanImageTemplateServiceShrinkFieldMap([
        'page' => 1,
        'x' => 10,
        'y' => 10,
        'size' => 11,
        'style' => 'B',
        'width' => 60,
        'shrink_to_fit' => true,
        'min_size' => 6.0,
        'value' => 'sample.text',
    ]);

    $service = app(ApprovedLoanImageTemplatePdfService::class);

    $content = $service->renderContent(
        approvedLoanImageTemplateServiceShrinkPages(),
        ['sample' => ['text' => 'Short']],
        $fieldMap,
    );

    expect($content)->toStartWith('%PDF');
    expect(approvedLoanImageTemplateServiceLastFontSize($content))->toBe(11.0);
});

test('image template shrink to fit reduces font size until a long value fits its declared width', function () {
    $fieldMap = approvedLoanImageTemplateServiceShrinkFieldMap([
        'page' => 1,
        'x' => 10,
        'y' => 10,
        'size' => 11,
        'style' => 'B',
        'width' => 30,
        'shrink_to_fit' => true,
        'min_size' => 6.0,
        'value' => 'sample.text',
    ]);

    $service = app(ApprovedLoanImageTemplatePdfService::class);

    $content = $service->renderContent(
        approvedLoanImageTemplateServiceShrinkPages(),
        ['sample' => ['text' => 'Maria Concepcion Villanueva-Fernandez de la Santisima Trinidad']],
        $fieldMap,
    );

    $size = approvedLoanImageTemplateServiceLastFontSize($content);

    expect($size)->not->toBeNull();
    expect($size)->toBeLessThan(11.0);
    expect($size)->toBeGreaterThanOrEqual(6.0);
});

test('image template shrink to fit never renders below the configured minimum size, even for an extreme value', function () {
    $fieldMap = approvedLoanImageTemplateServiceShrinkFieldMap([
        'page' => 1,
        'x' => 10,
        'y' => 10,
        'size' => 11,
        'style' => 'B',
        'width' => 15,
        'shrink_to_fit' => true,
        'min_size' => 6.0,
        'value' => 'sample.text',
    ]);

    $service = app(ApprovedLoanImageTemplatePdfService::class);

    $content = $service->renderContent(
        approvedLoanImageTemplateServiceShrinkPages(),
        ['sample' => ['text' => str_repeat('Extraordinarily Long Value ', 5)]],
        $fieldMap,
    );

    expect(approvedLoanImageTemplateServiceLastFontSize($content))->toBe(6.0);
});

test('image template shrink to fit without a width throws a catchable exception instead of silently ignoring it', function () {
    $fieldMap = approvedLoanImageTemplateServiceShrinkFieldMap([
        'page' => 1,
        'x' => 10,
        'y' => 10,
        'size' => 11,
        'style' => 'B',
        'shrink_to_fit' => true,
        'value' => 'sample.text',
    ]);

    $service = app(ApprovedLoanImageTemplatePdfService::class);

    expect(fn () => $service->renderContent(
        approvedLoanImageTemplateServiceShrinkPages(),
        ['sample' => ['text' => 'Some text']],
        $fieldMap,
    ))->toThrow(RuntimeException::class, 'shrink_to_fit but no width');
});
