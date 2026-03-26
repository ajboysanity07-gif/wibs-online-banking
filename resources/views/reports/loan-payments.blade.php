<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>Loan Payment Transaction Report</title>
        @php
            $reportTypography = $reportTypography ?? [];
            $googleFontUrl = $reportTypography['googleFontUrl'] ?? null;
        @endphp
        @if ($googleFontUrl)
            <link rel="stylesheet" href="{{ $googleFontUrl }}" />
        @endif
        <style>
            @include('reports.partials.report-typography')
            body {
                font-family: var(--report-font-value-family);
                font-size: 12px;
                color: #111;
                margin: 24px;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .report-header {
                margin-bottom: 25px;
            }
            .report-header--left {
                text-align: left;
            }
            .report-header--center {
                text-align: center;
            }
            .report-header--right {
                text-align: right;
            }
            .report-header-group {
                display: inline-block;
                text-align: inherit;
            }
            .report-brand {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                vertical-align: middle;
                margin-right: 12px;
            }
            .report-header-text {
                display: inline-block;
                vertical-align: middle;
                text-align: inherit;
            }
            .report-header--wordmark .report-brand {
                gap: 0;
            }
            .report-logo {
                height: 34px;
            }
            .report-header--wordmark .report-logo {
                height: 40px;
            }
            .report-company-name {
                font-family: var(--report-font-header-title-family);
                font-weight: var(--report-font-header-title-weight);
                font-style: var(--report-font-header-title-style);
                font-size: 12px;
                color: var(--report-font-header-color);
            }
            .report-title {
                text-align: inherit;
                font-family: var(--report-font-header-title-family);
                font-weight: var(--report-font-header-title-weight);
                font-style: var(--report-font-header-title-style);
                font-size: var(--report-font-header-title-size);
                color: var(--report-font-header-color);
                margin: 0;
                letter-spacing: 0.04em;
            }
            .report-tagline {
                text-align: inherit;
                font-family: var(--report-font-header-tagline-family);
                font-weight: var(--report-font-header-tagline-weight);
                font-style: var(--report-font-header-tagline-style);
                font-size: var(--report-font-header-tagline-size);
                color: var(--report-font-header-tagline-color);
                margin: 2px 0 0;
            }
            .report-intro {
                margin: 6px 0 16px;
            }
            .report-intro-title {
                font-family: var(--report-font-label-family);
                font-weight: var(--report-font-label-weight);
                font-style: var(--report-font-label-style);
                font-size: var(--report-font-label-size);
                color: var(--report-font-label-color);
                text-transform: uppercase;
                letter-spacing: 0.08em;
                margin-bottom: 4px;
            }
            .report-intro-text {
                font-family: var(--report-font-value-family);
                font-weight: var(--report-font-value-weight);
                font-style: var(--report-font-value-style);
                font-size: var(--report-font-value-size);
                color: var(--report-font-value-color);
                margin: 0;
                line-height: 1.5;
            }
            .meta-block {
                margin-bottom: 16px;
            }
            .meta-table {
                width: 100%;
                border-collapse: collapse;
            }
            .meta-table td {
                width: 50%;
                padding: 0 16px 10px 8px;
                vertical-align: top;
            }
            .meta-table td:last-child {
                padding-right: 8px;
            }
            .label {
                font-family: var(--report-font-label-family);
                font-weight: var(--report-font-label-weight);
                font-style: var(--report-font-label-style);
                font-size: var(--report-font-label-size);
                color: var(--report-font-label-color);
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }
            .value {
                font-family: var(--report-font-value-family);
                font-weight: var(--report-font-value-weight);
                font-style: var(--report-font-value-style);
                font-size: var(--report-font-value-size);
                color: var(--report-font-value-color);
            }
            .summary {
                border: 1px solid #e0e0e0;
                padding: 12px 18px;
                margin-bottom: 18px;
                background: #f9f9f9;
            }
            .summary-title {
                font-family: var(--report-font-label-family);
                font-weight: var(--report-font-label-weight);
                font-style: var(--report-font-label-style);
                font-size: var(--report-font-label-size);
                text-transform: uppercase;
                letter-spacing: 0.04em;
                color: var(--report-font-label-color);
                margin-bottom: 8px;
            }
            .summary-table {
                width: 100%;
                border-collapse: collapse;
            }
            .summary-table td {
                width: 50%;
                vertical-align: top;
                padding-right: 18px;
            }
            .summary-table td:last-child {
                padding-right: 0;
            }
            .coverage-note {
                border: 1px solid #e0e0e0;
                padding: 10px 12px;
                margin: 10px 0 16px;
                background: #fafafa;
                font-size: 10px;
                color: #333;
                line-height: 1.4;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
                font-size: 11px;
            }
            th,
            td {
                border: 1px solid #e2e2e2;
                padding: 6px 8px;
                text-align: left;
                vertical-align: top;
            }
            th {
                background: #f5f5f5;
                font-size: 10px;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }
            .text-right {
                text-align: right;
                white-space: nowrap;
            }
            .col-date {
                width: 18%;
            }
            .col-reference {
                width: 28%;
            }
            .col-money {
                width: 18%;
            }
            .row-avoid-break {
                page-break-inside: avoid;
            }
            .certification {
                margin-top: 18px;
                font-size: 10px;
                color: #333;
                line-height: 1.4;
            }
            .certification-title {
                font-family: var(--report-font-label-family);
                font-weight: var(--report-font-label-weight);
                font-style: var(--report-font-label-style);
                font-size: var(--report-font-label-size);
                color: var(--report-font-label-color);
                text-transform: uppercase;
                letter-spacing: 0.06em;
                margin-bottom: 6px;
            }
            .certification-text {
                margin: 0 0 8px;
            }
            .signature-block {
                margin-top: 18px;
            }
            .signature-table {
                width: 100%;
                border-collapse: collapse;
            }
            .signature-cell {
                width: 50%;
                padding: 12px 12px 0 0;
                vertical-align: top;
            }
            .signature-cell:last-child {
                padding-right: 0;
            }
            .signature-line {
                border-bottom: 1px solid #333;
                height: 18px;
            }
            .signature-label {
                font-size: 10px;
                color: #333;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-top: 4px;
            }
            .signature-cell--date .signature-line {
                width: 40%;
            }
            .page-break-avoid {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .footer {
                margin-top: 18px;
                font-size: 10px;
                color: #666;
            }
        </style>
    </head>
    <body>
        @php
            $formatCurrency = fn ($value) => $value === null ? '--' : number_format((float) $value, 2);
            $formatDate = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y/m/d') : '--';
            $formatDateTime = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y/m/d H:i:s') : '--';
            $formatBalance = fn ($value) => $value === null ? 'Not available' : number_format((float) $value, 2);
            $reportHeader = $reportHeader ?? [];
            $reportHeader['companyName'] = $reportHeader['companyName'] ?? ($companyName ?? '');
            $reportHeader['logoData'] = $reportHeader['logoData'] ?? ($logoData ?? null);
            if (! array_key_exists('showCompanyName', $reportHeader) && isset($showCompanyName)) {
                $reportHeader['showCompanyName'] = (bool) $showCompanyName;
            }
            $reportTitle = $reportHeader['title'] ?? null;
            $reportTagline = $reportHeader['tagline'] ?? null;
            $titleText = $reportTitle ?: 'Loan Payment Transaction Report';
        @endphp

        @include('reports.partials.report-header', [
            'reportHeader' => $reportHeader,
            'reportTitle' => $titleText,
            'reportTagline' => $reportTagline,
        ])

        <div class="report-intro page-break-avoid">
            <div class="report-intro-title">Loan Payment Report</div>
            <p class="report-intro-text">
                This document summarizes recorded loan payment transactions for the member and loan account identified
                below for the covered reporting period.
            </p>
        </div>

        <div class="meta-block">
            <table class="meta-table">
                <tr>
                    <td>
                        <div class="label">Member Name</div>
                        <div class="value">{{ $memberName }}</div>
                    </td>
                    <td>
                        <div class="label">Member Account No</div>
                        <div class="value">{{ $memberAccountNo ?? '--' }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="label">Loan Number</div>
                        <div class="value">{{ $loanNumber }}</div>
                    </td>
                    <td>
                        <div class="label">Report Period</div>
                        <div class="value">{{ $reportStart->format('Y/m/d') }} - {{ $reportEnd->format('Y/m/d') }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="label">Generated At</div>
                        <div class="value">{{ $formatDateTime($generatedAt) }}</div>
                    </td>
                    <td>
                        <div class="label">Generated By</div>
                        <div class="value">{{ $generatedBy ?? '--' }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="summary">
            <div class="summary-title">Balances</div>
            <table class="summary-table">
                <tr>
                    <td>
                        <div class="label">Opening Balance</div>
                        <div class="value">{{ $formatBalance($openingBalance) }}</div>
                    </td>
                    <td>
                        <div class="label">Closing Balance</div>
                        <div class="value">{{ $formatBalance($closingBalance) }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="coverage-note page-break-avoid">
            This report reflects loan payment transactions posted to the system for the reporting period stated in this
            document. Balances shown are based on records available at the time this report was generated.
        </div>

        <table>
            <thead>
                <tr>
                    <th class="col-date">Transaction Date</th>
                    <th class="col-reference">Reference No</th>
                    <th class="text-right col-money">Principal</th>
                    <th class="text-right col-money">Payment</th>
                    <th class="text-right col-money">Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $row)
                    <tr class="row-avoid-break">
                        <td>{{ $formatDate($row->date_in) }}</td>
                        <td>{{ $row->mreference ?? $row->transno ?? $row->controlno ?? '--' }}</td>
                        <td class="text-right">{{ $formatCurrency($row->principal) }}</td>
                        <td class="text-right">{{ $formatCurrency($row->payments) }}</td>
                        <td class="text-right">{{ $formatCurrency($row->balance) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No transactions found for this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="certification page-break-avoid">
            <div class="certification-title">Certification</div>
            <p class="certification-text">
                This is a system-generated report prepared from the loan payment records maintained by the organization
                for the account and reporting period stated above.
            </p>
            <p class="certification-text">
                This report was generated on {{ $formatDateTime($generatedAt) }} by {{ $generatedBy ?? '--' }}. Unless
                otherwise required by policy, this document is valid without handwritten signature.
            </p>
        </div>

        <div class="signature-block page-break-avoid">
            <table class="signature-table">
                <tr>
                    <td class="signature-cell">
                        <div class="signature-line"></div>
                        <div class="signature-label">Prepared by</div>
                    </td>
                    <td class="signature-cell">
                        <div class="signature-line"></div>
                        <div class="signature-label">Checked by</div>
                    </td>
                </tr>
                <tr>
                    <td class="signature-cell">
                        <div class="signature-line"></div>
                        <div class="signature-label">Noted by</div>
                    </td>
                    <td class="signature-cell">
                        <div class="signature-line"></div>
                        <div class="signature-label">Received by / Borrower</div>
                    </td>
                </tr>
                <tr>
                    <td class="signature-cell signature-cell--date" colspan="2">
                        <div class="signature-line"></div>
                        <div class="signature-label">Date</div>
                    </td>
                </tr>
            </table>
        </div>

        <script type="text/php">
            if (isset($pdf)) {
                $font = $fontMetrics->getFont("DejaVu Sans");
                $size = 9;
                $text = "Page {PAGE_NUM} of {PAGE_COUNT}";
                $width = $fontMetrics->getTextWidth($text, $font, $size);
                $x = $pdf->get_width() - $width - 36;
                $y = $pdf->get_height() - 28;
                $pdf->page_text($x, $y, $text, $font, $size, [0, 0, 0]);
            }
        </script>
        @if (! empty($autoPrint))
            <script>
                (() => {
                    let printed = false;

                    const triggerPrint = () => {
                        if (printed) {
                            return;
                        }

                        printed = true;
                        window.print();
                    };

                    const waitForFonts = () => {
                        if (document.fonts && document.fonts.ready) {
                            document.fonts.ready
                                .then(() => {
                                    setTimeout(triggerPrint, 100);
                                })
                                .catch(triggerPrint);
                            return;
                        }

                        setTimeout(triggerPrint, 100);
                    };

                    window.addEventListener('load', waitForFonts);
                })();
            </script>
        @endif
    </body>
</html>
