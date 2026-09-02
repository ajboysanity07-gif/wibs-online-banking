import { useEffect, useState, type ReactNode } from 'react';

import {
    DEPENDENT_CATEGORIES,
    DependentCategorySection,
    type DependentCategoryConfig,
    DependentSpouseCycleSection,
    SPOUSE_CYCLE_NUMBER_KEY,
    SPOUSE_CYCLE_STATUS_KEY,
    dependentCategoryPluralLabel,
    slotFieldKey,
    summarizeDependents,
} from '@/components/dependents/dependent-category-section';
import InputError from '@/components/input-error';
import { BooleanYesNoField } from '@/components/loan-request/boolean-yes-no-field';
import {
    LoanRequestPersonalFields,
    LoanRequestWorkFields,
    PAYDAY_OPTIONS,
} from '@/components/loan-request/loan-request-fields';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import {
    CurrencyInput,
    MonthsInput,
} from '@/components/loan-request/numeric-adorned-inputs';
import {
    PaymentAccountPickerSheet,
    PaymentMethodIcon,
    type PaymentMethodOption,
} from '@/components/loan-request/payment-account-picker-sheet';
import { SmokingStatusField } from '@/components/loan-request/smoking-status-field';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { useSavedPaymentAccounts } from '@/hooks/use-saved-payment-accounts';
import {
    calculateAge,
    composeAddress,
    composeBirthplace,
    formatCivilStatus,
    formatCurrency,
    formatDateTime,
    formatDisplayText,
    formatHousingStatus,
    formatPayday,
} from '@/lib/formatters';
import { resolveInstitutionalEmployerCategory } from '@/lib/institutional-employer-category';
import type {
    LoanRequestDataFieldDefinition,
    LoanRequestDataFieldValue,
    LoanRequestDataSectionDefinition,
    LoanRequestDataSectionValues,
    LoanRequestFormData,
    LoanRequestMemberSummary,
    LoanRequestPersonFormData,
    LoanRequestReadOnlyMap,
    LoanTypeOption,
    SavedCoMakerOption,
} from '@/types/loan-requests';

const AVAILMENT_OPTIONS = ['New', 'Re-Loan', 'Restructured'] as const;

const RELEASE_METHOD_OPTIONS = [
    'ATM',
    'Bank Transfer',
    'Check',
    'Cash',
] as const;
const PAYMENT_OPTION_OPTIONS = [
    'Salary Deduction',
    'ATM Deduction',
    'Check',
    'Cash',
] as const;

export const OTHER_LOAN_TYPECODE = '01';

const REPAYMENT_FREQUENCY_OPTIONS = [...PAYDAY_OPTIONS, 'Due date'] as const;

const KIND_OF_LOAN_OPTIONS = ['Regular', 'Emergency'] as const;

/** wlntype.lntype label match for "Micro Business Loan" -- no fixed typecode. */
const MICRO_BUSINESS_LOAN_LABEL = 'MICRO BUSINESS LOAN';

/** Loan type abbreviations shown alongside the kind-of-loan selection. */
const LOAN_TYPE_ABBREVIATIONS: Record<string, string> = {
    [MICRO_BUSINESS_LOAN_LABEL]: 'MBL',
};

function isMicroBusinessLoanLabel(label?: string | null): boolean {
    return (label ?? '').trim().toUpperCase() === MICRO_BUSINESS_LOAN_LABEL;
}

export function resolveLoanTypeAbbreviation(
    label?: string | null,
    kindOfLoan?: string | null,
): string | null {
    const abbreviation =
        LOAN_TYPE_ABBREVIATIONS[(label ?? '').trim().toUpperCase()];

    if (!abbreviation) {
        return null;
    }

    return kindOfLoan === 'Emergency'
        ? `${abbreviation}-Emergency`
        : abbreviation;
}

