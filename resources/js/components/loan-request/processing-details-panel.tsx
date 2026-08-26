import { FileText, Info } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { PAYDAY_OPTIONS } from '@/components/loan-request/loan-request-fields';
import {
    CurrencyInput,
    MonthsInput,
    PercentInput,
} from '@/components/loan-request/numeric-adorned-inputs';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldMessage } from '@/components/ui/field-message';
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
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { LoanRequestProcessingDetailsPayload } from '@/hooks/admin/use-loan-request-workflow';
import { adminApi } from '@/lib/api/admin';
import { formatCurrency } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type {
    LoanManagerOption,
    LoanRequestCycleState,
    LoanRequestDataSectionDefinitions,
    LoanRequestDataSections,
    LoanRequestDataSectionValues,
    LoanRequestDetail,
    LoanRequestPersonData,
    LoanRequestReviewer,
    LoanRequestWorkflowResult,
} from '@/types/loan-requests';

export const textareaClassName =
    'flex min-h-[112px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50';

const actionCardClassName =
    'border-primary/25 bg-card/80 shadow-sm ring-1 ring-primary/10';
const readOnlyProcessingFieldClassName =
    'bg-muted/30 text-muted-foreground/80 border-border/40';

export const toStringValue = (
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

export const snapshotDisplay = (value?: string | number | null): string => {
    if (value === null || value === undefined) {
        return '—';
    }

    const stringValue = `${value}`.trim();

    return stringValue !== '' ? stringValue : '—';
};

export const snapshotCurrency = (value?: string | number | null): string => {
    if (value === null || value === undefined || `${value}`.trim() === '') {
        return '—';
    }

    const numericValue = Number(value);

    return Number.isNaN(numericValue)
        ? `${value}`
        : formatCurrency(numericValue);
};

export const SnapshotRow = ({
    label,
    value,
    className,
}: {
    label: string;
    value: string;
    className?: string;
}) => (
    <div className={cn('space-y-1', className)}>
        <p className="text-xs text-muted-foreground">{label}</p>
        <p className="text-sm leading-relaxed font-medium">{value}</p>
    </div>
);

const withWitnessOneAutoFill = (
    processing: Record<string, string | number | boolean | null>,
    assignedProcessor: LoanRequestReviewer | null,
): Record<string, string | number | boolean | null> => {
    const current = processing.witness_one_name;
    const isBlank =
        current === null || current === undefined || `${current}`.trim() === '';

    if (!isBlank || !assignedProcessor?.name) {
        return processing;
    }

    return { ...processing, witness_one_name: assignedProcessor.name };
};

const withWitnessTwoAutoFill = (
    processing: Record<string, string | number | boolean | null>,
    loanManagers: LoanManagerOption[],
): Record<string, string | number | boolean | null> => {
    const current = processing.witness_two_name;
    const isBlank =
        current === null || current === undefined || `${current}`.trim() === '';

    if (!isBlank || loanManagers.length !== 1) {
        return processing;
    }

    return {
        ...processing,
        witness_two_name: loanManagers[0].name,
        witness_two_id: loanManagers[0].id,
    };
};

const SNAPSHOT_HIDDEN_FIELDS = new Set(['witness_two_id']);

const SNAPSHOT_BARANGAY_FIELDS = [
    'barangay_official_name',
    'barangay_official_title',
    'barangay_official_designation',
    'barangay_agency_name',
    'barangay_agency_address',
];

const SNAPSHOT_AUTHORITY_TO_DEDUCT_FIELDS = [
    'authority_to_deduct_institution_name',
    'authority_to_deduct_officer_1_name',
    'authority_to_deduct_officer_1_title',
    'authority_to_deduct_officer_2_name',
    'authority_to_deduct_officer_2_title',
    'authority_to_deduct_officers_unknown',
];

const SNAPSHOT_DEPED_FIELDS = [
    'deped_school_id_number',
    'deped_deduction_amount',
];

const SNAPSHOT_PENSION_FIELDS = [
    'pension_provider',
    'pension_bank_name',
    'pension_atm_card_number',
    'pension_deduction_amount',
];

const SNAPSHOT_GATED_FIELDS = new Set([
    ...SNAPSHOT_BARANGAY_FIELDS,
    ...SNAPSHOT_AUTHORITY_TO_DEDUCT_FIELDS,
    ...SNAPSHOT_DEPED_FIELDS,
    ...SNAPSHOT_PENSION_FIELDS,
]);

const PROCESSING_CHARGE_DEFAULTS: Record<string, number> = {
    loan_security_rate: 0.02,
    savings_rate: 0.02,
    // Institutional documentary stamp constant (₱1.50 per ₱200 of loan =
    // 1.5/200 = 0.75%); the amount itself follows the ₱200 banding rule.
    documentary_stamp_rate: 0.0075,
    penalty_rate_per_month: 0.05,
};

// Insurer's senior-age insurance rate bands (from the loan processors'
// reference table). Only these two bands are currently known; applicants
// outside them are locked to a fixed rate of 1 (see withProcessingChargeDefaults).
// These are per-mille rates (pesos per ₱1,000 of principal per month), NOT
// percentages -- insurance_rate feeds insurance_premium = (amount/1000) *
// insurance_term * insurance_rate, so the raw table value (e.g. 2.05) must
// be stored as-is, unlike the other *_rate fields which are true percentages.
const INSURANCE_RATE_AGE_BANDS: {
    minAge: number;
    maxAge: number;
    rate: number;
}[] = [
    { minAge: 66, maxAge: 70, rate: 2.05 },
    { minAge: 71, maxAge: 75, rate: 3.95 },
];

const calculateAgeFromBirthdate = (birthdate: string | null): number | null => {
    if (!birthdate) {
        return null;
    }

    const parsed = new Date(birthdate);

    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    const today = new Date();
    let age = today.getFullYear() - parsed.getFullYear();
    const hasNotHadBirthdayThisYear =
        today.getMonth() < parsed.getMonth() ||
        (today.getMonth() === parsed.getMonth() &&
            today.getDate() < parsed.getDate());

    if (hasNotHadBirthdayThisYear) {
        age -= 1;
    }

    return age;
};

const resolveAgeBandedInsuranceRate = (
    birthdate: string | null,
): number | null => {
    const age = calculateAgeFromBirthdate(birthdate);

    if (age === null) {
        return null;
    }

    const band = INSURANCE_RATE_AGE_BANDS.find(
        ({ minAge, maxAge }) => age >= minAge && age <= maxAge,
    );

    return band?.rate ?? null;
};

// "Due date" is a loan-level payment frequency, not a valid personal payday,
// so it is added only for this dropdown rather than to the shared
// PAYDAY_OPTIONS list (also used by the applicant/co-maker payday pickers).
const PAYMENT_FREQUENCY_OPTIONS = [...PAYDAY_OPTIONS, 'Due date'] as const;

const withProcessingChargeDefaults = (
    processing: Record<string, string | number | boolean | null>,
    applicantBirthdate: string | null = null,
): Record<string, string | number | boolean | null> => {
    let next = processing;

    for (const [key, defaultValue] of Object.entries(
        PROCESSING_CHARGE_DEFAULTS,
    )) {
        const current = next[key];
        const isBlank =
            current === null ||
            current === undefined ||
            `${current}`.trim() === '';

        if (isBlank) {
            next = { ...next, [key]: defaultValue };
        }
    }

    // insurance_rate is always system-controlled, never manually entered:
    // table-driven when the applicant falls in a known senior-age band,
    // otherwise fixed at 1. Force it every time (not just when blank) so a
    // stale saved value never survives a re-render.
    const ageBandedRate = resolveAgeBandedInsuranceRate(applicantBirthdate);
    next = { ...next, insurance_rate: ageBandedRate ?? 1 };

    // penalty_rate_per_month is likewise always locked to its institutional
    // default, never manually entered.
    next = {
        ...next,
        penalty_rate_per_month:
            PROCESSING_CHARGE_DEFAULTS.penalty_rate_per_month,
    };

    return next;
};

// Maps a cycleState slot key ('applicant', 'spouse', or 'child_1' /
// 'sibling_2' / etc) to its [status_field_key, number_field_key] pair
// within processingForm.processing / the submitted `processing` payload --
// mirrors LoanRequestCycleStateService::slotFieldKeys() on the backend.
const cycleSlotFieldKeys = (slotKey: string): [string, string] => {
    if (slotKey === 'applicant') {
        return ['applicant_cycle_status', 'applicant_cycle_number'];
    }

    if (slotKey === 'spouse') {
        return [
            'dependent_spouse_cycle_status',
            'dependent_spouse_cycle_number',
        ];
    }

    return [
        `dependent_${slotKey}_cycle_status`,
        `dependent_${slotKey}_cycle_number`,
    ];
};

// Seeds/refreshes the cycle-status fields inside processingForm.processing.
// Locked slots always take the server-computed value (never a stale saved
// one). Unlocked slots keep whatever was last saved for this loan request,
// falling back to the resolved New/null default so the processor has a legal
// starting point ("New" never carries a number -- there's no cycle history
// yet to number).
const withCycleStateDefaults = (
    processing: Record<string, string | number | boolean | null>,
    dependentsSection: LoanRequestDataSectionValues | undefined,
    cycleState: LoanRequestCycleState,
): Record<string, string | number | boolean | null> => {
    let next = processing;

    Object.entries(cycleState).forEach(([slotKey, slotState]) => {
        const [statusKey, numberKey] = cycleSlotFieldKeys(slotKey);

        if (slotState.locked) {
            next = {
                ...next,
                [statusKey]: slotState.cycle_status,
                [numberKey]: slotState.cycle_number,
            };

            return;
        }

        const currentStatus = dependentsSection?.[statusKey] ?? null;
        const currentNumber = dependentsSection?.[numberKey] ?? null;

        next = {
            ...next,
            [statusKey]: currentStatus ?? slotState.cycle_status,
            [numberKey]: currentNumber ?? slotState.cycle_number,
        };
    });

    return next;
};

const numericProcessingFieldValue = (
    value: string | number | boolean | null | undefined,
): string | number | null =>
    typeof value === 'boolean' || value === undefined ? null : value;

// Frontend-only override: dataSectionDefinitions field metadata has no currency/percent/months distinction, only 'number'/'integer'.
const PROCESSING_FIELD_KIND: Record<string, 'currency' | 'percent' | 'months'> =
    {
        notarial_fee: 'currency',
        other_charges_amount: 'currency',
        guaranteed_net_take_home_pay: 'currency',
        service_charge_rate: 'percent',
        loan_security_rate: 'percent',
        savings_rate: 'percent',
        documentary_stamp_rate: 'percent',
        penalty_rate_per_month: 'percent',
        insurance_term: 'months',
        // insurance_rate is deliberately absent here (falls through to the
        // plain numeric input below): it's a per-mille rate (pesos per
        // ₱1,000 of principal per month, e.g. 2.05), not a fraction of the
        // loan amount, so it must NOT go through the %-to-fraction
        // conversion that PercentInput applies to the other *_rate fields.
    };

// Category A — financial processing terms edited inline on the page.
type InlineProcessingFormState = {
    processing: Record<string, string | number | boolean | null>;
    recommended_amount: string;
    recommended_term: string;
    recommended_interest_rate: string;
    recommended_payment_frequency: string;
    reason: string;
};

const hasSecondOfficerValue = (
    processing: Record<string, string | number | boolean | null>,
): boolean => {
    const name = processing.authority_to_deduct_officer_2_name;
    const title = processing.authority_to_deduct_officer_2_title;

    return (
        (name !== null && name !== undefined && `${name}`.trim() !== '') ||
        (title !== null && title !== undefined && `${title}`.trim() !== '')
    );
};

type RecommendationPreviewState = {
    approved_amount_raw: number | null;
    finance_charge_total_raw: number | null;
    non_finance_charge_total_raw: number | null;
    deductions_total_raw: number | null;
    net_proceeds_raw: number | null;
    suggested_gnthp_raw: number | null;
    failure_information: { message: string; blockers: string[] } | null;
};

type ProcessingDetailsPanelProps = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    dataSections: LoanRequestDataSections;
    dataSectionDefinitions: LoanRequestDataSectionDefinitions;
    cycleState: LoanRequestCycleState;
    canUpdateProcessing: boolean;
    isProcessing: boolean;
    updateProcessingDetails: (
        loanRequestId: number,
        payload: LoanRequestProcessingDetailsPayload,
    ) => Promise<LoanRequestWorkflowResult | null>;
    loanManagers?: LoanManagerOption[];
};

