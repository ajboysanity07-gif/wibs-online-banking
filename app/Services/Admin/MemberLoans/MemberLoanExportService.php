<?php

namespace App\Services\Admin\MemberLoans;

use App\Models\AppUser;
use App\Services\Admin\MemberLoans\Exports\LoanPaymentsExport;
use App\Services\OrganizationSettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Symfony\Component\HttpFoundation\Response;

class MemberLoanExportService
{
    public function __construct(
        private MemberLoanService $loanService,
        private OrganizationSettingsService $brandingService,
    ) {}

    public function exportPayments(
        AppUser $member,
        string $loanNumber,
        string $format,
        ?string $range,
        ?string $start,
        ?string $end,
        bool $download = false,
    ): Response {
        $context = $this->buildPaymentsReportContext(
            $member,
            $loanNumber,
            $range,
            $start,
            $end,
        );
        $payload = $context['payload'];
        $memberName = $context['memberName'];
        $reportPeriod = $context['reportPeriod'];

        $filename = $this->buildFilename(
            $memberName,
            $reportPeriod['start'],
            $reportPeriod['end'],
            $format,
        );

        if ($format === 'pdf') {
            return $this->exportPdf(
                $payload,
                $memberName,
                $reportPeriod,
                $filename,
                $download,
            );
        }

        if ($format === 'csv') {
            return ExcelFacade::download(
                new LoanPaymentsExport($payload['payments']),
                $filename,
                Excel::CSV,
            );
        }

        if (! extension_loaded('zip')) {
            abort(500, 'The ZIP extension is required to generate Excel files.');
        }

        return ExcelFacade::download(
            new LoanPaymentsExport($payload['payments']),
            $filename,
            Excel::XLSX,
        );
    }

    public function renderPaymentsPrintView(
        AppUser $member,
        string $loanNumber,
        ?string $range,
        ?string $start,
        ?string $end,
    ): View {
        $context = $this->buildPaymentsReportContext(
            $member,
            $loanNumber,
            $range,
            $start,
            $end,
        );

        return view('reports.loan-payments', [
            ...$this->buildViewData(
                $context['payload'],
                $context['memberName'],
                $context['reportPeriod'],
            ),
            'autoPrint' => true,
        ]);
    }

    /**
     * @return array{
     *     payload: array{
     *         loan: \App\Models\Wlnmaster,
     *         summary: array{
     *             balance: float,
     *             nextPaymentDate: ?string,
     *             lastPaymentDate: ?string
     *         },
     *         payments: \Illuminate\Support\Collection<int, \App\Models\Wlnled>,
     *         filters: array{range: string, start: ?string, end: ?string},
     *         openingBalance: ?float,
     *         closingBalance: ?float
     *     },
     *     memberName: string,
     *     reportPeriod: array{start: \Illuminate\Support\Carbon, end: \Illuminate\Support\Carbon}
     * }
     */
    private function buildPaymentsReportContext(
        AppUser $member,
        string $loanNumber,
        ?string $range,
        ?string $start,
        ?string $end,
    ): array {
        $payload = $this->loanService->getPaymentsExportData(
            $member,
            $loanNumber,
            $range,
            $start,
            $end,
        );

        $memberName = $this->resolveMemberName($member);
        $reportPeriod = $this->resolveReportPeriod(
            $payload['filters']['start'],
            $payload['filters']['end'],
            $payload['payments'],
        );

        return [
            'payload' => $payload,
            'memberName' => $memberName,
            'reportPeriod' => $reportPeriod,
        ];
    }

