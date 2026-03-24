<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>Loan Application Form</title>
        <style>
            body {
                font-family: "DejaVu Sans", sans-serif;
                font-size: 11px;
                color: #111;
                margin: 24px;
            }
            .page {
                border: 2px solid #111;
                padding: 18px 18px 24px;
            }
            .header {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .header--wordmark {
                gap: 0;
            }
            .logo {
                height: 42px;
            }
            .header--wordmark .logo {
                height: 48px;
            }
            .company-name {
                font-size: 14px;
                font-weight: 700;
            }
            .form-title {
                text-align: center;
                font-size: 16px;
                font-weight: 700;
                margin: 10px 0 12px;
                letter-spacing: 0.05em;
            }
            .section-title {
                background: #111;
                color: #fff;
                padding: 4px 8px;
                font-size: 11px;
                text-transform: uppercase;
                margin-top: 14px;
            }
            .info-table,
            .section-table {
                width: 100%;
                border-collapse: collapse;
            }
            .info-table td,
            .section-table td {
                padding: 4px 6px;
                vertical-align: bottom;
            }
            .label {
                font-size: 9px;
                text-transform: uppercase;
                color: #333;
                white-space: nowrap;
            }
            .field {
                border-bottom: 1px solid #111;
                font-size: 11px;
                font-weight: 600;
                min-height: 14px;
            }
            .checkbox {
                display: inline-block;
                width: 12px;
                height: 12px;
                border: 1px solid #111;
                text-align: center;
                line-height: 12px;
                font-size: 10px;
                margin: 0 4px 0 8px;
            }
            .undertaking {
                font-size: 10px;
                line-height: 1.4;
                margin-top: 16px;
            }
            .signature-row {
                margin-top: 20px;
                display: flex;
                justify-content: space-between;
                gap: 12px;
                font-size: 10px;
                text-align: center;
            }
            .signature-line {
                border-top: 1px solid #111;
                padding-top: 6px;
                width: 32%;
            }
        </style>
    </head>
    <body>
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
            $check = fn (bool $value) => $value ? 'X' : '';
            $showCompanyName = $showCompanyName ?? true;
            $shouldShowCompanyName = $showCompanyName || ! $logoData;
            $headerClass = $showCompanyName ? 'header' : 'header header--wordmark';
        @endphp

        <div class="page">
            <div class="{{ $headerClass }}">
                @if ($logoData)
                    <img src="{{ $logoData }}" alt="Company logo" class="logo" />
                @endif
                @if ($shouldShowCompanyName)
                    <div class="company-name">{{ $companyName }}</div>
                @endif
            </div>

            <div class="form-title">APPLICATION FORM</div>

            <table class="info-table">
                <tr>
                    <td class="label">Application Status</td>
                    <td class="field">
                        Approved <span class="checkbox">{{ $check($status === 'approved') }}</span>
                        Declined <span class="checkbox">{{ $check($status === 'declined') }}</span>
                    </td>
                    <td class="label">Date</td>
                    <td class="field">{{ $formatDate($loanRequest->submitted_at) }}</td>
                </tr>
                <tr>
                    <td class="label">Amount Approved</td>
                    <td class="field">{{ $formatCurrency($loanRequest->approved_amount) }}</td>
                    <td class="label">Approved Loan Term/Duration</td>
                    <td class="field">{{ $loanRequest->approved_term ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Loan Type</td>
                    <td class="field">{{ $loanRequest->loan_type_label_snapshot }}</td>
                    <td class="label">Loan Purpose</td>
                    <td class="field">{{ $loanRequest->loan_purpose }}</td>
                </tr>
                <tr>
                    <td class="label">Availment Status</td>
                    <td class="field" colspan="3">
                        New <span class="checkbox">{{ $check($loanRequest->availment_status === 'New') }}</span>
                        Re-Loan <span class="checkbox">{{ $check($loanRequest->availment_status === 'Re-Loan') }}</span>
                        Re-Structured <span class="checkbox">{{ $check($loanRequest->availment_status === 'Restructured') }}</span>
                    </td>
                </tr>
                <tr>
                    <td class="label">Recommended By</td>
                    <td class="field"></td>
                    <td class="label">Approved By</td>
                    <td class="field"></td>
                </tr>
            </table>

            <div class="section-title">I. My Personal Data</div>
            <table class="section-table">
                <tr>
                    <td class="label">First Name</td>
                    <td class="field">{{ $applicant['first_name'] ?? '' }}</td>
                    <td class="label">Last Name</td>
                    <td class="field">{{ $applicant['last_name'] ?? '' }}</td>
                    <td class="label">Middle Name</td>
                    <td class="field">{{ $applicant['middle_name'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Nickname</td>
                    <td class="field">{{ $applicant['nickname'] ?? '' }}</td>
                    <td class="label">Birthdate</td>
                    <td class="field">{{ $formatDate($applicant['birthdate'] ?? null) }}</td>
                    <td class="label">Birth Place</td>
                    <td class="field">{{ $applicant['birthplace'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Address</td>
                    <td class="field" colspan="5">{{ $applicant['address'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Length of Stay</td>
                    <td class="field">{{ $applicant['length_of_stay'] ?? '' }}</td>
                    <td class="label">Housing Status</td>
                    <td class="field">{{ $applicant['housing_status'] ?? '' }}</td>
                    <td class="label">Cell No.</td>
                    <td class="field">{{ $applicant['cell_no'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Civil Status</td>
                    <td class="field">{{ $applicant['civil_status'] ?? '' }}</td>
                    <td class="label">Educational Attainment</td>
                    <td class="field">{{ $applicant['educational_attainment'] ?? '' }}</td>
                    <td class="label">No. of Children</td>
                    <td class="field">{{ $applicant['number_of_children'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Spouse Name</td>
                    <td class="field">{{ $applicant['spouse_name'] ?? '' }}</td>
                    <td class="label">Spouse Age</td>
                    <td class="field">{{ $applicant['spouse_age'] ?? '' }}</td>
                    <td class="label">Spouse Cell No.</td>
                    <td class="field">{{ $applicant['spouse_cell_no'] ?? '' }}</td>
                </tr>
            </table>

            <div class="section-title">II. My Work & Finances</div>
            <table class="section-table">
                <tr>
                    <td class="label">Employment</td>
                    <td class="field">{{ $applicant['employment_type'] ?? '' }}</td>
                    <td class="label">Employer/Business Name</td>
                    <td class="field" colspan="3">{{ $applicant['employer_business_name'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Business Address</td>
                    <td class="field" colspan="5">{{ $applicant['employer_business_address'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Tel. No.</td>
                    <td class="field">{{ $applicant['telephone_no'] ?? '' }}</td>
                    <td class="label">Current Position</td>
                    <td class="field" colspan="3">{{ $applicant['current_position'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Nature of Business</td>
                    <td class="field">{{ $applicant['nature_of_business'] ?? '' }}</td>
                    <td class="label">Total Years in Work/Business</td>
                    <td class="field" colspan="3">{{ $applicant['years_in_work_business'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Gross Monthly Income</td>
                    <td class="field">{{ $formatCurrency($applicant['gross_monthly_income'] ?? null) }}</td>
                    <td class="label">Payday</td>
                    <td class="field" colspan="3">{{ $applicant['payday'] ?? '' }}</td>
                </tr>
            </table>

            <div class="section-title">III. My Co Maker 1</div>
            <table class="section-table">
                <tr>
                    <td class="label">First Name</td>
                    <td class="field">{{ $coMakerOne['first_name'] ?? '' }}</td>
                    <td class="label">Last Name</td>
                    <td class="field">{{ $coMakerOne['last_name'] ?? '' }}</td>
                    <td class="label">Middle Name</td>
                    <td class="field">{{ $coMakerOne['middle_name'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Nickname</td>
                    <td class="field">{{ $coMakerOne['nickname'] ?? '' }}</td>
                    <td class="label">Birthdate</td>
                    <td class="field">{{ $formatDate($coMakerOne['birthdate'] ?? null) }}</td>
                    <td class="label">Birth Place</td>
                    <td class="field">{{ $coMakerOne['birthplace'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Address</td>
                    <td class="field" colspan="5">{{ $coMakerOne['address'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Length of Stay</td>
                    <td class="field">{{ $coMakerOne['length_of_stay'] ?? '' }}</td>
                    <td class="label">Cell No.</td>
                    <td class="field">{{ $coMakerOne['cell_no'] ?? '' }}</td>
                    <td class="label">Educational Attainment</td>
                    <td class="field">{{ $coMakerOne['educational_attainment'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Employment</td>
                    <td class="field">{{ $coMakerOne['employment_type'] ?? '' }}</td>
                    <td class="label">Employer/Business Name</td>
                    <td class="field" colspan="3">{{ $coMakerOne['employer_business_name'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Business Address</td>
                    <td class="field" colspan="5">{{ $coMakerOne['employer_business_address'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Tel. No.</td>
                    <td class="field">{{ $coMakerOne['telephone_no'] ?? '' }}</td>
                    <td class="label">Current Position</td>
                    <td class="field" colspan="3">{{ $coMakerOne['current_position'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Nature of Business</td>
                    <td class="field">{{ $coMakerOne['nature_of_business'] ?? '' }}</td>
                    <td class="label">Total Years in Work/Business</td>
                    <td class="field" colspan="3">{{ $coMakerOne['years_in_work_business'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Gross Monthly Income</td>
                    <td class="field">{{ $formatCurrency($coMakerOne['gross_monthly_income'] ?? null) }}</td>
                    <td class="label">Payday</td>
                    <td class="field" colspan="3">{{ $coMakerOne['payday'] ?? '' }}</td>
                </tr>
            </table>

            <div class="section-title">IV. My Co Maker 2</div>
            <table class="section-table">
                <tr>
                    <td class="label">First Name</td>
                    <td class="field">{{ $coMakerTwo['first_name'] ?? '' }}</td>
                    <td class="label">Last Name</td>
                    <td class="field">{{ $coMakerTwo['last_name'] ?? '' }}</td>
                    <td class="label">Middle Name</td>
                    <td class="field">{{ $coMakerTwo['middle_name'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Nickname</td>
                    <td class="field">{{ $coMakerTwo['nickname'] ?? '' }}</td>
                    <td class="label">Birthdate</td>
                    <td class="field">{{ $formatDate($coMakerTwo['birthdate'] ?? null) }}</td>
                    <td class="label">Birth Place</td>
                    <td class="field">{{ $coMakerTwo['birthplace'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Address</td>
                    <td class="field" colspan="5">{{ $coMakerTwo['address'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Length of Stay</td>
                    <td class="field">{{ $coMakerTwo['length_of_stay'] ?? '' }}</td>
                    <td class="label">Cell No.</td>
                    <td class="field">{{ $coMakerTwo['cell_no'] ?? '' }}</td>
                    <td class="label">Educational Attainment</td>
                    <td class="field">{{ $coMakerTwo['educational_attainment'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Employment</td>
                    <td class="field">{{ $coMakerTwo['employment_type'] ?? '' }}</td>
                    <td class="label">Employer/Business Name</td>
                    <td class="field" colspan="3">{{ $coMakerTwo['employer_business_name'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Business Address</td>
                    <td class="field" colspan="5">{{ $coMakerTwo['employer_business_address'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Tel. No.</td>
                    <td class="field">{{ $coMakerTwo['telephone_no'] ?? '' }}</td>
                    <td class="label">Current Position</td>
                    <td class="field" colspan="3">{{ $coMakerTwo['current_position'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Nature of Business</td>
                    <td class="field">{{ $coMakerTwo['nature_of_business'] ?? '' }}</td>
                    <td class="label">Total Years in Work/Business</td>
                    <td class="field" colspan="3">{{ $coMakerTwo['years_in_work_business'] ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Gross Monthly Income</td>
                    <td class="field">{{ $formatCurrency($coMakerTwo['gross_monthly_income'] ?? null) }}</td>
                    <td class="label">Payday</td>
                    <td class="field" colspan="3">{{ $coMakerTwo['payday'] ?? '' }}</td>
                </tr>
            </table>

            <div class="section-title">Undertaking</div>
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
    </body>
</html>
