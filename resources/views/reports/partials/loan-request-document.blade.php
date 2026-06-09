@php
    $normalizeValue = static function (mixed $value): string {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    };
    $formatDate = fn ($value) => $value
        ? \Illuminate\Support\Carbon::parse($value)->format('m/d/Y')
        : '';
    $displayRawValue = static function (mixed $value, string $fallback = '') use ($normalizeValue): string {
        $text = $normalizeValue($value);

        return $text !== '' ? $text : $fallback;
    };
    $displayText = static function (mixed $value, string $fallback = '') use ($normalizeValue): string {
        $text = $normalizeValue($value);

        if ($text === '') {
            return $fallback;
        }

        return \App\Support\DisplayText::normalize($text) ?? $fallback;
    };
    $displayProperText = static function (mixed $value, string $fallback = '') use ($displayText, $normalizeValue): string {
        $text = $normalizeValue($value);

        if ($text === '') {
            return $fallback;
        }

        $normalized = $displayText($text, $fallback);

        if ($normalized === '') {
            return $fallback;
        }

        return \Illuminate\Support\Str::of($normalized)->squish()->title()->value();
    };
    $extractPersonName = static function (array $person) use ($normalizeValue): string {
        $fullName = trim(implode(' ', array_filter([
            $person['first_name'] ?? null,
            $person['middle_name'] ?? null,
            $person['last_name'] ?? null,
        ], static fn (mixed $part): bool => $part !== null && trim((string) $part) !== '')));

        if ($fullName !== '') {
            return $fullName;
        }

        foreach (['name', 'full_name'] as $key) {
            $name = $normalizeValue($person[$key] ?? null);

            if ($name !== '') {
                return $name;
            }
        }

        return '';
    };
    $formatPrintedSignatureName = static function (mixed $value, string $fallback = 'N/A') use ($displayProperText, $normalizeValue): string {
        $text = $normalizeValue($value);

        if ($text === '') {
            return $fallback;
        }

        $normalized = $displayProperText($text);

        if ($normalized === '') {
            return $fallback;
        }

        return \Illuminate\Support\Str::of($normalized)->upper()->value();
    };
    $formatCurrency = static function (mixed $value, string $fallback = '') use ($displayRawValue): string {
        $text = $displayRawValue($value);

        if ($text === '') {
            return $fallback;
        }

        return '₱'.number_format((float) $text, 2);
    };
    $formatMonths = static function (mixed $value, string $fallback = '') use ($displayRawValue, $displayText): string {
        $text = $displayRawValue($value);

        if ($text === '') {
            return $fallback;
        }

        if (preg_match('/^-?\d+$/', $text) === 1) {
            $months = (int) $text;

            return sprintf('%d %s', $months, $months === 1 ? 'month' : 'months');
        }

        return $displayText($text, $fallback);
    };
    $formatYears = static function (mixed $value, string $fallback = '') use ($displayRawValue): string {
        $text = $displayRawValue($value);

        if ($text === '') {
            return $fallback;
        }

        if (preg_match('/^\d+/', $text, $matches) !== 1) {
            return $fallback;
        }

        $years = (int) $matches[0];

        return sprintf('%d %s', $years, $years === 1 ? 'year' : 'years');
    };
    $status = $loanRequest->status instanceof \App\LoanRequestStatus
        ? $loanRequest->status->value
        : (string) $loanRequest->status;
    $check = fn (bool $value) => $value ? '&#10003;' : '';
    $fitFieldClass = function ($value): string {
        $text = trim(strip_tags((string) $value));
        $length = mb_strlen($text);
        if ($length <= 18) {
            return 'field';
        }
        if ($length <= 28) {
            return 'field field--tight';
        }
        return 'field field--tightest';
    };
    $reportHeader = $reportHeader ?? [];
    $approvedTermLabel = $formatMonths($loanRequest->approved_term);
    $reviewerName = $reviewer['name'] ?? '';
    $loanManagerName = $displayProperText($reviewerName);
    $loanManagerSignatureName = $formatPrintedSignatureName($reviewerName);
    $signatureBlocks = [
        [
            'name' => $formatPrintedSignatureName($extractPersonName($applicant)),
            'label' => 'Member / Applicant',
        ],
        [
            'name' => $formatPrintedSignatureName($extractPersonName($coMakerOne)),
            'label' => 'Co-maker 1',
        ],
        [
            'name' => $formatPrintedSignatureName($extractPersonName($coMakerTwo)),
            'label' => 'Co-maker 2',
        ],
        [
            'name' => $loanManagerSignatureName,
            'label' => 'Loan Manager / Approved By',
        ],
    ];
