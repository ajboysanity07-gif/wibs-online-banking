@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\HtmlString;

    $companyName = trim((string) ($organization['company_name'] ?? ''));
    $headerDesign = $reportHeader['designData'] ?? null;
    $headerLogo = $organizationLogoDataUri ?? null;
    $borrowerName = trim((string) ($applicant['full_name'] ?? ''));
    $deductionAmount = trim((string) ($loan['amortization_total'] ?? ''));
    $deductionAmountWords = trim((string) ($loan['amortization_total_words'] ?? ''));
    $deductionStartDate = trim((string) ($loan['deduction_start_date'] ?? ''));
    $approvedDate = trim((string) ($loan['approved_date'] ?? ''));
    $placeOfSigning = trim((string) ($placeOfSigning ?? ''));
    $institutionName = trim((string) ($institution['name'] ?? ''));
    $officers = is_array($institution['officers'] ?? null) ? $institution['officers'] : [];

    $officerNames = array_values(array_filter(array_map(
        static fn (array $officer): string => trim((string) ($officer['name'] ?? '')),
        $officers,
    )));

    $representationClause = $officerNames !== []
        ? implode(' and ', $officerNames)
        : null;

    $signingDate = null;
    if ($approvedDate !== '') {
        try {
            $signingDate = Carbon::parse($approvedDate);
        } catch (Throwable) {
            $signingDate = null;
        }
    }

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
        <title>Authority to Deduct</title>
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
                color: #111111;
                font-family: "Calibri", Arial, sans-serif;
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
                margin: 0 0 14pt;
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

            .signature-layout {
                width: 100%;
                border-collapse: separate;
                margin: 28pt 0 0;
                page-break-inside: avoid;
            }

            .signature-signing-area {
                position: relative;
                min-height: 60pt;
            }

            .signature-name {
                min-height: 14pt;
                margin-bottom: 2pt;
                padding-top: 30pt;
                font-size: 11pt;
                font-weight: 700;
                text-align: center;
            }

            .signature-line {
                width: 60%;
                margin: 0 auto 3pt;
                border-bottom: 0.8pt solid #111111;
            }

            .signature-label {
                font-size: 10pt;
                font-weight: 400;
                letter-spacing: 0.04em;
                text-align: center;
                text-transform: uppercase;
            }

            .conforme-block {
                margin-top: 30pt;
            }

            .conforme-label {
                margin-bottom: 20pt;
                font-size: 11pt;
                font-weight: 700;
                text-transform: uppercase;
            }

            .conforme-signatory {
                margin: 0 0 16pt;
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
                            {{ $companyName !== '' ? $companyName : 'Authority to Deduct' }}
                        </div>
                    </div>
                @endif
            </div>

            <div class="document-title">Authority to Deduct</div>

            <p class="paragraph">
                THIS IS TO AUTHORIZE {!! $renderValue($institutionName, '14em', true) !!}, represented by
                {!! $renderValue($representationClause, '18em', true) !!}, to deduct from my salary the amount of
                Pesos: {!! $renderValue($deductionAmountWords, '16em') !!} ({!! $renderValue($deductionAmount, '8em') !!}),
                starting {!! $renderValue($deductionStartDate, '9em') !!} and every quincena/month thereafter until my
                loan obligation with MICRO FINANCE FOR RURAL DEVELOPMENT INC. shall have been paid in full.
            </p>

            <p class="paragraph">
                Said representative is further authorized, that in the event of my separation from the service, to
                deduct from my separation benefits, any and all amounts corresponding to my outstanding obligations
                with Micro Finance for Rural Development Inc.
            </p>

            <table class="signature-layout">
                <tr>
                    <td style="width: 60%;">
                        <div class="signature-signing-area">
                            <div class="signature-name">
                                {{ $borrowerName !== '' ? $borrowerName : ' ' }}
                            </div>
                            <div class="signature-line"></div>
                            <div class="signature-label">Signature over Printed Name of Borrower</div>
                        </div>
                    </td>
                    <td style="width: 40%; text-align: center;">
                        {!! $renderValue($signingDate?->format('F d, Y'), '12em') !!}
                        at {!! $renderValue($placeOfSigning, '12em') !!}
                    </td>
                </tr>
            </table>

            <div class="conforme-block">
                <div class="conforme-label">Conforme:</div>
                @if ($officers !== [])
                    @foreach ($officers as $officer)
                        <div class="conforme-signatory">
                            <div class="signature-name">{{ $officer['name'] ?? ' ' }}</div>
                            <div class="signature-line" style="width: 50%; margin: 0 auto 3pt;"></div>
                            <div class="signature-label">
                                Signature over Printed Name of {{ $officer['title'] ?? 'Authorized Representative' }}
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="conforme-signatory">
                        <div class="signature-name">{!! $renderValue(null, '16em') !!}</div>
                        <div class="signature-line" style="width: 50%; margin: 0 auto 3pt;"></div>
                        <div class="signature-label">Signature over Printed Name of Authorized Representative</div>
                    </div>
                @endif
            </div>
        </div>
    </body>
</html>
