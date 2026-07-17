@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\HtmlString;

    $companyName = trim((string) ($organization['company_name'] ?? ''));
    $displayCompanyName = $companyName !== '' ? $companyName : 'MICROFINANCE FOR RURAL DEVELOPMENT INC.';
    $headerDesign = $reportHeader['designData'] ?? null;
    $headerLogo = $organizationLogoDataUri ?? null;

    $loanTermDays = $loan['term_days'] ?? null;
    $approvedDateShort = trim((string) ($loan['approved_date_short'] ?? ''));
    $maturityDateShort = trim((string) ($loan['maturity_date_short'] ?? ''));
    $approvedAmountRaw = $loan['approved_amount_raw'] ?? null;
    $approvedAmountWords = trim((string) ($loan['approved_amount_words'] ?? ''));
    $interestRateWords = trim((string) ($loan['interest_rate_words'] ?? ''));
    $amortizationTotal = $loan['amortization_total_raw'] ?? null;
    $paymentMode = trim((string) ($loan['payment_mode_workbook'] ?? ''));
    $amortizationCount = $loan['amortization_count'] ?? null;
    $penaltyRate = $loan['penalty_rate_raw'] ?? null;

    $borrowerName = trim((string) ($applicant['full_name'] ?? ''));
    $borrowerAddress = trim((string) ($applicant['address'] ?? ''));
    $coMakerOneName = trim((string) ($co_maker_one['full_name'] ?? ''));
    $coMakerOneAddress = trim((string) ($co_maker_one['address'] ?? ''));
    $coMakerTwoName = trim((string) ($co_maker_two['full_name'] ?? ''));
    $coMakerTwoAddress = trim((string) ($co_maker_two['address'] ?? ''));
    $witnessOneName = trim((string) ($reviewer['witness_one_name'] ?? ''));
    $witnessTwoName = trim((string) ($reviewer['witness_two_name'] ?? ''));

    $formatAmount = static function (mixed $value): string {
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

    $renderValue = static function (
        ?string $value,
        string $blankWidth = '7em',
    ): HtmlString {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return new HtmlString(sprintf(
                '<span class="note-blank" style="min-width:%s;">&nbsp;</span>',
                e($blankWidth),
            ));
        }

        return new HtmlString(sprintf(
            '<span class="note-fill">%s</span>',
            e($trimmed),
        ));
    };
