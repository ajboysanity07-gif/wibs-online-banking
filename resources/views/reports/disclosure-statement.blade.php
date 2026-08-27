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
    $otherCharges        = $loan['other_charges_amount_raw'] ?? null;
    $otherChargesDescription = trim((string) ($loan['other_charges_description'] ?? ''));
    $nonFinanceTotal     = $loan['non_finance_charge_total_raw'] ?? null;
    $deductionsTotal     = $loan['deductions_total_raw'] ?? null;
    $netProceeds         = $loan['net_proceeds_raw'] ?? null;
    $amortizationTotal   = $loan['amortization_total_raw'] ?? null;
    $paymentMode         = trim((string) ($loan['payment_mode_workbook'] ?? ''));
    $isLumpsum           = $paymentMode === 'DUE-DATE';
    $isEmergencyLoan     = trim((string) ($loan['kind_of_loan'] ?? '')) === 'Emergency';
    $approvedTerm        = $loan['approved_term_raw'] ?? null;

    // "Certified Correct" is always signed by the bookkeeper, not the loan
    // manager/reviewer who processed the request -- do not source this from
    // $reviewer.
    $reviewerName        = 'VELINA P. GAMUTAN';
    $reviewerPosition    = 'BOOKKEEPER';

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
        @if (! empty($reportTypography['fontFaceCss'] ?? null))
            <style>{!! $reportTypography['fontFaceCss'] !!}</style>
        @endif
        <style>
            @page {
                size: 8.5in 13in;
                margin: .55in .7in .6in .7in;
            }

            body {
                margin: 0;
                color: #111;
                font-family: "Calibri", Arial, sans-serif;
                font-size: 8pt;
                line-height: 1.15;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .report-header {
                margin: 0 0 12pt;
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

            /* ===== Workbook grid (Disclosure Statement sheet, columns A-O) ===== */
            .wb {
                width: 100%;
                table-layout: fixed;
                border-collapse: collapse;
            }

            .wb td {
                padding: 0 1pt;
                vertical-align: bottom;
            }

            .b7  { font-size: 7pt; }
            .b9  { font-size: 9pt; }
            .b10 { font-size: 10pt; }
            .bold { font-weight: 700; }
            .nw { white-space: nowrap; }
            .l { text-align: left; }
            .c { text-align: center; }
            .r { text-align: right; }
            .u  { border-bottom: 0.6pt solid #333; }
            .ut { border-top: 0.6pt solid #333; }
            .ub { border-top: 0.6pt solid #333; border-bottom: 0.6pt solid #333; }

            .ds-opt {
                font-size: 7.5pt;
                white-space: nowrap;
            }
            .ds-opt .box { font-family: "Courier New", monospace; }
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

        {{-- ===================== STATUTORY BODY (workbook rows 7-59) ===================== --}}
        <table class="wb">
            <colgroup>
                <col style="width:3.594%" />
                <col style="width:3.486%" />
                <col style="width:7.855%" />
                <col style="width:8.396%" />
                <col style="width:7.855%" />
                <col style="width:10.909%" />
                <col style="width:2.504%" />
                <col style="width:8.504%" />
                <col style="width:2.072%" />
                <col style="width:10.359%" />
                <col style="width:2.396%" />
                <col style="width:9.819%" />
                <col style="width:4.036%" />
                <col style="width:13.747%" />
                <col style="width:4.468%" />
            </colgroup>

            {{-- Row 7: Name of Borrower / Loan Number --}}
            <tr>
                <td class="bold nw" colspan="3">NAME OF BORROWER:</td>
                <td class="u" colspan="8">&nbsp;{{ $borrowerName !== '' ? $borrowerName : '' }}</td>
                <td class="bold c nw">LOAN NUMBER:</td>
                <td class="u" colspan="2" style="padding-left: 4pt;">&nbsp;{{ $reference !== '' ? $reference : '' }}</td>
                <td></td>
            </tr>

            {{-- Row 8: Address --}}
            <tr>
                <td class="bold nw" colspan="2">ADDRESS:</td>
                <td class="u" colspan="9">&nbsp;{{ $address !== '' ? $address : '' }}</td>
                <td colspan="4"></td>
            </tr>

            {{-- Row 9: 1. Loan Granted (A) --}}
            <tr>
                <td class="r">1</td>
                <td class="bold nw" colspan="4">LOAN GRANTED (Amount to be financed)</td>
                <td class="u" colspan="2">&nbsp;</td>
                <td class="ub" colspan="5">&nbsp;</td>
                <td class="bold nw">(Php)</td>
                <td class="b10 bold r ub">{{ $approvedAmount !== null ? $fmt($approvedAmount) : '' }}</td>
                <td class="bold nw">( A )</td>
            </tr>

            {{-- Row 10: 2. Finance Charges --}}
            <tr>
                <td class="r">2</td>
                <td class="bold" colspan="14">FINANCE CHARGES</td>
            </tr>

            {{-- Rows 11-13: Not Deducted / Deducted header stacks --}}
            <tr>
                <td colspan="9"></td>
                <td class="c">Not Deducted</td>
                <td></td>
                <td class="c">Deducted</td>
                <td colspan="3"></td>
            </tr>
            <tr>
                <td colspan="9"></td>
                <td class="c">From</td>
                <td></td>
                <td class="c">From</td>
                <td colspan="3"></td>
            </tr>
            <tr>
                <td colspan="9"></td>
                <td class="c" colspan="3">Proceeds of Loan</td>
                <td colspan="3"></td>
            </tr>

            {{-- Row 14: a. Interest --}}
            {{-- Monthly loans: interest in "Not Deducted" column (emergency
                 loans show the amount; others leave it blank per the template).
                 Lumpsum/Due-date: interest is advance interest, shown in the
                 "Deducted" column instead. --}}
            <tr>
                <td class="r">a.</td>
                <td class="nw">Interest</td>
                <td></td>
                <td class="b9 bold c u">{{ $interestRate !== null ? $pct($interestRate) : '' }}</td>
                <td class="c nw">% p.a. From</td>
                <td class="b7 u">&nbsp;</td>
                <td class="c">To</td>
                <td class="b7 u">&nbsp;</td>
                <td class="c">P</td>
                <td class="b9 bold r u">{{ !$isLumpsum && $isEmergencyLoan && $interestNotDeducted !== null ? $fmt($interestNotDeducted) : '' }}</td>
                <td class="c">P</td>
                <td class="b9 bold r u">{{ $isLumpsum && $interestNotDeducted !== null ? $fmt($interestNotDeducted) : '' }}</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>

            {{-- Rows 15-18: compounding frequency checkboxes --}}
            <tr>
                <td></td>
                <td class="ds-opt" colspan="2"><span class="box">(     )</span> Single</td>
                <td></td>
                <td class="ds-opt" colspan="2"><span class="box">(               )</span> Monthly</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="ub">&nbsp;</td>
                <td></td>
                <td class="ub">&nbsp;</td>
                <td colspan="3"></td>
            </tr>
            <tr>
                <td></td>
                <td class="ds-opt" colspan="2"><span class="box">(     )</span> Compound</td>
                <td></td>
                <td class="ds-opt" colspan="2"><span class="box">(               )</span> Quarterly</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="ub">&nbsp;</td>
                <td></td>
                <td class="u">&nbsp;</td>
                <td colspan="3"></td>
            </tr>
            <tr>
                <td></td>
                <td class="ds-opt" colspan="2"><span class="box">(     )</span> Semi-Annual</td>
                <td></td>
                <td class="ds-opt" colspan="2"><span class="box">(               )</span> Semi-Monthly</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="ub">&nbsp;</td>
                <td></td>
                <td class="u">&nbsp;</td>
                <td colspan="3"></td>
            </tr>
            <tr>
                <td></td>
                <td class="ds-opt" colspan="2"><span class="box">(     )</span> Annual</td>
                <td></td>
                <td class="ds-opt" colspan="2"><span class="box">(               )</span> Lump-sum</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="ub">&nbsp;</td>
                <td></td>
                <td class="u">&nbsp;</td>
                <td colspan="3"></td>
            </tr>

            {{-- Rows 19-22: b-e finance charge lines --}}
            <tr>
                <td class="r">b.</td>
                <td class="nw" colspan="3">Premium Charges</td>
                <td colspan="2"><span class="box">(      %    )</span></td>
                <td colspan="9"></td>
            </tr>
            <tr>
                <td class="r">c.</td>
                <td class="nw" colspan="4">Commitment Fee</td>
                <td colspan="10"></td>
            </tr>
            <tr>
                <td class="r">d.</td>
                <td class="nw" colspan="4">Guarantee Fee</td>
                <td colspan="10"></td>
            </tr>
            <tr>
                <td class="r">e.</td>
                <td class="nw" colspan="13">Other Charges Incidental To The Extension of Credit</td>
                <td colspan="1"></td>
            </tr>

            {{-- Row 23: (Specify) Service Charge At --}}
            <tr>
                <td></td>
                <td class="nw" colspan="2">(Specify)</td>
                <td class="b9 bold r u" colspan="2">Service Charge At</td>
                <td class="b9 bold l u">{{ $serviceChargeRate !== null ? $pct($serviceChargeRate) : '' }}</td>
                <td colspan="2"></td>
                <td class="r">P</td>
                <td class="u">&nbsp;</td>
                <td class="r">P</td>
                <td class="b9 r u">{{ $serviceChargeAmt !== null ? $fmt($serviceChargeAmt) : '' }}</td>
                <td class="r">P</td>
                <td class="b9 bold r u">{{ $serviceChargeAmt !== null ? $fmt($serviceChargeAmt) : '' }}</td>
                <td></td>
            </tr>

            {{-- Row 24: blank underline strip --}}
            <tr>
                <td colspan="3"></td>
                <td class="ub" colspan="3">&nbsp;</td>
                <td colspan="2"></td>
                <td></td>
                <td class="ub">&nbsp;</td>
                <td></td>
                <td class="ub">&nbsp;</td>
                <td></td>
                <td class="ub">&nbsp;</td>
                <td></td>
            </tr>

            {{-- Row 25: Total Finance Charges (B) --}}
            <tr>
                <td class="bold" colspan="9">TOTAL FINANCE CHARGES</td>
                <td class="ut">&nbsp;</td>
                <td></td>
                <td class="ut">&nbsp;</td>
                <td class="r">P</td>
                <td class="b10 bold r ub">{{ $financeChargeTotal !== null ? $fmt($financeChargeTotal) : '' }}</td>
                <td class="bold nw">( B )</td>
            </tr>

            {{-- Row 27: 3. Non Finance Charges --}}
            <tr>
                <td class="r">3</td>
                <td class="bold" colspan="14">NON FINANCE CHARGES</td>
            </tr>

            {{-- Rows 28-32: a-e non finance charge lines --}}
            <tr>
                <td></td>
                <td class="nw" colspan="3">a. Insurance Premium</td>
                <td class="r">P</td>
                <td class="b9 r u">{{ $insurancePremium !== null ? $fmt($insurancePremium) : '' }}</td>
                <td colspan="9"></td>
            </tr>
            <tr>
                <td></td>
                <td class="nw" colspan="3">b. Loan Security</td>
                <td class="r">P</td>
                <td class="b9 r ub">{{ $loanSecurityAmt !== null ? $fmt($loanSecurityAmt) : '' }}</td>
                <td colspan="9"></td>
            </tr>
            <tr>
                <td></td>
                <td class="nw" colspan="3">c. Documentary Stamps</td>
                <td class="r">P</td>
                <td class="b9 r ub">{{ $docStampAmt !== null ? $fmt($docStampAmt) : '' }}</td>
                <td colspan="9"></td>
            </tr>
            <tr>
                <td></td>
                <td class="nw" colspan="3">d. Notarial Fees</td>
                <td class="r">P</td>
                <td class="b9 r ub">{{ $notarialFee !== null ? $fmt($notarialFee) : '' }}</td>
                <td colspan="9"></td>
            </tr>
            <tr>
                <td></td>
                <td class="nw" colspan="3">e. Others:{{ $otherChargesDescription !== '' ? ' '.$otherChargesDescription : '' }}</td>
                <td class="r">P</td>
                <td class="b9 r ub">{{ $otherCharges !== null ? $fmt($otherCharges) : '' }}</td>
                <td colspan="9"></td>
            </tr>

            {{-- Row 33: Total Non Finance Charges (C) --}}
            <tr>
                <td class="bold" colspan="8">TOTAL NON FINANCE CHARGES</td>
                <td class="r">P</td>
                <td class="u">&nbsp;</td>
                <td></td>
                <td class="b9 r u">{{ $nonFinanceTotal !== null ? $fmt($nonFinanceTotal) : '' }}</td>
                <td class="r">P</td>
                <td class="b9 bold r u">{{ $nonFinanceTotal !== null ? $fmt($nonFinanceTotal) : '' }}</td>
                <td class="bold nw">( C )</td>
            </tr>

            {{-- Row 34: 4. Total Deductions (D) --}}
            <tr>
                <td class="r">4</td>
                <td class="nw" colspan="10">TOTAL DEDUCTIONS FROM PROCEEDS OF LOAN (B + C)</td>
                <td class="ut">&nbsp;</td>
                <td class="r">P</td>
                <td class="b9 bold r ub">{{ $deductionsTotal !== null ? $fmt($deductionsTotal) : '' }}</td>
                <td class="bold nw">( D )</td>
            </tr>

            {{-- Row 35: 5. Net Proceeds (E) --}}
            <tr>
                <td class="r">5</td>
                <td class="nw" colspan="10">NET PROCEEDS OF LOAN (A LESS D)</td>
                <td></td>
                <td class="r">P</td>
                <td class="b10 bold r ub">{{ $netProceeds !== null ? $fmt($netProceeds) : '' }}</td>
                <td class="bold nw">( E )</td>
            </tr>

            {{-- Row 36-37: 6. Percentage of Finance Charges -- blank in statutory template (see TODO) --}}
            {{-- TODO(EIR): R.A. 3765 percentage-of-finance-charges value is not computed by the
                 app and is blank in the source workbook. Confirm with WIBS before wiring. --}}
            <tr>
                <td class="r">6</td>
                <td colspan="14">PERCENTAGE OF FINANCE CHARGES TO TOTAL AMOUNT FINANCED</td>
            </tr>
            <tr>
                <td></td>
                <td colspan="11">(Computed in accordance with (Method of Computations in accordance with Sec. 2 (1) of CB Circular 158)</td>
                <td class="nw">%</td>
                <td colspan="2"></td>
            </tr>

            {{-- Row 38: 7. Effective Interest Rate -- blank in statutory template (see TODO) --}}
            {{-- TODO(EIR): Effective interest rate is not computed by the app and is blank in
                 the source workbook. Do not guess the R.A. 3765 formula. Confirm with WIBS. --}}
            <tr>
                <td class="r">7</td>
                <td class="nw" colspan="4">EFFECTIVE INTEREST RATE</td>
                <td class="u">&nbsp;</td>
                <td class="l nw" colspan="2">% p.a.</td>
                <td colspan="7"></td>
            </tr>

            {{-- Row 39-42: 8. Schedule of Payment --}}
            <tr>
                <td class="r">8</td>
                <td class="nw" colspan="4">SCHEDULE OF PAYMENT</td>
                <td class="ut">&nbsp;</td>
                <td colspan="9"></td>
            </tr>
            @if ($isLumpsum)
                <tr>
                    <td></td>
                    <td class="nw" colspan="3">a. Single Payment Due On</td>
                    <td class="r">P</td>
                    <td class="b10 bold r ub">{{ $amortizationTotal !== null ? $fmt($amortizationTotal) : '' }}</td>
                    <td colspan="9"></td>
                </tr>
            @else
                <tr>
                    <td></td>
                    <td class="nw" colspan="3">b. Total Installment Payment</td>
                    <td class="r">P</td>
                    <td class="b10 bold r ub">&nbsp;</td>
                    <td class="b10 bold l" colspan="2">&nbsp;</td>
                    <td colspan="7"></td>
                </tr>
            @endif
            <tr>
                <td></td>
                <td class="nw" colspan="2">Payable in</td>
                <td class="b9 bold c u">{{ $approvedTerm !== null ? $int($approvedTerm) : '' }}</td>
                <td class="nw" colspan="2">months/quarter/years</td>
                <td colspan="9"></td>
            </tr>

            {{-- Row 43-46: 9. Collateral --}}
            <tr>
                <td class="r">9</td>
                <td colspan="14">COLLATERAL</td>
            </tr>
            <tr>
                <td></td>
                <td colspan="14">This loan is wholly/partly secured by:</td>
            </tr>
            <tr>
                <td></td>
                <td class="ds-opt" colspan="3"><span class="box">(               )</span> Check/s</td>
                <td class="ds-opt" colspan="2"><span class="box">(               )</span> Chattels</td>
                <td class="ds-opt" colspan="6"><span class="box">(    )</span> Government Securities</td>
                <td colspan="3"></td>
            </tr>
            <tr>
                <td></td>
                <td class="ds-opt" colspan="3"><span class="box">(               )</span> Real Estate</td>
                <td class="ds-opt" colspan="3"><span class="box">(               )</span> Unsecured</td>
                <td colspan="8"></td>
            </tr>

            {{-- Row 47-48: 10. Additional Charges --}}
            <tr>
                <td class="r">10</td>
                <td colspan="14">ADDITIONAL CHARGES IN CASE CERTAIN STIPULATIONS ARE NOT MET BY THE BORROWER</td>
            </tr>
            <tr>
                <td></td>
                <td class="u" colspan="4">&nbsp;</td>
                <td></td>
                <td class="u" colspan="4">&nbsp;</td>
                <td colspan="5"></td>
            </tr>

            {{-- Rows 49-53: Certified Correct block --}}
            <tr>
                <td colspan="11"></td>
                <td class="bold" colspan="3">CERTIFIED CORRECT:</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="11"></td>
                <td class="b9 c u" colspan="3">{{ mb_strtoupper($reviewerName) }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="11"></td>
                <td class="c nw" colspan="3">Signature Over Printed Name</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="11"></td>
                <td class="c u" colspan="3">{{ mb_strtoupper($reviewerPosition) }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="11"></td>
                <td class="c nw ut" colspan="3">Position</td>
                <td></td>
            </tr>

            {{-- Rows 54-55: Acknowledgement --}}
            <tr>
                <td colspan="2"></td>
                <td colspan="13">I ACKNOWLEDGE RECEIPT OF A COPY OF THE STATEMENT PRIOR TO THE CONSUMMATION OF THE CREDIT TRANSACTION AND CERTIFY</td>
            </tr>
            <tr>
                <td></td>
                <td colspan="14">THAT I UNDERSTAND AND FULLY AGREE TO THE TERMS AND CONDITIONS THEREOF.</td>
            </tr>

            {{-- Rows 57-58: Date / borrower signature --}}
            <tr>
                <td></td>
                <td class="l nw" colspan="2">DATE:</td>
                <td class="b9 c u" colspan="3">&nbsp;</td>
                <td colspan="5"></td>
                <td class="b9 c u" colspan="3">{{ $borrowerName !== '' ? $borrowerName : '' }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="11"></td>
                <td class="c nw ut" colspan="3">Signature of Borrower Over Printed Name</td>
                <td></td>
            </tr>

            {{-- Row 59: Notice --}}
            <tr>
                <td></td>
                <td colspan="14">NOTICE TO BORROWER: YOU ARE ENTITLED TO A COPY OF THE PAPER WHICH YOU SHALL SIGN.</td>
            </tr>
        </table>
    </body>
</html>
