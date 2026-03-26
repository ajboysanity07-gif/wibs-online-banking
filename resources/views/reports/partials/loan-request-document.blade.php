@php
    $formatDate = fn ($value) => $value
        ? \Illuminate\Support\Carbon::parse($value)->format('m/d/Y')
        : '';
    $formatCurrency = fn ($value) => $value === null
        ? ''
        : number_format((float) $value, 2);
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
    $reportTitle = $reportHeader['title'] ?? null;
    $reportTagline = $reportHeader['tagline'] ?? null;
    $titleText = $reportTitle ?: 'APPLICATION FORM';
@endphp

<div class="page">
    @include('reports.partials.report-header', [
        'reportTitle' => $titleText,
        'reportTagline' => $reportTagline,
    ])

    <div class="section-group">
        <table class="info-table">
        <colgroup>
            <col style="width: 14%" />
            <col style="width: 46%" />
            <col style="width: 14%" />
            <col style="width: 26%" />
        </colgroup>
        <tr class="row-line">
            <td class="label">Application Status</td>

            <td class="field">Approved <span class="checkbox">{!! $check($status === 'approved') !!}</span>
                    Declined <span class="checkbox">{!! $check($status === 'declined') !!}</span></td>
            <td class="label">Date</td>

            <td class="{{ $fitFieldClass($formatDate($loanRequest->submitted_at)) }}">{{ $formatDate($loanRequest->submitted_at) }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Amount Approved</td>

            <td class="{{ $fitFieldClass($formatCurrency($loanRequest->approved_amount)) }}">{{ $formatCurrency($loanRequest->approved_amount) }}</td>
            <td class="label">Approved Loan Term/Duration</td>

            <td class="{{ $fitFieldClass($loanRequest->approved_term ?? '') }}">{{ $loanRequest->approved_term ?? '' }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Loan Type</td>

            <td class="{{ $fitFieldClass($loanRequest->loan_type_label_snapshot) }}">{{ $loanRequest->loan_type_label_snapshot }}</td>
            <td class="label">Loan Purpose</td>

            <td class="{{ $fitFieldClass($loanRequest->loan_purpose) }}">{{ $loanRequest->loan_purpose }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Availment Status</td>

            <td class="field" colspan="3">New <span class="checkbox">{!! $check($loanRequest->availment_status === 'New') !!}</span>
                    Re-Loan <span class="checkbox">{!! $check($loanRequest->availment_status === 'Re-Loan') !!}</span>
                    Re-Structured <span class="checkbox">{!! $check($loanRequest->availment_status === 'Restructured') !!}</span></td>
        </tr>
        <tr class="row-line">
            <td class="label">Recommended By</td>

            <td class="field"></td>
            <td class="label">Approved By</td>

            <td class="field"></td>
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
            <td class="label">First Name</td>
            <td class="{{ $fitFieldClass($applicant['first_name'] ?? '') }}">{{ $applicant['first_name'] ?? '' }}</td>
            <td class="label">Last Name</td>
            <td class="{{ $fitFieldClass($applicant['last_name'] ?? '') }}">{{ $applicant['last_name'] ?? '' }}</td>
            <td class="label">Middle Name</td>
            <td class="{{ $fitFieldClass($applicant['middle_name'] ?? '') }}">{{ $applicant['middle_name'] ?? '' }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Nickname</td>

            <td class="{{ $fitFieldClass($applicant['nickname'] ?? '') }}">{{ $applicant['nickname'] ?? '' }}</td>
            <td class="label">Birthdate</td>

            <td class="{{ $fitFieldClass($formatDate($applicant['birthdate'] ?? null)) }}">{{ $formatDate($applicant['birthdate'] ?? null) }}</td>
            <td class="label">Birth Place</td>

            <td class="{{ $fitFieldClass($applicant['birthplace'] ?? '') }}">{{ $applicant['birthplace'] ?? '' }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Length of Stay</td>

            <td class="{{ $fitFieldClass($applicant['length_of_stay'] ?? '') }}">{{ $applicant['length_of_stay'] ?? '' }}</td>
            <td class="label">Housing Status</td>

            <td class="{{ $fitFieldClass($applicant['housing_status'] ?? '') }}">{{ $applicant['housing_status'] ?? '' }}</td>
            <td class="label">Cell No.</td>

            <td class="{{ $fitFieldClass($applicant['cell_no'] ?? '') }}">{{ $applicant['cell_no'] ?? '' }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Civil Status</td>

            <td class="{{ $fitFieldClass($applicant['civil_status'] ?? '') }}">{{ $applicant['civil_status'] ?? '' }}</td>
            <td class="label">Educational Attainment</td>

            <td class="{{ $fitFieldClass($applicant['educational_attainment'] ?? '') }}">{{ $applicant['educational_attainment'] ?? '' }}</td>
            <td class="label">No. of Children</td>

            <td class="{{ $fitFieldClass($applicant['number_of_children'] ?? '') }}">{{ $applicant['number_of_children'] ?? '' }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Spouse Name</td>

            <td class="{{ $fitFieldClass($applicant['spouse_name'] ?? '') }}">{{ $applicant['spouse_name'] ?? '' }}</td>
            <td class="label">Spouse Age</td>

            <td class="{{ $fitFieldClass($applicant['spouse_age'] ?? '') }}">{{ $applicant['spouse_age'] ?? '' }}</td>
            <td class="label">Spouse Cell No.</td>

            <td class="{{ $fitFieldClass($applicant['spouse_cell_no'] ?? '') }}">{{ $applicant['spouse_cell_no'] ?? '' }}</td>
        </tr>
        </table>
        <table class="section-table">
        <colgroup>
            <col style="width: 14%" />
            <col style="width: 86%" />
        </colgroup>
        <tr class="row-line">
            <td class="label">Address</td>

            <td class="{{ $fitFieldClass($applicant['address'] ?? '') }}">{{ $applicant['address'] ?? '' }}</td>
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
            <td class="label">Employment</td>

            <td class="{{ $fitFieldClass($applicant['employment_type'] ?? '') }}">{{ $applicant['employment_type'] ?? '' }}</td>
            <td class="label">Employer/Business Name</td>

            <td class="{{ $fitFieldClass($applicant['employer_business_name'] ?? '') }}">{{ $applicant['employer_business_name'] ?? '' }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Tel. No.</td>

            <td class="{{ $fitFieldClass($applicant['telephone_no'] ?? '') }}">{{ $applicant['telephone_no'] ?? '' }}</td>
            <td class="label">Current Position</td>

            <td class="{{ $fitFieldClass($applicant['current_position'] ?? '') }}">{{ $applicant['current_position'] ?? '' }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Nature of Business</td>

            <td class="{{ $fitFieldClass($applicant['nature_of_business'] ?? '') }}">{{ $applicant['nature_of_business'] ?? '' }}</td>
            <td class="label">TOTAL YEARS</td>

            <td class="{{ $fitFieldClass($applicant['years_in_work_business'] ?? '') }}">{{ $applicant['years_in_work_business'] ?? '' }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Gross Monthly Income</td>

            <td class="{{ $fitFieldClass($formatCurrency($applicant['gross_monthly_income'] ?? null)) }}">{{ $formatCurrency($applicant['gross_monthly_income'] ?? null) }}</td>
            <td class="label">Payday</td>

            <td class="{{ $fitFieldClass($applicant['payday'] ?? '') }}">{{ $applicant['payday'] ?? '' }}</td>
        </tr>
        </table>
        <table class="section-table">
            <colgroup>
                <col style="width: 14%" />
                <col style="width: 86%" />
            </colgroup>
            <tr class="row-line">
                <td class="label">Business Address</td>

                <td class="{{ $fitFieldClass($applicant['employer_business_address'] ?? '') }}">{{ $applicant['employer_business_address'] ?? '' }}</td>
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
            <td class="label">First Name</td>
            <td class="{{ $fitFieldClass($coMakerOne['first_name'] ?? '') }}">{{ $coMakerOne['first_name'] ?? '' }}</td>
            <td class="label">Last Name</td>
            <td class="{{ $fitFieldClass($coMakerOne['last_name'] ?? '') }}">{{ $coMakerOne['last_name'] ?? '' }}</td>
            <td class="label">Middle Name</td>
            <td class="{{ $fitFieldClass($coMakerOne['middle_name'] ?? '') }}">{{ $coMakerOne['middle_name'] ?? '' }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Nickname</td>

            <td class="{{ $fitFieldClass($coMakerOne['nickname'] ?? '') }}">{{ $coMakerOne['nickname'] ?? '' }}</td>
            <td class="label">Birthdate</td>

            <td class="{{ $fitFieldClass($formatDate($coMakerOne['birthdate'] ?? null)) }}">{{ $formatDate($coMakerOne['birthdate'] ?? null) }}</td>
            <td class="label">Birth Place</td>

            <td class="{{ $fitFieldClass($coMakerOne['birthplace'] ?? '') }}">{{ $coMakerOne['birthplace'] ?? '' }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Length of Stay</td>

            <td class="{{ $fitFieldClass($coMakerOne['length_of_stay'] ?? '') }}">{{ $coMakerOne['length_of_stay'] ?? '' }}</td>
            <td class="label">Cell No.</td>

            <td class="{{ $fitFieldClass($coMakerOne['cell_no'] ?? '') }}">{{ $coMakerOne['cell_no'] ?? '' }}</td>
            <td class="label">Educational Attainment</td>

            <td class="{{ $fitFieldClass($coMakerOne['educational_attainment'] ?? '') }}">{{ $coMakerOne['educational_attainment'] ?? '' }}</td>
        </tr>
        </table>
        <table class="section-table">
        <colgroup>
            <col style="width: 14%" />
            <col style="width: 86%" />
        </colgroup>
            <tr class="row-line">
                <td class="label">Address</td>

                <td class="{{ $fitFieldClass($coMakerOne['address'] ?? '') }}">{{ $coMakerOne['address'] ?? '' }}</td>
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
                <td class="label">Employment</td>

                <td class="{{ $fitFieldClass($coMakerOne['employment_type'] ?? '') }}">{{ $coMakerOne['employment_type'] ?? '' }}</td>
                <td class="label">Employer/Business Name</td>

                <td class="{{ $fitFieldClass($coMakerOne['employer_business_name'] ?? '') }}">{{ $coMakerOne['employer_business_name'] ?? '' }}</td>
            </tr>
            <tr class="row-line">
                <td class="label">Tel. No.</td>

                <td class="{{ $fitFieldClass($coMakerOne['telephone_no'] ?? '') }}">{{ $coMakerOne['telephone_no'] ?? '' }}</td>
                <td class="label">Current Position</td>

                <td class="{{ $fitFieldClass($coMakerOne['current_position'] ?? '') }}">{{ $coMakerOne['current_position'] ?? '' }}</td>
            </tr>
            <tr class="row-line">
                <td class="label">Nature of Business</td>

                <td class="{{ $fitFieldClass($coMakerOne['nature_of_business'] ?? '') }}">{{ $coMakerOne['nature_of_business'] ?? '' }}</td>
                <td class="label">TOTAL YEARS</td>

                <td class="{{ $fitFieldClass($coMakerOne['years_in_work_business'] ?? '') }}">{{ $coMakerOne['years_in_work_business'] ?? '' }}</td>
            </tr>
            <tr class="row-line">
                <td class="label">Gross Monthly Income</td>

                <td class="{{ $fitFieldClass($formatCurrency($coMakerOne['gross_monthly_income'] ?? null)) }}">{{ $formatCurrency($coMakerOne['gross_monthly_income'] ?? null) }}</td>
                <td class="label">Payday</td>

                <td class="{{ $fitFieldClass($coMakerOne['payday'] ?? '') }}">{{ $coMakerOne['payday'] ?? '' }}</td>
            </tr>
        </table>
        <table class="section-table">
            <colgroup>
                <col style="width: 14%" />
                <col style="width: 86%" />
            </colgroup>
            <tr class="row-line">
                <td class="label">Business Address</td>

                <td class="{{ $fitFieldClass($coMakerOne['employer_business_address'] ?? '') }}">{{ $coMakerOne['employer_business_address'] ?? '' }}</td>
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
            <td class="label">First Name</td>
            <td class="{{ $fitFieldClass($coMakerTwo['first_name'] ?? '') }}">{{ $coMakerTwo['first_name'] ?? '' }}</td>
            <td class="label">Last Name</td>
            <td class="{{ $fitFieldClass($coMakerTwo['last_name'] ?? '') }}">{{ $coMakerTwo['last_name'] ?? '' }}</td>
            <td class="label">Middle Name</td>
            <td class="{{ $fitFieldClass($coMakerTwo['middle_name'] ?? '') }}">{{ $coMakerTwo['middle_name'] ?? '' }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Nickname</td>

            <td class="{{ $fitFieldClass($coMakerTwo['nickname'] ?? '') }}">{{ $coMakerTwo['nickname'] ?? '' }}</td>
            <td class="label">Birthdate</td>

            <td class="{{ $fitFieldClass($formatDate($coMakerTwo['birthdate'] ?? null)) }}">{{ $formatDate($coMakerTwo['birthdate'] ?? null) }}</td>
            <td class="label">Birth Place</td>

            <td class="{{ $fitFieldClass($coMakerTwo['birthplace'] ?? '') }}">{{ $coMakerTwo['birthplace'] ?? '' }}</td>
        </tr>
        <tr class="row-line">
            <td class="label">Length of Stay</td>

            <td class="{{ $fitFieldClass($coMakerTwo['length_of_stay'] ?? '') }}">{{ $coMakerTwo['length_of_stay'] ?? '' }}</td>
            <td class="label">Cell No.</td>

            <td class="{{ $fitFieldClass($coMakerTwo['cell_no'] ?? '') }}">{{ $coMakerTwo['cell_no'] ?? '' }}</td>
            <td class="label">Educational Attainment</td>

            <td class="{{ $fitFieldClass($coMakerTwo['educational_attainment'] ?? '') }}">{{ $coMakerTwo['educational_attainment'] ?? '' }}</td>
        </tr>
        </table>
        <table class="section-table">
        <colgroup>
            <col style="width: 14%" />
            <col style="width: 86%" />
        </colgroup>
            <tr class="row-line">
                <td class="label">Address</td>

                <td class="{{ $fitFieldClass($coMakerTwo['address'] ?? '') }}">{{ $coMakerTwo['address'] ?? '' }}</td>
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
                <td class="label">Employment</td>

                <td class="{{ $fitFieldClass($coMakerTwo['employment_type'] ?? '') }}">{{ $coMakerTwo['employment_type'] ?? '' }}</td>
                <td class="label">Employer/Business Name</td>

                <td class="{{ $fitFieldClass($coMakerTwo['employer_business_name'] ?? '') }}">{{ $coMakerTwo['employer_business_name'] ?? '' }}</td>
            </tr>
            <tr class="row-line">
                <td class="label">Tel. No.</td>

                <td class="{{ $fitFieldClass($coMakerTwo['telephone_no'] ?? '') }}">{{ $coMakerTwo['telephone_no'] ?? '' }}</td>
                <td class="label">Current Position</td>

                <td class="{{ $fitFieldClass($coMakerTwo['current_position'] ?? '') }}">{{ $coMakerTwo['current_position'] ?? '' }}</td>
            </tr>
            <tr class="row-line">
                <td class="label">Nature of Business</td>

                <td class="{{ $fitFieldClass($coMakerTwo['nature_of_business'] ?? '') }}">{{ $coMakerTwo['nature_of_business'] ?? '' }}</td>
                <td class="label">TOTAL YEARS</td>

                <td class="{{ $fitFieldClass($coMakerTwo['years_in_work_business'] ?? '') }}">{{ $coMakerTwo['years_in_work_business'] ?? '' }}</td>
            </tr>
            <tr class="row-line">
                <td class="label">Gross Monthly Income</td>

                <td class="{{ $fitFieldClass($formatCurrency($coMakerTwo['gross_monthly_income'] ?? null)) }}">{{ $formatCurrency($coMakerTwo['gross_monthly_income'] ?? null) }}</td>
                <td class="label">Payday</td>

                <td class="{{ $fitFieldClass($coMakerTwo['payday'] ?? '') }}">{{ $coMakerTwo['payday'] ?? '' }}</td>
            </tr>
        </table>
        <table class="section-table">
            <colgroup>
                <col style="width: 14%" />
                <col style="width: 86%" />
            </colgroup>
            <tr class="row-line">
                <td class="label">Business Address</td>

                <td class="{{ $fitFieldClass($coMakerTwo['employer_business_address'] ?? '') }}">{{ $coMakerTwo['employer_business_address'] ?? '' }}</td>
            </tr>
        </table>
    </div>

    <div class="section-group">
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

        <div class="signature-row">
            <div class="signature-line">Signature of Applicant / Printed Name</div>
            <div class="signature-line">Signature of Co-Maker 1 / Printed Name</div>
            <div class="signature-line">Signature of Co-Maker 2 / Printed Name</div>
        </div>
    </div>
</div>




