<?php

namespace App\Services\LoanRequests;

use App\Services\LoanRequests\ExcelCellMaps\PlanOfPaymentDisclosurePromissoryNoteExcelCellMap;
use App\Services\SignaturePngService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as SharedDrawing;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing as WorksheetDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ApprovedLoanExcelTemplateService
{
    private const TEMPLATE_DIRECTORY = 'templates/approved-loan-documents/excel';

    private const HEADER_START_ROW = 1;

    private const HEADER_END_COLUMN = 'L';

    private const HEADER_RESERVED_ROWS = 4;

    private const HEADER_CONTENT_SCAN_ROWS = 16;

    private const HEADER_DEFAULT_COLUMN_WIDTH = 8.43;

    private const HEADER_DEFAULT_PRINTABLE_WIDTH_PIXELS = 624;

    private const HEADER_MAX_HEIGHT_POINTS = 70.0;

    private const HEADER_OFFSET_Y_PIXELS = 4;

    private const HEADER_BOTTOM_SPACING_PIXELS = 20;

    /**
     * @var list<string>
     */
    private const TEMPLATE_HELPER_PLACEHOLDERS = [
        'input data',
        'no input data',
        'do not input anything',
    ];

    /**
     * @var array<int, array{0: float, 1: float}>
     */
    private const PAPER_DIMENSIONS_INCHES = [
        PageSetup::PAPERSIZE_LETTER => [8.5, 11.0],
        PageSetup::PAPERSIZE_LETTER_SMALL => [8.5, 11.0],
        PageSetup::PAPERSIZE_LEGAL => [8.5, 14.0],
        PageSetup::PAPERSIZE_A4 => [8.27, 11.69],
        PageSetup::PAPERSIZE_A4_SMALL => [8.27, 11.69],
        PageSetup::PAPERSIZE_FOLIO => [8.5, 13.0],
    ];

    /**
     * @var array<string, array{startColumn: string, endColumn: string}>
     */
    private const WORKSHEET_PRINT_AREA_COLUMNS = [
        'Loan Information' => [
            'startColumn' => 'A',
            'endColumn' => 'H',
        ],
        'Plan of Payment' => [
            'startColumn' => 'A',
            'endColumn' => 'I',
        ],
        'Promissory Note' => [
            'startColumn' => 'A',
            'endColumn' => 'K',
        ],
    ];

    /**
     * @var array{startColumn: string, endColumn: string, startRow: int}
     */
    private const LOAN_INFORMATION_FORM_RANGE = [
        'startColumn' => 'A',
        'endColumn' => 'H',
        'startRow' => 6,
    ];

    /**
     * @var array<string, list<array<string, int|string|float|null>>>
     */
    private const WORKBOOK_SIGNATURE_PLACEMENTS = [
        'Loan Information' => [
            [
                'name' => 'Loan Information Loan Manager Signature',
                'description' => 'Loan manager approval signature for the loan information sheet',
                'source' => 'reviewer.signature_path',
                'coordinate' => 'D18',
                'startColumn' => 'D',
                'endColumn' => 'H',
                'width' => 120,
                'height' => 24,
                'maxWidth' => 150,
                'maxHeight' => 46,
                'offsetX' => 6,
                'offsetY' => 12,
                'rowHeight' => 44.0,
            ],
        ],
        'Plan of Payment' => [
            [
                'name' => 'Plan of Payment Borrower Signature',
                'description' => 'Borrower conforme signature for the plan of payment sheet',
                'source' => 'applicant.signature_path',
                'coordinate' => 'B27',
                'startColumn' => 'B',
                'endColumn' => 'D',
                'width' => 120,
                'height' => 24,
                'maxWidth' => 118,
                'maxHeight' => 44,
                'offsetX' => 0,
                'offsetY' => 12,
                'rowHeight' => 36.0,
            ],
            [
                'name' => 'Plan of Payment Loan Manager Signature',
                'description' => 'Loan manager approval signature for the plan of payment sheet',
                'source' => 'reviewer.signature_path',
                'coordinate' => 'G27',
                'startColumn' => 'G',
                'endColumn' => 'I',
                'width' => 120,
                'height' => 24,
                'maxWidth' => 118,
                'maxHeight' => 44,
                'offsetX' => 0,
                'offsetY' => 12,
                'rowHeight' => 36.0,
            ],
            [
                'name' => 'Plan of Payment Borrower Signature Copy',
                'description' => 'Borrower conforme signature for the lower plan of payment section',
                'source' => 'applicant.signature_path',
                'coordinate' => 'B59',
                'startColumn' => 'B',
                'endColumn' => 'D',
                'width' => 120,
                'height' => 24,
                'maxWidth' => 118,
                'maxHeight' => 44,
                'offsetX' => 0,
                'offsetY' => 12,
                'rowHeight' => 36.0,
            ],
            [
                'name' => 'Plan of Payment Loan Manager Signature Copy',
                'description' => 'Loan manager approval signature for the lower plan of payment section',
                'source' => 'reviewer.signature_path',
                'coordinate' => 'G59',
                'startColumn' => 'G',
                'endColumn' => 'I',
                'width' => 120,
                'height' => 24,
                'maxWidth' => 118,
                'maxHeight' => 44,
                'offsetX' => 0,
                'offsetY' => 12,
                'rowHeight' => 36.0,
            ],
        ],
        'Disclosure Statement' => [
            [
                'name' => 'Disclosure Statement Loan Manager Signature',
                'description' => 'Loan manager certification signature for the disclosure statement sheet',
                'source' => 'reviewer.signature_path',
                'coordinate' => 'L50',
                'startColumn' => 'L',
                'endColumn' => 'N',
                'width' => 120,
                'height' => 24,
                'maxWidth' => 118,
                'maxHeight' => 44,
                'offsetX' => 0,
                'offsetY' => 12,
                'rowHeight' => 36.0,
            ],
            [
                'name' => 'Disclosure Statement Borrower Signature',
                'description' => 'Borrower acknowledgment signature for the disclosure statement sheet',
                'source' => 'applicant.signature_path',
                'coordinate' => 'L57',
                'startColumn' => 'L',
                'endColumn' => 'N',
                'width' => 120,
                'height' => 24,
                'maxWidth' => 118,
                'maxHeight' => 44,
                'offsetX' => 0,
                'offsetY' => 12,
                'rowHeight' => 36.0,
            ],
        ],
        'Promissory Note' => [
            [
                'name' => 'Promissory Note Borrower Signature',
                'description' => 'Borrower signature for the promissory note sheet',
                'source' => 'applicant.signature_path',
                'coordinate' => 'B50',
                'startColumn' => 'B',
                'endColumn' => 'C',
                'width' => 112,
                'height' => 22,
                'maxWidth' => 112,
                'maxHeight' => 42,
                'offsetX' => 0,
                'offsetY' => 12,
                'rowHeight' => 35.0,
            ],
            [
                'name' => 'Promissory Note Co-maker 1 Signature',
                'description' => 'Co-maker 1 signature for the promissory note sheet',
                'source' => 'co_maker_one.signature_path',
                'coordinate' => 'E50',
                'startColumn' => 'E',
                'endColumn' => 'G',
                'width' => 112,
                'height' => 22,
                'maxWidth' => 112,
                'maxHeight' => 42,
                'offsetX' => 0,
                'offsetY' => 12,
                'rowHeight' => 35.0,
            ],
            [
                'name' => 'Promissory Note Co-maker 2 Signature',
                'description' => 'Co-maker 2 signature for the promissory note sheet',
                'source' => 'co_maker_two.signature_path',
                'coordinate' => 'I50',
                'startColumn' => 'I',
                'endColumn' => 'K',
                'width' => 112,
                'height' => 22,
                'maxWidth' => 112,
                'maxHeight' => 42,
                'offsetX' => 0,
                'offsetY' => 12,
                'rowHeight' => 35.0,
            ],
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const WORKSHEET_HEADER_CENTERING_MODES = [
        'Loan Information' => 'content',
        'Plan of Payment' => 'content',
    ];

    /**
     * @var array<string, int>
     */
    private const WORKSHEET_HEADER_OFFSET_X_ADJUSTMENTS = [
        'Loan Information' => 40,
        'Plan of Payment' => 48,
    ];

    public function __construct(
        private PlanOfPaymentDisclosurePromissoryNoteExcelCellMap $cellMap,
        private SignaturePngService $signaturePngService,
        private DocumentSignaturePlacement $signaturePlacement,
    ) {}

    public function generate(
        string $templateFilename,
        string $outputPath,
        array $documentData,
    ): void {
        $templatePath = $this->resolveTemplatePath($templateFilename);
        $spreadsheet = IOFactory::load($templatePath);
        $temporaryImagePaths = [];

        try {
            $this->applyMappedCells($spreadsheet, $documentData);
            $this->replaceTemplateTokens($spreadsheet, $documentData);
            $this->clearTemplateHelperPlaceholders($spreadsheet);
            $this->prepareWorksheetLayouts($spreadsheet);
            $temporaryHeaderImagePath = $this->insertReportHeaderImages(
                $spreadsheet,
                $documentData,
            );

            if (is_string($temporaryHeaderImagePath) && $temporaryHeaderImagePath !== '') {
                $temporaryImagePaths[] = $temporaryHeaderImagePath;
            }
            $this->finalizeLoanInformationWorksheetLayout($spreadsheet);
            File::ensureDirectoryExists(dirname($outputPath));
            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($outputPath);
        } finally {
            foreach (array_unique($temporaryImagePaths) as $temporaryImagePath) {
                if (is_string($temporaryImagePath) && $temporaryImagePath !== '') {
                    File::delete($temporaryImagePath);
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function applyMappedCells(Spreadsheet $spreadsheet, array $documentData): void
    {
        foreach ($this->cellMap->cells() as $sheetIndex => $cells) {
            if ($sheetIndex >= $spreadsheet->getSheetCount()) {
                continue;
            }

            $worksheet = $spreadsheet->getSheet($sheetIndex);

            foreach ($cells as $cellDefinition) {
                $coordinate = (string) ($cellDefinition['cell'] ?? '');

                if ($coordinate === '') {
                    continue;
                }

                $value = $this->resolveValue(
                    $cellDefinition['value'] ?? null,
                    $documentData,
                );

                if ($value === null || $value === '') {
                    $worksheet->setCellValueExplicit(
                        $coordinate,
                        '',
                        DataType::TYPE_STRING,
                    );

                    continue;
                }

                $type = (string) ($cellDefinition['type'] ?? 'string');

                if ($type === 'numeric' && is_numeric((string) $value)) {
                    $worksheet->setCellValue($coordinate, (float) $value);

                    continue;
                }

                $worksheet->setCellValueExplicit(
                    $coordinate,
                    (string) $value,
                    DataType::TYPE_STRING,
                );
            }
        }
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

    private function replaceTemplateTokens(Spreadsheet $spreadsheet, array $documentData): void
    {
        $flattened = $this->flattenDocumentData($documentData);

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $highestRow = $worksheet->getHighestRow();
            $highestColumnIndex = Coordinate::columnIndexFromString(
                $worksheet->getHighestColumn(),
            );

            for ($row = 1; $row <= $highestRow; $row++) {
                for ($column = 1; $column <= $highestColumnIndex; $column++) {
                    $coordinate = Coordinate::stringFromColumnIndex($column).$row;
                    $cell = $worksheet->getCell($coordinate);
                    $value = $cell->getValue();

                    if (! is_string($value)) {
                        continue;
                    }

                    if (
                        ! str_contains($value, '{{')
                        && ! str_contains($value, '[[')
                    ) {
                        continue;
                    }

                    $updated = $this->replaceTemplateTokensInString(
                        $value,
                        $flattened,
                    );

                    if ($updated !== $value) {
                        $cell->setValueExplicit($updated, DataType::TYPE_STRING);
                    }
                }
            }
        }
    }

    /**
     * @return array<string, scalar>
     */
    private function flattenDocumentData(array $documentData, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($documentData as $key => $value) {
            $composedKey = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $flattened += $this->flattenDocumentData($value, $composedKey);

                continue;
            }

            if (is_scalar($value)) {
                $flattened[$composedKey] = $value;
            }
        }

        return $flattened;
    }

    /**
     * @param  array<string, scalar>  $flattened
     */
    private function replaceTemplateTokensInString(string $value, array $flattened): string
    {
        $updated = $value;

        foreach ($flattened as $key => $replacement) {
            $tokens = [
                '{{'.$key.'}}',
                '{{ '.$key.' }}',
                '[['.$key.']]',
                '[[ '.$key.' ]]',
            ];
            $updated = str_replace($tokens, (string) $replacement, $updated);
        }

        return $updated;
    }

    private function clearTemplateHelperPlaceholders(
        Spreadsheet $spreadsheet,
    ): void {
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $highestRow = $worksheet->getHighestRow();
            $highestColumnIndex = Coordinate::columnIndexFromString(
                $worksheet->getHighestColumn(),
            );

            for ($row = 1; $row <= $highestRow; $row++) {
                for ($column = 1; $column <= $highestColumnIndex; $column++) {
                    $coordinate = Coordinate::stringFromColumnIndex($column).$row;
                    $value = $worksheet->getCell($coordinate)->getValue();

                    if (! is_string($value)) {
                        continue;
                    }

                    $normalizedValue = strtolower(trim($value));

                    if (! in_array($normalizedValue, self::TEMPLATE_HELPER_PLACEHOLDERS, true)) {
                        continue;
                    }

                    $worksheet->setCellValueExplicit(
                        $coordinate,
                        '',
                        DataType::TYPE_STRING,
                    );
                }
            }
        }
    }

    private function resolveTemplatePath(string $templateFilename): string
    {
        $templatePath = storage_path(
            'app/'.trim(self::TEMPLATE_DIRECTORY.'/'.$templateFilename, '/'),
        );

        if (! is_file($templatePath)) {
            throw new RuntimeException('Missing Excel template file: '.$templateFilename);
        }

        return $templatePath;
    }

    private function prepareWorksheetLayouts(Spreadsheet $spreadsheet): void
    {
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $worksheet->getPageSetup()->setHorizontalCentered(true);

            if ($worksheet->getTitle() === 'Promissory Note') {
                $this->preparePromissoryNoteWorksheetLayout($worksheet);
            }
        }
    }

    private function finalizeLoanInformationWorksheetLayout(
        Spreadsheet $spreadsheet,
    ): void {
        $worksheet = $spreadsheet->getSheetByName('Loan Information');

        if (! $worksheet instanceof Worksheet) {
            return;
        }

        $this->ensureWorksheetPrintArea($worksheet);
        $this->applyLoanInformationOuterBorder($worksheet);
    }

    /**
     * @param  array<string, mixed>  $documentData
     */
    private function insertSignatureImages(
        Spreadsheet $spreadsheet,
        array $documentData,
    ): array {
        $temporaryPaths = [];
        $resolvedPathCache = [];
        $overlayImageCache = [];

        foreach (self::WORKBOOK_SIGNATURE_PLACEMENTS as $worksheetTitle => $placements) {
            $worksheet = $spreadsheet->getSheetByName($worksheetTitle);

            if (! $worksheet instanceof Worksheet) {
                continue;
            }

            foreach ($placements as $placement) {
                $source = is_string($placement['source'] ?? null)
                    ? $placement['source']
                    : null;

                if ($source === null) {
                    continue;
                }

                $signaturePath = data_get($documentData, $source);
                $relativePath = is_string($signaturePath)
                    ? trim($signaturePath)
                    : null;

                if ($relativePath === null || $relativePath === '') {
                    continue;
                }

                $cacheKey = $source.'|'.$relativePath;

                if (! array_key_exists($cacheKey, $resolvedPathCache)) {
                    $resolvedPathCache[$cacheKey] = $this->resolveSignatureImagePath(
                        $relativePath,
                    );
                }

                $absolutePath = $resolvedPathCache[$cacheKey];

                if (! is_string($absolutePath) || $absolutePath === '') {
                    continue;
                }

                if (! array_key_exists($absolutePath, $overlayImageCache)) {
                    $overlayImageCache[$absolutePath] = $this->signaturePngService
                        ->prepareOverlayImage($absolutePath);

                    if (($overlayImageCache[$absolutePath]['temporary'] ?? false) === true) {
                        $temporaryPaths[] = $overlayImageCache[$absolutePath]['path'];
                    }
                }

                $this->insertSignatureDrawing(
                    $spreadsheet,
                    $worksheet,
                    $overlayImageCache[$absolutePath]['path'],
                    $placement,
                );
            }
        }

        return array_values(array_unique($temporaryPaths));
    }

    /**
     * @param  array<string, int|string|float|null>  $placement
     */
    private function insertSignatureDrawing(
        Spreadsheet $spreadsheet,
        Worksheet $worksheet,
        string $overlayImagePath,
        array $placement,
    ): void {
        $coordinate = is_string($placement['coordinate'] ?? null)
            ? $placement['coordinate']
            : null;
        $startColumn = is_string($placement['startColumn'] ?? null)
            ? $placement['startColumn']
            : null;
        $endColumn = is_string($placement['endColumn'] ?? null)
            ? $placement['endColumn']
            : null;

        if ($coordinate === null || $startColumn === null || $endColumn === null) {
            return;
        }

        [, $rowNumber] = Coordinate::coordinateFromString($coordinate);
        $placementWidth = $this->columnRangeWidthInPixels(
            $worksheet,
            $spreadsheet,
            $startColumn,
            $endColumn,
        );
        $fallbackWidth = is_numeric($placement['width'] ?? null)
            ? (float) $placement['width']
            : 0.0;
        $containerWidth = $placementWidth > 0
            ? (float) $placementWidth
            : $fallbackWidth;
        $containerHeight = is_numeric($placement['height'] ?? null)
            ? (float) $placement['height']
            : 0.0;

        if ($containerWidth <= 0 || $containerHeight <= 0) {
            return;
        }

        $dimensions = $this->signaturePlacement->calculateFromImagePath(
            $overlayImagePath,
            0.0,
            0.0,
            $containerWidth,
            $containerHeight,
            $this->signatureDrawingPlacementOptions($placement),
        );
        $anchor = $this->resolveDrawingAnchor(
            $worksheet,
            $spreadsheet,
            $startColumn,
            $endColumn,
            max(0, (int) round($dimensions['x'])),
        );
        $drawing = new WorksheetDrawing;
        $drawing->setName((string) ($placement['name'] ?? 'Workbook Signature'));
        $drawing->setDescription(
            (string) ($placement['description'] ?? 'Workbook signature image'),
        );
        $drawing->setPath($overlayImagePath);
        $drawing->setResizeProportional(true);
        $drawing->setCoordinates($anchor['column'].$rowNumber);
        $drawing->setOffsetX($anchor['offset']);
        $drawing->setOffsetY((int) round($dimensions['y']));
        $drawing->setWidth((int) round($dimensions['width']));
        $drawing->setWorksheet($worksheet);
        $this->ensureSignatureRowSpace(
            $worksheet,
            $coordinate,
            $dimensions['height'],
            is_numeric($placement['rowHeight'] ?? null)
                ? (float) $placement['rowHeight']
                : null,
        );
    }

    private function ensureSignatureRowSpace(
        Worksheet $worksheet,
        string $coordinate,
        float $requiredHeightPixels,
        ?float $minimumHeightPoints = null,
    ): void {
        [, $rowNumber] = Coordinate::coordinateFromString($coordinate);
        $targetHeight = SharedDrawing::pixelsToPoints(
            (int) ceil($requiredHeightPixels + 6),
        );

        if ($minimumHeightPoints !== null && $minimumHeightPoints > 0) {
            $targetHeight = max($targetHeight, $minimumHeightPoints);
        }

        $this->ensureMinimumRowHeight($worksheet, $rowNumber, $targetHeight);
    }

    private function resolveSignatureImagePath(?string $relativePath): ?string
    {
        $signaturePath = trim((string) $relativePath);

        if ($signaturePath === '') {
            return null;
        }

        $normalizedPath = $this->normalizeSignatureImagePath($signaturePath);

        if ($normalizedPath !== '' && is_file($normalizedPath)) {
            return $normalizedPath;
        }

        if ($normalizedPath !== '' && Storage::disk('public')->exists($normalizedPath)) {
            return Storage::disk('public')->path($normalizedPath);
        }

        $storagePath = storage_path('app/public/'.ltrim($normalizedPath, '/'));

        if ($normalizedPath !== '' && is_file($storagePath)) {
            return $storagePath;
        }

        Log::warning('Signature file could not be resolved for approved loan workbook.', [
            'signature_path' => $signaturePath,
            'normalized_signature_path' => $normalizedPath,
            'checked_storage_path' => $storagePath,
        ]);

        return null;
    }

    private function normalizeSignatureImagePath(string $path): string
    {
        $normalizedPath = trim($path);

        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $normalizedPath) === 1) {
            $parsedPath = parse_url($normalizedPath, PHP_URL_PATH);
            $normalizedPath = is_string($parsedPath) ? $parsedPath : '';
        }

        $normalizedPath = str_replace('\\', '/', rawurldecode($normalizedPath));
        $normalizedPath = explode('?', $normalizedPath, 2)[0];
        $normalizedPath = explode('#', $normalizedPath, 2)[0];

        if (preg_match('#^(?:[a-z]:/|/)#i', $normalizedPath) === 1) {
            return $normalizedPath;
        }

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

        return ltrim($normalizedPath, '/');
    }

    /**
     * @param  array<string, int|string|float|null>  $placement
     * @return array{
     *     scale?: float|int|null,
     *     max_width?: float|int|null,
     *     max_height?: float|int|null,
     *     offset_x?: float|int|null,
     *     offset_y?: float|int|null
     * }
     */
    private function signatureDrawingPlacementOptions(array $placement): array
    {
        return array_filter([
            'scale' => $placement['scale'] ?? null,
            'max_width' => $placement['maxWidth'] ?? null,
            'max_height' => $placement['maxHeight'] ?? null,
            'offset_x' => $placement['offsetX'] ?? null,
            'offset_y' => $placement['offsetY'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function preparePromissoryNoteWorksheetLayout(Worksheet $worksheet): void
    {
        $this->expandPromissoryNotePrintableColumnWidth($worksheet);
        $this->narrowPromissoryNoteMergedRanges($worksheet);
        $this->mergePromissoryNoteParagraphRanges($worksheet);
        $this->applyPromissoryNoteTextWrapping($worksheet);
        $this->expandPromissoryNoteRowHeights($worksheet);
        $this->setPromissoryNotePageSetup($worksheet);
        $worksheet->setCellValue('K15', '="for " & L15');
        $worksheet->getColumnDimension('L')->setVisible(false);
        $worksheet->getColumnDimension('M')->setVisible(false);
    }

    private function expandPromissoryNotePrintableColumnWidth(Worksheet $worksheet): void
    {
        $columnKWidth = (float) $worksheet->getColumnDimension('K')->getWidth();
        $columnLWidth = (float) $worksheet->getColumnDimension('L')->getWidth();
        $columnMWidth = (float) $worksheet->getColumnDimension('M')->getWidth();

        if ($columnKWidth <= 0 || $columnLWidth <= 0 || $columnMWidth <= 0) {
            return;
        }

        $worksheet->getColumnDimension('K')->setWidth(
            $columnKWidth + $columnLWidth + $columnMWidth,
        );
    }

    private function narrowPromissoryNoteMergedRanges(Worksheet $worksheet): void
    {
        foreach ([
            'J13:L13' => 'J13:K13',
            'A47:M47' => 'A47:K47',
            'I50:L50' => 'I50:K50',
            'I51:L51' => 'I51:K51',
            'I53:L53' => 'I53:K53',
            'H58:L58' => 'H58:K58',
        ] as $currentRange => $replacementRange) {
            $this->replaceMergedRange(
                $worksheet,
                $currentRange,
                $replacementRange,
            );
        }
    }

    private function mergePromissoryNoteParagraphRanges(Worksheet $worksheet): void
    {
        $this->mergeRangeIfAbsent($worksheet, 'C12:K12');
        $this->mergeRangeIfAbsent($worksheet, 'H14:K14');
        $this->mergeRangeIfAbsent($worksheet, 'A16:K16');
    }

    private function mergeRangeIfAbsent(
        Worksheet $worksheet,
        string $range,
    ): void {
        if (in_array($range, $worksheet->getMergeCells(), true)) {
            return;
        }

        $worksheet->mergeCells($range);
    }

    private function replaceMergedRange(
        Worksheet $worksheet,
        string $currentRange,
        string $replacementRange,
    ): void {
        if (! in_array($currentRange, $worksheet->getMergeCells(), true)) {
            return;
        }

        $worksheet->unmergeCells($currentRange);
        $worksheet->mergeCells($replacementRange);
    }

    private function applyPromissoryNoteTextWrapping(Worksheet $worksheet): void
    {
        foreach ([
            'C12:K12',
            'D13:H13',
            'E14:G14',
            'H14:K14',
            'A16:K16',
            'B50:C50',
            'E50:G50',
            'I50:K50',
            'B51:C51',
            'E51:G51',
            'I51:K51',
            'C53:C53',
            'E53:G53',
            'I53:K53',
            'B58:D58',
            'H58:K58',
        ] as $range) {
            $worksheet->getStyle($range)->getAlignment()->setWrapText(true);
        }
    }

    private function expandPromissoryNoteRowHeights(Worksheet $worksheet): void
    {
        foreach ([
            12 => 34.0,
            13 => 24.0,
            14 => 30.0,
            15 => 22.0,
            16 => 20.0,
            50 => 24.0,
            53 => 32.0,
            58 => 24.0,
        ] as $row => $minimumHeight) {
            $this->ensureMinimumRowHeight(
                $worksheet,
                $row,
                $minimumHeight,
            );
        }
    }

    private function ensureMinimumRowHeight(
        Worksheet $worksheet,
        int $row,
        float $minimumHeight,
    ): void {
        $currentHeight = (float) $worksheet->getRowDimension($row)->getRowHeight();

        if ($currentHeight >= $minimumHeight) {
            return;
        }

        $worksheet->getRowDimension($row)->setRowHeight($minimumHeight);
    }

    private function setPromissoryNotePageSetup(Worksheet $worksheet): void
    {
        $pageSetup = $worksheet->getPageSetup();
        $pageSetup->setScale(null, false);
        $pageSetup->setFitToWidth(1);
        $pageSetup->setFitToHeight(0);
        $pageSetup->setFitToPage(true);
    }

    private function applyLoanInformationOuterBorder(Worksheet $worksheet): void
    {
        $outlineStyle = [
            'borderStyle' => Border::BORDER_MEDIUM,
            'color' => ['rgb' => '000000'],
        ];

        $borderRanges = $this->resolveLoanInformationOuterBorderRanges($worksheet);

        $worksheet->getStyle($borderRanges['fullRange'])->applyFromArray([
            'borders' => [
                'outline' => $outlineStyle,
            ],
        ]);

        foreach ([
            'leftRange' => 'left',
            'rightRange' => 'right',
            'topRange' => 'top',
            'bottomRange' => 'bottom',
        ] as $rangeKey => $edge) {
            $worksheet->getStyle($borderRanges[$rangeKey])->applyFromArray([
                'borders' => [
                    $edge => $outlineStyle,
                ],
            ]);
        }
    }

    /**
     * @return array{
     *     fullRange: string,
     *     leftRange: string,
     *     rightRange: string,
     *     topRange: string,
     *     bottomRange: string
     * }
     */
    private function resolveLoanInformationOuterBorderRanges(
        Worksheet $worksheet,
    ): array {
        $columnRange = self::LOAN_INFORMATION_FORM_RANGE;
        $endRow = $worksheet->getHighestRow();
        $startColumn = $columnRange['startColumn'];
        $endColumn = $columnRange['endColumn'];
        $startRow = $columnRange['startRow'];

        return [
            'fullRange' => sprintf(
                '%s%d:%s%d',
                $startColumn,
                $startRow,
                $endColumn,
                $endRow,
            ),
            'leftRange' => sprintf(
                '%s%d:%s%d',
                $startColumn,
                $startRow,
                $startColumn,
                $endRow,
            ),
            'rightRange' => sprintf(
                '%s%d:%s%d',
                $endColumn,
                $startRow,
                $endColumn,
                $endRow,
            ),
            'topRange' => sprintf(
                '%s%d:%s%d',
                $startColumn,
                $startRow,
                $endColumn,
                $startRow,
            ),
            'bottomRange' => sprintf(
                '%s%d:%s%d',
                $startColumn,
                $endRow,
                $endColumn,
                $endRow,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $documentData
     */
    private function insertReportHeaderImages(
        Spreadsheet $spreadsheet,
        array $documentData,
    ): ?string {
        if ($spreadsheet->getSheetCount() === 0) {
            return null;
        }

        $headerImage = $this->resolveReportHeaderImagePath($documentData);

        if ($headerImage['path'] === null) {
            return $headerImage['temporaryPath'];
        }

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $this->insertWorksheetHeaderImage(
                $worksheet,
                $spreadsheet,
                $headerImage['path'],
            );
        }

        return $headerImage['temporaryPath'];
    }

    private function insertWorksheetHeaderImage(
        Worksheet $worksheet,
        Spreadsheet $spreadsheet,
        string $imagePath,
    ): void {
        $headerRowCount = $this->prepareWorksheetHeaderArea($worksheet);
        $drawing = new WorksheetDrawing;
        $drawing->setName('Report Header Design');
        $drawing->setDescription('Uploaded report header design');
        $drawing->setPath($imagePath);
        $drawing->setResizeProportional(true);
        $drawing->setOffsetY(self::HEADER_OFFSET_Y_PIXELS);
        $this->fitHeaderDrawing(
            $drawing,
            $worksheet,
            $spreadsheet,
            $headerRowCount,
        );
        $drawing->setWorksheet($worksheet);
    }

    /**
     * @param  array<string, mixed>  $documentData
     * @return array{path: ?string, temporaryPath: ?string}
     */
    private function resolveReportHeaderImagePath(array $documentData): array
    {
        $reportHeader = $documentData['organization']['report_header'] ?? null;

        if (! is_array($reportHeader)) {
            return [
                'path' => null,
                'temporaryPath' => null,
            ];
        }

        $storedPath = $reportHeader['designPath'] ?? null;
        if (is_string($storedPath) && trim($storedPath) !== '') {
            $storedPath = trim($storedPath);

            if (Storage::disk('public')->exists($storedPath)) {
                return [
                    'path' => Storage::disk('public')->path($storedPath),
                    'temporaryPath' => null,
                ];
            }
        }

        $designData = $reportHeader['designData'] ?? null;
        if (! is_string($designData) || trim($designData) === '') {
            return [
                'path' => null,
                'temporaryPath' => null,
            ];
        }

        $temporaryPath = $this->decodeDataUriToTemporaryImage(trim($designData));

        return [
            'path' => $temporaryPath,
            'temporaryPath' => $temporaryPath,
        ];
    }

    private function fitHeaderDrawing(
        WorksheetDrawing $drawing,
        Worksheet $worksheet,
        Spreadsheet $spreadsheet,
        int $headerRowCount,
    ): void {
        $originalWidth = $drawing->getWidth();
        $originalHeight = $drawing->getHeight();

        if ($originalWidth <= 0 || $originalHeight <= 0) {
            return;
        }

        $headerPlacementArea = $this->resolveHeaderPlacementArea(
            $worksheet,
            $spreadsheet,
        );
        $headerAreaWidth = $headerPlacementArea['width'];
        $maximumHeight = SharedDrawing::pointsToPixels(
            self::HEADER_MAX_HEIGHT_POINTS,
        );
        $printableWidth = $this->printableWidthInPixels($worksheet);
        $maximumWidth = $printableWidth > 0
            ? min($headerAreaWidth, $printableWidth)
            : $headerAreaWidth;

        if ($headerAreaWidth <= 0 || $maximumWidth <= 0 || $maximumHeight <= 0) {
            return;
        }

        $scale = min(
            $maximumWidth / $originalWidth,
            $maximumHeight / $originalHeight,
        );

        if ($scale <= 0) {
            return;
        }

        $scaledWidth = max(1, (int) floor($originalWidth * $scale));
        $centeringWidth = $this->headerCenteringWidth(
            $worksheet,
            $headerAreaWidth,
            $printableWidth,
        );
        $leftOffset = max(
            0,
            (int) floor(($centeringWidth - $scaledWidth) / 2)
                + $this->headerOffsetXAdjustment($worksheet),
        );
        $anchor = $this->resolveDrawingAnchor(
            $worksheet,
            $spreadsheet,
            $headerPlacementArea['startColumn'],
            $headerPlacementArea['endColumn'],
            $leftOffset,
        );

        $drawing->setCoordinates(
            $anchor['column'].self::HEADER_START_ROW,
        );
        $drawing->setWidth($scaledWidth);
        $this->ensureHeaderRowSpace(
            $worksheet,
            $drawing->getHeight(),
            $headerRowCount,
        );
        $drawing->setOffsetX($anchor['offset']);
    }

    private function headerCenteringWidth(
        Worksheet $worksheet,
        int $headerAreaWidth,
        int $printableWidth,
    ): int {
        $mode = self::WORKSHEET_HEADER_CENTERING_MODES[$worksheet->getTitle()] ?? 'page';

        if ($mode === 'content') {
            return $headerAreaWidth;
        }

        return $printableWidth > 0
            ? $printableWidth
            : $headerAreaWidth;
    }

    private function headerOffsetXAdjustment(Worksheet $worksheet): int
    {
        return self::WORKSHEET_HEADER_OFFSET_X_ADJUSTMENTS[$worksheet->getTitle()] ?? 0;
    }

    private function ensureHeaderRowSpace(
        Worksheet $worksheet,
        int $headerHeightPixels,
        int $headerRowCount,
    ): void {
        if ($headerRowCount <= 0) {
            return;
        }

        $requiredHeight = $headerHeightPixels
            + (self::HEADER_OFFSET_Y_PIXELS * 2)
            + self::HEADER_BOTTOM_SPACING_PIXELS;
        $availableHeight = 0;
        $defaultRowHeight = (float) $worksheet->getDefaultRowDimension()->getRowHeight();

        if ($defaultRowHeight <= 0) {
            $defaultRowHeight = 15.0;
        }

        for ($row = 1; $row <= $headerRowCount; $row++) {
            $rowHeight = (float) $worksheet->getRowDimension($row)->getRowHeight();

            if ($rowHeight <= 0) {
                $rowHeight = $defaultRowHeight;
            }

            $availableHeight += SharedDrawing::pointsToPixels($rowHeight);
        }

        if ($availableHeight >= $requiredHeight) {
            return;
        }

        $rowHeight = SharedDrawing::pixelsToPoints(
            (int) ceil($requiredHeight / $headerRowCount),
        );

        for ($row = 1; $row <= $headerRowCount; $row++) {
            $worksheet->getRowDimension($row)->setRowHeight($rowHeight);
        }
    }

    /**
     * @return array{startColumn: string, endColumn: string, width: int}
     */
    private function resolveHeaderPlacementArea(
        Worksheet $worksheet,
        Spreadsheet $spreadsheet,
    ): array {
        $headerRange = $this->ensureWorksheetPrintArea($worksheet)
            ?? $this->resolveWorksheetUsedColumnRange($worksheet)
            ?? $this->resolveTopContentColumnRange($worksheet)
            ?? $this->resolveMergedHeaderColumnRange($worksheet)
            ?? [
                'startColumn' => 'A',
                'endColumn' => self::HEADER_END_COLUMN,
            ];

        $headerWidth = $this->columnRangeWidthInPixels(
            $worksheet,
            $spreadsheet,
            $headerRange['startColumn'],
            $headerRange['endColumn'],
        );

        if ($headerWidth <= 0) {
            $headerWidth = $this->printableWidthInPixels($worksheet);
        }

        return [
            ...$headerRange,
            'width' => $headerWidth,
        ];
    }

    /**
     * @return array{startColumn: string, endColumn: string}|null
     */
    private function ensureWorksheetPrintArea(Worksheet $worksheet): ?array
    {
        $preferredRange = $this->configuredWorksheetPrintAreaRange($worksheet)
            ?? $this->resolvePrintableColumnRange($worksheet)
            ?? $this->resolveWorksheetUsedColumnRange($worksheet)
            ?? $this->resolveTopContentColumnRange($worksheet)
            ?? $this->resolveMergedHeaderColumnRange($worksheet);

        if ($preferredRange === null) {
            return null;
        }

        $printArea = sprintf(
            '%s1:%s%d',
            $preferredRange['startColumn'],
            $preferredRange['endColumn'],
            max($worksheet->getHighestRow(), self::HEADER_RESERVED_ROWS),
        );

        if ($worksheet->getPageSetup()->getPrintArea() !== $printArea) {
            $worksheet->getPageSetup()->setPrintArea($printArea);
        }

        return $preferredRange;
    }

    /**
     * @return array{startColumn: string, endColumn: string}|null
     */
    private function configuredWorksheetPrintAreaRange(Worksheet $worksheet): ?array
    {
        return self::WORKSHEET_PRINT_AREA_COLUMNS[$worksheet->getTitle()] ?? null;
    }

    /**
     * @param  array{startColumn: string, endColumn: string}  $range
     * @param  array{startColumn: string, endColumn: string}  $additionalRange
     * @return array{startColumn: string, endColumn: string}
     */
    private function expandColumnRanges(
        array $range,
        array $additionalRange,
    ): array {
        $startColumnIndex = min(
            Coordinate::columnIndexFromString($range['startColumn']),
            Coordinate::columnIndexFromString($additionalRange['startColumn']),
        );
        $endColumnIndex = max(
            Coordinate::columnIndexFromString($range['endColumn']),
            Coordinate::columnIndexFromString($additionalRange['endColumn']),
        );

        return [
            'startColumn' => Coordinate::stringFromColumnIndex($startColumnIndex),
            'endColumn' => Coordinate::stringFromColumnIndex($endColumnIndex),
        ];
    }

    /**
     * @return array{startColumn: string, endColumn: string}|null
     */
    private function resolveMergedHeaderColumnRange(Worksheet $worksheet): ?array
    {
        $selectedRange = null;
        $selectedWidth = 0;
        $selectedRow = PHP_INT_MAX;

        foreach ($worksheet->getMergeCells() as $mergedRange) {
            [$startBoundary, $endBoundary] = Coordinate::rangeBoundaries(
                str_replace('$', '', $mergedRange),
            );
            [$startColumnIndex, $startRow] = $startBoundary;
            [$endColumnIndex, $endRow] = $endBoundary;

            if ($endRow > self::HEADER_CONTENT_SCAN_ROWS) {
                continue;
            }

            $width = $endColumnIndex - $startColumnIndex + 1;

            if (
                $selectedRange !== null
                && ($width < $selectedWidth
                    || ($width === $selectedWidth && $startRow >= $selectedRow))
            ) {
                continue;
            }

            $selectedRange = [
                'startColumn' => Coordinate::stringFromColumnIndex($startColumnIndex),
                'endColumn' => Coordinate::stringFromColumnIndex($endColumnIndex),
            ];
            $selectedWidth = $width;
            $selectedRow = $startRow;
        }

        return $selectedRange;
    }

    /**
     * @return array{startColumn: string, endColumn: string}|null
     */
    private function resolveTopContentColumnRange(Worksheet $worksheet): ?array
    {
        $highestRow = min($worksheet->getHighestRow(), self::HEADER_CONTENT_SCAN_ROWS);
        $highestColumnIndex = Coordinate::columnIndexFromString(
            $worksheet->getHighestColumn(),
        );
        $startColumnIndex = null;
        $endColumnIndex = null;

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $coordinate = Coordinate::stringFromColumnIndex($column).$row;
                $value = $worksheet->getCell($coordinate)->getValue();

                if ($value === null || $value === '') {
                    continue;
                }

                $startColumnIndex = $startColumnIndex === null
                    ? $column
                    : min($startColumnIndex, $column);
                $endColumnIndex = $endColumnIndex === null
                    ? $column
                    : max($endColumnIndex, $column);
            }
        }

        if ($startColumnIndex === null || $endColumnIndex === null) {
            return null;
        }

        return [
            'startColumn' => Coordinate::stringFromColumnIndex($startColumnIndex),
            'endColumn' => Coordinate::stringFromColumnIndex($endColumnIndex),
        ];
    }

    /**
     * @return array{startColumn: string, endColumn: string}|null
     */
    private function resolvePrintableColumnRange(Worksheet $worksheet): ?array
    {
        $printArea = trim((string) $worksheet->getPageSetup()->getPrintArea());

        if ($printArea === '') {
            return null;
        }

        $firstRange = trim(explode(',', $printArea)[0] ?? '');
        $firstRange = preg_replace('/^[^!]+!/', '', $firstRange) ?? $firstRange;
        $firstRange = str_replace('$', '', $firstRange);

        if ($firstRange === '') {
            return null;
        }

        [$startBoundary, $endBoundary] = Coordinate::rangeBoundaries($firstRange);

        return [
            'startColumn' => Coordinate::stringFromColumnIndex($startBoundary[0]),
            'endColumn' => Coordinate::stringFromColumnIndex($endBoundary[0]),
        ];
    }

    /**
     * @return array{startColumn: string, endColumn: string}|null
     */
    private function resolveWorksheetUsedColumnRange(Worksheet $worksheet): ?array
    {
        $highestRow = $worksheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString(
            $worksheet->getHighestDataColumn(),
        );
        $startColumnIndex = null;
        $endColumnIndex = null;

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $value = $worksheet->getCell(
                    Coordinate::stringFromColumnIndex($column).$row,
                )->getValue();

                if ($value === null || $value === '') {
                    continue;
                }

                $startColumnIndex = $startColumnIndex === null
                    ? $column
                    : min($startColumnIndex, $column);
                $endColumnIndex = $endColumnIndex === null
                    ? $column
                    : max($endColumnIndex, $column);
            }
        }

        foreach ($worksheet->getMergeCells() as $mergedRange) {
            [$startBoundary, $endBoundary] = Coordinate::rangeBoundaries(
                str_replace('$', '', $mergedRange),
            );
            $topLeftCoordinate = Coordinate::stringFromColumnIndex(
                $startBoundary[0],
            ).$startBoundary[1];
            $value = $worksheet->getCell($topLeftCoordinate)->getValue();

            if ($value === null || $value === '') {
                continue;
            }

            $startColumnIndex = $startColumnIndex === null
                ? $startBoundary[0]
                : min($startColumnIndex, $startBoundary[0]);
            $endColumnIndex = $endColumnIndex === null
                ? $endBoundary[0]
                : max($endColumnIndex, $endBoundary[0]);
        }

        if ($startColumnIndex === null || $endColumnIndex === null) {
            return null;
        }

        return [
            'startColumn' => Coordinate::stringFromColumnIndex($startColumnIndex),
            'endColumn' => Coordinate::stringFromColumnIndex($endColumnIndex),
        ];
    }

    /**
     * @param  array{startColumn: string, endColumn: string}  $range
     * @param  array{startColumn: string, endColumn: string}  $printableRange
     * @return array{startColumn: string, endColumn: string}
     */
    private function intersectColumnRanges(
        array $range,
        array $printableRange,
    ): array {
        $startColumnIndex = max(
            Coordinate::columnIndexFromString($range['startColumn']),
            Coordinate::columnIndexFromString($printableRange['startColumn']),
        );
        $endColumnIndex = min(
            Coordinate::columnIndexFromString($range['endColumn']),
            Coordinate::columnIndexFromString($printableRange['endColumn']),
        );

        if ($startColumnIndex > $endColumnIndex) {
            return $printableRange;
        }

        return [
            'startColumn' => Coordinate::stringFromColumnIndex($startColumnIndex),
            'endColumn' => Coordinate::stringFromColumnIndex($endColumnIndex),
        ];
    }

    private function columnRangeWidthInPixels(
        Worksheet $worksheet,
        Spreadsheet $spreadsheet,
        string $startColumn,
        string $endColumn,
    ): int {
        $startColumnIndex = Coordinate::columnIndexFromString($startColumn);
        $endColumnIndex = Coordinate::columnIndexFromString($endColumn);
        $totalWidth = 0;

        if ($endColumnIndex < $startColumnIndex) {
            return 0;
        }

        for ($column = $startColumnIndex; $column <= $endColumnIndex; $column++) {
            $totalWidth += $this->columnWidthInPixels(
                $worksheet,
                $spreadsheet,
                Coordinate::stringFromColumnIndex($column),
            );
        }

        return $totalWidth;
    }

    /**
     * @return array{column: string, offset: int}
     */
    private function resolveDrawingAnchor(
        Worksheet $worksheet,
        Spreadsheet $spreadsheet,
        string $startColumn,
        string $endColumn,
        int $leftOffsetPixels,
    ): array {
        $startColumnIndex = Coordinate::columnIndexFromString($startColumn);
        $endColumnIndex = Coordinate::columnIndexFromString($endColumn);
        $remainingOffset = max(0, $leftOffsetPixels);

        for ($column = $startColumnIndex; $column <= $endColumnIndex; $column++) {
            $columnLetter = Coordinate::stringFromColumnIndex($column);
            $columnWidth = $this->columnWidthInPixels(
                $worksheet,
                $spreadsheet,
                $columnLetter,
            );

            if ($remainingOffset < $columnWidth) {
                return [
                    'column' => $columnLetter,
                    'offset' => $remainingOffset,
                ];
            }

            $remainingOffset -= $columnWidth;
        }

        return [
            'column' => $endColumn,
            'offset' => 0,
        ];
    }

    private function columnWidthInPixels(
        Worksheet $worksheet,
        Spreadsheet $spreadsheet,
        string $column,
    ): int {
        $font = $spreadsheet->getDefaultStyle()->getFont();
        $columnDimension = $worksheet->getColumnDimension($column);
        $columnWidth = (float) $columnDimension->getWidth();

        if ($columnWidth <= 0) {
            $columnWidth = self::HEADER_DEFAULT_COLUMN_WIDTH;
        }

        return SharedDrawing::cellDimensionToPixels(
            $columnWidth,
            $font,
        );
    }

    private function printableWidthInPixels(Worksheet $worksheet): int
    {
        $pageSetup = $worksheet->getPageSetup();
        $paperDimensions = self::PAPER_DIMENSIONS_INCHES[$pageSetup->getPaperSize()]
            ?? [8.5, 11.0];
        [$paperWidth, $paperHeight] = $paperDimensions;

        if ($pageSetup->getOrientation() === PageSetup::ORIENTATION_LANDSCAPE) {
            [$paperWidth, $paperHeight] = [$paperHeight, $paperWidth];
        }

        $printableWidthInches = $paperWidth
            - $worksheet->getPageMargins()->getLeft()
            - $worksheet->getPageMargins()->getRight();

        if ($printableWidthInches <= 0) {
            return self::HEADER_DEFAULT_PRINTABLE_WIDTH_PIXELS;
        }

        return max(1, (int) floor($printableWidthInches * 96));
    }

    private function prepareWorksheetHeaderArea(Worksheet $worksheet): int
    {
        $blankHeaderRowCount = $this->blankHeaderRowCount($worksheet);

        if ($blankHeaderRowCount > 0) {
            return $blankHeaderRowCount;
        }

        $worksheet->insertNewRowBefore(1, self::HEADER_RESERVED_ROWS);

        return self::HEADER_RESERVED_ROWS;
    }

    private function blankHeaderRowCount(Worksheet $worksheet): int
    {
        $firstContentRow = $this->firstContentRow($worksheet);

        if ($firstContentRow === null) {
            return self::HEADER_RESERVED_ROWS;
        }

        return max(0, $firstContentRow - 1);
    }

    private function firstContentRow(Worksheet $worksheet): ?int
    {
        $highestRow = $worksheet->getHighestRow();
        $highestColumnIndex = Coordinate::columnIndexFromString(
            $worksheet->getHighestColumn(),
        );

        for ($row = 1; $row <= $highestRow; $row++) {
            if ($this->rowContainsContent($worksheet, $row, $highestColumnIndex)) {
                return $row;
            }
        }

        return null;
    }

    private function rowContainsContent(
        Worksheet $worksheet,
        int $row,
        int $highestColumnIndex,
    ): bool {
        for ($column = 1; $column <= $highestColumnIndex; $column++) {
            $value = $worksheet->getCell(
                Coordinate::stringFromColumnIndex($column).$row,
            )->getValue();

            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    private function decodeDataUriToTemporaryImage(string $dataUri): ?string
    {
        if (
            preg_match(
                '/^data:(image\\/(png|jpeg|jpg|webp));base64,(.+)$/s',
                $dataUri,
                $matches,
            ) !== 1
        ) {
            return null;
        }

        $decoded = base64_decode($matches[3], true);

        if ($decoded === false) {
            return null;
        }

        $extension = match ($matches[2]) {
            'jpeg', 'jpg' => 'jpg',
            default => $matches[2],
        };
        $directory = storage_path('app/tmp');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.uniqid('approved-loan-header-', true).'.'.$extension;

        File::put($path, $decoded);

        return $path;
    }
}
