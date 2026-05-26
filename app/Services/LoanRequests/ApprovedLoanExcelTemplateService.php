<?php

namespace App\Services\LoanRequests;

use App\Services\LoanRequests\ExcelCellMaps\PlanOfPaymentDisclosurePromissoryNoteExcelCellMap;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as SharedDrawing;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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
    ) {}

    public function generate(
        string $templateFilename,
        string $outputPath,
        array $documentData,
    ): void {
        $templatePath = $this->resolveTemplatePath($templateFilename);
        $spreadsheet = IOFactory::load($templatePath);
        $temporaryHeaderImagePath = null;

        try {
            $this->applyMappedCells($spreadsheet, $documentData);
            $this->replaceTemplateTokens($spreadsheet, $documentData);
            $this->prepareWorksheetLayouts($spreadsheet);
            $temporaryHeaderImagePath = $this->insertReportHeaderImages(
                $spreadsheet,
                $documentData,
            );
            File::ensureDirectoryExists(dirname($outputPath));
            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($outputPath);
        } finally {
            if (is_string($temporaryHeaderImagePath) && $temporaryHeaderImagePath !== '') {
                File::delete($temporaryHeaderImagePath);
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
