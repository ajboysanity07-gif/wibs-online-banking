@php
    $reportHeader = $reportHeader ?? [];
    $companyName = $reportHeader['companyName'] ?? ($companyName ?? '');
    $logoData = $reportHeader['logoData'] ?? ($logoData ?? null);
    $reportTitle = $reportTitle ?? $reportHeader['title'] ?? null;
    $reportTagline = $reportTagline ?? $reportHeader['tagline'] ?? null;
    $showLogo = $reportHeader['showLogo'] ?? true;
    $showCompanyName = $reportHeader['showCompanyName'] ?? true;
    $alignment = $reportHeader['alignment'] ?? 'center';
    $alignment = in_array($alignment, ['left', 'center', 'right'], true)
        ? $alignment
        : 'center';
    $showLogo = $showLogo && $logoData;
    $showCompanyName = $showCompanyName && $companyName;
    $resolvedTitle = trim((string) ($reportTitle ?? ''));
    $resolvedTagline = trim((string) ($reportTagline ?? ''));
    $showTitle = $resolvedTitle !== '';
    $showTagline = $resolvedTagline !== '';
    $titleText = $showTitle ? $resolvedTitle : 'APPLICATION FORM';
    $showHeader = $showLogo || $showCompanyName || $showTitle || $showTagline;
    $headerClass = $showCompanyName
        ? 'report-header'
        : 'report-header report-header--wordmark';
    $headerClass .= sprintf(' report-header--%s', $alignment);
@endphp

@if ($showHeader)
    <div class="{{ $headerClass }}">
        <div class="report-header-group">
            @if ($showLogo || $showCompanyName)
                <div class="report-brand">
                    @if ($showLogo)
                        <img src="{{ $logoData }}" alt="Company logo" class="report-logo" />
                    @endif
                    @if ($showCompanyName)
                        <div class="report-company-name">{{ $companyName }}</div>
                    @endif
                </div>
            @endif
            <div class="report-header-text">
                @if ($titleText !== '')
                    <div class="report-title">{{ $titleText }}</div>
                @endif
                @if ($showTagline)
                    <div class="report-tagline">{{ $reportTagline }}</div>
                @endif
            </div>
        </div>
    </div>
@endif
