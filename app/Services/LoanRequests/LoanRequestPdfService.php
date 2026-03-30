<?php

namespace App\Services\LoanRequests;

use App\LoanRequestPersonRole;
use App\Models\LoanRequest;
use App\Services\OrganizationSettingsService;
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
    ) {}

    public function render(LoanRequest $loanRequest, bool $download = false): Response
    {
        $data = $this->buildViewData($loanRequest);
        $filename = $this->buildFilename($loanRequest, $data['applicant']);

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
        $loanRequest->loadMissing('people', 'user');

        $applicant = $this->resolvePerson($loanRequest, LoanRequestPersonRole::Applicant);
        $coMakerOne = $this->resolvePerson($loanRequest, LoanRequestPersonRole::CoMakerOne);
        $coMakerTwo = $this->resolvePerson($loanRequest, LoanRequestPersonRole::CoMakerTwo);
        $branding = $this->brandingService->branding();
        $logoData = $this->brandingService->logoDataUri();
        $reportHeader = $branding['reportHeader'] ?? [];
        $reportHeader['showCompanyName'] = ($reportHeader['showCompanyName'] ?? true)
            && ! ($branding['logoIsWordmark'] ?? false);
        $reportHeader['showLogo'] = $reportHeader['showLogo'] ?? true;
        $reportHeader['alignment'] = $reportHeader['alignment'] ?? 'center';
        $reportHeader['companyName'] = $branding['companyName'] ?? '';
        $reportHeader['logoData'] = $logoData;
        $reportHeader['titleColor'] = $branding['reportTypography']['headerTitle']['color']
            ?? null;
        $reportHeader['taglineColor'] = $branding['reportTypography']['headerTagline']['color']
            ?? null;

        return [
            'loanRequest' => $loanRequest,
            'applicant' => $applicant,
            'coMakerOne' => $coMakerOne,
            'coMakerTwo' => $coMakerTwo,
            'companyName' => $branding['companyName'],
            'logoData' => $logoData,
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
        );
        $address = $address !== '' ? $address : ($person['address'] ?? null);
        $employerBusinessAddress = LocationComposer::compose(
            $person['employer_business_address1'] ?? null,
            $person['employer_business_address2'] ?? null,
            $person['employer_business_address3'] ?? null,
        );
        $employerBusinessAddress = $employerBusinessAddress !== ''
            ? $employerBusinessAddress
            : ($person['employer_business_address'] ?? null);

        $person['birthplace'] = $birthplace;
        $person['address'] = $address;
        $person['employer_business_address'] = $employerBusinessAddress;

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

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderWithChromium(
        array $data,
        string $filename,
        bool $download,
    ): Response {
        $html = view('reports.loan-request', $data)->render();
        $path = $this->makePdfTempPath('loan-request');
        [$width, $height, $unit] = $this->resolvePaperSize();

        try {
            $shot = Browsershot::html($html)
                ->showBackground()
                ->emulateMedia('print')
                ->waitUntilNetworkIdle()
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

    /**
     * @param  array<string, mixed>  $applicant
     */
    private function buildFilename(LoanRequest $loanRequest, array $applicant): string
    {
        $fullName = trim(sprintf(
            '%s %s',
            $applicant['first_name'] ?? '',
            $applicant['last_name'] ?? '',
        ));

        $slug = $fullName !== '' ? Str::slug($fullName) : 'member';
        $date = $loanRequest->submitted_at?->format('Y-m-d') ?? now()->toDateString();

        return sprintf('%s-loan-request-%s.pdf', $slug, $date);
    }
}
