@php
    $companyName = trim((string) ($organization['company_name'] ?? ''));
    $headerDesign = $reportHeader['designData'] ?? null;
    $headerLogo = $organizationLogoDataUri ?? null;
    $borrowerName = trim((string) ($applicant['full_name'] ?? ''));
    $borrowerAddress = trim((string) ($applicant['address'] ?? ''));
    $loanReference = trim((string) ($loan['reference'] ?? ''));
    $approvedAmount = $loan['approved_amount_raw'] ?? null;
    $draweeBank = trim((string) ($pdc['drawee_bank'] ?? ''));

    $checkCount = (int) ($loan['amortization_count'] ?? 0);
    $principalPerCheck = $loan['amortization_principal_raw'] ?? null;
    $interestPerCheck = $loan['amortization_interest_raw'] ?? null;
    $loanSecurityPerCheck = $loan['amortization_loan_security_raw'] ?? null;
    $totalPerCheck = $loan['amortization_total_raw'] ?? null;

    $fmt = static function (mixed $value): string {
        if ($value === null || ! is_numeric((string) $value)) {
            return '';
        }

        return number_format((float) $value, 2, '.', ',');
    };

    $sum = static function (?float $perCheck, int $count) {
        return $perCheck !== null ? $perCheck * $count : null;
    };

    $totalPrincipal = $sum($principalPerCheck, $checkCount);
    $totalInterest = $sum($interestPerCheck, $checkCount);
    $totalLoanSecurity = $sum($loanSecurityPerCheck, $checkCount);
    $totalOverall = $sum($totalPerCheck, $checkCount);
