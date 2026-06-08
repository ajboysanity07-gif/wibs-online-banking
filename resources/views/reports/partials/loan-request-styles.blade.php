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
    body {
        font-family: var(--report-font-value-family);
        font-size: 9.5px;
        color: #111;
        margin: 0;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .page {
        border: 1.5px solid #111;
        padding: 12px 12px 16px;
        box-sizing: border-box;
    }
    .report-header {
        margin-bottom: 16px;
    }
    .report-header--design {
        margin-bottom: 16px;
        text-align: center;
    }
    .report-header-design {
        display: block;
        width: 100%;
        max-height: 95px;
        object-fit: contain;
    }
    .report-header--fallback {
        text-align: center;
    }
    .report-title {
        font-family: var(--report-font-value-family);
        font-weight: 700;
        font-style: normal;
        font-size: 12px;
        color: #111;
        margin: 0;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .section-title {
        display: block;
        width: 100%;
        margin: 10px 0 0;
        border-bottom: 1px solid #111;
        background: #111;
        color: #fff;
        font-weight: 700;
        padding: 3px 6px;
        font-size: 10px;
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
        font-size: 11px;
        width: 100%;
        margin: 4px 0 0;
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
        padding: 2px 3px;
        vertical-align: bottom;
    }
    .label {
        font-family: var(--report-font-label-family);
        font-weight: var(--report-font-label-weight);
        font-style: var(--report-font-label-style);
        font-size: var(--report-font-label-size);
        text-transform: uppercase;
        color: var(--report-font-label-color);
        white-space: normal;
        padding-left: 0;
        padding-right: 8px;
        line-height: 1.1;
    }
    .row-line td {
        border-bottom: 1px solid #111;
        padding-top: 2px;
        padding-bottom: 2px;
    }
    .field {
        border-bottom: 1px solid #111;
        font-family: var(--report-font-value-family);
        font-weight: var(--report-font-value-weight);
        font-style: var(--report-font-value-style);
        font-size: var(--report-font-value-size);
        color: var(--report-font-value-color);
        min-height: 12px;
        padding-left: 1px;
    }
    .field--tight {
        font-size: var(--report-font-value-size-tight);
    }
    .field--tightest {
        font-size: var(--report-font-value-size-tightest);
    }
    .row-line .field {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .checkbox {
        display: inline-block;
        width: 10px;
        height: 10px;
        border: 1px solid #111;
        text-align: center;
        line-height: 10px;
        font-size: 9px;
        margin: 0 4px 0 6px;
    }
    .undertaking {
        font-size: 9.7px;
        line-height: 1.55;
        margin-top: 6px;
        padding: 0 8px 1px;
    }
    .undertaking p {
        text-align: justify;
        margin: 0 0 6px;
        text-indent: 18px;
    }
    .undertaking p:last-child {
        margin-bottom: 0;
    }
    .section-group--undertaking {
        page-break-inside: avoid;
        break-inside: avoid;
    }
    .section-group--signature {
        margin-top: 6px;
        page-break-inside: avoid;
        break-inside: avoid;
    }
    .signature-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 10px 0;
        table-layout: fixed;
    }
    .signature-cell {
        width: 25%;
        padding: 0;
        vertical-align: top;
    }
    .signature-signing-space {
        height: 28px;
        margin: 0;
    }
    .signature-line {
        height: 0;
        margin: 0;
        border-bottom: 1px solid #111;
    }
    .signature-name {
        margin: 0;
        min-height: 0;
        font-size: 8.3px;
        font-weight: 700;
        letter-spacing: 0.04em;
        line-height: 1;
        text-align: center;
        word-break: break-word;
    }
    .signature-label {
        min-height: 10px;
        margin-top: 1px;
        font-size: 8.3px;
        font-weight: 600;
        line-height: 1.1;
        text-align: center;
    }
</style>
