<?php

namespace App\Services\LoanRequests;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Spatie\Browsershot\Browsershot;
use Throwable;

class PdcSchedulePdfService
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
            ->loadView('reports.pdc-schedule', $this->buildViewData($documentData));

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
        ) ?? 'Post-Dated Checks Schedule';

        $reportHeader['companyName'] = $companyName;
        $reportHeader['designData'] = is_string(
            $reportHeader['designData'] ?? null,
        )
            ? $reportHeader['designData']
            : null;

        $applicant = $documentData['applicant'] ?? [];
        $applicant = is_array($applicant) ? $applicant : [];

        $loan = $documentData['loan'] ?? [];
        $loan = is_array($loan) ? $loan : [];

        $pdc = $documentData['pdc'] ?? [];
        $pdc = is_array($pdc) ? $pdc : [];

        return [
            ...$documentData,
            'organization' => $organization,
            'applicant' => $applicant,
            'loan' => $loan,
            'pdc' => $pdc,
            'schedule' => $this->buildAmortizationSchedule($loan),
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
     * Builds a true declining-balance amortization schedule for the checks,
     * unlike the flat/add-on figures (amortization_*_raw) shared by the other
     * loan documents: the periodic payment is constant but its principal/
     * interest split shifts each check as the outstanding balance shrinks.
     *
     * @param  array<string, mixed>  $loan
     * @return array{rows: list<array<string, float>>, totals: array<string, float|null>}
     */
    private function buildAmortizationSchedule(array $loan): array
    {
        $emptyTotals = ['principal' => null, 'interest' => null, 'loan_security' => null, 'total' => null];

        $principal = is_numeric($loan['approved_amount_raw'] ?? null) ? (float) $loan['approved_amount_raw'] : null;
        $annualRate = is_numeric($loan['interest_rate_raw'] ?? null) ? (float) $loan['interest_rate_raw'] : null;
        $count = is_numeric($loan['amortization_count'] ?? null) ? (int) $loan['amortization_count'] : null;
        $paymentMode = is_string($loan['payment_mode_workbook'] ?? null) ? $loan['payment_mode_workbook'] : null;
        $lumpsumMonths = is_numeric($loan['lumpsum_months'] ?? null) ? (int) $loan['lumpsum_months'] : null;
        $savingsRate = is_numeric($loan['savings_rate_raw'] ?? null) ? (float) $loan['savings_rate_raw'] : 0.0;

        if ($principal === null || $annualRate === null || $count === null || $count <= 0) {
            return ['rows' => [], 'totals' => $emptyTotals];
        }

        $periodsPerYear = match ($paymentMode) {
            'DAILY' => 360.0,
            'QUINCENAL' => 24.0,
            'SEMI-ANNUAL' => 2.0,
            'WEEKLY' => 52.0,
            'YEARLY' => 1.0,
            'DUE-DATE' => $lumpsumMonths !== null && $lumpsumMonths > 0 ? 12.0 / $lumpsumMonths : 12.0,
            default => 12.0,
        };

        $periodicRate = $annualRate / $periodsPerYear;

        if ($periodicRate > 0) {
            $factor = (1 + $periodicRate) ** $count;
            $payment = $principal * $periodicRate * $factor / ($factor - 1);
        } else {
            $payment = $principal / $count;
        }

        // The payment is fixed to a whole peso once, up front, rather than
        // recomputed from the raw float every row. It's rounded UP (not to
        // nearest) so every regular check is never a peso short of covering
        // its due interest — the shortfall from rounding up is absorbed by
        // a smaller final balloon payment instead.
        $payment = ceil($payment);

        $rows = [];
        $balance = $principal;
        $totals = ['principal' => 0.0, 'interest' => 0.0, 'loan_security' => 0.0, 'total' => 0.0];

        // Loan security is spread evenly across every check (like the flat/
        // add-on figures elsewhere), not scaled to that row's principal —
        // otherwise it would rise alongside the growing principal share
        // instead of staying flat.
        $totalLoanSecurity = round($principal * $savingsRate, 0);
        $flatLoanSecurity = $count > 0 ? round($totalLoanSecurity / $count, 0) : 0.0;

        // Checks are written for whole-peso amounts (no centavos), so every
        // figure is rounded to the nearest peso — not just for display, but
        // immediately in the loop, so $balance is always a whole-peso value
        // and each row's interest is computed from that rounded balance
        // rather than an accumulating float. This is what keeps the final
        // row's principal (the exact remaining balance) reconciling to zero
        // with no drift.
        for ($i = 1; $i <= $count; $i++) {
            $interestDue = round($balance * $periodicRate, 0);
            $principalDue = $i === $count
                ? round($balance, 0)
                : round($payment - $interestDue, 0);
            $loanSecurityDue = $i === $count
                ? round($totalLoanSecurity - $totals['loan_security'], 0)
                : $flatLoanSecurity;
            $totalDue = round($principalDue + $interestDue + $loanSecurityDue, 0);

            $balance = round($balance - $principalDue, 0);

            $rows[] = [
                'principal' => $principalDue,
                'interest' => $interestDue,
                'loan_security' => $loanSecurityDue,
                'total' => $totalDue,
            ];

            $totals['principal'] += $principalDue;
            $totals['interest'] += $interestDue;
            $totals['loan_security'] += $loanSecurityDue;
            $totals['total'] += $totalDue;
        }

        return [
            'rows' => $rows,
            'totals' => array_map(static fn (float $value): float => round($value, 0), $totals),
        ];
    }

    /**
     * @param  array<string, mixed>  $documentData
     */
    private function saveChromiumPdf(array $documentData, string $outputPath): void
    {
        $html = view(
            'reports.pdc-schedule',
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
