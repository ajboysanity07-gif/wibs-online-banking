import { useState, type ReactNode } from 'react';

import {
    ApplicantCycleSection,
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
import { AtmHolderCheckboxField } from '@/components/loan-request/atm-holder-checkbox-field';
import { BooleanYesNoField } from '@/components/loan-request/boolean-yes-no-field';
import {
    LoanRequestPersonalFields,
    LoanRequestWorkFields,
} from '@/components/loan-request/loan-request-fields';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import {
    CurrencyInput,
    MonthsInput,
} from '@/components/loan-request/numeric-adorned-inputs';
import { SmokingStatusField } from '@/components/loan-request/smoking-status-field';
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
import {
    composeAddress,
    composeBirthplace,
    formatCivilStatus,
    formatCurrency,
    formatDateTime,
    formatDisplayText,
    formatHousingStatus,
    formatPayday,
} from '@/lib/formatters';
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
const ACCOUNT_TYPE_OPTIONS = ['Savings', 'Checking'] as const;

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

// Payout (release) field -> its payment (repayment) counterpart, used by
// the "use the same details" checkbox to mirror values live while checked.
const PAYOUT_TO_PAYMENT_MIRROR_FIELDS: [string, string][] = [
    ['payout_bank_name', 'payment_bank_name'],
    ['payout_account_name', 'payment_account_name'],
    ['payout_account_number', 'payment_account_number'],
    ['payout_account_type', 'payment_account_type'],
    ['payout_atm_number', 'payment_atm_number'],
    ['payout_bank_branch', 'payment_bank_branch'],
    ['payout_atm_holder_name', 'payment_atm_holder_name'],
];

type BankingSectionFieldsProps = {
    sectionKey: string;
    definition: LoanRequestDataSectionDefinition;
    values: LoanRequestDataSectionValues;
    errors: Record<string, string | undefined>;
    onChange: (field: string, value: string | number | boolean | null) => void;
    applicantFullName?: string;
};

// Two full-width, clearly headed subsections -- Loan Disbursement (release
// method) and Repayment Method (payment option) -- each with its own
// conditional bank/ATM detail fields. Kept separate from the generic
// per-field grid below since it needs cross-field behavior (the "use same
// details" checkbox) that a flat field map can't express.
function BankingSectionFields({
    sectionKey,
    definition,
    values,
    errors,
    onChange,
    applicantFullName,
}: BankingSectionFieldsProps) {
    const releaseMethod = values.release_method
        ? `${values.release_method}`
        : '';
    const paymentOption = values.payment_option
        ? `${values.payment_option}`
        : '';
    const showReleaseBankFields =
        releaseMethod === 'ATM' || releaseMethod === 'Bank Transfer';
    const showReleaseAtmFields = releaseMethod === 'ATM';
    const showPaymentAtmFields = paymentOption === 'ATM Deduction';
    const canUseSameDetails =
        releaseMethod === 'ATM' && paymentOption === 'ATM Deduction';

    const [useSameDetailsChecked, setUseSameDetailsChecked] = useState(false);
    // Derived rather than synced via effect: once the checkbox's
    // precondition (both channels ATM) stops holding, it should just read
    // as unchecked again without a render-triggering effect.
    const useSameDetails = canUseSameDetails && useSameDetailsChecked;

    const handleReleaseFieldChange = (fieldKey: string, value: string) => {
        onChange(fieldKey, value);

        if (useSameDetails) {
            const mirror = PAYOUT_TO_PAYMENT_MIRROR_FIELDS.find(
                ([payoutField]) => payoutField === fieldKey,
            );

            if (mirror) {
                onChange(mirror[1], value);
            }
        }
    };

    const handleUseSameDetailsChange = (checked: boolean) => {
        setUseSameDetailsChecked(checked);

        if (checked) {
            PAYOUT_TO_PAYMENT_MIRROR_FIELDS.forEach(
                ([payoutField, paymentField]) => {
                    onChange(paymentField, values[payoutField] ?? '');
                },
            );
        }
    };

    const textField = (
        fieldKey: string,
        onFieldChange: (value: string) => void,
        disabled = false,
    ) => {
        const field = definition.fields[fieldKey];

        if (!field) {
            return null;
        }

        return (
            <div key={fieldKey} className="grid gap-2">
                <Label htmlFor={`${sectionKey}_${fieldKey}`}>
                    {field.label}
                </Label>
                <Input
                    id={`${sectionKey}_${fieldKey}`}
                    value={values[fieldKey] ? `${values[fieldKey]}` : ''}
                    disabled={disabled}
                    onChange={(event) => onFieldChange(event.target.value)}
                />
                <InputError message={errors[`${sectionKey}.${fieldKey}`]} />
            </div>
        );
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

                <div className="grid gap-2">
                    <Label htmlFor={`${sectionKey}_release_method`}>
                        {definition.fields.release_method?.label ??
                            'Release method'}
                    </Label>
                    <Select
                        value={releaseMethod || undefined}
                        onValueChange={(nextValue) =>
                            handleReleaseFieldChange(
                                'release_method',
                                nextValue,
                            )
                        }
                    >
                        <SelectTrigger id={`${sectionKey}_release_method`}>
                            <SelectValue placeholder="Select release method" />
                        </SelectTrigger>
                        <SelectContent>
                            {RELEASE_METHOD_OPTIONS.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError
                        message={errors[`${sectionKey}.release_method`]}
                    />
                </div>

                {showReleaseBankFields && (
                    <div className="grid gap-4 md:grid-cols-2">
                        {textField('payout_bank_name', (v) =>
                            handleReleaseFieldChange('payout_bank_name', v),
                        )}
                        {textField('payout_account_name', (v) =>
                            handleReleaseFieldChange('payout_account_name', v),
                        )}
                        {textField('payout_account_number', (v) =>
                            handleReleaseFieldChange(
                                'payout_account_number',
                                v,
                            ),
                        )}
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`${sectionKey}_payout_account_type`}
                            >
                                {definition.fields.payout_account_type?.label ??
                                    'Account type'}
                            </Label>
                            <Select
                                value={
                                    values.payout_account_type
                                        ? `${values.payout_account_type}`
                                        : undefined
                                }
                                onValueChange={(nextValue) =>
                                    handleReleaseFieldChange(
                                        'payout_account_type',
                                        nextValue,
                                    )
                                }
                            >
                                <SelectTrigger
                                    id={`${sectionKey}_payout_account_type`}
                                >
                                    <SelectValue placeholder="Select account type" />
                                </SelectTrigger>
                                <SelectContent>
                                    {ACCOUNT_TYPE_OPTIONS.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError
                                message={
                                    errors[`${sectionKey}.payout_account_type`]
                                }
                            />
                        </div>
                    </div>
                )}

                {showReleaseAtmFields && (
                    <div className="grid gap-4 md:grid-cols-2">
                        {textField('payout_bank_branch', (v) =>
                            handleReleaseFieldChange('payout_bank_branch', v),
                        )}
                        {textField('payout_atm_number', (v) =>
                            handleReleaseFieldChange('payout_atm_number', v),
                        )}
                        <AtmHolderCheckboxField
                            id={`${sectionKey}_payout_atm_holder_name`}
                            label={
                                definition.fields.payout_atm_holder_name
                                    ?.label ?? 'ATM card holder name'
                            }
                            value={
                                values.payout_atm_holder_name
                                    ? `${values.payout_atm_holder_name}`
                                    : ''
                            }
                            applicantFullName={applicantFullName ?? ''}
                            error={
                                errors[`${sectionKey}.payout_atm_holder_name`]
                            }
                            onChange={(v) =>
                                handleReleaseFieldChange(
                                    'payout_atm_holder_name',
                                    v,
                                )
                            }
                        />
                    </div>
                )}
            </div>

            <Separator />

            <div className="space-y-4">
                <div>
                    <h4 className="text-sm font-semibold">Repayment Method</h4>
                    <p className="text-xs text-muted-foreground">
                        How your loan installments will be collected.
                    </p>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`${sectionKey}_payment_option`}>
                        {definition.fields.payment_option?.label ??
                            'Payment option'}
                    </Label>
                    <Select
                        value={paymentOption || undefined}
                        onValueChange={(nextValue) =>
                            onChange('payment_option', nextValue)
                        }
                    >
                        <SelectTrigger id={`${sectionKey}_payment_option`}>
                            <SelectValue placeholder="Select payment option" />
                        </SelectTrigger>
                        <SelectContent>
                            {PAYMENT_OPTION_OPTIONS.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError
                        message={errors[`${sectionKey}.payment_option`]}
                    />
                </div>

                {canUseSameDetails && (
                    <div className="flex items-start gap-3 rounded-md border border-border/50 bg-muted/10 p-3">
                        <Checkbox
                            id={`${sectionKey}_use_same_payment_details`}
                            checked={useSameDetails}
                            onCheckedChange={(checked) =>
                                handleUseSameDetailsChange(checked === true)
                            }
                        />
                        <Label
                            htmlFor={`${sectionKey}_use_same_payment_details`}
                            className="text-sm leading-snug font-normal"
                        >
                            Use the same ATM/bank details for repayment as for
                            release
                        </Label>
                    </div>
                )}

                {showPaymentAtmFields && (
                    <>
                        <div className="grid gap-4 md:grid-cols-2">
                            {textField(
                                'payment_bank_name',
                                (v) => onChange('payment_bank_name', v),
                                useSameDetails,
                            )}
                            {textField(
                                'payment_account_name',
                                (v) => onChange('payment_account_name', v),
                                useSameDetails,
                            )}
                            {textField(
                                'payment_account_number',
                                (v) => onChange('payment_account_number', v),
                                useSameDetails,
                            )}
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`${sectionKey}_payment_account_type`}
                                >
                                    {definition.fields.payment_account_type
                                        ?.label ?? 'Account type'}
                                </Label>
                                <Select
                                    value={
                                        values.payment_account_type
                                            ? `${values.payment_account_type}`
                                            : undefined
                                    }
                                    disabled={useSameDetails}
                                    onValueChange={(nextValue) =>
                                        onChange(
                                            'payment_account_type',
                                            nextValue,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id={`${sectionKey}_payment_account_type`}
                                    >
                                        <SelectValue placeholder="Select account type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {ACCOUNT_TYPE_OPTIONS.map((option) => (
                                            <SelectItem
                                                key={option}
                                                value={option}
                                            >
                                                {option}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={
                                        errors[
                                            `${sectionKey}.payment_account_type`
                                        ]
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            {textField(
                                'payment_bank_branch',
                                (v) => onChange('payment_bank_branch', v),
                                useSameDetails,
                            )}
                            {textField(
                                'payment_atm_number',
                                (v) => onChange('payment_atm_number', v),
                                useSameDetails,
                            )}
                            <AtmHolderCheckboxField
                                id={`${sectionKey}_payment_atm_holder_name`}
                                label={
                                    definition.fields.payment_atm_holder_name
                                        ?.label ?? 'ATM card holder name'
                                }
                                value={
                                    values.payment_atm_holder_name
                                        ? `${values.payment_atm_holder_name}`
                                        : ''
                                }
                                applicantFullName={applicantFullName ?? ''}
                                error={
                                    errors[
                                        `${sectionKey}.payment_atm_holder_name`
                                    ]
                                }
                                disabled={useSameDetails}
                                onChange={(v) =>
                                    onChange('payment_atm_holder_name', v)
                                }
                            />
                        </div>
                    </>
                )}
            </div>
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
                {/* Applicant's own cycle status isn't part of
                MemberDependentProfile, so there's no Settings equivalent to
                defer to here -- it stays editable even when the rest of this
                step is a read-only profile summary. */}
                <ApplicantCycleSection
                    values={values}
                    errors={errors}
                    errorKeyPrefix="dependents"
                    onChange={onChange}
                />
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
            <ApplicantCycleSection
                values={values}
                errors={errors}
                errorKeyPrefix="dependents"
                onChange={onChange}
            />
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
    const requestedAmount =
        data.requested_amount !== ''
            ? formatCurrency(Number(data.requested_amount))
            : '--';

    const loanSummary: SummaryItem[] = [
        { label: 'Loan type', value: displayText(loanTypeLabel || '') },
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
            label: 'Educational attainment',
            value: displayText(person.educational_attainment),
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

    const buildSectionItems = (
        sectionKey:
            | 'insurance'
            | 'health'
            | 'health_glapi'
            | 'banking'
            | 'declarations',
    ) =>
        Object.entries(sectionDefinitions[sectionKey]?.fields ?? {})
            .filter(
                ([fieldKey]) =>
                    sectionKey !== 'banking' ||
                    isBankingFieldApplicable(
                        fieldKey,
                        `${data.banking.release_method ?? ''}`,
                        `${data.banking.payment_option ?? ''}`,
                    ),
            )
            .map(([fieldKey, field]) => ({
                label: field.label,
                value: displaySectionValue(data[sectionKey][fieldKey], field),
            }));

    // 'health' and 'health_glapi' render as a single merged "Health Insurance
    // Questionnaire" card — there is no separate "Health declarations" concept.
    const dataSectionSummaries = (
        [
            ['insurance'],
            ['health', 'health_glapi'],
            ['banking'],
            ['declarations'],
        ] as const
    ).map((sectionKeys) => ({
        key: sectionKeys[0],
        title: sectionDefinitions[sectionKeys[0]]?.label ?? sectionKeys[0],
        items: sectionKeys.flatMap((sectionKey) =>
            buildSectionItems(sectionKey),
        ),
    }));

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

            <SummaryCard
                title="Co-maker 1"
                description="Review the proposed details for your first co-maker."
            >
                <SummaryGrid
                    items={buildCoMakerSummary('Co-maker 1', data.co_maker_1)}
                />
            </SummaryCard>

            <SummaryCard
                title="Co-maker 2"
                description="Review the proposed details for your second co-maker."
            >
                <SummaryGrid
                    items={buildCoMakerSummary('Co-maker 2', data.co_maker_2)}
                />
            </SummaryCard>

            {dataSectionSummaries.map((section) => (
                <SummaryCard
                    key={section.key}
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
                    <SummaryGrid items={section.items} />
                </SummaryCard>
            ))}

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
