import {
    AlertTriangle,
    Baby,
    Building2,
    ClipboardCheck,
    FileText,
    HeartPulse,
    Info,
    Save,
    Shield,
    User,
    Users,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { LoanRequestAnimatedStep } from '@/components/loan-request/loan-request-animated-step';
import {
    LoanRequestPersonalFields,
    LoanRequestWorkFields,
} from '@/components/loan-request/loan-request-fields';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import {
    LoanRequestDataSectionStep,
    LoanRequestDependentsStep,
    LoanRequestInsuranceBeneficiariesStep,
    LoanRequestLoanDetailsStep,
} from '@/components/loan-request/loan-request-steps';
import { LoanRequestWizardShell } from '@/components/loan-request/loan-request-wizard-shell';
import type { LoanRequestWizardStep } from '@/components/loan-request/loan-request-wizard-steps';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    formatCurrency,
    formatDateTime,
    toDateInputValue,
} from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type {
    LoanRequestCorrectionReport,
    LoanRequestCorrectionPayload,
    LoanRequestDataSectionDefinitions,
    LoanRequestDataSections,
    LoanRequestDataSectionValues,
    LoanRequestDetail,
    LoanRequestFormData,
    LoanRequestPersonData,
    LoanRequestPersonFormData,
    LoanTypeOption,
} from '@/types/loan-requests';

type CorrectionFormData = LoanRequestFormData & {
    change_reason: string;
};

type ValidationErrors = Record<string, string | undefined>;

type LoanDetailField =
    | 'typecode'
    | 'requested_amount'
    | 'requested_term'
    | 'loan_purpose'
    | 'availment_status';

type PersonSection = 'applicant' | 'co_maker_1' | 'co_maker_2';

type WizardStepId =
    | 'loan'
    | 'applicant'
    | 'co_maker_1'
    | 'co_maker_2'
    | 'insurance'
    | 'dependents'
    | 'health'
    | 'banking'
    | 'review';

type Props = {
    open: boolean;
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    loanTypes: LoanTypeOption[];
    dataSections: LoanRequestDataSections;
    dataSectionDefinitions: LoanRequestDataSectionDefinitions;
    correctionReportContext?: LoanRequestCorrectionReport | null;
    errors: ValidationErrors;
    isProcessing: boolean;
    onOpenChange: (open: boolean) => void;
    onSubmit: (payload: LoanRequestCorrectionPayload) => void;
};

type CorrectionDialogFormProps = Omit<Props, 'open'>;

type ChangeEntry = {
    field: string;
    label: string;
    before: string;
    after: string;
};

type ChangeGroup = {
    id: WizardStepId;
    title: string;
    description: string;
    changes: ChangeEntry[];
};

const WIZARD_STEPS: Array<LoanRequestWizardStep & { id: WizardStepId }> = [
    {
        id: 'loan',
        title: 'Loan details',
        description: 'Update loan type, amount, term, and purpose.',
        group: 'loan',
    },
    {
        id: 'applicant',
        title: 'Applicant details',
        description: 'Review applicant personal, work, and income data.',
        group: 'applicant',
    },
    {
        id: 'co_maker_1',
        title: 'Co-maker 1',
        description: 'Verify first co-maker information.',
        group: 'co_maker_1',
    },
    {
        id: 'co_maker_2',
        title: 'Co-maker 2',
        description: 'Verify second co-maker information.',
        group: 'co_maker_2',
    },
    {
        id: 'insurance',
        title: 'Insurance & beneficiaries',
        description:
            'Review beneficiary details -- who receives the insurance payout -- for document generation.',
        group: 'insurance',
    },
    {
        id: 'dependents',
        title: 'Dependents',
        description:
            'Review dependents covered under the group life insurance plan -- separate from the beneficiaries above.',
        group: 'dependents',
    },
    {
        id: 'health',
        title: 'Health basic info',
        description: 'Review smoking status and hypertension declaration.',
        group: 'health',
    },
    {
        id: 'banking',
        title: 'Loan Disbursement & Repayment',
        description: 'Review disbursement and repayment method details.',
        group: 'banking',
    },
    {
        id: 'review',
        title: 'Review changes & reason',
        description:
            'Confirm all corrections and provide a reason for the audit trail.',
        group: 'review',
        sidebarLabel: 'Review & reason',
    },
];

const WIZARD_STEP_GROUP_META = {
    loan: { label: 'Loan details', icon: FileText },
    applicant: { label: 'Applicant details', icon: User },
    co_maker_1: { label: 'Co-maker 1', icon: Users },
    co_maker_2: { label: 'Co-maker 2', icon: Users },
    insurance: { label: 'Insurance & beneficiaries', icon: Shield },
    dependents: { label: 'Dependents', icon: Baby },
    health: { label: 'Health basic info', icon: HeartPulse },
    banking: { label: 'Disbursement & Repayment', icon: Building2 },
    review: { label: 'Review & reason', icon: ClipboardCheck },
};

const AVAILMENT_OPTIONS = new Set(['New', 'Re-Loan', 'Restructured']);
const HOUSING_STATUS_OPTIONS = new Set(['OWNED', 'RENT']);
const CIVIL_STATUS_OPTIONS = new Set([
    'Single',
    'Married',
    'Separated',
    'Widowed',
]);
const PAYDAY_OPTIONS = new Set([
    'Weekly',
    '15th',
    '30th',
    '15th & 30th',
    'Bi-Weekly',
    'Monthly',
]);

const loanFieldLabels: Record<LoanDetailField, string> = {
    typecode: 'Loan type',
    requested_amount: 'Requested amount',
    requested_term: 'Requested term',
    loan_purpose: 'Loan purpose',
    availment_status: 'Availment status',
};

const personFieldLabels: Record<keyof LoanRequestPersonFormData, string> = {
    first_name: 'First name',
    middle_name: 'Middle name',
    last_name: 'Last name',
    nickname: 'Nickname',
    birthdate: 'Birthdate',
    birthplace_city: 'Birthplace city/municipality',
    birthplace_province: 'Birthplace province',
    address1: 'Address (street)',
    address_barangay: 'Barangay',
    address2: 'City/Municipality',
    address3: 'Province',
    address_zip: 'ZIP code',
    length_of_stay: 'Length of stay',
    housing_status: 'Housing status',
    cell_no: 'Cell no.',
    civil_status: 'Civil status',
    sex: 'Sex',
    educational_attainment: 'Educational attainment',
    number_of_children: 'No. of children',
    spouse_name: 'Spouse name',
    spouse_age: 'Spouse age',
    spouse_cell_no: 'Spouse cell no.',
    employment_type: 'Employment',
    employer_business_name: 'Employer/Business name',
    employer_business_address1: 'Employer/Business address (street)',
    employer_business_address_barangay: 'Employer/Business barangay',
    employer_business_address2: 'Employer/Business city/municipality',
    employer_business_address3: 'Employer/Business province',
    employer_business_address_zip: 'Employer/Business ZIP code',
    telephone_no: 'Tel. no.',
    current_position: 'Current position',
    nature_of_business: 'Nature of business',
    years_in_work_business: 'Total years in work/business',
    employer_date_employed: 'Date employed',
    gross_monthly_income: 'Gross monthly income',
    payday: 'Payday',
    // Saved co-maker reuse metadata -- never part of an admin correction,
    // see SavedCoMakersService. Included only to satisfy the shared type.
    save_for_reuse: 'Save for reuse',
    saved_co_maker_id: 'Saved co-maker ID',
    saved_co_maker_label: 'Saved co-maker label',
};

