import { FileText, Info, Pencil } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useRef,
    useState,
    type FormEvent,
    type ReactNode,
} from 'react';
import { DEPENDENT_CATEGORIES } from '@/components/dependents/dependent-category-section';
import { PAYDAY_OPTIONS } from '@/components/loan-request/loan-request-fields';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import {
    CurrencyInput,
    fractionToPercentDisplay,
    MonthsInput,
    PercentInput,
} from '@/components/loan-request/numeric-adorned-inputs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { FormErrorSummary } from '@/components/ui/form-error-summary';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
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
import type { LoanRequestChecklistPreviewItem } from '@/lib/api/admin';
import { formatCurrency } from '@/lib/formatters';
import {
    AUTHORITY_TO_DEDUCT_OFFICER_TITLES,
    INSTITUTIONAL_EMPLOYER_CATEGORY_LABELS,
    INSTITUTIONAL_EMPLOYER_CATEGORY_OPTIONS,
    resolveInstitutionalEmployerCategory,
} from '@/lib/institutional-employer-category';
import { missingSignatoryFields } from '@/lib/loan-request-review-tab';
import { cn } from '@/lib/utils';
import type {
    LoanManagerOption,
    LoanRequestBankingSectionValues,
    LoanRequestCycleState,
    LoanRequestDataSectionDefinitions,
    LoanRequestDataSections,
    LoanRequestDataSectionValues,
    LoanRequestDetail,
    LoanRequestPersonData,
    LoanRequestReviewer,
    LoanRequestWorkflowResult,
    SavedPaymentAccountSnapshotDetail,
} from '@/types/loan-requests';

export const textareaClassName =
    'flex min-h-[112px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive';

// PDC Schedule (Annex A) document generation is temporarily disabled
// (see LoanRequestDocumentKey::temporarilyDisabled()); pdc_drawee_bank has
// no other consumer, so hide it alongside the document. Flip to false to
// restore both.
const PDC_SCHEDULE_TEMPORARILY_DISABLED = true;

const INSTITUTIONAL_EMPLOYER_CATEGORY_UNSET_VALUE = 'unset';

const actionCardClassName =
    'border-primary/25 bg-card shadow-card ring-1 ring-primary/10';
const sectionEditButtonClassName =
    'transition-all duration-150 ease-out active:scale-90 active:duration-75 hover:-translate-y-0.5 hover:shadow-md [&_svg]:transition-transform [&_svg]:duration-150 active:[&_svg]:rotate-12';
const readOnlyProcessingFieldClassName =
    'bg-muted/30 text-muted-foreground/80 border-border';

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

// Rates are stored as decimal fractions (see numeric-adorned-inputs.tsx); the
// snapshot view converts to a real percentage, trimming trailing zeros so
// 0.25 reads "25%" and 0.0075 reads "0.75%" rather than "25.00%".
export const snapshotPercent = (value?: string | number | null): string => {
    if (value === null || value === undefined || `${value}`.trim() === '') {
        return '—';
    }

    const display = fractionToPercentDisplay(`${value}`);

    return display !== '' ? `${display}%` : '—';
};

export const PolicyTag = () => (
    <span className="rounded border border-border px-1 text-[10px] font-bold tracking-wide text-muted-foreground uppercase">
        Policy
    </span>
);

export const SnapshotRow = ({
    label,
    value,
    className,
    locked = false,
    strong = false,
    destructive = false,
}: {
    label: string;
    value: string;
    className?: string;
    /** Set by policy, not editable per loan. */
    locked?: boolean;
    strong?: boolean;
    /** A required value that is missing. */
    destructive?: boolean;
}) => (
    <div className={cn('min-w-0', className)}>
        <p className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
            {label}
            {locked ? <PolicyTag /> : null}
        </p>
        <p
            className={cn(
                'text-sm [overflow-wrap:anywhere] tabular-nums',
                strong ? 'text-[15px] font-bold' : 'font-semibold',
                destructive && 'text-destructive',
            )}
        >
            {value}
        </p>
    </div>
);

/** Uppercase label + divider + responsive auto-fill grid. */
function SnapshotGroup({
    title,
    first = false,
    children,
}: {
    title: string;
    first?: boolean;
    children: ReactNode;
}) {
    return (
        <div className={first ? undefined : 'pt-4'}>
            <p className="mb-2 border-b border-border pb-1.5 text-[11px] font-bold tracking-[0.14em] text-muted-foreground uppercase">
                {title}
            </p>
            <div className="grid grid-cols-1 gap-x-[18px] gap-y-3.5 sm:grid-cols-[repeat(auto-fill,minmax(190px,1fr))]">
                {children}
            </div>
        </div>
    );
}

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

