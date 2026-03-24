<?php

namespace App\Services\LoanRequests;

use App\LoanRequestPersonRole;
use App\Models\LoanRequest;
use App\Services\OrganizationSettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LoanRequestPdfService
{
    public function __construct(
        private OrganizationSettingsService $brandingService,
    ) {}

    public function render(LoanRequest $loanRequest, bool $download = false): Response
    {
        $loanRequest->loadMissing('people', 'user');

        $applicant = $this->resolvePerson($loanRequest, LoanRequestPersonRole::Applicant);
        $coMakerOne = $this->resolvePerson($loanRequest, LoanRequestPersonRole::CoMakerOne);
        $coMakerTwo = $this->resolvePerson($loanRequest, LoanRequestPersonRole::CoMakerTwo);
        $logoData = $this->brandingService->logoDataUri();
        $branding = $this->brandingService->branding();
        $showCompanyName = ! ($branding['logoIsWordmark'] ?? false);

        $pdf = Pdf::setOption('isPhpEnabled', true)
            ->loadView('reports/loan-request', [
                'loanRequest' => $loanRequest,
                'applicant' => $applicant,
                'coMakerOne' => $coMakerOne,
                'coMakerTwo' => $coMakerTwo,
                'companyName' => $branding['companyName'],
                'logoData' => $logoData,
                'showCompanyName' => $showCompanyName,
                'generatedAt' => Carbon::now(),
            ]);

        $filename = $this->buildFilename($loanRequest, $applicant);

        return $download ? $pdf->download($filename) : $pdf->stream($filename);
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

        return $person->toArray();
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