const dataSectionFieldLabels: Record<string, Record<string, string>> = {
    insurance: {
        beneficiary_primary_name: 'Primary beneficiary name',
        beneficiary_primary_relationship: 'Primary beneficiary relationship',
        beneficiary_primary_birthdate: 'Primary beneficiary birthdate',
        beneficiary_secondary_name: 'Secondary beneficiary name',
        beneficiary_secondary_relationship:
            'Secondary beneficiary relationship',
        beneficiary_secondary_birthdate: 'Secondary beneficiary birthdate',
    },
    health: {
        health_smoking_status: 'Smoking status',
        health_hypertension: 'Hypertension',
    },
    health_glapi: {
        applicant_pep_status: 'Applicant is a Politically Exposed Person (PEP)',
        applicant_pep_status_details: 'PEP role, function, and date assumed',
    },
    banking: {
        payout_bank_name: 'Payout bank name',
        payout_account_name: 'Payout account name',
        payout_account_number: 'Payout account number',
        payout_account_type: 'Payout account type',
        release_method: 'Release method',
        payment_option: 'Payment option',
        payout_atm_number: 'ATM number',
        payout_bank_branch: 'Payout bank branch',
        payout_atm_holder_name: 'Payout ATM card holder name',
        payment_bank_name: 'Repayment bank name',
        payment_account_name: 'Repayment account name',
        payment_account_number: 'Repayment account number',
        payment_account_type: 'Repayment account type',
        payment_atm_number: 'Repayment ATM number',
        payment_bank_branch: 'Repayment bank branch',
        payment_atm_holder_name: 'Repayment ATM card holder name',
    },
};

const applicantRequiredFields: Array<keyof LoanRequestPersonFormData> = [
    'first_name',
    'last_name',
    'birthdate',
    'birthplace_city',
    'birthplace_province',
    'address1',
    'address2',
    'address3',
    'length_of_stay',
    'housing_status',
    'cell_no',
    'civil_status',
    'educational_attainment',
    'number_of_children',
    'employment_type',
    'employer_business_name',
    'employer_business_address1',
    'employer_business_address2',
    'employer_business_address3',
    'current_position',
    'nature_of_business',
    'years_in_work_business',
    'gross_monthly_income',
    'payday',
];

const coMakerRequiredFields: Array<keyof LoanRequestPersonFormData> = [
    'first_name',
    'last_name',
    'birthdate',
    'birthplace_city',
    'birthplace_province',
    'address1',
    'address2',
    'address3',
    'length_of_stay',
    'cell_no',
    'educational_attainment',
    'employment_type',
    'employer_business_name',
    'employer_business_address1',
    'employer_business_address2',
    'employer_business_address3',
    'current_position',
    'nature_of_business',
    'years_in_work_business',
    'gross_monthly_income',
    'payday',
];

const loanStepFieldKeys: string[] = [
    'typecode',
    'requested_amount',
    'requested_term',
    'loan_purpose',
    'availment_status',
];

const applicantStepFieldKeys = [
    ...new Set(applicantRequiredFields.map((field) => `applicant.${field}`)),
    'applicant.spouse_age',
    'applicant.spouse_cell_no',
];

const coMakerOneStepFieldKeys = [
    ...new Set(coMakerRequiredFields.map((field) => `co_maker_1.${field}`)),
];

const coMakerTwoStepFieldKeys = [
    ...new Set(coMakerRequiredFields.map((field) => `co_maker_2.${field}`)),
];

const reviewStepFieldKeys = ['change_reason'];

const numericFieldPaths = new Set([
    'requested_amount',
    'requested_term',
    'applicant.number_of_children',
    'applicant.spouse_age',
    'applicant.gross_monthly_income',
    'co_maker_1.gross_monthly_income',
    'co_maker_2.gross_monthly_income',
]);

const currencyFieldPaths = new Set([
    'requested_amount',
    'applicant.gross_monthly_income',
    'co_maker_1.gross_monthly_income',
    'co_maker_2.gross_monthly_income',
]);

const applicantChangeFields: Array<keyof LoanRequestPersonFormData> = [
    'first_name',
    'middle_name',
    'last_name',
    'nickname',
    'birthdate',
    'birthplace_city',
    'birthplace_province',
    'address1',
    'address2',
    'address3',
    'length_of_stay',
    'housing_status',
    'cell_no',
    'civil_status',
    'educational_attainment',
    'number_of_children',
    'spouse_name',
    'spouse_age',
    'spouse_cell_no',
    'employment_type',
    'employer_business_name',
    'employer_business_address1',
    'employer_business_address2',
    'employer_business_address3',
    'telephone_no',
    'current_position',
    'nature_of_business',
    'years_in_work_business',
    'employer_date_employed',
    'gross_monthly_income',
    'payday',
];

const coMakerChangeFields: Array<keyof LoanRequestPersonFormData> = [
    'first_name',
    'middle_name',
    'last_name',
    'nickname',
    'birthdate',
    'birthplace_city',
    'birthplace_province',
    'address1',
    'address2',
    'address3',
    'length_of_stay',
    'cell_no',
    'educational_attainment',
    'employment_type',
    'employer_business_name',
    'employer_business_address1',
    'employer_business_address2',
    'employer_business_address3',
    'telephone_no',
    'current_position',
    'nature_of_business',
    'years_in_work_business',
    'gross_monthly_income',
    'payday',
];

const emptyPerson: LoanRequestPersonFormData = {
    first_name: '',
    middle_name: '',
    last_name: '',
    nickname: '',
    birthdate: '',
    birthplace_city: '',
    birthplace_province: '',
    address1: '',
    address_barangay: '',
    address2: '',
    address3: '',
    address_zip: '',
    length_of_stay: '',
    housing_status: '',
    cell_no: '',
    civil_status: '',
    sex: '',
    educational_attainment: '',
    number_of_children: '',
    spouse_name: '',
    spouse_age: '',
    spouse_cell_no: '',
    employment_type: '',
    employer_business_name: '',
    employer_business_address1: '',
    employer_business_address_barangay: '',
    employer_business_address2: '',
    employer_business_address3: '',
    employer_business_address_zip: '',
    telephone_no: '',
    current_position: '',
    nature_of_business: '',
    years_in_work_business: '',
    employer_date_employed: '',
    gross_monthly_income: '',
    payday: '',
    save_for_reuse: false,
    saved_co_maker_id: '',
    saved_co_maker_label: '',
};

const toStringValue = (
    value?: string | number | null,
    options?: { emptyIfZero?: boolean },
): string => {
    if (value === null || value === undefined) {
        return '';
    }

    const stringValue = `${value}`.trim();
    const emptyIfZero = options?.emptyIfZero ?? true;

    if (emptyIfZero && (stringValue === '0' || stringValue === '0.00')) {
        return '';
    }

    return stringValue;
};

