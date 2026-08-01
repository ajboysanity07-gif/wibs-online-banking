@php
    $companyName     = trim((string) ($organization['company_name'] ?? ''));
    $headerDesign    = $reportHeader['designData'] ?? null;
    $headerLogo      = $organizationLogoDataUri ?? null;
    $borrowerName    = trim((string) ($applicant['full_name'] ?? ''));
    $borrowerAddress = trim((string) ($applicant['address'] ?? ''));
    $approvedAmount  = $loan['amortization_principal_raw'] !== null
        ? $loan['approved_amount_raw'] ?? null
        : ($loan['approved_amount_raw'] ?? null);
    $approvedAmount  = $loan['approved_amount_raw'] ?? null;
    $loanType        = trim((string) ($loan['type'] ?? ''));
    $paymentMode     = trim((string) ($loan['payment_mode_workbook'] ?? ''));
    $principalAmt    = $loan['amortization_principal_raw'] ?? null;
    $interestAmt     = $loan['amortization_interest_raw'] ?? null;
    $loanSecurityAmt = $loan['amortization_loan_security_raw'] ?? null;
    $totalAmt        = $loan['amortization_total_raw'] ?? null;
    $approvedDate    = trim((string) ($loan['approved_date_short'] ?? ''));
    $maturityDate    = trim((string) ($loan['maturity_date_short'] ?? ''));
    $reviewerName    = trim((string) ($reviewer['name'] ?? ''));

    $fmt = static function (mixed $value): string {
        if ($value === null || !is_numeric((string) $value)) {
            return '';
        }
        return number_format((float) $value, 2, '.', ',');
    };
