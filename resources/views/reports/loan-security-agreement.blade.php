@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\HtmlString;

    $companyName = trim((string) ($organization['company_name'] ?? ''));
    $headerDesign = $reportHeader['designData'] ?? null;
    $headerLogo = $organizationLogoDataUri ?? null;
    $borrowerName = trim((string) ($applicant['full_name'] ?? ''));
    $borrowerAddress = trim((string) ($applicant['address'] ?? ''));
    $borrowerSignatureData = $applicant['signature_data'] ?? null;
    $loanType = trim((string) ($loan['type'] ?? ''));
    $approvedDate = trim((string) ($loan['approved_date'] ?? ''));
    $reviewerName = trim((string) ($reviewer['name'] ?? ''));
    $reviewerTitle = trim((string) ($reviewer['position'] ?? ''));
    $reviewerSignatureData = $reviewer['signature_data'] ?? null;
    $lenderSignatureData = $reviewerSignatureData;
    $lenderSignatureName = $reviewerName !== '' ? $reviewerName : $companyName;
    $lenderRepresentationClause = $reviewerName !== ''
        ? trim($reviewerName.($reviewerTitle !== '' ? ', '.$reviewerTitle : ''))
        : 'its duly authorized representative';
    $placeOfSigning = trim((string) ($placeOfSigning ?? ''));

    $signingDate = null;
    if ($approvedDate !== '') {
        try {
            $signingDate = Carbon::parse($approvedDate);
        } catch (Throwable) {
            $signingDate = null;
        }
    }

    $signingDay = $signingDate?->format('j');
    $signingMonth = $signingDate?->format('F');
    $signingYear = $signingDate?->format('Y');

    $renderValue = static function (
        ?string $value,
        string $blankWidth = '7em',
        bool $emphasized = false,
    ): HtmlString {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return new HtmlString(sprintf(
                '<span class="agreement-blank" style="min-width:%s;">&nbsp;</span>',
                e($blankWidth),
            ));
        }

        if (! $emphasized) {
            return new HtmlString(e($trimmed));
        }

        return new HtmlString(sprintf(
            '<span class="agreement-fill">%s</span>',
            e($trimmed),
        ));
    };