const SNAPSHOT_BARANGAY_FIELDS = [
    'barangay_official_name',
    'barangay_official_title',
    'barangay_official_designation',
    'barangay_agency_name',
    'barangay_agency_address',
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

const SNAPSHOT_PDC_FIELDS = ['pdc_drawee_bank'];

const BANKING_ACCOUNT_DETAIL_LABELS: {
    key: keyof SavedPaymentAccountSnapshotDetail;
    label: string;
}[] = [
    { key: 'bank_name', label: 'Bank name' },
    { key: 'account_name', label: 'Account name' },
    { key: 'account_number', label: 'Account number' },
    { key: 'account_type', label: 'Account type' },
    { key: 'atm_number', label: 'ATM number' },
    { key: 'bank_branch', label: 'Bank branch' },
    { key: 'atm_holder_name', label: 'ATM card holder name' },
];

const PROCESSING_CHARGE_DEFAULTS: Record<string, number> = {
    // Fallback only -- loan_security_rate/savings_rate are actually resolved
    // by resolveDefaultLoanSecurityRate() below, which is typecode-aware.
    loan_security_rate: 0.02,
    savings_rate: 0.02,
    // Institutional documentary stamp constant (₱1.50 per ₱200 of loan =
    // 1.5/200 = 0.75%); the amount itself follows the ₱200 banding rule.
    documentary_stamp_rate: 0.0075,
    penalty_rate_per_month: 0.05,
};

// Mirrors WIBS desktop's loanpay.SCT: loansec = IIF(typc='01', prn*.02, prn*.05).
// Typecode '01' ("Other Loan") is hardcoded there to 2% Loan Security; every
// other loan type is hardcoded to 5%. There is no rate field on the WIBS
// side -- just this typecode branch -- so it's reproduced here as the
// suggested default (still staff-editable, same as before).
const OTHER_LOAN_TYPECODE = '01';

export const resolveDefaultLoanSecurityRate = (
    typecode: string | null | undefined,
): number => (typecode === OTHER_LOAN_TYPECODE ? 0.02 : 0.05);

// Insurer's senior-age insurance rate bands (from the loan processors'
// reference table). Only these two bands are currently known; applicants
// outside them are locked to a fixed rate of 1 (see withProcessingChargeDefaults).
// These are per-mille rates (pesos per ₱1,000 of principal per month), NOT
// percentages -- insurance_rate feeds insurance_premium = (amount/1000) *
// insurance_term * insurance_rate, so the raw table value (e.g. 2.05) must
// be stored as-is, unlike the other *_rate fields which are true percentages.
export const INSURANCE_RATE_AGE_BANDS: {
    minAge: number;
    maxAge: number;
    rate: number;
}[] = [
    { minAge: 66, maxAge: 70, rate: 2.05 },
    { minAge: 71, maxAge: 75, rate: 3.95 },
];

export const calculateAgeFromBirthdate = (
    birthdate: string | null,
): number | null => {
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
    isInsuranceSkipped = false,
    typecode: string | null | undefined = null,
): Record<string, string | number | boolean | null> => {
    let next = processing;

    const defaultLoanSecurityRate = resolveDefaultLoanSecurityRate(typecode);
    const defaults: Record<string, number> = {
        ...PROCESSING_CHARGE_DEFAULTS,
        loan_security_rate: defaultLoanSecurityRate,
        savings_rate: defaultLoanSecurityRate,
    };

    for (const [key, defaultValue] of Object.entries(defaults)) {
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
    // otherwise fixed at 1 -- unless insurance itself is skipped (term under
    // two months), in which case both insurance_rate and insurance_term are
    // locked to 0, mirroring ApprovedLoanDocumentDataBuilder's
    // $isDueDateNoInsurance. Force these every time (not just when blank) so
    // a stale saved value never survives a re-render.
    const ageBandedRate = resolveAgeBandedInsuranceRate(applicantBirthdate);
    next = {
        ...next,
        insurance_rate: isInsuranceSkipped ? 0 : (ageBandedRate ?? 1),
        insurance_term: isInsuranceSkipped ? 0 : next.insurance_term,
    };

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

// Every cycle slot key the panel can show: applicant (always auto-computed,
// see LoanRequestCycleStateService), spouse, and every dependent category
// slot (mirrors MemberDependentProfile::CATEGORY_CAPS). Only 'applicant'
// ever appears in the server-supplied cycleState -- the rest are always
// manually entered, so their presence here doesn't depend on it.
const ALL_CYCLE_SLOT_KEYS: string[] = [
    'applicant',
    'spouse',
    ...DEPENDENT_CATEGORIES.flatMap((category) =>
        Array.from(
            { length: category.cap },
            (_, index) => `${category.key}_${index + 1}`,
        ),
    ),
];

// Seeds/refreshes the cycle-status fields inside processingForm.processing.
// Locked slots (applicant) always take the server-computed value (never a
// stale saved one). Unlocked slots (spouse/dependents) are manually entered
// by the processor, so they simply keep whatever was last saved for this
// loan request.
const withCycleStateDefaults = (
    processing: Record<string, string | number | boolean | null>,
    dependentsSection: LoanRequestDataSectionValues | undefined,
    cycleState: LoanRequestCycleState,
): Record<string, string | number | boolean | null> => {
    let next = processing;

    ALL_CYCLE_SLOT_KEYS.forEach((slotKey) => {
        const [statusKey, numberKey] = cycleSlotFieldKeys(slotKey);
        const slotState = cycleState[slotKey];

        if (slotState?.locked) {
            next = {
                ...next,
                [statusKey]: slotState.cycle_status,
                [numberKey]: slotState.cycle_number,
            };

            return;
        }

        next = {
            ...next,
            [statusKey]: dependentsSection?.[statusKey] ?? null,
            [numberKey]: dependentsSection?.[numberKey] ?? null,
        };
    });

    return next;
};

const numericProcessingFieldValue = (
    value: string | number | boolean | null | undefined,
): string | number | null =>
    typeof value === 'boolean' || value === undefined ? null : value;

// Inputs to the backend preview; any change re-runs the live computation.
const PREVIEW_PROCESSING_KEYS = [
    'service_charge_rate',
    'insurance_rate',
    'insurance_term',
    'loan_security_rate',
    'savings_rate',
    'documentary_stamp_rate',
    'notarial_fee',
    'other_charges_amount',
    'other_charges_description',
    'penalty_rate_per_month',
];

// Written by the system alongside a staff edit (mirrored rate, locked
// policy values, computed GNTHP), so they never count as a change.
const DERIVED_PROCESSING_KEYS = [
    'savings_rate',
    'insurance_rate',
    'penalty_rate_per_month',
    'guaranteed_net_take_home_pay',
    'witness_two_id',
];

const RECOMMENDATION_FORM_KEYS = [
    'recommended_amount',
    'recommended_term',
    'recommended_interest_rate',
    'recommended_payment_frequency',
    'institutional_employer_category',
] as const;

// "30000" and "30000.00" are the same value.
const sameFormValue = (
    a: string | number | boolean | null | undefined,
    b: string | number | boolean | null | undefined,
): boolean => {
    const left = `${a ?? ''}`.trim();
    const right = `${b ?? ''}`.trim();

    return (
        left === right ||
        (left !== '' && right !== '' && Number(left) === Number(right))
    );
};

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
    institutional_employer_category: string;
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

export type RecommendationPreviewState = {
    approved_amount_raw: number | null;
    service_charge_amount_raw: number | null;
    interest_not_deducted_raw: number | null;
    finance_charge_total_raw: number | null;
    insurance_premium_raw: number | null;
    loan_security_amount_raw: number | null;
    documentary_stamp_amount_raw: number | null;
    notarial_fee_raw: number | null;
    other_charges_amount_raw: number | null;
    other_charges_description: string | null;
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
    // Full validation failure from the last failed save, so it can stay
    // visible for review/troubleshooting instead of only flashing as a
    // toast (see use-loan-request-workflow's lastErrors).
    saveError?: { fieldErrors: Record<string, string> } | null;
    onDismissSaveError?: () => void;
    // Lets the document checklist card update live as the Employer
    // Classification dropdown changes, ahead of actually saving processing
    // details -- see previewChecklistForCategory below.
    onDocumentChecklistPreview?: (
        updates: LoanRequestChecklistPreviewItem[],
    ) => void;
    // Mirrors the computed preview to the page (read-only) so the
    // recommendation summary can show net proceeds without recomputing.
    onPreviewChange?: (preview: RecommendationPreviewState | null) => void;
    // Bump to open the inline editor from outside (attention card action).
    openEditSignal?: number;
    /**
     * Which half of the processing record this instance shows. The staff
     * workspace renders `terms` and `signatories` as separate sections; both
     * save the full processing payload through the same endpoint.
     */
    view?: 'all' | 'terms' | 'signatories';
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
    saveError = null,
    onDismissSaveError,
    onDocumentChecklistPreview,
    onPreviewChange,
    openEditSignal = 0,
    view = 'all',
}: ProcessingDetailsPanelProps) {
    const buildInitialProcessingForm = useCallback(
        (): InlineProcessingFormState => ({
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
                    Number(loanRequest.recommended_term ?? '') < 2,
                    loanRequest.typecode,
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
            institutional_employer_category:
                applicant?.institutional_employer_category ?? '',
            reason: '',
        }),
        [
            applicant?.birthdate,
            applicant?.institutional_employer_category,
            cycleState,
            dataSections.dependents,
            dataSections.processing,
            loanManagers,
            loanRequest.assigned_processor,
            loanRequest.recommended_amount,
            loanRequest.recommended_interest_rate,
            loanRequest.recommended_payment_frequency,
            loanRequest.recommended_term,
            loanRequest.typecode,
        ],
    );
    const [processingForm, setProcessingForm] =
        useState<InlineProcessingFormState>(buildInitialProcessingForm());
    // Processing details start read-only (summary card), even for viewers who
    // are allowed to edit -- they must explicitly click "Edit" to reveal the
    // inline form, so the recommendation/charges aren't accidentally changed
    // while just reviewing a request.
    const [isEditing, setIsEditing] = useState(false);
    const [recommendationPreview, setRecommendationPreview] =
        useState<RecommendationPreviewState | null>(null);
    // Preview at the saved values: the "vs saved" reference and what the
    // page mirrors into the header and summary.
    const [baselinePreview, setBaselinePreview] =
        useState<RecommendationPreviewState | null>(null);
    const [savedNotice, setSavedNotice] = useState<string | null>(null);
    const [seenEditSignal, setSeenEditSignal] = useState(openEditSignal);

    if (openEditSignal !== seenEditSignal) {
        setSeenEditSignal(openEditSignal);
        setIsEditing(canUpdateProcessing);
        setSavedNotice(null);
    }

    useEffect(() => {
        onPreviewChange?.(baselinePreview);
    }, [onPreviewChange, baselinePreview]);
    const [reasonError, setReasonError] = useState<string | null>(null);
    const isFirstProcessingSave = loanRequest.is_first_processing_save;
    const [isRecommendationPreviewLoading, setIsRecommendationPreviewLoading] =
        useState(false);
    const [recommendationPreviewError, setRecommendationPreviewError] =
        useState<string | null>(null);
    // Derived from the live, locally-edited category (not the server's
    // last-saved authority_to_deduct_guidance) so the UI reacts immediately
    // when staff change the Employer Classification Select, before any save
    // round-trip.
    const fixedOfficerTitles =
        AUTHORITY_TO_DEDUCT_OFFICER_TITLES[
            processingForm.institutional_employer_category
        ] ?? [];
    const [showSecondOfficer, setShowSecondOfficer] = useState(
        fixedOfficerTitles.length > 0
            ? fixedOfficerTitles.length > 1
            : loanRequest.authority_to_deduct_guidance?.recommended_officers !==
                  1 || hasSecondOfficerValue(dataSections.processing),
    );
    const resolvedInstitutionalEmployerCategory =
        resolveInstitutionalEmployerCategory(
            applicant?.employer_business_name,
            applicant?.employment_type,
            applicant?.nature_of_business,
        );
    const institutionalEmployerCategoryHint =
        resolvedInstitutionalEmployerCategory
            ? INSTITUTIONAL_EMPLOYER_CATEGORY_LABELS[
                  resolvedInstitutionalEmployerCategory
              ]
            : null;
    const officersUnknown =
        processingForm.processing.authority_to_deduct_officers_unknown === true;
    // Mirrors ApprovedLoanDocumentDataBuilder::$isDueDateNoInsurance: only a
    // term under two months carries no insurance premium, regardless of
    // payment frequency or kind_of_loan, regardless of what staff enter here.
    const isInsuranceSkipped = Number(processingForm.recommended_term) < 2;

    // Keeps insurance_rate/insurance_term locked at 0 as staff live-edit the
    // payment frequency Select and/or the Recommended term input -- these
    // are two independently-editable fields, so neither one's onChange
    // handler alone can recompute the combined condition.
    useEffect(() => {
        const ageBandedRate = resolveAgeBandedInsuranceRate(
            applicant?.birthdate ?? null,
        );
        const nextInsuranceRate = isInsuranceSkipped ? 0 : (ageBandedRate ?? 1);

        setProcessingForm((current) => {
            const currentRate = current.processing.insurance_rate;
            const currentTerm = current.processing.insurance_term;
            const nextTerm = isInsuranceSkipped ? 0 : currentTerm;

            // Bail out with the same reference when nothing changed -- this
            // effect both reads and writes these two fields, so an
            // unguarded write would re-fire itself every render.
            if (currentRate === nextInsuranceRate && currentTerm === nextTerm) {
                return current;
            }

            return {
                ...current,
                processing: {
                    ...current.processing,
                    insurance_rate: nextInsuranceRate,
                    insurance_term: nextTerm,
                },
            };
        });
    }, [isInsuranceSkipped, applicant?.birthdate]);

    useEffect(() => {
        setProcessingForm(buildInitialProcessingForm());
        const initialFixedTitles =
            AUTHORITY_TO_DEDUCT_OFFICER_TITLES[
                applicant?.institutional_employer_category ?? ''
            ] ?? [];
        setShowSecondOfficer(
            initialFixedTitles.length > 0
                ? initialFixedTitles.length > 1
                : loanRequest.authority_to_deduct_guidance
                      ?.recommended_officers !== 1 ||
                      hasSecondOfficerValue(dataSections.processing),
        );
    }, [
        applicant?.institutional_employer_category,
        buildInitialProcessingForm,
        dataSections.processing,
        loanRequest.authority_to_deduct_guidance,
        loanRequest.kind_of_loan,
    ]);

    // Once a category maps to fixed officer titles, keep the submitted
    // title(s) canonical -- staff only type the officer's name, the title
    // field is rendered read-only below. Also keeps the officer-2 slot's
    // visibility in sync when staff switch categories live (e.g. BLGU's 2
    // officers vs. LGU/MRDINC/LDH's 1), without waiting for a save.
    useEffect(() => {
        if (fixedOfficerTitles.length === 0) {
            return;
        }

        setShowSecondOfficer(fixedOfficerTitles.length > 1);

        setProcessingForm((current) => {
            const nextTitle1 = fixedOfficerTitles[0] ?? null;
            const nextTitle2 = fixedOfficerTitles[1] ?? null;

            if (
                current.processing.authority_to_deduct_officer_1_title ===
                    nextTitle1 &&
                current.processing.authority_to_deduct_officer_2_title ===
                    nextTitle2
            ) {
                return current;
            }

            return {
                ...current,
                processing: {
                    ...current.processing,
                    authority_to_deduct_officer_1_title: nextTitle1,
                    authority_to_deduct_officer_2_title: nextTitle2,
                },
            };
        });
    }, [fixedOfficerTitles]);

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

    const updateInstitutionalEmployerCategory = (value: string) => {
        setProcessingForm((current) => ({
            ...current,
            institutional_employer_category: value,
        }));

        void previewChecklistForCategory(value);
    };

    // A single Select choice, unlike free-text fields, needs no debounce --
    // fires straight after the dropdown change so the checklist card
    // (rendered by the parent page) reflects the new category immediately,
    // ahead of actually saving processing details. Guarded the same way as
    // recalculateGnthp: the preview endpoint authorizes against the same
    // policy as saving, so calling it before review has started would 403.
    const previewChecklistForCategory = async (value: string) => {
        if (!canUpdateProcessing || !onDocumentChecklistPreview) {
            return;
        }

        try {
            const updates = await adminApi.previewLoanRequestDocumentChecklist(
                loanRequest.id,
                { institutional_employer_category: value || null },
            );

            onDocumentChecklistPreview(updates);
        } catch (error) {
            // Best-effort live preview -- the saved checklist (recomputed on
            // the next successful save) remains the source of truth, so a
            // failed preview call doesn't surface as an error toast. Still
            // logged so a broken preview is diagnosable instead of silently
            // looking like a no-op to whoever's watching the dropdown.
            console.error('Failed to preview document checklist', error);
        }
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
    // applies the server's suggestion once it resolves. Skipped entirely when
    // the viewer can't update processing details (e.g. review not started
    // yet) -- the preview endpoint authorizes against the same policy as
    // saving, so calling it here would 403 and surface a spurious toast.
    // `asBaseline` marks the result as the saved-values computation (the
    // "vs saved" reference and what the page mirrors); edit-mode runs only
    // update the live computation.
    const recalculateGnthp = async (asBaseline = !isEditing) => {
        if (!canUpdateProcessing || view === 'signatories') {
            return;
        }

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

            if (asBaseline) {
                setBaselinePreview(result);
            }

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

    // Keep the latest preview function available without making it a
    // dependency of the effects below (it is recreated each render).
    const recalculateGnthpRef = useRef(recalculateGnthp);

    useEffect(() => {
        recalculateGnthpRef.current = recalculateGnthp;
    });

    // Live computation: every keystroke re-runs the backend preview (the
    // same builder the documents use), debounced so typing sends one call.
    const previewInputsKey = JSON.stringify([
        processingForm.recommended_amount,
        processingForm.recommended_term,
        processingForm.recommended_interest_rate,
        processingForm.recommended_payment_frequency,
        ...PREVIEW_PROCESSING_KEYS.map(
            (key) => processingForm.processing[key] ?? null,
        ),
    ]);

    useEffect(() => {
        if (!isEditing) {
            return;
        }

        const timeout = setTimeout(
            () => void recalculateGnthpRef.current(false),
            250,
        );

        return () => clearTimeout(timeout);
    }, [isEditing, previewInputsKey]);

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
        ALL_CYCLE_SLOT_KEYS.forEach((slotKey) => {
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
            applicant: {
                institutional_employer_category:
                    processingForm.institutional_employer_category || null,
            },
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
            setIsEditing(false);
            setSavedNotice(
                result.documentChecklist.some(
                    (document) =>
                        document.is_applicable &&
                        document.status === 'generated_stale',
                )
                    ? 'Saved. Changes are recorded in the audit trail. Generated documents are now outdated; regenerate the package.'
                    : 'Saved. Changes are recorded in the audit trail.',
            );
            void recalculateGnthp(true);
        }
    };

    const cancelEditingProcessingDetails = () => {
        setProcessingForm(buildInitialProcessingForm());
        setReasonError(null);
        onDismissSaveError?.();
        setRecommendationPreview(baselinePreview);
        setIsEditing(false);
    };

    // Change tracking for the edit form: compared with the saved values the
    // form was built from.
    const initialForm = buildInitialProcessingForm();
    const formValue = (form: InlineProcessingFormState, key: string) =>
        (RECOMMENDATION_FORM_KEYS as readonly string[]).includes(key)
            ? form[key as (typeof RECOMMENDATION_FORM_KEYS)[number]]
            : form.processing[key];
    const derivedKeys = [
        ...DERIVED_PROCESSING_KEYS,
        // Canonical titles are written from the category, not typed.
        ...(fixedOfficerTitles.length > 0
            ? [
                  'authority_to_deduct_officer_1_title',
                  'authority_to_deduct_officer_2_title',
              ]
            : []),
    ];
    const changedKeys = [
        ...RECOMMENDATION_FORM_KEYS,
        ...Object.keys({
            ...initialForm.processing,
            ...processingForm.processing,
        }).filter((key) => !derivedKeys.includes(key)),
    ].filter(
        (key) =>
            !sameFormValue(
                formValue(processingForm, key),
                formValue(initialForm, key),
            ),
    );
    const isChanged = (key: string) => isEditing && changedKeys.includes(key);
    const formatFieldValue = (
        fieldKey: string,
        value: string | number | boolean | null | undefined,
    ): string => {
        if (typeof value === 'boolean') {
            return value ? 'Yes' : 'No';
        }

        const kind =
            fieldKey === 'recommended_amount'
                ? 'currency'
                : fieldKey === 'recommended_interest_rate'
                  ? 'percent'
                  : PROCESSING_FIELD_KIND[fieldKey];

        return kind === 'percent'
            ? snapshotPercent(value)
            : kind === 'currency'
              ? snapshotCurrency(value)
              : snapshotDisplay(value);
    };
    const changedDot = (key: string) =>
        isChanged(key) ? (
            <span className="ml-auto size-2 shrink-0 rounded-full border border-primary bg-accent">
                <span className="sr-only">(changed)</span>
            </span>
        ) : null;
    const wasHint = (key: string) => {
        if (!isChanged(key)) {
            return null;
        }

        const previous = formatFieldValue(key, formValue(initialForm, key));

        return (
            <p className="text-[11px] font-medium text-primary">
                was {previous === '—' ? 'blank' : previous}
            </p>
        );
    };
    const changedInputClassName = (key: string) =>
        isChanged(key) ? 'border-primary' : undefined;
    const isRemarksRequired = !isFirstProcessingSave;
    const isRemarksMissing =
        isRemarksRequired && processingForm.reason.trim() === '';
    // First save may go through unchanged (it records the defaults); later
    // saves need a change and remarks. The server still validates.
    const canSaveProcessing =
        isFirstProcessingSave || (changedKeys.length > 0 && !isRemarksMissing);

    const recommendedTermLabel =
        loanRequest.recommended_term !== null &&
        `${loanRequest.recommended_term}`.trim() !== ''
            ? `${loanRequest.recommended_term} months`
            : '—';

    const renderSnapshotField = (
        fieldKey: string,
        options?: { locked?: boolean },
    ) => {
        const field = dataSectionDefinitions.processing.fields[fieldKey];

        if (!field) {
            return null;
        }

        const display = formatFieldValue(
            fieldKey,
            processingForm.processing[fieldKey],
        );

        return (
            <SnapshotRow
                key={fieldKey}
                label={field.label}
                value={display}
                locked={options?.locked}
            />
        );
    };

    const renderBankingSnapshotField = (fieldKey: string) => {
        const field = dataSectionDefinitions.banking?.fields[fieldKey];

        if (!field) {
            return null;
        }

        const value = dataSections.banking?.[fieldKey];

        return (
            <SnapshotRow
                key={fieldKey}
                label={field.label}
                value={snapshotDisplay(value as string | number | null)}
            />
        );
    };

    const bankingSection = dataSections.banking as
        | LoanRequestBankingSectionValues
        | undefined;
    const releaseAccountDetail =
        loanRequest.account_snapshot?.release ??
        bankingSection?.release_account_detail ??
        null;
    const paymentAccountDetail =
        loanRequest.account_snapshot?.payment ??
        bankingSection?.payment_account_detail ??
        null;

    const renderAccountDetailRows = (
        detail: SavedPaymentAccountSnapshotDetail | null,
    ) =>
        detail
            ? BANKING_ACCOUNT_DETAIL_LABELS.filter(
                  ({ key }) =>
                      typeof detail[key] === 'string' &&
                      `${detail[key]}`.trim() !== '',
              ).map(({ key, label }) => (
                  <SnapshotRow
                      key={key}
                      label={label}
                      value={`${detail[key]}`}
                  />
              ))
            : null;

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
                    className="col-span-full flex items-start gap-3 rounded-lg border border-border bg-muted/10 p-3 text-sm"
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
                    'grid content-start gap-1',
                    options?.fullWidth && 'col-span-full',
                )}
            >
                <Label
                    htmlFor={`inline_processing_${fieldKey}`}
                    className="flex items-center gap-1.5 text-xs font-semibold"
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
                    {changedDot(fieldKey)}
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
                        className={cn(
                            options?.className,
                            changedInputClassName(fieldKey),
                        )}
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
                        className={cn(
                            options?.className,
                            changedInputClassName(fieldKey),
                        )}
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
                        className={cn(
                            options?.className,
                            changedInputClassName(fieldKey),
                        )}
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
                        className={cn(
                            options?.className,
                            changedInputClassName(fieldKey),
                        )}
                        disabled={options?.disabled}
                        placeholder={options?.placeholder}
                    />
                )}
                {wasHint(fieldKey)}
            </div>
        );
    };

    // Only render a cycle-status row for the applicant (always), the spouse
    // (only when the applicant is married -- mirrors dependent_spouse_*'s
    // visible_when), and dependent slots that actually have a name filled
    // in (an "empty" slot has nothing to verify a cycle for).
    const cycleStateSlots = ALL_CYCLE_SLOT_KEYS.filter((slotKey) => {
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
            const categoryLabel =
                slotKey === 'applicant'
                    ? 'Applicant'
                    : slotKey === 'spouse'
                      ? 'Spouse'
                      : (
                            dataSectionDefinitions.dependents.fields[
                                `dependent_${slotKey}_name`
                            ]?.label ?? slotKey
                        ).replace(/ name$/, '');

            const dependentName =
                slotKey !== 'applicant' && slotKey !== 'spouse'
                    ? dataSections.dependents?.[`dependent_${slotKey}_name`]
                    : undefined;

            const label =
                typeof dependentName === 'string' && dependentName.trim()
                    ? `${categoryLabel} — ${dependentName.trim()}`
                    : categoryLabel;

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
        const locked = slotState?.locked ?? false;
        const [statusKey, numberKey] = cycleSlotFieldKeys(slotKey);
        const statusValue = processingForm.processing[statusKey];
        const numberValue = processingForm.processing[numberKey];
        const statusStringValue =
            typeof statusValue === 'string' ? statusValue : '';

        if (locked) {
            const numberDisplay =
                numberValue !== null && numberValue !== undefined
                    ? `${numberValue}`
                    : '—';

            return (
                <div
                    key={slotKey}
                    className="grid gap-3 rounded-lg border border-border bg-muted/10 p-3 sm:grid-cols-[1fr_auto] sm:items-center"
                >
                    <div className="flex flex-col justify-center">
                        <span className="text-sm font-medium">{label}</span>
                        <span className="text-xs text-muted-foreground">
                            Auto-computed from loan history.
                        </span>
                    </div>
                    <Badge variant="secondary" className="w-fit">
                        {`Suggestion from system: Cycle ${numberDisplay}`}
                        {statusStringValue ? ` (${statusStringValue})` : ''}
                    </Badge>
                </div>
            );
        }

        // Spouse/dependent cycle data is genuinely different per person, so
        // (unlike the applicant) it's manually entered by the processor, not
        // auto-computed. Cycle number is optional metadata regardless of status.
        return (
            <div
                key={slotKey}
                className="grid gap-3 rounded-lg border border-border bg-muted/10 p-3"
            >
                <div className="flex flex-col justify-center">
                    <span className="text-sm font-medium">{label}</span>
                    <span className="text-xs text-muted-foreground">
                        Entered manually by the processor.
                    </span>
                </div>
                <div className="grid gap-3 sm:grid-cols-[auto_10rem] sm:items-start">
                    <div className="grid gap-2">
                        <Label className="text-xs text-muted-foreground">
                            Cycle status
                        </Label>
                        <RadioGroup
                            value={statusStringValue}
                            onValueChange={(nextValue: string) => {
                                updateProcessingSectionField(
                                    statusKey,
                                    nextValue === '' ? null : nextValue,
                                );

                                if (nextValue !== 'Old') {
                                    updateProcessingSectionField(
                                        numberKey,
                                        null,
                                    );
                                }
                            }}
                            disabled={!canUpdateProcessing}
                            aria-label={`${label} cycle status`}
                            className="flex flex-row gap-4"
                        >
                            <div className="flex items-center gap-2">
                                <RadioGroupItem
                                    value="New"
                                    id={`${statusKey}-new`}
                                />
                                <Label
                                    htmlFor={`${statusKey}-new`}
                                    className="text-sm font-normal"
                                >
                                    New
                                </Label>
                            </div>
                            <div className="flex items-center gap-2">
                                <RadioGroupItem
                                    value="Old"
                                    id={`${statusKey}-old`}
                                />
                                <Label
                                    htmlFor={`${statusKey}-old`}
                                    className="text-sm font-normal"
                                >
                                    Old
                                </Label>
                            </div>
                        </RadioGroup>
                    </div>
                    <div className="grid gap-2">
                        <Label
                            className="text-xs text-muted-foreground"
                            htmlFor={numberKey}
                        >
                            Cycle number (optional)
                        </Label>
                        <Input
                            id={numberKey}
                            type="number"
                            min={1}
                            value={
                                numberValue !== null &&
                                numberValue !== undefined
                                    ? `${numberValue}`
                                    : ''
                            }
                            onChange={(event) =>
                                updateProcessingSectionField(
                                    numberKey,
                                    event.target.value,
                                )
                            }
                            disabled={!canUpdateProcessing}
                        />
                    </div>
                </div>
            </div>
        );
    };

    const showTerms = view !== 'signatories';
    const showSignatories = view !== 'terms';
    const isDueDate =
        processingForm.recommended_payment_frequency === 'Due date';
    const applicantAge = calculateAgeFromBirthdate(
        applicant?.birthdate ?? null,
    );
    const insuranceBand =
        applicantAge === null
            ? undefined
            : INSURANCE_RATE_AGE_BANDS.find(
                  ({ minAge, maxAge }) =>
                      applicantAge >= minAge && applicantAge <= maxAge,
              );
    const processingValue = (key: string) => {
        const value = processingForm.processing[key];

        return typeof value === 'boolean' ? null : value;
    };
    const isBlankValue = (key: string) =>
        `${processingValue(key) ?? ''}`.trim() === '';
    const missingSignatories = missingSignatoryFields(
        processingForm.processing,
        {
            authorityToDeductApplicable:
                loanRequest.authority_to_deduct_guidance?.applicable === true,
            witnessOneFallback: null,
        },
    );

    // Read view: charges at the saved values, amounts from the backend preview.
    const chargeRows: {
        label: string;
        basis: string;
        amount: number | null | undefined;
        policy?: boolean;
    }[] = [
        {
            label: 'Service charge',
            basis: `${snapshotPercent(processingValue('service_charge_rate'))} of amount`,
            amount: baselinePreview?.service_charge_amount_raw,
        },
        ...(isDueDate
            ? [
                  {
                      label: 'Advance interest',
                      basis: `${snapshotPercent(loanRequest.recommended_interest_rate)} over ${recommendedTermLabel}, deducted for Due date`,
                      amount: baselinePreview?.interest_not_deducted_raw,
                  },
              ]
            : []),
        {
            label: 'Loan security / savings',
            basis: `${snapshotPercent(processingValue('loan_security_rate'))} of amount`,
            amount: baselinePreview?.loan_security_amount_raw,
        },
        {
            label: 'Insurance premium',
            basis: isInsuranceSkipped
                ? 'Not applied, term under 2 months'
                : `${snapshotDisplay(processingValue('insurance_rate'))} per PHP 1,000 × ${snapshotDisplay(processingValue('insurance_term'))} mo${
                      insuranceBand
                          ? ` · age ${insuranceBand.minAge}–${insuranceBand.maxAge}`
                          : ''
                  }`,
            amount: baselinePreview?.insurance_premium_raw,
            policy: true,
        },
        {
            label: 'Documentary stamp',
            basis: `${snapshotPercent(processingValue('documentary_stamp_rate'))} · PHP 1.50 per PHP 200 or fraction`,
            amount: baselinePreview?.documentary_stamp_amount_raw,
            policy: true,
        },
        {
            label: 'Notarial fee',
            basis: 'Flat fee',
            amount: baselinePreview?.notarial_fee_raw,
        },
        {
            label: 'Other charges',
            basis: isBlankValue('other_charges_description')
                ? 'None'
                : `${processingValue('other_charges_description')}`,
            amount: baselinePreview?.other_charges_amount_raw,
        },
    ];

    // Live computation: the backend preview at the values being typed.
    const live = recommendationPreview;
    const addAmounts = (...amounts: (number | null | undefined)[]) =>
        amounts.every((amount) => amount === null || amount === undefined)
            ? null
            : amounts.reduce<number>((sum, amount) => sum + (amount ?? 0), 0);
    const liveRows: {
        label: string;
        amount: number | null | undefined;
        tone: 'group' | 'item' | 'total';
    }[] = live
        ? [
              {
                  label: 'Loan granted',
                  amount: live.approved_amount_raw,
                  tone: 'group',
              },
              {
                  label: 'Finance charges',
                  amount: live.finance_charge_total_raw,
                  tone: 'group',
              },
              {
                  label: 'Service charge',
                  amount: live.service_charge_amount_raw,
                  tone: 'item',
              },
              ...(isDueDate
                  ? [
                        {
                            label: 'Advance interest',
                            amount: live.interest_not_deducted_raw,
                            tone: 'item' as const,
                        },
                    ]
                  : []),
              {
                  label: 'Non-finance charges',
                  amount: live.non_finance_charge_total_raw,
                  tone: 'group',
              },
              {
                  label: 'Insurance premium',
                  amount: live.insurance_premium_raw,
                  tone: 'item',
              },
              {
                  label: 'Loan security',
                  amount: live.loan_security_amount_raw,
                  tone: 'item',
              },
              {
                  label: 'Documentary stamp',
                  amount: live.documentary_stamp_amount_raw,
                  tone: 'item',
              },
              {
                  label: 'Notarial and other',
                  amount: addAmounts(
                      live.notarial_fee_raw,
                      live.other_charges_amount_raw,
                  ),
                  tone: 'item',
              },
              {
                  label: 'Total deductions',
                  amount: live.deductions_total_raw,
                  tone: 'total',
              },
          ]
        : [];
    const liveNet = live?.net_proceeds_raw ?? null;
    const savedNet = baselinePreview?.net_proceeds_raw ?? null;
    const netDelta =
        liveNet !== null && savedNet !== null
            ? Math.round((liveNet - savedNet) * 100) / 100
            : null;
    const formAmount = Number(processingForm.recommended_amount);
    const requestedAmount = Number(loanRequest.requested_amount ?? 0);
    const liveWarnings = [
        processingForm.recommended_term !== '' && isInsuranceSkipped
            ? 'Term under 2 months: insurance does not apply.'
            : null,
        processingForm.recommended_amount !== '' && formAmount <= 0
            ? 'Recommended amount must be greater than 0.'
            : null,
        requestedAmount > 0 && formAmount > requestedAmount
            ? `Above the requested ${formatCurrency(requestedAmount)}. The manager will see this flagged.`
            : null,
    ].filter((warning): warning is string => warning !== null);
    const changeLabel = `${
        changedKeys.length === 0
            ? 'No changes yet'
            : `${changedKeys.length} field${changedKeys.length === 1 ? '' : 's'} changed`
    }${isRemarksMissing ? ' · remarks required' : ''}`;

    const fieldLabel = (
        htmlFor: string,
        key: string,
        text: string,
        tooltip?: ReactNode,
    ) => (
        <Label
            htmlFor={htmlFor}
            className="flex items-center gap-1.5 text-xs font-semibold"
        >
            {text}
            {tooltip}
            {changedDot(key)}
        </Label>
    );
    const infoTooltip = (content: ReactNode) => (
        <TooltipProvider delayDuration={0}>
            <Tooltip>
                <TooltipTrigger type="button">
                    <Info className="size-3.5 text-muted-foreground" />
                </TooltipTrigger>
                <TooltipContent>{content}</TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
    const fieldGridClassName =
        'grid grid-cols-1 gap-x-3.5 gap-y-3 sm:grid-cols-[repeat(auto-fill,minmax(180px,1fr))]';
    const warningClassName =
        'rounded-lg border border-amber-500/30 bg-amber-500/10 px-2.5 py-2 text-xs font-semibold text-amber-800 dark:text-amber-200';

    const cardTitle =
        view === 'terms'
            ? 'Terms and charges'
            : view === 'signatories'
              ? 'Signatories and insurance'
              : 'Processing details';
    const cardDescription =
        view === 'terms'
            ? 'Your recommendation and the charges used across the document package.'
            : view === 'signatories'
              ? 'Names printed on the document package and insurance cycle.'
              : 'Recommendation and financial terms used across the document package.';

    const termsEditFields = (
        <>
            <div className={fieldGridClassName}>
                <div className="grid content-start gap-1">
                    {fieldLabel(
                        'inline_recommended_amount',
                        'recommended_amount',
                        'Recommended amount',
                    )}
                    <CurrencyInput
                        id="inline_recommended_amount"
                        value={processingForm.recommended_amount}
                        onValueChange={(value) =>
                            setProcessingForm((current) => ({
                                ...current,
                                recommended_amount: value,
                            }))
                        }
                        className={cn(
                            'mt-0',
                            changedInputClassName('recommended_amount'),
                        )}
                    />
                    {wasHint('recommended_amount') ?? (
                        <p className="text-[11px] text-muted-foreground">
                            Requested{' '}
                            {snapshotCurrency(loanRequest.requested_amount)}
                        </p>
                    )}
                </div>
                <div className="grid content-start gap-1">
                    {fieldLabel(
                        'inline_recommended_term',
                        'recommended_term',
                        'Term',
                    )}
                    <MonthsInput
                        id="inline_recommended_term"
                        value={processingForm.recommended_term}
                        onChange={(value) =>
                            setProcessingForm((current) => ({
                                ...current,
                                recommended_term: value,
                            }))
                        }
                        className={changedInputClassName('recommended_term')}
                    />
                    {wasHint('recommended_term') ?? (
                        <p className="text-[11px] text-muted-foreground">
                            Insurance applies from 2 months
                        </p>
                    )}
                </div>
                <div className="grid content-start gap-1">
                    {fieldLabel(
                        'inline_recommended_interest_rate',
                        'recommended_interest_rate',
                        'Interest rate',
                    )}
                    <PercentInput
                        id="inline_recommended_interest_rate"
                        value={processingForm.recommended_interest_rate}
                        onValueChange={(value) =>
                            setProcessingForm((current) => ({
                                ...current,
                                recommended_interest_rate: value,
                            }))
                        }
                        className={cn(
                            'mt-0',
                            changedInputClassName('recommended_interest_rate'),
                        )}
                    />
                    {wasHint('recommended_interest_rate')}
                </div>
                <div className="grid content-start gap-1">
                    {fieldLabel(
                        'inline_recommended_payment_frequency',
                        'recommended_payment_frequency',
                        'Payment frequency',
                        infoTooltip(
                            <>
                                <p>
                                    Member&apos;s payday:{' '}
                                    {applicant?.payday || '—'}
                                </p>
                                <p>
                                    Member requested:{' '}
                                    {loanRequest.requested_payment_frequency ||
                                        '—'}
                                </p>
                            </>,
                        ),
                    )}
                    <Select
                        value={
                            processingForm.recommended_payment_frequency ||
                            undefined
                        }
                        onValueChange={(value) => {
                            setProcessingForm((current) => ({
                                ...current,
                                recommended_payment_frequency: value,
                                // Due date forces loan security/savings to 0%;
                                // switching away restores the standard default
                                // so staff aren't stuck at 0%. The Due date month
                                // count is derived from the term, not entered
                                // separately.
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
                                                  resolveDefaultLoanSecurityRate(
                                                      loanRequest.typecode,
                                                  ),
                                              savings_rate:
                                                  resolveDefaultLoanSecurityRate(
                                                      loanRequest.typecode,
                                                  ),
                                          },
                            }));
                        }}
                    >
                        <SelectTrigger
                            id="inline_recommended_payment_frequency"
                            className={cn(
                                'w-full',
                                changedInputClassName(
                                    'recommended_payment_frequency',
                                ),
                            )}
                        >
                            <SelectValue placeholder="Select payment frequency" />
                        </SelectTrigger>
                        <SelectContent>
                            {PAYMENT_FREQUENCY_OPTIONS.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {wasHint('recommended_payment_frequency') ??
                        (isDueDate ? (
                            <p className="text-[11px] text-muted-foreground">
                                Single payment after{' '}
                                {processingForm.recommended_term || '—'} month
                                {processingForm.recommended_term === '1'
                                    ? ''
                                    : 's'}
                            </p>
                        ) : null)}
                </div>
                <div className="grid content-start gap-1">
                    {fieldLabel(
                        'inline_institutional_employer_category',
                        'institutional_employer_category',
                        'Employer category',
                    )}
                    <Select
                        value={
                            processingForm.institutional_employer_category ||
                            INSTITUTIONAL_EMPLOYER_CATEGORY_UNSET_VALUE
                        }
                        onValueChange={(value) =>
                            updateInstitutionalEmployerCategory(
                                value ===
                                    INSTITUTIONAL_EMPLOYER_CATEGORY_UNSET_VALUE
                                    ? ''
                                    : value,
                            )
                        }
                    >
                        <SelectTrigger
                            id="inline_institutional_employer_category"
                            className={cn(
                                'w-full',
                                changedInputClassName(
                                    'institutional_employer_category',
                                ),
                            )}
                        >
                            <SelectValue placeholder="Not set" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                value={
                                    INSTITUTIONAL_EMPLOYER_CATEGORY_UNSET_VALUE
                                }
                            >
                                Not set
                            </SelectItem>
                            {INSTITUTIONAL_EMPLOYER_CATEGORY_OPTIONS.map(
                                (option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                    {wasHint('institutional_employer_category') ??
                        (institutionalEmployerCategoryHint ? (
                            <p className="text-[11px] text-muted-foreground">
                                Detected from employer info:{' '}
                                {institutionalEmployerCategoryHint}
                            </p>
                        ) : (
                            <p className="text-[11px] text-muted-foreground">
                                Drives deduction documents
                            </p>
                        ))}
                </div>
                {renderProcessingField('service_charge_rate', {})}
                {!isDueDate ? (
                    <div className="grid content-start gap-1">
                        {fieldLabel(
                            'inline_processing_loan_security_rate',
                            'loan_security_rate',
                            'Loan security / savings rate',
                            infoTooltip(
                                <p>
                                    Suggested institutional rate, matching WIBS
                                    desktop&apos;s typecode rule (2% for
                                    &quot;Other Loan&quot;, 5% for every other
                                    loan type). Editable per loan when this
                                    request needs a different rate. Zeroed
                                    automatically for Due date loans.
                                </p>,
                            ),
                        )}
                        <PercentInput
                            id="inline_processing_loan_security_rate"
                            value={
                                processingForm.processing.loan_security_rate !==
                                    null &&
                                processingForm.processing.loan_security_rate !==
                                    undefined
                                    ? `${processingForm.processing.loan_security_rate}`
                                    : ''
                            }
                            onValueChange={updateLoanSecurityRate}
                            className={cn(
                                'mt-0',
                                changedInputClassName('loan_security_rate'),
                            )}
                        />
                        {wasHint('loan_security_rate')}
                    </div>
                ) : null}
                {renderProcessingField('insurance_term', {
                    disabled: isInsuranceSkipped,
                    tooltip: isInsuranceSkipped
                        ? 'No insurance premium applies to this loan (recommended term under 2 months) — locked at 0.'
                        : undefined,
                })}
                {renderProcessingField('notarial_fee', {
                    placeholder: 'Enter notarial fee',
                })}
                {renderProcessingField('other_charges_amount', {})}
                {renderProcessingField('other_charges_description', {})}
            </div>
            <p className="text-xs text-muted-foreground">
                Insurance rate, documentary stamp and penalty rate are set by
                policy; they stay in the charges table and are not edited here.
            </p>

            {loanRequest.waiver_applicability?.deped.applicable && (
                <>
                    {renderProcessingSectionLabel(
                        'Salary Deduction Authorization Waiver (Education Sector)',
                    )}
                    <div className={fieldGridClassName}>
                        {renderProcessingField('deped_school_id_number')}
                        {renderProcessingField('deped_deduction_amount')}
                    </div>
                </>
            )}

            {loanRequest.waiver_applicability?.pension.applicable && (
                <>
                    {renderProcessingSectionLabel('Waiver (Pensioners)')}
                    <div className={fieldGridClassName}>
                        {renderProcessingField('pension_provider')}
                        {renderProcessingField('pension_bank_name')}
                        {renderProcessingField('pension_atm_card_number')}
                        {renderProcessingField('pension_deduction_amount')}
                    </div>
                </>
            )}

            {!PDC_SCHEDULE_TEMPORARILY_DISABLED &&
                dataSections.banking?.payment_option === 'Check' && (
                    <>
                        {renderProcessingSectionLabel(
                            'Post-Dated Checks (PDC)',
                        )}
                        <div className={fieldGridClassName}>
                            {renderProcessingField('pdc_drawee_bank')}
                        </div>
                    </>
                )}
        </>
    );

    const signatoriesEditFields = (
        <>
            {renderProcessingSectionLabel('Signatories', {
                first: view === 'signatories',
            })}
            <div className={fieldGridClassName}>
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
                        className="grid content-start gap-1"
                    >
                        {fieldLabel(
                            'inline_processing_witness_two_name',
                            'witness_two_name',
                            dataSectionDefinitions.processing.fields
                                .witness_two_name?.label ?? 'Witness 2',
                            infoTooltip(
                                <p>
                                    Select the loan manager who will witness
                                    this loan. Their name is recorded
                                    automatically on the documents.
                                </p>,
                            ),
                        )}
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
                                className={cn(
                                    'w-full',
                                    changedInputClassName('witness_two_name'),
                                )}
                            >
                                <SelectValue placeholder="Select loan manager" />
                            </SelectTrigger>
                            <SelectContent>
                                {loanManagers.map((manager) => (
                                    <SelectItem
                                        key={manager.id}
                                        value={String(manager.id)}
                                    >
                                        {manager.name} ({manager.active_loans}{' '}
                                        {manager.active_loans === 1
                                            ? 'loan'
                                            : 'loans'}{' '}
                                        in flight)
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {wasHint('witness_two_name')}
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
                {loanRequest.authority_to_deduct_guidance?.category ===
                    'blgu' && (
                    <>
                        {renderProcessingField('barangay_official_name')}
                        {renderProcessingField('barangay_official_title')}
                        {renderProcessingField('barangay_official_designation')}
                        {renderProcessingField('barangay_agency_name')}
                        {renderProcessingField('barangay_agency_address')}
                    </>
                )}
            </div>

            {loanRequest.authority_to_deduct_guidance?.applicable !== false && (
                <>
                    {renderProcessingSectionLabel(
                        'Authority to Deduct (Salary Deduction)',
                    )}
                    {loanRequest.authority_to_deduct_guidance?.note && (
                        <p className="mb-3 text-sm text-muted-foreground">
                            {loanRequest.authority_to_deduct_guidance.note}
                        </p>
                    )}
                    <div className={fieldGridClassName}>
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
                                    className="col-span-full min-h-11 text-left text-sm text-primary hover:underline lg:min-h-0"
                                    onClick={() => {
                                        const savedContact =
                                            loanRequest
                                                .authority_to_deduct_guidance
                                                ?.saved_contact;

                                        if (!savedContact) {
                                            return;
                                        }

                                        setProcessingForm((current) => ({
                                            ...current,
                                            processing: {
                                                ...current.processing,
                                                authority_to_deduct_officer_1_name:
                                                    savedContact.officer_1_name,
                                                // Title is derived from the employer
                                                // category, not the saved contact --
                                                // only copy it here when no category
                                                // has locked it already.
                                                ...(fixedOfficerTitles.length ===
                                                0
                                                    ? {
                                                          authority_to_deduct_officer_1_title:
                                                              savedContact.officer_1_title,
                                                      }
                                                    : {}),
                                                authority_to_deduct_officer_2_name:
                                                    savedContact.officer_2_name,
                                                ...(fixedOfficerTitles.length <
                                                2
                                                    ? {
                                                          authority_to_deduct_officer_2_title:
                                                              savedContact.officer_2_title,
                                                      }
                                                    : {}),
                                            },
                                        }));

                                        if (
                                            fixedOfficerTitles.length === 0 &&
                                            (savedContact.officer_2_name ||
                                                savedContact.officer_2_title)
                                        ) {
                                            setShowSecondOfficer(true);
                                        }
                                    }}
                                >
                                    Use saved officer(s) for this institution
                                </button>
                            )}
                        <label className="col-span-full flex items-start gap-3 rounded-lg border border-border bg-muted/10 p-3 text-sm">
                            <Checkbox
                                checked={officersUnknown}
                                onCheckedChange={(checked) => {
                                    const isUnknown = checked === true;

                                    setProcessingForm((current) => ({
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
                                    }));
                                }}
                            />
                            <span>
                                I don&apos;t know the officer information yet —
                                leave these fields blank
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
                                disabled:
                                    officersUnknown ||
                                    fixedOfficerTitles.length > 0,
                                className:
                                    officersUnknown ||
                                    fixedOfficerTitles.length > 0
                                        ? readOnlyProcessingFieldClassName
                                        : undefined,
                            },
                        )}
                        {fixedOfficerTitles.length > 0 && (
                            <p className="col-span-full -mt-1 text-xs text-muted-foreground">
                                Title is fixed for this employer category.
                            </p>
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
                                        disabled:
                                            officersUnknown ||
                                            fixedOfficerTitles.length > 0,
                                        className:
                                            officersUnknown ||
                                            fixedOfficerTitles.length > 0
                                                ? readOnlyProcessingFieldClassName
                                                : undefined,
                                    },
                                )}
                            </>
                        ) : (
                            !officersUnknown &&
                            fixedOfficerTitles.length === 0 && (
                                <button
                                    type="button"
                                    className="col-span-full min-h-11 text-left text-sm text-primary hover:underline lg:min-h-0"
                                    onClick={() => setShowSecondOfficer(true)}
                                >
                                    + Add second officer
                                </button>
                            )
                        )}
                    </div>
                </>
            )}

            {cycleStateSlots.length > 0 && (
                <>
                    {renderProcessingSectionLabel('Group Life Insurance Cycle')}
                    <div className="grid gap-3">
                        {cycleStateSlots.map(({ slotKey, label }) =>
                            renderCycleStateRow(slotKey, label),
                        )}
                    </div>
                </>
            )}
        </>
    );

    const liveComputation = (
        <div
            className="rounded-xl border border-border bg-muted px-4 py-3.5"
            aria-live="polite"
        >
            <div className="flex items-center justify-between gap-2">
                <p className="text-sm font-bold">Live computation</p>
                <span className="rounded-full bg-secondary px-2.5 py-0.5 text-[11px] font-bold text-secondary-foreground">
                    {changedKeys.length} changed
                </span>
            </div>
            <p className="mb-2 text-xs text-muted-foreground">
                {isRecommendationPreviewLoading
                    ? 'Calculating…'
                    : 'Updates as you type'}
            </p>
            {recommendationPreviewError ? (
                <p className="text-sm text-destructive">
                    {recommendationPreviewError}
                </p>
            ) : null}
            {live && liveNet !== null ? (
                <>
                    {liveRows.map((row, index) => (
                        <div
                            key={row.label}
                            className={cn(
                                'flex justify-between gap-2 py-1 text-[13px]',
                                row.tone === 'item' &&
                                    'pl-3 text-muted-foreground',
                                row.tone === 'group' && 'font-semibold',
                                row.tone === 'total' && 'font-bold',
                                row.tone !== 'item' &&
                                    index > 0 &&
                                    'border-t border-border',
                            )}
                        >
                            <span>{row.label}</span>
                            <span className="tabular-nums">
                                {snapshotCurrency(row.amount)}
                            </span>
                        </div>
                    ))}
                    <div className="mt-2 flex items-baseline justify-between gap-2 border-t-2 border-foreground pt-2">
                        <span className="text-[13px] font-bold">
                            Net proceeds
                        </span>
                        <span className="text-xl font-bold tabular-nums">
                            {formatCurrency(liveNet)}
                        </span>
                    </div>
                    <p
                        className={cn(
                            'mt-0.5 text-right text-xs',
                            netDelta === null || netDelta === 0
                                ? 'text-muted-foreground'
                                : netDelta > 0
                                  ? 'text-primary'
                                  : 'text-destructive',
                        )}
                    >
                        {netDelta === null
                            ? 'No saved value to compare'
                            : netDelta === 0
                              ? 'Same as saved'
                              : `${netDelta > 0 ? '+' : '−'}${formatCurrency(Math.abs(netDelta))} vs saved`}
                    </p>
                    <p className="mt-2 text-xs text-muted-foreground">
                        Suggested GNTHP:{' '}
                        {snapshotCurrency(live.suggested_gnthp_raw)}
                        {live.suggested_gnthp_raw === null &&
                        live.failure_information
                            ? ` — ${live.failure_information.message}${
                                  live.failure_information.blockers.length
                                      ? ` ${live.failure_information.blockers.join(' ')}`
                                      : ''
                              }`
                            : null}
                    </p>
                </>
            ) : live ? (
                <p className="text-sm text-muted-foreground">
                    Not enough data to compute.
                </p>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Fill in the terms above to calculate.
                </p>
            )}
            {liveWarnings.map((warning) => (
                <p key={warning} className={cn('mt-2.5', warningClassName)}>
                    {warning}
                </p>
            ))}
        </div>
    );

    const termsReadView = (
        <>
            <div className="grid grid-cols-1 gap-x-[18px] gap-y-3.5 sm:grid-cols-[repeat(auto-fill,minmax(170px,1fr))]">
                <SnapshotRow
                    label="Recommended amount"
                    value={snapshotCurrency(loanRequest.recommended_amount)}
                    strong
                />
                <SnapshotRow label="Term" value={recommendedTermLabel} strong />
                <SnapshotRow
                    label="Interest rate"
                    value={snapshotPercent(
                        loanRequest.recommended_interest_rate,
                    )}
                    strong
                />
                <SnapshotRow
                    label="Payment frequency"
                    value={snapshotDisplay(
                        loanRequest.recommended_payment_frequency,
                    )}
                    strong
                />
                <SnapshotRow
                    label="Employer category"
                    value={snapshotDisplay(
                        INSTITUTIONAL_EMPLOYER_CATEGORY_LABELS[
                            processingForm.institutional_employer_category
                        ] ?? processingForm.institutional_employer_category,
                    )}
                    strong
                />
                {renderSnapshotField('employer_date_employed')}
            </div>

            <div className="overflow-hidden rounded-[10px] border border-border">
                <div className="hidden grid-cols-[minmax(0,1.2fr)_minmax(0,1.6fr)_minmax(90px,0.8fr)] gap-2.5 bg-muted px-3.5 py-2 text-[11px] font-bold tracking-widest text-muted-foreground uppercase sm:grid">
                    <span>Charge</span>
                    <span>Basis</span>
                    <span className="text-right">Amount</span>
                </div>
                {chargeRows.map((row, index) => (
                    <div
                        key={row.label}
                        className={cn(
                            'grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-2.5 gap-y-0.5 px-3.5 py-2.5 text-sm sm:grid-cols-[minmax(0,1.2fr)_minmax(0,1.6fr)_minmax(90px,0.8fr)]',
                            index > 0
                                ? 'border-t border-border'
                                : 'sm:border-t sm:border-border',
                        )}
                    >
                        <span className="flex flex-wrap items-center gap-1.5 font-semibold">
                            {row.label}
                            {row.policy ? <PolicyTag /> : null}
                        </span>
                        <span className="col-span-full row-start-2 text-[13px] text-muted-foreground sm:col-span-1 sm:col-start-2 sm:row-start-1">
                            {row.basis}
                        </span>
                        <span className="col-start-2 row-start-1 text-right font-semibold tabular-nums sm:col-start-3">
                            {snapshotCurrency(row.amount)}
                        </span>
                    </div>
                ))}
                <div className="grid grid-cols-[1fr_auto] gap-2.5 border-t border-input px-3.5 py-2.5 text-sm font-bold">
                    <span>Total deductions</span>
                    <span className="tabular-nums">
                        {snapshotCurrency(
                            baselinePreview?.deductions_total_raw,
                        )}
                    </span>
                </div>
                <div className="grid grid-cols-[1fr_auto] items-baseline gap-2.5 bg-secondary px-3.5 py-3 text-secondary-foreground">
                    <span className="text-sm font-bold">Net proceeds</span>
                    <span className="text-xl font-bold tabular-nums">
                        {snapshotCurrency(baselinePreview?.net_proceeds_raw)}
                    </span>
                </div>
            </div>
            <p className="text-[13px] text-muted-foreground">
                Penalty rate{' '}
                {snapshotPercent(processingValue('penalty_rate_per_month'))} per
                month (policy). Suggested guaranteed net take-home pay:{' '}
                {snapshotCurrency(
                    baselinePreview?.suggested_gnthp_raw ??
                        processingValue('guaranteed_net_take_home_pay'),
                )}
                .
                {baselinePreview === null && !canUpdateProcessing
                    ? ' Charge amounts are computed for staff who can edit these terms.'
                    : ''}
            </p>

            {loanRequest.waiver_applicability?.deped.applicable && (
                <SnapshotGroup title="Salary deduction waiver (education sector)">
                    {SNAPSHOT_DEPED_FIELDS.map((fieldKey) =>
                        renderSnapshotField(fieldKey),
                    )}
                </SnapshotGroup>
            )}

            {loanRequest.waiver_applicability?.pension.applicable && (
                <SnapshotGroup title="Waiver (pensioners)">
                    {SNAPSHOT_PENSION_FIELDS.map((fieldKey) =>
                        renderSnapshotField(fieldKey),
                    )}
                </SnapshotGroup>
            )}

            {!PDC_SCHEDULE_TEMPORARILY_DISABLED &&
                dataSections.banking?.payment_option === 'Check' && (
                    <SnapshotGroup title="Post-dated checks (PDC)">
                        {SNAPSHOT_PDC_FIELDS.map((fieldKey) =>
                            renderSnapshotField(fieldKey),
                        )}
                    </SnapshotGroup>
                )}

            <SnapshotGroup title="Disbursement and repayment">
                {renderBankingSnapshotField('release_method')}
                {renderBankingSnapshotField('payment_option')}
                {(dataSections.banking?.release_method === 'ATM' ||
                    dataSections.banking?.release_method === 'Bank Transfer') &&
                    renderAccountDetailRows(releaseAccountDetail)}
                {(dataSections.banking?.payment_option === 'ATM Deduction' ||
                    dataSections.banking?.payment_option === 'Bank Transfer') &&
                    renderAccountDetailRows(paymentAccountDetail)}
            </SnapshotGroup>
        </>
    );

    const signatoriesReadView = (
        <>
            <SnapshotGroup title="Signatories" first={view === 'signatories'}>
                <SnapshotRow
                    label="Witness 1"
                    value={
                        isBlankValue('witness_one_name')
                            ? 'Not entered'
                            : `${processingValue('witness_one_name')} (auto)`
                    }
                    destructive={missingSignatories.includes(
                        'witness_one_name',
                    )}
                />
                <SnapshotRow
                    label="Witness 2"
                    value={
                        isBlankValue('witness_two_name')
                            ? 'Set at approval'
                            : `${processingValue('witness_two_name')}`
                    }
                />
                {loanRequest.authority_to_deduct_guidance?.category ===
                    'blgu' &&
                    SNAPSHOT_BARANGAY_FIELDS.map((fieldKey) =>
                        renderSnapshotField(fieldKey),
                    )}
            </SnapshotGroup>

            {loanRequest.authority_to_deduct_guidance?.applicable !== false && (
                <SnapshotGroup title="Authority to deduct">
                    {renderSnapshotField(
                        'authority_to_deduct_institution_name',
                    )}
                    {officersUnknown ? (
                        <SnapshotRow label="Officers" value="Not known yet" />
                    ) : (
                        <>
                            <SnapshotRow
                                label="Officer 1 name"
                                value={
                                    isBlankValue(
                                        'authority_to_deduct_officer_1_name',
                                    )
                                        ? 'Not entered'
                                        : `${processingValue('authority_to_deduct_officer_1_name')}`
                                }
                                destructive={missingSignatories.includes(
                                    'authority_to_deduct_officer_1_name',
                                )}
                            />
                            <SnapshotRow
                                label="Officer 1 title"
                                value={snapshotDisplay(
                                    processingValue(
                                        'authority_to_deduct_officer_1_title',
                                    ),
                                )}
                                locked={fixedOfficerTitles.length > 0}
                            />
                            {showSecondOfficer ? (
                                <>
                                    <SnapshotRow
                                        label="Officer 2 name"
                                        value={snapshotDisplay(
                                            processingValue(
                                                'authority_to_deduct_officer_2_name',
                                            ),
                                        )}
                                    />
                                    <SnapshotRow
                                        label="Officer 2 title"
                                        value={snapshotDisplay(
                                            processingValue(
                                                'authority_to_deduct_officer_2_title',
                                            ),
                                        )}
                                        locked={fixedOfficerTitles.length > 1}
                                    />
                                </>
                            ) : null}
                        </>
                    )}
                </SnapshotGroup>
            )}

            {cycleStateSlots.length > 0 && (
                <SnapshotGroup title="Insurance cycle">
                    {cycleStateSlots.map(({ slotKey, label }) => {
                        const [statusKey, numberKey] =
                            cycleSlotFieldKeys(slotKey);
                        const status = processingForm.processing[statusKey];
                        const cycleNumber =
                            processingForm.processing[numberKey];
                        const locked = cycleState[slotKey]?.locked ?? false;

                        return (
                            <SnapshotRow
                                key={slotKey}
                                label={
                                    locked
                                        ? `${label} (system suggestion)`
                                        : label
                                }
                                value={snapshotDisplay(
                                    [
                                        cycleNumber !== null &&
                                        cycleNumber !== undefined &&
                                        `${cycleNumber}` !== ''
                                            ? `Cycle ${cycleNumber}`
                                            : null,
                                        status ? `${status}` : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' · '),
                                )}
                            />
                        );
                    })}
                </SnapshotGroup>
            )}
        </>
    );

    return (
        <LoanRequestSectionCard
            title={cardTitle}
            description={cardDescription}
            icon={view === 'all' ? FileText : undefined}
            workspace={view !== 'all'}
            titleAddon={
                canUpdateProcessing && isEditing ? (
                    <span className="rounded-full bg-secondary px-2.5 py-0.5 text-[11px] font-bold text-secondary-foreground">
                        Editing
                    </span>
                ) : null
            }
            className={
                canUpdateProcessing && view === 'all'
                    ? actionCardClassName
                    : 'border-border bg-card shadow-card'
            }
            contentClassName="space-y-4"
            headerAction={
                canUpdateProcessing && !isEditing ? (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className={cn(
                            sectionEditButtonClassName,
                            'min-h-11 lg:min-h-9',
                        )}
                        onClick={() => {
                            setSavedNotice(null);
                            setIsEditing(true);
                        }}
                    >
                        <Pencil />
                        {view === 'terms' ? 'Edit terms' : 'Edit'}
                    </Button>
                ) : null
            }
        >
            {canUpdateProcessing && isEditing ? (
                <form
                    className="space-y-4 motion-safe:animate-in motion-safe:duration-200 motion-safe:fade-in motion-safe:slide-in-from-top-2"
                    onSubmit={submitProcessingDetails}
                >
                    <FormErrorSummary
                        errors={{
                            inline_processing_reason: reasonError ?? undefined,
                        }}
                    />
                    {showTerms ? termsEditFields : null}
                    {showSignatories ? signatoriesEditFields : null}

                    <div className="grid gap-1">
                        <Label
                            htmlFor="inline_processing_reason"
                            className="text-xs font-semibold"
                        >
                            Remarks{' '}
                            {isFirstProcessingSave && (
                                <span className="font-normal text-muted-foreground">
                                    (optional)
                                </span>
                            )}
                        </Label>
                        <Input
                            id="inline_processing_reason"
                            className="h-10"
                            placeholder={
                                isFirstProcessingSave
                                    ? 'Optional — add context beyond the auto-generated summary.'
                                    : 'Required. Why are you changing these values?'
                            }
                            value={processingForm.reason}
                            aria-invalid={reasonError !== null}
                            onChange={(event) => {
                                setReasonError(null);
                                setProcessingForm((current) => ({
                                    ...current,
                                    reason: event.target.value,
                                }));
                            }}
                        />
                    </div>
                    {saveError && (
                        <div className="space-y-2">
                            <FormErrorSummary
                                errors={saveError.fieldErrors}
                                idResolver={(key) =>
                                    `inline_processing_${key.replace(/^processing\./, '')}`
                                }
                            />
                            {onDismissSaveError && (
                                <button
                                    type="button"
                                    className="text-xs text-muted-foreground hover:underline"
                                    onClick={onDismissSaveError}
                                >
                                    Dismiss
                                </button>
                            )}
                        </div>
                    )}
                    <div className="flex flex-wrap items-center gap-2.5 border-t border-border pt-3">
                        <span className="min-w-40 flex-1 text-[13px] text-muted-foreground">
                            {changeLabel}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11 lg:min-h-9"
                            disabled={isProcessing}
                            onClick={cancelEditingProcessingDetails}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            className="min-h-11 lg:min-h-9"
                            disabled={isProcessing || !canSaveProcessing}
                        >
                            {view === 'terms'
                                ? 'Save terms'
                                : view === 'signatories'
                                  ? 'Save'
                                  : 'Save processing details'}
                        </Button>
                    </div>
                    {showTerms ? liveComputation : null}
                </form>
            ) : (
                <div className="space-y-4 motion-safe:animate-in motion-safe:duration-200 motion-safe:fade-in">
                    {savedNotice ? (
                        <p
                            role="status"
                            className="rounded-[10px] bg-secondary px-3.5 py-2.5 text-[13px] font-semibold text-secondary-foreground"
                        >
                            {savedNotice}
                        </p>
                    ) : null}
                    {showTerms ? termsReadView : null}
                    {showSignatories ? signatoriesReadView : null}
                    {!canUpdateProcessing ? (
                        <p className="text-[13px] text-muted-foreground">
                            Only the assigned loan processor can edit processing
                            terms before approval, or the designated manager
                            afterward.
                        </p>
                    ) : null}
                </div>
            )}
        </LoanRequestSectionCard>
    );
}
