<?php

namespace App\Services\LoanRequests;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\Browsershot\Browsershot;
use Throwable;

class AuthorityToDeductPdfService
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
            ->setPaper([0, 0, 612, 792], 'portrait')
            ->loadView('reports.authority-to-deduct', $this->buildViewData(
                $documentData,
            ));

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
        ) ?? 'Authority to Deduct';

        $reportHeader['companyName'] = $companyName;
        $reportHeader['designData'] = is_string(
            $reportHeader['designData'] ?? null,
        )
            ? $reportHeader['designData']
            : null;

        $applicant = $documentData['applicant'] ?? [];
        $applicant = is_array($applicant) ? $applicant : [];
        $applicant['signature_data'] = null;

        $loan = $documentData['loan'] ?? [];
        $loan = is_array($loan) ? $loan : [];

        return [
            ...$documentData,
            'organization' => $organization,
            'applicant' => $applicant,
            'loan' => $loan,
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
            'institution' => $this->buildInstitution($documentData),
        ];
    }

    /**
     * Institution name and signing officer(s) are entered manually by staff (via the
     * "authority_to_deduct_*" processing fields) rather than guessed from the
     * applicant's employer name, so any institution can be represented -- not just
     * the handful with real signed templates on file.
     *
     * @param  array<string, mixed>  $documentData
     * @return array{name: ?string, officers: list<array{name: string, title: string}>}
     */
    private function buildInstitution(array $documentData): array
    {
        $authorityToDeduct = $documentData['authority_to_deduct'] ?? [];
        $authorityToDeduct = is_array($authorityToDeduct) ? $authorityToDeduct : [];

        $officers = [];

        foreach ([1, 2] as $slot) {
            $name = $this->blank(
                is_string($authorityToDeduct["officer_{$slot}_name"] ?? null)
                    ? $authorityToDeduct["officer_{$slot}_name"]
                    : null,
            );

            if ($name === null) {
                continue;
            }

            $title = $this->blank(
                is_string($authorityToDeduct["officer_{$slot}_title"] ?? null)
                    ? $authorityToDeduct["officer_{$slot}_title"]
                    : null,
            ) ?? 'Authorized Representative';

            $officers[] = ['name' => $name, 'title' => $title];
        }

        return [
            'name' => $this->blank(
                is_string($authorityToDeduct['institution_name'] ?? null)
                    ? $authorityToDeduct['institution_name']
                    : null,
            ),
            'officers' => $officers,
        ];
    }

    /**
     * @param  array<string, mixed>  $documentData
     */
    private function saveChromiumPdf(array $documentData, string $outputPath): void
    {
        $html = view(
            'reports.authority-to-deduct',
            $this->buildViewData($documentData),
        )->render();

        $shot = Browsershot::html($html)
            ->showBackground()
            ->emulateMedia('print')
            ->waitForFunction(
                '!document.fonts || document.fonts.status === "loaded"',
                null,
                5000,
            )
            ->paperSize(8.5, 11, 'in')
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