@endphp

<div class="page">
    @include('reports.partials.report-header', ['reportHeader' => $reportHeader])

    <div class="section-group">
        <table class="info-table">
        <colgroup>
            <col style="width: 14%" />
            <col style="width: 46%" />
            <col style="width: 14%" />
            <col style="width: 26%" />
        </colgroup>
        <tr class="row-line">
            <td class="label">Application Status:</td>

            <td class="field">Approved <span class="checkbox">{!! $check($status === 'approved') !!}</span>
                    Declined <span class="checkbox">{!! $check($status === 'declined') !!}</span></td>
            <td class="label">Date:</td>

            <td class="{{ $fitFieldClass($formatDate($loanRequest->submitted_at)) }}">{{ $formatDate($loanRequest->submitted_at) }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Amount Approved:</td>

            <td class="{{ $fitFieldClass($formatCurrency($loanRequest->approved_amount)) }}">{{ $formatCurrency($loanRequest->approved_amount) }}</td>
            <td class="label">Approved Loan Term/Duration:</td>

            <td class="{{ $fitFieldClass($approvedTermLabel) }}">{{ $approvedTermLabel }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Loan Type:</td>

            <td class="{{ $fitFieldClass($loanRequest->loan_type_label_snapshot) }}">{{ $displayText($loanRequest->loan_type_label_snapshot) }}</td>
            <td class="label">Loan Purpose:</td>

            <td class="{{ $fitFieldClass($loanRequest->loan_purpose) }}">{{ $displayText($loanRequest->loan_purpose) }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Availment Status:</td>

            <td class="field" colspan="3">New <span class="checkbox">{!! $check($loanRequest->availment_status === 'New') !!}</span>
                    Re-Loan <span class="checkbox">{!! $check($loanRequest->availment_status === 'Re-Loan') !!}</span>
                    Re-Structured <span class="checkbox">{!! $check($loanRequest->availment_status === 'Restructured') !!}</span></td>
        </tr>
        <tr class="row-line">
            <td class="label">Recommended By:</td>

            <td class="field"></td>
            <td class="label">Approved By:</td>

            <td class="{{ $fitFieldClass($loanManagerName) }}">{{ $loanManagerName }}</td>
        </tr>
        </table>
    </div>

    <div class="section-group">
        <div class="section-title">I. My Personal Data</div>
        <table class="section-table">
        <colgroup>
            <col style="width: 14%" />
            <col style="width: 18%" />
            <col style="width: 14%" />
            <col style="width: 14%" />
            <col style="width: 14%" />
            <col style="width: 26%" />
        </colgroup>
        <tr>
            <td class="label">First Name:</td>
            <td class="{{ $fitFieldClass($displayProperText($applicant['first_name'] ?? '')) }}">{{ $displayProperText($applicant['first_name'] ?? '') }}</td>
            <td class="label">Last Name:</td>
            <td class="{{ $fitFieldClass($displayProperText($applicant['last_name'] ?? '')) }}">{{ $displayProperText($applicant['last_name'] ?? '') }}</td>
            <td class="label">Middle Name:</td>
            <td class="{{ $fitFieldClass($displayProperText($applicant['middle_name'] ?? '')) }}">{{ $displayProperText($applicant['middle_name'] ?? '') }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Nickname:</td>

            <td class="{{ $fitFieldClass($displayProperText($applicant['nickname'] ?? '')) }}">{{ $displayProperText($applicant['nickname'] ?? '') }}</td>
            <td class="label">Birthdate:</td>

            <td class="{{ $fitFieldClass($formatDate($applicant['birthdate'] ?? null)) }}">{{ $formatDate($applicant['birthdate'] ?? null) }}</td>
            <td class="label">Birth Place:</td>

            <td class="{{ $fitFieldClass($displayProperText($applicant['birthplace'] ?? '')) }}">{{ $displayProperText($applicant['birthplace'] ?? '') }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Length of Stay:</td>

            <td class="{{ $fitFieldClass($formatMonths($applicant['length_of_stay'] ?? null)) }}">{{ $formatMonths($applicant['length_of_stay'] ?? null) }}</td>
            <td class="label">Housing Status:</td>

            <td class="{{ $fitFieldClass($displayRawValue($applicant['housing_status'] ?? null)) }}">{{ $displayRawValue($applicant['housing_status'] ?? null) }}</td>
            <td class="label">Cell No.:</td>

            <td class="{{ $fitFieldClass($displayRawValue($applicant['cell_no'] ?? null)) }}">{{ $displayRawValue($applicant['cell_no'] ?? null) }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Civil Status:</td>

            <td class="{{ $fitFieldClass($displayRawValue($applicant['civil_status'] ?? null)) }}">{{ $displayRawValue($applicant['civil_status'] ?? null) }}</td>
            <td class="label">Educational Attainment:</td>

            <td class="{{ $fitFieldClass($displayRawValue($applicant['educational_attainment'] ?? null)) }}">{{ $displayRawValue($applicant['educational_attainment'] ?? null) }}</td>
            <td class="label">No. of Children:</td>

            <td class="{{ $fitFieldClass($displayRawValue($applicant['number_of_children'] ?? null)) }}">{{ $displayRawValue($applicant['number_of_children'] ?? null) }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Spouse Name:</td>

            <td class="{{ $fitFieldClass($displayProperText($applicant['spouse_name'] ?? '')) }}">{{ $displayProperText($applicant['spouse_name'] ?? '') }}</td>
            <td class="label">Spouse Age:</td>

            <td class="{{ $fitFieldClass($displayRawValue($applicant['spouse_age'] ?? null)) }}">{{ $displayRawValue($applicant['spouse_age'] ?? null) }}</td>
            <td class="label">Spouse Cell No.:</td>

            <td class="{{ $fitFieldClass($displayRawValue($applicant['spouse_cell_no'] ?? null)) }}">{{ $displayRawValue($applicant['spouse_cell_no'] ?? null) }}</td>
        </tr>
        </table>
        <table class="section-table">
        <colgroup>
            <col style="width: 14%" />
            <col style="width: 86%" />
        </colgroup>
        <tr class="row-line">
            <td class="label">Address:</td>

            <td class="{{ $fitFieldClass($displayProperText($applicant['address'] ?? '')) }}">{{ $displayProperText($applicant['address'] ?? '') }}</td>
        </tr>
        </table>
    </div>

    <div class="section-group">
        <div class="section-title">II. My Work & Finances</div>
        <table class="section-table">
            <colgroup>
                <col style="width: 14%" />
                <col style="width: 18%" />
                <col style="width: 14%" />
                <col style="width: 54%" />
            </colgroup>
        <tr class="row-line">
            <td class="label">Employment:</td>

            <td class="{{ $fitFieldClass($displayRawValue($applicant['employment_type'] ?? null)) }}">{{ $displayRawValue($applicant['employment_type'] ?? null) }}</td>
            <td class="label">Employer/Business Name:</td>

            <td class="{{ $fitFieldClass($displayProperText($applicant['employer_business_name'] ?? '')) }}">{{ $displayProperText($applicant['employer_business_name'] ?? '') }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Tel. No.:</td>

            <td class="{{ $fitFieldClass($displayRawValue($applicant['telephone_no'] ?? null)) }}">{{ $displayRawValue($applicant['telephone_no'] ?? null) }}</td>
            <td class="label">Current Position:</td>

            <td class="{{ $fitFieldClass($displayProperText($applicant['current_position'] ?? '')) }}">{{ $displayProperText($applicant['current_position'] ?? '') }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Nature of Business:</td>

            <td class="{{ $fitFieldClass($applicant['nature_of_business'] ?? '') }}">{{ $displayText($applicant['nature_of_business'] ?? '') }}</td>
            <td class="label">TOTAL YEARS:</td>

            <td class="{{ $fitFieldClass($formatYears($applicant['years_in_work_business'] ?? null)) }}">{{ $formatYears($applicant['years_in_work_business'] ?? null) }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Gross Monthly Income:</td>

            <td class="{{ $fitFieldClass($formatCurrency($applicant['gross_monthly_income'] ?? null)) }}">{{ $formatCurrency($applicant['gross_monthly_income'] ?? null) }}</td>
            <td class="label">Payday:</td>

            <td class="{{ $fitFieldClass($displayRawValue($applicant['payday'] ?? null)) }}">{{ $displayRawValue($applicant['payday'] ?? null) }}</td>
        </tr>
        </table>
        <table class="section-table">
            <colgroup>
                <col style="width: 14%" />
                <col style="width: 86%" />
            </colgroup>
            <tr class="row-line">
                <td class="label">Business Address:</td>

                <td class="{{ $fitFieldClass($displayProperText($applicant['employer_business_address'] ?? '')) }}">{{ $displayProperText($applicant['employer_business_address'] ?? '') }}</td>
            </tr>
        </table>
    </div>

    <div class="section-group">
        <div class="section-title">III. My Co Maker 1</div>
        <table class="section-table">
        <colgroup>
            <col style="width: 14%" />
            <col style="width: 18%" />
            <col style="width: 14%" />
            <col style="width: 14%" />
            <col style="width: 14%" />
            <col style="width: 26%" />
        </colgroup>
        <tr>
            <td class="label">First Name:</td>
            <td class="{{ $fitFieldClass($displayProperText($coMakerOne['first_name'] ?? '')) }}">{{ $displayProperText($coMakerOne['first_name'] ?? '') }}</td>
            <td class="label">Last Name:</td>
            <td class="{{ $fitFieldClass($displayProperText($coMakerOne['last_name'] ?? '')) }}">{{ $displayProperText($coMakerOne['last_name'] ?? '') }}</td>
            <td class="label">Middle Name:</td>
            <td class="{{ $fitFieldClass($displayProperText($coMakerOne['middle_name'] ?? '')) }}">{{ $displayProperText($coMakerOne['middle_name'] ?? '') }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Nickname:</td>

            <td class="{{ $fitFieldClass($displayProperText($coMakerOne['nickname'] ?? '')) }}">{{ $displayProperText($coMakerOne['nickname'] ?? '') }}</td>
            <td class="label">Birthdate:</td>

            <td class="{{ $fitFieldClass($formatDate($coMakerOne['birthdate'] ?? null)) }}">{{ $formatDate($coMakerOne['birthdate'] ?? null) }}</td>
            <td class="label">Birth Place:</td>

            <td class="{{ $fitFieldClass($displayProperText($coMakerOne['birthplace'] ?? '')) }}">{{ $displayProperText($coMakerOne['birthplace'] ?? '') }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Length of Stay:</td>

            <td class="{{ $fitFieldClass($formatMonths($coMakerOne['length_of_stay'] ?? null)) }}">{{ $formatMonths($coMakerOne['length_of_stay'] ?? null) }}</td>
            <td class="label">Cell No.:</td>

            <td class="{{ $fitFieldClass($displayRawValue($coMakerOne['cell_no'] ?? null)) }}">{{ $displayRawValue($coMakerOne['cell_no'] ?? null) }}</td>
            <td class="label">Educational Attainment:</td>

            <td class="{{ $fitFieldClass($displayRawValue($coMakerOne['educational_attainment'] ?? null)) }}">{{ $displayRawValue($coMakerOne['educational_attainment'] ?? null) }}</td>
        </tr>
        </table>
        <table class="section-table">
        <colgroup>
            <col style="width: 14%" />
            <col style="width: 86%" />
        </colgroup>
            <tr class="row-line">
                <td class="label">Address:</td>

                <td class="{{ $fitFieldClass($displayProperText($coMakerOne['address'] ?? '')) }}">{{ $displayProperText($coMakerOne['address'] ?? '') }}</td>
            </tr>
        </table>
        <table class="section-table">
            <colgroup>
                <col style="width: 14%" />
                <col style="width: 18%" />
                <col style="width: 14%" />
                <col style="width: 54%" />
            </colgroup>
            <tr class="row-line">
                <td class="label">Employment:</td>

                <td class="{{ $fitFieldClass($displayRawValue($coMakerOne['employment_type'] ?? null)) }}">{{ $displayRawValue($coMakerOne['employment_type'] ?? null) }}</td>
                <td class="label">Employer/Business Name:</td>

                <td class="{{ $fitFieldClass($displayProperText($coMakerOne['employer_business_name'] ?? '')) }}">{{ $displayProperText($coMakerOne['employer_business_name'] ?? '') }}</td>
            </tr>
            <tr class="row-line">
                <td class="label">Tel. No.:</td>

                <td class="{{ $fitFieldClass($displayRawValue($coMakerOne['telephone_no'] ?? null)) }}">{{ $displayRawValue($coMakerOne['telephone_no'] ?? null) }}</td>
                <td class="label">Current Position:</td>

                <td class="{{ $fitFieldClass($displayProperText($coMakerOne['current_position'] ?? '')) }}">{{ $displayProperText($coMakerOne['current_position'] ?? '') }}</td>
            </tr>
            <tr class="row-line">
                <td class="label">Nature of Business:</td>

                <td class="{{ $fitFieldClass($coMakerOne['nature_of_business'] ?? '') }}">{{ $displayText($coMakerOne['nature_of_business'] ?? '') }}</td>
                <td class="label">TOTAL YEARS:</td>

                <td class="{{ $fitFieldClass($formatYears($coMakerOne['years_in_work_business'] ?? null)) }}">{{ $formatYears($coMakerOne['years_in_work_business'] ?? null) }}</td>
            </tr>
            <tr class="row-line">
                <td class="label">Gross Monthly Income:</td>

                <td class="{{ $fitFieldClass($formatCurrency($coMakerOne['gross_monthly_income'] ?? null)) }}">{{ $formatCurrency($coMakerOne['gross_monthly_income'] ?? null) }}</td>
                <td class="label">Payday:</td>

                <td class="{{ $fitFieldClass($displayRawValue($coMakerOne['payday'] ?? null)) }}">{{ $displayRawValue($coMakerOne['payday'] ?? null) }}</td>
            </tr>
        </table>
        <table class="section-table">
            <colgroup>
                <col style="width: 14%" />
                <col style="width: 86%" />
            </colgroup>
            <tr class="row-line">
                <td class="label">Business Address:</td>

                <td class="{{ $fitFieldClass($displayProperText($coMakerOne['employer_business_address'] ?? '')) }}">{{ $displayProperText($coMakerOne['employer_business_address'] ?? '') }}</td>
            </tr>
        </table>
    </div>

    <div class="section-group">
        <div class="section-title">IV. My Co Maker 2</div>
        <table class="section-table">
        <colgroup>
            <col style="width: 14%" />
            <col style="width: 18%" />
            <col style="width: 14%" />
            <col style="width: 14%" />
            <col style="width: 14%" />
            <col style="width: 26%" />
        </colgroup>
        <tr>
            <td class="label">First Name:</td>
            <td class="{{ $fitFieldClass($displayProperText($coMakerTwo['first_name'] ?? '')) }}">{{ $displayProperText($coMakerTwo['first_name'] ?? '') }}</td>
            <td class="label">Last Name:</td>
            <td class="{{ $fitFieldClass($displayProperText($coMakerTwo['last_name'] ?? '')) }}">{{ $displayProperText($coMakerTwo['last_name'] ?? '') }}</td>
            <td class="label">Middle Name:</td>
            <td class="{{ $fitFieldClass($displayProperText($coMakerTwo['middle_name'] ?? '')) }}">{{ $displayProperText($coMakerTwo['middle_name'] ?? '') }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Nickname:</td>

            <td class="{{ $fitFieldClass($displayProperText($coMakerTwo['nickname'] ?? '')) }}">{{ $displayProperText($coMakerTwo['nickname'] ?? '') }}</td>
            <td class="label">Birthdate:</td>

            <td class="{{ $fitFieldClass($formatDate($coMakerTwo['birthdate'] ?? null)) }}">{{ $formatDate($coMakerTwo['birthdate'] ?? null) }}</td>
            <td class="label">Birth Place:</td>

            <td class="{{ $fitFieldClass($displayProperText($coMakerTwo['birthplace'] ?? '')) }}">{{ $displayProperText($coMakerTwo['birthplace'] ?? '') }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Length of Stay:</td>

            <td class="{{ $fitFieldClass($formatMonths($coMakerTwo['length_of_stay'] ?? null)) }}">{{ $formatMonths($coMakerTwo['length_of_stay'] ?? null) }}</td>
            <td class="label">Cell No.:</td>

            <td class="{{ $fitFieldClass($displayRawValue($coMakerTwo['cell_no'] ?? null)) }}">{{ $displayRawValue($coMakerTwo['cell_no'] ?? null) }}</td>
            <td class="label">Educational Attainment:</td>

            <td class="{{ $fitFieldClass($displayRawValue($coMakerTwo['educational_attainment'] ?? null)) }}">{{ $displayRawValue($coMakerTwo['educational_attainment'] ?? null) }}</td>
        </tr>
        </table>
        <table class="section-table">
        <colgroup>
            <col style="width: 14%" />
            <col style="width: 86%" />
        </colgroup>
            <tr class="row-line">
                <td class="label">Address:</td>

                <td class="{{ $fitFieldClass($displayProperText($coMakerTwo['address'] ?? '')) }}">{{ $displayProperText($coMakerTwo['address'] ?? '') }}</td>
            </tr>
        </table>
        <table class="section-table">
            <colgroup>
                <col style="width: 14%" />
                <col style="width: 18%" />
                <col style="width: 14%" />
                <col style="width: 54%" />
            </colgroup>
            <tr class="row-line">
                <td class="label">Employment:</td>

                <td class="{{ $fitFieldClass($displayRawValue($coMakerTwo['employment_type'] ?? null)) }}">{{ $displayRawValue($coMakerTwo['employment_type'] ?? null) }}</td>
                <td class="label">Employer/Business Name:</td>

                <td class="{{ $fitFieldClass($displayProperText($coMakerTwo['employer_business_name'] ?? '')) }}">{{ $displayProperText($coMakerTwo['employer_business_name'] ?? '') }}</td>
            </tr>
            <tr class="row-line">
                <td class="label">Tel. No.:</td>

                <td class="{{ $fitFieldClass($displayRawValue($coMakerTwo['telephone_no'] ?? null)) }}">{{ $displayRawValue($coMakerTwo['telephone_no'] ?? null) }}</td>
                <td class="label">Current Position:</td>

                <td class="{{ $fitFieldClass($displayProperText($coMakerTwo['current_position'] ?? '')) }}">{{ $displayProperText($coMakerTwo['current_position'] ?? '') }}</td>
            </tr>
            <tr class="row-line">
                <td class="label">Nature of Business:</td>

                <td class="{{ $fitFieldClass($coMakerTwo['nature_of_business'] ?? '') }}">{{ $displayText($coMakerTwo['nature_of_business'] ?? '') }}</td>
                <td class="label">TOTAL YEARS:</td>

                <td class="{{ $fitFieldClass($formatYears($coMakerTwo['years_in_work_business'] ?? null)) }}">{{ $formatYears($coMakerTwo['years_in_work_business'] ?? null) }}</td>
            </tr>
            <tr class="row-line">
                <td class="label">Gross Monthly Income:</td>

                <td class="{{ $fitFieldClass($formatCurrency($coMakerTwo['gross_monthly_income'] ?? null)) }}">{{ $formatCurrency($coMakerTwo['gross_monthly_income'] ?? null) }}</td>
                <td class="label">Payday:</td>

                <td class="{{ $fitFieldClass($displayRawValue($coMakerTwo['payday'] ?? null)) }}">{{ $displayRawValue($coMakerTwo['payday'] ?? null) }}</td>
            </tr>
        </table>
        <table class="section-table">
            <colgroup>
                <col style="width: 14%" />
                <col style="width: 86%" />
            </colgroup>
            <tr class="row-line">
                <td class="label">Business Address:</td>

                <td class="{{ $fitFieldClass($displayProperText($coMakerTwo['employer_business_address'] ?? '')) }}">{{ $displayProperText($coMakerTwo['employer_business_address'] ?? '') }}</td>
            </tr>
        </table>
    </div>

    <div class="section-group section-group--undertaking">
        <div class="section-title section-title--undertaking">Undertaking</div>
        <div class="undertaking">
        <p>
            I/We hereby undertake that all information provided here in this application form
            and in all supporting document are true and correct. I/We hereby authorized MRDINC
            to verify any and all information furnished by me/us including previous credit
            transactions with other institution. In this connection, I/We hereby expressly waive
            any and all statutory or regulatory provisions governing confidentiality of such
            information. I fully understand that any misrepresentation or failure to disclose
            information on my/our part as required herein, may cause the disapproval of my
            application.
        </p>
        <p>
            Upon acceptance of my application, I/We legally and validly bind to the terms and
            conditions of MRDINC including, but not limited to, join and several liability for
            all charges, fees and other obligations incurred through the use of my loan. In case
            of disapproval of this application, I understand that MRDINC is not obligated to
            disclose the reasons for such disapproval.
        </p>
        <p>
            In the event of future delinquency, I hereby authorized MRDINC to report and or
            include my name in the negative listing of any bureau or institution.
        </p>
        </div>
    </div>

    <div class="section-group section-group--signature">
        <table class="signature-table">
            <tr>
                @foreach ($signatureBlocks as $signatureBlock)
                    <td class="signature-cell">
                        <div class="signature-signing-space"></div>
                        <div class="signature-name">{{ $signatureBlock['name'] }}</div>
                        <div class="signature-line"></div>
                        <div class="signature-label">{{ $signatureBlock['label'] }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    </div>
</div>
