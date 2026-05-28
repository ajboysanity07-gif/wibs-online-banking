<?php

namespace App\Services\LoanRequests;

use App\Services\LoanRequests\PdfFieldMaps\ApprovedLoanPdfFieldMap;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use TCPDF;
use Throwable;

class ApprovedLoanImageTemplatePdfService
{
    private const TEMPLATE_DIRECTORY = 'templates/approved-loan-documents/images';

    private const PUBLIC_TEMPLATE_DIRECTORY =
        'public/app/templates/approved-loan-documents/images';

    private const LEGACY_PUBLIC_TEMPLATE_DIRECTORY =
        'public/app/templates/approved-loan-documents';

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, mixed>  $documentData
     */
    public function generate(
        array $pages,
        string $outputPath,
        array $documentData,
        ApprovedLoanPdfFieldMap $fieldMap,
    ): void {
        File::ensureDirectoryExists(dirname($outputPath));
        File::put($outputPath, $this->renderContent($pages, $documentData, $fieldMap));
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, mixed>  $documentData
     */
    public function renderContent(
        array $pages,
        array $documentData,
        ApprovedLoanPdfFieldMap $fieldMap,
    ): string {
        $fieldsByPage = $this->groupFieldsByPage($fieldMap);

        return $this->renderImageTemplateBytes(
            $pages,
            function (TCPDF $pdf, int $pageNumber) use (
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
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, mixed>  $documentData
     */
    public function renderResponse(
        array $pages,
        string $filename,
        array $documentData,
        ApprovedLoanPdfFieldMap $fieldMap,
        string $disposition = 'inline',
    ): Response {
        return response(
            $this->renderContent($pages, $documentData, $fieldMap),
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
     * @param  array<int, array<string, mixed>>  $pages
     * @param  callable(TCPDF, int): void  $overlay
     */
    private function renderImageTemplateBytes(
        array $pages,
        callable $overlay,
    ): string {
        $resolvedPages = array_map(
            fn (array $page): array => $this->resolvePageDefinition($page),
            $pages,
        );

        $pdf = $this->makePdf();

        try {
            foreach ($resolvedPages as $index => $page) {
                $pageNumber = $index + 1;
                $orientation = $page['width'] > $page['height'] ? 'L' : 'P';

                $pdf->AddPage($orientation, [$page['width'], $page['height']]);
                $pdf->Image(
                    $page['image'],
                    0,
                    0,
                    $page['width'],
                    $page['height'],
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

                $overlay($pdf, $pageNumber);
            }

            return $pdf->Output('', 'S');
        } catch (Throwable $exception) {
            Log::error('Failed generating approved loan image template PDF.', [
                'template_images' => array_column($resolvedPages, 'image'),
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
     * @param  array<string, mixed>  $page
     * @return array{image: string, width: float, height: float}
     */
    private function resolvePageDefinition(array $page): array
    {
        $templateImage = trim((string) ($page['image'] ?? ''));

        if ($templateImage === '') {
            throw new RuntimeException('Missing image template path.');
        }

        $imagePath = $this->resolveTemplateImagePath($templateImage);
        $size = getimagesize($imagePath);

        if ($size === false) {
            Log::error('Unreadable approved loan image template file.', [
                'template_image' => $templateImage,
                'template_path' => $imagePath,
            ]);

            throw new RuntimeException(
                'Unreadable image template file: '.$templateImage,
            );
        }

        $imageWidth = (float) $size[0];
        $imageHeight = (float) $size[1];
        $ratio = $imageHeight / max($imageWidth, 1.0);
        $width = is_numeric($page['width'] ?? null) ? (float) $page['width'] : 216.0;
        $height = is_numeric($page['height'] ?? null) ? (float) $page['height'] : 0.0;

        if ($height <= 0) {
            $height = $width * $ratio;
        } elseif (abs(($height / $width) - $ratio) > 0.02) {
            $height = $width * $ratio;
        }

        return [
            'image' => $imagePath,
            'width' => round($width, 3),
            'height' => round($height, 3),
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $documentData
     */
    private function renderField(TCPDF $pdf, array $field, array $documentData): void
    {
        $type = (string) ($field['type'] ?? 'text');

        if ($type === 'signature') {
            $this->renderSignatureField($pdf, $field, $documentData);

            return;
        }

        if ($type === 'check') {
            $this->renderCheckField($pdf, $field, $documentData);

            return;
        }

        $value = $this->resolveValue($field['value'] ?? null, $documentData);
        $value = $this->transformValue($field['transform'] ?? null, $value);
        $text = $this->blank(is_scalar($value) ? (string) $value : null);

        if ($text === '') {
            return;
        }

        $x = (float) ($field['x'] ?? 0);
        $y = (float) ($field['y'] ?? 0);
        $width = $field['width'] ?? null;
        $lineHeight = (float) ($field['line_height'] ?? 4);
        $align = (string) ($field['align'] ?? 'L');

        if (is_numeric($width)) {
            $this->writeText(
                $pdf,
                $x,
                $y,
                $text,
                (int) ($field['size'] ?? 7),
                (string) ($field['font'] ?? 'helvetica'),
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
            (string) ($field['font'] ?? 'helvetica'),
            (string) ($field['style'] ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $documentData
     */
    private function renderCheckField(
        TCPDF $pdf,
        array $field,
        array $documentData,
    ): void {
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
    private function renderSignatureField(
        TCPDF $pdf,
        array $field,
        array $documentData,
    ): void {
        $relativePath = $this->resolveValue($field['value'] ?? null, $documentData);

        $this->writeSignature(
            $pdf,
            (float) ($field['x'] ?? 0),
            (float) ($field['y'] ?? 0),
            (float) ($field['width'] ?? 0),
            (float) ($field['height'] ?? 0),
            is_string($relativePath) ? $relativePath : null,
        );
    }

    public function writeText(
        TCPDF $pdf,
        float $x,
        float $y,
        ?string $text,
        int $size = 7,
        string $font = 'helvetica',
        string $style = '',
        ?float $width = null,
        float $lineHeight = 4,
        string $align = 'L',
    ): void {
        $text = $this->blank($text);

        if ($text === '') {
            return;
        }

        $pdf->SetFont($this->normalizeFontName($font), $style, $size);
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

    public function writeCheck(TCPDF $pdf, float $x, float $y, int $size = 8): void
    {
        $pdf->SetFont('zapfdingbats', '', $size);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Text($x, $y, '4');
    }

    public function writeSignature(
        TCPDF $pdf,
        float $x,
        float $y,
        float $width,
        float $height,
        ?string $signaturePath,
    ): void {
        if ($signaturePath === null || trim($signaturePath) === '') {
            return;
        }

        $absolutePath = $this->resolveSignaturePath($signaturePath);

        if ($absolutePath === null) {
            return;
        }

        $dimensions = $this->fitImageToBox(
            $absolutePath,
            $x,
            $y,
            $width,
            $height,
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

    private function resolveTemplateImagePath(string $templateImage): string
    {
        if (is_file($templateImage)) {
            return $templateImage;
        }

        $candidatePaths = [
            storage_path('app/'.trim(self::TEMPLATE_DIRECTORY.'/'.$templateImage, '/')),
            storage_path(
                'app/'.trim(self::PUBLIC_TEMPLATE_DIRECTORY.'/'.$templateImage, '/'),
            ),
            storage_path(
                'app/'.trim(self::LEGACY_PUBLIC_TEMPLATE_DIRECTORY.'/'.$templateImage, '/'),
            ),
        ];

        foreach ($candidatePaths as $candidatePath) {
            if (is_file($candidatePath)) {
                return $candidatePath;
            }
        }

        Log::error('Missing approved loan image template file.', [
            'template_image' => $templateImage,
            'template_path' => $candidatePaths[0] ?? null,
            'fallback_template_path' => $candidatePaths[1] ?? null,
            'legacy_public_template_path' => $candidatePaths[2] ?? null,
        ]);

        throw new RuntimeException('Missing image template file: '.$templateImage);
    }

    private function resolveSignaturePath(string $relativePath): ?string
    {
        $normalizedPath = trim($relativePath);

        if ($normalizedPath === '') {
            return null;
        }

        if (is_file($normalizedPath)) {
            return $normalizedPath;
        }

        $normalizedPath = $this->normalizePublicSignaturePath($normalizedPath);

        if ($normalizedPath === null) {
            return null;
        }

        if (Storage::disk('public')->exists($normalizedPath)) {
            return Storage::disk('public')->path($normalizedPath);
        }

        foreach ($this->signatureAbsolutePathCandidates($normalizedPath) as $absolutePath) {
            if (is_file($absolutePath)) {
                return $absolutePath;
            }
        }

        return null;
    }

    private function normalizePublicSignaturePath(string $path): ?string
    {
        $normalizedPath = trim($path);

        if ($normalizedPath === '') {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $normalizedPath) === 1) {
            $parsedPath = parse_url($normalizedPath, PHP_URL_PATH);
            $normalizedPath = is_string($parsedPath) ? $parsedPath : '';
        }

        $normalizedPath = str_replace('\\', '/', rawurldecode($normalizedPath));
        $normalizedPath = explode('?', $normalizedPath, 2)[0];
        $normalizedPath = explode('#', $normalizedPath, 2)[0];
        $normalizedPath = preg_replace(
            '#^/?(?:storage/app/public/|public/storage/|storage/)#i',
            '',
            $normalizedPath,
        ) ?? $normalizedPath;

        foreach ([
            '/storage/app/public/',
            '/public/storage/',
            '/storage/',
        ] as $marker) {
            $markerPosition = stripos($normalizedPath, $marker);

            if ($markerPosition === false) {
                continue;
            }

            $normalizedPath = substr(
                $normalizedPath,
                $markerPosition + strlen($marker),
            );

            break;
        }

        $normalizedPath = ltrim($normalizedPath, '/');
        $normalizedPath = preg_replace(
            '#^(?:app/public/|public/)+#i',
            '',
            $normalizedPath,
        ) ?? $normalizedPath;
        $normalizedPath = ltrim($normalizedPath, '/');

        return $normalizedPath !== '' ? $normalizedPath : null;
    }

    /**
     * @return list<string>
     */
    private function signatureAbsolutePathCandidates(string $relativePath): array
    {
        return [
            storage_path('app/public/'.$relativePath),
            public_path('storage/'.$relativePath),
        ];
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
    ): array {
        if ($width <= 0 || $height <= 0) {
            return [
                'x' => $x,
                'y' => $y,
                'width' => $width,
                'height' => $height,
            ];
        }

        $size = @getimagesize($absolutePath);

        if ($size === false || ($size[0] ?? 0) <= 0 || ($size[1] ?? 0) <= 0) {
            return [
                'x' => $x,
                'y' => $y,
                'width' => $width,
                'height' => $height,
            ];
        }

        $imageWidth = (float) $size[0];
        $imageHeight = (float) $size[1];
        $imageRatio = $imageWidth / $imageHeight;
        $boxRatio = $width / $height;

        if ($imageRatio >= $boxRatio) {
            $renderWidth = $width;
            $renderHeight = $width / $imageRatio;
        } else {
            $renderHeight = $height;
            $renderWidth = $height * $imageRatio;
        }

        return [
            'x' => $x + (($width - $renderWidth) / 2),
            'y' => $y + (($height - $renderHeight) / 2),
            'width' => $renderWidth,
            'height' => $renderHeight,
        ];
    }

    private function transformValue(mixed $transformer, mixed $value): mixed
    {
        if (is_callable($transformer)) {
            return $transformer($value);
        }

        return $value;
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

    private function makePdf(): TCPDF
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0, true);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetCompression(false);

        return $pdf;
    }
}