const toPersonForm = (
    person: LoanRequestPersonData | null,
): LoanRequestPersonFormData => {
    if (!person) {
        return { ...emptyPerson };
    }

    return {
        ...emptyPerson,
        first_name: person.first_name ?? '',
        middle_name: person.middle_name ?? '',
        last_name: person.last_name ?? '',
        nickname: person.nickname ?? '',
        birthdate: toDateInputValue(person.birthdate),
        birthplace_city: person.birthplace_city ?? '',
        birthplace_province: person.birthplace_province ?? '',
        address1: person.address1 ?? '',
        address2: person.address2 ?? '',
        address3: person.address3 ?? '',
        address_zip: person.address_zip ?? '',
        length_of_stay: person.length_of_stay ?? '',
        housing_status: person.housing_status ?? '',
        cell_no: person.cell_no ?? '',
        civil_status: person.civil_status ?? '',
        educational_attainment: person.educational_attainment ?? '',
        number_of_children: toStringValue(person.number_of_children, {
            emptyIfZero: false,
        }),
        spouse_name: person.spouse_name ?? '',
        spouse_age: toStringValue(person.spouse_age),
        spouse_cell_no: person.spouse_cell_no ?? '',
        employment_type: person.employment_type ?? '',
        employer_business_name: person.employer_business_name ?? '',
        employer_business_address1: person.employer_business_address1 ?? '',
        employer_business_address2: person.employer_business_address2 ?? '',
        employer_business_address3: person.employer_business_address3 ?? '',
        employer_business_address_zip:
            person.employer_business_address_zip ?? '',
        telephone_no: person.telephone_no ?? '',
        current_position: person.current_position ?? '',
        nature_of_business: person.nature_of_business ?? '',
        years_in_work_business: person.years_in_work_business ?? '',
        employer_date_employed: person.employer_date_employed ?? '',
        gross_monthly_income: toStringValue(person.gross_monthly_income),
        payday: person.payday ?? '',
    };
};

const buildInitialFormData = (
    loanRequest: LoanRequestDetail,
    applicant: LoanRequestPersonData | null,
    coMakerOne: LoanRequestPersonData | null,
    coMakerTwo: LoanRequestPersonData | null,
    dataSections: LoanRequestDataSections,
    correctionReportContext?: LoanRequestCorrectionReport | null,
): CorrectionFormData => ({
    typecode: loanRequest.typecode ?? '',
    requested_amount: toStringValue(loanRequest.requested_amount),
    requested_term: toStringValue(loanRequest.requested_term),
    loan_purpose: loanRequest.loan_purpose ?? '',
    availment_status: loanRequest.availment_status ?? '',
    undertaking_accepted: false,
    applicant: toPersonForm(applicant),
    co_maker_1: toPersonForm(coMakerOne),
    co_maker_2: toPersonForm(coMakerTwo),
    insurance: dataSections.insurance ?? {},
    health: dataSections.health ?? {},
    health_glapi: dataSections.health_glapi ?? {},
    banking: dataSections.banking ?? {},
    declarations: dataSections.declarations ?? {},
    dependents: dataSections.dependents ?? {},
    change_reason: correctionReportContext
        ? `Member reported incorrect details: ${correctionReportContext.issue_description}. Correct information: ${correctionReportContext.correct_information}.`
        : '',
});

const textareaClassName =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex min-h-[112px] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50';

const reportContextFieldTitleClassName =
    'text-[11px] font-semibold tracking-[0.18em] text-muted-foreground uppercase';

const hasTextValue = (value?: string | null): value is string =>
    value !== null && value !== undefined && value.trim() !== '';

const formatReportedByLabel = (
    report: LoanRequestCorrectionReport,
): string | null => {
    const reportedByName = report.reported_by?.name?.trim() ?? '';
    const reportedByAcctNo = report.reported_by?.acctno?.trim() ?? '';

    if (reportedByName === '' && reportedByAcctNo === '') {
        return null;
    }

    if (reportedByName !== '' && reportedByAcctNo !== '') {
        return `${reportedByName} (Acct: ${reportedByAcctNo})`;
    }

    return reportedByName !== '' ? reportedByName : `Acct: ${reportedByAcctNo}`;
};

type ReportContextFieldProps = {
    label: string;
    value: string;
};

const ReportContextField = ({ label, value }: ReportContextFieldProps) => (
    <div className="space-y-1.5">
        <p className={reportContextFieldTitleClassName}>{label}</p>
        <p className="text-sm leading-relaxed break-words whitespace-pre-wrap text-foreground/95">
            {value}
        </p>
    </div>
);

type CorrectionReportContextCardProps = {
    report: LoanRequestCorrectionReport;
    title: string;
    description?: string;
    compact?: boolean;
    showMeta?: boolean;
};

const CorrectionReportContextCard = ({
    report,
    title,
    description,
    compact = false,
    showMeta = true,
}: CorrectionReportContextCardProps) => {
    const supportingNote = hasTextValue(report.supporting_note)
        ? report.supporting_note
        : null;
    const reportedBy = formatReportedByLabel(report);
    const reportedAt = hasTextValue(report.reported_at)
        ? formatDateTime(report.reported_at)
        : null;

    return (
        <section
            className={cn(
                'rounded-2xl border border-amber-500/30 bg-amber-500/[0.08] shadow-[0_18px_44px_-36px_rgba(245,158,11,0.65)] dark:border-amber-400/25 dark:bg-amber-400/[0.08]',
                compact ? 'space-y-3 p-4' : 'space-y-4 p-4 sm:p-5',
            )}
        >
            <div className="flex items-start gap-3">
                <div className="rounded-full bg-amber-500/12 p-2 text-amber-700 dark:text-amber-200">
                    <AlertTriangle className="size-4" />
                </div>
                <div className="min-w-0 space-y-1">
                    <p className="text-sm font-semibold text-foreground">
                        {title}
                    </p>
                    {description ? (
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            {description}
                        </p>
                    ) : null}
                </div>
            </div>

            <div
                className={cn(
                    'grid gap-3',
                    showMeta
                        ? 'lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]'
                        : '',
                )}
            >
                <div className="grid gap-3">
                    <ReportContextField
                        label="Reported issue"
                        value={report.issue_description}
                    />
                    <ReportContextField
                        label="Correct information"
                        value={report.correct_information}
                    />
                    {supportingNote ? (
                        <ReportContextField
                            label="Supporting note/proof"
                            value={supportingNote}
                        />
                    ) : null}
                </div>

                {showMeta && (reportedBy || reportedAt) ? (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                        {reportedBy ? (
                            <ReportContextField
                                label="Reported by"
                                value={reportedBy}
                            />
                        ) : null}
                        {reportedAt ? (
                            <ReportContextField
                                label="Reported at"
                                value={reportedAt}
                            />
                        ) : null}
                    </div>
                ) : null}
            </div>
        </section>
    );
};

const humanizeFieldKey = (key: string): string =>
    key
        .split('_')
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

const isBlank = (value: string | boolean | null | undefined): boolean =>
    `${value ?? ''}`.trim() === '';

const isDigits = (value: string, length: number): boolean =>
    new RegExp(`^\\d{${length}}$`).test(value.trim());

