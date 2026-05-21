<?php

namespace App\Services\LoanRequests;

use App\Services\LoanRequests\ExcelCellMaps\PlanOfPaymentDisclosurePromissoryNoteExcelCellMap;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;

class ApprovedLoanExcelTemplateService
{
    private const TEMPLATE_DIRECTORY = 'templates/approved-loan-documents/excel';

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

        try {
            $this->applyMappedCells($spreadsheet, $documentData);
            $this->replaceTemplateTokens($spreadsheet, $documentData);
            File::ensureDirectoryExists(dirname($outputPath));
            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($outputPath);
        } finally {
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
}