@endphp
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>Post-Dated Checks Schedule</title>
        @if (! empty($reportTypography['fontFaceCss'] ?? null))
            <style>{!! $reportTypography['fontFaceCss'] !!}</style>
        @endif
        <style>
            @page {
                size: 8.5in 11in;
                margin: .75in 1in 1in 1in;
            }

            body {
                margin: 0;
                color: #111;
                font-family: "Calibri", Arial, sans-serif;
                font-size: 10pt;
                line-height: 1.35;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .report-header {
                margin: 0 0 6pt;
                text-align: center;
            }

            .report-header-design {
                display: block;
                width: 100%;
                max-height: 60pt;
                margin: 0 auto;
                object-fit: contain;
            }

            .report-header--fallback { text-align: center; }

            .report-header-logo {
                display: block;
                max-width: 50pt;
                max-height: 50pt;
                margin: 0 auto 4pt;
                object-fit: contain;
            }

            .report-header-company {
                font-size: 11pt;
                font-weight: 700;
                letter-spacing: 0.04em;
                text-transform: uppercase;
            }

            .document-title {
                margin: 0 0 2pt;
                font-size: 13pt;
                font-weight: 700;
                text-align: center;
                text-transform: uppercase;
            }

            .document-subtitle {
                margin: 0 0 12pt;
                font-size: 9pt;
                font-weight: 600;
                text-align: center;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            .fl {
                width: 100%;
                table-layout: fixed;
                border-collapse: collapse;
                margin-bottom: 12pt;
            }

            .fl td { padding: 2pt 3pt; vertical-align: bottom; }
            .fl .lbl { white-space: nowrap; font-weight: 600; padding-right: 4pt; }
            .fl .val { border-bottom: 0.6pt solid #333; font-weight: 700; }

            .schedule-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 12pt;
            }

            .schedule-table th,
            .schedule-table td {
                border: 0.8pt solid #444;
                padding: 4pt 5pt;
                text-align: center;
            }

            .schedule-table th {
                background: #e8e8e8;
                font-weight: 700;
                text-transform: uppercase;
                font-size: 8.5pt;
            }

            .schedule-table td.amt { text-align: right; }
            .schedule-table td.check-no { width: 12%; }
            .schedule-table td.check-date { width: 20%; }

            .schedule-table tfoot td {
                font-weight: 700;
                background: #f2f2f2;
            }

            .sig-layout {
                width: 100%;
                border-collapse: separate;
                table-layout: fixed;
                margin-top: 24pt;
            }

            .sig-col { width: 50%; vertical-align: top; padding: 0 4pt; }

            .sig-name {
                min-height: 14pt;
                padding-top: 24pt;
                font-size: 9pt;
                font-weight: 700;
                text-align: center;
                text-transform: uppercase;
                border-bottom: 0.6pt solid #333;
            }

            .sig-lbl {
                margin-top: 2pt;
                font-size: 8.5pt;
                text-align: center;
                text-transform: uppercase;
            }
        </style>
    </head>
    <body>
        <div class="report-header">
            @if ($headerDesign)
                <img src="{{ $headerDesign }}" alt="Report header design" class="report-header-design" />
            @else
                <div class="report-header--fallback">
                    @if ($headerLogo)
                        <img src="{{ $headerLogo }}"
                             alt="{{ $companyName !== '' ? $companyName : 'Organization logo' }}"
                             class="report-header-logo" />
                    @endif
                    <div class="report-header-company">
                        {{ $companyName !== '' ? $companyName : 'Post-Dated Checks Schedule' }}
                    </div>
                </div>
            @endif
        </div>

        <div class="document-title">Post-Dated Checks Schedule</div>
        <div class="document-subtitle">Annex A</div>

        <table class="fl">
            <colgroup>
                <col style="width:100pt" />
                <col />
                <col style="width:10pt" />
                <col style="width:85pt" />
                <col />
            </colgroup>
            <tr>
                <td class="lbl">Borrower's Name:</td>
                <td class="val" colspan="4">{{ $borrowerName !== '' ? $borrowerName : ' ' }}</td>
            </tr>
            <tr>
                <td class="lbl">Address:</td>
                <td class="val" colspan="4">{{ $borrowerAddress !== '' ? $borrowerAddress : ' ' }}</td>
            </tr>
            <tr>
                <td class="lbl">Loan Amount: P</td>
                <td class="val">{{ $approvedAmount !== null ? $fmt($approvedAmount) : ' ' }}</td>
                <td></td>
                <td class="lbl">Reference No.:</td>
                <td class="val">{{ $loanReference !== '' ? $loanReference : ' ' }}</td>
            </tr>
            <tr>
                <td class="lbl">Drawee Bank:</td>
                <td class="val" colspan="4">{{ $draweeBank !== '' ? $draweeBank : ' ' }}</td>
            </tr>
        </table>

        <table class="schedule-table">
            <thead>
                <tr>
                    <th>Check No.</th>
                    <th>Check Date</th>
                    <th>Principal</th>
                    <th>Interest</th>
                    <th>Loan Security</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
                @for ($i = 1; $i <= $checkCount; $i++)
                    <tr>
                        <td class="check-no">{{ $i }}</td>
                        <td class="check-date">&nbsp;</td>
                        <td class="amt">{{ $fmt($principalPerCheck) }}</td>
                        <td class="amt">{{ $fmt($interestPerCheck) }}</td>
                        <td class="amt">{{ $fmt($loanSecurityPerCheck) }}</td>
                        <td class="amt">{{ $fmt($totalPerCheck) }}</td>
                    </tr>
                @endfor
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">Total</td>
                    <td class="amt">{{ $fmt($totalPrincipal) }}</td>
                    <td class="amt">{{ $fmt($totalInterest) }}</td>
                    <td class="amt">{{ $fmt($totalLoanSecurity) }}</td>
                    <td class="amt">{{ $fmt($totalOverall) }}</td>
                </tr>
            </tfoot>
        </table>

        <table class="sig-layout">
            <tr>
                <td class="sig-col">
                    <div class="sig-name">{{ $borrowerName !== '' ? $borrowerName : ' ' }}</div>
                    <div class="sig-lbl">Borrower</div>
                </td>
                <td class="sig-col">
                    <div class="sig-name">&nbsp;</div>
                    <div class="sig-lbl">Received By</div>
                </td>
            </tr>
        </table>
    </body>
</html>