const normalizeComparable = (
    path: string,
    value: string | boolean | null | undefined,
): string => {
    const trimmed = `${value ?? ''}`.trim();

    if (trimmed === '') {
        return '';
    }

    if (numericFieldPaths.has(path)) {
        const numeric = Number(trimmed);

        if (Number.isNaN(numeric)) {
            return trimmed;
        }

        return `${numeric}`;
    }

    return trimmed;
};

const formatChangeValue = (
    path: string,
    value: string | boolean | null | undefined,
    loanTypes: LoanTypeOption[],
): string => {
    const trimmed = `${value ?? ''}`.trim();

    if (trimmed === '') {
        return '--';
    }

    if (path === 'typecode') {
        return (
            loanTypes.find((type) => type.typecode === trimmed)?.label ??
            trimmed
        );
    }

    if (path === 'requested_term') {
        return `${trimmed} months`;
    }

    if (currencyFieldPaths.has(path)) {
        const numeric = Number(trimmed);

        return Number.isNaN(numeric) ? trimmed : formatCurrency(numeric);
    }

    return trimmed;
};

const validateLoanDetails = (data: CorrectionFormData): ValidationErrors => {
    const validationErrors: ValidationErrors = {};

    if (isBlank(data.typecode)) {
        validationErrors.typecode = 'Loan type is required.';
    }

    const requestedAmount = data.requested_amount.trim();

    if (requestedAmount === '') {
        validationErrors.requested_amount = 'Requested amount is required.';
    } else {
        const parsedAmount = Number(requestedAmount);

        if (Number.isNaN(parsedAmount) || parsedAmount < 1) {
            validationErrors.requested_amount =
                'Requested amount must be at least 1.';
        }
    }

    const requestedTerm = data.requested_term.trim();

    if (requestedTerm === '') {
        validationErrors.requested_term = 'Loan term is required.';
    } else {
        const parsedTerm = Number(requestedTerm);

        if (!Number.isInteger(parsedTerm)) {
            validationErrors.requested_term =
                'Loan term must be a whole number.';
        } else if (parsedTerm < 1 || parsedTerm > 360) {
            validationErrors.requested_term =
                'Loan term must be between 1 and 360 months.';
        }
    }

    if (isBlank(data.loan_purpose)) {
        validationErrors.loan_purpose = 'Loan purpose is required.';
    }

    if (isBlank(data.availment_status)) {
        validationErrors.availment_status = 'Availment status is required.';
    } else if (!AVAILMENT_OPTIONS.has(data.availment_status.trim())) {
        validationErrors.availment_status = 'Select a valid availment status.';
    }

    return validationErrors;
};

const validatePerson = (
    prefix: PersonSection,
    person: LoanRequestPersonFormData,
    requiredFields: Array<keyof LoanRequestPersonFormData>,
    options?: {
        validateChildren?: boolean;
        validateSpouse?: boolean;
    },
): ValidationErrors => {
    const validationErrors: ValidationErrors = {};

    requiredFields.forEach((field) => {
        if (isBlank(person[field])) {
            validationErrors[`${prefix}.${field}`] =
                `${personFieldLabels[field]} is required.`;
        }
    });

    if (!isBlank(person.cell_no) && !isDigits(person.cell_no, 11)) {
        validationErrors[`${prefix}.cell_no`] = 'Cell no. must be 11 digits.';
    }

    if (
        !isBlank(person.housing_status) &&
        !HOUSING_STATUS_OPTIONS.has(person.housing_status.trim())
    ) {
        validationErrors[`${prefix}.housing_status`] =
            'Select a valid housing status.';
    }

    if (
        !isBlank(person.civil_status) &&
        !CIVIL_STATUS_OPTIONS.has(person.civil_status.trim())
    ) {
        validationErrors[`${prefix}.civil_status`] =
            'Select a valid civil status.';
    }

    if (!isBlank(person.payday) && !PAYDAY_OPTIONS.has(person.payday.trim())) {
        validationErrors[`${prefix}.payday`] = 'Select a valid payday.';
    }

    if (options?.validateChildren) {
        const childrenValue = person.number_of_children.trim();

        if (childrenValue !== '') {
            const parsedChildren = Number(childrenValue);

            if (!Number.isInteger(parsedChildren) || parsedChildren < 0) {
                validationErrors[`${prefix}.number_of_children`] =
                    'No. of children must be 0 or greater.';
            }
        }
    }

    if (!isBlank(person.gross_monthly_income)) {
        const parsedIncome = Number(person.gross_monthly_income.trim());

        if (Number.isNaN(parsedIncome) || parsedIncome < 0) {
            validationErrors[`${prefix}.gross_monthly_income`] =
                'Gross monthly income must be 0 or greater.';
        }
    }

    if (options?.validateSpouse) {
        if (!isBlank(person.spouse_age)) {
            const parsedSpouseAge = Number(person.spouse_age.trim());

            if (!Number.isInteger(parsedSpouseAge)) {
                validationErrors[`${prefix}.spouse_age`] =
                    'Spouse age must be a whole number.';
            } else if (parsedSpouseAge < 18 || parsedSpouseAge > 120) {
                validationErrors[`${prefix}.spouse_age`] =
                    'Spouse age must be between 18 and 120.';
            }
        }

        if (
            !isBlank(person.spouse_cell_no) &&
            !isDigits(person.spouse_cell_no, 11)
        ) {
            validationErrors[`${prefix}.spouse_cell_no`] =
                'Spouse cell no. must be 11 digits.';
        }
    }

    return validationErrors;
};

const validateChangeReason = (reason: string): ValidationErrors => {
    const validationErrors: ValidationErrors = {};

    if (reason.trim() === '') {
        validationErrors.change_reason = 'Change reason is required.';
    } else if (reason.trim().length > 1000) {
        validationErrors.change_reason =
            'Change reason must not exceed 1000 characters.';
    }

    return validationErrors;
};

const stepIndexOf = (id: WizardStepId): number =>
    WIZARD_STEPS.findIndex((step) => step.id === id);

const resolveStepFromErrors = (
    validationErrors: ValidationErrors,
): number | null => {
    const matchedSteps: number[] = [];

    Object.keys(validationErrors).forEach((field) => {
        if (validationErrors[field] === undefined) {
            return;
        }

        if (
            field === 'typecode' ||
            field === 'requested_amount' ||
            field === 'requested_term' ||
            field === 'loan_purpose' ||
            field === 'availment_status'
        ) {
            matchedSteps.push(stepIndexOf('loan'));
            return;
        }

        if (field.startsWith('applicant.')) {
            matchedSteps.push(stepIndexOf('applicant'));
            return;
        }

        if (field.startsWith('co_maker_1.')) {
            matchedSteps.push(stepIndexOf('co_maker_1'));
            return;
        }

        if (field.startsWith('co_maker_2.')) {
            matchedSteps.push(stepIndexOf('co_maker_2'));
            return;
        }

        if (field === 'change_reason') {
            matchedSteps.push(stepIndexOf('review'));
        }
    });

    return matchedSteps.length > 0 ? Math.min(...matchedSteps) : null;
};

const STEP_FIELD_KEYS: Partial<Record<WizardStepId, string[]>> = {
    loan: loanStepFieldKeys,
    applicant: applicantStepFieldKeys,
    co_maker_1: coMakerOneStepFieldKeys,
    co_maker_2: coMakerTwoStepFieldKeys,
    review: reviewStepFieldKeys,
};

