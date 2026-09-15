@php
    $companyName = trim((string) ($organization['company_name'] ?? ''));
    $headerDesign = $reportHeader['designData'] ?? null;
    $headerLogo = $organizationLogoDataUri ?? null;
    $borrowerName = trim((string) ($applicant['full_name'] ?? ''));
    $borrowerAddress = trim((string) ($applicant['address'] ?? ''));
    $loanReference = trim((string) ($loan['reference'] ?? ''));
    $approvedAmount = $loan['approved_amount_raw'] ?? null;
    $draweeBank = trim((string) ($pdc['drawee_bank'] ?? ''));

    $scheduleRows = $schedule['rows'] ?? [];
    $scheduleTotals = $schedule['totals'] ?? [];

    $fmt = static function (mixed $value): string {
        if ($value === null || ! is_numeric((string) $value)) {
            return '';
        }

        return number_format((float) $value, 2, '.', ',');
    };

    $totalPrincipal = $scheduleTotals['principal'] ?? null;
    $totalInterest = $scheduleTotals['interest'] ?? null;
    $totalLoanSecurity = $scheduleTotals['loan_security'] ?? null;
    $totalOverall = $scheduleTotals['total'] ?? null;

    $approvedDateUpper = strtoupper(trim((string) ($loan['approved_date'] ?? '')));
    $witnessOneName = trim((string) ($reviewer['witness_one_name'] ?? ''));
    $witnessTwoName = trim((string) ($reviewer['witness_two_name'] ?? ''));
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
            .schedule-table td.check-no { width: 10%; }
            .schedule-table td.check-date { width: 16%; }
            .schedule-table td.drawee-bank { width: 22%; text-align: left; }

            .schedule-table tfoot td {
                font-weight: 700;
                background: #f2f2f2;
            }

            .certification {
                margin: 0 0 10pt;
                text-align: justify;
                font-size: 9.5pt;
                line-height: 1.5;
            }

            .signed-presence {
                margin: 0 0 4pt;
                font-size: 9pt;
                font-weight: 700;
                text-transform: uppercase;
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
                    <th>Drawee Bank</th>
                    <th>Principal</th>
                    <th>Interest</th>
                    <th>Loan Security</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($scheduleRows as $row)
                    <tr>
                        <td class="check-no">&nbsp;</td>
                        <td class="check-date">&nbsp;</td>
                        <td class="drawee-bank">{{ $draweeBank !== '' ? $draweeBank : ' ' }}</td>
                        <td class="amt">{{ $fmt($row['principal'] ?? null) }}</td>
                        <td class="amt">{{ $fmt($row['interest'] ?? null) }}</td>
                        <td class="amt">{{ $fmt($row['loan_security'] ?? null) }}</td>
                        <td class="amt">{{ $fmt($row['total'] ?? null) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">Total</td>
                    <td class="amt">{{ $fmt($totalPrincipal) }}</td>
                    <td class="amt">{{ $fmt($totalInterest) }}</td>
                    <td class="amt">{{ $fmt($totalLoanSecurity) }}</td>
                    <td class="amt">{{ $fmt($totalOverall) }}</td>
                </tr>
            </tfoot>
        </table>

        <p class="certification">
            I hereby certify that this attachment forms part of the Promissory Note I executed in favor of
            {{ $companyName !== '' ? $companyName : ' ' }}
            dated {{ $approvedDateUpper !== '' ? $approvedDateUpper : ' ' }}
            covering the amount of Php. {{ $approvedAmount !== null ? $fmt($approvedAmount) : ' ' }}
            and that I also give my consent, in relation to the checks that I issued in payment for this Loan,
            and allow {{ $companyName !== '' ? $companyName : ' ' }}
            through its authorized representative, without need of prior notification to me, to deface,
            stamp/mark as cancelled, perforate or do any other means to make the relevant check/s herein listed
            unusable in case my loan, as secured by said checks, is renewed or is paid in full.
        </p>

        <div class="signed-presence">SIGNED IN THE PRESENCE OF:</div>

        <table class="sig-layout">
            <tr>
                <td class="sig-col">
                    <div class="sig-name">{{ $borrowerName !== '' ? $borrowerName : ' ' }}</div>
                    <div class="sig-lbl">Borrower</div>
                </td>
                <td class="sig-col">
                    <div class="sig-name">{{ $witnessOneName !== '' ? $witnessOneName : ' ' }}</div>
                    <div class="sig-lbl">Witness</div>
                </td>
            </tr>
            <tr>
                <td class="sig-col">
                    <div class="sig-name">{{ $witnessTwoName !== '' ? $witnessTwoName : ' ' }}</div>
                    <div class="sig-lbl">Witness</div>
                </td>
                <td class="sig-col"></td>
            </tr>
        </table>
    </body>
</html>
