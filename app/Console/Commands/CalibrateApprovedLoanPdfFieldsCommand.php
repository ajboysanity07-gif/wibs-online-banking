<?php

namespace App\Console\Commands;

use App\Services\LoanRequests\PdfFieldMaps\AffidavitUndertakingPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\ApprovedLoanPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\GeneraliPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\GrepalifePdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\LoanInformationPdfFieldMap;
use App\Services\LoanRequests\PdfFieldMaps\UndertakingBarangayPdfFieldMap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use setasign\Fpdi\Tcpdf\Fpdi;
use TCPDF;

class CalibrateApprovedLoanPdfFieldsCommand extends Command
{
    protected $signature = 'loan-documents:calibrate-fields
                            {document : Document key: au, ub, li, ge, or gl}
                            {--output= : Override output path (default: storage/app/tmp/calibrate-{doc}.pdf)}';

    protected $description = 'Generate a calibration PDF overlaying field boxes and an mm grid on the template.';

    private const PDF_TEMPLATES = [
        'au' => [
            'file' => 'affidavit-undertaking.pdf',
            'label' => 'Affidavit of Undertaking',
            'field_map' => AffidavitUndertakingPdfFieldMap::class,
        ],
        'ub' => [
            'file' => 'undertaking-barangay-officials.pdf',
            'label' => 'Undertaking – Barangay Officials',
            'field_map' => UndertakingBarangayPdfFieldMap::class,
        ],
        'li' => [
            'file' => 'loan information sheet.pdf',
            'label' => 'Loan Information Sheet',
            'field_map' => LoanInformationPdfFieldMap::class,
        ],
        // 'gl' is already taken by GREPALIFE's image-template calibration branch below.
        'ge' => [
            'file' => 'generali.pdf',
            'label' => 'Generali (GLAPI) Health Statement',
            'field_map' => GeneraliPdfFieldMap::class,
        ],
    ];

    private const GL_PAGES = [
        ['image' => 'grepalife-page-1.png', 'width' => 216.0, 'height' => 279.0],
        ['image' => 'grepalife-page-2.png', 'width' => 216.0, 'height' => 279.0],
    ];

    public function handle(): int
    {
        $doc = strtolower((string) $this->argument('document'));

        if (isset(self::PDF_TEMPLATES[$doc])) {
            return $this->calibratePdfTemplate($doc);
        }

        if ($doc === 'gl') {
            return $this->calibrateImageTemplate();
        }

        $this->error('Unknown document. Valid keys: au, az, ub, li, gl');

        return Command::FAILURE;
    }

    private function calibratePdfTemplate(string $doc): int
    {
        $config = self::PDF_TEMPLATES[$doc];
        $templatePath = storage_path(
            'app/templates/approved-loan-documents/pdf/'.$config['file'],
        );

        if (! is_file($templatePath)) {
            $this->error('Template PDF not found: '.$templatePath);

            return Command::FAILURE;
        }

        $fieldMap = new $config['field_map'];
        $fieldsByPage = $this->groupByPage($fieldMap);

        $pdf = $this->makeFpdi();
        $pageCount = $pdf->setSourceFile($templatePath);

        for ($page = 1; $page <= $pageCount; $page++) {
            $templateId = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($templateId);
            $w = (float) ($size['width'] ?? 210);
            $h = (float) ($size['height'] ?? 297);

            $pdf->AddPage($w > $h ? 'L' : 'P', [$w, $h]);
            $pdf->useTemplate($templateId);
            $this->drawGrid($pdf, $w, $h);
            $this->drawFields($pdf, $fieldsByPage[$page] ?? []);
        }

        return $this->saveAndReport($pdf->Output('', 'S'), $doc);
    }

    private function calibrateImageTemplate(): int
    {
        $fieldsByPage = $this->groupByPage(new GrepalifePdfFieldMap);
        $pdf = $this->makeTcpdf();

        foreach (self::GL_PAGES as $index => $pageConfig) {
            $page = $index + 1;
            $w = (float) $pageConfig['width'];
            $h = (float) $pageConfig['height'];
            $imagePath = $this->resolveImageTemplate($pageConfig['image']);

            $pdf->AddPage('P', [$w, $h]);

            if ($imagePath !== null) {
                $pdf->Image($imagePath, 0, 0, $w, $h, '', '', '', false, 300);
            } else {
                $this->warn("Image not found: {$pageConfig['image']} – drawing grid only");
            }

            $this->drawGrid($pdf, $w, $h);
            $this->drawFields($pdf, $fieldsByPage[$page] ?? []);
        }

        return $this->saveAndReport($pdf->Output('', 'S'), 'gl');
    }

