@php
    $googleFontUrl = $reportTypography['googleFontUrl'] ?? null;
@endphp
@if ($googleFontUrl)
    <link rel="stylesheet" href="{{ $googleFontUrl }}" />
@endif
<style>
    @include('reports.partials.report-typography')
    @page {
        size: 8.5in 13in;
        margin: 0.5in;
    }
    html,
    body {
        margin: 0;
        padding: 0;
    }
    body {
        font-family: var(--report-font-value-family);
        font-size: 9pt;
        line-height: 1.15;
        color: #111;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .page {
        border: 1.5px solid #111;
        width: 7.5in;
        min-height: 12in;
        padding: 8px 10px 10px;
        box-sizing: border-box;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
    }
    .report-header {
        margin-bottom: 10px;
    }
    .report-header--design {
        margin-bottom: 10px;
        text-align: center;
    }
    .report-header-design {
        display: block;
        width: 100%;
        max-height: 75px;
        object-fit: contain;
    }
    .report-header--fallback {
        text-align: center;
    }
    .report-title {
        font-family: var(--report-font-value-family);
        font-weight: 700;
        font-style: normal;
        font-size: 10pt;
        color: #111;
        margin: 0;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .section-title {
        display: block;
        width: 100%;
        margin: 5px 0 0;
        border-bottom: 1px solid #111;
        background: #111;
        color: #fff;
        font-weight: 700;
        padding: 2px 5px;
        font-size: 9pt;
        text-transform: uppercase;
        box-sizing: border-box;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .section-title--undertaking {
        background: none;
        color: #111;
        border-bottom: none;
        text-align: center;
        text-decoration: underline;
        font-size: 9pt;
        width: 100%;
        margin: 2px 0 0;
        padding: 0;
        letter-spacing: 0.02em;
    }
    .info-table,
    .section-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin: 0;
    }
    .info-table td,
    .section-table td {
        padding: 1px 2px;
        vertical-align: bottom;
    }
    .label {
        font-family: var(--report-font-label-family);
        font-weight: var(--report-font-label-weight);
        font-style: var(--report-font-label-style);
        font-size: 7.5pt;
        text-transform: uppercase;
        color: var(--report-font-label-color);
        white-space: normal;
        padding-left: 0;
        padding-right: 6px;
        line-height: 1;
    }
    .row-line td {
        border-bottom: 1px solid #111;
        padding-top: 1px;
        padding-bottom: 1px;
    }
    .field {
        border-bottom: 1px solid #111;
        font-family: var(--report-font-value-family);
        font-weight: var(--report-font-value-weight);
        font-style: var(--report-font-value-style);
        font-size: 8.5pt;
        color: var(--report-font-value-color);
        min-height: 11px;
        padding-left: 1px;
        line-height: 1.08;
    }
    .field--tight {
        font-size: 8.2pt;
    }
    .field--tightest {
        font-size: 7.8pt;
    }
    .row-line .field {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .checkbox {
        display: inline-block;
        width: 9px;
        height: 9px;
        border: 1px solid #111;
        text-align: center;
        line-height: 9px;
        font-size: 8pt;
        margin: 0 3px 0 5px;
    }
    .undertaking {
        font-size: 8pt;
        line-height: 1.2;
        margin-top: 5px;
        padding: 0 4px;
    }
    .undertaking p {
        text-align: justify;
        margin: 0 0 1px;
        text-indent: 10px;
    }
    .undertaking p:last-child {
        margin-bottom: 0;
    }
    .section-group--undertaking {
        margin-bottom: 0;
        margin-top: 10px;
    }
    .section-group--signature {
        margin-top: 8px;
        page-break-inside: avoid;
        break-inside: avoid;
    }
    .signature-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 3px 0;
        table-layout: fixed;
        page-break-inside: avoid;
        break-inside: avoid;
    }
    .signature-table tr {
        page-break-inside: avoid;
        break-inside: avoid;
    }
    .signature-cell {
        width: 25%;
        padding: 0;
        vertical-align: top;
        page-break-inside: avoid;
        break-inside: avoid;
    }
    .signature-signing-space {
        height: 10px;
        margin: 0;
    }
    .signature-line {
        height: 0;
        margin: 0;
        margin-top: 2px;
        border-bottom: 1px solid #111;
    }
    .signature-name {
        margin: 0;
        min-height: 0;
        font-size: 8.5pt;
        font-weight: 700;
        letter-spacing: 0.02em;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        word-break: normal;
        overflow-wrap: normal;
        text-transform: uppercase;
    }
    .signature-name--tight {
        font-size: 8.4pt;
        letter-spacing: 0;
    }
    .signature-name--tighter {
        font-size: 7.4pt;
        letter-spacing: -0.02em;
    }
    .signature-name--tightest {
        font-size: 6.6pt;
        letter-spacing: -0.04em;
    }
    .signature-label {
        min-height: 9px;
        margin-top: 0;
        font-size: 8.5pt;
        font-weight: 600;
        line-height: 1;
        text-align: center;
    }
    .section-group--signature {
        margin-top: 80px;
    }
    @media print {
        html,
        body {
            margin: 0;
            padding: 0;
            background: #fff;
        }

        .page {
            margin: 0 auto;
        }
    }
</style>
