import { Head, router, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Bell,
    CheckCircle2,
    Circle,
    ClipboardCheck,
    Clock,
    FileText,
    HeartPulse,
    MinusCircle,
    MoreHorizontal,
    PlayCircle,
    RefreshCw,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { LoanRequestAuditTrail } from '@/components/loan-request/loan-request-audit-trail';
import {
    LoanRequestApplicantCard,
    LoanRequestCoMakersCard,
    LoanRequestDetailPage,
    LoanRequestSummaryHeader,
    displayCurrency,
    displayText,
    displayValue,
} from '@/components/loan-request/loan-request-detail-page';
import {
    LoanRequestPersonalFields,
    LoanRequestWorkFields,
} from '@/components/loan-request/loan-request-fields';
import { LoanRequestProcessingReviewStrip } from '@/components/loan-request/loan-request-processing-review-strip';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import { LoanRequestSectionNav } from '@/components/loan-request/loan-request-section-nav';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useLoanRequestWorkflow } from '@/hooks/admin/use-loan-request-workflow';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDate, formatDateTime } from '@/lib/formatters';
import { showErrorToast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import {
    approvedDocuments as requestsApprovedDocuments,
    index as requestsIndex,
    pdf as requestsPdf,
    print as requestsPrint,
    show as requestsShow,
} from '@/routes/staff/loan-requests';
import {
    affidavitUndertaking as requestsAffidavitUndertakingDocument,
    applicationForm as requestsApplicationFormDocument,
    authorization as requestsAuthorizationDocument,
    disclosureStatement as requestsDisclosureStatementDocument,
    grepalife as requestsGrepalifeDocument,
    loanInformation as requestsLoanInformationDocument,
    loanSecurityAgreement as requestsLoanSecurityAgreementDocument,
    planOfPayment as requestsPlanOfPaymentDocument,
    promissoryNote as requestsPromissoryNoteDocument,
    undertakingBarangay as requestsUndertakingBarangayDocument,
} from '@/routes/staff/loan-requests/documents';
import {
    confirmRelease as wibsConfirmRelease,
    markForEncoding as wibsMarkForEncoding,
    recordReference as wibsRecordReference,
    scheduleRelease as wibsScheduleRelease,
} from '@/routes/staff/loan-requests/wibs';
import type { BreadcrumbItem } from '@/types';
import type { Auth } from '@/types/auth';
import type {
    LoanRequestAuditEntry,
    LoanRequestAssignmentOfficerOption,
    LoanRequestDataSectionDefinitions,
    LoanRequestDataSections,
    LoanRequestDetail,
    LoanRequestDocumentChecklistItem,
    LoanRequestDocumentKey,
    LoanRequestDocumentReadinessStatus,
    LoanRequestMemberAction,
    LoanRequestNotificationHistoryItem,
    LoanRequestPersonData,
    LoanRequestPersonFormData,
    LoanRequestWorkflowContext,
    LoanRequestWorkflowHealth,
    LoanRequestWorkflowPermission,
} from '@/types/loan-requests';

type Props = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    auditTrail: LoanRequestAuditEntry[];
    eligibleOfficers: LoanRequestAssignmentOfficerOption[];
    dataSections: LoanRequestDataSections;
    dataSectionDefinitions: LoanRequestDataSectionDefinitions;
    documentChecklist: LoanRequestDocumentChecklistItem[];
    memberAction: LoanRequestMemberAction;
    notificationHistory: LoanRequestNotificationHistoryItem[];
    workflowPermissions: LoanRequestWorkflowPermission[];
    workflowContext: LoanRequestWorkflowContext;
    workflowHealth: LoanRequestWorkflowHealth;
};

const textareaClassName =
    'flex min-h-[112px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50';

const actionCardClassName =
    'border-primary/25 bg-card/80 shadow-sm ring-1 ring-primary/10';
const readOnlyCardClassName = 'border-border/20 bg-card/40 shadow-sm';

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
        birthdate: person.birthdate ?? '',
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

const displayChecklistStatusTone = (status: string): string => {
    return (
        {
            generated_current:
                'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-200',
            generated_stale:
                'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-200',
            ready_to_generate:
                'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-200',
            awaiting_member_confirmation:
                'border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-200',
            generation_failed:
                'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-200',
        }[status] ?? 'border-border/60 bg-muted/20 text-muted-foreground'
    );
};

const PROCESSING_AGE_ISSUE_THRESHOLD_DAYS = 3;

const checklistStatusIcon = (status: LoanRequestDocumentReadinessStatus) => {
    switch (status) {
        case 'not_started':
            return { Icon: Circle, className: 'text-muted-foreground' };
        case 'incomplete':
            return { Icon: AlertCircle, className: 'text-amber-600 dark:text-amber-300' };
        case 'awaiting_member_confirmation':
            return { Icon: Clock, className: 'text-violet-600 dark:text-violet-300' };
        case 'ready_to_generate':
            return { Icon: PlayCircle, className: 'text-sky-600 dark:text-sky-300' };
        case 'generated_current':
            return { Icon: CheckCircle2, className: 'text-emerald-600 dark:text-emerald-300' };
        case 'generated_stale':
            return { Icon: RefreshCw, className: 'text-amber-600 dark:text-amber-300' };
        case 'generation_failed':
            return { Icon: XCircle, className: 'text-rose-600 dark:text-rose-300' };
        case 'not_applicable':
            return { Icon: MinusCircle, className: 'text-muted-foreground' };
        case 'legacy_data_incomplete':
            return { Icon: AlertCircle, className: 'text-amber-600 dark:text-amber-300' };
        default:
            return { Icon: Circle, className: 'text-muted-foreground' };
    }
};

const displayNotificationStatusTone = (status: string | null): string => {
    return (
        {
            queued: 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-200',
            sending:
                'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-200',
            sent: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-200',
            failed: 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-200',
            skipped:
                'border-border/60 bg-muted/20 text-muted-foreground dark:text-muted-foreground',
        }[status ?? ''] ?? 'border-border/60 bg-muted/20 text-muted-foreground'
    );
};

const documentResultStatusOrder = [
    'generated_current',
    'generated_stale',
    'ready_to_generate',
    'not_applicable',
    'generation_failed',
    'awaiting_member_confirmation',
    'incomplete',
] as const;

// Category A — financial processing terms edited inline on the page.
export type InlineProcessingFormState = {
    processing: Record<string, string | number | boolean | null>;
    recommended_amount: string;
    recommended_term: string;
    recommended_interest_rate: string;
    recommended_payment_frequency: string;
    recommendation_remarks: string;
    reason: string;
    information_source: string;
};

type ProcessingSectionKey =
    | 'recommendation'
    | 'charges_fees'
    | 'net_take_home_pay'
    | 'document_requirements'
    | 'personnel'
    | 'confirmation';

type ProcessingSectionMeta = {
    key: ProcessingSectionKey;
    label: string;
};

const PROCESSING_SECTIONS: ProcessingSectionMeta[] = [
    { key: 'recommendation', label: 'Recommendation' },
    { key: 'charges_fees', label: 'Charges & fees' },
    { key: 'net_take_home_pay', label: 'Net take-home pay' },
    { key: 'document_requirements', label: 'Document requirements' },
    { key: 'personnel', label: 'Personnel' },
    { key: 'confirmation', label: 'Confirmation' },
];

