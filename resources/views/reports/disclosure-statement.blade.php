@php
    $companyName  = trim((string) ($organization['company_name'] ?? ''));
    $headerDesign = $reportHeader['designData'] ?? null;
    $headerLogo   = $organizationLogoDataUri ?? null;

    $borrowerName = mb_strtoupper(trim((string) ($applicant['full_name'] ?? '')));
    $address      = trim((string) ($applicant['address'] ?? ''));
    $reference    = trim((string) ($loan['reference'] ?? ''));

    $approvedAmount      = $loan['approved_amount_raw'] ?? null;
    $interestRate        = $loan['interest_rate_raw'] ?? null;
    $interestNotDeducted = $loan['interest_not_deducted_raw'] ?? null;
    $serviceChargeRate   = $loan['service_charge_rate_raw'] ?? null;
    $serviceChargeAmt    = $loan['service_charge_amount_raw'] ?? null;
    $financeChargeTotal  = $loan['finance_charge_total_raw'] ?? null;
    $insurancePremium    = $loan['insurance_premium_raw'] ?? null;
    $loanSecurityAmt     = $loan['loan_security_amount_raw'] ?? null;
    $docStampAmt         = $loan['documentary_stamp_amount_raw'] ?? null;
    $notarialFee         = $loan['notarial_fee_raw'] ?? null;
    $nonFinanceTotal     = $loan['non_finance_charge_total_raw'] ?? null;
    $deductionsTotal     = $loan['deductions_total_raw'] ?? null;
    $netProceeds         = $loan['net_proceeds_raw'] ?? null;
    $amortizationTotal   = $loan['amortization_total_raw'] ?? null;
    $paymentMode         = trim((string) ($loan['payment_mode_workbook'] ?? ''));
    $approvedTerm        = $loan['approved_term_raw'] ?? null;

    $reviewerName = mb_strtoupper(trim((string) ($reviewer['name'] ?? '')));
    $reviewerPos  = trim((string) ($reviewer['position'] ?? ''));

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
        <title>Disclosure Statement</title>
        <style>
            @page {
                size: 8.5in 13in;
                margin: .55in .7in .6in .7in;
            }

            body {
                margin: 0;
                color: #111;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 8pt;
                line-height: 1.25;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .report-header {
                margin: 0 0 4pt;
                text-align: center;
            }

            .report-header-design {
                display: block;
                width: 100%;
                max-height: 56pt;
                margin: 0 auto;
                object-fit: contain;
            }

            .report-header--fallback { text-align: center; }

            .report-header-logo {
                display: block;
                max-width: 46pt;
                max-height: 46pt;
                margin: 0 auto 3pt;
                object-fit: contain;
            }

            .report-header-company {
                font-size: 10pt;
                font-weight: 700;
                letter-spacing: 0.04em;
                text-transform: uppercase;
            }

            .document-title {
                margin: 0;
                font-size: 11pt;
                font-weight: 700;
                text-align: center;
                text-transform: uppercase;
            }

            .document-subtitle {
                margin: 0 0 5pt;
                font-size: 8pt;
                font-style: italic;
                text-align: center;
            }

            .fl {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 2pt;
            }

            .fl td { padding: 1pt 2pt; vertical-align: bottom; }
            .fl .lbl { white-space: nowrap; font-weight: 600; padding-right: 4pt; }
            .fl .val { border-bottom: 0.6pt solid #333; font-weight: 700; min-width: 50pt; }

            .ds-table {
                width: 100%;
                border-collapse: collapse;
                margin: 3pt 0;
            }

            .ds-table td {
                padding: 1pt 3pt;
                vertical-align: top;
            }

            .ds-num { width: 16pt; font-weight: 700; text-align: center; }
            .ds-let { width: 16pt; text-align: center; }
            .ds-label { font-weight: 600; }
            .ds-section { font-weight: 700; text-transform: uppercase; }
            .ds-amt {
                width: 78pt;
                text-align: right;
                font-weight: 700;
                border-bottom: 0.6pt solid #333;
            }
            .ds-ref { width: 26pt; text-align: center; font-weight: 700; }
            .ds-peso { width: 10pt; text-align: center; }
            .ds-total td { font-weight: 700; text-transform: uppercase; }
            .ds-total .ds-label { border-top: 0.6pt solid #555; }

            .ds-cols-head td {
                font-size: 7pt;
                font-weight: 700;
                text-align: center;
                padding-bottom: 0;
            }

            .ds-opt {
                font-size: 7.5pt;
                padding-left: 26pt;
            }
            .ds-opt .box { font-family: "Courier New", monospace; }

            .ds-note {
                font-size: 7pt;
                font-style: italic;
            }

            .ack {
                margin: 6pt 0 2pt;
                font-size: 7.5pt;
            }

            .sig-layout {
                width: 100%;
                border-collapse: separate;
                table-layout: fixed;
                margin-top: 10pt;
            }

            .sig-col { width: 50%; vertical-align: top; padding: 0 4pt; }

            .sig-name {
                min-height: 12pt;
                padding-top: 16pt;
                font-size: 8.5pt;
                font-weight: 700;
                text-align: center;
                text-transform: uppercase;
                border-bottom: 0.6pt solid #333;
            }

            .sig-lbl {
                margin-top: 2pt;
                font-size: 7pt;
                text-align: center;
            }

            .notice {
                margin-top: 6pt;
                font-size: 7.5pt;
                font-weight: 700;
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
                        {{ $companyName !== '' ? $companyName : 'Disclosure Statement' }}
                    </div>
                </div>
            @endif
        </div>

        <div class="document-title">Disclosure Statement on Loan/Credit Transaction</div>
        <div class="document-subtitle">(As Required Under R.A. 3765 Truth In Lending Act)</div>

        {{-- ===================== BORROWER HEADER ===================== --}}
        <table class="fl" style="table-layout:fixed;width:100%;">
            <tr>
                <td class="lbl" style="width:auto;">Name of Borrower:</td>
                <td class="val" style="width:48%;">{{ $borrowerName !== '' ? $borrowerName : ' ' }}</td>
                <td style="width:8pt;"></td>
                <td class="lbl">Loan Number:</td>
                <td class="val">{{ $reference !== '' ? $reference : ' ' }}</td>
            </tr>
        </table>
        <table class="fl">
            <tr>
                <td class="lbl">Address:</td>
                <td class="val">{{ $address !== '' ? $address : ' ' }}</td>
            </tr>
        </table>

        {{-- ===================== STATUTORY ITEMS ===================== --}}
        <table class="ds-table">
            {{-- 1. Loan Granted (A) --}}
            <tr>
                <td class="ds-num">1</td>
                <td class="ds-label ds-section" colspan="3">Loan Granted (Amount to be Financed)</td>
                <td class="ds-peso">(Php)</td>
                <td class="ds-amt">{{ $approvedAmount !== null ? $fmt($approvedAmount) : '' }}</td>
                <td class="ds-ref">( A )</td>
            </tr>

            {{-- 2. Finance Charges --}}
            <tr>
                <td class="ds-num">2</td>
                <td class="ds-section" colspan="6">Finance Charges</td>
            </tr>
            <tr class="ds-cols-head">
                <td></td>
                <td colspan="4"></td>
                <td>Not Deducted<br>From Proceeds of Loan</td>
                <td>Deducted<br>From Proceeds of Loan</td>
            </tr>
            {{-- a. Interest --}}
            <tr>
                <td class="ds-let">a.</td>
                <td class="ds-label">Interest</td>
                <td>{{ $interestRate !== null ? $pct($interestRate) : '' }} p.a.</td>
                <td colspan="2" style="font-size:7pt;">From _______ To _______</td>
                <td class="ds-amt">{{ $interestNotDeducted !== null ? 'P '.$fmt($interestNotDeducted) : '' }}</td>
                <td class="ds-amt">&nbsp;</td>
            </tr>
            <tr><td></td><td class="ds-opt" colspan="6">
                <span class="box">(&nbsp;&nbsp;)</span> Single
                <span class="box">(&nbsp;&nbsp;)</span> Compound
                <span class="box">(&nbsp;&nbsp;)</span> Semi-Annual
                <span class="box">(&nbsp;&nbsp;)</span> Annual
            </td></tr>
            <tr><td></td><td class="ds-opt" colspan="6">
                <span class="box">(&nbsp;&nbsp;)</span> Monthly
                <span class="box">(&nbsp;&nbsp;)</span> Quarterly
                <span class="box">(&nbsp;&nbsp;)</span> Semi-Monthly
                <span class="box">(&nbsp;&nbsp;)</span> Lump-sum
            </td></tr>
            {{-- b. Premium Charges --}}
            <tr>
                <td class="ds-let">b.</td>
                <td class="ds-label" colspan="2">Premium Charges</td>
                <td colspan="2">(&nbsp;&nbsp;&nbsp;%&nbsp;&nbsp;&nbsp;)</td>
                <td class="ds-amt">&nbsp;</td>
                <td class="ds-amt">&nbsp;</td>
            </tr>
            {{-- c. Commitment Fee --}}
            <tr>
                <td class="ds-let">c.</td>
                <td class="ds-label" colspan="4">Commitment Fee</td>
                <td class="ds-amt">&nbsp;</td>
                <td class="ds-amt">&nbsp;</td>
            </tr>
            {{-- d. Guarantee Fee --}}
            <tr>
                <td class="ds-let">d.</td>
                <td class="ds-label" colspan="4">Guarantee Fee</td>
                <td class="ds-amt">&nbsp;</td>
                <td class="ds-amt">&nbsp;</td>
            </tr>
            {{-- e. Other Charges --}}
            <tr>
                <td class="ds-let">e.</td>
                <td class="ds-label" colspan="4">Other Charges Incidental To The Extension of Credit</td>
                <td class="ds-amt">&nbsp;</td>
                <td class="ds-amt">&nbsp;</td>
            </tr>
            <tr>
                <td></td>
                <td style="font-size:7pt;">(Specify)</td>
                <td class="ds-label" colspan="2">Service Charge At {{ $serviceChargeRate !== null ? $pct($serviceChargeRate) : '' }}</td>
                <td></td>
                <td class="ds-amt">&nbsp;</td>
                <td class="ds-amt">{{ $serviceChargeAmt !== null ? 'P '.$fmt($serviceChargeAmt) : '' }}</td>
            </tr>
            {{-- Total Finance Charges (B) --}}
            <tr class="ds-total">
                <td></td>
                <td class="ds-label" colspan="4">Total Finance Charges</td>
                <td class="ds-amt">{{ $financeChargeTotal !== null ? 'P '.$fmt($financeChargeTotal) : '' }}</td>
                <td class="ds-ref">( B )</td>
            </tr>

            {{-- 3. Non Finance Charges --}}
            <tr>
                <td class="ds-num">3</td>
                <td class="ds-section" colspan="6">Non Finance Charges</td>
            </tr>
            <tr>
                <td></td>
                <td class="ds-label" colspan="4">a. Insurance Premium</td>
                <td class="ds-amt">{{ $insurancePremium !== null ? 'P '.$fmt($insurancePremium) : '' }}</td>
                <td></td>
            </tr>
            <tr>
                <td></td>
                <td class="ds-label" colspan="4">b. Loan Security</td>
                <td class="ds-amt">{{ $loanSecurityAmt !== null ? 'P '.$fmt($loanSecurityAmt) : '' }}</td>
                <td></td>
            </tr>
            <tr>
                <td></td>
                <td class="ds-label" colspan="4">c. Documentary Stamps</td>
                <td class="ds-amt">{{ $docStampAmt !== null ? 'P '.$fmt($docStampAmt) : '' }}</td>
                <td></td>
            </tr>
            <tr>
                <td></td>
                <td class="ds-label" colspan="4">d. Notarial Fees</td>
                <td class="ds-amt">{{ $notarialFee !== null ? 'P '.$fmt($notarialFee) : '' }}</td>
                <td></td>
            </tr>
            <tr>
                <td></td>
                <td class="ds-label" colspan="4">e. Others:</td>
                <td class="ds-amt">P&nbsp;</td>
                <td></td>
            </tr>
            {{-- Total Non Finance Charges (C) --}}
            <tr class="ds-total">
                <td></td>
                <td class="ds-label" colspan="4">Total Non Finance Charges</td>
                <td class="ds-amt">{{ $nonFinanceTotal !== null ? 'P '.$fmt($nonFinanceTotal) : '' }}</td>
                <td class="ds-ref">( C )</td>
            </tr>

            {{-- 4. Total Deductions (D) --}}
            <tr class="ds-total">
                <td class="ds-num">4</td>
                <td class="ds-label" colspan="4">Total Deductions From Proceeds of Loan (B + C)</td>
                <td class="ds-amt">{{ $deductionsTotal !== null ? 'P '.$fmt($deductionsTotal) : '' }}</td>
                <td class="ds-ref">( D )</td>
            </tr>

            {{-- 5. Net Proceeds (E) --}}
            <tr class="ds-total">
                <td class="ds-num">5</td>
                <td class="ds-label" colspan="4">Net Proceeds of Loan (A Less D)</td>
                <td class="ds-amt">{{ $netProceeds !== null ? 'P '.$fmt($netProceeds) : '' }}</td>
                <td class="ds-ref">( E )</td>
            </tr>

            {{-- 6. Percentage of Finance Charges -- blank in statutory template (see TODO) --}}
            {{-- TODO(EIR): R.A. 3765 percentage-of-finance-charges value is not computed by the
                 app and is blank in the source workbook. Confirm with WIBS before wiring. --}}
            <tr>
                <td class="ds-num">6</td>
                <td class="ds-label" colspan="4">Percentage of Finance Charges to Total Amount Financed</td>
                <td class="ds-amt">&nbsp;%</td>
                <td></td>
            </tr>
            <tr>
                <td></td>
                <td class="ds-note" colspan="6">(Computed in accordance with Method of Computations in accordance with Sec. 2 (1) of CB Circular 158)</td>
            </tr>

            {{-- 7. Effective Interest Rate -- blank in statutory template (see TODO) --}}
            {{-- TODO(EIR): Effective interest rate is not computed by the app and is blank in
                 the source workbook. Do not guess the R.A. 3765 formula. Confirm with WIBS. --}}
            <tr>
                <td class="ds-num">7</td>
                <td class="ds-label" colspan="4">Effective Interest Rate</td>
                <td class="ds-amt">&nbsp;% p.a.</td>
                <td></td>
            </tr>

            {{-- 8. Schedule of Payment --}}
            <tr>
                <td class="ds-num">8</td>
                <td class="ds-section" colspan="6">Schedule of Payment</td>
            </tr>
            <tr>
                <td></td>
                <td class="ds-label" colspan="4">a. Single Payment Due On</td>
                <td class="ds-amt">P&nbsp;</td>
                <td></td>
            </tr>
            <tr>
                <td></td>
                <td class="ds-label" colspan="2">b. Total Installment Payment</td>
                <td colspan="2">{{ $paymentMode !== '' ? $paymentMode : '' }}</td>
                <td class="ds-amt">{{ $amortizationTotal !== null ? 'P '.$fmt($amortizationTotal) : '' }}</td>
                <td></td>
            </tr>
            <tr>
                <td></td>
                <td class="ds-label" colspan="4">Payable in {{ $approvedTerm !== null ? $int($approvedTerm) : '____' }} months/quarter/years</td>
                <td></td>
                <td></td>
            </tr>

            {{-- 9. Collateral --}}
            <tr>
                <td class="ds-num">9</td>
                <td class="ds-section" colspan="6">Collateral</td>
            </tr>
            <tr>
                <td></td>
                <td colspan="6">This loan is wholly/partly secured by:</td>
            </tr>
            <tr><td></td><td class="ds-opt" colspan="6">
                <span class="box">(&nbsp;&nbsp;)</span> Check/s
                <span class="box">(&nbsp;&nbsp;)</span> Chattels
                <span class="box">(&nbsp;&nbsp;)</span> Government Securities
            </td></tr>
            <tr><td></td><td class="ds-opt" colspan="6">
                <span class="box">(&nbsp;&nbsp;)</span> Real Estate
                <span class="box">(&nbsp;&nbsp;)</span> Unsecured
            </td></tr>

            {{-- 10. Additional Charges --}}
            <tr>
                <td class="ds-num">10</td>
                <td class="ds-label" colspan="6">Additional Charges In Case Certain Stipulations Are Not Met By The Borrower</td>
            </tr>
        </table>

        {{-- ===================== CERTIFIED CORRECT ===================== --}}
        <table class="sig-layout">
            <tr>
                <td class="sig-col">&nbsp;</td>
                <td class="sig-col">
                    <div style="font-size:7.5pt;font-weight:700;">Certified Correct:</div>
                    <div class="sig-name">{{ $reviewerName !== '' ? $reviewerName : ' ' }}</div>
                    <div class="sig-lbl">Signature Over Printed Name</div>
                    <div class="sig-name" style="padding-top:8pt;">{{ $reviewerPos !== '' ? $reviewerPos : ' ' }}</div>
                    <div class="sig-lbl">Position</div>
                </td>
            </tr>
        </table>

        {{-- ===================== ACKNOWLEDGEMENT ===================== --}}
        <div class="ack">
            I ACKNOWLEDGE RECEIPT OF A COPY OF THE STATEMENT PRIOR TO THE CONSUMMATION OF THE
            CREDIT TRANSACTION AND CERTIFY THAT I UNDERSTAND AND FULLY AGREE TO THE TERMS AND
            CONDITIONS THEREOF.
        </div>

        <table class="sig-layout">
            <tr>
                <td class="sig-col">
                    <div style="font-size:7.5pt;">Date: _______________</div>
                </td>
                <td class="sig-col">
                    <div class="sig-name">{{ $borrowerName !== '' ? $borrowerName : ' ' }}</div>
                    <div class="sig-lbl">Signature of Borrower Over Printed Name</div>
                </td>
            </tr>
        </table>

        <div class="notice">
            NOTICE TO BORROWER: YOU ARE ENTITLED TO A COPY OF THE PAPER WHICH YOU SHALL SIGN.
        </div>
    </body>
</html>
