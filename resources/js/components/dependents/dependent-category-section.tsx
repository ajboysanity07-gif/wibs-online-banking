import { Baby, Heart, UserRound, UsersRound, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ComponentType } from 'react';

import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

export type DependentCategoryConfig = {
    key: string;
    label: string;
    // Only needed for irregular plurals (e.g. Child -> Children).
    // Falls back to `${label}s` when omitted.
    pluralLabel?: string;
    cap: number;
    icon: ComponentType<{ className?: string }>;
};

// Row caps are provisional -- see LoanRequestDataService::FIELD_DEFINITIONS
// and MemberDependentProfile::CATEGORY_CAPS.
export const DEPENDENT_CATEGORIES: DependentCategoryConfig[] = [
    {
        key: 'child',
        label: 'Child',
        pluralLabel: 'Children',
        cap: 3,
        icon: Baby,
    },
    { key: 'sibling', label: 'Sibling', cap: 3, icon: UserRound },
    { key: 'parent', label: 'Parent', cap: 2, icon: Heart },
    {
        key: 'extended',
        label: 'Extended family member',
        cap: 3,
        icon: UsersRound,
    },
];

export function dependentCategoryPluralLabel(
    category: DependentCategoryConfig,
): string {
    return category.pluralLabel ?? `${category.label}s`;
}

// No 'relationship'/'occupation' -- the physical Form B "Dependents
// Information" section has no such columns for any category.
export const DEPENDENT_SLOT_ATTRIBUTES = [
    'name',
    'birthdate',
    'cycle_status',
    'cycle_number',
] as const;

export type DependentSlotAttribute = (typeof DEPENDENT_SLOT_ATTRIBUTES)[number];

const DEPENDENT_ATTRIBUTE_LABELS: Record<DependentSlotAttribute, string> = {
    name: 'Name',
    birthdate: 'Birthdate',
    cycle_status: 'Group life coverage status',
    cycle_number: 'Cycle number',
};

const CYCLE_STATUS_HELP_TEXT =
    'New if this is their first time covered under the group life plan. Old if they were already enrolled before -- enter which cycle.';

export type DependentValues = Record<
    string,
    string | number | boolean | null | undefined
>;

export function slotFieldKey(
    category: string,
    slot: number,
    attribute: string,
): string {
    return `dependent_${category}_${slot}_${attribute}`;
}

export function slotHasValue(
    values: DependentValues,
    category: string,
    slot: number,
): boolean {
    return DEPENDENT_SLOT_ATTRIBUTES.some((attribute) => {
        const value = values[slotFieldKey(category, slot, attribute)];
        return value !== null && value !== undefined && value !== '';
    });
}

// The slot itself already renders "{category.label} {slot}" as its own
// heading, so field-level labels only need the attribute name.
function defaultSlotFieldLabel(attribute: DependentSlotAttribute): string {
    return DEPENDENT_ATTRIBUTE_LABELS[attribute];
}

/**
 * Add/remove-row UI for a single dependent category. Shared by the
 * loan-request wizard's Dependents step (controlled by wizard form state)
 * and the Settings > Dependents tab (controlled by its own local state,
 * with inputs also carrying `name` attributes so the page's native Form
 * submission picks them up).
 */
