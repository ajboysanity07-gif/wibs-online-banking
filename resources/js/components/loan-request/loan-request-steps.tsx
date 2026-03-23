import type { ReactNode } from 'react';
import { NumericFormat } from 'react-number-format';
import InputError from '@/components/input-error';
import {
    LoanRequestPersonalFields,
    LoanRequestWorkFields,
} from '@/components/loan-request/loan-request-fields';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { formatCurrency } from '@/lib/formatters';
import type {
    LoanRequestFormData,
    LoanRequestMemberSummary,
    LoanRequestPersonFormData,
    LoanRequestReadOnlyMap,
    LoanTypeOption,
} from '@/types/loan-requests';

const AVAILMENT_OPTIONS = ['New', 'Re-Loan', 'Restructured'] as const;

type LoanDetailField =
    | 'typecode'
    | 'requested_amount'
    | 'requested_term'
    | 'loan_purpose'
    | 'availment_status';

type LoanDetailsProps = {
    data: LoanRequestFormData;
    errors: Record<string, string | undefined>;
    loanTypes: LoanTypeOption[];
    onChange: (field: LoanDetailField, value: string) => void;
};

export function LoanRequestLoanDetailsStep({
    data,
    errors,
    loanTypes,
    onChange,
}: LoanDetailsProps) {
    return (
        <LoanRequestSectionCard
            title="Loan details"
            description="Select your preferred loan type and request details."
        >
            <div className="grid gap-4 md:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="loan_type">Loan type</Label>
                    <Select
                        value={data.typecode || undefined}
                        onValueChange={(value) => onChange('typecode', value)}
                    >
                        <SelectTrigger id="loan_type" className="mt-1 w-full">
                            <SelectValue placeholder="Select loan type" />
                        </SelectTrigger>
                        <SelectContent>
                            {loanTypes.map((option) => (
                                <SelectItem
                                    key={option.typecode}
                                    value={option.typecode}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.typecode} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="requested_amount">Requested amount</Label>
                    <div className="relative">
                        <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-muted-foreground">
                            PHP
                        </span>
                        <NumericFormat
                            id="requested_amount"
                            className="mt-1 block w-full pl-12"
                            value={data.requested_amount}
                            onValueChange={(values) => {
                                onChange('requested_amount', values.value);
                            }}
                            thousandSeparator
                            decimalScale={2}
                            fixedDecimalScale
                            allowNegative={false}
                            placeholder="0.00"
                            inputMode="decimal"
                            valueIsNumericString
                            customInput={Input}
                        />
                    </div>
                    <InputError message={errors.requested_amount} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="requested_term">Loan term (months)</Label>
                    <Input
                        id="requested_term"
                        type="number"
                        value={data.requested_term}
                        className="mt-1 block w-full"
                        placeholder="e.g. 12"
                        required
                        onChange={(event) =>
                            onChange('requested_term', event.target.value)
                        }
                    />
                    <InputError message={errors.requested_term} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="availment_status">Availment status</Label>
                    <Select
                        value={data.availment_status || undefined}
                        onValueChange={(value) =>
                            onChange('availment_status', value)
                        }
                    >
                        <SelectTrigger
                            id="availment_status"
                            className="mt-1 w-full"
                        >
                            <SelectValue placeholder="Select status" />
                        </SelectTrigger>
                        <SelectContent>
                            {AVAILMENT_OPTIONS.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.availment_status} />
                </div>

                <div className="grid gap-2 md:col-span-2">
                    <Label htmlFor="loan_purpose">Loan purpose</Label>
                    <Input
                        id="loan_purpose"
                        value={data.loan_purpose}
                        className="mt-1 block w-full"
                        placeholder="Describe your loan purpose"
                        required
                        onChange={(event) =>
                            onChange('loan_purpose', event.target.value)
                        }
                    />
                    <InputError message={errors.loan_purpose} />
                </div>
            </div>
        </LoanRequestSectionCard>
    );
}

type PersonStepProps = {
    values: LoanRequestPersonFormData;
    errors: Record<string, string | undefined>;
    readOnly?: LoanRequestReadOnlyMap | null;
    onChange: (field: keyof LoanRequestPersonFormData, value: string) => void;
};

export function LoanRequestApplicantPersonalStep({
    values,
    errors,
    readOnly,
    onChange,
}: PersonStepProps) {
    return (
        <LoanRequestSectionCard
            title="My personal data"
            description="Confirm your personal details."
        >
            <LoanRequestPersonalFields
                prefix="applicant"
                values={values}
                errors={errors}
                readOnly={readOnly}
                includeSpouse
                includeChildren
                onChange={onChange}
            />
        </LoanRequestSectionCard>
    );
}

export function LoanRequestApplicantWorkStep({
    values,
    errors,
    onChange,
}: PersonStepProps) {
    return (
        <LoanRequestSectionCard
            title="My work & finances"
            description="Share your current employment and income details."
        >
            <LoanRequestWorkFields
                prefix="applicant"
                values={values}
                errors={errors}
                onChange={onChange}
            />
        </LoanRequestSectionCard>
    );
}

type CoMakerStepProps = {
    title: string;
    description: string;
    prefix: string;
    values: LoanRequestPersonFormData;
    errors: Record<string, string | undefined>;
    onChange: (field: keyof LoanRequestPersonFormData, value: string) => void;
};

export function LoanRequestCoMakerStep({
    title,
    description,
    prefix,
    values,
    errors,
    onChange,
}: CoMakerStepProps) {
    return (
        <LoanRequestSectionCard title={title} description={description}>
            <LoanRequestPersonalFields
                prefix={prefix}
                values={values}
                errors={errors}
                onChange={onChange}
            />
            <Separator className="bg-border/40" />
            <LoanRequestWorkFields
                prefix={prefix}
                values={values}
                errors={errors}
                onChange={onChange}
            />
        </LoanRequestSectionCard>
    );
}

type ReviewStepProps = {
    data: LoanRequestFormData;
    loanTypes: LoanTypeOption[];
    member: LoanRequestMemberSummary;
    errors: Record<string, string | undefined>;
    onUndertakingChange: (value: boolean) => void;
};

type SummaryItem = {
    label: string;
    value: string;
};

const displayValue = (value: string): string =>
    value.trim() !== '' ? value : '--';

const formatHousingStatus = (value: string): string => {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '--';
    }

    const upper = trimmed.toUpperCase();

    if (upper === 'OWNED') {
        return 'Owned';
    }

    if (upper === 'RENT' || upper === 'RENTAL') {
        return 'Rent';
    }

    return trimmed;
};

const displayName = (person: LoanRequestPersonFormData): string => {
    const name = [
        person.first_name,
        person.middle_name,
        person.last_name,
    ]
        .map((value) => value.trim())
        .filter(Boolean)
        .join(' ');

    return name !== '' ? name : '--';
};

const SummaryGrid = ({ items }: { items: SummaryItem[] }) => (
    <div className="grid gap-3 sm:grid-cols-2">
        {items.map((item) => (
            <div key={item.label} className="space-y-1">
                <p className="text-xs text-muted-foreground">{item.label}</p>
                <p className="text-sm font-medium break-words">
                    {item.value}
                </p>
            </div>
        ))}
    </div>
);

type SummaryCardProps = {
    title: string;
    description?: string;
    children: ReactNode;
};

const SummaryCard = ({ title, description, children }: SummaryCardProps) => (
    <div className="rounded-lg border border-border/50 bg-card/60 p-4">
        <div className="space-y-1">
            <h3 className="text-sm font-semibold">{title}</h3>
            {description ? (
                <p className="text-xs text-muted-foreground">
                    {description}
                </p>
            ) : null}
        </div>
        <div className="mt-4">{children}</div>
    </div>
);

export function LoanRequestReviewStep({
    data,
    loanTypes,
    member,
    errors,
    onUndertakingChange,
}: ReviewStepProps) {
    const loanTypeLabel =
        loanTypes.find((type) => type.typecode === data.typecode)?.label ??
        data.typecode;
    const requestedAmount =
        data.requested_amount !== ''
            ? formatCurrency(Number(data.requested_amount))
            : '--';

    const loanSummary: SummaryItem[] = [
        { label: 'Loan type', value: displayValue(loanTypeLabel || '') },
        { label: 'Requested amount', value: requestedAmount },
        {
            label: 'Requested term',
            value:
                data.requested_term.trim() !== ''
                    ? `${data.requested_term} months`
                    : '--',
        },
        { label: 'Availment status', value: displayValue(data.availment_status) },
        { label: 'Loan purpose', value: displayValue(data.loan_purpose) },
    ];

    const applicantPersonal: SummaryItem[] = [
        { label: 'Applicant name', value: displayName(data.applicant) },
        { label: 'Nickname', value: displayValue(data.applicant.nickname) },
        { label: 'Birthdate', value: displayValue(data.applicant.birthdate) },
        { label: 'Birthplace', value: displayValue(data.applicant.birthplace) },
        { label: 'Address', value: displayValue(data.applicant.address) },
        { label: 'Length of stay', value: displayValue(data.applicant.length_of_stay) },
        { label: 'Housing status', value: formatHousingStatus(data.applicant.housing_status) },
        { label: 'Cell no.', value: displayValue(data.applicant.cell_no) },
        { label: 'Civil status', value: displayValue(data.applicant.civil_status) },
        {
            label: 'Educational attainment',
            value: displayValue(data.applicant.educational_attainment),
        },
        {
            label: 'No. of children',
            value: displayValue(data.applicant.number_of_children),
        },
        { label: 'Spouse name', value: displayValue(data.applicant.spouse_name) },
        { label: 'Spouse age', value: displayValue(data.applicant.spouse_age) },
        {
            label: 'Spouse cell no.',
            value: displayValue(data.applicant.spouse_cell_no),
        },
    ];

    const applicantWork: SummaryItem[] = [
        {
            label: 'Employment type',
            value: displayValue(data.applicant.employment_type),
        },
        {
            label: 'Employer/Business name',
            value: displayValue(data.applicant.employer_business_name),
        },
        {
            label: 'Employer/Business address',
            value: displayValue(data.applicant.employer_business_address),
        },
        { label: 'Telephone no.', value: displayValue(data.applicant.telephone_no) },
        { label: 'Current position', value: displayValue(data.applicant.current_position) },
        {
            label: 'Nature of business',
            value: displayValue(data.applicant.nature_of_business),
        },
        {
            label: 'Years in work/business',
            value: displayValue(data.applicant.years_in_work_business),
        },
        {
            label: 'Gross monthly income',
            value:
                data.applicant.gross_monthly_income.trim() !== ''
                    ? formatCurrency(Number(data.applicant.gross_monthly_income))
                    : '--',
        },
        { label: 'Payday', value: displayValue(data.applicant.payday) },
    ];

    const buildCoMakerSummary = (
        label: string,
        person: LoanRequestPersonFormData,
    ): SummaryItem[] => [
        { label: `${label} name`, value: displayName(person) },
        { label: 'Nickname', value: displayValue(person.nickname) },
        { label: 'Birthdate', value: displayValue(person.birthdate) },
        { label: 'Birthplace', value: displayValue(person.birthplace) },
        { label: 'Address', value: displayValue(person.address) },
        { label: 'Length of stay', value: displayValue(person.length_of_stay) },
        { label: 'Housing status', value: formatHousingStatus(person.housing_status) },
        { label: 'Cell no.', value: displayValue(person.cell_no) },
        {
            label: 'Educational attainment',
            value: displayValue(person.educational_attainment),
        },
        {
            label: 'Employment type',
            value: displayValue(person.employment_type),
        },
        {
            label: 'Employer/Business name',
            value: displayValue(person.employer_business_name),
        },
        {
            label: 'Employer/Business address',
            value: displayValue(person.employer_business_address),
        },
        { label: 'Telephone no.', value: displayValue(person.telephone_no) },
        { label: 'Current position', value: displayValue(person.current_position) },
        {
            label: 'Nature of business',
            value: displayValue(person.nature_of_business),
        },
        {
            label: 'Years in work/business',
            value: displayValue(person.years_in_work_business),
        },
        {
            label: 'Gross monthly income',
            value:
                person.gross_monthly_income.trim() !== ''
                    ? formatCurrency(Number(person.gross_monthly_income))
                    : '--',
        },
        { label: 'Payday', value: displayValue(person.payday) },
    ];

    return (
        <LoanRequestSectionCard
            title="Review & undertaking"
            description="Review your application before submitting."
            contentClassName="space-y-5"
        >
            <div className="rounded-lg border border-border/50 bg-muted/20 p-4 text-sm">
                <p className="text-xs uppercase text-muted-foreground">
                    Member
                </p>
                <p className="mt-2 font-medium">{member.name}</p>
                <p className="text-xs text-muted-foreground">
                    Account No: {member.acctno ?? '--'}
                </p>
            </div>

            <SummaryCard
                title="Loan details"
                description="Review the requested loan information."
            >
                <SummaryGrid items={loanSummary} />
            </SummaryCard>

            <SummaryCard
                title="Applicant personal data"
                description="Confirm personal information."
            >
                <SummaryGrid items={applicantPersonal} />
            </SummaryCard>

            <SummaryCard
                title="Applicant work & finances"
                description="Verify employment and income details."
            >
                <SummaryGrid items={applicantWork} />
            </SummaryCard>

            <SummaryCard
                title="Co-maker 1"
                description="Summary for your first co-maker."
            >
                <SummaryGrid
                    items={buildCoMakerSummary('Co-maker 1', data.co_maker_1)}
                />
            </SummaryCard>

            <SummaryCard
                title="Co-maker 2"
                description="Summary for your second co-maker."
            >
                <SummaryGrid
                    items={buildCoMakerSummary('Co-maker 2', data.co_maker_2)}
                />
            </SummaryCard>

            <SummaryCard
                title="Undertaking"
                description="Please read and confirm before submission."
            >
                <div className="space-y-4 text-sm text-muted-foreground">
                    <p>
                        I/We hereby undertake that all information provided here
                        in this application form and in all supporting document
                        are true and correct. I/We hereby authorized MRDINC to
                        verify any and all information furnished by me/us
                        including previous credit transactions with other
                        institution. In this connection, I/We hereby expressly
                        waive any and all statutory or regulatory provisions
                        governing confidentiality of such information. I fully
                        understand that any misrepresentation or failure to
                        disclose information on my/our part as required herein,
                        may cause the disapproval of my application.
                    </p>
                    <p>
                        Upon acceptance of my application, I/We legally and
                        validly bind to the terms and conditions of MRDINC
                        including, but not limited to, join and several
                        liability for all charges, fees and other obligations
                        incurred through the use of my loan. In case of
                        disapproval of this application, I understand that
                        MRDINC is not obligated to disclose the reasons for such
                        disapproval.
                    </p>
                    <p>
                        In the event of future delinquency, I hereby authorized
                        MRDINC to report and or include my name in the negative
                        listing of any bureau or institution.
                    </p>
                </div>

                <Separator className="my-4 bg-border/40" />

                <div className="flex items-start gap-3">
                    <Checkbox
                        id="undertaking_accepted"
                        checked={data.undertaking_accepted}
                        onCheckedChange={(checked) =>
                            onUndertakingChange(checked === true)
                        }
                    />
                    <div className="space-y-2">
                        <Label htmlFor="undertaking_accepted">
                            I confirm that I have read and agree to the
                            undertaking above.
                        </Label>
                        <InputError message={errors.undertaking_accepted} />
                    </div>
                </div>
            </SummaryCard>
        </LoanRequestSectionCard>
    );
}