@endphp
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>Plan of Payment</title>
        @if (! empty($reportTypography['fontFaceCss'] ?? null))
            <style>{!! $reportTypography['fontFaceCss'] !!}</style>
        @endif
        <style>
            @page {
                size: 8.5in 13in;
                margin: .6in .8in .8in .8in;
            }

            body {
                margin: 0;
                color: #111;
                font-family: "Calibri", Arial, sans-serif;
                font-size: 9pt;
                line-height: 1.3;
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
                margin: 0 0 8pt;
                font-size: 12pt;
                font-weight: 700;
                text-align: center;
                text-transform: uppercase;
            }

            .copy-section { page-break-inside: avoid; }

            .copy-label {
                font-size: 8pt;
                font-weight: 700;
                text-align: center;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                background: #e8e8e8;
                border: 0.8pt solid #444;
                border-bottom: none;
                padding: 2pt 4pt;
            }

            .copy-body {
                border: 0.8pt solid #444;
                padding: 6pt 8pt 8pt;
            }

            .fl {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 4pt;
            }

            .fl td { padding: 1pt 2pt; vertical-align: bottom; }
            .fl .lbl { white-space: nowrap; font-weight: 600; padding-right: 4pt; }
            .fl .val { border-bottom: 0.6pt solid #333; font-weight: 700; min-width: 80pt; }

            .amort-table {
                width: 100%;
                border-collapse: collapse;
                margin: 5pt 0 6pt;
                font-size: 9pt;
            }

            .amort-table th, .amort-table td {
                border: 0.6pt solid #555;
                padding: 2pt 5pt;
            }

            .amort-table th {
                background: #f0f0f0;
                font-weight: 700;
                text-align: center;
                font-size: 8pt;
            }

            .amort-table td:first-child { text-align: left; }
            .amort-table td:last-child  { text-align: right; }

            .sig-layout {
                width: 100%;
                border-collapse: separate;
                table-layout: fixed;
                margin-top: 10pt;
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

            .divider {
                border: none;
                border-top: 1pt dashed #999;
                margin: 10pt 0;
            }
        </style>
    </head>
    <body>
        {{-- ===================== HEADER ===================== --}}
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
                        {{ $companyName !== '' ? $companyName : 'Plan of Payment' }}
                    </div>
                </div>
            @endif
        </div>

        <div class="document-title">Plan of Payment</div>

        {{-- ===================== BORROWER'S COPY ===================== --}}
        <div class="copy-section">
            <div class="copy-label">Borrower's Copy</div>
            <div class="copy-body">
                <table class="fl">
                    <tr>
                        <td class="lbl">Borrower's Name:</td>
                        <td class="val">{{ $borrowerName !== '' ? $borrowerName : ' ' }}</td>
                    </tr>
                </table>
                <table class="fl">
                    <tr>
                        <td class="lbl">Address:</td>
                        <td class="val">{{ $borrowerAddress !== '' ? $borrowerAddress : ' ' }}</td>
                    </tr>
                </table>
                <table class="fl" style="table-layout:fixed;width:100%;">
                    <tr>
                        <td class="lbl" style="width:auto;">Loan Amount: P</td>
                        <td class="val" style="width:28%;">{{ $approvedAmount !== null ? $fmt($approvedAmount) : ' ' }}</td>
                        <td style="width:10pt;"></td>
                        <td class="lbl">Loan Type:</td>
                        <td class="val">{{ $loanType !== '' ? $loanType : ' ' }}</td>
                    </tr>
                </table>
                <table class="fl">
                    <tr>
                        <td class="lbl">Mode of Payment:</td>
                        <td class="val">{{ $paymentMode !== '' ? $paymentMode : ' ' }}</td>
                    </tr>
                </table>

                <table class="amort-table">
                    <thead>
                        <tr><th>Description</th><th>Amortization Amount (P)</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Principal</td><td>{{ $principalAmt !== null ? $fmt($principalAmt) : '' }}</td></tr>
                        <tr><td>Interest</td><td>{{ $interestAmt !== null ? $fmt($interestAmt) : '' }}</td></tr>
                        <tr><td>Savings (2%)</td><td>{{ $loanSecurityAmt !== null ? $fmt($loanSecurityAmt) : '' }}</td></tr>
                        <tr style="font-weight:700;"><td>Total Amortization</td><td>{{ $totalAmt !== null ? $fmt($totalAmt) : '' }}</td></tr>
                    </tbody>
                </table>

                <table class="fl" style="table-layout:fixed;width:100%;">
                    <tr>
                        <td class="lbl" style="width:auto;">Date Granted:</td>
                        <td class="val" style="width:22%;">{{ $approvedDate !== '' ? $approvedDate : ' ' }}</td>
                        <td style="width:10pt;"></td>
                        <td class="lbl">Maturity Date:</td>
                        <td class="val">{{ $maturityDate !== '' ? $maturityDate : ' ' }}</td>
                    </tr>
                </table>

                <table class="sig-layout">
                    <tr>
                        <td class="sig-col">
                            <div class="sig-name">{{ $borrowerName !== '' ? $borrowerName : ' ' }}</div>
                            <div class="sig-lbl">Borrower</div>
                        </td>
                        <td class="sig-col">
                            <div class="sig-name">{{ $reviewerName !== '' ? $reviewerName : ' ' }}</div>
                            <div class="sig-lbl">Loan Manager</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <hr class="divider" />

        {{-- ===================== COOPERATIVE'S COPY ===================== --}}
        <div class="copy-section">
            <div class="copy-label">Cooperative's Copy</div>
            <div class="copy-body">
                <table class="fl">
                    <tr>
                        <td class="lbl">Borrower's Name:</td>
                        <td class="val">{{ $borrowerName !== '' ? $borrowerName : ' ' }}</td>
                    </tr>
                </table>
                <table class="fl">
                    <tr>
                        <td class="lbl">Address:</td>
                        <td class="val">{{ $borrowerAddress !== '' ? $borrowerAddress : ' ' }}</td>
                    </tr>
                </table>
                <table class="fl" style="table-layout:fixed;width:100%;">
                    <tr>
                        <td class="lbl" style="width:auto;">Loan Amount: P</td>
                        <td class="val" style="width:28%;">{{ $approvedAmount !== null ? $fmt($approvedAmount) : ' ' }}</td>
                        <td style="width:10pt;"></td>
                        <td class="lbl">Loan Type:</td>
                        <td class="val">{{ $loanType !== '' ? $loanType : ' ' }}</td>
                    </tr>
                </table>
                <table class="fl">
                    <tr>
                        <td class="lbl">Mode of Payment:</td>
                        <td class="val">{{ $paymentMode !== '' ? $paymentMode : ' ' }}</td>
                    </tr>
                </table>

                <table class="amort-table">
                    <thead>
                        <tr><th>Description</th><th>Amortization Amount (P)</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>Principal</td><td>{{ $principalAmt !== null ? $fmt($principalAmt) : '' }}</td></tr>
                        <tr><td>Interest</td><td>{{ $interestAmt !== null ? $fmt($interestAmt) : '' }}</td></tr>
                        <tr><td>Savings (2%)</td><td>{{ $loanSecurityAmt !== null ? $fmt($loanSecurityAmt) : '' }}</td></tr>
                        <tr style="font-weight:700;"><td>Total Amortization</td><td>{{ $totalAmt !== null ? $fmt($totalAmt) : '' }}</td></tr>
                    </tbody>
                </table>

                <table class="fl" style="table-layout:fixed;width:100%;">
                    <tr>
                        <td class="lbl" style="width:auto;">Date Granted:</td>
                        <td class="val" style="width:22%;">{{ $approvedDate !== '' ? $approvedDate : ' ' }}</td>
                        <td style="width:10pt;"></td>
                        <td class="lbl">Maturity Date:</td>
                        <td class="val">{{ $maturityDate !== '' ? $maturityDate : ' ' }}</td>
                    </tr>
                </table>

                <table class="sig-layout">
                    <tr>
                        <td class="sig-col">
                            <div class="sig-name">{{ $borrowerName !== '' ? $borrowerName : ' ' }}</div>
                            <div class="sig-lbl">Borrower</div>
                        </td>
                        <td class="sig-col">
                            <div class="sig-name">{{ $reviewerName !== '' ? $reviewerName : ' ' }}</div>
                            <div class="sig-lbl">Loan Manager</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </body>
</html>
