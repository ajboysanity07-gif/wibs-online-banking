<?php

namespace App\Services\LoanRequests;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\Browsershot\Browsershot;
use Throwable;

class LoanInformationPdfService
{
    /**
     * @param  array<string, mixed>  $documentData
     */
    public function generate(string $outputPath, array $documentData): void
    {
        File::ensureDirectoryExists(dirname($outputPath));

        if ($this->shouldUseChromium()) {
            try {
                $this->saveChromiumPdf($documentData, $outputPath);
            } catch (Throwable $exception) {
                if (is_file($outputPath)) {
                    @unlink($outputPath);
                }

                throw $exception;
            }

            return;
        }

        $pdf = Pdf::setOption('isPhpEnabled', true)
            ->setOption('isFontSubsettingEnabled', false)
            ->setPaper([0, 0, 612, 936], 'portrait')
            ->loadView('reports.loan-information', $this->buildViewData($documentData));

        File::put($outputPath, $pdf->output());
    }

    /**
     * @param  array<string, mixed>  $documentData
     * @return array<string, mixed>
     */
    private function buildViewData(array $documentData): array
    {
        $organization = $documentData['organization'] ?? [];
        $organization = is_array($organization) ? $organization : [];
        $reportHeader = $organization['report_header'] ?? [];
        $reportHeader = is_array($reportHeader) ? $reportHeader : [];
        $companyName = $this->blank(
            is_string($organization['company_name'] ?? null)
                ? $organization['company_name']
                : null,
        ) ?? 'LOAN INFORMATION';

        $reportHeader['companyName'] = $companyName;
        $reportHeader['designData'] = is_string(
            $reportHeader['designData'] ?? null,
        )
            ? $reportHeader['designData']
            : null;

        return [
            ...$documentData,
            'organization' => $organization,
            'reportHeader' => $reportHeader,
            'reportTypography' => is_array(
                $organization['report_typography'] ?? null,
            )
                ? $organization['report_typography']
                : [],
            'organizationLogoDataUri' => is_string(
                $organization['logo_data_uri'] ?? null,
            )
                ? $organization['logo_data_uri']
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $documentData
     */
    private function saveChromiumPdf(array $documentData, string $outputPath): void
    {
        $html = view(
            'reports.loan-information',
            $this->buildViewData($documentData),
        )->render();

        $shot = Browsershot::html($html)
            ->showBackground()
            ->emulateMedia('print')
            ->waitUntilNetworkIdle()
            ->waitForFunction(
                '!document.fonts || document.fonts.status === "loaded"',
                null,
                5000,
            )
            ->paperSize(8.5, 13, 'in')
            ->margins(0, 0, 0, 0);

        if (config('reports.chromium.no_sandbox', true)) {
            $shot->noSandbox();
        }

        $timeout = (int) config('reports.chromium.timeout', 120);
        if ($timeout > 0) {
            $shot->timeout($timeout);
        }

        $shot->savePdf($outputPath);
    }

    private function shouldUseChromium(): bool
    {
        return config('reports.pdf_driver', 'chromium') === 'chromium';
    }

    private function blank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