export function DependentCategorySection({
    category,
    values,
    errors,
    errorKeyPrefix = '',
    withNameAttribute = false,
    showCycleFields = true,
    onChange,
}: {
    category: DependentCategoryConfig;
    values: DependentValues;
    errors: Record<string, string | undefined>;
    errorKeyPrefix?: string;
    withNameAttribute?: boolean;
    showCycleFields?: boolean;
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

    const renderTextField = (slot: number, attribute: 'name' | 'birthdate') => {
        const fieldKey = slotFieldKey(category.key, slot, attribute);
        const label = defaultSlotFieldLabel(attribute);
        const type = attribute === 'birthdate' ? 'date' : 'text';
        const errorKey = errorKeyPrefix
            ? `${errorKeyPrefix}.${fieldKey}`
            : fieldKey;
        const value = values[fieldKey];

        return (
            <div key={fieldKey} className="grid gap-2">
                <Label htmlFor={fieldKey}>{label}</Label>
                <Input
                    id={fieldKey}
                    name={withNameAttribute ? fieldKey : undefined}
                    type={type}
                    value={value ? `${value}` : ''}
                    onChange={(event) => onChange(fieldKey, event.target.value)}
                />
                <InputError message={errors[errorKey]} />
            </div>
        );
    };

    // New/Old + conditional cycle number, same reveal-on-selection pattern
    // as GLAPI item 17 (LoanRequestHealthQuestionnaireStep): the cycle
    // number field only appears once "Old" is selected, and is cleared if
    // the selection changes back to "New".
    const renderCycleFields = (slot: number) => {
        const statusKey = slotFieldKey(category.key, slot, 'cycle_status');
        const numberKey = slotFieldKey(category.key, slot, 'cycle_number');
        const statusLabel = defaultSlotFieldLabel('cycle_status');
        const numberLabel = defaultSlotFieldLabel('cycle_number');
        const statusErrorKey = errorKeyPrefix
            ? `${errorKeyPrefix}.${statusKey}`
            : statusKey;
        const numberErrorKey = errorKeyPrefix
            ? `${errorKeyPrefix}.${numberKey}`
            : numberKey;
        const statusValue = values[statusKey];
        const numberValue = values[numberKey];

        return (
            <div
                key={statusKey}
                className="grid gap-3 rounded-md bg-muted/40 p-3"
            >
                <div className="grid gap-1.5">
                    <Label htmlFor={statusKey}>{statusLabel}</Label>
                    <p className="text-xs text-muted-foreground">
                        {CYCLE_STATUS_HELP_TEXT}
                    </p>
                </div>
                <ToggleGroup
                    id={statusKey}
                    type="single"
                    variant="outline"
                    value={statusValue ? `${statusValue}` : ''}
                    onValueChange={(nextValue: string) => {
                        onChange(
                            statusKey,
                            nextValue === '' ? null : nextValue,
                        );

                        if (nextValue !== 'Old') {
                            onChange(numberKey, null);
                        }
                    }}
                    aria-label={statusLabel}
                    className="w-full"
                >
                    <ToggleGroupItem value="New" className="flex-1">
                        New
                    </ToggleGroupItem>
                    <ToggleGroupItem value="Old" className="flex-1">
                        Old
                    </ToggleGroupItem>
                </ToggleGroup>
                {withNameAttribute ? (
                    // ToggleGroup renders plain buttons, not a native radio
                    // input, so its value never lands in FormData -- carry
                    // it via a hidden input, same as the cycle_number
                    // carrier below.
                    <input
                        type="hidden"
                        name={statusKey}
                        value={statusValue ? `${statusValue}` : ''}
                    />
                ) : null}
                <InputError message={errors[statusErrorKey]} />

                {statusValue === 'Old' ? (
                    <div className="grid animate-in gap-2 pt-1 duration-150 fade-in slide-in-from-top-1 sm:max-w-xs">
                        <Label htmlFor={numberKey}>{numberLabel}</Label>
                        <Input
                            id={numberKey}
                            name={withNameAttribute ? numberKey : undefined}
                            type="number"
                            min={1}
                            value={numberValue ? `${numberValue}` : ''}
                            onChange={(event) =>
                                onChange(numberKey, event.target.value)
                            }
                        />
                        <InputError message={errors[numberErrorKey]} />
                    </div>
                ) : withNameAttribute ? (
                    // Keep a disabled, empty carrier so a native Form submit
                    // doesn't retain a stale value after switching back to "New".
                    <input type="hidden" name={numberKey} value="" />
                ) : null}
            </div>
        );
    };

    const canRemoveSlot = (slot: number) => slot > 1 || visibleSlots > 1;

    const renderSlot = (slot: number) => {
        return (
            <Card key={slot} className="gap-3 py-4">
                <CardContent className="space-y-3 px-4">
                    <div className="flex items-center justify-between gap-2">
                        <p className="text-sm font-semibold text-foreground">
                            {category.label} {slot}
                        </p>
                        {canRemoveSlot(slot) ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7 text-muted-foreground hover:text-destructive"
                                onClick={() => handleRemoveSlot(slot)}
                                aria-label={`Remove ${category.label.toLowerCase()} ${slot}`}
                            >
                                <X className="size-4" />
                            </Button>
                        ) : null}
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        {renderTextField(slot, 'name')}
                        {renderTextField(slot, 'birthdate')}
                    </div>
                    {showCycleFields ? (
                        <>
                            <Separator />
                            {renderCycleFields(slot)}
                        </>
                    ) : null}
                </CardContent>
            </Card>
        );
    };

    const atCap = visibleSlots >= category.cap;
    const Icon = category.icon;

    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2">
                <Icon className="size-4 text-muted-foreground" />
                <p className="text-sm font-semibold text-foreground">
                    {dependentCategoryPluralLabel(category)}
                </p>
                <Badge variant="secondary" className="font-normal">
                    {visibleSlots} of {category.cap}
                </Badge>
            </div>
            <div className="space-y-3">
                {Array.from(
                    { length: visibleSlots },
                    (_, index) => index + 1,
                ).map(renderSlot)}
            </div>
            {atCap ? (
                <p className="text-xs text-muted-foreground">
                    Maximum of {category.cap}{' '}
                    {dependentCategoryPluralLabel(category).toLowerCase()}{' '}
                    reached.
                </p>
            ) : (
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
            )}
        </div>
    );
}

