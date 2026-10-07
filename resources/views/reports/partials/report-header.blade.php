@php
    $reportHeader = $reportHeader ?? [];
    $designData = $reportHeader['designData'] ?? null;
    $companyName = trim((string) ($reportHeader['companyName'] ?? ($companyName ?? '')));
    $fallbackTitle = $companyName !== '' ? $companyName : 'APPLICATION FORM';
    $footer = trim((string) ($reportHeader['footer'] ?? ''));
@endphp

@if ($footer !== '')
    {{-- ponytail: fixed footer relies on dompdf repeating it per page; margins are not tuned per document --}}
    <div style="position: fixed; bottom: -8px; left: 0; right: 0; text-align: center; font-size: 9px; color: #555;">{{ $footer }}</div>
@endif

@if ($designData)
    <div class="report-header report-header--design">
        <img src="{{ $designData }}" alt="Report header design" class="report-header-design" />
    </div>
@else
    <div class="report-header report-header--fallback">
        <div class="report-title">{{ $fallbackTitle }}</div>
    </div>
@endif
