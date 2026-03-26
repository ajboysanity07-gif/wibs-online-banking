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
        margin: 0;
    }
    body {
        font-family: var(--report-font-value-family);
        font-size: 9.5px;
        color: #111;
        margin: 12px;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .page {
        border: 1.5px solid #111;
        padding: 12px 12px 16px;
    }
    .report-header {
        margin-bottom: 20px;
    }
    .report-header--left {
        text-align: left;
    }
    .report-header--center {
        text-align: center;
    }
    .report-header--right {
        text-align: right;
    }
    .report-header-group {
        display: inline-block;
        text-align: inherit;
    }
    .report-brand {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        vertical-align: middle;
        margin-right: 12px;
    }
    .report-header-text {
        display: inline-block;
        vertical-align: middle;
        text-align: inherit;
    }
    .report-header--wordmark .report-brand {
        gap: 0;
    }
    .report-logo {
        height: 36px;
    }
    .report-header--wordmark .report-logo {
        height: 42px;
    }
    .report-company-name {
        font-family: var(--report-font-header-title-family);
        font-weight: var(--report-font-header-title-weight);
        font-style: var(--report-font-header-title-style);
        font-size: 12px;
        color: var(--report-font-header-color);
    }
    .report-title {
        text-align: inherit;
        font-family: var(--report-font-header-title-family);
        font-weight: var(--report-font-header-title-weight);
        font-style: var(--report-font-header-title-style);
        font-size: var(--report-font-header-title-size);
        color: var(--report-font-header-color);
        margin: 0;
        letter-spacing: 0.04em;
    }
    .report-tagline {
        text-align: inherit;
        font-family: var(--report-font-header-tagline-family);
        font-weight: var(--report-font-header-tagline-weight);
        font-style: var(--report-font-header-tagline-style);
        font-size: var(--report-font-header-tagline-size);
        color: var(--report-font-header-tagline-color);
        margin: 2px 0 0;
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
        margin-top: 60px;
        display: flex;
        justify-content: space-between;
        gap: 10px;
        font-size: 9px;
        text-align: center;
    }
    .signature-line {
        border-top: 1px solid #111;
        padding-top: 10px;
        width: 32%;
    }
</style>