@endphp
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Promissory Note</title>
        <style>
            @page {
                size: 8.5in 13in;
                margin: .75in 1in 1in 1in;
            }

            body {
                margin: 0;
                color: #111111;
                font-family: "Times New Roman", Times, serif;
                font-size: 11pt;
                line-height: 1.4;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .page {
                width: 100%;
            }

            .report-header {
                margin: 0 0 10pt;
                text-align: center;
            }

            .report-header-design {
                display: block;
                width: 100%;
                max-height: 70pt;
                margin: 0 auto;
                object-fit: contain;
            }

            .report-header--fallback {
                padding-bottom: 7pt;
                text-align: center;
            }

            .report-header-logo {
                display: block;
                max-width: 60pt;
                max-height: 60pt;
                margin: 0 auto 6pt;
                object-fit: contain;
            }

            .report-header-company {
                font-size: 12pt;
                font-weight: 700;
                letter-spacing: 0.05em;
                text-transform: uppercase;
            }

            .document-title {
                margin: 0 0 12pt;
                font-size: 14pt;
                font-weight: 700;
                letter-spacing: 0.04em;
                text-align: center;
                text-transform: uppercase;
            }

            .meta-block {
                width: 100%;
                margin: 0 0 12pt;
            }

            .meta-block td {
                padding: 1pt 4pt 1pt 0;
                font-size: 10.5pt;
                vertical-align: top;
                white-space: nowrap;
            }

            .meta-block .meta-label {
                text-align: right;
                font-weight: 600;
            }

            .meta-block .meta-value {
                font-weight: 700;
                min-width: 100pt;
                border-bottom: 0.8pt solid #111;
            }

            .note-body {
                margin: 0 0 10pt;
                text-align: justify;
            }

            .note-fill {
                font-weight: 700;
                text-decoration: underline;
                text-decoration-thickness: 0.8pt;
                text-underline-offset: 2pt;
            }

            .note-blank {
                display: inline-block;
                line-height: 0.95;
                vertical-align: baseline;
                border-bottom: 0.8pt solid #111111;
            }

            .paragraph {
                margin: 0 0 8pt;
                text-align: justify;
                text-indent: 34pt;
            }

            .paragraph--no-indent {
                text-indent: 0;
            }

            .dishonor-block {
                margin: 10pt 0 10pt;
                font-weight: 700;
                text-align: justify;
            }

            .sign-instruction {
                margin: 8pt 0 12pt;
                text-align: center;
                font-style: italic;
                font-size: 10pt;
            }

            .signature-layout {
                width: 100%;
                border-collapse: separate;
                table-layout: fixed;
                margin: 0 0 6pt;
                page-break-inside: avoid;
            }

            .signature-column {
                width: 33.33%;
                vertical-align: top;
                padding: 0 4pt;
            }

            .signature-block {
                width: 100%;
            }

            .signature-signing-area {
                position: relative;
                min-height: 54pt;
            }

            .signature-name {
                min-height: 14pt;
                padding-top: 34pt;
                font-size: 10.5pt;
                font-weight: 700;
                text-align: center;
                text-transform: uppercase;
                border-bottom: 0.8pt solid #111;
            }

            .signature-label {
                margin-top: 2pt;
                font-size: 10pt;
                text-align: center;
                text-transform: uppercase;
            }

            .address-layout {
                width: 100%;
                border-collapse: separate;
                table-layout: fixed;
                margin: 4pt 0 14pt;
            }

            .address-column {
                width: 33.33%;
                vertical-align: top;
                padding: 0 4pt;
                font-size: 10pt;
            }

            .address-label {
                font-weight: 600;
                white-space: nowrap;
            }

            .address-value {
                display: block;
                margin-top: 2pt;
                border-bottom: 0.8pt solid #111;
                min-height: 12pt;
                word-break: break-word;
            }

            .witness-section {
                margin: 8pt 0 0;
            }

            .witness-instruction {
                margin: 0 0 6pt;
                text-align: center;
                font-size: 10pt;
                font-style: italic;
            }

            .witness-layout {
                width: 76%;
                border-collapse: separate;
                table-layout: fixed;
                margin: 0 auto;
                page-break-inside: avoid;
            }

            .witness-column {
                width: 50%;
                vertical-align: top;
                padding: 0 6pt;
            }

            .witness-name {
                min-height: 14pt;
                padding-top: 30pt;
                font-size: 10.5pt;
                font-weight: 700;
                text-align: center;
                text-transform: uppercase;
                border-bottom: 0.8pt solid #111;
            }

            .witness-label {
                margin-top: 2pt;
                font-size: 10pt;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="page">
            <div class="report-header">
                @if ($headerDesign)
                    <img
                        src="{{ $headerDesign }}"
                        alt="Report header design"
                        class="report-header-design"
                    />
                @else
                    <div class="report-header--fallback">
                        @if ($headerLogo)
                            <img
                                src="{{ $headerLogo }}"
                                alt="{{ $companyName !== '' ? $companyName : 'Organization logo' }}"
                                class="report-header-logo"
                            />
                        @endif
                        <div class="report-header-company">
                            {{ $companyName !== '' ? $companyName : 'Promissory Note' }}
                        </div>
                    </div>
                @endif
            </div>

            <div class="document-title">Promissory Note</div>

            <table class="meta-block" style="float:right; width:auto; margin-bottom:8pt;">
                <tr>
                    <td class="meta-label">Date Granted:</td>
                    <td class="meta-value">{{ $approvedDateShort !== '' ? $approvedDateShort : ' ' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Date Due:</td>
                    <td class="meta-value">{{ $maturityDateShort !== '' ? $maturityDateShort : ' ' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Amount:&nbsp;&nbsp;P</td>
                    <td class="meta-value">{{ $approvedAmountRaw !== null ? $formatAmount($approvedAmountRaw) : ' ' }}</td>
                </tr>
            </table>

            <div style="clear:right;"></div>

            <div class="note-body">
                <p class="paragraph">
                    {!! $renderValue($loanTermDays !== null ? (string)(int)$loanTermDays : null, '4em') !!}
                    days after date for value received, I/we promise to pay jointly and severally to the order of
                    <span class="note-fill">{{ $displayCompanyName }}</span>
                    the sum of
                    {!! $renderValue($approvedAmountWords, '18em') !!}
                    P{!! $renderValue($approvedAmountRaw !== null ? $formatAmount($approvedAmountRaw) : null, '8em') !!}
                    Philippine Currency with an interest rate of
                    {!! $renderValue($interestRateWords, '14em') !!}
                    per annum. Amortization/Installment payment of
                    {!! $renderValue($amortizationTotal !== null ? $formatAmount($amortizationTotal) : null, '7em') !!}
                    inclusive of interest every
                    {!! $renderValue($paymentMode, '7em') !!}
                    starting
                    {!! $renderValue($approvedDateShort, '7em') !!}
                    to
                    {!! $renderValue($maturityDateShort, '7em') !!}
                    for
                    {!! $renderValue($amortizationCount !== null ? (string)(int)$amortizationCount : null, '4em') !!}
                    Amortization/Installment payment.
                </p>

                <p class="paragraph">
                    In case I/we fail to pay this note on any of the amortization installments payments when due,
                    I/we further agree to pay a penalty of {!! $renderValue($penaltyRate !== null ? $pct($penaltyRate) : null, '4em') !!} per month of any unpaid arrears starting from the date of default.
                </p>

                <p class="paragraph">
                    In case of defect, fraud or misrepresentation in the application of this loan or violation of any of the
                    terms of the loan evidenced by this note the entire outstanding balance shall immediately become due and demandable.
                </p>

                <p class="paragraph">
                    I/We hereby agree and authorize <span class="note-fill">{{ $displayCompanyName }}</span> to encumber, assign or sell to any
                    person or entity any right which may have under this note and/or any assignment, mortgage lien, pledge or other
                    encumbrances constituted in favor of <span class="note-fill">{{ $displayCompanyName }}</span> pursuant to the provision
                    of the loan agreement and this note if any. The consent herein granted is recognized and acknowledge by me/us as a
                    waiver to all intents and purpose of whatever right I/We may have to notice of actual encumbrance are assignment.
                </p>

                <p class="paragraph">
                    I/We further agree that in case of prepayment of the loan, <span class="note-fill">{{ $displayCompanyName }}</span>
                    is not under any legal obligations to return the interest corresponding to the period from date of prepayment to
                    the stipulated maturity date of the loan. Any repayment made should not therefore affect the computation of the
                    interest rate stipulated herein.
                </p>

                <p class="paragraph">
                    In case of judicial enforcement of this obligation or any part of it. I/We waived all my/our right under the
                    provision of Rule 39 Section 12 of the Rules of Court and I/We and my/our co-maker(s) and encloser(s) shall pay
                    jointly and severally ten percent(10%) of the amount due on the note as attorney's fees, which in no case shall
                    be less than FIVE HUNDRED PESOS(500.00) exclusive of all cost and fees allowed by law.
                </p>

                <p class="paragraph">
                    In case that I/We shall retire from employment anytime I/We hereby waived and relinquish in favor of
                    <span class="note-fill">{{ $displayCompanyName }}</span> from and out of my/our retirement benefits, gratuity and
                    all other benefits due to me/us from the institution such amount equivalient to my/our Outstanding Loan Balance
                    under this note including the interest and other charges. I/We hereby obligate myself/ourselves to give a written
                    notice to <span class="note-fill">{{ $displayCompanyName }}</span> within (7) days after my/our retirement.
                </p>
            </div>

            <div class="dishonor-block">
                DEMAND AND DISHONOR WAIVED Holder may accept partial payment reserving his right of recourse against each and all endorsers.
            </div>

            <div class="sign-instruction">(SIGN OVER PRINTED NAME)</div>

            <table class="signature-layout">
                <tr>
                    <td class="signature-column">
                        <div class="signature-block">
                            <div class="signature-signing-area">
                                <div class="signature-name">
                                    {{ $borrowerName !== '' ? $borrowerName : ' ' }}
                                </div>
                                <div class="signature-label">Borrower</div>
                            </div>
                        </div>
                    </td>
                    <td class="signature-column">
                        <div class="signature-block">
                            <div class="signature-signing-area">
                                <div class="signature-name">
                                    {{ $coMakerOneName !== '' ? $coMakerOneName : ' ' }}
                                </div>
                                <div class="signature-label">Co-borrower 1</div>
                            </div>
                        </div>
                    </td>
                    <td class="signature-column">
                        <div class="signature-block">
                            <div class="signature-signing-area">
                                <div class="signature-name">
                                    {{ $coMakerTwoName !== '' ? $coMakerTwoName : ' ' }}
                                </div>
                                <div class="signature-label">Co-borrower 2</div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>

            <table class="address-layout">
                <tr>
                    <td class="address-column">
                        <span class="address-label">Address:</span>
                        <span class="address-value">{{ $borrowerAddress }}</span>
                    </td>
                    <td class="address-column">
                        <span class="address-label">Address:</span>
                        <span class="address-value">{{ $coMakerOneAddress }}</span>
                    </td>
                    <td class="address-column">
                        <span class="address-label">Address:</span>
                        <span class="address-value">{{ $coMakerTwoAddress }}</span>
                    </td>
                </tr>
            </table>

            <div class="witness-section">
                <div class="witness-instruction">Sign In The Presence Of</div>
                <table class="witness-layout">
                    <tr>
                        <td class="witness-column">
                            <div class="witness-name">
                                {{ $witnessOneName !== '' ? $witnessOneName : ' ' }}
                            </div>
                            <div class="witness-label">Witness</div>
                        </td>
                        <td class="witness-column">
                            <div class="witness-name">
                                {{ $witnessTwoName !== '' ? $witnessTwoName : ' ' }}
                            </div>
                            <div class="witness-label">Witness</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </body>
</html>