// Category B — application-data corrections edited in the modal.
type CorrectionFormState = {
    loan_request: {
        requested_amount: string;
        requested_term: string;
        loan_purpose: string;
        availment_status: string;
    };
    applicant: LoanRequestPersonFormData;
    co_maker_1: LoanRequestPersonFormData;
    co_maker_2: LoanRequestPersonFormData;
    reason: string;
    information_source: string;
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

export default function StaffLoanRequestShow({
    loanRequest,
    applicant,
    coMakerOne,
    coMakerTwo,
    auditTrail,
    eligibleOfficers,
    dataSections,
    dataSectionDefinitions,
    documentChecklist,
    notificationHistory,
    workflowPermissions,
    workflowContext,
    workflowHealth,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [currentRequest, setCurrentRequest] =
        useState<LoanRequestDetail>(loanRequest);
    const [currentApplicant, setCurrentApplicant] =
        useState<LoanRequestPersonData | null>(applicant);
    const [currentCoMakerOne, setCurrentCoMakerOne] =
        useState<LoanRequestPersonData | null>(coMakerOne);
    const [currentCoMakerTwo, setCurrentCoMakerTwo] =
        useState<LoanRequestPersonData | null>(coMakerTwo);
    const [currentAuditTrail, setCurrentAuditTrail] =
        useState<LoanRequestAuditEntry[]>(auditTrail);
    const [currentEligibleOfficers, setCurrentEligibleOfficers] =
        useState<LoanRequestAssignmentOfficerOption[]>(eligibleOfficers);
    const [currentDataSections, setCurrentDataSections] =
        useState<LoanRequestDataSections>(dataSections);
    const [currentDocumentChecklist, setCurrentDocumentChecklist] =
        useState<LoanRequestDocumentChecklistItem[]>(documentChecklist);
    const [currentNotificationHistory, setCurrentNotificationHistory] =
        useState<LoanRequestNotificationHistoryItem[]>(notificationHistory);
    const [currentWorkflowHealth, setCurrentWorkflowHealth] =
        useState<LoanRequestWorkflowHealth>(workflowHealth);
    const [lastDocumentResults, setLastDocumentResults] = useState<
        LoanRequestDocumentChecklistItem[] | null
    >(null);
    const [isCorrectionDialogOpen, setIsCorrectionDialogOpen] = useState(false);
    const [isMemberActionDialogOpen, setIsMemberActionDialogOpen] =
        useState(false);
    const [wibsReference, setWibsReference] = useState('');
    const [wibsReleaseDate, setWibsReleaseDate] = useState('');
    const [isWibsSubmitting, setIsWibsSubmitting] = useState(false);
    const [memberActionType, setMemberActionType] = useState<
        'needs_revision' | 'awaiting_member_information'
    >('awaiting_member_information');
    const [memberActionMessage, setMemberActionMessage] = useState('');
    const [memberActionReason, setMemberActionReason] = useState('');
    const [selectedMemberFields, setSelectedMemberFields] = useState<string[]>(
        [],
    );
    const [processingForm, setProcessingForm] =
        useState<InlineProcessingFormState>({
            processing: { ...dataSections.processing },
            recommended_amount: toStringValue(loanRequest.recommended_amount),
            recommended_term: toStringValue(loanRequest.recommended_term),
            recommended_interest_rate: toStringValue(
                loanRequest.recommended_interest_rate,
            ),
            recommended_payment_frequency:
                loanRequest.recommended_payment_frequency ?? '',
            recommendation_remarks: loanRequest.recommendation_remarks ?? '',
            reason: '',
            information_source: '',
        });
    const [activeProcessingSection, setActiveProcessingSection] =
        useState<ProcessingSectionKey>('recommendation');
    const [lastProcessingSavedAt, setLastProcessingSavedAt] =
        useState<Date | null>(null);
    const [correctionForm, setCorrectionForm] = useState<CorrectionFormState>({
        loan_request: {
            requested_amount: toStringValue(loanRequest.requested_amount),
            requested_term: toStringValue(loanRequest.requested_term),
            loan_purpose: loanRequest.loan_purpose ?? '',
            availment_status: loanRequest.availment_status ?? '',
        },
        applicant: toPersonForm(applicant),
        co_maker_1: toPersonForm(coMakerOne),
        co_maker_2: toPersonForm(coMakerTwo),
        reason: '',
        information_source: '',
    });
    const {
        claimLoanRequest,
        assignLoanRequest,
        reassignLoanRequest,
        returnLoanRequestToQueue,
        startReview,
        requestRevision,
        rejectLoanRequest,
        updateProcessingDetails,
        requestMemberAction,
        rejectLoanRequestDuringProcessing,
        generateDocuments,
        recommendApproval,
        approveLoanRequest,
        declineLoanRequest,
        returnForProcessing,
        reopenLoanRequest,
        upgradeWorkflow,
        processingIds: workflowProcessingIds,
    } = useLoanRequestWorkflow({
        onUpdated: (result) => {
            setCurrentRequest(result.loanRequest);
            setCurrentApplicant(result.applicant);
            setCurrentCoMakerOne(result.coMakerOne);
            setCurrentCoMakerTwo(result.coMakerTwo);
            setCurrentAuditTrail(result.auditTrail);
            setCurrentEligibleOfficers(result.eligibleOfficers);
            setCurrentDataSections(result.dataSections);
            setCurrentDocumentChecklist(result.documentChecklist);
            setCurrentNotificationHistory(result.notificationHistory);
            setCurrentWorkflowHealth(result.workflowHealth);
            setLastDocumentResults(
                result.documentResults
                    ? result.documentChecklist.filter((document) =>
                          result.documentResults?.some(
                              (item) => item.key === document.key,
                          ),
                      )
                    : null,
            );
        },
    });
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Loan Workflow',
            href: requestsIndex().url,
        },
        {
            title: 'Loan request',
            href: requestsShow(currentRequest.id).url,
        },
    ];
    const pdfHref = requestsPdf(currentRequest.id, {
        query: { download: 1 },
    }).url;
    const printHref = requestsPrint(currentRequest.id).url;
    const approvedDocumentHrefs =
        currentRequest.status === 'approved' ||
        currentRequest.status === 'converted_to_loan'
            ? {
                  applicationForm: requestsApplicationFormDocument(
                      currentRequest.id,
                  ).url,
                  grepalife: requestsGrepalifeDocument(currentRequest.id).url,
                  affidavitUndertaking: requestsAffidavitUndertakingDocument(
                      currentRequest.id,
                  ).url,
                  authorization: requestsAuthorizationDocument(
                      currentRequest.id,
                  ).url,
                  loanInformation: requestsLoanInformationDocument(
                      currentRequest.id,
                  ).url,
                  planOfPayment: requestsPlanOfPaymentDocument(
                      currentRequest.id,
                  ).url,
                  disclosureStatement: requestsDisclosureStatementDocument(
                      currentRequest.id,
                  ).url,
                  promissoryNote: requestsPromissoryNoteDocument(
                      currentRequest.id,
                  ).url,
                  undertakingBarangay: requestsUndertakingBarangayDocument(
                      currentRequest.id,
                  ).url,
                  loanSecurityAgreement: requestsLoanSecurityAgreementDocument(
                      currentRequest.id,
                  ).url,
                  packageZip: requestsApprovedDocuments(currentRequest.id).url,
              }
            : null;

    useEffect(() => {
        setProcessingForm({
            processing: { ...currentDataSections.processing },
            recommended_amount: toStringValue(
                currentRequest.recommended_amount,
            ),
            recommended_term: toStringValue(currentRequest.recommended_term),
            recommended_interest_rate: toStringValue(
                currentRequest.recommended_interest_rate,
            ),
            recommended_payment_frequency:
                currentRequest.recommended_payment_frequency ?? '',
            recommendation_remarks: currentRequest.recommendation_remarks ?? '',
            reason: '',
            information_source: '',
        });
    }, [
        currentDataSections.processing,
        currentRequest.recommendation_remarks,
        currentRequest.recommended_amount,
        currentRequest.recommended_interest_rate,
        currentRequest.recommended_payment_frequency,
        currentRequest.recommended_term,
    ]);

    useEffect(() => {
        setCorrectionForm({
            loan_request: {
                requested_amount: toStringValue(
                    currentRequest.requested_amount,
                ),
                requested_term: toStringValue(currentRequest.requested_term),
                loan_purpose: currentRequest.loan_purpose ?? '',
                availment_status: currentRequest.availment_status ?? '',
            },
            applicant: toPersonForm(currentApplicant),
            co_maker_1: toPersonForm(currentCoMakerOne),
            co_maker_2: toPersonForm(currentCoMakerTwo),
            reason: '',
            information_source: '',
        });
    }, [
        currentApplicant,
        currentCoMakerOne,
        currentCoMakerTwo,
        currentRequest.availment_status,
        currentRequest.loan_purpose,
        currentRequest.requested_amount,
        currentRequest.requested_term,
    ]);

    const hasWorkflowPermission = (
        permission: LoanRequestWorkflowPermission,
    ): boolean => workflowPermissions.includes(permission);
    const isOwnRequest = workflowContext.isOwnRequest;
    const actorUserId = auth.user.id;
    const assignedProcessorId =
        currentRequest.assigned_processor_id ??
        currentRequest.assigned_officer_id;
    const normalizeId = (
        value: number | string | null | undefined,
    ): number | null => {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        const numeric = Number(value);

        return Number.isNaN(numeric) ? null : numeric;
    };
    const normalizedAssignedProcessorId = normalizeId(assignedProcessorId);
    const normalizedActorUserId = normalizeId(actorUserId);
    const documentResultSummary = useMemo(() => {
        if (lastDocumentResults === null) {
            return [];
        }

        return documentResultStatusOrder
            .map((status) => {
                const matches = lastDocumentResults.filter(
                    (document) => document.status === status,
                );

                if (matches.length === 0) {
                    return null;
                }

                return {
                    status,
                    count: matches.length,
                    label: matches[0]?.status_label ?? status,
                };
            })
            .filter(
                (
                    item,
                ): item is {
                    status: (typeof documentResultStatusOrder)[number];
                    count: number;
                    label: string;
                } => item !== null,
            );
    }, [lastDocumentResults]);
    const workflowHealthIssues = useMemo(
        () => ({
            processingAge:
                currentWorkflowHealth.processing_age_days !== null &&
                currentWorkflowHealth.processing_age_days >=
                    PROCESSING_AGE_ISSUE_THRESHOLD_DAYS,
            pendingMemberAction: currentWorkflowHealth.pending_member_action,
            staleDocuments: currentWorkflowHealth.stale_document_count > 0,
            failedDocuments: currentWorkflowHealth.failed_document_count > 0,
            legacyBlockers: currentWorkflowHealth.legacy_blocker_count > 0,
            notificationFailures:
                currentWorkflowHealth.notification_failure_count > 0,
            workflowFailedJobs:
                currentWorkflowHealth.workflow_failed_job_count > 0,
        }),
        [currentWorkflowHealth],
    );
    const workflowHealthIssueCount =
        Object.values(workflowHealthIssues).filter(Boolean).length;
    const isV2Workflow =
        currentRequest.workflow_version === 'document_workflow_v2';
    const canClaim = currentRequest.can_claim;
    const canStartReview =
        !isOwnRequest &&
        currentRequest.status === 'pending_review' &&
        hasWorkflowPermission('loan.review') &&
        (normalizedAssignedProcessorId === null ||
            normalizedAssignedProcessorId === normalizedActorUserId);
    const canRequestRevision =
        !isV2Workflow &&
        !isOwnRequest &&
        (currentRequest.status === 'pending_review' ||
            currentRequest.status === 'under_review') &&
        hasWorkflowPermission('loan.request_revision') &&
        normalizedAssignedProcessorId === normalizedActorUserId;
    const canReject =
        !isV2Workflow &&
        !isOwnRequest &&
        (currentRequest.status === 'pending_review' ||
            currentRequest.status === 'under_review') &&
        hasWorkflowPermission('loan.reject') &&
        normalizedAssignedProcessorId === normalizedActorUserId;
    const canUpdateProcessing =
        !isOwnRequest &&
        hasWorkflowPermission('loan.review') &&
        normalizedAssignedProcessorId === normalizedActorUserId &&
        [
            'pending_review',
            'under_review',
            'needs_revision',
            'awaiting_member_information',
        ].includes(currentRequest.status ?? '');
    const canRequestMemberAction = canUpdateProcessing;
    const isProcessingStage = [
        'pending_review',
        'under_review',
        'needs_revision',
        'awaiting_member_information',
    ].includes(currentRequest.status ?? '');
    const showWibsTrackingSection =
        hasWorkflowPermission('loan.wibs_encode') &&
        [
            'converted_to_loan',
            'for_wibs_encoding',
            'wibs_loan_created',
            'release_scheduled',
            'released',
        ].includes(currentRequest.status ?? '');
    const canRejectDuringProcessing =
        isV2Workflow &&
        !isOwnRequest &&
        hasWorkflowPermission('loan.reject') &&
        normalizedAssignedProcessorId === normalizedActorUserId &&
        [
            'pending_review',
            'under_review',
            'needs_revision',
            'awaiting_member_information',
        ].includes(currentRequest.status ?? '');
    const canGenerateDocuments = canUpdateProcessing;
    const canRecommendApproval =
        !isOwnRequest &&
        currentRequest.status === 'under_review' &&
        hasWorkflowPermission('loan.recommend_approval') &&
        normalizedAssignedProcessorId === normalizedActorUserId;
    const canWorkflowApprove =
        !isOwnRequest &&
        currentRequest.status === 'recommended_for_approval' &&
        hasWorkflowPermission('loan.approve');
    const canWorkflowDecline =
        !isOwnRequest &&
        currentRequest.status === 'recommended_for_approval' &&
        hasWorkflowPermission('loan.decline');
    const canAssign =
        currentRequest.can_assign && currentEligibleOfficers.length > 0;
    const canReassign =
        currentRequest.can_reassign &&
        currentEligibleOfficers.some(
            (officer) =>
                normalizeId(officer.user_id) !== normalizedAssignedProcessorId,
        );
    const canReturnToQueue = currentRequest.can_return_to_queue;
    const canReturnForProcessing =
        !isOwnRequest &&
        ['recommended_for_approval', 'awaiting_member_acceptance'].includes(
            currentRequest.status ?? '',
        ) &&
        (hasWorkflowPermission('loan.manage_assignment') ||
            hasWorkflowPermission('loan.approve') ||
            hasWorkflowPermission('loan.decline'));
    const canReopenRejectedRequest =
        !isOwnRequest &&
        currentRequest.status === 'rejected' &&
        hasWorkflowPermission('loan.manage_assignment');
    const canUpgradeWorkflow =
        !isOwnRequest &&
        currentRequest.workflow_version === 'legacy_v1' &&
        hasWorkflowPermission('loan.manage_assignment') &&
        ![
            'approved',
            'declined',
            'rejected',
            'cancelled',
            'converted_to_loan',
        ].includes(currentRequest.status ?? '');
    const isWorkflowProcessing =
        workflowProcessingIds[currentRequest.id] ?? false;
    const memberFieldDefinitions = useMemo(
        () =>
            Object.entries(dataSectionDefinitions).flatMap(
                ([sectionKey, section]) =>
                    Object.entries(section.fields)
                        .filter(([, field]) => field.owner === 'member')
                        .map(([fieldKey, field]) => ({
                            fieldKey,
                            sectionKey,
                            field,
                        })),
            ),
        [dataSectionDefinitions],
    );
    const memberFieldGroups = useMemo(() => {
        const grouped = new Map<string, typeof memberFieldDefinitions>();

        memberFieldDefinitions.forEach((item) => {
            const existing = grouped.get(item.sectionKey) ?? [];
            existing.push(item);
            grouped.set(item.sectionKey, existing);
        });

        const priorityOrder = ['insurance', 'health', 'banking', 'barangay'];
        const priorityKeys = priorityOrder.filter((key) => grouped.has(key));
        const remainingKeys = Array.from(grouped.keys())
            .filter((key) => !priorityOrder.includes(key))
            .sort((a, b) =>
                (dataSectionDefinitions[a]?.label ?? a).localeCompare(
                    dataSectionDefinitions[b]?.label ?? b,
                ),
            );

        return [...priorityKeys, ...remainingKeys].map((sectionKey) => ({
            sectionKey,
            label: dataSectionDefinitions[sectionKey]?.label ?? sectionKey,
            items: grouped.get(sectionKey) ?? [],
        }));
    }, [memberFieldDefinitions, dataSectionDefinitions]);

    const updateCorrectionPersonField =
        (personKey: 'applicant' | 'co_maker_1' | 'co_maker_2') =>
        (field: keyof LoanRequestPersonFormData, value: string) => {
            setCorrectionForm((current) => ({
                ...current,
                [personKey]: {
                    ...current[personKey],
                    [field]: value,
                },
            }));
        };

    const updateCorrectionDetailField = (
        field: keyof CorrectionFormState['loan_request'],
        value: string,
    ) => {
        setCorrectionForm((current) => ({
            ...current,
            loan_request: {
                ...current.loan_request,
                [field]: value,
            },
        }));
    };

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
            currentRequest.requested_amount !== null &&
            `${currentRequest.requested_amount}`.trim() !== ''
        ) {
            passthrough.requested_amount = currentRequest.requested_amount;
        }

        if (
            currentRequest.requested_term !== null &&
            `${currentRequest.requested_term}`.trim() !== ''
        ) {
            passthrough.requested_term = currentRequest.requested_term;
        }

        if ((currentRequest.loan_purpose ?? '').trim() !== '') {
            passthrough.loan_purpose = currentRequest.loan_purpose as string;
        }

        if ((currentRequest.availment_status ?? '').trim() !== '') {
            passthrough.availment_status =
                currentRequest.availment_status as string;
        }

        return passthrough;
    };

    const submitProcessingDetails = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        if (
            processingForm.reason.trim() === '' ||
            processingForm.information_source.trim() === ''
        ) {
            setActiveProcessingSection('confirmation');
            showErrorToast(
                null,
                'Reason and information source are required before saving processing details.',
            );
            return;
        }

        const result = await updateProcessingDetails(currentRequest.id, {
            reason: processingForm.reason,
            information_source: processingForm.information_source,
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
                information_source: '',
            }));
            setLastProcessingSavedAt(new Date());
        }
    };

    const submitCorrectionData = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const result = await updateProcessingDetails(currentRequest.id, {
            reason: correctionForm.reason,
            information_source: correctionForm.information_source,
            loan_request: correctionForm.loan_request,
            applicant: correctionForm.applicant,
            co_maker_1: correctionForm.co_maker_1,
            co_maker_2: correctionForm.co_maker_2,
        });

        if (result) {
            setIsCorrectionDialogOpen(false);
        }
    };

    const submitMemberAction = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const result = await requestMemberAction(currentRequest.id, {
            action_type: memberActionType,
            message: memberActionMessage,
            reason: memberActionReason,
            field_keys: selectedMemberFields,
        });

        if (result) {
            setIsMemberActionDialogOpen(false);
            setMemberActionMessage('');
            setMemberActionReason('');
            setSelectedMemberFields([]);
        }
    };

    const submitGenerateDocuments = async (
        documentKey?: LoanRequestDocumentKey,
    ) => {
        await generateDocuments(currentRequest.id, {
            document_key: documentKey ?? null,
        });
    };

    const recommendedTermLabel =
        currentRequest.recommended_term !== null &&
        `${currentRequest.recommended_term}`.trim() !== ''
            ? `${currentRequest.recommended_term} months`
            : '—';

    const processingSecurityRequired =
        processingForm.processing['security_required'] === true;

    const renderProcessingSectionLabel = (
        title: string,
        options?: { first?: boolean },
    ) => (
        <div className={options?.first ? undefined : 'mt-6'}>
            <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {title}
            </p>
            <Separator className="mb-4 bg-border/40" />
        </div>
    );

    const renderProcessingField = (
        fieldKey: string,
        options?: { fullWidth?: boolean },
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
                        checked={
                            processingForm.processing[fieldKey] === true
                        }
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

        return (
            <div
                key={fieldKey}
                className={cn(
                    'grid gap-2',
                    options?.fullWidth && 'sm:col-span-2',
                )}
            >
                <Label htmlFor={`inline_processing_${fieldKey}`}>
                    {field.label}
                </Label>
                <Input
                    id={`inline_processing_${fieldKey}`}
                    type={
                        field.type === 'number' || field.type === 'integer'
                            ? 'number'
                            : 'text'
                    }
                    step={field.type === 'number' ? '0.01' : undefined}
                    value={
                        processingForm.processing[fieldKey] !== null &&
                        processingForm.processing[fieldKey] !== undefined
                            ? `${processingForm.processing[fieldKey]}`
                            : ''
                    }
                    onChange={(event) =>
                        updateProcessingSectionField(
                            fieldKey,
                            event.target.value,
                        )
                    }
                />
            </div>
        );
    };

    const inlineProcessingPanel = (
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
                        <LoanRequestProcessingReviewStrip
                            processingForm={processingForm}
                            processingFieldDefinitions={
                                dataSectionDefinitions.processing.fields
                            }
                            lastProcessingSavedAt={lastProcessingSavedAt}
                        />
                        <div className="grid gap-6 lg:grid-cols-[180px_minmax(0,1fr)]">
                        <div className="lg:sticky lg:top-24">
                            <LoanRequestSectionNav
                                sections={PROCESSING_SECTIONS}
                                activeSection={activeProcessingSection}
                                onSectionChange={(key) =>
                                    setActiveProcessingSection(
                                        key as ProcessingSectionKey,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-4">
                        <div
                            className={cn(
                                activeProcessingSection === 'recommendation'
                                    ? 'block'
                                    : 'hidden',
                            )}
                        >
                        {renderProcessingSectionLabel('Recommendation', {
                            first: true,
                        })}
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="inline_recommended_amount">
                                    Recommended amount
                                </Label>
                                <Input
                                    id="inline_recommended_amount"
                                    type="number"
                                    value={processingForm.recommended_amount}
                                    onChange={(event) =>
                                        setProcessingForm((current) => ({
                                            ...current,
                                            recommended_amount:
                                                event.target.value,
                                        }))
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="inline_recommended_term">
                                    Recommended term
                                </Label>
                                <Input
                                    id="inline_recommended_term"
                                    type="number"
                                    value={processingForm.recommended_term}
                                    onChange={(event) =>
                                        setProcessingForm((current) => ({
                                            ...current,
                                            recommended_term:
                                                event.target.value,
                                        }))
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="inline_recommended_interest_rate">
                                    Recommended interest rate
                                </Label>
                                <Input
                                    id="inline_recommended_interest_rate"
                                    type="number"
                                    step="0.01"
                                    value={
                                        processingForm.recommended_interest_rate
                                    }
                                    onChange={(event) =>
                                        setProcessingForm((current) => ({
                                            ...current,
                                            recommended_interest_rate:
                                                event.target.value,
                                        }))
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="inline_recommended_payment_frequency">
                                    Payment frequency
                                </Label>
                                <Input
                                    id="inline_recommended_payment_frequency"
                                    value={
                                        processingForm.recommended_payment_frequency
                                    }
                                    onChange={(event) =>
                                        setProcessingForm((current) => ({
                                            ...current,
                                            recommended_payment_frequency:
                                                event.target.value,
                                        }))
                                    }
                                />
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
                        </div>

                        <div
                            className={cn(
                                activeProcessingSection === 'charges_fees'
                                    ? 'block'
                                    : 'hidden',
                            )}
                        >
                        {renderProcessingSectionLabel('Charges & fees')}
                        <div className="grid gap-4 sm:grid-cols-2">
                            {renderProcessingField('service_charge_rate')}
                            {renderProcessingField('insurance_rate')}
                            {renderProcessingField('insurance_required')}
                            {renderProcessingField('insurance_term')}
                            {renderProcessingField('loan_security_rate')}
                            {renderProcessingField('documentary_stamp_rate')}
                            {renderProcessingField('notarial_fee')}
                            {renderProcessingField('notarial_venue')}
                            {renderProcessingField('penalty_rate_per_month')}
                        </div>
                        </div>

                        <div
                            className={cn(
                                activeProcessingSection ===
                                    'net_take_home_pay'
                                    ? 'block'
                                    : 'hidden',
                            )}
                        >
                        {renderProcessingSectionLabel('Net take-home pay')}
                        {renderProcessingField('guaranteed_net_take_home_pay')}
                        </div>

                        <div
                            className={cn(
                                activeProcessingSection ===
                                    'document_requirements'
                                    ? 'block'
                                    : 'hidden',
                            )}
                        >
                        {renderProcessingSectionLabel(
                            'Document requirements',
                        )}
                        <div className="grid gap-4 sm:grid-cols-2">
                            {renderProcessingField('authorization_required')}
                            {renderProcessingField('barangay_required')}
                            {renderProcessingField('security_required')}
                            {renderProcessingField('loan_security_details', {
                                fullWidth: processingSecurityRequired,
                            })}
                        </div>
                        </div>

                        <div
                            className={cn(
                                activeProcessingSection === 'personnel'
                                    ? 'block'
                                    : 'hidden',
                            )}
                        >
                        {renderProcessingSectionLabel('Personnel')}
                        <div className="grid gap-4 sm:grid-cols-2">
                            {renderProcessingField('witness_one_name')}
                            {renderProcessingField('witness_two_name')}
                            {renderProcessingField('barangay_official_name')}
                            {renderProcessingField('barangay_official_title')}
                        </div>
                        </div>

                        <div
                            className={cn(
                                activeProcessingSection === 'confirmation'
                                    ? 'block'
                                    : 'hidden',
                            )}
                        >
                        <Separator className="bg-border/40" />
                        <div className="grid gap-2">
                            <Label htmlFor="inline_processing_reason">
                                Reason
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
                        <div className="grid gap-2">
                            <Label htmlFor="inline_processing_information_source">
                                Information source
                            </Label>
                            <Input
                                id="inline_processing_information_source"
                                value={processingForm.information_source}
                                onChange={(event) =>
                                    setProcessingForm((current) => ({
                                        ...current,
                                        information_source: event.target.value,
                                    }))
                                }
                            />
                        </div>
                        </div>
                        </div>
                        </div>
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={isWorkflowProcessing}
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
                                    currentRequest.recommended_amount,
                                )}
                            />
                            <SnapshotRow
                                label="Recommended term"
                                value={recommendedTermLabel}
                            />
                            <SnapshotRow
                                label="Recommended interest rate"
                                value={snapshotDisplay(
                                    currentRequest.recommended_interest_rate,
                                )}
                            />
                            <SnapshotRow
                                label="Payment frequency"
                                value={snapshotDisplay(
                                    currentRequest.recommended_payment_frequency,
                                )}
                            />
                        </div>
                        <SnapshotRow
                            label="Recommendation remarks"
                            value={snapshotDisplay(
                                currentRequest.recommendation_remarks,
                            )}
                        />
                        <Separator className="bg-border/40" />
                        <div className="grid gap-4 sm:grid-cols-2">
                            {Object.entries(
                                dataSectionDefinitions.processing.fields,
                            ).map(([fieldKey, field]) => {
                                const value =
                                    currentDataSections.processing[fieldKey];
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

    const submittedAt = currentRequest.submitted_at
        ? formatDate(currentRequest.submitted_at)
        : null;
    const summarySubmittedLabel = submittedAt
        ? `Submitted ${submittedAt}`
        : 'Not submitted yet';
    const summaryAmount = displayCurrency(currentRequest.requested_amount);
    const summaryLoanTypeLabel = displayText(
        currentRequest.loan_type_label_snapshot,
    );
    const summaryRequestedTerm =
        currentRequest.requested_term !== null &&
        currentRequest.requested_term !== undefined &&
        `${currentRequest.requested_term}`.trim() !== ''
            ? `${currentRequest.requested_term} months`
            : '--';
    const summaryAvailmentStatus = displayValue(
        currentRequest.availment_status,
    );

    const processingWorkflowActions = {
        rejectDuringProcessing: canRejectDuringProcessing
            ? {
                  show: true,
                  isProcessing: isWorkflowProcessing,
                  onSubmit: (payload: {
                      rejection_category: string;
                      member_visible_reason: string;
                  }) =>
                      rejectLoanRequestDuringProcessing(
                          currentRequest.id,
                          payload,
                      ),
              }
            : undefined,
        returnForProcessing: canReturnForProcessing
            ? {
                  show: true,
                  isProcessing: isWorkflowProcessing,
                  onSubmit: (payload: { reason: string }) =>
                      returnForProcessing(currentRequest.id, payload),
              }
            : undefined,
        reopen: canReopenRejectedRequest
            ? {
                  show: true,
                  isProcessing: isWorkflowProcessing,
                  onSubmit: (payload: {
                      reason: string;
                      retain_assignment: boolean;
                  }) => reopenLoanRequest(currentRequest.id, payload),
              }
            : undefined,
        upgradeWorkflow: canUpgradeWorkflow
            ? {
                  show: true,
                  isProcessing: isWorkflowProcessing,
                  onSubmit: (payload: { reason: string }) =>
                      upgradeWorkflow(currentRequest.id, payload),
              }
            : undefined,
        generateDocuments: canGenerateDocuments
            ? {
                  show: true,
                  isProcessing: isWorkflowProcessing,
                  onSubmit: () => submitGenerateDocuments(),
              }
            : undefined,
    };

    const actionsHeaderContent = (
        <>
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant="outline">
                    Workflow:{' '}
                    {currentRequest.workflow_version === 'document_workflow_v2'
                        ? 'Document Workflow v2'
                        : 'Legacy v1'}
                </Badge>
                {assignedProcessorId !== null ? (
                    <Badge variant="secondary">Assigned Loan Processor</Badge>
                ) : null}
            </div>
            {lastDocumentResults !== null ? (
                <Alert className="border-sky-500/30 bg-sky-500/10">
                    <AlertTitle>Document generation results</AlertTitle>
                    <AlertDescription>
                        <p>
                            {lastDocumentResults.length} document
                            {lastDocumentResults.length === 1 ? '' : 's'}{' '}
                            refreshed from the latest generation run.
                        </p>
                        {documentResultSummary.length > 0 ? (
                            <div className="mt-2 flex flex-wrap gap-2">
                                {documentResultSummary.map((item) => (
                                    <span
                                        key={item.status}
                                        className={`rounded-full border px-2 py-1 text-[11px] font-semibold ${displayChecklistStatusTone(item.status)}`}
                                    >
                                        {item.label}: {item.count}
                                    </span>
                                ))}
                            </div>
                        ) : null}
                    </AlertDescription>
                </Alert>
            ) : null}
            {currentRequest.member_action_type !== null ? (
                <Alert className="border-violet-500/30 bg-violet-500/10">
                    <AlertTitle>Pending member action</AlertTitle>
                    <AlertDescription>
                        {currentRequest.member_action_message ??
                            'This request is waiting for a member response.'}
                    </AlertDescription>
                </Alert>
            ) : null}
            {canUpdateProcessing ? (
                <Button
                    type="button"
                    variant="outline"
                    className="w-full justify-start"
                    disabled={isWorkflowProcessing}
                    onClick={() => setIsCorrectionDialogOpen(true)}
                >
                    Correct Application Data
                </Button>
            ) : null}
            {canRequestMemberAction ? (
                <Button
                    type="button"
                    variant="outline"
                    className="w-full justify-start"
                    disabled={isWorkflowProcessing}
                    onClick={() => setIsMemberActionDialogOpen(true)}
                >
                    Request Member Action
                </Button>
            ) : null}
        </>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan request" />
            <section className="mx-auto mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <LoanRequestSummaryHeader
                    reference={currentRequest.reference}
                    status={currentRequest.status}
                    submittedLabel={summarySubmittedLabel}
                    amount={summaryAmount}
                    loanTypeLabel={summaryLoanTypeLabel}
                    requestedTerm={summaryRequestedTerm}
                    availmentStatus={summaryAvailmentStatus}
                    loanPurpose={displayText(currentRequest.loan_purpose)}
                />
            </section>
            <section className="mx-auto mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="space-y-6">
                    <LoanRequestApplicantCard applicant={currentApplicant} />
                    <LoanRequestCoMakersCard
                        coMakerOne={currentCoMakerOne}
                        coMakerTwo={currentCoMakerTwo}
                    />
                    <LoanRequestAuditTrail
                        entries={currentAuditTrail}
                        audience="staff"
                    />
                    <Card className={readOnlyCardClassName}>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <HeartPulse className="size-4 text-muted-foreground" />
                                Workflow health
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-x-6 gap-y-4 grid-cols-2 sm:grid-cols-4">
                            <div
                                className={`col-span-2 rounded-lg border px-3 py-2 text-sm font-medium sm:col-span-4 ${
                                    workflowHealthIssueCount === 0
                                        ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-200'
                                        : 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-200'
                                }`}
                            >
                                {workflowHealthIssueCount === 0
                                    ? 'All clear — no issues detected'
                                    : `${workflowHealthIssueCount} issue${workflowHealthIssueCount === 1 ? '' : 's'} need${workflowHealthIssueCount === 1 ? 's' : ''} attention`}
                            </div>
                            <div>
                                <p className="text-xs tracking-[0.18em] text-muted-foreground uppercase">
                                    Processing age
                                </p>
                                <p
                                    className={`mt-2 text-2xl ${workflowHealthIssues.processingAge ? 'font-bold text-rose-600 dark:text-rose-400' : 'font-semibold'}`}
                                >
                                    {currentWorkflowHealth.processing_age_days ===
                                    null
                                        ? '-'
                                        : `${currentWorkflowHealth.processing_age_days}d`}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs tracking-[0.18em] text-muted-foreground uppercase">
                                    Pending member action
                                </p>
                                <p
                                    className={`mt-2 text-2xl ${workflowHealthIssues.pendingMemberAction ? 'font-bold text-rose-600 dark:text-rose-400' : 'font-semibold'}`}
                                >
                                    {currentWorkflowHealth.pending_member_action
                                        ? 'Yes'
                                        : 'No'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs tracking-[0.18em] text-muted-foreground uppercase">
                                    Stale documents
                                </p>
                                <p
                                    className={`mt-2 text-2xl ${workflowHealthIssues.staleDocuments ? 'font-bold text-rose-600 dark:text-rose-400' : 'font-semibold'}`}
                                >
                                    {currentWorkflowHealth.stale_document_count}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs tracking-[0.18em] text-muted-foreground uppercase">
                                    Failed documents
                                </p>
                                <p
                                    className={`mt-2 text-2xl ${workflowHealthIssues.failedDocuments ? 'font-bold text-rose-600 dark:text-rose-400' : 'font-semibold'}`}
                                >
                                    {
                                        currentWorkflowHealth.failed_document_count
                                    }
                                </p>
                            </div>
                            <div>
                                <p className="text-xs tracking-[0.18em] text-muted-foreground uppercase">
                                    Legacy blockers
                                </p>
                                <p
                                    className={`mt-2 text-2xl ${workflowHealthIssues.legacyBlockers ? 'font-bold text-rose-600 dark:text-rose-400' : 'font-semibold'}`}
                                >
                                    {currentWorkflowHealth.legacy_blocker_count}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs tracking-[0.18em] text-muted-foreground uppercase">
                                    Notification failures
                                </p>
                                <p
                                    className={`mt-2 text-2xl ${workflowHealthIssues.notificationFailures ? 'font-bold text-rose-600 dark:text-rose-400' : 'font-semibold'}`}
                                >
                                    {
                                        currentWorkflowHealth.notification_failure_count
                                    }
                                </p>
                            </div>
                            <div>
                                <p className="text-xs tracking-[0.18em] text-muted-foreground uppercase">
                                    Workflow failed jobs
                                </p>
                                <p
                                    className={`mt-2 text-2xl ${workflowHealthIssues.workflowFailedJobs ? 'font-bold text-rose-600 dark:text-rose-400' : 'font-semibold'}`}
                                >
                                    {
                                        currentWorkflowHealth.workflow_failed_job_count
                                    }
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card className={readOnlyCardClassName}>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Bell className="size-4 text-muted-foreground" />
                                Notification history
                            </CardTitle>
                            <CardDescription>
                                Delivery state for workflow-triggered member
                                notifications.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {currentNotificationHistory.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No workflow notifications recorded yet.
                                </p>
                            ) : (
                                currentNotificationHistory.map((event) => (
                                    <div
                                        key={event.id}
                                        className="rounded-xl border border-border/40 bg-muted/10 p-4"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="text-sm font-semibold">
                                                        {event.event_label}
                                                    </p>
                                                    <Badge variant="outline">
                                                        {event.channel}
                                                    </Badge>
                                                    <span
                                                        className={`rounded-full border px-2 py-1 text-[11px] font-semibold ${displayNotificationStatusTone(event.status)}`}
                                                    >
                                                        {event.status ??
                                                            'unknown'}
                                                    </span>
                                                </div>
                                                <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                                    <span>
                                                        Queued:{' '}
                                                        {event.queued_at
                                                            ? formatDateTime(
                                                                  event.queued_at,
                                                              )
                                                            : '-'}
                                                    </span>
                                                    <span>
                                                        Sent:{' '}
                                                        {event.sent_at
                                                            ? formatDateTime(
                                                                  event.sent_at,
                                                              )
                                                            : '-'}
                                                    </span>
                                                    <span>
                                                        Failed:{' '}
                                                        {event.failed_at
                                                            ? formatDateTime(
                                                                  event.failed_at,
                                                              )
                                                            : '-'}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="text-right text-xs text-muted-foreground">
                                                <p>
                                                    Attempts:{' '}
                                                    {event.attempt_count}
                                                </p>
                                                <p>
                                                    Retries: {event.retry_count}
                                                </p>
                                                <p>
                                                    Reminders:{' '}
                                                    {event.reminder_attempts}
                                                </p>
                                            </div>
                                        </div>
                                        {event.provider_error ? (
                                            <p className="mt-3 text-xs text-rose-700 dark:text-rose-300">
                                                {event.provider_error}
                                            </p>
                                        ) : null}
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>
            </section>
            <section className="mx-auto mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div className="space-y-6">
                        {isProcessingStage ? inlineProcessingPanel : null}
                    <Card className="border-border/30 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ClipboardCheck className="size-4 text-muted-foreground" />
                                Document checklist
                            </CardTitle>
                            <CardDescription>
                                Every applicable document must be current before
                                recommendation.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col divide-y divide-border/40">
                            {[...currentDocumentChecklist]
                                .sort((a, b) => {
                                    const aIncomplete =
                                        a.blockers.length > 0 ? 1 : 0;
                                    const bIncomplete =
                                        b.blockers.length > 0 ? 1 : 0;

                                    return bIncomplete - aIncomplete;
                                })
                                .map((document) => {
                                const viewHref = `/staff/loan-requests/${currentRequest.id}/documents/generated/${document.key}`;
                                const isWorkbookDocument = [
                                    'loan_information',
                                    'plan_of_payment',
                                    'disclosure_statement',
                                    'promissory_note',
                                ].includes(document.key);
                                const previewHref = isWorkbookDocument
                                    ? `${viewHref}?preview=1`
                                    : viewHref;
                                const printDocumentHref = isWorkbookDocument
                                    ? `${viewHref}?print=1`
                                    : viewHref;
                                const downloadHref = `${viewHref}?download=1`;

                                const hasBeenGenerated =
                                    (document.generated_version ?? 0) > 0;
                                const missingFieldCount =
                                    document.blockers.length;
                                const { Icon: StatusIcon, className: statusIconClassName } =
                                    checklistStatusIcon(document.status);
                                const subtitle =
                                    document.template_version ?? document.key;

                                return (
                                    <div
                                        key={document.key}
                                        className="flex flex-col gap-2 py-3 first:pt-0 last:pb-0"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="flex min-w-0 items-center gap-2">
                                                <StatusIcon
                                                    className={cn(
                                                        'size-4 shrink-0',
                                                        statusIconClassName,
                                                    )}
                                                />
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-semibold">
                                                        {document.label}
                                                    </p>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {subtitle}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                {missingFieldCount > 0 ? (
                                                    <span className="text-xs text-muted-foreground">
                                                        {missingFieldCount}{' '}
                                                        field
                                                        {missingFieldCount === 1
                                                            ? ''
                                                            : 's'}{' '}
                                                        missing
                                                    </span>
                                                ) : null}
                                                {document.generated_filename ? (
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                            >
                                                                <MoreHorizontal className="size-4" />
                                                                <span className="sr-only">
                                                                    Document
                                                                    actions
                                                                </span>
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <a
                                                                    href={
                                                                        previewHref
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                >
                                                                    Preview
                                                                </a>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <a
                                                                    href={
                                                                        printDocumentHref
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                >
                                                                    Print
                                                                </a>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <a
                                                                    href={
                                                                        downloadHref
                                                                    }
                                                                >
                                                                    Download
                                                                </a>
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                ) : null}
                                                {canGenerateDocuments &&
                                                document.is_applicable ? (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        disabled={
                                                            isWorkflowProcessing
                                                        }
                                                        onClick={() =>
                                                            void submitGenerateDocuments(
                                                                document.key,
                                                            )
                                                        }
                                                    >
                                                        Regenerate
                                                    </Button>
                                                ) : null}
                                            </div>
                                        </div>
                                        {document.generated_at ||
                                        hasBeenGenerated ? (
                                            <div className="flex flex-wrap items-center gap-3 pl-6 text-[11px] text-muted-foreground">
                                                {document.generated_at ? (
                                                    <span>
                                                        Last generated:{' '}
                                                        {formatDateTime(
                                                            document.generated_at,
                                                        )}
                                                    </span>
                                                ) : null}
                                                {hasBeenGenerated ? (
                                                    <span>
                                                        Generated by:{' '}
                                                        {document.generated_by ??
                                                            '-'}{' '}
                                                        (v
                                                        {
                                                            document.generated_version
                                                        }
                                                        {document.source_version
                                                            ? `, source v${document.source_version}`
                                                            : ''}
                                                        )
                                                    </span>
                                                ) : null}
                                            </div>
                                        ) : null}
                                        {document.failure_message &&
                                        document.status !== 'incomplete' ? (
                                            <p className="pl-6 text-[11px] font-medium text-rose-700 dark:text-rose-200">
                                                {document.failure_message}
                                            </p>
                                        ) : null}
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                    {showWibsTrackingSection ? (
                    <Card className="border-border/30 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardTitle>WIBS Tracking</CardTitle>
                            <CardDescription>
                                Official loan tracking in the WIBS system.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-muted-foreground">
                                    Status:
                                </span>
                                <Badge variant="secondary">
                                    {currentRequest.status ===
                                    'converted_to_loan'
                                        ? 'Converted to Loan'
                                        : currentRequest.status ===
                                            'for_wibs_encoding'
                                          ? 'For WIBS Encoding'
                                          : currentRequest.status ===
                                              'wibs_loan_created'
                                            ? 'WIBS Loan Created'
                                            : currentRequest.status ===
                                                'release_scheduled'
                                              ? 'Release Scheduled'
                                              : 'Released'}
                                </Badge>
                            </div>

                            {currentRequest.wibs_loan_reference ? (
                                <div className="text-sm">
                                    <span className="font-medium">
                                        WIBS Reference:
                                    </span>{' '}
                                    {currentRequest.wibs_loan_reference}
                                </div>
                            ) : null}

                            {currentRequest.wibs_release_date ? (
                                <div className="text-sm">
                                    <span className="font-medium">
                                        Scheduled Release:
                                    </span>{' '}
                                    {currentRequest.wibs_release_date}
                                </div>
                            ) : null}

                            {currentRequest.wibs_released_at ? (
                                <div className="text-sm">
                                    <span className="font-medium">
                                        Released at:
                                    </span>{' '}
                                    {formatDateTime(
                                        currentRequest.wibs_released_at,
                                    )}
                                </div>
                            ) : null}

                            <Separator />

                            {currentRequest.status === 'converted_to_loan' ? (
                                <div className="space-y-2">
                                    <p className="text-sm text-muted-foreground">
                                        Forward this loan to WIBS for encoding.
                                    </p>
                                    <Button
                                        disabled={isWibsSubmitting}
                                        onClick={() => {
                                            setIsWibsSubmitting(true);
                                            router.patch(
                                                wibsMarkForEncoding(
                                                    currentRequest.id,
                                                ).url,
                                                {},
                                                {
                                                    onFinish: () =>
                                                        setIsWibsSubmitting(
                                                            false,
                                                        ),
                                                },
                                            );
                                        }}
                                    >
                                        {isWibsSubmitting
                                            ? 'Processing…'
                                            : 'Mark for WIBS Encoding'}
                                    </Button>
                                </div>
                            ) : currentRequest.status ===
                              'for_wibs_encoding' ? (
                                <form
                                    className="space-y-3"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        setIsWibsSubmitting(true);
                                        router.patch(
                                            wibsRecordReference(
                                                currentRequest.id,
                                            ).url,
                                            {
                                                wibs_loan_reference:
                                                    wibsReference,
                                            },
                                            {
                                                onFinish: () =>
                                                    setIsWibsSubmitting(false),
                                            },
                                        );
                                    }}
                                >
                                    <div className="space-y-1">
                                        <Label htmlFor="wibs_loan_reference">
                                            WIBS Loan Reference
                                        </Label>
                                        <Input
                                            id="wibs_loan_reference"
                                            value={wibsReference}
                                            onChange={(e) =>
                                                setWibsReference(e.target.value)
                                            }
                                            maxLength={100}
                                            required
                                            placeholder="e.g. WIBS-2026-001"
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={isWibsSubmitting}
                                    >
                                        {isWibsSubmitting
                                            ? 'Saving…'
                                            : 'Record WIBS Reference'}
                                    </Button>
                                </form>
                            ) : currentRequest.status ===
                              'wibs_loan_created' ? (
                                <form
                                    className="space-y-3"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        setIsWibsSubmitting(true);
                                        router.patch(
                                            wibsScheduleRelease(
                                                currentRequest.id,
                                            ).url,
                                            {
                                                wibs_release_date:
                                                    wibsReleaseDate,
                                            },
                                            {
                                                onFinish: () =>
                                                    setIsWibsSubmitting(false),
                                            },
                                        );
                                    }}
                                >
                                    <div className="space-y-1">
                                        <Label htmlFor="wibs_release_date">
                                            Release Date
                                        </Label>
                                        <Input
                                            id="wibs_release_date"
                                            type="date"
                                            value={wibsReleaseDate}
                                            onChange={(e) =>
                                                setWibsReleaseDate(
                                                    e.target.value,
                                                )
                                            }
                                            required
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={isWibsSubmitting}
                                    >
                                        {isWibsSubmitting
                                            ? 'Saving…'
                                            : 'Schedule Release'}
                                    </Button>
                                </form>
                            ) : currentRequest.status ===
                              'release_scheduled' ? (
                                <div className="space-y-2">
                                    <p className="text-sm text-muted-foreground">
                                        Confirm that the loan has been released
                                        to the member.
                                    </p>
                                    <Button
                                        disabled={isWibsSubmitting}
                                        onClick={() => {
                                            setIsWibsSubmitting(true);
                                            router.patch(
                                                wibsConfirmRelease(
                                                    currentRequest.id,
                                                ).url,
                                                {},
                                                {
                                                    onFinish: () =>
                                                        setIsWibsSubmitting(
                                                            false,
                                                        ),
                                                },
                                            );
                                        }}
                                    >
                                        {isWibsSubmitting
                                            ? 'Processing…'
                                            : 'Confirm Release'}
                                    </Button>
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>
                    ) : null}
                    </div>
                    <div>
            <LoanRequestDetailPage
                loanRequest={currentRequest}
                applicant={currentApplicant}
                coMakerOne={currentCoMakerOne}
                coMakerTwo={currentCoMakerTwo}
                backHref={requestsIndex().url}
                backLabel="Back to workflow queue"
                pdfHref={pdfHref}
                printHref={printHref}
                approvedDocumentHrefs={approvedDocumentHrefs}
                auditTrail={currentAuditTrail}
                auditTrailAudience="staff"
                workflow={{
                    claim: canClaim
                        ? {
                              show: true,
                              isProcessing: isWorkflowProcessing,
                              onSubmit: () =>
                                  claimLoanRequest(currentRequest.id),
                          }
                        : undefined,
                    assign: canAssign
                        ? {
                              show: true,
                              isProcessing: isWorkflowProcessing,
                              officerOptions: currentEligibleOfficers,
                              onSubmit: (payload) =>
                                  assignLoanRequest(currentRequest.id, payload),
                          }
                        : undefined,
                    reassign: canReassign
                        ? {
                              show: true,
                              isProcessing: isWorkflowProcessing,
                              officerOptions: currentEligibleOfficers,
                              onSubmit: (payload) =>
                                  reassignLoanRequest(
                                      currentRequest.id,
                                      payload,
                                  ),
                          }
                        : undefined,
                    returnToQueue: canReturnToQueue
                        ? {
                              show: true,
                              isProcessing: isWorkflowProcessing,
                              onSubmit: (payload) =>
                                  returnLoanRequestToQueue(
                                      currentRequest.id,
                                      payload,
                                  ),
                          }
                        : undefined,
                    startReview: canStartReview
                        ? {
                              show: true,
                              isProcessing: isWorkflowProcessing,
                              onSubmit: (payload) =>
                                  startReview(currentRequest.id, payload),
                          }
                        : undefined,
                    requestRevision: canRequestRevision
                        ? {
                              show: true,
                              isProcessing: isWorkflowProcessing,
                              onSubmit: (payload) =>
                                  requestRevision(currentRequest.id, payload),
                          }
                        : undefined,
                    reject: canReject
                        ? {
                              show: true,
                              isProcessing: isWorkflowProcessing,
                              onSubmit: (payload) =>
                                  rejectLoanRequest(currentRequest.id, payload),
                          }
                        : undefined,
                    recommendApproval: canRecommendApproval
                        ? {
                              show: true,
                              isProcessing: isWorkflowProcessing,
                              onSubmit: (payload) =>
                                  recommendApproval(currentRequest.id, payload),
                          }
                        : undefined,
                    approve: canWorkflowApprove
                        ? {
                              show: true,
                              isProcessing: isWorkflowProcessing,
                              onSubmit: (payload) =>
                                  approveLoanRequest(
                                      currentRequest.id,
                                      payload,
                                  ),
                          }
                        : undefined,
                    decline: canWorkflowDecline
                        ? {
                              show: true,
                              isProcessing: isWorkflowProcessing,
                              onSubmit: (payload) =>
                                  declineLoanRequest(
                                      currentRequest.id,
                                      payload,
                                  ),
                          }
                        : undefined,
                    ...processingWorkflowActions,
                }}
                actionsPanelHeader={actionsHeaderContent}
                hideSummaryHeader
                hideMainColumn
                wrapInShell={false}
            />
                    </div>
                </div>
            </section>

            <Dialog
                open={isCorrectionDialogOpen}
                onOpenChange={setIsCorrectionDialogOpen}
            >
                <DialogContent className="flex max-h-[90vh] flex-col sm:max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>Correct Application Data</DialogTitle>
                        <DialogDescription>
                            Correct the member-supplied request, applicant, and
                            co-maker details when supported by a record. Provide
                            a reason and information source for the audit trail.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="flex min-h-0 flex-1 flex-col"
                        onSubmit={submitCorrectionData}
                    >
                        <div className="flex-1 space-y-6 overflow-y-auto px-6 py-4">
                            <LoanRequestSectionCard
                                title="Loan request details"
                                description="Update the verified request details used throughout the document package."
                            >
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="correction_requested_amount">
                                            Requested amount
                                        </Label>
                                        <Input
                                            id="correction_requested_amount"
                                            type="number"
                                            value={
                                                correctionForm.loan_request
                                                    .requested_amount
                                            }
                                            onChange={(event) =>
                                                updateCorrectionDetailField(
                                                    'requested_amount',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="correction_requested_term">
                                            Requested term
                                        </Label>
                                        <Input
                                            id="correction_requested_term"
                                            type="number"
                                            value={
                                                correctionForm.loan_request
                                                    .requested_term
                                            }
                                            onChange={(event) =>
                                                updateCorrectionDetailField(
                                                    'requested_term',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="correction_loan_purpose">
                                            Loan purpose
                                        </Label>
                                        <Input
                                            id="correction_loan_purpose"
                                            value={
                                                correctionForm.loan_request
                                                    .loan_purpose
                                            }
                                            onChange={(event) =>
                                                updateCorrectionDetailField(
                                                    'loan_purpose',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2 md:col-span-2">
                                        <Label htmlFor="correction_availment_status">
                                            Availment status
                                        </Label>
                                        <Input
                                            id="correction_availment_status"
                                            value={
                                                correctionForm.loan_request
                                                    .availment_status
                                            }
                                            onChange={(event) =>
                                                updateCorrectionDetailField(
                                                    'availment_status',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            </LoanRequestSectionCard>

                            <LoanRequestSectionCard
                                title="Applicant information"
                                description="Correct verified applicant data when supported by the processing record."
                            >
                                <div className="space-y-6">
                                    <LoanRequestPersonalFields
                                        prefix="applicant"
                                        values={correctionForm.applicant}
                                        errors={{}}
                                        includeSpouse
                                        includeChildren
                                        onChange={updateCorrectionPersonField(
                                            'applicant',
                                        )}
                                    />
                                    <Separator className="bg-border/40" />
                                    <LoanRequestWorkFields
                                        prefix="applicant"
                                        values={correctionForm.applicant}
                                        errors={{}}
                                        onChange={updateCorrectionPersonField(
                                            'applicant',
                                        )}
                                    />
                                </div>
                            </LoanRequestSectionCard>

                            <LoanRequestSectionCard
                                title="Co-maker 1"
                                description="Update verified co-maker information when corrections are confirmed."
                            >
                                <div className="space-y-6">
                                    <LoanRequestPersonalFields
                                        prefix="co_maker_1"
                                        values={correctionForm.co_maker_1}
                                        errors={{}}
                                        onChange={updateCorrectionPersonField(
                                            'co_maker_1',
                                        )}
                                    />
                                    <Separator className="bg-border/40" />
                                    <LoanRequestWorkFields
                                        prefix="co_maker_1"
                                        values={correctionForm.co_maker_1}
                                        errors={{}}
                                        onChange={updateCorrectionPersonField(
                                            'co_maker_1',
                                        )}
                                    />
                                </div>
                            </LoanRequestSectionCard>

                            <LoanRequestSectionCard
                                title="Co-maker 2"
                                description="Update verified co-maker information when corrections are confirmed."
                            >
                                <div className="space-y-6">
                                    <LoanRequestPersonalFields
                                        prefix="co_maker_2"
                                        values={correctionForm.co_maker_2}
                                        errors={{}}
                                        onChange={updateCorrectionPersonField(
                                            'co_maker_2',
                                        )}
                                    />
                                    <Separator className="bg-border/40" />
                                    <LoanRequestWorkFields
                                        prefix="co_maker_2"
                                        values={correctionForm.co_maker_2}
                                        errors={{}}
                                        onChange={updateCorrectionPersonField(
                                            'co_maker_2',
                                        )}
                                    />
                                </div>
                            </LoanRequestSectionCard>
                        </div>
                        <div className="shrink-0 border-t border-border px-6 py-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="correction_reason">
                                        Reason
                                    </Label>
                                    <textarea
                                        id="correction_reason"
                                        className={textareaClassName}
                                        required
                                        value={correctionForm.reason}
                                        onChange={(event) =>
                                            setCorrectionForm((current) => ({
                                                ...current,
                                                reason: event.target.value,
                                            }))
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="correction_information_source">
                                        Information source
                                    </Label>
                                    <Input
                                        id="correction_information_source"
                                        required
                                        value={
                                            correctionForm.information_source
                                        }
                                        onChange={(event) =>
                                            setCorrectionForm((current) => ({
                                                ...current,
                                                information_source:
                                                    event.target.value,
                                            }))
                                        }
                                    />
                                </div>
                            </div>
                            <DialogFooter className="mt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        setIsCorrectionDialogOpen(false)
                                    }
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={isWorkflowProcessing}
                                >
                                    Save Corrections
                                </Button>
                            </DialogFooter>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={isMemberActionDialogOpen}
                onOpenChange={setIsMemberActionDialogOpen}
            >
                <DialogContent className="flex max-h-[90vh] flex-col sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Request Member Action</DialogTitle>
                        <DialogDescription>
                            Ask the member for a correction or for additional
                            information, and record exactly which fields need
                            their attention.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="flex flex-1 flex-col space-y-5 overflow-hidden"
                        onSubmit={submitMemberAction}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="member_action_type">
                                Action type
                            </Label>
                            <Input
                                id="member_action_type"
                                value={
                                    memberActionType ===
                                    'awaiting_member_information'
                                        ? 'Awaiting member information'
                                        : 'Needs revision'
                                }
                                readOnly
                                onClick={() =>
                                    setMemberActionType((current) =>
                                        current ===
                                        'awaiting_member_information'
                                            ? 'needs_revision'
                                            : 'awaiting_member_information',
                                    )
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Click to toggle between correction and
                                information requests.
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="member_action_message">
                                Member-visible message
                            </Label>
                            <textarea
                                id="member_action_message"
                                className={textareaClassName}
                                required
                                value={memberActionMessage}
                                onChange={(event) =>
                                    setMemberActionMessage(event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="member_action_reason">
                                Internal reason
                            </Label>
                            <textarea
                                id="member_action_reason"
                                className={cn(
                                    textareaClassName,
                                    'min-h-[80px]',
                                )}
                                rows={3}
                                required
                                value={memberActionReason}
                                onChange={(event) =>
                                    setMemberActionReason(event.target.value)
                                }
                            />
                        </div>
                        <div className="flex-1 space-y-3 overflow-y-auto px-6 pb-6">
                            <p className="text-sm font-medium">
                                Fields requiring member action
                            </p>
                            {memberFieldGroups.map((group) => (
                                <div key={group.sectionKey}>
                                    <p className="mt-4 mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        {group.label}
                                    </p>
                                    <div className="grid gap-3 md:grid-cols-2">
                                        {group.items.map((item) => (
                                            <label
                                                key={item.fieldKey}
                                                className="flex items-start gap-3 rounded-lg border border-border/40 bg-muted/10 p-3 text-sm"
                                            >
                                                <Checkbox
                                                    checked={selectedMemberFields.includes(
                                                        item.fieldKey,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setSelectedMemberFields(
                                                            (current) =>
                                                                checked === true
                                                                    ? [
                                                                          ...current,
                                                                          item.fieldKey,
                                                                      ]
                                                                    : current.filter(
                                                                          (
                                                                              field,
                                                                          ) =>
                                                                              field !==
                                                                              item.fieldKey,
                                                                      ),
                                                        )
                                                    }
                                                />
                                                <span>{item.field.label}</span>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    setIsMemberActionDialogOpen(false)
                                }
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={isWorkflowProcessing}
                            >
                                Send Member Action Request
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

        </AppLayout>
    );
}