export const SPOUSE_CYCLE_STATUS_KEY = 'dependent_spouse_cycle_status';
export const SPOUSE_CYCLE_NUMBER_KEY = 'dependent_spouse_cycle_number';
export const APPLICANT_CYCLE_STATUS_KEY = 'applicant_cycle_status';
export const APPLICANT_CYCLE_NUMBER_KEY = 'applicant_cycle_number';

/**
 * New/Old + cycle number for a singleton (not a repeatable-category slot,
 * e.g. Spouse or the Applicant themselves), so it gets its own small section
 * rather than going through DependentCategorySection. Same reveal-on-selection
 * pattern: cycle number only appears once "Old" is selected.
 */
export function SingletonCycleSection({
    label,
    statusKey,
    numberKey,
    values,
    errors,
    errorKeyPrefix = '',
    withNameAttribute = false,
    onChange,
}: {
    label: string;
    statusKey: string;
    numberKey: string;
    values: DependentValues;
    errors: Record<string, string | undefined>;
    errorKeyPrefix?: string;
    withNameAttribute?: boolean;
    onChange: (field: string, value: string | number | boolean | null) => void;
}) {
    const statusLabel = 'Group life coverage status';
    const numberLabel = 'Cycle number';
    const statusErrorKey = errorKeyPrefix
        ? `${errorKeyPrefix}.${statusKey}`
        : statusKey;
    const numberErrorKey = errorKeyPrefix
        ? `${errorKeyPrefix}.${numberKey}`
        : numberKey;
    const statusValue = values[statusKey];
    const numberValue = values[numberKey];

    return (
        <div className="space-y-3">
            <div className="flex items-center gap-2">
                <UserRound className="size-4 text-muted-foreground" />
                <p className="text-sm font-semibold text-foreground">{label}</p>
            </div>
            <Card className="gap-3 py-4">
                <CardContent className="grid gap-3 px-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor={statusKey}>{statusLabel}</Label>
                        <p className="text-xs text-muted-foreground">
                            {CYCLE_STATUS_HELP_TEXT}
                        </p>
                    </div>
                    <ToggleGroup
                        id={statusKey}
                        type="single"
                        variant="outline"
                        value={statusValue ? `${statusValue}` : ''}
                        onValueChange={(nextValue: string) => {
                            onChange(
                                statusKey,
                                nextValue === '' ? null : nextValue,
                            );

                            if (nextValue !== 'Old') {
                                onChange(numberKey, null);
                            }
                        }}
                        aria-label={statusLabel}
                        className="w-full"
                    >
                        <ToggleGroupItem value="New" className="flex-1">
                            New
                        </ToggleGroupItem>
                        <ToggleGroupItem value="Old" className="flex-1">
                            Old
                        </ToggleGroupItem>
                    </ToggleGroup>
                    {withNameAttribute ? (
                        <input
                            type="hidden"
                            name={statusKey}
                            value={statusValue ? `${statusValue}` : ''}
                        />
                    ) : null}
                    <InputError message={errors[statusErrorKey]} />

                    {statusValue === 'Old' ? (
                        <div className="grid animate-in gap-2 duration-150 fade-in slide-in-from-top-1">
                            <Label htmlFor={numberKey}>{numberLabel}</Label>
                            <Input
                                id={numberKey}
                                name={withNameAttribute ? numberKey : undefined}
                                type="number"
                                min={1}
                                value={numberValue ? `${numberValue}` : ''}
                                onChange={(event) =>
                                    onChange(numberKey, event.target.value)
                                }
                            />
                            <InputError message={errors[numberErrorKey]} />
                        </div>
                    ) : withNameAttribute ? (
                        <input type="hidden" name={numberKey} value="" />
                    ) : null}
                </CardContent>
            </Card>
        </div>
    );
}

