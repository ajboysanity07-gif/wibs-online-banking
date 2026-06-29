@php
    $companyName  = trim((string) ($organization['company_name'] ?? ''));
    $headerDesign = $reportHeader['designData'] ?? null;
    $headerLogo   = $organizationLogoDataUri ?? null;

    $borrowerName = mb_strtoupper(trim((string) ($applicant['full_name'] ?? '')));
    $employerName = trim((string) ($applicant['employer_or_business'] ?? ''));
    $address      = trim((string) ($applicant['address'] ?? ''));

    $approvedAmount    = $loan['approved_amount_raw'] ?? null;
    $interestRate      = $loan['interest_rate_raw'] ?? null;
    $approvedTerm      = $loan['approved_term_raw'] ?? null;
    $serviceChargeRate = $loan['service_charge_rate_raw'] ?? null;
    $interestAmt       = $loan['interest_not_deducted_raw'] ?? null;
    $loanType          = trim((string) ($loan['type'] ?? ''));
    $paymentMode       = trim((string) ($loan['payment_mode_workbook'] ?? ''));
    $amortCount        = $loan['amortization_count'] ?? null;
    $insuranceTerm     = $loan['insurance_term'] ?? null;
    $insuranceRate     = $loan['insurance_rate_raw'] ?? null;
    $serviceChargeAmt  = $loan['service_charge_amount_raw'] ?? null;
    $insurancePremium  = $loan['insurance_premium_raw'] ?? null;
    $loanSecurityAmt   = $loan['loan_security_amount_raw'] ?? null;
    $docStampAmt       = $loan['documentary_stamp_amount_raw'] ?? null;
    $notarialFee       = $loan['notarial_fee_raw'] ?? null;

    $principalAmort = $loan['amortization_principal_raw'] ?? null;
    $interestAmort  = $loan['amortization_interest_raw'] ?? null;
    $loanSecAmort   = $loan['amortization_loan_security_raw'] ?? null;
    $totalAmort     = $loan['amortization_total_raw'] ?? null;

    $certifierName = mb_strtoupper(trim((string) ($reviewer['name'] ?? '')));
    $certifierPos  = trim((string) ($reviewer['position'] ?? ''));

    $cm1Name = mb_strtoupper(trim((string) ($co_maker_one['full_name'] ?? '')));
    $cm1Addr = trim((string) ($co_maker_one['address'] ?? ''));
    $cm2Name = mb_strtoupper(trim((string) ($co_maker_two['full_name'] ?? '')));
    $cm2Addr = trim((string) ($co_maker_two['address'] ?? ''));

    $termDays    = $loan['term_days'] ?? null;
    $amtWords    = trim((string) ($loan['approved_amount_words'] ?? ''));
    $rateWords   = trim((string) ($loan['interest_rate_words'] ?? ''));
    $penaltyRate = $loan['penalty_rate_raw'] ?? null;
    $witness1    = mb_strtoupper(trim((string) ($reviewer['witness_one_name'] ?? '')));
    $witness2    = mb_strtoupper(trim((string) ($reviewer['witness_two_name'] ?? '')));

    $fmt = static function (mixed $value): string {
        if ($value === null || !is_numeric((string) $value)) {
            return '';
        }
        return number_format((float) $value, 2, '.', ',');
    };

    $pct = static function (mixed $value): string {
        if ($value === null || !is_numeric((string) $value)) {
            return '';
        }
        return number_format((float) $value * 100, 2, '.', '') . '%';
    };

    $int = static function (mixed $value): string {
        if ($value === null || !is_numeric((string) $value)) {
            return '';
        }
        return (string) (int) round((float) $value);
    };