@endphp
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Loan Security Agreement</title>
        <style>
            @page {
                size: 8.5in 11in;
                margin: .75in 1in 1in 1in;
            }

            body {
                margin: 0;
                color: #111111;
                font-family: "Times New Roman", Times, serif;
                font-size: 11pt;
                line-height: 1.38;
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
                margin: 0 0 10pt;
                font-size: 14pt;
                font-weight: 700;
                letter-spacing: 0.04em;
                text-align: center;
                text-transform: uppercase;
            }

            .paragraph {
                margin: 0 0 10pt;
                text-align: justify;
                text-indent: 34pt;
            }

            .paragraph--closing {
                margin-top: 2pt;
                margin-bottom: 2pt;
                text-indent: 34pt;
            }

            .agreement-fill {
                font-weight: 700;
                text-decoration: underline;
                text-decoration-thickness: 0.8pt;
                text-underline-offset: 2pt;
            }

            .agreement-blank {
                display: inline-block;
                line-height: 0.95;
                vertical-align: baseline;
                border-bottom: 0.8pt solid #111111;
            }

            .agreement-clauses {
                margin: 0 0 12pt 0;
                padding: 0 0 0 26pt;
                list-style-position: outside;
            }

            .agreement-clauses li {
                margin: 0 0 8pt 0;
                padding-left: 4pt;
                text-align: justify;
            }

            .signature-layout {
                width: 76%;
                border-collapse: separate;
                table-layout: fixed;
                margin: 20pt auto 0;
                page-break-inside: avoid;
            }

            .signature-column {
                width: 50%;
                vertical-align: top;
            }

            .signature-column--left {
                padding-right: 12pt;
            }

            .signature-column--right {
                padding-left: 12pt;
            }

            .signature-block {
                width: 100%;
            }

            .signature-signing-area {
                position: relative;
                min-height: 72pt;
            }

            .signature-art {
                position: absolute;
                right: 0;
                left: 0;
                display: flex;
                align-items: flex-end;
                justify-content: center;
                z-index: 2;
            }

            .signature-art--borrower {
                bottom: 18pt;
                height: 48pt;
            }

            .signature-art--lender {
                bottom: 18pt;
                height: 46pt;
            }

            .signature-image {
                display: block;
                width: auto;
                object-fit: contain;
            }

            .signature-image--borrower {
                max-width: 114%;
                max-height: 48pt;
            }

            .signature-image--lender {
                max-width: 112%;
                max-height: 46pt;
            }

            .signature-name,
            .signature-line,
            .signature-label {
                position: relative;
            }

            .signature-name {
                z-index: 1;
                min-height: 14pt;
                margin-bottom: 2pt;
                padding-top: 34pt;
                font-size: 11pt;
                font-weight: 700;
                text-align: center;
            }

            .signature-line {
                z-index: 1;
                width: 100%;
                margin: 0 0 3pt;
                border-bottom: 0.8pt solid #111111;
            }

            .signature-label {
                z-index: 1;
                font-size: 10pt;
                font-weight: 400;
                letter-spacing: 0.04em;
                text-align: center;
                text-transform: uppercase;
            }

            .signature-block--lender .signature-name {
                padding-top: 33pt;
            }

            .signature-block--lender .signature-label {
                margin-top: 1pt;
            }

            .signature-block--borrower .signature-label {
                margin-top: 1pt;
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
                            {{ $companyName !== '' ? $companyName : 'Loan Security Agreement' }}
                        </div>
                    </div>
                @endif
            </div>

            <div class="document-title">Loan Security Agreement</div>

            <p class="paragraph">
                We, the {!! $renderValue($companyName, '11em') !!}, a corporation duly registered with the Securities and
                Exchange Commission, represented by {!! $renderValue($lenderRepresentationClause, '14em') !!}, hereinafter
                called the "Lender", and {!! $renderValue($borrowerName, '12em', true) !!}, Filipino, of legal age, and a resident
                of {!! $renderValue($borrowerAddress, '18em', true) !!}, hereinafter called the
                "Borrower", hereby agree to the following terms and conditions of the Loan Security:
            </p>

            <ol class="agreement-clauses">
                <li>
                    That, as allowed under Sec. 6(d) of R.A. 10693 dated November 3, 2015, the amount deducted as
                    Loan Security that is collected up front from the principal loan amount, including the amounts
                    collected as such upon payment of the loan amortizations, shall be considered as Compensating
                    Deposit for the {!! $renderValue($loanType, '8em', true) !!} obtained by the Borrower from the Lender;
                </li>
                <li>
                    That subject compensating deposit shall exist for as long as the Borrower has an outstanding
                    loan from the Lender;
                </li>
                <li>
                    That in the event of full payment of the loan, the Compensating Deposit may be paid back by the
                    Lender to the Borrower subject to re-instatement in case of loan renewal, to be deducted upon
                    release of the principal amount of the renewed loan, it being that subject compensating deposit
                    is considered as the Borrower's equity to the loan availed from the Lender;
                </li>
                <li>
                    That if for any reason, the Borrower will not agree to re-instatement of the paid Compensating
                    Deposit, re-availment of the loan by the Borrower may be allowed in an amount equivalent to the
                    Borrower's first cycle loan amount and subject to the usual finance and non-finance charges on
                    the loan;
                </li>
                <li>
                    That in the case of default by the Borrower in the payment of the loan upon its maturity, the
                    compensating deposit shall be automatically applied as repayment thereto without need of demand
                    nor another authority from the Borrower, and without prejudice to the filing of appropriate
                    legal action by the Lender to enforce collection of any and all amounts remaining after the
                    compensating deposit shall have been applied as repayment to the loan.
                </li>
            </ol>

            <p class="paragraph paragraph--closing">
                In witness thereof, we have signed this Agreement this {!! $renderValue($signingDay, '2.8em') !!} day of
                {!! $renderValue($signingMonth, '8em') !!}, {!! $renderValue($signingYear, '4.5em') !!} at
                {!! $renderValue($placeOfSigning, '12em') !!}.
            </p>

            <table class="signature-layout">
                <tr>
                    <td class="signature-column signature-column--left">
                        <div class="signature-block signature-block--borrower">
                            <div class="signature-signing-area signature-signing-area--borrower">
                                <div class="signature-art signature-art--borrower">
                                    @if ($borrowerSignatureData)
                                        <img
                                            src="{{ $borrowerSignatureData }}"
                                            alt="Borrower signature"
                                            class="signature-image signature-image--borrower"
                                        />
                                    @endif
                                </div>
                                <div class="signature-name">
                                    {{ $borrowerName !== '' ? $borrowerName : ' ' }}
                                </div>
                                <div class="signature-line"></div>
                                <div class="signature-label">Borrower</div>
                            </div>
                        </div>
                    </td>
                    <td class="signature-column signature-column--right">
                        <div class="signature-block signature-block--lender">
                            <div class="signature-signing-area signature-signing-area--lender">
                                <div class="signature-art signature-art--lender">
                                    @if ($lenderSignatureData)
                                        <img
                                            src="{{ $lenderSignatureData }}"
                                            alt="Lender signature"
                                            class="signature-image signature-image--lender"
                                        />
                                    @endif
                                </div>
                                <div class="signature-name">
                                    {{ $lenderSignatureName !== '' ? $lenderSignatureName : ' ' }}
                                </div>
                                <div class="signature-line"></div>
                                <div class="signature-label">Lender</div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </body>
</html>