type LoanDetailField =
    | 'typecode'
    | 'requested_amount'
    | 'requested_term'
    | 'loan_purpose'
    | 'other_loan_type_name'
    | 'availment_status'
    | 'requested_payment_frequency'
    | 'kind_of_loan';

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
    const isOtherLoan = data.typecode === OTHER_LOAN_TYPECODE;
    const selectedLoanTypeLabel =
        loanTypes.find((option) => option.typecode === data.typecode)?.label ??
        null;
    const isMicroBusinessLoan = isMicroBusinessLoanLabel(selectedLoanTypeLabel);
    const loanTypeAbbreviation = resolveLoanTypeAbbreviation(
        selectedLoanTypeLabel,
        data.kind_of_loan,
    );

    useEffect(() => {
        if (!isOtherLoan && data.other_loan_type_name) {
            onChange('other_loan_type_name', '');
        }
    }, [isOtherLoan, data.other_loan_type_name, onChange]);

    useEffect(() => {
        if (!isMicroBusinessLoan && data.kind_of_loan) {
            onChange('kind_of_loan', '');
        }
    }, [isMicroBusinessLoan, data.kind_of_loan, onChange]);

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

                {isOtherLoan && (
                    <div className="grid gap-2">
                        <Label htmlFor="other_loan_type_name">
                            Name this loan
                        </Label>
                        <Input
                            id="other_loan_type_name"
                            value={data.other_loan_type_name}
                            className="mt-1 block w-full"
                            placeholder="e.g. Motorcycle Loan"
                            required
                            onChange={(event) =>
                                onChange(
                                    'other_loan_type_name',
                                    event.target.value,
                                )
                            }
                        />
                        <InputError message={errors.other_loan_type_name} />
                    </div>
                )}

                {isMicroBusinessLoan && (
                    <div className="grid gap-2">
                        <Label htmlFor="kind_of_loan">Kind of loan</Label>
                        <Select
                            value={data.kind_of_loan || undefined}
                            onValueChange={(value) =>
                                onChange('kind_of_loan', value)
                            }
                        >
                            <SelectTrigger
                                id="kind_of_loan"
                                className="mt-1 w-full"
                            >
                                <SelectValue placeholder="Select kind of loan" />
                            </SelectTrigger>
                            <SelectContent>
                                {KIND_OF_LOAN_OPTIONS.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.kind_of_loan} />
                        {loanTypeAbbreviation && (
                            <p className="text-xs text-muted-foreground">
                                Shown as{' '}
                                <Badge variant="secondary">
                                    {loanTypeAbbreviation}
                                </Badge>
                            </p>
                        )}
                        {data.kind_of_loan === 'Emergency' && (
                            <p className="text-xs text-muted-foreground">
                                Emergency loans skip the insurance and health
                                questionnaire steps below.
                            </p>
                        )}
                    </div>
                )}

                <div className="grid gap-2">
                    <Label htmlFor="requested_amount">Requested amount</Label>
                    <CurrencyInput
                        id="requested_amount"
                        value={data.requested_amount}
                        onValueChange={(value) =>
                            onChange('requested_amount', value)
                        }
                    />
                    <InputError message={errors.requested_amount} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="requested_term">Loan term</Label>
                    <MonthsInput
                        id="requested_term"
                        value={data.requested_term}
                        placeholder="e.g. 12"
                        required
                        onChange={(value) => onChange('requested_term', value)}
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

                {isOtherLoan && (
                    <div className="grid gap-2">
                        <Label htmlFor="requested_payment_frequency">
                            Preferred repayment frequency
                        </Label>
                        <Select
                            value={
                                data.requested_payment_frequency || undefined
                            }
                            onValueChange={(value) =>
                                onChange('requested_payment_frequency', value)
                            }
                        >
                            <SelectTrigger
                                id="requested_payment_frequency"
                                className="mt-1 w-full"
                            >
                                <SelectValue placeholder="Select repayment frequency" />
                            </SelectTrigger>
                            <SelectContent>
                                {REPAYMENT_FREQUENCY_OPTIONS.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError
                            message={errors.requested_payment_frequency}
                        />
                    </div>
                )}

                {data.requested_payment_frequency === 'Due date' && (
                    <div className="grid gap-2 md:col-span-2">
                        <p className="text-sm text-muted-foreground">
                            Due date is repaid as a single payment after the
                            loan term above.
                            {data.requested_term === '1' &&
                                ' Paying in 1 month skips the insurance and health questionnaire steps below.'}
                        </p>
                    </div>
                )}
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

type ApplicantPersonalStepProps = PersonStepProps & {
    section: 'basic' | 'contact' | 'family';
};

const PERSONAL_STEP_TITLES: Record<
    ApplicantPersonalStepProps['section'],
    string
> = {
    basic: 'My personal data',
    contact: 'Address & contact',
    family: 'Family & background',
};

const PERSONAL_STEP_DESCS: Record<
    ApplicantPersonalStepProps['section'],
    string
> = {
    basic: 'Confirm your basic personal details.',
    contact: 'Confirm your address and contact details.',
    family: 'Confirm civil status, education, and family details.',
};

export function LoanRequestApplicantPersonalStep({
    values,
    errors,
    readOnly,
    onChange,
    section,
}: ApplicantPersonalStepProps) {
    return (
        <LoanRequestSectionCard
            title={PERSONAL_STEP_TITLES[section]}
            description={PERSONAL_STEP_DESCS[section]}
        >
            <LoanRequestPersonalFields
                prefix="applicant"
                values={values}
                errors={errors}
                readOnly={readOnly}
                includeSpouse
                includeChildren
                includeCivilHousing
                section={section}
                onChange={onChange}
            />
        </LoanRequestSectionCard>
    );
}

type ApplicantWorkStepProps = Omit<PersonStepProps, 'readOnly'> & {
    section: 'employment' | 'income';
};

const WORK_STEP_DESCS: Record<ApplicantWorkStepProps['section'], string> = {
    employment: 'Share your employment and employer details.',
    income: 'Share your income, position, and business details.',
};

export function LoanRequestApplicantWorkStep({
    values,
    errors,
    onChange,
    section,
}: ApplicantWorkStepProps) {
    return (
        <LoanRequestSectionCard
            title="My work & finances"
            description={WORK_STEP_DESCS[section]}
        >
            <LoanRequestWorkFields
                prefix="applicant"
                values={values}
                errors={errors}
                section={section}
                onChange={onChange}
            />
            {section === 'income' ? (
                <>
                    <Separator className="bg-border/40" />
                    <Alert className="border-border/50 bg-muted/10">
                        <AlertTitle>Physical signatures</AlertTitle>
                        <AlertDescription>
                            Signatures will be collected physically upon loan
                            release.
                        </AlertDescription>
                    </Alert>
                </>
            ) : null}
        </LoanRequestSectionCard>
    );
}

type CoMakerStepProps = {
    title: string;
    description: string;
    prefix: string;
    section: 'basic' | 'contact' | 'employment' | 'income';
    values: LoanRequestPersonFormData;
    errors: Record<string, string | undefined>;
    onChange: (field: keyof LoanRequestPersonFormData, value: string) => void;
    savedCoMakers?: SavedCoMakerOption[];
    onLoadSavedCoMaker?: (id: number) => void;
    onRemoveSavedCoMaker?: (id: number) => void;
    onToggleSaveForReuse?: (checked: boolean) => void;
};

// "Load a saved co-maker" only appears on the first (basic) step of each
// co-maker's flow -- it's a starting point that fills every section at
// once, so repeating it on every step would be redundant.
function SavedCoMakerPicker({
    savedCoMakers,
    onLoadSavedCoMaker,
    onRemoveSavedCoMaker,
}: {
    savedCoMakers: SavedCoMakerOption[];
    onLoadSavedCoMaker?: (id: number) => void;
    onRemoveSavedCoMaker?: (id: number) => void;
}) {
    if (savedCoMakers.length === 0) {
        return null;
    }

    return (
        <div className="space-y-3 rounded-md border border-border/50 bg-muted/10 p-3">
            <div>
                <p className="text-sm font-medium">Load a saved co-maker</p>
                <p className="text-xs text-muted-foreground">
                    Pick someone you&apos;ve used as a co-maker before to fill
                    in their details below. You can still edit anything after
                    loading.
                </p>
            </div>
            <div className="flex flex-col gap-2">
                {savedCoMakers.map((option) => (
                    <div
                        key={option.id}
                        className="flex items-center justify-between gap-2 rounded-md border border-border/40 bg-background px-3 py-2"
                    >
                        <div>
                            <p className="text-sm font-medium">
                                {option.label}
                            </p>
                            {option.last_used_at ? (
                                <p className="text-xs text-muted-foreground">
                                    Last used{' '}
                                    {formatDateTime(option.last_used_at)}
                                </p>
                            ) : null}
                        </div>
                        <div className="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={() => onLoadSavedCoMaker?.(option.id)}
                            >
                                Use this
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    onRemoveSavedCoMaker?.(option.id)
                                }
                            >
                                Remove
                            </Button>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

export function LoanRequestCoMakerStep({
    title,
    description,
    prefix,
    section,
    values,
    errors,
    onChange,
    savedCoMakers,
    onLoadSavedCoMaker,
    onRemoveSavedCoMaker,
    onToggleSaveForReuse,
}: CoMakerStepProps) {
    return (
        <LoanRequestSectionCard title={title} description={description}>
            {section === 'basic' && savedCoMakers ? (
                <SavedCoMakerPicker
                    savedCoMakers={savedCoMakers}
                    onLoadSavedCoMaker={onLoadSavedCoMaker}
                    onRemoveSavedCoMaker={onRemoveSavedCoMaker}
                />
            ) : null}
            {section === 'basic' || section === 'contact' ? (
                <LoanRequestPersonalFields
                    prefix={prefix}
                    values={values}
                    errors={errors}
                    section={section}
                    onChange={onChange}
                />
            ) : (
                <>
                    <LoanRequestWorkFields
                        prefix={prefix}
                        values={values}
                        errors={errors}
                        section={section}
                        onChange={onChange}
                    />
                    {section === 'income' ? (
                        <>
                            <Separator className="bg-border/40" />
                            <Alert className="border-border/50 bg-muted/10">
                                <AlertTitle>Physical signatures</AlertTitle>
                                <AlertDescription>
                                    Signatures will be collected physically upon
                                    loan release.
                                </AlertDescription>
                            </Alert>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id={`${prefix}_save_for_reuse`}
                                    checked={values.save_for_reuse}
                                    onCheckedChange={(checked) =>
                                        onToggleSaveForReuse?.(checked === true)
                                    }
                                />
                                <Label
                                    htmlFor={`${prefix}_save_for_reuse`}
                                    className="text-sm font-normal"
                                >
                                    Save this co-maker&apos;s details so I can
                                    reuse them on a future loan
                                </Label>
                            </div>
                        </>
                    ) : null}
                </>
            )}
        </LoanRequestSectionCard>
    );
}

type ReviewStepProps = {
    data: LoanRequestFormData;
    loanTypes: LoanTypeOption[];
    member: LoanRequestMemberSummary;
    errors: Record<string, string | undefined>;
    sectionDefinitions: Record<string, LoanRequestDataSectionDefinition>;
    onUndertakingChange: (value: boolean) => void;
};

type SummaryItem = {
    label: string;
    value: string;
};

const displayValue = (value: string): string =>
    value.trim() !== '' ? value : '--';

const textareaClassName =
    'flex min-h-[112px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50';

const displayText = (value?: string | null): string => {
    const normalized = formatDisplayText(value);

    return normalized !== '' ? normalized : '--';
};

const displayName = (person: LoanRequestPersonFormData): string => {
    const name = [person.first_name, person.middle_name, person.last_name]
        .map((value) => formatDisplayText(value))
        .map((value) => value.trim())
        .filter((value) => value !== '')
        .join(' ');

    return name !== '' ? name : '--';
};

const resolveBirthplace = (person: LoanRequestPersonFormData): string =>
    composeBirthplace(person.birthplace_city, person.birthplace_province);

const resolveAddress = (person: LoanRequestPersonFormData): string =>
    composeAddress(
        person.address1,
        person.address2,
        person.address3,
        person.address_barangay,
    );

const resolveEmployerBusinessAddress = (
    person: LoanRequestPersonFormData,
): string =>
    composeAddress(
        person.employer_business_address1,
        person.employer_business_address2,
        person.employer_business_address3,
        person.employer_business_address_barangay,
    );

const SummaryGrid = ({ items }: { items: SummaryItem[] }) => (
    <div className="grid gap-3 sm:grid-cols-2">
        {items.map((item) => (
            <div key={item.label} className="space-y-1">
                <p className="text-xs text-muted-foreground">{item.label}</p>
                <p className="text-sm font-medium wrap-break-word">
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
                <p className="text-xs text-muted-foreground">{description}</p>
            ) : null}
        </div>
        <div className="mt-4">{children}</div>
    </div>
);

type AccordionSummaryCardProps = SummaryCardProps & { value: string };

// Same visual shell as SummaryCard, but as a standalone collapsible
// AccordionItem so long sections (co-makers, health questionnaire,
// declarations) don't force the member to scroll past everything to reach
// submit. Overrides the default AccordionItem's shared-container border
// styling so each card still reads as its own bordered panel.
const AccordionSummaryCard = ({
    value,
    title,
    description,
    children,
}: AccordionSummaryCardProps) => (
    <AccordionItem
        value={value}
        className="rounded-lg border border-b-0 border-border/50 bg-card/60 px-4"
    >
        <AccordionTrigger className="py-4 hover:no-underline">
            <div className="space-y-1 text-left">
                <h3 className="text-sm font-semibold">{title}</h3>
                {description ? (
                    <p className="text-xs font-normal text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
        </AccordionTrigger>
        <AccordionContent>{children}</AccordionContent>
    </AccordionItem>
);

type QaEntry = {
    fieldKey: string;
    label: string;
    answer: string;
    children: QaEntry[];
};

// Renders question/details pairs (identified via each field's `detail_of`
// metadata -- see LoanRequestHealthQuestionnaireStep above for the same
// parent/child derivation used during data entry) as a numbered Q&A list
// instead of a flat label/value grid, so a question stays visually attached
// to the answer and any conditional follow-up the member gave for it.
const QuestionAnswerList = ({
    items,
    depth = 0,
}: {
    items: QaEntry[];
    depth?: number;
}) => (
    <ol className="space-y-4">
        {items.map((item, index) => (
            <li key={item.fieldKey} className="space-y-2">
                <div className="flex items-start gap-2">
                    <span className="mt-0.5 shrink-0 text-xs font-semibold text-muted-foreground">
                        {depth === 0 ? `${index + 1}.` : '↳'}
                    </span>
                    <div className="flex-1 space-y-1">
                        <p className="text-sm font-medium wrap-break-word">
                            {item.label}
                        </p>
                        <p className="text-sm wrap-break-word text-muted-foreground">
                            {item.answer}
                        </p>
                    </div>
                </div>
                {item.children.length > 0 ? (
                    <div className="ml-4 space-y-3 rounded-md border-l-4 border-primary/25 bg-muted/10 py-3 pl-4">
                        <QuestionAnswerList
                            items={item.children}
                            depth={depth + 1}
                        />
                    </div>
                ) : null}
            </li>
        ))}
    </ol>
);

type DataSectionStepProps = {
    sectionKey: keyof Pick<
        LoanRequestFormData,
        'insurance' | 'health' | 'health_glapi' | 'banking' | 'declarations'
    >;
    title: string;
    description: string;
    values: LoanRequestDataSectionValues;
    definition: LoanRequestDataSectionDefinition;
    errors: Record<string, string | undefined>;
    onChange: (field: string, value: string | number | boolean | null) => void;
    // Source of truth for the "This is my own ATM card" checkbox
    // (payout_atm_holder_name, banking section only) -- omit for sections
    // that don't render that field.
    applicantFullName?: string;
    // Employer signal (banking section only) used to restrict the "Salary
    // Deduction" payment option to institutional-payroll employers -- see
    // resolveInstitutionalEmployerCategory().
    applicantEmployerBusinessName?: string | null;
    applicantEmploymentType?: string | null;
    applicantNatureOfBusiness?: string | null;
};

// declaration_truth_confirmation and declaration_data_privacy_consent are
// agreements the applicant must actively accept, not factual Yes/No
// disclosures -- render them as a standard blocking "I agree" checkbox
// instead of the BooleanYesNoField used for the rest of this section.
const CONSENT_CHECKBOX_COPY: Record<string, string> = {
    declaration_truth_confirmation:
        'I confirm that all information I have provided in this loan request is true, complete, and accurate to the best of my knowledge.',
    declaration_data_privacy_consent:
        'I consent to the collection, use, and processing of my personal data for purposes of evaluating and processing this loan request, in accordance with the Data Privacy Act.',
};

const displaySectionValue = (
    value: unknown,
    field: LoanRequestDataFieldDefinition,
): string => {
    if (field.type === 'boolean') {
        if (value === true) {
            return 'Yes';
        }

        if (value === false) {
            return 'No';
        }

        return '--';
    }

    if (value === null || value === undefined) {
        return '--';
    }

    if (field.type === 'number' || field.type === 'integer') {
        const numericValue = Number(value);

        return Number.isFinite(numericValue)
            ? `${numericValue}`
            : displayText(`${value}`);
    }

    return displayText(`${value}`);
};

type BankingSectionFieldsProps = {
    sectionKey: string;
    definition: LoanRequestDataSectionDefinition;
    values: LoanRequestDataSectionValues;
    errors: Record<string, string | undefined>;
    onChange: (field: string, value: string | number | boolean | null) => void;
    applicantFullName?: string;
    applicantEmployerBusinessName?: string | null;
    applicantEmploymentType?: string | null;
    applicantNatureOfBusiness?: string | null;
};

const RELEASE_METHOD_PICKER_OPTIONS: PaymentMethodOption[] =
    RELEASE_METHOD_OPTIONS.map((value) => ({
        value,
        label: value,
        needsAccount: value === 'ATM' || value === 'Bank Transfer',
    }));

// Two full-width, clearly headed subsections -- Loan Disbursement (release
// method) and Repayment Method (payment option) -- each backed by the
// member's saved payment accounts via the same Shopee-style picker used on
// the loan request show page and in profile settings, instead of free-text
// bank/ATM fields.
function BankingSectionFields({
    sectionKey,
    values,
    errors,
    onChange,
    applicantEmployerBusinessName,
    applicantEmploymentType,
    applicantNatureOfBusiness,
}: BankingSectionFieldsProps) {
    const {
        accounts,
        isLoading: isLoadingAccounts,
        isSaving: isSavingAccount,
        loadAccounts,
        createAccount,
        updateAccount,
    } = useSavedPaymentAccounts();
    const [isReleaseSheetOpen, setIsReleaseSheetOpen] = useState(false);
    const [isPaymentSheetOpen, setIsPaymentSheetOpen] = useState(false);

    useEffect(() => {
        void loadAccounts();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const releaseMethod = values.release_method
        ? `${values.release_method}`
        : '';
    const paymentOption = values.payment_option
        ? `${values.payment_option}`
        : '';
    const releaseAccountId =
        typeof values.release_saved_account_id === 'number'
            ? values.release_saved_account_id
            : null;
    const paymentAccountId =
        typeof values.payment_saved_account_id === 'number'
            ? values.payment_saved_account_id
            : null;
    const isInstitutionalEmployer =
        resolveInstitutionalEmployerCategory(
            applicantEmployerBusinessName,
            applicantEmploymentType,
            applicantNatureOfBusiness,
        ) !== null;
    const paymentOptionPickerOptions: PaymentMethodOption[] =
        PAYMENT_OPTION_OPTIONS.filter(
            (option) =>
                isInstitutionalEmployer || option !== 'Salary Deduction',
        ).map((value) => ({
            value,
            label: value,
            needsAccount: value === 'ATM Deduction',
        }));
    const releaseNeedsAccount =
        releaseMethod === 'ATM' || releaseMethod === 'Bank Transfer';
    const paymentNeedsAccount = paymentOption === 'ATM Deduction';
    const releaseAccountLabel = accounts.find(
        (account) => account.id === releaseAccountId,
    )?.label;
    const paymentAccountLabel = accounts.find(
        (account) => account.id === paymentAccountId,
    )?.label;

    const confirmRelease = async (method: string, accountId: number | null) => {
        onChange('release_method', method);
        onChange('release_saved_account_id', accountId);

        return true;
    };

    const confirmPayment = async (method: string, accountId: number | null) => {
        onChange('payment_option', method);
        onChange('payment_saved_account_id', accountId);

        return true;
    };

    return (
        <div className="space-y-8">
            <div className="space-y-4">
                <div>
                    <h4 className="text-sm font-semibold">Loan Disbursement</h4>
                    <p className="text-xs text-muted-foreground">
                        How you&apos;ll receive your loan proceeds.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-3 rounded-md border border-input p-3">
                    <div className="flex-1 space-y-1">
                        <div className="flex items-center gap-2">
                            <PaymentMethodIcon
                                method={releaseMethod || null}
                                className="h-4 w-4 text-muted-foreground"
                            />
                            <p className="text-sm font-medium">
                                {releaseMethod || 'Not set'}
                            </p>
                        </div>
                        {releaseNeedsAccount && (
                            <p className="text-sm text-muted-foreground">
                                {releaseAccountLabel ?? 'No account selected'}
                            </p>
                        )}
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => {
                            void loadAccounts();
                            setIsReleaseSheetOpen(true);
                        }}
                    >
                        {releaseMethod ? 'Change' : 'Choose release method'}
                    </Button>
                </div>
                <InputError message={errors[`${sectionKey}.release_method`]} />
                <InputError
                    message={errors[`${sectionKey}.release_saved_account_id`]}
                />
            </div>

            <Separator />

            <div className="space-y-4">
                <div>
                    <h4 className="text-sm font-semibold">Repayment Method</h4>
                    <p className="text-xs text-muted-foreground">
                        How your loan installments will be collected.
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-3 rounded-md border border-input p-3">
                    <div className="flex-1 space-y-1">
                        <div className="flex items-center gap-2">
                            <PaymentMethodIcon
                                method={paymentOption || null}
                                className="h-4 w-4 text-muted-foreground"
                            />
                            <p className="text-sm font-medium">
                                {paymentOption || 'Not set'}
                            </p>
                        </div>
                        {paymentNeedsAccount && (
                            <p className="text-sm text-muted-foreground">
                                {paymentAccountLabel ?? 'No account selected'}
                            </p>
                        )}
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => {
                            void loadAccounts();
                            setIsPaymentSheetOpen(true);
                        }}
                    >
                        {paymentOption ? 'Change' : 'Choose repayment method'}
                    </Button>
                </div>
                <InputError message={errors[`${sectionKey}.payment_option`]} />
                <InputError
                    message={errors[`${sectionKey}.payment_saved_account_id`]}
                />
                {!isInstitutionalEmployer && (
                    <p className="text-xs text-muted-foreground">
                        Salary Deduction is only available for BLGU, LGU, LDH,
                        or MRDINC employees.
                    </p>
                )}
            </div>

            <PaymentAccountPickerSheet
                open={isReleaseSheetOpen}
                onOpenChange={setIsReleaseSheetOpen}
                title="Choose release method"
                description="Select how you'd like to receive your loan proceeds."
                accounts={accounts}
                methodOptions={RELEASE_METHOD_PICKER_OPTIONS}
                initialMethod={releaseMethod || null}
                initialAccountId={releaseAccountId}
                isSaving={isSavingAccount || isLoadingAccounts}
                onConfirm={confirmRelease}
                onCreateAccount={createAccount}
                onUpdateAccount={updateAccount}
            />
            <PaymentAccountPickerSheet
                open={isPaymentSheetOpen}
                onOpenChange={setIsPaymentSheetOpen}
                title="Choose repayment method"
                description="Select how you'd like to repay your loan."
                accounts={accounts}
                methodOptions={paymentOptionPickerOptions}
                initialMethod={paymentOption || null}
                initialAccountId={paymentAccountId}
                isSaving={isSavingAccount || isLoadingAccounts}
                onConfirm={confirmPayment}
                onCreateAccount={createAccount}
                onUpdateAccount={updateAccount}
            />
        </div>
    );
}

export function LoanRequestDataSectionStep({
    sectionKey,
    title,
    description,
    values,
    definition,
    errors,
    onChange,
    applicantFullName,
    applicantEmployerBusinessName,
    applicantEmploymentType,
    applicantNatureOfBusiness,
}: DataSectionStepProps) {
    if (sectionKey === 'banking') {
        return (
            <LoanRequestSectionCard
                title={title}
                description={description}
                contentClassName="space-y-5"
            >
                <BankingSectionFields
                    sectionKey={sectionKey}
                    definition={definition}
                    values={values}
                    errors={errors}
                    onChange={onChange}
                    applicantFullName={applicantFullName}
                    applicantEmployerBusinessName={
                        applicantEmployerBusinessName
                    }
                    applicantEmploymentType={applicantEmploymentType}
                    applicantNatureOfBusiness={applicantNatureOfBusiness}
                />
                <Alert className="border-border/50 bg-muted/10">
                    <AlertTitle>Member-provided details</AlertTitle>
                    <AlertDescription>
                        Complete the applicable fields in this section before
                        submitting the request for processing.
                    </AlertDescription>
                </Alert>
            </LoanRequestSectionCard>
        );
    }

    return (
        <LoanRequestSectionCard
            title={title}
            description={description}
            contentClassName="space-y-5"
        >
            <div className="grid gap-4 md:grid-cols-2">
                {Object.entries(definition.fields).map(([fieldKey, field]) => {
                    const errorKey = `${sectionKey}.${fieldKey}`;
                    const value = values[fieldKey];
                    const isNotesField = fieldKey.includes('notes');
                    const consentCopy = CONSENT_CHECKBOX_COPY[fieldKey];

                    // Existing-loan detail fields are auto-filled from
                    // account records and feed the GLAPI PDF, but are not
                    // shown to the member in the wizard.
                    if (fieldKey.startsWith('existing_loan_')) {
                        return null;
                    }

                    if (fieldKey === 'declaration_existing_loans') {
                        return (
                            <div
                                key={fieldKey}
                                className="grid gap-2 md:col-span-2"
                            >
                                <Label htmlFor={`${sectionKey}_${fieldKey}`}>
                                    {field.label}
                                </Label>
                                <BooleanYesNoField
                                    id={`${sectionKey}_${fieldKey}`}
                                    value={value}
                                    aria-label={field.label}
                                    fullWidth={sectionKey === 'declarations'}
                                    disabled
                                    onChange={(nextValue) =>
                                        onChange(fieldKey, nextValue)
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    Automatically determined from your account
                                    records.
                                </p>
                                <InputError message={errors[errorKey]} />
                            </div>
                        );
                    }

                    if (consentCopy) {
                        return (
                            <div
                                key={fieldKey}
                                className="grid gap-2 md:col-span-2"
                            >
                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id={`${sectionKey}_${fieldKey}`}
                                        checked={value === true}
                                        aria-label={field.label}
                                        onCheckedChange={(checked) =>
                                            onChange(fieldKey, checked === true)
                                        }
                                    />
                                    <Label
                                        htmlFor={`${sectionKey}_${fieldKey}`}
                                        className="text-sm leading-snug font-normal"
                                    >
                                        {consentCopy}{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                </div>
                                <InputError message={errors[errorKey]} />
                            </div>
                        );
                    }

                    const isFullWidthToggle =
                        field.type === 'boolean' &&
                        sectionKey === 'declarations';

                    return (
                        <div
                            key={fieldKey}
                            className={
                                isNotesField || isFullWidthToggle
                                    ? 'grid gap-2 md:col-span-2'
                                    : 'grid gap-2'
                            }
                        >
                            <Label htmlFor={`${sectionKey}_${fieldKey}`}>
                                {field.label}
                            </Label>
                            {field.type === 'boolean' ? (
                                <>
                                    <BooleanYesNoField
                                        id={`${sectionKey}_${fieldKey}`}
                                        value={value}
                                        aria-label={field.label}
                                        fullWidth={isFullWidthToggle}
                                        disabled={
                                            fieldKey ===
                                            'declaration_pending_cases'
                                        }
                                        onChange={(nextValue) =>
                                            onChange(fieldKey, nextValue)
                                        }
                                    />
                                    {fieldKey ===
                                        'declaration_pending_cases' && (
                                        <p className="text-xs text-muted-foreground">
                                            Automatically determined from your
                                            account records.
                                        </p>
                                    )}
                                </>
                            ) : isNotesField ? (
                                <textarea
                                    id={`${sectionKey}_${fieldKey}`}
                                    aria-label={field.label}
                                    className={textareaClassName}
                                    value={value ? `${value}` : ''}
                                    maxLength={1000}
                                    onChange={(event) =>
                                        onChange(fieldKey, event.target.value)
                                    }
                                />
                            ) : (
                                <Input
                                    id={`${sectionKey}_${fieldKey}`}
                                    type={
                                        field.type === 'number' ||
                                        field.type === 'integer'
                                            ? 'number'
                                            : field.type === 'date'
                                              ? 'date'
                                              : 'text'
                                    }
                                    step={
                                        field.type === 'number'
                                            ? '0.01'
                                            : undefined
                                    }
                                    value={value ? `${value}` : ''}
                                    onChange={(event) =>
                                        onChange(fieldKey, event.target.value)
                                    }
                                />
                            )}
                            <InputError message={errors[errorKey]} />
                        </div>
                    );
                })}
            </div>
            <Alert className="border-border/50 bg-muted/10">
                <AlertTitle>Member-provided details</AlertTitle>
                <AlertDescription>
                    Complete the applicable fields in this section before
                    submitting the request for processing.
                </AlertDescription>
            </Alert>
        </LoanRequestSectionCard>
    );
}

type LoanRequestHealthStepProps = {
    healthValues: LoanRequestDataSectionValues;
    healthDefinition: LoanRequestDataSectionDefinition;
    glapiValues: LoanRequestDataSectionValues;
    glapiDefinition: LoanRequestDataSectionDefinition;
    glapiTitle: string;
    glapiDescription: string;
    glapiItemNumbers: string[];
    errors: Record<string, string | undefined>;
    crossSectionValues: Record<string, LoanRequestDataFieldValue>;
    onHealthChange: (
        field: string,
        value: string | number | boolean | null,
    ) => void;
    onGlapiChange: (
        field: string,
        value: string | number | boolean | null,
    ) => void;
};

/**
 * Thin wrapper around `LoanRequestHealthQuestionnaireStep` for the GLAPI
 * sub-step that also carries the smoking status/hypertension questions
 * (which live in the separate `health` section). Those two items are
 * spliced into the questionnaire's own numbered sequence at their thematic
 * position (see `GLAPI_VIRTUAL_ITEMS`), not rendered as a separate cluster.
 */
export function LoanRequestHealthStep({
    healthValues,
    healthDefinition,
    glapiValues,
    glapiDefinition,
    glapiTitle,
    glapiDescription,
    glapiItemNumbers,
    errors,
    crossSectionValues,
    onHealthChange,
    onGlapiChange,
}: LoanRequestHealthStepProps) {
    return (
        <LoanRequestHealthQuestionnaireStep
            sectionKey="health_glapi"
            title={glapiTitle}
            description={glapiDescription}
            values={glapiValues}
            definition={glapiDefinition}
            errors={errors}
            crossSectionValues={crossSectionValues}
            onChange={onGlapiChange}
            itemNumbers={glapiItemNumbers}
            healthValues={healthValues}
            healthDefinition={healthDefinition}
            onHealthChange={onHealthChange}
        />
    );
}

type HealthQuestionnaireStepProps = {
    sectionKey: 'health_glapi';
    title: string;
    description: string;
    values: LoanRequestDataSectionValues;
    definition: LoanRequestDataSectionDefinition;
    errors: Record<string, string | undefined>;
    crossSectionValues: Record<string, LoanRequestDataFieldValue>;
    onChange: (field: string, value: string | number | boolean | null) => void;
    // Restricts rendering to the given source-form item numbers (as returned
    // by `parseGlapiItem().number`). The GLAPI questionnaire is split across
    // several wizard sub-steps (see GLAPI_GROUPS_PER_STEP / chunkGlapiItemGroups
    // in loan-request.tsx), each passing the item numbers it owns.
    itemNumbers: string[];
    // The smoking status/hypertension questions live in the separate `health`
    // section but are spliced into this questionnaire's numbered sequence at
    // their thematic position (see GLAPI_VIRTUAL_ITEMS), so this component
    // reads/writes across both sections rather than being scoped to one.
    healthValues: LoanRequestDataSectionValues;
    healthDefinition: LoanRequestDataSectionDefinition;
    onHealthChange: (
        field: string,
        value: string | number | boolean | null,
    ) => void;
};

/**
 * Renders a slice of the GLAPI health questionnaire generically from field
 * metadata: each field's `detail_of` links it to its parent, so a "Yes"
 * answer reveals its children (a details textarea, or -- for item 17's
 * nested "With GLAPI / With other companies" breakdown -- further booleans
 * with their own amount fields). No item is hardcoded; the nesting comes
 * entirely from the field definitions.
 *
 * Root fields are additionally clustered into the source form's own
 * item/sub-item groups (e.g. "2a"-"2j") by parsing the `gl_health_qNN[letter]_`
 * naming convention on each field key -- this is a display grouping only, it
 * doesn't change which fields exist or how detail_of/visible_when behave.
 */
export const GLAPI_ITEM_KEY_PATTERN = /^gl_health_q(\d+)([a-z])?_/;

// Instruction copy for the one item that groups several sub-questions under
// a shared prompt (source form item 2). Purely display text, keyed by the
// item number parsed from the field key -- not item-specific branching logic.
const GLAPI_GROUP_HEADINGS: Record<string, string> = {
    '2': 'Have you ever suffered from or sought medical treatment for:',
};

export type GlapiItemGroup = {
    number: string;
    fieldKeys: string[];
};

// The GLAPI questionnaire is split across several wizard sub-steps so a
// member isn't faced with all its items at once. Sub-step boundaries fall
// between item groups only, so an expanded parent and its revealed children
// -- always rendered within the same group -- can never be split across two
// sub-steps. Chunk size is configurable, not tied to any specific item,
// consistent with the "no item is hardcoded" design.
export const GLAPI_GROUPS_PER_STEP = 4;

export function chunkGlapiItemGroups(
    groups: GlapiItemGroup[],
    chunkSize: number,
): GlapiItemGroup[][] {
    const chunks: GlapiItemGroup[][] = [];

    for (let index = 0; index < groups.length; index += chunkSize) {
        chunks.push(groups.slice(index, index + chunkSize));
    }

    return chunks.length > 0 ? chunks : [[]];
}

// Groups a GLAPI section's fields into the source form's own item numbering
// (e.g. "1", "2", "4", ... "17"), in field-definition order. Derived purely
// from field metadata -- no item numbers are hardcoded here.
export function getGlapiItemGroups(
    definition: LoanRequestDataSectionDefinition,
): GlapiItemGroup[] {
    const rootFieldKeys = Object.keys(definition.fields).filter(
        (fieldKey) => !definition.fields[fieldKey].detail_of,
    );

    const groups: GlapiItemGroup[] = [];

    rootFieldKeys.forEach((fieldKey) => {
        const groupNumber = parseGlapiItem(fieldKey)?.number ?? fieldKey;
        const lastGroup = groups[groups.length - 1];

        if (lastGroup && lastGroup.number === groupNumber) {
            lastGroup.fieldKeys.push(fieldKey);
        } else {
            groups.push({ number: groupNumber, fieldKeys: [fieldKey] });
        }
    });

    return groups;
}

export function parseGlapiItem(
    fieldKey: string,
): { number: string; letter: string | null } | null {
    const match = GLAPI_ITEM_KEY_PATTERN.exec(fieldKey);

    if (!match) {
        return null;
    }

    return { number: String(parseInt(match[1], 10)), letter: match[2] ?? null };
}

// Cross-section fields that thematically belong inside the GLAPI item
// sequence but are stored in the separate `health` section (so their values
// survive independently of which GLAPI chunk happens to be on screen).
// `afterNumber` is the GLAPI item number (as produced by getGlapiItemGroups)
// each virtual item is spliced immediately after -- their position in the
// sequence, and therefore their displayed badge number and wizard sub-step,
// is derived the same way as any real item, not hardcoded.
type GlapiVirtualItem = {
    key: 'health_hypertension' | 'health_smoking_status';
    afterNumber: string;
};

export const GLAPI_VIRTUAL_ITEMS: GlapiVirtualItem[] = [
    { key: 'health_hypertension', afterNumber: '2' },
    { key: 'health_smoking_status', afterNumber: '10' },
];

export type GlapiSequenceEntry =
    | { kind: 'group'; number: string; group: GlapiItemGroup }
    | {
          kind: 'virtual';
          number: string;
          key: GlapiVirtualItem['key'];
          afterNumber: string;
      };

// Combines the real GLAPI item groups with the virtual cross-section items
// into a single ordered sequence, each virtual item placed immediately after
// its anchor group. Sequence position (1-based) is what drives the displayed
// badge number and, in loan-request.tsx, which wizard sub-step an item lands
// in -- so this is the single source of truth for "where does item X live."
export function buildGlapiSequence(
    definition: LoanRequestDataSectionDefinition,
): GlapiSequenceEntry[] {
    const sequence: GlapiSequenceEntry[] = [];

    getGlapiItemGroups(definition).forEach((group) => {
        sequence.push({ kind: 'group', number: group.number, group });

        GLAPI_VIRTUAL_ITEMS.filter(
            (virtual) => virtual.afterNumber === group.number,
        ).forEach((virtual) => {
            sequence.push({
                kind: 'virtual',
                number: virtual.key,
                key: virtual.key,
                afterNumber: virtual.afterNumber,
            });
        });
    });

    return sequence;
}

function childWrapperClassName(depth: number): string {
    if (depth >= 2) {
        return 'ml-6 space-y-3 rounded-md border-l-4 border-primary/50 bg-muted/20 py-3 pl-4';
    }

    return 'ml-4 space-y-3 rounded-md border-l-4 border-primary/25 bg-muted/10 py-3 pl-4';
}

export function LoanRequestHealthQuestionnaireStep({
    sectionKey,
    title,
    description,
    values,
    definition,
    errors,
    crossSectionValues,
    onChange,
    itemNumbers,
    healthValues,
    healthDefinition,
    onHealthChange,
}: HealthQuestionnaireStepProps) {
    const childrenByParent: Record<string, string[]> = {};

    Object.entries(definition.fields).forEach(([fieldKey, field]) => {
        if (!field.detail_of) {
            return;
        }

        const parents = Array.isArray(field.detail_of)
            ? field.detail_of
            : [field.detail_of];

        parents.forEach((parentKey) => {
            if (!childrenByParent[parentKey]) {
                childrenByParent[parentKey] = [];
            }

            childrenByParent[parentKey].push(fieldKey);
        });
    });

    const parentsByChild: Record<string, string[]> = {};

    Object.entries(definition.fields).forEach(([fieldKey, field]) => {
        if (!field.detail_of) {
            return;
        }

        parentsByChild[fieldKey] = Array.isArray(field.detail_of)
            ? field.detail_of
            : [field.detail_of];
    });

    const renderedChildren = new Set<string>();

    const isVisible = (field: LoanRequestDataFieldDefinition): boolean => {
        if (!field.visible_when) {
            return true;
        }

        return (
            crossSectionValues[field.visible_when.field] ===
            field.visible_when.equals
        );
    };

    const clearDescendants = (fieldKey: string) => {
        (childrenByParent[fieldKey] ?? []).forEach((childKey) => {
            const otherParents = (parentsByChild[childKey] ?? []).filter(
                (parentKey) => parentKey !== fieldKey,
            );
            const stillHasTrueParent = otherParents.some(
                (parentKey) => values[parentKey] === true,
            );

            if (stillHasTrueParent) {
                return;
            }

            onChange(childKey, null);
            clearDescendants(childKey);
        });
    };

    const renderField = (fieldKey: string, depth = 0): ReactNode => {
        const field = definition.fields[fieldKey];

        if (!field || !isVisible(field)) {
            return null;
        }

        const errorKey = `${sectionKey}.${fieldKey}`;
        const value = values[fieldKey];

        if (field.type === 'boolean') {
            const children =
                value === true
                    ? (childrenByParent[fieldKey] ?? []).filter(
                          (childKey) => !renderedChildren.has(childKey),
                      )
                    : [];
            children.forEach((childKey) => renderedChildren.add(childKey));

            return (
                <div key={fieldKey} className="space-y-3">
                    <div className="grid gap-2 sm:max-w-sm">
                        <Label htmlFor={`${sectionKey}_${fieldKey}`}>
                            {field.label}
                        </Label>
                        <BooleanYesNoField
                            id={`${sectionKey}_${fieldKey}`}
                            value={value}
                            aria-label={field.label}
                            fullWidth
                            onChange={(nextBoolean) => {
                                onChange(fieldKey, nextBoolean);

                                if (nextBoolean !== true) {
                                    clearDescendants(fieldKey);
                                }
                            }}
                        />
                        <InputError message={errors[errorKey]} />
                    </div>
                    {children.length > 0 ? (
                        <div className={childWrapperClassName(depth + 1)}>
                            {children.map((childKey) =>
                                renderField(childKey, depth + 1),
                            )}
                        </div>
                    ) : null}
                </div>
            );
        }

        if (field.type === 'number') {
            return (
                <div key={fieldKey} className="grid gap-2 sm:max-w-xs">
                    <Label htmlFor={`${sectionKey}_${fieldKey}`}>
                        {field.label}
                    </Label>
                    <Input
                        id={`${sectionKey}_${fieldKey}`}
                        type="number"
                        step="0.01"
                        value={value ? `${value}` : ''}
                        onChange={(event) =>
                            onChange(fieldKey, event.target.value)
                        }
                    />
                    <InputError message={errors[errorKey]} />
                </div>
            );
        }

        return (
            <div key={fieldKey} className="grid gap-2">
                <Label htmlFor={`${sectionKey}_${fieldKey}`}>
                    {field.label}
                </Label>
                <textarea
                    id={`${sectionKey}_${fieldKey}`}
                    aria-label={field.label}
                    className={textareaClassName}
                    value={value ? `${value}` : ''}
                    maxLength={1000}
                    onChange={(event) => onChange(fieldKey, event.target.value)}
                />
                <InputError message={errors[errorKey]} />
            </div>
        );
    };

    // The full sequence (real groups + virtual cross-section items) drives
    // the displayed badge number, so a virtual item's number reflects its
    // actual position among everything that comes before it -- not the
    // literal source-form item number it was thematically anchored to.
    const sequence = buildGlapiSequence(definition);

    const badgeNumbers: Record<string, string> = {};

    sequence.forEach((entry, index) => {
        badgeNumbers[entry.number] = String(index + 1);
    });

    const renderedItemNumberSet = new Set(itemNumbers);

    const renderedEntries = sequence.filter((entry) =>
        entry.kind === 'group'
            ? renderedItemNumberSet.has(entry.number)
            : renderedItemNumberSet.has(entry.afterNumber),
    );

    const renderCard = (
        key: string,
        badgeLabel: string | null,
        content: ReactNode,
    ): ReactNode => (
        <div
            key={key}
            className="rounded-lg border border-border/50 bg-card/60 p-4"
        >
            <div className="flex items-start gap-3">
                {badgeLabel ? (
                    <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground">
                        {badgeLabel}
                    </span>
                ) : null}
                <div className="flex-1 space-y-4">{content}</div>
            </div>
        </div>
    );

    const renderItemGroup = (group: GlapiItemGroup): ReactNode => {
        const isCluster = group.fieldKeys.length > 1;
        const heading = isCluster
            ? GLAPI_GROUP_HEADINGS[group.number]
            : undefined;

        const items = group.fieldKeys
            .map((fieldKey) => ({
                fieldKey,
                letter: isCluster ? parseGlapiItem(fieldKey)?.letter : null,
                node: renderField(fieldKey),
            }))
            .filter((item) => item.node !== null);

        if (items.length === 0) {
            return null;
        }

        return renderCard(
            group.fieldKeys[0],
            badgeNumbers[group.number] ?? null,
            <>
                {heading ? (
                    <p className="text-sm font-medium text-foreground">
                        {heading}
                    </p>
                ) : null}
                <div className="space-y-4">
                    {items.map(({ fieldKey, letter, node }) =>
                        letter ? (
                            <div
                                key={fieldKey}
                                className="flex items-start gap-2"
                            >
                                <span className="mt-0.5 text-xs font-semibold text-muted-foreground">
                                    {letter}.
                                </span>
                                <div className="flex-1">{node}</div>
                            </div>
                        ) : (
                            <div key={fieldKey}>{node}</div>
                        ),
                    )}
                </div>
            </>,
        );
    };

    const renderSmokingStatusItem = (): ReactNode => {
        const field = healthDefinition.fields.health_smoking_status;

        if (!field) {
            return null;
        }

        const value = healthValues.health_smoking_status;
        const detailsField = definition.fields.health_smoking_status_details;

        return (
            <div className="space-y-3">
                <div className="grid gap-2">
                    <Label htmlFor="health_health_smoking_status">
                        {field.label}
                    </Label>
                    <SmokingStatusField
                        id="health_health_smoking_status"
                        value={value}
                        aria-label={field.label}
                        onChange={(nextValue) => {
                            onHealthChange('health_smoking_status', nextValue);

                            if (nextValue === null || nextValue === 'none') {
                                onChange('health_smoking_status_details', null);
                            }
                        }}
                    />
                    <InputError
                        message={errors['health.health_smoking_status']}
                    />
                </div>
                {detailsField && value && value !== 'none' ? (
                    <div className={childWrapperClassName(1)}>
                        <div className="grid gap-2">
                            <Label htmlFor="health_glapi_health_smoking_status_details">
                                {detailsField.label}
                            </Label>
                            <textarea
                                id="health_glapi_health_smoking_status_details"
                                aria-label={detailsField.label}
                                className={textareaClassName}
                                value={
                                    values.health_smoking_status_details
                                        ? `${values.health_smoking_status_details}`
                                        : ''
                                }
                                maxLength={1000}
                                onChange={(event) =>
                                    onChange(
                                        'health_smoking_status_details',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={
                                    errors[
                                        'health_glapi.health_smoking_status_details'
                                    ]
                                }
                            />
                        </div>
                    </div>
                ) : null}
            </div>
        );
    };

    const renderHypertensionItem = (): ReactNode => {
        const field = healthDefinition.fields.health_hypertension;

        if (!field) {
            return null;
        }

        const value = healthValues.health_hypertension;
        const detailsField = definition.fields.health_hypertension_details;

        return (
            <div className="space-y-3">
                <div className="grid gap-2 sm:max-w-sm">
                    <Label htmlFor="health_health_hypertension">
                        {field.label}
                    </Label>
                    <BooleanYesNoField
                        id="health_health_hypertension"
                        value={value}
                        aria-label={field.label}
                        fullWidth
                        onChange={(nextValue) => {
                            onHealthChange('health_hypertension', nextValue);

                            if (nextValue !== true) {
                                onChange('health_hypertension_details', null);
                            }
                        }}
                    />
                    <InputError
                        message={errors['health.health_hypertension']}
                    />
                </div>
                {detailsField && value === true ? (
                    <div className={childWrapperClassName(1)}>
                        <div className="grid gap-2">
                            <Label htmlFor="health_glapi_health_hypertension_details">
                                {detailsField.label}
                            </Label>
                            <textarea
                                id="health_glapi_health_hypertension_details"
                                aria-label={detailsField.label}
                                className={textareaClassName}
                                value={
                                    values.health_hypertension_details
                                        ? `${values.health_hypertension_details}`
                                        : ''
                                }
                                maxLength={1000}
                                onChange={(event) =>
                                    onChange(
                                        'health_hypertension_details',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={
                                    errors[
                                        'health_glapi.health_hypertension_details'
                                    ]
                                }
                            />
                        </div>
                    </div>
                ) : null}
            </div>
        );
    };

    const renderVirtualItem = (key: GlapiVirtualItem['key']): ReactNode => {
        const content =
            key === 'health_hypertension'
                ? renderHypertensionItem()
                : renderSmokingStatusItem();

        if (!content) {
            return null;
        }

        return renderCard(key, badgeNumbers[key] ?? null, content);
    };

    return (
        <LoanRequestSectionCard
            title={title}
            description={description}
            contentClassName="space-y-5"
        >
            <div className="space-y-4">
                {renderedEntries.map((entry) =>
                    entry.kind === 'group'
                        ? renderItemGroup(entry.group)
                        : renderVirtualItem(entry.key),
                )}
            </div>
            <Alert className="border-border/50 bg-muted/10">
                <AlertTitle>Member-provided details</AlertTitle>
                <AlertDescription>
                    Answer each item honestly. If you answer "Yes," a details
                    field will appear below it -- please fill it in.
                </AlertDescription>
            </Alert>
        </LoanRequestSectionCard>
    );
}

const PRIMARY_BENEFICIARY_KEYS = [
    'beneficiary_primary_name',
    'beneficiary_primary_relationship',
    'beneficiary_primary_birthdate',
] as const;

const SECONDARY_BENEFICIARY_KEYS = [
    'beneficiary_secondary_name',
    'beneficiary_secondary_relationship',
    'beneficiary_secondary_birthdate',
] as const;

export function LoanRequestInsuranceBeneficiariesStep({
    sectionKey,
    title,
    description,
    values,
    definition,
    errors,
    onChange,
}: DataSectionStepProps) {
    const renderField = (fieldKey: string) => {
        const field = definition.fields[fieldKey];
        if (!field) return null;
        const errorKey = `${sectionKey}.${fieldKey}`;
        const value = values[fieldKey];

        return (
            <div key={fieldKey} className="grid gap-2">
                <Label htmlFor={`${sectionKey}_${fieldKey}`}>
                    {field.label}
                </Label>
                {field.type === 'boolean' ? (
                    <BooleanYesNoField
                        id={`${sectionKey}_${fieldKey}`}
                        value={value}
                        aria-label={field.label}
                        onChange={(nextValue) => onChange(fieldKey, nextValue)}
                    />
                ) : (
                    <Input
                        id={`${sectionKey}_${fieldKey}`}
                        type={field.type === 'date' ? 'date' : 'text'}
                        value={value ? `${value}` : ''}
                        onChange={(event) =>
                            onChange(fieldKey, event.target.value)
                        }
                    />
                )}
                <InputError message={errors[errorKey]} />
            </div>
        );
    };

    return (
        <LoanRequestSectionCard
            title={title}
            description={description}
            contentClassName="space-y-5"
        >
            <div className="space-y-3">
                <p className="text-sm font-semibold text-foreground">
                    Primary beneficiary
                </p>
                <div className="grid gap-4 md:grid-cols-2">
                    {PRIMARY_BENEFICIARY_KEYS.map(renderField)}
                </div>
            </div>
            <Separator className="bg-border/40" />
            <div className="space-y-3">
                <div className="flex items-baseline gap-2">
                    <p className="text-sm font-semibold text-foreground">
                        Secondary beneficiary
                    </p>
                    <span className="text-xs text-muted-foreground">
                        (Optional)
                    </span>
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    {SECONDARY_BENEFICIARY_KEYS.map(renderField)}
                </div>
            </div>
            <Alert className="border-border/50 bg-muted/10">
                <AlertTitle>Member-provided details</AlertTitle>
                <AlertDescription>
                    Complete the applicable fields in this section before
                    submitting the request for processing.
                </AlertDescription>
            </Alert>
        </LoanRequestSectionCard>
    );
}

type DependentsStepProps = {
    sectionKey: 'dependents';
    title: string;
    description: string;
    values: LoanRequestDataSectionValues;
    definition: LoanRequestDataSectionDefinition;
    errors: Record<string, string | undefined>;
    crossSectionValues: Record<string, LoanRequestDataFieldValue>;
    onChange: (field: string, value: string | number | boolean | null) => void;
    hasExistingProfileData: boolean;
};

function isDependentCategoryVisible(
    category: DependentCategoryConfig,
    definition: LoanRequestDataSectionDefinition,
    crossSectionValues: Record<string, LoanRequestDataFieldValue>,
): boolean {
    const firstField = definition.fields[slotFieldKey(category.key, 1, 'name')];

    if (!firstField?.visible_when) {
        return true;
    }

    return (
        crossSectionValues[firstField.visible_when.field] ===
        firstField.visible_when.equals
    );
}

function isDependentSpouseVisible(
    definition: LoanRequestDataSectionDefinition,
    crossSectionValues: Record<string, LoanRequestDataFieldValue>,
): boolean {
    const field = definition.fields[SPOUSE_CYCLE_STATUS_KEY];

    if (!field?.visible_when) {
        return true;
    }

    return (
        crossSectionValues[field.visible_when.field] ===
        field.visible_when.equals
    );
}

/**
 * Dependents (Form B). Fixed slots per category (see
 * LoanRequestDataService::FIELD_DEFINITIONS), rendered with add/remove-row
 * UX so members aren't shown every slot at once. Each category (and the
 * Spouse singleton) is gated by civil_status via visible_when, same
 * mechanism as the GLAPI pregnancy question -- see
 * LoanRequestHealthQuestionnaireStep: Spouse/Children show for Married
 * members, Siblings/Parents show for Single members, Extended is ungated.
 *
 * Members with existing profile data (dependentsPrefilledFromProfile) get a
 * compact read-only summary instead of the full form -- editing happens in
 * Settings > Dependents only, this step is read-and-confirm for them.
 */
export function LoanRequestDependentsStep({
    title,
    description,
    values,
    definition,
    errors,
    crossSectionValues,
    onChange,
    hasExistingProfileData,
}: DependentsStepProps) {
    const [forceEditable, setForceEditable] = useState(false);
    const visibleCategories = DEPENDENT_CATEGORIES.filter((category) =>
        isDependentCategoryVisible(category, definition, crossSectionValues),
    );
    const spouseVisible = isDependentSpouseVisible(
        definition,
        crossSectionValues,
    );
    const spouseCycleStatus = values[SPOUSE_CYCLE_STATUS_KEY];
    const spouseCycleNumber = values[SPOUSE_CYCLE_NUMBER_KEY];
    const hasSpouseCycleData = spouseVisible && Boolean(spouseCycleStatus);

    if (hasExistingProfileData && !forceEditable) {
        const summaries = summarizeDependents(visibleCategories, values);
        const summaryCounts = summaries.map(({ category, count }) => {
            const label =
                count === 1
                    ? category.label
                    : dependentCategoryPluralLabel(category);

            return `${count} ${label.toLowerCase()}`;
        });

        if (hasSpouseCycleData) {
            summaryCounts.unshift('Spouse');
        }

        const totalLabel =
            summaryCounts.length > 0
                ? summaryCounts.join(', ') + ' on file'
                : 'No dependents on file';

        // Cycle status is now required on submit, but this on-file profile
        // data may predate that requirement -- the read-only summary below
        // has no inputs to attach a validation error to, so surface it here
        // instead of leaving the member stuck on a silent submit failure.
        const missingCycleStatusNames: string[] = [];

        if (spouseVisible && !spouseCycleStatus) {
            missingCycleStatusNames.push('Spouse');
        }

        summaries.forEach(({ rows }) => {
            rows.forEach((row) => {
                if (!row.cycleStatus) {
                    missingCycleStatusNames.push(row.name);
                }
            });
        });

        return (
            <LoanRequestSectionCard
                title={title}
                description={description}
                contentClassName="space-y-6"
            >
                {missingCycleStatusNames.length > 0 ? (
                    <Alert variant="destructive">
                        <AlertTitle>
                            Missing required coverage status
                        </AlertTitle>
                        <AlertDescription>
                            {missingCycleStatusNames.join(', ')}{' '}
                            {missingCycleStatusNames.length === 1
                                ? 'is'
                                : 'are'}{' '}
                            missing a group life coverage status (New/Old).{' '}
                            <button
                                type="button"
                                onClick={() => setForceEditable(true)}
                                className="font-medium underline underline-offset-2"
                            >
                                Edit here
                            </button>{' '}
                            to complete it before submitting.
                        </AlertDescription>
                    </Alert>
                ) : null}
                <div className="space-y-4">
                    <p className="text-sm font-medium text-foreground">
                        {totalLabel}
                    </p>
                    <div className="space-y-3">
                        {hasSpouseCycleData ? (
                            <Card className="gap-2 py-3">
                                <CardContent className="flex items-center justify-between gap-2 px-4 text-sm">
                                    <span className="font-semibold text-foreground">
                                        Spouse
                                    </span>
                                    <Badge
                                        variant="outline"
                                        className="font-normal"
                                    >
                                        {spouseCycleStatus === 'Old' &&
                                        spouseCycleNumber
                                            ? `Old · cycle ${spouseCycleNumber}`
                                            : `${spouseCycleStatus}`}
                                    </Badge>
                                </CardContent>
                            </Card>
                        ) : null}
                        {summaries.map(({ category, rows }) => (
                            <Card key={category.key} className="gap-2 py-3">
                                <CardContent className="space-y-1.5 px-4">
                                    <p className="text-sm font-semibold text-foreground">
                                        {dependentCategoryPluralLabel(category)}
                                    </p>
                                    {rows.map((row, index) => (
                                        <div
                                            key={`${category.key}-${index}`}
                                            className="flex items-center justify-between gap-2 text-sm"
                                        >
                                            <span className="text-muted-foreground">
                                                {row.name}
                                            </span>
                                            {row.cycleStatus ? (
                                                <Badge
                                                    variant="outline"
                                                    className="font-normal"
                                                >
                                                    {row.cycleStatus}
                                                </Badge>
                                            ) : null}
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setForceEditable(true)}
                    >
                        Edit here
                    </Button>
                </div>
            </LoanRequestSectionCard>
        );
    }

    return (
        <LoanRequestSectionCard
            title={title}
            description={description}
            contentClassName="space-y-6"
        >
            {spouseVisible ? (
                <DependentSpouseCycleSection
                    values={values}
                    errors={errors}
                    errorKeyPrefix="dependents"
                    onChange={onChange}
                />
            ) : null}
            {visibleCategories.map((category) => (
                <DependentCategorySection
                    key={category.key}
                    category={category}
                    values={values}
                    errors={errors}
                    errorKeyPrefix="dependents"
                    onChange={onChange}
                />
            ))}
        </LoanRequestSectionCard>
    );
}

export function LoanRequestReviewStep({
    data,
    loanTypes,
    member,
    errors,
    sectionDefinitions,
    onUndertakingChange,
}: ReviewStepProps) {
    const loanTypeLabel =
        loanTypes.find((type) => type.typecode === data.typecode)?.label ??
        data.typecode;
    const loanTypeAbbreviation = resolveLoanTypeAbbreviation(
        loanTypeLabel,
        data.kind_of_loan,
    );
    const requestedAmount =
        data.requested_amount !== ''
            ? formatCurrency(Number(data.requested_amount))
            : '--';

    const loanSummary: SummaryItem[] = [
        {
            label: 'Loan type',
            value: loanTypeAbbreviation
                ? `${displayText(loanTypeLabel || '')} (${loanTypeAbbreviation})`
                : displayText(loanTypeLabel || ''),
        },
        ...(isMicroBusinessLoanLabel(loanTypeLabel)
            ? [
                  {
                      label: 'Kind of loan',
                      value: displayValue(data.kind_of_loan),
                  },
              ]
            : []),
        ...(data.typecode === OTHER_LOAN_TYPECODE
            ? [
                  {
                      label: 'Loan name',
                      value: displayText(data.other_loan_type_name),
                  },
              ]
            : []),
        { label: 'Requested amount', value: requestedAmount },
        {
            label: 'Requested term',
            value:
                data.requested_term.trim() !== ''
                    ? `${data.requested_term} months`
                    : '--',
        },
        {
            label: 'Availment status',
            value: displayValue(data.availment_status),
        },
        { label: 'Loan purpose', value: displayText(data.loan_purpose) },
        ...(data.requested_payment_frequency
            ? [
                  {
                      label: 'Requested repayment frequency',
                      value:
                          data.requested_payment_frequency === 'Due date'
                              ? `Due date (${data.requested_term || '--'} month${data.requested_term === '1' ? '' : 's'})`
                              : displayText(data.requested_payment_frequency),
                  },
              ]
            : []),
    ];

    const applicantPersonal: SummaryItem[] = [
        { label: 'Applicant name', value: displayName(data.applicant) },
        { label: 'Nickname', value: displayText(data.applicant.nickname) },
        { label: 'Birthdate', value: displayValue(data.applicant.birthdate) },
        {
            label: 'Birthplace',
            value: displayText(resolveBirthplace(data.applicant)),
        },
        {
            label: 'Address',
            value: displayText(resolveAddress(data.applicant)),
        },
        {
            label: 'Length of stay',
            value: displayText(data.applicant.length_of_stay),
        },
        {
            label: 'Housing status',
            value: formatHousingStatus(data.applicant.housing_status),
        },
        { label: 'Cell no.', value: displayValue(data.applicant.cell_no) },
        {
            label: 'Civil status',
            value: formatCivilStatus(data.applicant.civil_status),
        },
        { label: 'Sex', value: displayText(data.applicant.sex) },
        {
            label: 'Educational attainment',
            value: displayText(data.applicant.educational_attainment),
        },
        {
            label: 'No. of children',
            value: displayValue(data.applicant.number_of_children),
        },
        {
            label: 'Spouse name',
            value: displayText(data.applicant.spouse_name),
        },
        {
            label: 'Spouse age',
            value: displayValue(
                calculateAge(data.applicant.spouse_birthdate)?.toString() ?? '',
            ),
        },
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
            value: displayText(data.applicant.employer_business_name),
        },
        {
            label: 'Employer/Business address',
            value: displayText(resolveEmployerBusinessAddress(data.applicant)),
        },
        {
            label: 'Telephone no.',
            value: displayValue(data.applicant.telephone_no),
        },
        {
            label: 'Current position',
            value: displayText(data.applicant.current_position),
        },
        {
            label: 'Nature of business',
            value: displayText(data.applicant.nature_of_business),
        },
        {
            label: 'Years in work/business',
            value: displayText(data.applicant.years_in_work_business),
        },
        {
            label: 'Gross monthly income',
            value:
                data.applicant.gross_monthly_income.trim() !== ''
                    ? formatCurrency(
                          Number(data.applicant.gross_monthly_income),
                      )
                    : '--',
        },
        { label: 'Payday', value: formatPayday(data.applicant.payday) },
    ];

    const buildCoMakerSummary = (
        label: string,
        person: LoanRequestPersonFormData,
    ): SummaryItem[] => [
        { label: `${label} name`, value: displayName(person) },
        { label: 'Nickname', value: displayText(person.nickname) },
        { label: 'Birthdate', value: displayValue(person.birthdate) },
        { label: 'Birthplace', value: displayText(resolveBirthplace(person)) },
        { label: 'Address', value: displayText(resolveAddress(person)) },
        { label: 'Length of stay', value: displayText(person.length_of_stay) },
        { label: 'Cell no.', value: displayValue(person.cell_no) },
        {
            label: 'Civil status',
            value: formatCivilStatus(person.civil_status),
        },
        { label: 'Sex', value: displayText(person.sex) },
        {
            label: 'Educational attainment',
            value: displayText(person.educational_attainment),
        },
        {
            label: 'No. of children',
            value: displayValue(person.number_of_children),
        },
        { label: 'Spouse name', value: displayText(person.spouse_name) },
        {
            label: 'Spouse age',
            value: displayValue(
                calculateAge(person.spouse_birthdate)?.toString() ?? '',
            ),
        },
        {
            label: 'Spouse cell no.',
            value: displayValue(person.spouse_cell_no),
        },
        {
            label: 'Employment type',
            value: displayValue(person.employment_type),
        },
        {
            label: 'Employer/Business name',
            value: displayText(person.employer_business_name),
        },
        {
            label: 'Employer/Business address',
            value: displayText(resolveEmployerBusinessAddress(person)),
        },
        { label: 'Telephone no.', value: displayValue(person.telephone_no) },
        {
            label: 'Current position',
            value: displayText(person.current_position),
        },
        {
            label: 'Nature of business',
            value: displayText(person.nature_of_business),
        },
        {
            label: 'Years in work/business',
            value: displayText(person.years_in_work_business),
        },
        {
            label: 'Gross monthly income',
            value:
                person.gross_monthly_income.trim() !== ''
                    ? formatCurrency(Number(person.gross_monthly_income))
                    : '--',
        },
        { label: 'Payday', value: formatPayday(person.payday) },
    ];

    // Mirrors the edit step's ATM/Bank-Transfer field visibility (see
    // BankingSectionFields above) so the review card doesn't list fields
    // that don't apply to the chosen release method / payment option as
    // "--". release_method and payment_option are shown as badges instead
    // of grid rows (see the banking SummaryCard below), so both are hidden
    // here.
    const isBankingFieldApplicable = (
        fieldKey: string,
        releaseMethod: string,
        paymentOption: string,
    ): boolean => {
        if (fieldKey === 'release_method' || fieldKey === 'payment_option') {
            return false;
        }

        if (
            fieldKey === 'release_saved_account_id' ||
            fieldKey === 'payment_saved_account_id'
        ) {
            return false;
        }

        if (
            fieldKey === 'payout_bank_branch' ||
            fieldKey === 'payout_atm_number' ||
            fieldKey === 'payout_atm_holder_name'
        ) {
            return releaseMethod === 'ATM';
        }

        if (
            fieldKey === 'payout_bank_name' ||
            fieldKey === 'payout_account_name' ||
            fieldKey === 'payout_account_number' ||
            fieldKey === 'payout_account_type'
        ) {
            return releaseMethod === 'ATM' || releaseMethod === 'Bank Transfer';
        }

        if (
            fieldKey === 'payment_bank_name' ||
            fieldKey === 'payment_account_name' ||
            fieldKey === 'payment_account_number' ||
            fieldKey === 'payment_account_type' ||
            fieldKey === 'payment_bank_branch' ||
            fieldKey === 'payment_atm_number' ||
            fieldKey === 'payment_atm_holder_name'
        ) {
            return paymentOption === 'ATM Deduction';
        }

        return true;
    };

    type ReviewSectionKey =
        | 'insurance'
        | 'health'
        | 'health_glapi'
        | 'banking'
        | 'declarations';

    // Splits a section's fields into a numbered Q&A list (a question paired
    // with its conditional follow-up, identified the same way as the entry
    // step's `detail_of` metadata) and a plain grid for fields with no
    // question/answer shape (beneficiary info, bank account numbers, rate
    // settings, etc). Merges field definitions/values across sectionKeys
    // first so a question and a details field stored in different sections
    // (e.g. health / health_glapi) still pair up.
    const buildSectionData = (sectionKeys: ReviewSectionKey[]) => {
        const fields: Record<string, LoanRequestDataFieldDefinition> = {};
        const values: Record<string, LoanRequestDataFieldValue> = {};

        sectionKeys.forEach((sectionKey) => {
            Object.assign(fields, sectionDefinitions[sectionKey]?.fields ?? {});
            Object.assign(values, data[sectionKey]);
        });

        const applicableFieldKeys = new Set(
            Object.keys(fields).filter(
                (fieldKey) =>
                    !sectionKeys.includes('banking') ||
                    isBankingFieldApplicable(
                        fieldKey,
                        `${data.banking.release_method ?? ''}`,
                        `${data.banking.payment_option ?? ''}`,
                    ),
            ),
        );

        const childrenByParent: Record<string, string[]> = {};

        Object.entries(fields).forEach(([fieldKey, field]) => {
            if (!field.detail_of || !applicableFieldKeys.has(fieldKey)) {
                return;
            }

            const parents = Array.isArray(field.detail_of)
                ? field.detail_of
                : [field.detail_of];

            parents.forEach((parentKey) => {
                if (!childrenByParent[parentKey]) {
                    childrenByParent[parentKey] = [];
                }

                childrenByParent[parentKey].push(fieldKey);
            });
        });

        const buildChildren = (parentKey: string): QaEntry[] =>
            (childrenByParent[parentKey] ?? [])
                .map((childKey): QaEntry | null => {
                    const childField = fields[childKey];
                    const rawValue = values[childKey];

                    if (
                        !childField ||
                        rawValue === null ||
                        rawValue === undefined ||
                        rawValue === ''
                    ) {
                        return null;
                    }

                    return {
                        fieldKey: childKey,
                        label: childField.label,
                        answer: displaySectionValue(rawValue, childField),
                        children: buildChildren(childKey),
                    };
                })
                .filter((entry): entry is QaEntry => entry !== null);

        const questions: QaEntry[] = [];
        const plainItems: SummaryItem[] = [];

        Object.keys(fields)
            .filter(
                (fieldKey) =>
                    applicableFieldKeys.has(fieldKey) &&
                    !fields[fieldKey].detail_of,
            )
            .forEach((fieldKey) => {
                const field = fields[fieldKey];
                const hasChildren =
                    (childrenByParent[fieldKey] ?? []).length > 0;

                if (hasChildren || field.type === 'boolean') {
                    questions.push({
                        fieldKey,
                        label: field.label,
                        answer: displaySectionValue(values[fieldKey], field),
                        children: buildChildren(fieldKey),
                    });
                } else {
                    plainItems.push({
                        label: field.label,
                        value: displaySectionValue(values[fieldKey], field),
                    });
                }
            });

        return { questions, plainItems };
    };

    // 'health' and 'health_glapi' render as a single merged "Health Insurance
    // Questionnaire" card — there is no separate "Health declarations" concept.
    const dataSectionSummaries = (
        [
            ['insurance'],
            ['health', 'health_glapi'],
            ['banking'],
            ['declarations'],
        ] as ReviewSectionKey[][]
    ).map((sectionKeys) => ({
        key: sectionKeys[0],
        title: sectionDefinitions[sectionKeys[0]]?.label ?? sectionKeys[0],
        ...buildSectionData(sectionKeys),
    }));

    const dependentSummaries = summarizeDependents(
        DEPENDENT_CATEGORIES,
        data.dependents,
    );
    const spouseCycleStatus = data.dependents[SPOUSE_CYCLE_STATUS_KEY];
    const spouseCycleNumber = data.dependents[SPOUSE_CYCLE_NUMBER_KEY];
    const spouseCycleLabel =
        spouseCycleStatus === 'Old' && spouseCycleNumber
            ? `Old · cycle ${spouseCycleNumber}`
            : spouseCycleStatus
              ? `${spouseCycleStatus}`
              : '';
    const hasDependentsData =
        dependentSummaries.length > 0 || Boolean(spouseCycleStatus);

    return (
        <LoanRequestSectionCard
            title="Review & undertaking"
            description="Review your application before submitting."
            contentClassName="space-y-5"
        >
            <div className="rounded-lg border border-border/50 bg-muted/20 p-4 text-sm">
                <p className="text-xs text-muted-foreground uppercase">
                    Member
                </p>
                <p className="mt-2 font-medium">{displayText(member.name)}</p>
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

            <Accordion type="multiple" className="space-y-3">
                <AccordionSummaryCard
                    value="co_maker_1"
                    title="Co-maker 1"
                    description="Review the proposed details for your first co-maker."
                >
                    <SummaryGrid
                        items={buildCoMakerSummary(
                            'Co-maker 1',
                            data.co_maker_1,
                        )}
                    />
                </AccordionSummaryCard>

                <AccordionSummaryCard
                    value="co_maker_2"
                    title="Co-maker 2"
                    description="Review the proposed details for your second co-maker."
                >
                    <SummaryGrid
                        items={buildCoMakerSummary(
                            'Co-maker 2',
                            data.co_maker_2,
                        )}
                    />
                </AccordionSummaryCard>

                <AccordionSummaryCard
                    value="dependents"
                    title="Dependents"
                    description="Review the dependents on file for this application."
                >
                    {hasDependentsData ? (
                        <div className="space-y-4">
                            {spouseCycleStatus ? (
                                <div className="space-y-1">
                                    <p className="text-sm font-medium">
                                        Spouse (group life coverage)
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {displayText(spouseCycleLabel)}
                                    </p>
                                </div>
                            ) : null}
                            {dependentSummaries.map(({ category, rows }) => (
                                <div key={category.key} className="space-y-2">
                                    <p className="text-sm font-medium">
                                        {dependentCategoryPluralLabel(category)}
                                    </p>
                                    <ul className="space-y-1">
                                        {rows.map((row) => (
                                            <li
                                                key={row.name}
                                                className="text-sm text-muted-foreground"
                                            >
                                                {row.name}
                                                {row.cycleStatus
                                                    ? ` — ${row.cycleStatus}`
                                                    : ''}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No dependents added.
                        </p>
                    )}
                </AccordionSummaryCard>

                {dataSectionSummaries.map((section) => (
                    <AccordionSummaryCard
                        key={section.key}
                        value={section.key}
                        title={section.title}
                        description="Review the member-provided document details."
                    >
                        {section.key === 'banking' ? (
                            <div className="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-muted-foreground">
                                        Release method:
                                    </span>
                                    <Badge variant="secondary">
                                        {displayText(
                                            data.banking.release_method
                                                ? `${data.banking.release_method}`
                                                : null,
                                        )}
                                    </Badge>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-muted-foreground">
                                        Payment option:
                                    </span>
                                    <Badge variant="secondary">
                                        {displayText(
                                            data.banking.payment_option
                                                ? `${data.banking.payment_option}`
                                                : null,
                                        )}
                                    </Badge>
                                </div>
                            </div>
                        ) : null}
                        {section.questions.length > 0 ? (
                            <QuestionAnswerList items={section.questions} />
                        ) : null}
                        {section.plainItems.length > 0 ? (
                            <SummaryGrid items={section.plainItems} />
                        ) : null}
                    </AccordionSummaryCard>
                ))}
            </Accordion>

            <Alert className="border-border/50 bg-muted/10">
                <AlertTitle>Physical signatures</AlertTitle>
                <AlertDescription>
                    Signatures will be collected physically upon loan release.
                </AlertDescription>
            </Alert>

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
