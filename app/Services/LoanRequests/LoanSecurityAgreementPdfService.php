<?php

namespace App\Services\LoanRequests;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Throwable;

class LoanSecurityAgreementPdfService
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
            ->setPaper('letter', 'portrait')
            ->loadView('reports.loan-security-agreement', $this->buildViewData(
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
        ) ?? 'LOAN SECURITY AGREEMENT';

        $reportHeader['companyName'] = $companyName;
        $reportHeader['designData'] = is_string(
            $reportHeader['designData'] ?? null,
        )
            ? $reportHeader['designData']
            : null;

        $applicant = $documentData['applicant'] ?? [];
        $applicant = is_array($applicant) ? $applicant : [];
        $applicant['signature_data'] = $this->signatureDataUri(
            is_string($applicant['signature_path'] ?? null)
                ? $applicant['signature_path']
                : null,
        );

        return [
            ...$documentData,
            'organization' => $organization,
            'applicant' => $applicant,
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
            'placeOfSigning' => $this->resolvePlaceOfSigning($applicant),
        ];
    }

    /**
     * @param  array<string, mixed>  $documentData
     */
    private function saveChromiumPdf(array $documentData, string $outputPath): void
    {
        $html = view(
            'reports.loan-security-agreement',
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

    /**
     * @param  array<string, mixed>  $applicant
     */
    private function resolvePlaceOfSigning(array $applicant): ?string
    {
        $city = $this->blank(
            is_string($applicant['address_city'] ?? null)
                ? $applicant['address_city']
                : null,
        );
        $province = $this->blank(
            is_string($applicant['address_province'] ?? null)
                ? $applicant['address_province']
                : null,
        );
        $parts = array_values(array_filter([$city, $province]));

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }

    private function signatureDataUri(?string $path): ?string
    {
        $path = $this->blank($path);

        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $contents = Storage::disk('public')->get($path);
        $mimeType = $this->resolveImageMimeType($path);

        return sprintf(
            'data:%s;base64,%s',
            $mimeType,
            base64_encode($contents),
        );
    }

    private function resolveImageMimeType(string $path): string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
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
