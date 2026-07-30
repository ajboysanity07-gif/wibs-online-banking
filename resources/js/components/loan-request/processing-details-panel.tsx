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
import { showErrorToast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import type {
    LoanRequestDataSectionDefinitions,
    LoanRequestDataSections,
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

const numericProcessingFieldValue = (
    value: string | number | boolean | null | undefined,
): string | number | null =>
    typeof value === 'boolean' || value === undefined ? null : value;

// Frontend-only override: dataSectionDefinitions field metadata has no currency/percent/months distinction, only 'number'/'integer'.
const PROCESSING_FIELD_KIND: Record<string, 'currency' | 'percent' | 'months'> =
    {
        notarial_fee: 'currency',
        guaranteed_net_take_home_pay: 'currency',
        service_charge_rate: 'percent',
        insurance_rate: 'percent',
        loan_security_rate: 'percent',
        savings_rate: 'percent',
        documentary_stamp_rate: 'percent',
        penalty_rate_per_month: 'percent',
        insurance_term: 'months',
    };

// Category A — financial processing terms edited inline on the page.
type InlineProcessingFormState = {
    processing: Record<string, string | number | boolean | null>;
    recommended_amount: string;
    recommended_term: string;
    recommended_interest_rate: string;
    recommended_payment_frequency: string;
    recommendation_remarks: string;
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
    net_proceeds_raw: number | null;
    suggested_gnthp_raw: number | null;
    failure_information: { message: string; blockers: string[] } | null;
};

type ProcessingDetailsPanelProps = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    dataSections: LoanRequestDataSections;
    dataSectionDefinitions: LoanRequestDataSectionDefinitions;
    canUpdateProcessing: boolean;
    isProcessing: boolean;
    updateProcessingDetails: (
        loanRequestId: number,
        payload: LoanRequestProcessingDetailsPayload,
    ) => Promise<LoanRequestWorkflowResult | null>;
};

export function ProcessingDetailsPanel({
    loanRequest,
    applicant,
    dataSections,
    dataSectionDefinitions,
    canUpdateProcessing,
    isProcessing,
    updateProcessingDetails,
}: ProcessingDetailsPanelProps) {
    const [processingForm, setProcessingForm] =
        useState<InlineProcessingFormState>({
            processing: withWitnessOneAutoFill(
                { ...dataSections.processing },
                loanRequest.assigned_processor,
            ),
            recommended_amount: toStringValue(loanRequest.recommended_amount),
            recommended_term: toStringValue(loanRequest.recommended_term),
            recommended_interest_rate: toStringValue(
                loanRequest.recommended_interest_rate,
            ),
            recommended_payment_frequency:
                loanRequest.recommended_payment_frequency ?? '',
            recommendation_remarks: loanRequest.recommendation_remarks ?? '',
            reason: '',
        });
    const [recommendationPreview, setRecommendationPreview] =
        useState<RecommendationPreviewState | null>(null);
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

    useEffect(() => {
        setProcessingForm({
            processing: withWitnessOneAutoFill(
                { ...dataSections.processing },
                loanRequest.assigned_processor,
            ),
            recommended_amount: toStringValue(loanRequest.recommended_amount),
            recommended_term: toStringValue(loanRequest.recommended_term),
            recommended_interest_rate: toStringValue(
                loanRequest.recommended_interest_rate,
            ),
            recommended_payment_frequency:
                loanRequest.recommended_payment_frequency ?? '',
            recommendation_remarks: loanRequest.recommendation_remarks ?? '',
            reason: '',
        });
        setShowSecondOfficer(
            loanRequest.authority_to_deduct_guidance?.recommended_officers !==
                1 || hasSecondOfficerValue(dataSections.processing),
        );
    }, [
        dataSections.processing,
        loanRequest.assigned_processor,
        loanRequest.authority_to_deduct_guidance,
        loanRequest.recommendation_remarks,
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

        if (processingForm.reason.trim() === '') {
            showErrorToast(
                null,
                'Remarks are required before saving processing details.',
            );
            return;
        }

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
            recommendation_remarks:
                processingForm.recommendation_remarks || null,
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
                            field.type === 'number' ||
                            field.type === 'integer'
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
                                        {PAYDAY_OPTIONS.map((option) => (
                                            <SelectItem
                                                key={option}
                                                value={option}
                                            >
                                                {option}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="inline_recommendation_remarks">
                                Recommendation remarks
                            </Label>
                            <textarea
                                id="inline_recommendation_remarks"
                                className={textareaClassName}
                                value={processingForm.recommendation_remarks}
                                onChange={(event) =>
                                    setProcessingForm((current) => ({
                                        ...current,
                                        recommendation_remarks:
                                            event.target.value,
                                    }))
                                }
                            />
                        </div>

                        {renderProcessingSectionLabel('Charges & fees')}
                        <div className="grid gap-4 sm:grid-cols-2">
                            {renderProcessingField('service_charge_rate', {
                                onBlur: scheduleGnthpRecalculation,
                            })}
                            {renderProcessingField('insurance_rate', {
                                onBlur: scheduleGnthpRecalculation,
                            })}
                            {renderProcessingField('insurance_term', {
                                onBlur: scheduleGnthpRecalculation,
                            })}
                            {renderProcessingField('loan_security_rate', {
                                onBlur: scheduleGnthpRecalculation,
                            })}
                            {renderProcessingField('savings_rate', {
                                onBlur: scheduleGnthpRecalculation,
                            })}
                            {renderProcessingField('documentary_stamp_rate', {
                                onBlur: scheduleGnthpRecalculation,
                            })}
                            {renderProcessingField('notarial_fee', {
                                onBlur: scheduleGnthpRecalculation,
                            })}
                            {renderProcessingField('notarial_venue', {
                                onBlur: scheduleGnthpRecalculation,
                            })}
                            {renderProcessingField('penalty_rate_per_month', {
                                onBlur: scheduleGnthpRecalculation,
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

                            {recommendationPreview && (
                                <p className="text-sm">
                                    Net proceeds (at recommended terms):{' '}
                                    {recommendationPreview.net_proceeds_raw !==
                                    null ? (
                                        <span className="font-medium">
                                            {formatCurrency(
                                                recommendationPreview.net_proceeds_raw,
                                            )}
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            Not enough data to compute.
                                        </span>
                                    )}
                                </p>
                            )}

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
                            {renderProcessingField('witness_two_name', {
                                disabled: true,
                                placeholder:
                                    'Filled automatically upon approval',
                                tooltip:
                                    "Recorded automatically using the approving manager's name when the request is approved.",
                            })}
                            {renderProcessingField('barangay_official_name')}
                            {renderProcessingField('barangay_official_title')}
                        </div>

                        {loanRequest.authority_to_deduct_guidance?.applicable !==
                            false && (
                            <>
                                {renderProcessingSectionLabel(
                                    'Authority to Deduct',
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
                                                className="text-sm text-primary hover:underline sm:col-span-2 text-left"
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
                                    {renderProcessingField(
                                        'authority_to_deduct_officer_1_name',
                                    )}
                                    {renderProcessingField(
                                        'authority_to_deduct_officer_1_title',
                                    )}
                                    {showSecondOfficer ? (
                                        <>
                                            {renderProcessingField(
                                                'authority_to_deduct_officer_2_name',
                                            )}
                                            {renderProcessingField(
                                                'authority_to_deduct_officer_2_title',
                                            )}
                                        </>
                                    ) : (
                                        <button
                                            type="button"
                                            className="text-sm text-primary hover:underline sm:col-span-2 text-left"
                                            onClick={() =>
                                                setShowSecondOfficer(true)
                                            }
                                        >
                                            + Add second officer
                                        </button>
                                    )}
                                </div>
                            </>
                        )}
                        {loanRequest.authority_to_deduct_guidance
                            ?.applicable === false && (
                            <>
                                {renderProcessingSectionLabel(
                                    'Authority to Deduct',
                                )}
                                <p className="text-sm text-muted-foreground">
                                    {
                                        loanRequest.authority_to_deduct_guidance
                                            .note
                                    }
                                </p>
                            </>
                        )}

                        <Separator className="bg-border/40" />
                        <div className="grid gap-2">
                            <Label htmlFor="inline_processing_reason">
                                Remarks
                            </Label>
                            <textarea
                                id="inline_processing_reason"
                                className={textareaClassName}
                                value={processingForm.reason}
                                onChange={(event) =>
                                    setProcessingForm((current) => ({
                                        ...current,
                                        reason: event.target.value,
                                    }))
                                }
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
                        <SnapshotRow
                            label="Recommendation remarks"
                            value={snapshotDisplay(
                                loanRequest.recommendation_remarks,
                            )}
                        />
                        <Separator className="bg-border/40" />
                        <div className="grid gap-4 sm:grid-cols-2">
                            {Object.entries(
                                dataSectionDefinitions.processing.fields,
                            ).map(([fieldKey, field]) => {
                                const value = dataSections.processing[fieldKey];
                                const display =
                                    field.type === 'boolean'
                                        ? value === true
                                            ? 'Yes'
                                            : value === false
                                              ? 'No'
                                              : '—'
                                        : snapshotDisplay(
                                              value as string | number | null,
                                          );

                                return (
                                    <SnapshotRow
                                        key={fieldKey}
                                        label={field.label}
                                        value={display}
                                    />
                                );
                            })}
                        </div>
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