/**
 * Spouse New/Old + cycle number -- thin wrapper around SingletonCycleSection
 * kept for existing call sites.
 */
export function DependentSpouseCycleSection(props: {
    values: DependentValues;
    errors: Record<string, string | undefined>;
    errorKeyPrefix?: string;
    withNameAttribute?: boolean;
    onChange: (field: string, value: string | number | boolean | null) => void;
}) {
    return (
        <SingletonCycleSection
            {...props}
            label="Spouse"
            statusKey={SPOUSE_CYCLE_STATUS_KEY}
            numberKey={SPOUSE_CYCLE_NUMBER_KEY}
        />
    );
}

/**
 * Applicant's own New/Old + cycle number -- unconditional (unlike Spouse,
 * which only applies when married), feeds the Generali Individual
 * Application Form's cycle-status fields.
 */
export function ApplicantCycleSection(props: {
    values: DependentValues;
    errors: Record<string, string | undefined>;
    errorKeyPrefix?: string;
    withNameAttribute?: boolean;
    onChange: (field: string, value: string | number | boolean | null) => void;
}) {
    return (
        <SingletonCycleSection
            {...props}
            label="Applicant"
            statusKey={APPLICANT_CYCLE_STATUS_KEY}
            numberKey={APPLICANT_CYCLE_NUMBER_KEY}
        />
    );
}

export type DependentCategorySummary = {
    category: DependentCategoryConfig;
    count: number;
    rows: Array<{ name: string; cycleStatus: string }>;
};

/**
 * Summarize saved dependents per visible category, for the wizard's
 * read-and-confirm view once a member already has profile data on file.
 */
export function summarizeDependents(
    categories: DependentCategoryConfig[],
    values: DependentValues,
): DependentCategorySummary[] {
    return categories
        .map((category) => {
            const rows: Array<{ name: string; cycleStatus: string }> = [];

            for (let slot = 1; slot <= category.cap; slot += 1) {
                if (!slotHasValue(values, category.key, slot)) {
                    continue;
                }

                const name = values[slotFieldKey(category.key, slot, 'name')];
                const cycleStatus =
                    values[slotFieldKey(category.key, slot, 'cycle_status')];
                const cycleNumber =
                    values[slotFieldKey(category.key, slot, 'cycle_number')];

                const cycleStatusLabel =
                    cycleStatus === 'Old' && cycleNumber
                        ? `Old · cycle ${cycleNumber}`
                        : cycleStatus
                          ? `${cycleStatus}`
                          : '';

                rows.push({
                    name: name ? `${name}` : `${category.label} ${slot}`,
                    cycleStatus: cycleStatusLabel,
                });
            }

            return { category, count: rows.length, rows };
        })
        .filter((entry) => entry.count > 0);
}
