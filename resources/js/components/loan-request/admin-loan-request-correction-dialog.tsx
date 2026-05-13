import { AlertTriangle, Check, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { LoanRequestAnimatedStep } from '@/components/loan-request/loan-request-animated-step';
import {
    LoanRequestPersonalFields,
    LoanRequestWorkFields,
} from '@/components/loan-request/loan-request-fields';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import { LoanRequestLoanDetailsStep } from '@/components/loan-request/loan-request-steps';
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
import { formatCurrency, toDateInputValue } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type {
    LoanRequestCorrectionPayload,
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
    | 'review';

type Props = {
    open: boolean;
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    loanTypes: LoanTypeOption[];
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

const WIZARD_STEPS: Array<{
    id: WizardStepId;
    title: string;
    description: string;
}> = [
    {
        id: 'loan',
        title: 'Loan details',
        description: 'Update loan type, amount, term, and purpose.',
    },
    {
        id: 'applicant',
        title: 'Applicant details',
        description: 'Review applicant personal, work, and income data.',
    },
    {
        id: 'co_maker_1',
        title: 'Co-maker 1',
        description: 'Verify first co-maker information.',
    },
    {
        id: 'co_maker_2',
        title: 'Co-maker 2',
        description: 'Verify second co-maker information.',
    },
    {
        id: 'review',
        title: 'Review changes & reason',
        description:
            'Confirm all corrections and provide a reason for the audit trail.',
    },
];

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
    address2: 'City/Municipality',
    address3: 'Province',
    length_of_stay: 'Length of stay',
    housing_status: 'Housing status',
    cell_no: 'Cell no.',
    civil_status: 'Civil status',
    educational_attainment: 'Educational attainment',
    number_of_children: 'No. of children',
    spouse_name: 'Spouse name',
    spouse_age: 'Spouse age',
    spouse_cell_no: 'Spouse cell no.',
    employment_type: 'Employment',
    employer_business_name: 'Employer/Business name',
    employer_business_address1: 'Employer/Business address (street)',
    employer_business_address2: 'Employer/Business city/municipality',
    employer_business_address3: 'Employer/Business province',
    telephone_no: 'Tel. no.',
    current_position: 'Current position',
    nature_of_business: 'Nature of business',
    years_in_work_business: 'Total years in work/business',
    gross_monthly_income: 'Gross monthly income',
    payday: 'Payday',
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
    'housing_status',
    'cell_no',
    'civil_status',
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
    'housing_status',
    'cell_no',
    'civil_status',
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
    address2: '',
    address3: '',
    length_of_stay: '',
    housing_status: '',
    cell_no: '',
    civil_status: '',
    educational_attainment: '',
    number_of_children: '',
    spouse_name: '',
    spouse_age: '',
    spouse_cell_no: '',
    employment_type: '',
    employer_business_name: '',
    employer_business_address1: '',
    employer_business_address2: '',
    employer_business_address3: '',
    telephone_no: '',
    current_position: '',
    nature_of_business: '',
    years_in_work_business: '',
    gross_monthly_income: '',
    payday: '',
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
        telephone_no: person.telephone_no ?? '',
        current_position: person.current_position ?? '',
        nature_of_business: person.nature_of_business ?? '',
        years_in_work_business: person.years_in_work_business ?? '',
        gross_monthly_income: toStringValue(person.gross_monthly_income),
        payday: person.payday ?? '',
    };
};

const buildInitialFormData = (
    loanRequest: LoanRequestDetail,
    applicant: LoanRequestPersonData | null,
    coMakerOne: LoanRequestPersonData | null,
    coMakerTwo: LoanRequestPersonData | null,
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
    change_reason: '',
});

const textareaClassName =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex min-h-[112px] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50';

const isBlank = (value: string): boolean => value.trim() === '';

const isDigits = (value: string, length: number): boolean =>
    new RegExp(`^\\d{${length}}$`).test(value.trim());

const normalizeComparable = (path: string, value: string): string => {
    const trimmed = value.trim();

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
    value: string,
    loanTypes: LoanTypeOption[],
): string => {
    const trimmed = value.trim();

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

const validateLoanDetails = (
    data: CorrectionFormData,
): ValidationErrors => {
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
            matchedSteps.push(0);
            return;
        }

        if (field.startsWith('applicant.')) {
            matchedSteps.push(1);
            return;
        }

        if (field.startsWith('co_maker_1.')) {
            matchedSteps.push(2);
            return;
        }

        if (field.startsWith('co_maker_2.')) {
            matchedSteps.push(3);
            return;
        }

        if (field === 'change_reason') {
            matchedSteps.push(4);
        }
    });

    return matchedSteps.length > 0 ? Math.min(...matchedSteps) : null;
};

