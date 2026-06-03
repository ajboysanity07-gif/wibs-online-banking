<?php

namespace App\Services\LoanRequests;

use App\LoanRequestPersonRole;
use App\Models\LoanRequest;
use App\Services\OrganizationSettingsService;
use App\Services\SignaturePngService;
use App\Support\LocationComposer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\Response;

class LoanRequestPdfService
{
    public function __construct(
        private OrganizationSettingsService $brandingService,
        private SignaturePngService $signaturePngService,
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
        $loanRequest->loadMissing(
            'people',
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

        return [
            'loanRequest' => $loanRequest,
            'applicant' => $applicant,
            'coMakerOne' => $coMakerOne,
            'coMakerTwo' => $coMakerTwo,
            'reviewer' => [
                'name' => $loanRequest->reviewedBy?->adminProfile?->fullname
                    ?? $loanRequest->reviewedBy?->name,
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
        $person['signatureData'] = null;

        return $person;
    }

    private function signatureDataUri(?string $path): ?string
    {
        $normalizedPath = $this->normalizeSignaturePath($path);

        if ($normalizedPath === null || ! Storage::disk('public')->exists($normalizedPath)) {
            return null;
        }

        $contents = Storage::disk('public')->get($normalizedPath);
        $mime = Storage::disk('public')->mimeType($normalizedPath)
            ?: $this->resolveImageMimeType($normalizedPath);

        if (strtolower($mime) === 'image/png') {
            $contents = $this->signaturePngService->normalizePngBinary($contents)
                ?? $contents;
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
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

    private function normalizeSignaturePath(?string $value): ?string
    {
        $normalizedPath = $this->blank($value);

        if ($normalizedPath === null) {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $normalizedPath) === 1) {
            $parsedPath = parse_url($normalizedPath, PHP_URL_PATH);
            $normalizedPath = is_string($parsedPath) ? $parsedPath : '';
        }

        $normalizedPath = str_replace('\\', '/', rawurldecode($normalizedPath));
        $normalizedPath = explode('?', $normalizedPath, 2)[0];
        $normalizedPath = explode('#', $normalizedPath, 2)[0];
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
        $normalizedPath = ltrim($normalizedPath, '/');

        return $normalizedPath !== '' ? $normalizedPath : null;
    }

    private function blank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
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