const getStepFieldKeys = (stepIndex: number): string[] => {
    const stepId = WIZARD_STEPS[stepIndex]?.id;

    return stepId ? (STEP_FIELD_KEYS[stepId] ?? []) : [];
};

const mergeValidationErrors = (
    currentErrors: ValidationErrors,
    nextErrors: ValidationErrors,
    stepFields: string[],
): ValidationErrors => {
    const merged: ValidationErrors = { ...currentErrors };

    stepFields.forEach((field) => {
        delete merged[field];
    });

    Object.entries(nextErrors).forEach(([field, message]) => {
        if (stepFields.includes(field)) {
            merged[field] = message;
        }
    });

    return merged;
};

const buildPersonChangeEntries = (
    section: PersonSection,
    before: LoanRequestPersonFormData,
    after: LoanRequestPersonFormData,
    fields: Array<keyof LoanRequestPersonFormData>,
    loanTypes: LoanTypeOption[],
): ChangeEntry[] => {
    return fields.reduce<ChangeEntry[]>((changes, field) => {
        const path = `${section}.${field}`;
        const beforeValue = before[field];
        const afterValue = after[field];

        if (
            normalizeComparable(path, beforeValue) ===
            normalizeComparable(path, afterValue)
        ) {
            return changes;
        }

        changes.push({
            field: path,
            label: personFieldLabels[field],
            before: formatChangeValue(path, beforeValue, loanTypes),
            after: formatChangeValue(path, afterValue, loanTypes),
        });

        return changes;
    }, []);
};

const validateStepData = (
    stepIndex: number,
    data: CorrectionFormData,
): ValidationErrors => {
    switch (WIZARD_STEPS[stepIndex]?.id) {
        case 'loan':
            return validateLoanDetails(data);
        case 'applicant':
            return validatePerson(
                'applicant',
                data.applicant,
                applicantRequiredFields,
                {
                    validateChildren: true,
                    validateSpouse: true,
                },
            );
        case 'co_maker_1':
            return validatePerson(
                'co_maker_1',
                data.co_maker_1,
                coMakerRequiredFields,
            );
        case 'co_maker_2':
            return validatePerson(
                'co_maker_2',
                data.co_maker_2,
                coMakerRequiredFields,
            );
        case 'review':
            return validateChangeReason(data.change_reason);
        default:
            return {};
    }
};

const validateAllRequiredFields = (
    data: CorrectionFormData,
): ValidationErrors => {
    return {
        ...validateLoanDetails(data),
        ...validatePerson(
            'applicant',
            data.applicant,
            applicantRequiredFields,
            {
                validateChildren: true,
                validateSpouse: true,
            },
        ),
        ...validatePerson('co_maker_1', data.co_maker_1, coMakerRequiredFields),
        ...validatePerson('co_maker_2', data.co_maker_2, coMakerRequiredFields),
        ...validateChangeReason(data.change_reason),
    };
};

export function AdminLoanRequestCorrectionDialog({
    open,
    isProcessing,
    onOpenChange,
    ...formProps
}: Props) {
    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (isProcessing && !nextOpen) {
                    return;
                }

                onOpenChange(nextOpen);
            }}
        >
            {open ? (
                <CorrectionDialogForm
                    key={`${formProps.loanRequest.id}-${formProps.correctionReportContext?.id ?? 'none'}`}
                    {...formProps}
                    isProcessing={isProcessing}
                    onOpenChange={onOpenChange}
                />
            ) : null}
        </Dialog>
    );
}

