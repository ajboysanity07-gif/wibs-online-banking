<?php

namespace App\Services\LoanRequests;

use App\LoanRequestPersonRole;
use App\Models\LoanRequest;
use App\Services\OrganizationSettingsService;
use App\Support\DocumentFilename;
use App\Support\LocationComposer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\Response;

class LoanRequestPdfService
{
    public function __construct(
        private OrganizationSettingsService $brandingService,
        private OfficialLoanManagerResolver $officialLoanManagerResolver,
    ) {}

    public function render(LoanRequest $loanRequest, bool $download = false): Response
    {
        $data = $this->buildViewData($loanRequest);
        $filename = $this->buildFilename($loanRequest);

        if ($this->shouldUseChromium()) {
            return $this->renderWithChromium($data, $filename, $download);
        }

        return $this->renderWithDompdf($data, $filename, $download);
    }

    public function renderPrintView(LoanRequest $loanRequest): View
    {
        return view('reports.loan-request-print', $this->buildViewData($loanRequest));
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePerson(
        LoanRequest $loanRequest,
        LoanRequestPersonRole $role,
    ): array {
        $person = $loanRequest->people
            ->first(fn ($item) => $item->role === $role);

        if ($person === null) {
            return [];
        }

        return $this->normalizePersonForReport($person->toArray());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildViewData(LoanRequest $loanRequest): array
    {
        $loanRequest->loadMissing(
            'people',
            'assignedProcessor.adminProfile',
            'reviewedBy.adminProfile',
            'user',
        );

        $applicant = $this->resolvePerson($loanRequest, LoanRequestPersonRole::Applicant);
        $coMakerOne = $this->resolvePerson($loanRequest, LoanRequestPersonRole::CoMakerOne);
        $coMakerTwo = $this->resolvePerson($loanRequest, LoanRequestPersonRole::CoMakerTwo);
        $branding = $this->brandingService->branding();
        $reportHeader = $branding['reportHeader'] ?? [];
        $reportHeader['companyName'] = $branding['companyName'] ?? '';
        $reportHeader['designData'] = $reportHeader['designData'] ?? null;
        $officialLoanManager = $this->officialLoanManagerResolver->documentData();
        $processorName = $loanRequest->assignedProcessor?->adminProfile?->fullname
            ?? $loanRequest->assignedProcessor?->name
            ?? $loanRequest->assignedProcessor?->username;

        return [
            'loanRequest' => $loanRequest,
            'applicant' => $applicant,
            'coMakerOne' => $coMakerOne,
            'coMakerTwo' => $coMakerTwo,
            'processor' => [
                'name' => $processorName,
                'position' => $processorName !== null ? 'Loan Processor' : null,
                'signatureData' => null,
            ],
            'reviewer' => [
                'name' => $officialLoanManager['name'],
                'position' => $officialLoanManager['position'],
                'signatureData' => null,
            ],
            'reviewerSignatureData' => null,
            'companyName' => $branding['companyName'],
            'reportHeader' => $reportHeader,
            'reportTypography' => $branding['reportTypography'] ?? [],
            'generatedAt' => Carbon::now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    private function normalizePersonForReport(array $person): array
    {
        $birthplace = LocationComposer::composeBirthplace(
            $person['birthplace_city'] ?? null,
            $person['birthplace_province'] ?? null,
        );
        $birthplace = $birthplace !== '' ? $birthplace : ($person['birthplace'] ?? null);
        $address = LocationComposer::compose(
            $person['address1'] ?? null,
            $person['address2'] ?? null,
            $person['address3'] ?? null,
            $person['address_barangay'] ?? null,
        );
        $address = $address !== '' ? $address : ($person['address'] ?? null);
        $employerBusinessAddress = LocationComposer::compose(
            $person['employer_business_address1'] ?? null,
            $person['employer_business_address2'] ?? null,
            $person['employer_business_address3'] ?? null,
            $person['employer_business_address_barangay'] ?? null,
        );
        $employerBusinessAddress = $employerBusinessAddress !== ''
            ? $employerBusinessAddress
            : ($person['employer_business_address'] ?? null);

        $person['birthplace'] = $birthplace;
        $person['address'] = $address;
        $person['employer_business_address'] = $employerBusinessAddress;
        $person['signatureData'] = null;

        return $person;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderWithDompdf(
        array $data,
        string $filename,
        bool $download,
    ): Response {
        $pdf = Pdf::setOption('isPhpEnabled', true)
            ->setPaper($this->resolveDompdfPaper())
            ->loadView('reports/loan-request', $data);

        return $download ? $pdf->download($filename) : $pdf->stream($filename);
    }

    public function saveToPath(LoanRequest $loanRequest, string $path): void
    {
        File::ensureDirectoryExists(dirname($path));

        $data = $this->buildViewData($loanRequest);

        if ($this->shouldUseChromium()) {
            try {
                $this->saveChromiumPdf($data, $path);
            } catch (\Throwable $exception) {
                if (is_file($path)) {
                    @unlink($path);
                }

                throw $exception;
            }

            return;
        }

        $pdf = Pdf::setOption('isPhpEnabled', true)
            ->setPaper($this->resolveDompdfPaper())
            ->loadView('reports/loan-request', $data);

        File::put($path, $pdf->output());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderWithChromium(
        array $data,
        string $filename,
        bool $download,
    ): Response {
        $path = $this->makePdfTempPath('loan-request');

        try {
            $this->saveChromiumPdf($data, $path);

            if ($download) {
                return response()
                    ->download($path, $filename, [
                        'Content-Type' => 'application/pdf',
                    ])
                    ->deleteFileAfterSend(true);
            }

            return response()
                ->file($path, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="'.$filename.'"',
                ])
                ->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            if (is_file($path)) {
                @unlink($path);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveChromiumPdf(array $data, string $path): void
    {
        $html = view('reports.loan-request', $data)->render();
        [$width, $height, $unit] = $this->resolvePaperSize();

        $shot = Browsershot::html($html)
            ->showBackground()
            ->emulateMedia('print')
            ->waitForFunction(
                '!document.fonts || document.fonts.status === "loaded"',
                null,
                5000,
            )
            ->paperSize($width, $height, $unit)
            ->margins(0, 0, 0, 0);

        if (config('reports.chromium.no_sandbox', true)) {
            $shot->noSandbox();
        }

        $timeout = (int) config('reports.chromium.timeout', 120);
        if ($timeout > 0) {
            $shot->timeout($timeout);
        }

        $shot->savePdf($path);
    }

    private function shouldUseChromium(): bool
    {
        return config('reports.pdf_driver', 'chromium') === 'chromium';
    }

    private function makePdfTempPath(string $prefix): string
    {
        $directory = storage_path('app/tmp');

        File::ensureDirectoryExists($directory);

        return sprintf('%s/%s-%s.pdf', $directory, $prefix, Str::uuid());
    }

    /**
     * @return array{0: float, 1: float, 2: string}
     */
    private function resolvePaperSize(): array
    {
        $width = (float) config('reports.paper.width', 8.5);
        $height = (float) config('reports.paper.height', 13);
        $unit = (string) config('reports.paper.unit', 'in');
        $unit = in_array($unit, ['in', 'mm', 'cm'], true) ? $unit : 'in';

        return [$width, $height, $unit];
    }

    /**
     * @return array<int, float>
     */
    private function resolveDompdfPaper(): array
    {
        [$width, $height, $unit] = $this->resolvePaperSize();
        $pointsPerUnit = match ($unit) {
            'mm' => 72 / 25.4,
            'cm' => 72 / 2.54,
            default => 72,
        };

        return [0, 0, $width * $pointsPerUnit, $height * $pointsPerUnit];
    }

    private function buildFilename(LoanRequest $loanRequest): string
    {
        return DocumentFilename::build(
            $loanRequest->reference,
            'application_form',
            'pdf',
            $loanRequest->submitted_at,
        );
    }
}
