@php
    $reportTypography = $reportTypography ?? [];
    $labelFont = $reportTypography['label'] ?? [];
    $valueFont = $reportTypography['value'] ?? [];
    $labelFamily = $labelFont['cssFamily'] ?? '"DejaVu Sans", sans-serif';
    $valueFamily = $valueFont['cssFamily'] ?? '"DejaVu Sans", sans-serif';
    $labelWeight = $labelFont['weight'] ?? 400;
    $valueWeight = $valueFont['weight'] ?? 600;
    $labelStyle = $labelFont['cssStyle'] ?? 'normal';
    $valueStyle = $valueFont['cssStyle'] ?? 'normal';
    $labelSize = $labelFont['size'] ?? 8;
    $valueSize = $valueFont['size'] ?? 10;
    $labelColor = $labelFont['color'] ?? null;
    $valueColor = $valueFont['color'] ?? null;
    $resolvedLabelColor = $labelColor ?? '#333333';
    $resolvedValueColor = $valueColor ?? '#111111';
    $valueSizeTight = max($valueSize - 1, 6);
    $valueSizeTightest = max($valueSize - 1.5, 6);
@endphp
:root {
    --report-font-label-family: {!! $labelFamily !!};
    --report-font-label-weight: {{ $labelWeight }};
    --report-font-label-style: {{ $labelStyle }};
    --report-font-label-size: {{ $labelSize }}px;
    --report-font-label-color: {{ $resolvedLabelColor }};
    --report-font-value-family: {!! $valueFamily !!};
    --report-font-value-weight: {{ $valueWeight }};
    --report-font-value-style: {{ $valueStyle }};
    --report-font-value-size: {{ $valueSize }}px;
    --report-font-value-color: {{ $resolvedValueColor }};
    --report-font-value-size-tight: {{ $valueSizeTight }}px;
    --report-font-value-size-tightest: {{ $valueSizeTightest }}px;
}
