@php
    $reportHeader = $reportHeader ?? [];
    $designData = $reportHeader['designData'] ?? null;
    $companyName = trim((string) ($reportHeader['companyName'] ?? ($companyName ?? '')));
    $fallbackTitle = $companyName !== '' ? $companyName : 'APPLICATION FORM';
@endphp

@if ($designData)
    <div class="report-header report-header--design">
        <img src="{{ $designData }}" alt="Report header design" class="report-header-design" />
    </div>
@else
    <div class="report-header report-header--fallback">
        <div class="report-title">{{ $fallbackTitle }}</div>
    </div>
@endif
