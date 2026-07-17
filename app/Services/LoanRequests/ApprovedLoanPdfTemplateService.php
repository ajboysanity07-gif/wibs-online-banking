<?php

namespace App\Services\LoanRequests;

use App\Services\LoanRequests\PdfFieldMaps\ApprovedLoanPdfFieldMap;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use setasign\Fpdi\Tcpdf\Fpdi;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApprovedLoanPdfTemplateService
{
    private const TEMPLATE_DIRECTORY = 'templates/approved-loan-documents/pdf';

    private const CUSTOM_FONT_DIRECTORY = 'fonts/tcpdf';

    /**
     * TCPDF's core "helvetica" font uses a non-Unicode encoding that silently
     * renders the Philippine Peso sign (₱, U+20B1) as "?" -- Calibri (converted
     * from the system TTF, see storage/app/fonts/tcpdf/) renders it correctly
     * and is used as the default text font across all approved-loan PDF
     * documents, unless a field explicitly overrides 'font'.
     */
    private const DEFAULT_FONT = 'calibri';

    public function __construct(
        private DocumentSignaturePlacement $signaturePlacement,
    ) {}

    /**
     * @param  array<string, mixed>  $documentData
     */
    public function generate(
        string $templateFilename,
        string $outputPath,
        array $documentData,
        ApprovedLoanPdfFieldMap $fieldMap,
    ): void {
        File::ensureDirectoryExists(dirname($outputPath));
        File::put(
            $outputPath,
            $this->renderContent($templateFilename, $documentData, $fieldMap),
        );
    }

    /**
     * @param  array<string, mixed>  $documentData
     */
    public function renderContent(
        string $templateFilename,
        array $documentData,
        ApprovedLoanPdfFieldMap $fieldMap,
    ): string {
        $fieldsByPage = $this->groupFieldsByPage($fieldMap);

        return $this->renderTemplateBytes(
            $templateFilename,
            function (Fpdi $pdf, int $pageNumber) use (
                $documentData,
                $fieldsByPage,
            ): void {
                foreach ($fieldsByPage[$pageNumber] ?? [] as $field) {
                    $this->renderField($pdf, $field, $documentData);
                }
            },
        );
    }

    /**
     * @param  array<string, mixed>  $documentData
     */
    public function renderResponse(
        string $templateFilename,
        string $filename,
        array $documentData,
        ApprovedLoanPdfFieldMap $fieldMap,
        string $disposition = 'attachment',
    ): Response {
        $fieldsByPage = $this->groupFieldsByPage($fieldMap);

        return response(
            $this->renderTemplateBytes(
                $templateFilename,
                function (Fpdi $pdf, int $pageNumber) use (
                    $documentData,
                    $fieldsByPage,
                ): void {
                    foreach ($fieldsByPage[$pageNumber] ?? [] as $field) {
                        $this->renderField($pdf, $field, $documentData);
                    }
                },
            ),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf(
                    '%s; filename="%s"',
                    $disposition,
                    $filename,
                ),
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ],
        );
    }

    /**
     * @param  callable(Fpdi, int): void  $overlay
     */
    private function renderTemplateBytes(
        string $templateFilename,
        callable $overlay,
    ): string {
        $templatePath = $this->resolveTemplatePath($templateFilename);
        $pdf = $this->makePdf();

        try {
            $pageCount = $pdf->setSourceFile($templatePath);

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $pageSize = $pdf->getTemplateSize($templateId);
                $orientation =
                    ($pageSize['width'] ?? 0) > ($pageSize['height'] ?? 0)
                        ? 'L'
                        : 'P';

                $pdf->AddPage($orientation, [
                    $pageSize['width'],
                    $pageSize['height'],
                ]);
                $pdf->useTemplate($templateId);
                $overlay($pdf, $pageNumber);
            }

            return $pdf->Output('', 'S');
        } catch (Throwable $exception) {
            Log::error('Failed generating approved loan PDF template.', [
                'template_filename' => $templateFilename,
                'template_path' => $templatePath,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function groupFieldsByPage(ApprovedLoanPdfFieldMap $fieldMap): array
    {
        $fieldsByPage = [];

        foreach ($fieldMap->fields() as $field) {
            $pageNumber = (int) ($field['page'] ?? 1);
            $fieldsByPage[$pageNumber][] = $field;
        }

        return $fieldsByPage;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $documentData
     */
    private function renderField(Fpdi $pdf, array $field, array $documentData): void
    {
        $type = (string) ($field['type'] ?? 'text');

        if ($type === 'check') {
            $this->renderCheckField($pdf, $field, $documentData);

            return;
        }

        if ($type === 'image') {
            $this->renderImageField($pdf, $field, $documentData);

            return;
        }

        $value = $this->resolveValue($field['value'] ?? null, $documentData);
        $text = $this->blank(is_scalar($value) ? (string) $value : null);

        if ($text === '') {
            return;
        }

        $x = (float) ($field['x'] ?? 0);
        $y = (float) ($field['y'] ?? 0);
        $width = $field['width'] ?? null;
        $lineHeight = (float) ($field['line_height'] ?? 4);
        $align = (string) ($field['align'] ?? 'L');

        if ($align !== 'L' && ! is_numeric($width)) {
            throw new RuntimeException(sprintf(
                'Field "%s" sets align "%s" but no width; alignment only takes effect via MultiCell, which needs a width to align within.',
                (string) ($field['value'] ?? '?'),
                $align,
            ));
        }

        if ((bool) ($field['shrink_to_fit'] ?? false)) {
            if (! is_numeric($width)) {
                throw new RuntimeException(sprintf(
                    'Field "%s" sets shrink_to_fit but no width; shrink-to-fit measures the text against a width to decide how far to shrink.',
                    (string) ($field['value'] ?? '?'),
                ));
            }

            $this->writeShrinkToFitText(
                $pdf,
                $x,
                $y,
                $text,
                (int) ($field['size'] ?? 7),
                (string) ($field['font'] ?? self::DEFAULT_FONT),
                (string) ($field['style'] ?? ''),
                (float) $width,
                $align === '' ? 'L' : $align,
                (float) ($field['min_size'] ?? 6.0),
            );

            return;
        }

        if (is_numeric($width)) {
            $this->writeText(
                $pdf,
                $x,
                $y,
                $text,
                (int) ($field['size'] ?? 7),
                (string) ($field['font'] ?? self::DEFAULT_FONT),
                (string) ($field['style'] ?? ''),
                (float) $width,
                $lineHeight,
                $align,
            );

            return;
        }

        $this->writeText(
            $pdf,
            $x,
            $y,
            $text,
            (int) ($field['size'] ?? 7),
            (string) ($field['font'] ?? self::DEFAULT_FONT),
            (string) ($field['style'] ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $documentData
     */
    private function renderCheckField(Fpdi $pdf, array $field, array $documentData): void
    {
        $checked = (bool) $this->resolveValue($field['value'] ?? null, $documentData);

        if (! $checked) {
            return;
        }

        $this->writeCheck(
            $pdf,
            (float) ($field['x'] ?? 0),
            (float) ($field['y'] ?? 0),
            (int) ($field['size'] ?? 8),
        );
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $documentData
     */
    private function renderImageField(Fpdi $pdf, array $field, array $documentData): void
    {
        $relativePath = $this->resolveValue($field['value'] ?? null, $documentData);

        $this->writeImage(
            $pdf,
            (float) ($field['x'] ?? 0),
            (float) ($field['y'] ?? 0),
            (float) ($field['width'] ?? 0),
            (float) ($field['height'] ?? 0),
            is_string($relativePath) ? $relativePath : null,
            $this->signaturePlacementOptions($field),
        );
    }

    public function writeText(
        Fpdi $pdf,
        float $x,
        float $y,
        ?string $text,
        int $size = 7,
        string $font = self::DEFAULT_FONT,
        string $style = '',
        ?float $width = null,
        float $lineHeight = 4,
        string $align = 'L',
    ): void {
        $text = $this->blank($text);

        if ($text === '') {
            return;
        }

        $normalizedFont = $this->normalizeFontName($font);
        $this->assertFontStyleAvailable($normalizedFont, $style);

        $pdf->SetFont($normalizedFont, $style, $size);
        $pdf->SetTextColor(0, 0, 0);

        if ($width !== null) {
            $pdf->MultiCell(
                $width,
                $lineHeight,
                $text,
                0,
                $align,
                false,
                1,
                $x,
                $y,
                true,
                0,
                false,
                true,
                0,
                'T',
                false,
            );

            return;
        }

        $pdf->Text($x, $y, $text);
    }

    /**
     * Single-line "shrink to fit" text: unlike writeText()'s MultiCell path, this never
     * wraps to a second line -- it measures the text at $maxSize and steps the font size
     * down (in 0.5pt increments, never below $minSize) until it fits $width, then draws it
     * once via Cell() at whatever size fit. Intended for values whose natural length varies
     * a lot per record (a person's full name, a peso amount, a composed address) where
     * wrapping would look broken (a name split mid-word) or isn't physically possible
     * (an inline blank inside a sentence).
     */
    public function writeShrinkToFitText(
        Fpdi $pdf,
        float $x,
        float $y,
        ?string $text,
        int $maxSize,
        string $font = self::DEFAULT_FONT,
        string $style = '',
        float $width = 0,
        string $align = 'L',
        float $minSize = 6.0,
    ): void {
        $text = $this->blank($text);

        if ($text === '') {
            return;
        }

        $normalizedFont = $this->normalizeFontName($font);
        $this->assertFontStyleAvailable($normalizedFont, $style);

        $size = $this->resolveShrinkToFitSize(
            $pdf,
            $text,
            $normalizedFont,
            $style,
            (float) $maxSize,
            $width,
            $minSize,
        );

        $pdf->SetFont($normalizedFont, $style, $size);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($x, $y);
        $pdf->Cell($width, $size * 0.3528 * 1.4, $text, 0, 0, $align, false, '', 0, false, 'T', 'T');
    }

    /**
     * Steps font size down from $maxSize to $minSize (0.5pt increments) until
     * GetStringWidth() fits within $width, minus a small safety margin for Cell()'s own
     * internal cMargin padding (not reflected in GetStringWidth() itself). Never returns
     * below $minSize -- a value that still doesn't fit at the floor size renders at that
     * floor and is left to overflow slightly, rather than shrinking to unreadable size.
     */
    private function resolveShrinkToFitSize(
        Fpdi $pdf,
        string $text,
        string $font,
        string $style,
        float $maxSize,
        float $width,
        float $minSize,
    ): float {
        $availableWidth = max($width - 2.0, 1.0);

        for ($size = $maxSize; $size > $minSize; $size -= 0.5) {
            $pdf->SetFont($font, $style, $size);

            if ($pdf->GetStringWidth($text) <= $availableWidth) {
                return $size;
            }
        }

        return $minSize;
    }

    public function writeCheck(Fpdi $pdf, float $x, float $y, int $size = 8): void
    {
        $pdf->SetFont('zapfdingbats', '', $size);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Text($x, $y, '4');
    }

    public function writeImage(
        Fpdi $pdf,
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $imagePath,
        array $placementOptions = [],
    ): void {
        if ($imagePath === null || trim($imagePath) === '') {
            return;
        }

        $absolutePath = $this->resolveSignaturePath($imagePath);

        if ($absolutePath === null) {
            return;
        }

        $dimensions = $this->fitImageToBox(
            $absolutePath,
            $x,
            $y,
            $width,
            $height,
            $placementOptions,
        );

        $pdf->Image(
            $absolutePath,
            $dimensions['x'],
            $dimensions['y'],
            $dimensions['width'],
            $dimensions['height'],
            '',
            '',
            '',
            false,
            300,
            '',
            false,
            false,
            0,
            false,
            false,
            false,
        );
    }

    public function blank(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : '';
    }

    private function resolveSignaturePath(string $relativePath): ?string
    {
        if (Storage::disk('public')->exists($relativePath)) {
            return Storage::disk('public')->path($relativePath);
        }

        $absolutePath = storage_path('app/public/'.$relativePath);

        return is_file($absolutePath) ? $absolutePath : null;
    }

    private function resolveTemplatePath(string $templateFilename): string
    {
        $templatePath = storage_path(
            'app/'.trim(self::TEMPLATE_DIRECTORY.'/'.$templateFilename, '/'),
        );

        if (! is_file($templatePath)) {
            Log::error('Missing approved loan PDF template file.', [
                'template_filename' => $templateFilename,
                'template_path' => $templatePath,
            ]);

            throw new RuntimeException(
                'Missing PDF template file: '.$templateFilename,
            );
        }

        return $templatePath;
    }

    /**
     * @return array{x: float, y: float, width: float, height: float}
     */
    private function fitImageToBox(
        string $absolutePath,
        float $x,
        float $y,
        float $width,
        float $height,
        array $placementOptions = [],
    ): array {
        return $this->signaturePlacement->calculateFromImagePath(
            $absolutePath,
            $x,
            $y,
            $width,
            $height,
            $placementOptions,
        );
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array{
     *     scale?: float|int|null,
     *     max_width?: float|int|null,
     *     max_height?: float|int|null,
     *     offset_x?: float|int|null,
     *     offset_y?: float|int|null
     * }
     */
    private function signaturePlacementOptions(array $field): array
    {
        return array_filter([
            'scale' => $field['scale'] ?? null,
            'max_width' => $field['max_width'] ?? null,
            'max_height' => $field['max_height'] ?? null,
            'offset_x' => $field['offset_x'] ?? null,
            'offset_y' => $field['offset_y'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function resolveValue(mixed $resolver, array $documentData): mixed
    {
        if (is_callable($resolver)) {
            return $resolver($documentData);
        }

        if (is_string($resolver)) {
            return data_get($documentData, $resolver);
        }

        return $resolver;
    }

    private function normalizeFontName(string $font): string
    {
        return match (strtolower(trim($font))) {
            'arial' => 'helvetica',
            'zapfdingbats' => 'zapfdingbats',
            default => $font,
        };
    }

    private function makePdf(): Fpdi
    {
        $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0, true);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetCompression(false);
        $this->registerCustomFonts($pdf);

        return $pdf;
    }

    private function registerCustomFonts(Fpdi $pdf): void
    {
        $this->registerCustomFontStyle($pdf, '');
        $this->registerCustomFontStyle($pdf, 'B');
    }

    private function registerCustomFontStyle(Fpdi $pdf, string $style): void
    {
        $fontDefinitionPath = $this->customFontDefinitionPath($style);

        if (is_file($fontDefinitionPath)) {
            $pdf->AddFont(self::DEFAULT_FONT, $style, $fontDefinitionPath);
        }
    }

    /**
     * TCPDF's SetFont() falls back to its own filename-guessing search when
     * a font+style combination was never registered via AddFont(), and its
     * failure path is a raw die() (see registerCustomFonts()). Check the
     * definition file ourselves first so a missing style becomes a normal
     * catchable exception instead.
     */
    private function assertFontStyleAvailable(string $font, string $style): void
    {
        if ($font !== self::DEFAULT_FONT) {
            return;
        }

        $fontDefinitionPath = $this->customFontDefinitionPath($style);

        if (! is_file($fontDefinitionPath)) {
            throw new RuntimeException(sprintf(
                'Missing PDF font definition for font "%s" style "%s" (expected %s). Regenerate it with TCPDF_FONTS::addTTFfont().',
                $font,
                $style === '' ? '(regular)' : $style,
                $fontDefinitionPath,
            ));
        }
    }

    private function customFontDefinitionPath(string $style): string
    {
        return storage_path(
            'app/'.self::CUSTOM_FONT_DIRECTORY.'/'.self::DEFAULT_FONT.strtolower($style).'.php',
        );
    }
}