const getStepFieldKeys = (stepIndex: number): string[] => {
    switch (stepIndex) {
        case 0:
            return loanStepFieldKeys;
        case 1:
            return applicantStepFieldKeys;
        case 2:
            return coMakerOneStepFieldKeys;
        case 3:
            return coMakerTwoStepFieldKeys;
        case 4:
            return reviewStepFieldKeys;
        default:
            return [];
    }
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
    switch (stepIndex) {
        case 0:
            return validateLoanDetails(data);
        case 1:
            return validatePerson(
                'applicant',
                data.applicant,
                applicantRequiredFields,
                {
                    validateChildren: true,
                    validateSpouse: true,
                },
            );
        case 2:
            return validatePerson('co_maker_1', data.co_maker_1, coMakerRequiredFields);
        case 3:
            return validatePerson('co_maker_2', data.co_maker_2, coMakerRequiredFields);
        case 4:
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
        ...validatePerson('applicant', data.applicant, applicantRequiredFields, {
            validateChildren: true,
            validateSpouse: true,
        }),
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
                    key={formProps.loanRequest.id}
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
    errors,
    isProcessing,
    onOpenChange,
    onSubmit,
}: CorrectionDialogFormProps) {
    const [currentStep, setCurrentStep] = useState(0);
    const [stepDirection, setStepDirection] = useState<'forward' | 'backward'>(
        'forward',
    );
    const initialFormData = useMemo(
        () => buildInitialFormData(loanRequest, applicant, coMakerOne, coMakerTwo),
        [applicant, coMakerOne, coMakerTwo, loanRequest],
    );
    const [formData, setFormData] = useState<CorrectionFormData>(initialFormData);
    const [clientErrors, setClientErrors] = useState<ValidationErrors>({});

    const availableLoanTypes = useMemo(() => {
        if (
            !loanRequest.typecode ||
            loanTypes.some((type) => type.typecode === loanRequest.typecode)
        ) {
            return loanTypes;
        }

        return [
            {
                typecode: loanRequest.typecode,
                label:
                    loanRequest.loan_type_label_snapshot ??
                    loanRequest.typecode,
            },
            ...loanTypes,
        ];
    }, [loanRequest, loanTypes]);

    const changeGroups = useMemo<ChangeGroup[]>(() => {
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
                before: formatChangeValue(field, beforeValue, availableLoanTypes),
                after: formatChangeValue(field, afterValue, availableLoanTypes),
            });

            return changes;
        }, []);

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
        ];
    }, [availableLoanTypes, formData, initialFormData]);

    const totalChanges = useMemo(
        () =>
            changeGroups.reduce(
                (count, group) => count + group.changes.length,
                0,
            ),
        [changeGroups],
    );

    const hasChanges = totalChanges > 0;
    const fullValidationErrors = useMemo(
        () => validateAllRequiredFields(formData),
        [formData],
    );
    const canSubmit =
        hasChanges &&
        Object.keys(fullValidationErrors).length === 0 &&
        !isProcessing;
    const isLastStep = currentStep === WIZARD_STEPS.length - 1;
    const stepMeta = WIZARD_STEPS[currentStep];

    const mergedErrors = useMemo(
        () => ({ ...errors, ...clientErrors }),
        [clientErrors, errors],
    );

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
            moveToStep(4);
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
            change_reason: formData.change_reason.trim(),
        });
    };

    return (
        <DialogContent className="grid max-h-[calc(100vh-2rem)] grid-rows-[auto_minmax(0,1fr)] overflow-hidden border-border/60 bg-card/95 p-0 shadow-2xl backdrop-blur-sm sm:max-w-5xl">
            <DialogHeader>
                <div className="space-y-6 border-b border-border/40 px-6 pb-5 pt-6 sm:px-7">
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

                    <div className="rounded-2xl border border-border/40 bg-muted/15 p-4 sm:p-5">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <p className="text-sm font-semibold text-foreground">
                                {stepMeta.title}
                            </p>
                            <span className="text-xs font-medium text-muted-foreground">
                                Step {currentStep + 1} of {WIZARD_STEPS.length}
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {stepMeta.description}
                        </p>

                        <ol
                            className="mt-4 grid gap-2 sm:grid-cols-5"
                            aria-label="Correction steps"
                        >
                            {WIZARD_STEPS.map((step, index) => {
                                const isCurrent = index === currentStep;
                                const isCompleted = index < currentStep;
                                const isUpcoming = index > currentStep;
                                const canNavigate = index <= currentStep;

                                return (
                                    <li key={step.id}>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                canNavigate
                                                    ? moveToStep(index)
                                                    : undefined
                                            }
                                            disabled={!canNavigate}
                                            aria-current={
                                                isCurrent ? 'step' : undefined
                                            }
                                            className={cn(
                                                'flex w-full items-center gap-3 rounded-xl border px-3 py-2 text-left transition-colors focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none',
                                                isCompleted
                                                    ? 'border-primary/35 bg-primary/10'
                                                    : isCurrent
                                                      ? 'border-primary/45 bg-card shadow-sm'
                                                      : 'border-border/35 bg-muted/10',
                                                canNavigate
                                                    ? 'cursor-pointer'
                                                    : 'cursor-default opacity-80',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold',
                                                    isCompleted
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : isCurrent
                                                          ? 'border-primary/60 text-primary'
                                                          : 'border-border/50 text-muted-foreground',
                                                )}
                                            >
                                                {isCompleted ? (
                                                    <Check className="h-4 w-4" />
                                                ) : (
                                                    index + 1
                                                )}
                                            </span>
                                            <span className="min-w-0">
                                                <span
                                                    className={cn(
                                                        'block truncate text-xs font-medium',
                                                        isCurrent
                                                            ? 'text-foreground'
                                                            : isUpcoming
                                                              ? 'text-muted-foreground'
                                                              : 'text-foreground/80',
                                                    )}
                                                >
                                                    {step.title}
                                                </span>
                                            </span>
                                        </button>
                                    </li>
                                );
                            })}
                        </ol>
                    </div>
                </div>
            </DialogHeader>

            <form
                className="grid min-h-0 grid-rows-[minmax(0,1fr)_auto] gap-0"
                onSubmit={handleSubmit}
            >
                <div className="min-h-0 overflow-y-auto px-6 pb-28 pt-5 sm:px-7">
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
                                    onChange={updatePersonField('applicant')}
                                />
                            </LoanRequestSectionCard>
                            <LoanRequestSectionCard title="Applicant work & finances">
                                <LoanRequestWorkFields
                                    prefix="applicant"
                                    values={formData.applicant}
                                    errors={mergedErrors}
                                    onChange={updatePersonField('applicant')}
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
                                    onChange={updatePersonField('co_maker_1')}
                                />
                                <Separator className="bg-border/40" />
                                <LoanRequestWorkFields
                                    prefix="co_maker_1"
                                    values={formData.co_maker_1}
                                    errors={mergedErrors}
                                    onChange={updatePersonField('co_maker_1')}
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
                                    onChange={updatePersonField('co_maker_2')}
                                />
                                <Separator className="bg-border/40" />
                                <LoanRequestWorkFields
                                    prefix="co_maker_2"
                                    values={formData.co_maker_2}
                                    errors={mergedErrors}
                                    onChange={updatePersonField('co_maker_2')}
                                />
                            </LoanRequestSectionCard>
                        </div>
                    </LoanRequestAnimatedStep>

                    <LoanRequestAnimatedStep
                        show={currentStep === 4}
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
                                        No changes detected. Update at least one
                                        field before saving a correction.
                                    </AlertDescription>
                                </Alert>
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
                                                    No changes in this section.
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
                                            const value = event.target.value;

                                            setFormData((current) => ({
                                                ...current,
                                                change_reason: value,
                                            }));
                                            setClientErrors((current) => {
                                                if (!current.change_reason) {
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
                                            message={mergedErrors.change_reason}
                                        />
                                        <span className="ml-auto text-xs text-muted-foreground">
                                            {formData.change_reason.length}/1000
                                        </span>
                                    </div>
                                </div>
                            </LoanRequestSectionCard>
                        </div>
                    </LoanRequestAnimatedStep>
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