export function ProcessingDetailsPanel({
    loanRequest,
    applicant,
    dataSections,
    dataSectionDefinitions,
    cycleState,
    canUpdateProcessing,
    isProcessing,
    updateProcessingDetails,
    loanManagers = [],
}: ProcessingDetailsPanelProps) {
    const [processingForm, setProcessingForm] =
        useState<InlineProcessingFormState>({
            processing: withCycleStateDefaults(
                withProcessingChargeDefaults(
                    withWitnessOneAutoFill(
                        withWitnessTwoAutoFill(
                            { ...dataSections.processing },
                            loanManagers,
                        ),
                        loanRequest.assigned_processor,
                    ),
                    applicant?.birthdate ?? null,
                ),
                dataSections.dependents,
                cycleState,
            ),
            recommended_amount: toStringValue(loanRequest.recommended_amount),
            recommended_term: toStringValue(loanRequest.recommended_term),
            recommended_interest_rate: toStringValue(
                loanRequest.recommended_interest_rate,
            ),
            recommended_payment_frequency:
                loanRequest.recommended_payment_frequency ?? '',
            reason: '',
        });
    const [recommendationPreview, setRecommendationPreview] =
        useState<RecommendationPreviewState | null>(null);
    const [reasonError, setReasonError] = useState<string | null>(null);
    const isFirstProcessingSave = loanRequest.is_first_processing_save;
    const [isRecommendationPreviewLoading, setIsRecommendationPreviewLoading] =
        useState(false);
    const [recommendationPreviewError, setRecommendationPreviewError] =
        useState<string | null>(null);
    const [showSecondOfficer, setShowSecondOfficer] = useState(
        loanRequest.authority_to_deduct_guidance?.recommended_officers !== 1 ||
            hasSecondOfficerValue(dataSections.processing),
    );
    const gnthpRecalculationTimeoutRef = useRef<ReturnType<
        typeof setTimeout
    > | null>(null);
    const officersUnknown =
        processingForm.processing.authority_to_deduct_officers_unknown === true;
    const isApplicantInInsuranceAgeBand =
        resolveAgeBandedInsuranceRate(applicant?.birthdate ?? null) !== null;

    useEffect(() => {
        setProcessingForm({
            processing: withCycleStateDefaults(
                withProcessingChargeDefaults(
                    withWitnessOneAutoFill(
                        withWitnessTwoAutoFill(
                            { ...dataSections.processing },
                            loanManagers,
                        ),
                        loanRequest.assigned_processor,
                    ),
                    applicant?.birthdate ?? null,
                ),
                dataSections.dependents,
                cycleState,
            ),
            recommended_amount: toStringValue(loanRequest.recommended_amount),
            recommended_term: toStringValue(loanRequest.recommended_term),
            recommended_interest_rate: toStringValue(
                loanRequest.recommended_interest_rate,
            ),
            recommended_payment_frequency:
                loanRequest.recommended_payment_frequency ?? '',
            reason: '',
        });
        setShowSecondOfficer(
            loanRequest.authority_to_deduct_guidance?.recommended_officers !==
                1 || hasSecondOfficerValue(dataSections.processing),
        );
    }, [
        applicant?.birthdate,
        cycleState,
        dataSections.dependents,
        dataSections.processing,
        loanManagers,
        loanRequest.assigned_processor,
        loanRequest.authority_to_deduct_guidance,
        loanRequest.recommended_amount,
        loanRequest.recommended_interest_rate,
        loanRequest.recommended_payment_frequency,
        loanRequest.recommended_term,
    ]);

    const updateProcessingSectionField = (
        field: string,
        value: string | number | boolean | null,
    ) => {
        setProcessingForm((current) => ({
            ...current,
            processing: {
                ...current.processing,
                [field]: value,
            },
        }));
    };

    // "Loan security" and "savings" are the same rate to staff, but the
    // backend keeps them as two independent fields (loan_security_rate drives
    // a one-time proceeds deduction, savings_rate drives the per-installment
    // amortization contribution) — this input writes one value to both.
    const updateLoanSecurityRate = (value: string | number | null) => {
        setProcessingForm((current) => ({
            ...current,
            processing: {
                ...current.processing,
                loan_security_rate: value,
                savings_rate: value,
            },
        }));
    };

    // GNTHP is system-computed (never manually editable), so this always
    // applies the server's suggestion once it resolves.
    const recalculateGnthp = async () => {
        setIsRecommendationPreviewLoading(true);
        setRecommendationPreviewError(null);

        try {
            const result = await adminApi.previewLoanRequestProcessingDetails(
                loanRequest.id,
                {
                    recommended_amount:
                        processingForm.recommended_amount || null,
                    recommended_term: processingForm.recommended_term || null,
                    recommended_interest_rate:
                        processingForm.recommended_interest_rate || null,
                    recommended_payment_frequency:
                        processingForm.recommended_payment_frequency || null,
                    service_charge_rate: numericProcessingFieldValue(
                        processingForm.processing.service_charge_rate,
                    ),
                    insurance_rate: numericProcessingFieldValue(
                        processingForm.processing.insurance_rate,
                    ),
                    insurance_term: numericProcessingFieldValue(
                        processingForm.processing.insurance_term,
                    ),
                    loan_security_rate: numericProcessingFieldValue(
                        processingForm.processing.loan_security_rate,
                    ),
                    savings_rate: numericProcessingFieldValue(
                        processingForm.processing.savings_rate,
                    ),
                    documentary_stamp_rate: numericProcessingFieldValue(
                        processingForm.processing.documentary_stamp_rate,
                    ),
                    notarial_fee: numericProcessingFieldValue(
                        processingForm.processing.notarial_fee,
                    ),
                    other_charges_amount: numericProcessingFieldValue(
                        processingForm.processing.other_charges_amount,
                    ),
                    other_charges_description:
                        typeof processingForm.processing
                            .other_charges_description === 'string'
                            ? processingForm.processing
                                  .other_charges_description
                            : null,
                    penalty_rate_per_month: numericProcessingFieldValue(
                        processingForm.processing.penalty_rate_per_month,
                    ),
                },
            );

            setRecommendationPreview(result);
            setProcessingForm((current) => ({
                ...current,
                processing: {
                    ...current.processing,
                    guaranteed_net_take_home_pay: result.suggested_gnthp_raw,
                },
            }));
        } catch {
            setRecommendationPreviewError(
                'Unable to compute a preview. Please try again.',
            );
        } finally {
            setIsRecommendationPreviewLoading(false);
        }
    };

    // Debounced so tabbing through several contributing fields in quick
    // succession triggers one recalculation instead of one per field.
    const scheduleGnthpRecalculation = () => {
        if (gnthpRecalculationTimeoutRef.current !== null) {
            clearTimeout(gnthpRecalculationTimeoutRef.current);
        }

        gnthpRecalculationTimeoutRef.current = setTimeout(() => {
            gnthpRecalculationTimeoutRef.current = null;
            void recalculateGnthp();
        }, 400);
    };

    useEffect(() => {
        return () => {
            if (gnthpRecalculationTimeoutRef.current !== null) {
                clearTimeout(gnthpRecalculationTimeoutRef.current);
            }
        };
    }, []);

    // Keep the latest preview function available without making it a
    // dependency of the income effect below (it is recreated each render).
    const recalculateGnthpRef = useRef(recalculateGnthp);

    useEffect(() => {
        recalculateGnthpRef.current = recalculateGnthp;
    });

    // The applicant's income snapshot can change (member-profile income sync)
    // without any processing field being edited, so recompute the GNTHP on
    // mount and whenever the income changes.
    useEffect(() => {
        void recalculateGnthpRef.current();
    }, [applicant?.gross_monthly_income, loanRequest.id]);

    // Build the processing payload: booleans are always sent as true/false
    // (never null, which the endpoint rejects); empty text/number fields become
    // null so they can be cleared without failing numeric validation.
    const buildInlineProcessingPayload = (
        values: Record<string, string | number | boolean | null>,
    ): Record<string, string | number | boolean | null> => {
        const payload: Record<string, string | number | boolean | null> = {};

        Object.entries(dataSectionDefinitions.processing.fields).forEach(
            ([fieldKey, field]) => {
                const raw = values[fieldKey];

                if (field.type === 'boolean') {
                    payload[fieldKey] = raw === true;
                } else if (raw === '' || raw === undefined) {
                    payload[fieldKey] = null;
                } else {
                    payload[fieldKey] = raw;
                }
            },
        );

        // Cycle-status fields live in the 'dependents' data section, not
        // 'processing', so the loop above (which walks
        // dataSectionDefinitions.processing.fields) never picks them up --
        // add them explicitly from the values the cycle-state UI wrote into
        // processingForm.processing (see withCycleStateDefaults).
        Object.keys(cycleState).forEach((slotKey) => {
            const [statusKey, numberKey] = cycleSlotFieldKeys(slotKey);

            [statusKey, numberKey].forEach((fieldKey) => {
                const raw = values[fieldKey];
                payload[fieldKey] =
                    raw === '' || raw === undefined ? null : raw;
            });
        });

        return payload;
    };

    // The inline panel does not edit the loan request details, but the endpoint
    // wipes them to zero/empty unless a `loan_request` object is present. Send a
    // passthrough of the current (unchanged) values to protect them.
    const buildLoanRequestPassthrough = (): Record<string, string | number> => {
        const passthrough: Record<string, string | number> = {};

        if (
            loanRequest.requested_amount !== null &&
            `${loanRequest.requested_amount}`.trim() !== ''
        ) {
            passthrough.requested_amount = loanRequest.requested_amount;
        }

        if (
            loanRequest.requested_term !== null &&
            `${loanRequest.requested_term}`.trim() !== ''
        ) {
            passthrough.requested_term = loanRequest.requested_term;
        }

        if ((loanRequest.loan_purpose ?? '').trim() !== '') {
            passthrough.loan_purpose = loanRequest.loan_purpose as string;
        }

        if ((loanRequest.other_loan_type_name ?? '').trim() !== '') {
            passthrough.other_loan_type_name =
                loanRequest.other_loan_type_name as string;
        }

        if ((loanRequest.availment_status ?? '').trim() !== '') {
            passthrough.availment_status =
                loanRequest.availment_status as string;
        }

        return passthrough;
    };

    const submitProcessingDetails = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        if (!isFirstProcessingSave && processingForm.reason.trim() === '') {
            setReasonError(
                'Remarks are required — explain why you’re making this change.',
            );

            return;
        }

        setReasonError(null);

        const result = await updateProcessingDetails(loanRequest.id, {
            reason: processingForm.reason,
            loan_request: buildLoanRequestPassthrough(),
            processing: buildInlineProcessingPayload(processingForm.processing),
            recommended_amount: processingForm.recommended_amount || null,
            recommended_term: processingForm.recommended_term || null,
            recommended_interest_rate:
                processingForm.recommended_interest_rate || null,
            recommended_payment_frequency:
                processingForm.recommended_payment_frequency || null,
        });

        if (result) {
            setProcessingForm((current) => ({
                ...current,
                reason: '',
            }));
        }
    };

    const recommendedTermLabel =
        loanRequest.recommended_term !== null &&
        `${loanRequest.recommended_term}`.trim() !== ''
            ? `${loanRequest.recommended_term} months`
            : '—';

    const renderSnapshotField = (fieldKey: string) => {
        const field = dataSectionDefinitions.processing.fields[fieldKey];

        if (!field) {
            return null;
        }

        const value = processingForm.processing[fieldKey];
        const display =
            field.type === 'boolean'
                ? value === true
                    ? 'Yes'
                    : value === false
                      ? 'No'
                      : '—'
                : snapshotDisplay(value as string | number | null);

        return (
            <SnapshotRow key={fieldKey} label={field.label} value={display} />
        );
    };

    const renderProcessingSectionLabel = (
        title: string,
        options?: { first?: boolean },
    ) => (
        <div className={options?.first ? undefined : 'mt-6'}>
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {title}
            </p>
            <Separator className="mb-4 bg-border/40" />
        </div>
    );

    const renderProcessingField = (
        fieldKey: string,
        options?: {
            fullWidth?: boolean;
            disabled?: boolean;
            placeholder?: string;
            tooltip?: string;
            className?: string;
            onBlur?: () => void;
        },
    ) => {
        const field = dataSectionDefinitions.processing.fields[fieldKey];

        if (!field) {
            return null;
        }

        if (field.type === 'boolean') {
            return (
                <label
                    key={fieldKey}
                    className="flex items-start gap-3 rounded-lg border border-border/40 bg-muted/10 p-3 text-sm sm:col-span-2"
                >
                    <Checkbox
                        checked={processingForm.processing[fieldKey] === true}
                        onCheckedChange={(checked) =>
                            updateProcessingSectionField(
                                fieldKey,
                                checked === true,
                            )
                        }
                    />
                    <span>{field.label}</span>
                </label>
            );
        }

        const fieldValue =
            processingForm.processing[fieldKey] !== null &&
            processingForm.processing[fieldKey] !== undefined
                ? `${processingForm.processing[fieldKey]}`
                : '';
        const fieldKind = PROCESSING_FIELD_KIND[fieldKey];

        return (
            <div
                key={fieldKey}
                className={cn(
                    'grid gap-2',
                    options?.fullWidth && 'sm:col-span-2',
                )}
            >
                <Label
                    htmlFor={`inline_processing_${fieldKey}`}
                    className={
                        options?.tooltip
                            ? 'inline-flex items-center gap-1.5'
                            : undefined
                    }
                >
                    {field.label}
                    {options?.tooltip && (
                        <TooltipProvider delayDuration={0}>
                            <Tooltip>
                                <TooltipTrigger>
                                    <Info className="size-3.5 text-muted-foreground" />
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{options.tooltip}</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    )}
                </Label>
                {fieldKind === 'currency' ? (
                    <CurrencyInput
                        id={`inline_processing_${fieldKey}`}
                        value={fieldValue}
                        onValueChange={(value) =>
                            updateProcessingSectionField(fieldKey, value)
                        }
                        onBlur={options?.onBlur}
                        disabled={options?.disabled}
                        placeholder={options?.placeholder}
                        className={options?.className}
                    />
                ) : fieldKind === 'percent' ? (
                    <PercentInput
                        id={`inline_processing_${fieldKey}`}
                        value={fieldValue}
                        onValueChange={(value) =>
                            updateProcessingSectionField(fieldKey, value)
                        }
                        onBlur={options?.onBlur}
                        disabled={options?.disabled}
                        placeholder={options?.placeholder}
                        className={options?.className}
                    />
                ) : fieldKind === 'months' ? (
                    <MonthsInput
                        id={`inline_processing_${fieldKey}`}
                        value={fieldValue}
                        onChange={(value) =>
                            updateProcessingSectionField(fieldKey, value)
                        }
                        onBlur={options?.onBlur}
                        disabled={options?.disabled}
                        placeholder={options?.placeholder}
                        className={options?.className}
                    />
                ) : (
                    <Input
                        id={`inline_processing_${fieldKey}`}
                        type={
                            field.type === 'number' || field.type === 'integer'
                                ? 'number'
                                : 'text'
                        }
                        step={field.type === 'number' ? '0.01' : undefined}
                        value={fieldValue}
                        onChange={(event) =>
                            updateProcessingSectionField(
                                fieldKey,
                                event.target.value,
                            )
                        }
                        onBlur={options?.onBlur}
                        className={options?.className}
                        disabled={options?.disabled}
                        placeholder={options?.placeholder}
                    />
                )}
            </div>
        );
    };

    // Only render a cycle-status row for the applicant (always), the spouse
    // (only when the applicant is married -- mirrors dependent_spouse_*'s
    // visible_when), and dependent slots that actually have a name filled
    // in (an "empty" slot has nothing to verify a cycle for).
    const cycleStateSlots = Object.keys(cycleState)
        .filter((slotKey) => {
            if (slotKey === 'applicant') {
                return true;
            }

            if (slotKey === 'spouse') {
                return applicant?.civil_status === 'Married';
            }

            const name = dataSections.dependents?.[`dependent_${slotKey}_name`];

            return typeof name === 'string' && name.trim() !== '';
        })
        .map((slotKey) => {
            const label =
                slotKey === 'applicant'
                    ? 'Applicant'
                    : slotKey === 'spouse'
                      ? 'Spouse'
                      : (
                            dataSectionDefinitions.dependents.fields[
                                `dependent_${slotKey}_name`
                            ]?.label ?? slotKey
                        ).replace(/ name$/, '');

            return { slotKey, label };
        })
        .sort((a, b) => {
            const rank = (key: string) =>
                key === 'applicant' ? 0 : key === 'spouse' ? 1 : 2;

            return (
                rank(a.slotKey) - rank(b.slotKey) ||
                a.label.localeCompare(b.label)
            );
        });

    const renderCycleStateRow = (slotKey: string, label: string) => {
        const slotState = cycleState[slotKey];
        const [statusKey, numberKey] = cycleSlotFieldKeys(slotKey);
        const statusValue = processingForm.processing[statusKey];
        const numberValue = processingForm.processing[numberKey];

        return (
            <div
                key={slotKey}
                className="grid gap-3 rounded-lg border border-border/40 bg-muted/10 p-3 sm:grid-cols-[1fr_auto_auto]"
            >
                <div className="flex flex-col justify-center">
                    <span className="text-sm font-medium">{label}</span>
                    <span className="text-xs text-muted-foreground">
                        Auto-computed from loan history.
                    </span>
                </div>
                <div className="grid gap-2">
                    <Label className="text-xs text-muted-foreground">
                        Cycle status
                    </Label>
                    <Input
                        value={
                            typeof statusValue === 'string'
                                ? statusValue
                                : ''
                        }
                        disabled
                        className={readOnlyProcessingFieldClassName}
                    />
                </div>
                <div className="grid gap-2">
                    <Label className="text-xs text-muted-foreground">
                        Cycle number
                    </Label>
                    <Input
                        type="number"
                        min={1}
                        value={
                            numberValue !== null && numberValue !== undefined
                                ? `${numberValue}`
                                : ''
                        }
                        disabled
                        className={readOnlyProcessingFieldClassName}
                    />
                </div>
            </div>
        );
    };

    return (
        <Card
            className={
                canUpdateProcessing
                    ? actionCardClassName
                    : 'border-border/30 bg-card/70 shadow-sm'
            }
        >
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <FileText
                        className={cn(
                            'size-4',
                            canUpdateProcessing
                                ? 'text-primary'
                                : 'text-muted-foreground',
                        )}
                    />
                    Processing details
                </CardTitle>
                <CardDescription>
                    Recommendation and financial terms used across the document
                    package.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {canUpdateProcessing ? (
                    <form
                        className="space-y-4"
                        onSubmit={submitProcessingDetails}
                    >
                        {renderProcessingSectionLabel('Recommendation', {
                            first: true,
                        })}
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="inline_recommended_amount">
                                    Recommended amount
                                </Label>
                                <CurrencyInput
                                    id="inline_recommended_amount"
                                    value={processingForm.recommended_amount}
                                    onValueChange={(value) =>
                                        setProcessingForm((current) => ({
                                            ...current,
                                            recommended_amount: value,
                                        }))
                                    }
                                    onBlur={scheduleGnthpRecalculation}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="inline_recommended_term">
                                    Recommended term
                                </Label>
                                <MonthsInput
                                    id="inline_recommended_term"
                                    value={processingForm.recommended_term}
                                    onChange={(value) =>
                                        setProcessingForm((current) => ({
                                            ...current,
                                            recommended_term: value,
                                        }))
                                    }
                                    onBlur={scheduleGnthpRecalculation}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="inline_recommended_interest_rate">
                                    Recommended interest rate
                                </Label>
                                <PercentInput
                                    id="inline_recommended_interest_rate"
                                    value={
                                        processingForm.recommended_interest_rate
                                    }
                                    onValueChange={(value) =>
                                        setProcessingForm((current) => ({
                                            ...current,
                                            recommended_interest_rate: value,
                                        }))
                                    }
                                    onBlur={scheduleGnthpRecalculation}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="inline_recommended_payment_frequency"
                                    className="inline-flex items-center gap-1.5"
                                >
                                    Payment frequency
                                    <TooltipProvider delayDuration={0}>
                                        <Tooltip>
                                            <TooltipTrigger>
                                                <Info className="size-3.5 text-muted-foreground" />
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>
                                                    Member&apos;s payday:{' '}
                                                    {applicant?.payday || '—'}
                                                </p>
                                                <p>
                                                    Member requested:{' '}
                                                    {loanRequest.requested_payment_frequency ||
                                                        '—'}
                                                </p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </Label>
                                <Select
                                    value={
                                        processingForm.recommended_payment_frequency ||
                                        undefined
                                    }
                                    onValueChange={(value) => {
                                        setProcessingForm((current) => ({
                                            ...current,
                                            recommended_payment_frequency:
                                                value,
                                            // Due date forces loan security/savings to 0%;
                                            // switching away restores the standard default
                                            // so staff aren't stuck at 0%. The Due date month
                                            // count is derived from "Recommended term", not
                                            // entered separately.
                                            processing:
                                                value === 'Due date'
                                                    ? {
                                                          ...current.processing,
                                                          loan_security_rate: 0,
                                                          savings_rate: 0,
                                                      }
                                                    : {
                                                          ...current.processing,
                                                          loan_security_rate:
                                                              PROCESSING_CHARGE_DEFAULTS.loan_security_rate,
                                                          savings_rate:
                                                              PROCESSING_CHARGE_DEFAULTS.savings_rate,
                                                      },
                                        }));
                                        scheduleGnthpRecalculation();
                                    }}
                                >
                                    <SelectTrigger
                                        id="inline_recommended_payment_frequency"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select payment frequency" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {PAYMENT_FREQUENCY_OPTIONS.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option}
                                                    value={option}
                                                >
                                                    {option}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                {loanRequest.requested_payment_frequency && (
                                    <p className="text-xs text-muted-foreground">
                                        Member requested:{' '}
                                        {
                                            loanRequest.requested_payment_frequency
                                        }
                                    </p>
                                )}
                            </div>
                            {processingForm.recommended_payment_frequency ===
                                'Due date' && (
                                <p className="text-sm text-muted-foreground sm:col-span-2">
                                    Due date is repaid as a single payment after
                                    the recommended term above (
                                    {processingForm.recommended_term || '—'}{' '}
                                    month
                                    {processingForm.recommended_term === '1'
                                        ? ''
                                        : 's'}
                                    ).
                                </p>
                            )}
                        </div>
                        {renderProcessingSectionLabel('Charges & fees')}
                        <div className="grid gap-4 sm:grid-cols-2">
                            {renderProcessingField('service_charge_rate', {
                                onBlur: scheduleGnthpRecalculation,
                            })}
                            {renderProcessingField('insurance_rate', {
                                onBlur: scheduleGnthpRecalculation,
                                disabled: true,
                                tooltip: isApplicantInInsuranceAgeBand
                                    ? "Pesos per ₱1,000 of principal per month, NOT a percentage. Locked to the insurer's senior-age band rate for this applicant's age (66–70 → 2.05, 71–75 → 3.95)."
                                    : "Pesos per ₱1,000 of principal per month, NOT a percentage. Applicant is outside the insurer's senior-age bands (66–70 → 2.05, 71–75 → 3.95), so the rate is fixed at 1.",
                            })}
                            {renderProcessingField('insurance_term', {
                                onBlur: scheduleGnthpRecalculation,
                            })}
                            {processingForm.recommended_payment_frequency !==
                                'Due date' && (
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="inline_processing_loan_security_rate"
                                        className="inline-flex items-center gap-1.5"
                                    >
                                        Loan security / Savings rate
                                        <TooltipProvider delayDuration={0}>
                                            <Tooltip>
                                                <TooltipTrigger>
                                                    <Info className="size-3.5 text-muted-foreground" />
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>
                                                        Fixed institutional rate
                                                        (2%), matching the
                                                        reference workbook. Not
                                                        editable per loan.
                                                        Zeroed automatically for
                                                        Due date loans.
                                                    </p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </Label>
                                    <PercentInput
                                        id="inline_processing_loan_security_rate"
                                        value={
                                            processingForm.processing
                                                .loan_security_rate !== null &&
                                            processingForm.processing
                                                .loan_security_rate !==
                                                undefined
                                                ? `${processingForm.processing.loan_security_rate}`
                                                : ''
                                        }
                                        onValueChange={updateLoanSecurityRate}
                                        onBlur={scheduleGnthpRecalculation}
                                        disabled
                                    />
                                </div>
                            )}
                            {renderProcessingField('documentary_stamp_rate', {
                                onBlur: scheduleGnthpRecalculation,
                                disabled: true,
                                tooltip:
                                    'Fixed institutional rate (0.75%) — documentary stamp tax is ₱1.50 per ₱200 of the loan amount (or fraction thereof), so the amount rounds up per band, matching the reference workbook. Not editable per loan.',
                            })}
                            {renderProcessingField('notarial_fee', {
                                onBlur: scheduleGnthpRecalculation,
                                placeholder: 'Enter notarial fee',
                            })}
                            {renderProcessingField('other_charges_amount', {
                                onBlur: scheduleGnthpRecalculation,
                            })}
                            {renderProcessingField(
                                'other_charges_description',
                                {
                                    onBlur: scheduleGnthpRecalculation,
                                },
                            )}
                            {renderProcessingField('penalty_rate_per_month', {
                                onBlur: scheduleGnthpRecalculation,
                                disabled: true,
                                tooltip:
                                    'Fixed institutional rate (5% per month). Not editable per loan.',
                            })}
                        </div>
                        {renderProcessingSectionLabel('Net take-home pay')}
                        <div className="space-y-3">
                            <p className="text-sm text-muted-foreground">
                                Net proceeds and GNTHP are computed
                                automatically from the recommendation and
                                charges above.
                            </p>

                            {recommendationPreviewError && (
                                <p className="text-sm text-destructive">
                                    {recommendationPreviewError}
                                </p>
                            )}

                            <div className="rounded-lg border border-primary/20 bg-primary/5 p-4">
                                <div className="mb-3 flex items-center gap-2">
                                    <Label className="text-sm font-medium text-foreground">
                                        Net Proceeds (at recommended terms)
                                    </Label>
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <button
                                                    type="button"
                                                    className="text-muted-foreground hover:text-foreground"
                                                >
                                                    <Info className="h-4 w-4" />
                                                </button>
                                            </TooltipTrigger>
                                            <TooltipContent className="max-w-xs">
                                                <p>
                                                    Loan granted less finance
                                                    charges (service charge),
                                                    non-finance charges
                                                    (insurance, loan security,
                                                    documentary stamp, notarial
                                                    fee, and other charges), and
                                                    total charges. Interest is
                                                    disclosed under "Not
                                                    Deducted From Proceeds of
                                                    Loan" — it is amortized into
                                                    the payment schedule instead
                                                    of being deducted here.
                                                </p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </div>
                                {isRecommendationPreviewLoading ? (
                                    <div className="flex items-center gap-2 text-muted-foreground">
                                        <div className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                        <span className="text-sm">
                                            Calculating…
                                        </span>
                                    </div>
                                ) : recommendationPreview &&
                                  recommendationPreview.net_proceeds_raw !==
                                      null ? (
                                    <div className="space-y-1.5">
                                        <div className="flex items-center justify-between gap-3 text-sm">
                                            <span className="text-muted-foreground">
                                                Loan granted
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                {formatCurrency(
                                                    recommendationPreview.approved_amount_raw,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between gap-3 text-sm">
                                            <span className="text-muted-foreground">
                                                Finance charges
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                {formatCurrency(
                                                    recommendationPreview.finance_charge_total_raw,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between gap-3 text-sm">
                                            <span className="text-muted-foreground">
                                                Non-finance charges
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                {formatCurrency(
                                                    recommendationPreview.non_finance_charge_total_raw,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between gap-3 text-sm">
                                            <span className="text-muted-foreground">
                                                Total charges
                                            </span>
                                            <span className="font-medium tabular-nums">
                                                {formatCurrency(
                                                    recommendationPreview.deductions_total_raw,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between gap-3 border-t border-primary/20 pt-2">
                                            <span className="text-sm font-medium text-foreground">
                                                Net Proceeds
                                            </span>
                                            <span className="text-2xl font-semibold text-foreground tabular-nums">
                                                {formatCurrency(
                                                    recommendationPreview.net_proceeds_raw,
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                ) : recommendationPreview ? (
                                    <p className="text-sm text-muted-foreground">
                                        Not enough data to compute.
                                    </p>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Fill in recommendation and charges above
                                        to calculate.
                                    </p>
                                )}
                            </div>

                            {renderProcessingField(
                                'guaranteed_net_take_home_pay',
                                {
                                    disabled: true,
                                    className: readOnlyProcessingFieldClassName,
                                    placeholder: isRecommendationPreviewLoading
                                        ? 'Recalculating…'
                                        : 'Computed automatically',
                                    tooltip:
                                        'Guaranteed Net Take-Home Pay is always the gross monthly income minus the monthly amortization at the recommended terms — it cannot be edited manually.',
                                },
                            )}

                            {recommendationPreview &&
                                recommendationPreview.suggested_gnthp_raw ===
                                    null && (
                                    <p className="text-xs text-muted-foreground">
                                        {
                                            recommendationPreview
                                                .failure_information?.message
                                        }
                                        {recommendationPreview
                                            .failure_information?.blockers
                                            .length
                                            ? ` ${recommendationPreview.failure_information.blockers.join(' ')}`
                                            : null}
                                    </p>
                                )}
                        </div>

                        {renderProcessingSectionLabel('Personnel')}
                        <div className="grid gap-4 sm:grid-cols-2">
                            {renderProcessingField('witness_one_name', {
                                disabled: true,
                                placeholder:
                                    "Filled automatically from the assigned processor's name",
                                tooltip:
                                    "Recorded automatically using the assigned processor's name.",
                            })}
                            {loanManagers.length > 1 ? (
                                <div
                                    key="witness_two_name"
                                    className="grid gap-2"
                                >
                                    <Label
                                        htmlFor="inline_processing_witness_two_name"
                                        className="inline-flex items-center gap-1.5"
                                    >
                                        {
                                            dataSectionDefinitions.processing
                                                .fields.witness_two_name?.label
                                        }
                                        <TooltipProvider delayDuration={0}>
                                            <Tooltip>
                                                <TooltipTrigger>
                                                    <Info className="size-3.5 text-muted-foreground" />
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>
                                                        Select the loan manager
                                                        who will witness this
                                                        loan. Their name is
                                                        recorded automatically
                                                        on the documents.
                                                    </p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </Label>
                                    <Select
                                        value={
                                            typeof processingForm.processing
                                                .witness_two_id === 'number'
                                                ? String(
                                                      processingForm.processing
                                                          .witness_two_id,
                                                  )
                                                : undefined
                                        }
                                        onValueChange={(value) => {
                                            const manager = loanManagers.find(
                                                (m) => String(m.id) === value,
                                            );
                                            if (manager) {
                                                updateProcessingSectionField(
                                                    'witness_two_id',
                                                    manager.id,
                                                );
                                                updateProcessingSectionField(
                                                    'witness_two_name',
                                                    manager.name,
                                                );
                                            }
                                        }}
                                        disabled={!canUpdateProcessing}
                                    >
                                        <SelectTrigger
                                            id="inline_processing_witness_two_name"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select loan manager" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {loanManagers.map((manager) => (
                                                <SelectItem
                                                    key={manager.id}
                                                    value={String(manager.id)}
                                                >
                                                    {manager.name} (
                                                    {manager.active_loans}{' '}
                                                    {manager.active_loans === 1
                                                        ? 'loan'
                                                        : 'loans'}{' '}
                                                    in flight)
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            ) : (
                                renderProcessingField('witness_two_name', {
                                    disabled: true,
                                    placeholder:
                                        loanManagers.length === 1
                                            ? "Filled automatically from the loan manager's name"
                                            : 'Filled automatically upon approval',
                                    tooltip:
                                        loanManagers.length === 1
                                            ? "Recorded automatically using the sole active loan manager's name."
                                            : "Recorded automatically using the approving manager's name when the request is approved.",
                                })
                            )}
                            {loanRequest.authority_to_deduct_guidance
                                ?.category === 'blgu' && (
                                <>
                                    {renderProcessingField(
                                        'barangay_official_name',
                                    )}
                                    {renderProcessingField(
                                        'barangay_official_title',
                                    )}
                                    {renderProcessingField(
                                        'barangay_official_designation',
                                    )}
                                    {renderProcessingField(
                                        'barangay_agency_name',
                                    )}
                                    {renderProcessingField(
                                        'barangay_agency_address',
                                    )}
                                </>
                            )}
                        </div>

                        {cycleStateSlots.length > 0 && (
                            <>
                                {renderProcessingSectionLabel(
                                    'Group Life Insurance Cycle (Auto-computed)',
                                )}
                                <div className="grid gap-3">
                                    {cycleStateSlots.map(({ slotKey, label }) =>
                                        renderCycleStateRow(slotKey, label),
                                    )}
                                </div>
                            </>
                        )}

                        {loanRequest.authority_to_deduct_guidance
                            ?.applicable !== false && (
                            <>
                                {renderProcessingSectionLabel(
                                    'Authority to Deduct (Salary Deduction)',
                                )}
                                {loanRequest.authority_to_deduct_guidance
                                    ?.note && (
                                    <p className="mb-3 text-sm text-muted-foreground">
                                        {
                                            loanRequest
                                                .authority_to_deduct_guidance
                                                .note
                                        }
                                    </p>
                                )}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {renderProcessingField(
                                        'authority_to_deduct_institution_name',
                                        { fullWidth: true },
                                    )}
                                    {loanRequest.authority_to_deduct_guidance
                                        ?.saved_contact &&
                                        `${processingForm.processing.authority_to_deduct_officer_1_name ?? ''}`.trim() ===
                                            '' && (
                                            <button
                                                type="button"
                                                className="text-left text-sm text-primary hover:underline sm:col-span-2"
                                                onClick={() => {
                                                    const savedContact =
                                                        loanRequest
                                                            .authority_to_deduct_guidance
                                                            ?.saved_contact;

                                                    if (!savedContact) {
                                                        return;
                                                    }

                                                    setProcessingForm(
                                                        (current) => ({
                                                            ...current,
                                                            processing: {
                                                                ...current.processing,
                                                                authority_to_deduct_officer_1_name:
                                                                    savedContact.officer_1_name,
                                                                authority_to_deduct_officer_1_title:
                                                                    savedContact.officer_1_title,
                                                                authority_to_deduct_officer_2_name:
                                                                    savedContact.officer_2_name,
                                                                authority_to_deduct_officer_2_title:
                                                                    savedContact.officer_2_title,
                                                            },
                                                        }),
                                                    );

                                                    if (
                                                        savedContact.officer_2_name ||
                                                        savedContact.officer_2_title
                                                    ) {
                                                        setShowSecondOfficer(
                                                            true,
                                                        );
                                                    }
                                                }}
                                            >
                                                Use saved officer(s) for this
                                                institution
                                            </button>
                                        )}
                                    <label className="flex items-start gap-3 rounded-lg border border-border/40 bg-muted/10 p-3 text-sm sm:col-span-2">
                                        <Checkbox
                                            checked={officersUnknown}
                                            onCheckedChange={(checked) => {
                                                const isUnknown =
                                                    checked === true;

                                                setProcessingForm(
                                                    (current) => ({
                                                        ...current,
                                                        processing: {
                                                            ...current.processing,
                                                            authority_to_deduct_officers_unknown:
                                                                isUnknown,
                                                            ...(isUnknown
                                                                ? {
                                                                      authority_to_deduct_officer_1_name:
                                                                          null,
                                                                      authority_to_deduct_officer_1_title:
                                                                          null,
                                                                      authority_to_deduct_officer_2_name:
                                                                          null,
                                                                      authority_to_deduct_officer_2_title:
                                                                          null,
                                                                  }
                                                                : {}),
                                                        },
                                                    }),
                                                );
                                            }}
                                        />
                                        <span>
                                            I don&apos;t know the officer
                                            information yet — leave these fields
                                            blank
                                        </span>
                                    </label>
                                    {renderProcessingField(
                                        'authority_to_deduct_officer_1_name',
                                        {
                                            disabled: officersUnknown,
                                            className: officersUnknown
                                                ? readOnlyProcessingFieldClassName
                                                : undefined,
                                        },
                                    )}
                                    {renderProcessingField(
                                        'authority_to_deduct_officer_1_title',
                                        {
                                            disabled: officersUnknown,
                                            className: officersUnknown
                                                ? readOnlyProcessingFieldClassName
                                                : undefined,
                                        },
                                    )}
                                    {showSecondOfficer ? (
                                        <>
                                            {renderProcessingField(
                                                'authority_to_deduct_officer_2_name',
                                                {
                                                    disabled: officersUnknown,
                                                    className: officersUnknown
                                                        ? readOnlyProcessingFieldClassName
                                                        : undefined,
                                                },
                                            )}
                                            {renderProcessingField(
                                                'authority_to_deduct_officer_2_title',
                                                {
                                                    disabled: officersUnknown,
                                                    className: officersUnknown
                                                        ? readOnlyProcessingFieldClassName
                                                        : undefined,
                                                },
                                            )}
                                        </>
                                    ) : (
                                        !officersUnknown && (
                                            <button
                                                type="button"
                                                className="text-left text-sm text-primary hover:underline sm:col-span-2"
                                                onClick={() =>
                                                    setShowSecondOfficer(true)
                                                }
                                            >
                                                + Add second officer
                                            </button>
                                        )
                                    )}
                                </div>
                            </>
                        )}
                        {loanRequest.waiver_applicability?.deped.applicable && (
                            <>
                                {renderProcessingSectionLabel('Waiver (DepEd)')}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {renderProcessingField(
                                        'deped_school_id_number',
                                    )}
                                    {renderProcessingField(
                                        'deped_deduction_amount',
                                    )}
                                </div>
                            </>
                        )}

                        {loanRequest.waiver_applicability?.pension
                            .applicable && (
                            <>
                                {renderProcessingSectionLabel(
                                    'Waiver (Pensioners)',
                                )}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {renderProcessingField('pension_provider')}
                                    {renderProcessingField('pension_bank_name')}
                                    {renderProcessingField(
                                        'pension_atm_card_number',
                                    )}
                                    {renderProcessingField(
                                        'pension_deduction_amount',
                                    )}
                                </div>
                            </>
                        )}

                        <Separator className="bg-border/40" />
                        <div className="grid gap-2">
                            <Label htmlFor="inline_processing_reason">
                                Remarks{' '}
                                {isFirstProcessingSave && (
                                    <span className="text-xs font-normal text-muted-foreground">
                                        (optional)
                                    </span>
                                )}
                            </Label>
                            <textarea
                                id="inline_processing_reason"
                                className={textareaClassName}
                                placeholder={
                                    isFirstProcessingSave
                                        ? 'Optional — add context beyond the auto-generated summary.'
                                        : 'Required — explain why you’re making this change.'
                                }
                                value={processingForm.reason}
                                aria-describedby="inline_processing_reason_message"
                                aria-invalid={reasonError !== null}
                                onChange={(event) => {
                                    setReasonError(null);
                                    setProcessingForm((current) => ({
                                        ...current,
                                        reason: event.target.value,
                                    }));
                                }}
                            />
                            <FieldMessage
                                id="inline_processing_reason_message"
                                error={reasonError ?? undefined}
                            />
                        </div>
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={isProcessing}
                        >
                            Save processing details
                        </Button>
                    </form>
                ) : (
                    <div className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <SnapshotRow
                                label="Recommended amount"
                                value={snapshotCurrency(
                                    loanRequest.recommended_amount,
                                )}
                            />
                            <SnapshotRow
                                label="Recommended term"
                                value={recommendedTermLabel}
                            />
                            <SnapshotRow
                                label="Recommended interest rate"
                                value={snapshotDisplay(
                                    loanRequest.recommended_interest_rate,
                                )}
                            />
                            <SnapshotRow
                                label="Payment frequency"
                                value={snapshotDisplay(
                                    loanRequest.recommended_payment_frequency,
                                )}
                            />
                        </div>
                        <Separator className="bg-border/40" />
                        <div className="grid gap-4 sm:grid-cols-2">
                            {Object.entries(
                                dataSectionDefinitions.processing.fields,
                            )
                                .filter(
                                    ([fieldKey]) =>
                                        !SNAPSHOT_HIDDEN_FIELDS.has(fieldKey) &&
                                        !SNAPSHOT_GATED_FIELDS.has(fieldKey),
                                )
                                .map(([fieldKey]) =>
                                    renderSnapshotField(fieldKey),
                                )}
                        </div>

                        {loanRequest.authority_to_deduct_guidance?.category ===
                            'blgu' && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                {SNAPSHOT_BARANGAY_FIELDS.map((fieldKey) =>
                                    renderSnapshotField(fieldKey),
                                )}
                            </div>
                        )}

                        {loanRequest.authority_to_deduct_guidance
                            ?.applicable !== false && (
                            <>
                                {renderProcessingSectionLabel(
                                    'Authority to Deduct (Salary Deduction)',
                                )}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {SNAPSHOT_AUTHORITY_TO_DEDUCT_FIELDS.map(
                                        (fieldKey) =>
                                            renderSnapshotField(fieldKey),
                                    )}
                                </div>
                            </>
                        )}

                        {loanRequest.waiver_applicability?.deped.applicable && (
                            <>
                                {renderProcessingSectionLabel('Waiver (DepEd)')}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {SNAPSHOT_DEPED_FIELDS.map((fieldKey) =>
                                        renderSnapshotField(fieldKey),
                                    )}
                                </div>
                            </>
                        )}

                        {loanRequest.waiver_applicability?.pension
                            .applicable && (
                            <>
                                {renderProcessingSectionLabel(
                                    'Waiver (Pensioners)',
                                )}
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {SNAPSHOT_PENSION_FIELDS.map((fieldKey) =>
                                        renderSnapshotField(fieldKey),
                                    )}
                                </div>
                            </>
                        )}

                        <p className="text-xs text-muted-foreground">
                            Only the assigned loan processor can edit processing
                            terms.
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
