<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>Loan Payment Transaction Report</title>
        <style>
            body {
                font-family: "DejaVu Sans", sans-serif;
                font-size: 12px;
                color: #111;
                margin: 24px;
            }
            .header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid #d7d7d7;
                padding-bottom: 12px;
                margin-bottom: 14px;
            }
            .brand {
                display: flex;
                align-items: center;
            }
            .brand--wordmark .logo {
                height: 46px;
                margin-right: 0;
            }
            .logo {
                height: 38px;
                margin-right: 10px;
            }
            .company-name {
                font-size: 14px;
                font-weight: 700;
                line-height: 1.2;
            }
            .header-meta {
                text-align: right;
                font-size: 11px;
                color: #555;
            }
            .report-title {
                font-size: 18px;
                margin: 0 0 10px 0;
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
                color: #555;
                font-size: 10px;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }
            .value {
                font-size: 12px;
                font-weight: 600;
            }
            .summary {
                border: 1px solid #e0e0e0;
                padding: 12px 18px;
                margin-bottom: 18px;
                background: #f9f9f9;
            }
            .summary-title {
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                color: #555;
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
            $showCompanyName = $showCompanyName ?? true;
            $shouldShowCompanyName = $showCompanyName || ! $logoData;
            $brandClass = $showCompanyName ? 'brand' : 'brand brand--wordmark';
        @endphp

        <div class="header">
            <div class="{{ $brandClass }}">
                @if ($logoData)
                    <img src="{{ $logoData }}" alt="Company logo" class="logo" />
                @endif
                @if ($shouldShowCompanyName)
                    <div class="company-name">{{ $companyName }}</div>
                @endif
            </div>
            <div class="header-meta">
                <div class="label">Report Period</div>
                <div>{{ $reportStart->format('Y/m/d') }} - {{ $reportEnd->format('Y/m/d') }}</div>
            </div>
        </div>

        <h1 class="report-title">Loan Payment Transaction Report</h1>

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
    </body>
</html>