    /**
     * @param  array{
     *     loan: \App\Models\Wlnmaster,
     *     summary: array{
     *         balance: float,
     *         nextPaymentDate: ?string,
     *         lastPaymentDate: ?string
     *     },
     *     payments: \Illuminate\Support\Collection<int, \App\Models\Wlnled>,
     *     filters: array{range: string, start: ?string, end: ?string},
     *     openingBalance: ?float,
     *     closingBalance: ?float
     * }  $payload
     * @param  array{start: \Illuminate\Support\Carbon, end: \Illuminate\Support\Carbon}  $reportPeriod
     */
    private function exportPdf(
        array $payload,
        string $memberName,
        array $reportPeriod,
        string $filename,
        bool $download,
    ): Response {
        $pdf = Pdf::setOption('isPhpEnabled', true)
            ->loadView('reports.loan-payments', $this->buildViewData(
                $payload,
                $memberName,
                $reportPeriod,
            ));

        if ($download) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    /**
     * @param  array{
     *     loan: \App\Models\Wlnmaster,
     *     summary: array{
     *         balance: float,
     *         nextPaymentDate: ?string,
     *         lastPaymentDate: ?string
     *     },
     *     payments: \Illuminate\Support\Collection<int, \App\Models\Wlnled>,
     *     filters: array{range: string, start: ?string, end: ?string},
     *     openingBalance: ?float,
     *     closingBalance: ?float
     * }  $payload
     * @param  array{start: \Illuminate\Support\Carbon, end: \Illuminate\Support\Carbon}  $reportPeriod
     * @return array{
     *     logoData: ?string,
     *     companyName: string,
     *     showCompanyName: bool,
     *     memberName: string,
     *     memberAccountNo: ?string,
     *     loanNumber: string,
     *     reportStart: \Illuminate\Support\Carbon,
     *     reportEnd: \Illuminate\Support\Carbon,
     *     generatedAt: \Illuminate\Support\Carbon,
     *     generatedBy: ?string,
     *     payments: \Illuminate\Support\Collection<int, \App\Models\Wlnled>,
     *     openingBalance: ?float,
     *     closingBalance: ?float
     * }
     */
    private function buildViewData(
        array $payload,
        string $memberName,
        array $reportPeriod,
    ): array {
        $logoData = $this->brandingService->logoDataUri();
        $branding = $this->brandingService->branding();
        $generatedBy = auth()->user()?->name ?? auth()->user()?->username;
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
            'logoData' => $logoData,
            'companyName' => $branding['companyName'],
            'showCompanyName' => $reportHeader['showCompanyName'],
            'memberName' => $memberName,
            'memberAccountNo' => $payload['loan']->acctno ?? null,
            'loanNumber' => $payload['loan']->lnnumber,
            'reportStart' => $reportPeriod['start'],
            'reportEnd' => $reportPeriod['end'],
            'generatedAt' => Carbon::now(),
            'generatedBy' => $generatedBy,
            'payments' => $payload['payments'],
            'openingBalance' => $payload['openingBalance'],
            'closingBalance' => $payload['closingBalance'],
            'reportHeader' => $reportHeader,
            'reportTypography' => $branding['reportTypography'] ?? [],
        ];
    }

    private function resolveMemberName(AppUser $member): string
    {
        $name = null;

        if (Schema::hasTable('wmaster')) {
            $member->loadMissing('wmaster');
            $name = $member->wmaster?->displayName();
        }

        if (! is_string($name) || trim($name) === '') {
            $name = $member->username;
        }

        if (! is_string($name) || trim($name) === '') {
            return 'Member';
        }

        return $name;
    }

    /**
     * @return array{start: \Illuminate\Support\Carbon, end: \Illuminate\Support\Carbon}
     */
    private function resolveReportPeriod(
        ?string $start,
        ?string $end,
        Collection $payments,
    ): array {
        $startDate = $start ? Carbon::parse($start) : $this->getBoundaryDate($payments, 'min');
        $endDate = $end ? Carbon::parse($end) : $this->getBoundaryDate($payments, 'max');

        if ($startDate === null) {
            $startDate = Carbon::today();
        }

        if ($endDate === null) {
            $endDate = $startDate->copy();
        }

        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    private function getBoundaryDate(Collection $payments, string $type): ?Carbon
    {
        $value = $type === 'min'
            ? $payments->min('date_in')
            : $payments->max('date_in');

        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        return Carbon::parse($value);
    }

    private function buildFilename(
        string $memberName,
        Carbon $start,
        Carbon $end,
        string $format,
    ): string {
        $memberLastName = $this->resolveMemberLastName($memberName);
        $basename = sprintf(
            '%s-lnpayment-%s-%s',
            Str::slug($memberLastName),
            $start->toDateString(),
            $end->toDateString(),
        );

        return sprintf('%s.%s', $basename, $format);
    }

    private function resolveMemberLastName(string $memberName): string
    {
        $value = trim($memberName);

        if ($value === '') {
            return 'member';
        }

        if (str_contains($value, ',')) {
            return (string) Str::of($value)->before(',')->trim();
        }

        $parts = preg_split('/\s+/', $value) ?: [];

        return $parts !== [] ? (string) end($parts) : $value;
    }
}
