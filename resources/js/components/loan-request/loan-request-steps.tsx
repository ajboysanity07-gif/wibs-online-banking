import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';

import InputError from '@/components/input-error';
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
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
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
                        onChange={(value) =>
                            onChange('requested_term', value)
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

type ApplicantPersonalStepProps = PersonStepProps & {
    section: 'basic' | 'contact' | 'family';
};

const PERSONAL_STEP_TITLES: Record<ApplicantPersonalStepProps['section'], string> = {
    basic: 'My personal data',
    contact: 'Address & contact',
    family: 'Family & background',
};

const PERSONAL_STEP_DESCS: Record<ApplicantPersonalStepProps['section'], string> = {
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
                            Signatures will be collected physically upon loan release.
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
};

export function LoanRequestCoMakerStep({
    title,
    description,
    prefix,
    section,
    values,
    errors,
    onChange,
}: CoMakerStepProps) {
    return (
        <LoanRequestSectionCard title={title} description={description}>
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
                                    Signatures will be collected physically upon loan release.
                                </AlertDescription>
                            </Alert>
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
    composeAddress(person.address1, person.address2, person.address3);

const resolveEmployerBusinessAddress = (
    person: LoanRequestPersonFormData,
): string =>
    composeAddress(
        person.employer_business_address1,
        person.employer_business_address2,
        person.employer_business_address3,
    );

const SummaryGrid = ({ items }: { items: SummaryItem[] }) => (
    <div className="grid gap-3 sm:grid-cols-2">
        {items.map((item) => (
            <div key={item.label} className="space-y-1">
                <p className="text-xs text-muted-foreground">{item.label}</p>
                <p className="text-sm font-medium wrap-break-word">{item.value}</p>
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
        | 'insurance'
        | 'health'
        | 'banking'
        | 'barangay'
        | 'declarations'
    >;
    title: string;
    description: string;
    values: LoanRequestDataSectionValues;
    definition: LoanRequestDataSectionDefinition;
    errors: Record<string, string | undefined>;
    onChange: (
        field: string,
        value: string | number | boolean | null,
    ) => void;
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

export function LoanRequestDataSectionStep({
    sectionKey,
    title,
    description,
    values,
    definition,
    errors,
    onChange,
}: DataSectionStepProps) {
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

                    return (
                        <div
                            key={fieldKey}
                            className={
                                isNotesField ? 'grid gap-2 md:col-span-2' : 'grid gap-2'
                            }
                        >
                            <Label htmlFor={`${sectionKey}_${fieldKey}`}>
                                {field.label}
                            </Label>
                            {field.type === 'boolean' ? (
                                <BooleanYesNoField
                                    id={`${sectionKey}_${fieldKey}`}
                                    value={value}
                                    aria-label={field.label}
                                    onChange={(nextValue) =>
                                        onChange(fieldKey, nextValue)
                                    }
                                />
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
}: HealthQuestionnaireStepProps) {
    const childrenByParent = useMemo(() => {
        const map: Record<string, string[]> = {};

        Object.entries(definition.fields).forEach(([fieldKey, field]) => {
            if (!field.detail_of) {
                return;
            }

            if (!map[field.detail_of]) {
                map[field.detail_of] = [];
            }

            map[field.detail_of].push(fieldKey);
        });

        return map;
    }, [definition]);

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
        const children = childrenByParent[fieldKey] ?? [];

        if (field.type === 'boolean') {
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
                            onChange={(nextBoolean) => {
                                onChange(fieldKey, nextBoolean);

                                if (nextBoolean !== true) {
                                    clearDescendants(fieldKey);
                                }
                            }}
                        />
                        <InputError message={errors[errorKey]} />
                    </div>
                    {value === true && children.length > 0 ? (
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

    const itemGroups = useMemo(() => {
        const itemNumberSet = new Set(itemNumbers);

        return getGlapiItemGroups(definition).filter((group) =>
            itemNumberSet.has(group.number),
        );
    }, [definition, itemNumbers]);

    const renderItemGroup = (group: GlapiItemGroup): ReactNode => {
        const isCluster = group.fieldKeys.length > 1;
        const heading = isCluster
            ? GLAPI_GROUP_HEADINGS[group.number]
            : undefined;
        const badgeLabel = /^\d+$/.test(group.number) ? group.number : null;

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

        return (
            <div
                key={group.fieldKeys[0]}
                className="rounded-lg border border-border/50 bg-card/60 p-4"
            >
                <div className="flex items-start gap-3">
                    {badgeLabel ? (
                        <span className="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground">
                            {badgeLabel}
                        </span>
                    ) : null}
                    <div className="flex-1 space-y-4">
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
                    </div>
                </div>
            </div>
        );
    };

    return (
        <LoanRequestSectionCard
            title={title}
            description={description}
            contentClassName="space-y-5"
        >
            <div className="space-y-4">
                {itemGroups.map((group) => renderItemGroup(group))}
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
};

type DependentCategoryConfig = {
    key: string;
    label: string;
    cap: number;
};

// Row caps are provisional -- see LoanRequestDataService::FIELD_DEFINITIONS.
const DEPENDENT_CATEGORIES: DependentCategoryConfig[] = [
    { key: 'child', label: 'Child', cap: 3 },
    { key: 'sibling', label: 'Sibling', cap: 3 },
    { key: 'parent', label: 'Parent', cap: 2 },
    { key: 'extended', label: 'Extended family member', cap: 3 },
];

const DEPENDENT_SLOT_ATTRIBUTES = [
    'name',
    'relationship',
    'birthdate',
    'occupation',
    'cycle_status',
] as const;

function slotFieldKey(category: string, slot: number, attribute: string): string {
    return `dependent_${category}_${slot}_${attribute}`;
}

function slotHasValue(
    values: LoanRequestDataSectionValues,
    category: string,
    slot: number,
): boolean {
    return DEPENDENT_SLOT_ATTRIBUTES.some((attribute) => {
        const value = values[slotFieldKey(category, slot, attribute)];
        return value !== null && value !== undefined && value !== '';
    });
}

function DependentCategorySection({
    category,
    sectionKey,
    values,
    definition,
    errors,
    onChange,
}: {
    category: DependentCategoryConfig;
    sectionKey: 'dependents';
    values: LoanRequestDataSectionValues;
    definition: LoanRequestDataSectionDefinition;
    errors: Record<string, string | undefined>;
    onChange: (field: string, value: string | number | boolean | null) => void;
}) {
    const initialVisibleSlots = useMemo(() => {
        let count = 1;

        for (let slot = category.cap; slot >= 1; slot -= 1) {
            if (slotHasValue(values, category.key, slot)) {
                count = Math.max(count, slot);
                break;
            }
        }

        return count;
        // Only computed once on mount -- subsequent add/remove clicks own the count.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const [visibleSlots, setVisibleSlots] = useState(initialVisibleSlots);

    const handleRemoveSlot = (slot: number) => {
        DEPENDENT_SLOT_ATTRIBUTES.forEach((attribute) => {
            onChange(slotFieldKey(category.key, slot, attribute), null);
        });
        setVisibleSlots((current) => Math.max(1, current - 1));
    };

    const renderSlot = (slot: number) => {
        const nameField = definition.fields[slotFieldKey(category.key, slot, 'name')];
        const relationshipField =
            definition.fields[slotFieldKey(category.key, slot, 'relationship')];
        const birthdateField =
            definition.fields[slotFieldKey(category.key, slot, 'birthdate')];
        const occupationField =
            definition.fields[slotFieldKey(category.key, slot, 'occupation')];

        return (
            <div
                key={slot}
                className="space-y-3 rounded-md border border-border/50 bg-muted/5 p-4"
            >
                <div className="flex items-center justify-between">
                    <p className="text-sm font-semibold text-foreground">
                        {category.label} {slot}
                    </p>
                    {slot > 1 || visibleSlots > 1 ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => handleRemoveSlot(slot)}
                        >
                            Remove
                        </Button>
                    ) : null}
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    {[
                        { field: nameField, key: 'name', type: 'text' as const },
                        {
                            field: relationshipField,
                            key: 'relationship',
                            type: 'text' as const,
                        },
                        {
                            field: birthdateField,
                            key: 'birthdate',
                            type: 'date' as const,
                        },
                        {
                            field: occupationField,
                            key: 'occupation',
                            type: 'text' as const,
                        },
                    ].map(({ field, key, type }) => {
                        if (!field) {
                            return null;
                        }

                        const fieldKey = slotFieldKey(category.key, slot, key);
                        const errorKey = `${sectionKey}.${fieldKey}`;
                        const value = values[fieldKey];

                        return (
                            <div key={fieldKey} className="grid gap-2">
                                <Label htmlFor={`${sectionKey}_${fieldKey}`}>
                                    {field.label}
                                </Label>
                                <Input
                                    id={`${sectionKey}_${fieldKey}`}
                                    type={type}
                                    value={value ? `${value}` : ''}
                                    onChange={(event) =>
                                        onChange(fieldKey, event.target.value)
                                    }
                                />
                                <InputError message={errors[errorKey]} />
                            </div>
                        );
                    })}
                </div>
            </div>
        );
    };

    return (
        <div className="space-y-3">
            <p className="text-sm font-semibold text-foreground">
                {category.label}s
            </p>
            <div className="space-y-3">
                {Array.from({ length: visibleSlots }, (_, index) => index + 1).map(
                    renderSlot,
                )}
            </div>
            {visibleSlots < category.cap ? (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        setVisibleSlots((current) =>
                            Math.min(category.cap, current + 1),
                        )
                    }
                >
                    + Add another {category.label.toLowerCase()}
                </Button>
            ) : null}
        </div>
    );
}

/**
 * Dependents (Form B). Fixed slots per category (see
 * LoanRequestDataService::FIELD_DEFINITIONS), rendered with add/remove-row
 * UX so members aren't shown every slot at once. The "child" category is
 * gated by civil_status via visible_when, same mechanism as the GLAPI
 * pregnancy question -- see LoanRequestHealthQuestionnaireStep.
 */
export function LoanRequestDependentsStep({
    sectionKey,
    title,
    description,
    values,
    definition,
    errors,
    crossSectionValues,
    onChange,
}: DependentsStepProps) {
    const isCategoryVisible = (category: DependentCategoryConfig): boolean => {
        const firstField = definition.fields[slotFieldKey(category.key, 1, 'name')];

        if (!firstField?.visible_when) {
            return true;
        }

        return (
            crossSectionValues[firstField.visible_when.field] ===
            firstField.visible_when.equals
        );
    };

    return (
        <LoanRequestSectionCard
            title={title}
            description={description}
            contentClassName="space-y-6"
        >
            {DEPENDENT_CATEGORIES.filter(isCategoryVisible).map((category) => (
                <DependentCategorySection
                    key={category.key}
                    category={category}
                    sectionKey={sectionKey}
                    values={values}
                    definition={definition}
                    errors={errors}
                    onChange={onChange}
                />
            ))}
            <Alert className="border-border/50 bg-muted/10">
                <AlertTitle>Optional</AlertTitle>
                <AlertDescription>
                    Dependents are optional. Add only the ones applicable to
                    you -- leave a category empty if it doesn&apos;t apply.
                </AlertDescription>
            </Alert>
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
        { label: 'Address', value: displayText(resolveAddress(data.applicant)) },
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
            value: displayText(
                resolveEmployerBusinessAddress(data.applicant),
            ),
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

    const dataSectionSummaries = (
        [
            'insurance',
            'health',
            'health_glapi',
            'banking',
            'barangay',
            'declarations',
        ] as const
    ).map((sectionKey) => ({
        key: sectionKey,
        title: sectionDefinitions[sectionKey]?.label ?? sectionKey,
        items: Object.entries(sectionDefinitions[sectionKey]?.fields ?? {}).map(
            ([fieldKey, field]) => ({
                label: field.label,
                value: displaySectionValue(
                    data[sectionKey][fieldKey],
                    field,
                ),
            }),
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