@endphp
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>Loan Information</title>
        <style>
            @page {
                size: 8.5in 13in;
                margin: .6in .8in .8in .8in;
            }

            body {
                margin: 0;
                color: #111;
                font-family: Arial, Helvetica, sans-serif;
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
                margin: 0 0 6pt;
                font-size: 12pt;
                font-weight: 700;
                text-align: center;
                text-transform: uppercase;
            }

            .section-lbl {
                font-size: 8pt;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                margin: 4pt 0 2pt;
            }

            .fl {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 2pt;
            }

            .fl td { padding: 1pt 2pt; vertical-align: bottom; }
            .fl .lbl { white-space: nowrap; font-weight: 600; padding-right: 4pt; font-size: 8pt; }
            .fl .val { border-bottom: 0.6pt solid #333; font-weight: 700; min-width: 60pt; }

            .section-box {
                border: 0.8pt solid #444;
                padding: 5pt 7pt 3pt;
                margin-bottom: 5pt;
            }

            .data-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 4pt;
                font-size: 9pt;
            }

            .data-table th, .data-table td {
                border: 0.6pt solid #555;
                padding: 2pt 5pt;
            }

            .data-table th {
                background: #f0f0f0;
                font-weight: 700;
                text-align: center;
                font-size: 8pt;
            }

            .data-table td:first-child { text-align: left; }
            .data-table td:not(:first-child) { text-align: right; }
            .data-table tr:last-child td { font-weight: 700; }

            .sig-layout {
                width: 100%;
                border-collapse: separate;
                table-layout: fixed;
                margin-top: 8pt;
            }

            .sig-col { width: 50%; vertical-align: top; padding: 0 4pt; }

            .sig-name {
                min-height: 14pt;
                padding-top: 20pt;
                font-size: 9pt;
                font-weight: 700;
                text-align: center;
                text-transform: uppercase;
                border-bottom: 0.6pt solid #333;
            }

            .sig-lbl {
                margin-top: 2pt;
                font-size: 8pt;
                text-align: center;
                text-transform: uppercase;
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
                        {{ $companyName !== '' ? $companyName : 'Loan Information' }}
                    </div>
                </div>
            @endif
        </div>

        <div class="document-title">Loan Information</div>

        {{-- ===================== BORROWER HEADER ===================== --}}
        <table class="fl" style="table-layout:fixed;width:100%;">
            <tr>
                <td class="lbl" style="width:auto;">Borrower's Name:</td>
                <td class="val" style="width:44%;">{{ $borrowerName !== '' ? $borrowerName : ' ' }}</td>
                <td style="width:8pt;"></td>
                <td class="lbl">Employer / Business:</td>
                <td class="val">{{ $employerName !== '' ? $employerName : ' ' }}</td>
            </tr>
        </table>
        <table class="fl">
            <tr>
                <td class="lbl">Address:</td>
                <td class="val">{{ $address !== '' ? $address : ' ' }}</td>
            </tr>
        </table>

        {{-- ===================== SECTION A: FOR DISCLOSURE STATEMENT ===================== --}}
        <div class="section-lbl">A. For Disclosure Statement</div>
        <div class="section-box">
            <table class="fl" style="table-layout:fixed;width:100%;">
                <tr>
                    <td class="lbl" style="width:auto;">Approved Loan Amount (P):</td>
                    <td class="val" style="width:22%;">{{ $approvedAmount !== null ? $fmt($approvedAmount) : ' ' }}</td>
                    <td style="width:10pt;"></td>
                    <td class="lbl">Kind of Loan:</td>
                    <td class="val">{{ $loanType !== '' ? $loanType : ' ' }}</td>
                </tr>
            </table>
            <table class="fl" style="table-layout:fixed;width:100%;">
                <tr>
                    <td class="lbl" style="width:auto;">Interest Rate per Annum:</td>
                    <td class="val" style="width:22%;">{{ $interestRate !== null ? $pct($interestRate) : ' ' }}</td>
                    <td style="width:10pt;"></td>
                    <td class="lbl">Mode of Payment:</td>
                    <td class="val" style="width:24%;">{{ $paymentMode !== '' ? $paymentMode : ' ' }}</td>
                    <td style="width:6pt;"></td>
                    <td class="lbl">No. of Amortizations:</td>
                    <td class="val">{{ $amortCount !== null ? $int($amortCount) : ' ' }}</td>
                </tr>
            </table>
            <table class="fl" style="table-layout:fixed;width:100%;">
                <tr>
                    <td class="lbl" style="width:auto;">Term (Months):</td>
                    <td class="val" style="width:22%;">{{ $approvedTerm !== null ? $int($approvedTerm) : ' ' }}</td>
                    <td style="width:10pt;"></td>
                    <td class="lbl">Insurance Term (Months):</td>
                    <td class="val" style="width:24%;">{{ $insuranceTerm !== null ? $int($insuranceTerm) : ' ' }}</td>
                    <td style="width:6pt;"></td>
                    <td class="lbl">Insurance Rate / 1,000:</td>
                    <td class="val">{{ $insuranceRate !== null ? $fmt($insuranceRate) : ' ' }}</td>
                </tr>
            </table>
            <table class="fl" style="table-layout:fixed;width:100%;">
                <tr>
                    <td class="lbl" style="width:auto;">Service Charge Rate:</td>
                    <td class="val" style="width:22%;">{{ $serviceChargeRate !== null ? $pct($serviceChargeRate) : ' ' }}</td>
                    <td style="width:10pt;"></td>
                    <td class="lbl">Penalty Rate per Month:</td>
                    <td class="val">{{ $penaltyRate !== null ? $pct($penaltyRate) : ' ' }}</td>
                </tr>
            </table>
            <table class="fl" style="table-layout:fixed;width:100%;">
                <tr>
                    <td class="lbl" style="width:auto;">Recommended By:</td>
                    <td class="val" style="width:30%;">{{ $certifierName !== '' ? $certifierName : ' ' }}</td>
                    <td style="width:6pt;"></td>
                    <td class="lbl">Position:</td>
                    <td class="val" style="width:26%;">{{ $certifierPos !== '' ? $certifierPos : ' ' }}</td>
                    <td style="width:6pt;"></td>
                    <td class="lbl">Approved By:</td>
                    <td class="val">{{ $certifierName !== '' ? $certifierName : ' ' }}</td>
                </tr>
            </table>
        </div>

        {{-- ===================== DEDUCTIONS / COMPUTED AMOUNTS ===================== --}}
        <div class="section-lbl">Deductions / Computed Amounts</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="text-align:left;">Description</th>
                    <th>Amount (P)</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Interest (Not Deducted)</td><td>{{ $interestAmt !== null ? $fmt($interestAmt) : '' }}</td></tr>
                <tr><td>Service Charge Amount</td><td>{{ $serviceChargeAmt !== null ? $fmt($serviceChargeAmt) : '' }}</td></tr>
                <tr><td>Insurance Premium</td><td>{{ $insurancePremium !== null ? $fmt($insurancePremium) : '' }}</td></tr>
                <tr><td>Loan Security Amount</td><td>{{ $loanSecurityAmt !== null ? $fmt($loanSecurityAmt) : '' }}</td></tr>
                <tr><td>Documentary Stamp</td><td>{{ $docStampAmt !== null ? $fmt($docStampAmt) : '' }}</td></tr>
                <tr><td>Notarial Fee</td><td>{{ $notarialFee !== null ? $fmt($notarialFee) : '' }}</td></tr>
            </tbody>
        </table>

        {{-- ===================== AMORTIZATION PER PERIOD ===================== --}}
        <div class="section-lbl">Amortization per Period</div>
        <table class="data-table">
            <thead>
                <tr><th style="text-align:left;">Description</th><th>Amortization Amount (P)</th></tr>
            </thead>
            <tbody>
                <tr><td>Principal</td><td>{{ $principalAmort !== null ? $fmt($principalAmort) : '' }}</td></tr>
                <tr><td>Interest</td><td>{{ $interestAmort !== null ? $fmt($interestAmort) : '' }}</td></tr>
                <tr><td>Loan Security / Insurance</td><td>{{ $loanSecAmort !== null ? $fmt($loanSecAmort) : '' }}</td></tr>
                <tr><td>Total Amortization</td><td>{{ $totalAmort !== null ? $fmt($totalAmort) : '' }}</td></tr>
            </tbody>
        </table>

        {{-- ===================== CO-MAKERS ===================== --}}
        <table class="fl" style="table-layout:fixed;width:100%;margin-top:4pt;">
            <tr>
                <td class="lbl" style="width:auto;">Co-Maker 1:</td>
                <td class="val" style="width:32%;">{{ $cm1Name !== '' ? $cm1Name : ' ' }}</td>
                <td style="width:8pt;"></td>
                <td class="lbl">Address:</td>
                <td class="val">{{ $cm1Addr !== '' ? $cm1Addr : ' ' }}</td>
            </tr>
        </table>
        <table class="fl" style="table-layout:fixed;width:100%;">
            <tr>
                <td class="lbl" style="width:auto;">Co-Maker 2:</td>
                <td class="val" style="width:32%;">{{ $cm2Name !== '' ? $cm2Name : ' ' }}</td>
                <td style="width:8pt;"></td>
                <td class="lbl">Address:</td>
                <td class="val">{{ $cm2Addr !== '' ? $cm2Addr : ' ' }}</td>
            </tr>
        </table>

        {{-- ===================== ADDITIONAL TERMS ===================== --}}
        <table class="fl" style="table-layout:fixed;width:100%;margin-top:4pt;">
            <tr>
                <td class="lbl" style="width:auto;">Term in Days:</td>
                <td class="val" style="width:20%;">{{ $termDays !== null ? $int($termDays) : ' ' }}</td>
                <td style="width:8pt;"></td>
                <td class="lbl">Amount in Words:</td>
                <td class="val">{{ $amtWords !== '' ? $amtWords : ' ' }}</td>
            </tr>
        </table>
        <table class="fl">
            <tr>
                <td class="lbl">Interest Rate in Words:</td>
                <td class="val">{{ $rateWords !== '' ? $rateWords : ' ' }}</td>
            </tr>
        </table>

        {{-- ===================== WITNESSES ===================== --}}
        <table class="sig-layout">
            <tr>
                <td class="sig-col">
                    <div class="sig-name">{{ $witness1 !== '' ? $witness1 : ' ' }}</div>
                    <div class="sig-lbl">Witness 1</div>
                </td>
                <td class="sig-col">
                    <div class="sig-name">{{ $witness2 !== '' ? $witness2 : ' ' }}</div>
                    <div class="sig-lbl">Witness 2</div>
                </td>
            </tr>
        </table>
    </body>
</html>
