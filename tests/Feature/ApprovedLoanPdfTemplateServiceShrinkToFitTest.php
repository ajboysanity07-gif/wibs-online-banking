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
 * size the renderer actually chose, without reaching into private internals. This takes
 * the *last* "Tf" in the byte stream, not the smallest overall, on the assumption the
 * field map under test's own stamped value is the only (or last) text in the file.
 *
 * That assumption breaks if the base template itself bakes in substantial real text at
 * varying sizes (its own "Tf" operators sit in the same raw file bytes, in an XObject
 * whose physical byte position isn't guaranteed to precede the overlay's) -- this is why
 * these tests deliberately use loan-security-agreement.pdf (still a near-blank
 * placeholder) rather than a template with real multi-size artwork text baked in. Confirmed
 * by direct investigation: the now-removed Authorization document's template had exactly
 * this problem, picking up 8.0 (a size baked into its own artwork) instead of the field
 * map's own declared/shrunk size when swapped in here.
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
        'loan-security-agreement.pdf',
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
        'loan-security-agreement.pdf',
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
        'loan-security-agreement.pdf',
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
        'loan-security-agreement.pdf',
        ['sample' => ['text' => 'Some text']],
        $fieldMap,
    ))->toThrow(RuntimeException::class, 'shrink_to_fit but no width');
});