function CorrectionDialogForm({
    loanRequest,
    applicant,
    coMakerOne,
    coMakerTwo,
    loanTypes,
    dataSections,
    dataSectionDefinitions,
    correctionReportContext,
    errors,
    isProcessing,
    onOpenChange,
    onSubmit,
}: CorrectionDialogFormProps) {
    const [currentStep, setCurrentStep] = useState(0);
    const [stepDirection, setStepDirection] = useState<'forward' | 'backward'>(
        'forward',
    );
    const initialFormData = buildInitialFormData(
        loanRequest,
        applicant,
        coMakerOne,
        coMakerTwo,
        dataSections,
        correctionReportContext,
    );
    const [formData, setFormData] =
        useState<CorrectionFormData>(initialFormData);
    const [clientErrors, setClientErrors] = useState<ValidationErrors>({});

    const availableLoanTypes =
        !loanRequest.typecode ||
        loanTypes.some((type) => type.typecode === loanRequest.typecode)
            ? loanTypes
            : [
                  {
                      typecode: loanRequest.typecode,
                      label:
                          loanRequest.loan_type_label_snapshot ??
                          loanRequest.typecode,
                  },
                  ...loanTypes,
              ];

    const changeGroups: ChangeGroup[] = (() => {
        const loanChanges = (
            Object.keys(loanFieldLabels) as LoanDetailField[]
        ).reduce<ChangeEntry[]>((changes, field) => {
            const beforeValue = initialFormData[field];
            const afterValue = formData[field];

            if (
                normalizeComparable(field, beforeValue) ===
                normalizeComparable(field, afterValue)
            ) {
                return changes;
            }

            changes.push({
                field,
                label: loanFieldLabels[field],
                before: formatChangeValue(
                    field,
                    beforeValue,
                    availableLoanTypes,
                ),
                after: formatChangeValue(field, afterValue, availableLoanTypes),
            });

            return changes;
        }, []);

        const buildDataSectionChanges = (
            sectionKey: string,
            original: LoanRequestDataSectionValues,
            updated: LoanRequestDataSectionValues,
            labelMap: Record<string, string>,
        ): ChangeEntry[] => {
            const changes: ChangeEntry[] = [];
            const allKeys = new Set([
                ...Object.keys(original),
                ...Object.keys(updated),
            ]);

            allKeys.forEach((key) => {
                const before = original[key];
                const after = updated[key];

                if (before !== after) {
                    changes.push({
                        field: `${sectionKey}.${key}`,
                        label: labelMap[key] ?? humanizeFieldKey(key),
                        before: String(before ?? '(empty)'),
                        after: String(after ?? '(empty)'),
                    });
                }
            });

            return changes;
        };

        const insuranceChanges = buildDataSectionChanges(
            'insurance',
            initialFormData.insurance ?? {},
            formData.insurance,
            dataSectionFieldLabels.insurance,
        );

        const healthChanges = buildDataSectionChanges(
            'health',
            initialFormData.health ?? {},
            formData.health,
            dataSectionFieldLabels.health,
        );

        const healthGlapiChanges = buildDataSectionChanges(
            'health_glapi',
            initialFormData.health_glapi ?? {},
            formData.health_glapi,
            dataSectionFieldLabels.health_glapi,
        );

        const bankingChanges = buildDataSectionChanges(
            'banking',
            initialFormData.banking ?? {},
            formData.banking,
            dataSectionFieldLabels.banking,
        );

        const dependentsChanges = buildDataSectionChanges(
            'dependents',
            initialFormData.dependents ?? {},
            formData.dependents,
            dataSectionFieldLabels.dependents ?? {},
        );

        return [
            {
                id: 'loan',
                title: 'Loan details',
                description: 'Changes to loan type, amount, term, and purpose.',
                changes: loanChanges,
            },
            {
                id: 'applicant',
                title: 'Applicant',
                description: 'Changes to applicant personal and work details.',
                changes: buildPersonChangeEntries(
                    'applicant',
                    initialFormData.applicant,
                    formData.applicant,
                    applicantChangeFields,
                    availableLoanTypes,
                ),
            },
            {
                id: 'co_maker_1',
                title: 'Co-maker 1',
                description: 'Changes to first co-maker details.',
                changes: buildPersonChangeEntries(
                    'co_maker_1',
                    initialFormData.co_maker_1,
                    formData.co_maker_1,
                    coMakerChangeFields,
                    availableLoanTypes,
                ),
            },
            {
                id: 'co_maker_2',
                title: 'Co-maker 2',
                description: 'Changes to second co-maker details.',
                changes: buildPersonChangeEntries(
                    'co_maker_2',
                    initialFormData.co_maker_2,
                    formData.co_maker_2,
                    coMakerChangeFields,
                    availableLoanTypes,
                ),
            },
            {
                id: 'insurance',
                title: 'Insurance & beneficiaries',
                description: 'Beneficiary details for loan documents.',
                changes: insuranceChanges,
            },
            {
                id: 'dependents',
                title: 'Dependents',
                description: 'Changes to dependents on file.',
                changes: dependentsChanges,
            },
            {
                id: 'health',
                title: 'Health basic info',
                description: 'Smoking status and hypertension.',
                changes: [...healthChanges, ...healthGlapiChanges],
            },
            {
                id: 'banking',
                title: 'Banking & payout',
                description: 'Bank account details.',
                changes: [...bankingChanges],
            },
        ];
    })();

    const totalChanges = changeGroups.reduce(
        (count, group) => count + group.changes.length,
        0,
    );

    const hasChanges = totalChanges > 0;
    const fullValidationErrors = validateAllRequiredFields(formData);
    const canSubmit =
        hasChanges &&
        Object.keys(fullValidationErrors).length === 0 &&
        !isProcessing;
    const isLastStep = currentStep === WIZARD_STEPS.length - 1;
    const stepMeta = WIZARD_STEPS[currentStep];

    const mergedErrors = { ...errors, ...clientErrors };

    // Correction is scoped to just the applicant's own PEP self-attestation --
    // the other ~66 GLAPI health-questionnaire fields are member-only and have
    // no editing surface here, mirroring how the 'health' section above is
    // narrowed to smoking status/hypertension rather than exposing the full
    // health questionnaire.
    const pepDefinition = {
        label: dataSectionDefinitions.health_glapi?.label ?? 'GLAPI',
        fields: {
            applicant_pep_status:
                dataSectionDefinitions.health_glapi?.fields
                    ?.applicant_pep_status,
            applicant_pep_status_details:
                dataSectionDefinitions.health_glapi?.fields
                    ?.applicant_pep_status_details,
        },
    };

    const handleLoanDetailChange = (field: LoanDetailField, value: string) => {
        setFormData((current) => ({
            ...current,
            [field]: value,
        }));
        setClientErrors((current) => {
            if (!current[field]) {
                return current;
            }

            const next = { ...current };
            delete next[field];
            return next;
        });
    };

    const updatePersonField =
        (section: PersonSection) =>
        (field: keyof LoanRequestPersonFormData, value: string) => {
            const key = `${section}.${field}`;
            setFormData((current) => ({
                ...current,
                [section]: {
                    ...current[section],
                    [field]: value,
                },
            }));
            setClientErrors((current) => {
                if (!current[key]) {
                    return current;
                }

                const next = { ...current };
                delete next[key];
                return next;
            });
        };

    const updateDataSection =
        (
            sectionKey:
                | 'insurance'
                | 'health'
                | 'health_glapi'
                | 'banking'
                | 'dependents',
        ) =>
        (field: string, value: string | number | boolean | null) => {
            const key = `${sectionKey}.${field}`;
            setFormData((current) => ({
                ...current,
                [sectionKey]: {
                    ...current[sectionKey],
                    [field]: value,
                },
            }));
            setClientErrors((current) => {
                if (!current[key]) {
                    return current;
                }

                const next = { ...current };
                delete next[key];
                return next;
            });
        };

    const moveToStep = (nextStep: number) => {
        setCurrentStep((current) => {
            if (nextStep === current) {
                return current;
            }

            setStepDirection(nextStep > current ? 'forward' : 'backward');
            return nextStep;
        });
    };

    const handleStepValidation = (stepIndex: number): boolean => {
        const nextStepErrors = validateStepData(stepIndex, formData);
        const stepFieldKeys = getStepFieldKeys(stepIndex);

        setClientErrors((current) =>
            mergeValidationErrors(current, nextStepErrors, stepFieldKeys),
        );

        return Object.keys(nextStepErrors).length === 0;
    };

    const handleNextStep = () => {
        if (!handleStepValidation(currentStep)) {
            return;
        }

        moveToStep(Math.min(WIZARD_STEPS.length - 1, currentStep + 1));
    };

    const handlePreviousStep = () => {
        moveToStep(Math.max(0, currentStep - 1));
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const validationErrors = validateAllRequiredFields(formData);
        setClientErrors(validationErrors);

        if (!hasChanges) {
            moveToStep(WIZARD_STEPS.length - 1);
            return;
        }

        if (Object.keys(validationErrors).length > 0) {
            const firstErrorStep = resolveStepFromErrors(validationErrors);

            if (firstErrorStep !== null) {
                moveToStep(firstErrorStep);
            }

            return;
        }

        onSubmit({
            typecode: formData.typecode,
            requested_amount: formData.requested_amount,
            requested_term: formData.requested_term,
            loan_purpose: formData.loan_purpose,
            availment_status: formData.availment_status,
            applicant: formData.applicant,
            co_maker_1: formData.co_maker_1,
            co_maker_2: formData.co_maker_2,
            insurance: formData.insurance,
            dependents: formData.dependents,
            health: formData.health,
            health_glapi: {
                applicant_pep_status:
                    formData.health_glapi.applicant_pep_status,
                applicant_pep_status_details:
                    formData.health_glapi.applicant_pep_status_details,
            },
            banking: formData.banking,
            change_reason: formData.change_reason.trim(),
        });
    };

    return (
        <DialogContent className="grid max-h-[calc(100vh-2rem)] grid-rows-[auto_minmax(0,1fr)] overflow-hidden border-border/60 bg-card/95 p-0 shadow-2xl backdrop-blur-sm sm:max-w-6xl">
            <DialogHeader>
                <div className="space-y-6 border-b border-border/40 px-6 pt-6 pb-5 sm:px-7">
                    <div className="space-y-2">
                        <DialogTitle className="text-2xl font-semibold tracking-tight">
                            Edit request details
                        </DialogTitle>
                        <DialogDescription className="max-w-3xl text-sm leading-relaxed">
                            Follow each step to correct applicant and co-maker
                            details while the request is under review. Approval
                            and decision actions remain unchanged.
                        </DialogDescription>
                    </div>

                    <Alert className="border-border/50 bg-muted/10">
                        <Info className="size-4" />
                        <AlertTitle>What you can correct here</AlertTitle>
                        <AlertDescription>
                            This covers loan terms, applicant/co-maker profiles,
                            insurance beneficiaries, basic health info, banking,
                            and dependents. The full health questionnaire and
                            the member&apos;s declarations are the member&apos;s
                            own sworn statements — they can only be corrected by
                            the member directly, not here.
                        </AlertDescription>
                    </Alert>

                    {correctionReportContext ? (
                        <CorrectionReportContextCard
                            report={correctionReportContext}
                            title="Member reported incorrect details"
                            description="Keep the member's reported issue visible while updating the copied request."
                        />
                    ) : null}
                </div>
            </DialogHeader>

            <form
                className="grid min-h-0 grid-rows-[minmax(0,1fr)_auto] gap-0"
                onSubmit={handleSubmit}
            >
                <div className="flex min-h-0 flex-1">
                    <LoanRequestWizardShell
                        currentStep={currentStep}
                        onStepClick={moveToStep}
                        steps={WIZARD_STEPS}
                        groupMeta={WIZARD_STEP_GROUP_META}
                        contentClassName="overflow-y-auto px-6 pt-5 pb-28 sm:px-7"
                    >
                        <div className="mb-5 space-y-1">
                            <p className="text-sm font-semibold text-foreground">
                                {stepMeta.title}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {stepMeta.description}
                            </p>
                        </div>

                        <LoanRequestAnimatedStep
                            show={currentStep === 0}
                            direction={stepDirection}
                        >
                            <div className="space-y-5">
                                <LoanRequestLoanDetailsStep
                                    data={formData}
                                    errors={mergedErrors}
                                    loanTypes={availableLoanTypes}
                                    onChange={handleLoanDetailChange}
                                />
                            </div>
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={currentStep === 1}
                            direction={stepDirection}
                        >
                            <div className="space-y-5">
                                <LoanRequestSectionCard title="Applicant personal data">
                                    <LoanRequestPersonalFields
                                        prefix="applicant"
                                        values={formData.applicant}
                                        errors={mergedErrors}
                                        includeSpouse
                                        includeChildren
                                        onChange={updatePersonField(
                                            'applicant',
                                        )}
                                    />
                                </LoanRequestSectionCard>
                                <LoanRequestSectionCard
                                    title="Applicant work & finances"
                                    description={
                                        isBlank(
                                            formData.applicant
                                                .employer_date_employed,
                                        )
                                            ? 'Date employed is optional — leave it blank if unknown for this request.'
                                            : undefined
                                    }
                                >
                                    <LoanRequestWorkFields
                                        prefix="applicant"
                                        values={formData.applicant}
                                        errors={mergedErrors}
                                        onChange={updatePersonField(
                                            'applicant',
                                        )}
                                    />
                                </LoanRequestSectionCard>
                            </div>
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={currentStep === 2}
                            direction={stepDirection}
                        >
                            <div className="space-y-5">
                                <LoanRequestSectionCard title="Co-maker 1">
                                    <LoanRequestPersonalFields
                                        prefix="co_maker_1"
                                        values={formData.co_maker_1}
                                        errors={mergedErrors}
                                        onChange={updatePersonField(
                                            'co_maker_1',
                                        )}
                                    />
                                    <Separator className="bg-border/40" />
                                    <LoanRequestWorkFields
                                        prefix="co_maker_1"
                                        values={formData.co_maker_1}
                                        errors={mergedErrors}
                                        onChange={updatePersonField(
                                            'co_maker_1',
                                        )}
                                    />
                                </LoanRequestSectionCard>
                            </div>
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={currentStep === 3}
                            direction={stepDirection}
                        >
                            <div className="space-y-5">
                                <LoanRequestSectionCard title="Co-maker 2">
                                    <LoanRequestPersonalFields
                                        prefix="co_maker_2"
                                        values={formData.co_maker_2}
                                        errors={mergedErrors}
                                        onChange={updatePersonField(
                                            'co_maker_2',
                                        )}
                                    />
                                    <Separator className="bg-border/40" />
                                    <LoanRequestWorkFields
                                        prefix="co_maker_2"
                                        values={formData.co_maker_2}
                                        errors={mergedErrors}
                                        onChange={updatePersonField(
                                            'co_maker_2',
                                        )}
                                    />
                                </LoanRequestSectionCard>
                            </div>
                        </LoanRequestAnimatedStep>

                        {/* Insurance & beneficiaries */}
                        <LoanRequestAnimatedStep
                            show={currentStep === stepIndexOf('insurance')}
                            direction={stepDirection}
                        >
                            <div className="space-y-5">
                                <LoanRequestInsuranceBeneficiariesStep
                                    sectionKey="insurance"
                                    title="Insurance and beneficiaries"
                                    description="Review beneficiary details -- who receives the insurance payout -- used in loan documents."
                                    values={formData.insurance}
                                    definition={
                                        dataSectionDefinitions.insurance
                                    }
                                    errors={mergedErrors}
                                    onChange={updateDataSection('insurance')}
                                />
                            </div>
                        </LoanRequestAnimatedStep>

                        {/* Dependents */}
                        <LoanRequestAnimatedStep
                            show={currentStep === stepIndexOf('dependents')}
                            direction={stepDirection}
                        >
                            <div className="space-y-5">
                                <LoanRequestDependentsStep
                                    sectionKey="dependents"
                                    title="Dependents"
                                    description="Review dependents covered under the group life insurance plan -- separate from the beneficiaries above."
                                    values={formData.dependents}
                                    definition={
                                        dataSectionDefinitions.dependents
                                    }
                                    errors={mergedErrors}
                                    crossSectionValues={{
                                        'applicant.civil_status':
                                            formData.applicant.civil_status,
                                    }}
                                    onChange={updateDataSection('dependents')}
                                    hasExistingProfileData={false}
                                />
                            </div>
                        </LoanRequestAnimatedStep>

                        {/* Health basic info */}
                        <LoanRequestAnimatedStep
                            show={currentStep === stepIndexOf('health')}
                            direction={stepDirection}
                        >
                            <div className="space-y-5">
                                <LoanRequestDataSectionStep
                                    sectionKey="health"
                                    title="Health basic information"
                                    description="Review smoking status and hypertension declaration. Full health questionnaire is completed by the member during application."
                                    values={formData.health}
                                    definition={dataSectionDefinitions.health}
                                    errors={mergedErrors}
                                    onChange={updateDataSection('health')}
                                />
                                <LoanRequestDataSectionStep
                                    sectionKey="health_glapi"
                                    title="Politically Exposed Person (PEP) status"
                                    description="Applicant's self-attested PEP status for the Generali Individual Application Form."
                                    values={formData.health_glapi}
                                    definition={pepDefinition}
                                    errors={mergedErrors}
                                    onChange={updateDataSection('health_glapi')}
                                />
                            </div>
                        </LoanRequestAnimatedStep>

                        {/* Loan Disbursement & Repayment */}
                        <LoanRequestAnimatedStep
                            show={currentStep === stepIndexOf('banking')}
                            direction={stepDirection}
                        >
                            <div className="space-y-5">
                                <LoanRequestDataSectionStep
                                    sectionKey="banking"
                                    title="Loan Disbursement & Repayment"
                                    description="Review disbursement and repayment method details."
                                    values={formData.banking}
                                    definition={dataSectionDefinitions.banking}
                                    errors={mergedErrors}
                                    onChange={updateDataSection('banking')}
                                    applicantFullName={`${applicant?.first_name ?? ''} ${applicant?.last_name ?? ''}`.trim()}
                                />
                            </div>
                        </LoanRequestAnimatedStep>

                        {/* Review changes & reason */}
                        <LoanRequestAnimatedStep
                            show={currentStep === stepIndexOf('review')}
                            direction={stepDirection}
                        >
                            <div className="space-y-5">
                                {!hasChanges ? (
                                    <Alert className="border-amber-500/35 bg-amber-500/10 text-foreground">
                                        <AlertTriangle className="h-4 w-4 text-amber-700 dark:text-amber-200" />
                                        <AlertTitle>
                                            No changes detected.
                                        </AlertTitle>
                                        <AlertDescription>
                                            No changes detected. Update at least
                                            one field before saving a
                                            correction.
                                        </AlertDescription>
                                    </Alert>
                                ) : null}

                                {correctionReportContext ? (
                                    <CorrectionReportContextCard
                                        report={correctionReportContext}
                                        title="Reported correction context"
                                        compact
                                        showMeta={false}
                                    />
                                ) : null}

                                <LoanRequestSectionCard
                                    title="Review changes"
                                    description="Only modified fields are listed below."
                                >
                                    <div className="space-y-4">
                                        {changeGroups.map((group) => (
                                            <section
                                                key={group.id}
                                                className="rounded-2xl border border-border/45 bg-muted/10 p-4"
                                            >
                                                <div className="space-y-1">
                                                    <h3 className="text-sm font-semibold text-foreground">
                                                        {group.title}
                                                    </h3>
                                                    <p className="text-xs text-muted-foreground">
                                                        {group.description}
                                                    </p>
                                                </div>

                                                {group.changes.length === 0 ? (
                                                    <p className="mt-3 text-xs text-muted-foreground">
                                                        No changes in this
                                                        section.
                                                    </p>
                                                ) : (
                                                    <div className="mt-4 space-y-3">
                                                        {group.changes.map(
                                                            (change) => (
                                                                <div
                                                                    key={
                                                                        change.field
                                                                    }
                                                                    className="rounded-xl border border-border/45 bg-card/70 p-3"
                                                                >
                                                                    <p className="text-xs font-semibold text-foreground">
                                                                        {
                                                                            change.label
                                                                        }
                                                                    </p>
                                                                    <div className="mt-2 grid gap-2 text-xs sm:grid-cols-2">
                                                                        <div className="rounded-lg border border-border/40 bg-muted/20 p-2">
                                                                            <p className="text-[10px] font-medium tracking-[0.14em] text-muted-foreground uppercase">
                                                                                Before
                                                                            </p>
                                                                            <p className="mt-1 break-words text-foreground/90">
                                                                                {
                                                                                    change.before
                                                                                }
                                                                            </p>
                                                                        </div>
                                                                        <div className="rounded-lg border border-primary/30 bg-primary/10 p-2">
                                                                            <p className="text-[10px] font-medium tracking-[0.14em] text-primary uppercase">
                                                                                After
                                                                            </p>
                                                                            <p className="mt-1 break-words text-foreground">
                                                                                {
                                                                                    change.after
                                                                                }
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                            </section>
                                        ))}
                                    </div>
                                </LoanRequestSectionCard>

                                <LoanRequestSectionCard
                                    title="Member declarations (read-only)"
                                    description="These are the member's legal attestations from their original submission. Contact the member if corrections are needed."
                                >
                                    <div className="space-y-3 rounded-lg border border-amber-500/30 bg-amber-500/5 p-4">
                                        <div className="flex items-start gap-2">
                                            <AlertTriangle className="mt-0.5 h-4 w-4 text-amber-600 dark:text-amber-400" />
                                            <p className="text-xs text-muted-foreground">
                                                Member declarations cannot be
                                                modified by staff. These
                                                represent the member's sworn
                                                statements.
                                            </p>
                                        </div>

                                        <div className="mt-4 space-y-2 text-sm">
                                            <div className="flex items-center justify-between">
                                                <span>
                                                    Existing loans declared:
                                                </span>
                                                <span className="font-medium">
                                                    {formData.declarations
                                                        .declaration_existing_loans
                                                        ? 'Yes'
                                                        : 'No'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span>
                                                    Pending cases declared:
                                                </span>
                                                <span className="font-medium">
                                                    {formData.declarations
                                                        .declaration_pending_cases
                                                        ? 'Yes'
                                                        : 'No'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span>Truth confirmation:</span>
                                                <span className="font-medium">
                                                    {formData.declarations
                                                        .declaration_truth_confirmation
                                                        ? 'Confirmed'
                                                        : 'Not confirmed'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span>
                                                    Data privacy consent:
                                                </span>
                                                <span className="font-medium">
                                                    {formData.declarations
                                                        .declaration_data_privacy_consent
                                                        ? 'Consented'
                                                        : 'Not consented'}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </LoanRequestSectionCard>

                                <LoanRequestSectionCard
                                    title="Audit reason"
                                    description="Explain why this correction is needed."
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor="change_reason">
                                            Change reason
                                        </Label>
                                        <textarea
                                            id="change_reason"
                                            className={textareaClassName}
                                            value={formData.change_reason}
                                            maxLength={1000}
                                            required
                                            disabled={isProcessing}
                                            placeholder="Explain why this correction is needed."
                                            onChange={(event) => {
                                                const value =
                                                    event.target.value;

                                                setFormData((current) => ({
                                                    ...current,
                                                    change_reason: value,
                                                }));
                                                setClientErrors((current) => {
                                                    if (
                                                        !current.change_reason
                                                    ) {
                                                        return current;
                                                    }

                                                    const next = { ...current };
                                                    delete next.change_reason;
                                                    return next;
                                                });
                                            }}
                                        />
                                        <div className="flex items-start justify-between gap-3">
                                            <InputError
                                                message={
                                                    mergedErrors.change_reason
                                                }
                                            />
                                            <span className="ml-auto text-xs text-muted-foreground">
                                                {formData.change_reason.length}
                                                /1000
                                            </span>
                                        </div>
                                    </div>
                                </LoanRequestSectionCard>
                            </div>
                        </LoanRequestAnimatedStep>
                    </LoanRequestWizardShell>
                </div>

                <div className="sticky bottom-0 z-20 border-t border-border/60 bg-background/90 px-6 py-4 shadow-[0_-12px_24px_-24px_rgba(0,0,0,0.35)] backdrop-blur supports-[backdrop-filter]:bg-background/70 sm:px-7 sm:py-5">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={isProcessing}
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>

                        <div className="flex flex-wrap items-center justify-end gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                disabled={currentStep === 0 || isProcessing}
                                onClick={handlePreviousStep}
                            >
                                Back
                            </Button>
                            {isLastStep ? (
                                <Button type="submit" disabled={!canSubmit}>
                                    <Save />
                                    Save correction
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    disabled={isProcessing}
                                    onClick={handleNextStep}
                                >
                                    {currentStep === 3
                                        ? 'Review changes'
                                        : 'Next'}
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            </form>
        </DialogContent>
    );
}