    private function drawGrid(TCPDF $pdf, float $pageWidth, float $pageHeight): void
    {
        $pdf->SetFont('helvetica', '', 3.0);

        // Minor grid: every 5 mm, pale blue
        $pdf->SetDrawColor(210, 225, 245);
        $pdf->SetLineWidth(0.05);
        $pdf->SetTextColor(210, 225, 245);

        for ($x = 5; $x < $pageWidth; $x += 10) {
            $pdf->Line($x, 0, $x, $pageHeight);
        }

        for ($y = 5; $y < $pageHeight; $y += 10) {
            $pdf->Line(0, $y, $pageWidth, $y);
        }

        // Major grid: every 10 mm, medium blue with labels
        $pdf->SetDrawColor(160, 190, 230);
        $pdf->SetLineWidth(0.12);
        $pdf->SetTextColor(120, 150, 200);

        for ($x = 10; $x < $pageWidth; $x += 10) {
            $pdf->Line($x, 0, $x, $pageHeight);
            $pdf->Text($x + 0.4, 1.5, (string) $x);
        }

        for ($y = 10; $y < $pageHeight; $y += 10) {
            $pdf->Line(0, $y, $pageWidth, $y);
            $pdf->Text(0.5, $y + 0.5, (string) $y);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    private function drawFields(TCPDF $pdf, array $fields): void
    {
        // Cycle through distinct colors per field
        $palette = [
            [220, 50, 50],
            [50, 150, 50],
            [50, 50, 220],
            [200, 110, 0],
            [140, 0, 200],
            [0, 160, 180],
            [180, 0, 120],
        ];
        $ci = 0;

        foreach ($fields as $field) {
            $x = (float) ($field['x'] ?? 0);
            $y = (float) ($field['y'] ?? 0);
            $size = (int) ($field['size'] ?? 7);
            $width = isset($field['width']) ? (float) $field['width'] : null;
            $lineHeight = (float) ($field['line_height'] ?? ($size * 0.353 * 1.4));
            $type = (string) ($field['type'] ?? 'text');
            $value = $field['value'] ?? '?';

            [$r, $g, $b] = $palette[$ci % count($palette)];
            $ci++;

            $boxWidth = $width ?? 40.0;
            $boxHeight = $lineHeight;

            // Draw field bounding box
            $pdf->SetDrawColor($r, $g, $b);
            $pdf->SetFillColor($r, $g, $b);
            $pdf->SetLineWidth(0.25);
            $pdf->Rect($x, $y, $boxWidth, $boxHeight, 'D');

            // Draw a small filled dot at the anchor point
            $pdf->Rect($x - 0.5, $y - 0.5, 1.0, 1.0, 'F');

            // Label: field name and coordinates above the box
            $label = is_string($value)
                ? $this->shortFieldName($value)
                : '['.$type.']';
            $coords = sprintf('(%.1f, %.1f) sz:%d', $x, $y, $size);

            $pdf->SetFont('helvetica', '', 3.5);
            $pdf->SetTextColor($r, $g, $b);
            $labelY = max(1.0, $y - 1.8);
            $pdf->Text($x, $labelY, $label.' '.$coords);
        }
    }

    private function shortFieldName(string $path): string
    {
        $parts = explode('.', $path);

        return count($parts) >= 2
            ? $parts[count($parts) - 2].'.'.$parts[count($parts) - 1]
            : $path;
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    private function groupByPage(ApprovedLoanPdfFieldMap $fieldMap): array
    {
        $byPage = [];

        foreach ($fieldMap->fields() as $field) {
            $byPage[(int) ($field['page'] ?? 1)][] = $field;
        }

        return $byPage;
    }

    private function saveAndReport(string $bytes, string $doc): int
    {
        $outputPath = $this->option('output') ?: storage_path("app/tmp/calibrate-{$doc}.pdf");

        File::ensureDirectoryExists(dirname((string) $outputPath));
        File::put((string) $outputPath, $bytes);

        $this->info("Calibration PDF → {$outputPath}");
        $this->line('Open in a PDF viewer and compare field boxes against the template form fields.');

        return Command::SUCCESS;
    }

    private function resolveImageTemplate(string $imageName): ?string
    {
        $candidates = [
            resource_path('templates/approved-loan-documents/images/'.$imageName),
            storage_path('app/templates/approved-loan-documents/images/'.$imageName),
            storage_path('app/public/app/templates/approved-loan-documents/images/'.$imageName),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function makeFpdi(): Fpdi
    {
        $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0, true);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetCompression(false);

        return $pdf;
    }

    private function makeTcpdf(): TCPDF
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
