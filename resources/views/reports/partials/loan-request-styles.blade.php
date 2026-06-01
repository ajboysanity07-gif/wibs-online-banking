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
        font-size: 13px;
        width: 100%;
        margin: 10px 0 0;
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
        font-size: 9px;
        line-height: 1.35;
        margin-top: 12px;
    }
    .undertaking p {
        text-align: center;
        margin: 0 0 6px;
    }
    .signature-row {
        margin-top: 44px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        page-break-inside: avoid;
        break-inside: avoid;
        overflow: visible;
    }
    .signature-box {
        flex: 1 1 0;
        min-width: 0;
        text-align: center;
        overflow: visible;
    }
    .signature-signing-area {
        position: relative;
        min-height: 108px;
        overflow: visible;
    }
    .signature-art {
        position: absolute;
        right: -6px;
        left: -6px;
        bottom: 20px;
        height: 72px;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        z-index: 2;
        overflow: visible;
    }
    .signature-image {
        display: block;
        margin: 0 auto;
        width: auto;
        max-width: 126%;
        max-height: 72px;
        object-fit: contain;
    }
    .signature-line {
        position: relative;
        z-index: 1;
        width: 100%;
        margin: 0 0 4px;
        border-bottom: 1px solid #111;
    }
    .signature-name {
        position: relative;
        z-index: 1;
        min-height: 14px;
        margin-bottom: 2px;
        padding-top: 52px;
        font-size: 9px;
        font-weight: 600;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .signature-label {
        position: relative;
        z-index: 1;
        font-size: 9px;
        font-weight: 600;
        line-height: 1.2;
    }
</style>
