<?php

use App\Services\LoanRequests\ApprovedLoanPdfTemplateService;
use App\Services\LoanRequests\PdfFieldMaps\ApprovedLoanPdfFieldMap;

function approvedLoanPdfTemplateServiceShrinkFieldMap(array $field): ApprovedLoanPdfFieldMap
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
 * size the renderer actually chose, without reaching into private internals. The base
 * template PDF has its own baked-in text at various sizes, so this takes the *last* "Tf" in
 * the byte stream, not the smallest overall -- the field map under test renders on top of
 * (i.e. after, in stream order) the imported template page.
 */
function approvedLoanPdfTemplateServiceLastFontSize(string $pdfContent): ?float
{
    if (preg_match_all('/(\d+(?:\.\d+)?)\s+Tf\b/', $pdfContent, $matches) === 0) {
        return null;
    }

    return (float) end($matches[1]);
}

test('shrink to fit renders at the declared size when the text already fits the width', function () {
    $fieldMap = approvedLoanPdfTemplateServiceShrinkFieldMap([
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

    $service = app(ApprovedLoanPdfTemplateService::class);

    $content = $service->renderContent(
        'authorization.pdf',
        ['sample' => ['text' => 'Short']],
        $fieldMap,
    );

    expect($content)->toStartWith('%PDF');
    expect(approvedLoanPdfTemplateServiceLastFontSize($content))->toBe(11.0);
});

test('shrink to fit reduces font size until a long value fits its declared width', function () {
    $fieldMap = approvedLoanPdfTemplateServiceShrinkFieldMap([
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

    $service = app(ApprovedLoanPdfTemplateService::class);

    $content = $service->renderContent(
        'authorization.pdf',
        ['sample' => ['text' => 'Maria Concepcion Villanueva-Fernandez de la Santisima Trinidad']],
        $fieldMap,
    );

    $size = approvedLoanPdfTemplateServiceLastFontSize($content);

    expect($size)->not->toBeNull();
    expect($size)->toBeLessThan(11.0);
    expect($size)->toBeGreaterThanOrEqual(6.0);
});

test('shrink to fit never renders below the configured minimum size, even for an extreme value', function () {
    $fieldMap = approvedLoanPdfTemplateServiceShrinkFieldMap([
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

    $service = app(ApprovedLoanPdfTemplateService::class);

    $content = $service->renderContent(
        'authorization.pdf',
        ['sample' => ['text' => str_repeat('Extraordinarily Long Value ', 5)]],
        $fieldMap,
    );

    expect(approvedLoanPdfTemplateServiceLastFontSize($content))->toBe(6.0);
});

test('shrink to fit without a width throws a catchable exception instead of silently ignoring it', function () {
    $fieldMap = approvedLoanPdfTemplateServiceShrinkFieldMap([
        'page' => 1,
        'x' => 10,
        'y' => 10,
        'size' => 11,
        'style' => 'B',
        'shrink_to_fit' => true,
        'value' => 'sample.text',
    ]);

    $service = app(ApprovedLoanPdfTemplateService::class);

    expect(fn () => $service->renderContent(
        'authorization.pdf',
        ['sample' => ['text' => 'Some text']],
        $fieldMap,
    ))->toThrow(RuntimeException::class, 'shrink_to_fit but no width');
});
