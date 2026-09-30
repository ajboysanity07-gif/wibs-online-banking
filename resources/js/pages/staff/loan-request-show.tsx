import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Truck } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { DateInputWithPicker } from '@/components/loan-request/date-input-with-picker';
import { LoanRequestActivityTab } from '@/components/loan-request/loan-request-activity-tab';
import { LoanRequestApplicantSnapshot } from '@/components/loan-request/loan-request-applicant-snapshot';
import { LoanRequestAttentionCard } from '@/components/loan-request/loan-request-attention-card';
import { LoanRequestConditionsCard } from '@/components/loan-request/loan-request-conditions-card';
import { LoanRequestDecisionHeader } from '@/components/loan-request/loan-request-decision-header';
import {
    LoanRequestLoanInformationCard,
    displayText,
    personName,
} from '@/components/loan-request/loan-request-detail-page';
import {
    displayChecklistStatusTone,
    LoanRequestDocumentChecklistCard,
} from '@/components/loan-request/loan-request-document-checklist-card';
import {
    LoanRequestPersonalFields,
    LoanRequestWorkFields,
} from '@/components/loan-request/loan-request-fields';
import { LoanRequestProgressCard } from '@/components/loan-request/loan-request-progress-card';
import { LoanRequestRecommendationSummary } from '@/components/loan-request/loan-request-recommendation-summary';
import {
    LoanRequestApplicantPanel,
    LoanRequestCoMakerCard,
} from '@/components/loan-request/loan-request-review-people';
import {
    LoanRequestHealthCard,
    LoanRequestReadyCard,
    LoanRequestStatusRailCard,
} from '@/components/loan-request/loan-request-review-rail';
import {
    LoanRequestReviewTabs,
    ReviewTabPanel,
    useReviewTab,
} from '@/components/loan-request/loan-request-review-tabs';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import {
    isMicroBusinessLoanLabel,
    KIND_OF_LOAN_OPTIONS,
    OTHER_LOAN_TYPECODE,
} from '@/components/loan-request/loan-request-steps';
import type { LoanRequestWorkflowProps } from '@/components/loan-request/loan-request-workflow-actions';
import { LoanRequestWorkflowActions } from '@/components/loan-request/loan-request-workflow-actions';
import {
    CurrencyInput,
    MonthsInput,
} from '@/components/loan-request/numeric-adorned-inputs';
import {
    ProcessingDetailsPanel,
    type RecommendationPreviewState,
    textareaClassName,
    toStringValue,
} from '@/components/loan-request/processing-details-panel';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useLoanRequestWorkflow } from '@/hooks/admin/use-loan-request-workflow';
import { useApprovedDocumentPackageDownload } from '@/hooks/loan-request/use-approved-document-package-download';
import AppLayout from '@/layouts/app-layout';
import { adminApi } from '@/lib/api/admin';
import { staffApprovedDocumentPackageApi } from '@/lib/api/approved-document-package';
import { formatDate, formatDateTime } from '@/lib/formatters';
import { institutionalEmployerCategoryMismatch } from '@/lib/institutional-employer-category';
import { buildAttentionRows } from '@/lib/loan-request-attention';
import { buildRecommendGates } from '@/lib/loan-request-gates';
import type { ReviewTabId } from '@/lib/loan-request-review-tab';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import {
    index as requestsIndex,
    pdf as requestsPdf,
    show as requestsShow,
} from '@/routes/staff/loan-requests';
import {
    confirmRelease as wibsConfirmRelease,
    markForEncoding as wibsMarkForEncoding,
    recordReference as wibsRecordReference,
    scheduleRelease as wibsScheduleRelease,
} from '@/routes/staff/loan-requests/wibs';
import type { BreadcrumbItem } from '@/types';
import type { Auth } from '@/types/auth';
import type {
    LoanManagerOption,
    LoanRequestAuditEntry,
    LoanRequestAssignmentOfficerOption,
    LoanRequestBankingSectionValues,
    LoanRequestConditions,
    LoanRequestCycleState,
    LoanRequestDataSectionDefinitions,
    LoanRequestDataSections,
    LoanRequestDetail,
    LoanRequestDocumentChecklistItem,
    LoanRequestDocumentKey,
    LoanRequestMemberAction,
    LoanRequestNotificationHistoryItem,
    LoanRequestPersonData,
    LoanRequestPersonFormData,
    LoanRequestWorkflowContext,
    LoanRequestWorkflowHealth,
    LoanRequestWorkflowPermission,
    LoanTypeOption,
} from '@/types/loan-requests';

type Props = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    auditTrail: LoanRequestAuditEntry[];
    eligibleOfficers: LoanRequestAssignmentOfficerOption[];
    loanManagers: LoanManagerOption[];
    loanTypes: LoanTypeOption[];
    dataSections: LoanRequestDataSections;
    dataSectionDefinitions: LoanRequestDataSectionDefinitions;
    cycleState: LoanRequestCycleState;
    documentChecklist: LoanRequestDocumentChecklistItem[];
    conditions: LoanRequestConditions;
    memberAction: LoanRequestMemberAction;
    notificationHistory: LoanRequestNotificationHistoryItem[];
    workflowPermissions: LoanRequestWorkflowPermission[];
    workflowContext: LoanRequestWorkflowContext;
    workflowHealth: LoanRequestWorkflowHealth;
};

const sectionEditButtonClassName =
    'transition-all duration-150 ease-out active:scale-90 active:duration-75 hover:-translate-y-0.5 hover:shadow-md [&_svg]:transition-transform [&_svg]:duration-150 active:[&_svg]:rotate-12';

const emptyPerson: LoanRequestPersonFormData = {
    first_name: '',
    middle_name: '',
    last_name: '',
    nickname: '',
    birthdate: '',
    birthplace_city: '',
    birthplace_province: '',
    address1: '',
    address_barangay: '',
    address2: '',
    address3: '',
    address_zip: '',
    length_of_stay: '',
    housing_status: '',
    cell_no: '',
    civil_status: '',
    sex: '',
    educational_attainment: '',
    number_of_children: '',
    spouse_name: '',
    spouse_birthdate: '',
    spouse_cell_no: '',
    employment_type: '',
    employer_business_name: '',
    employer_business_address1: '',
    employer_business_address_barangay: '',
    employer_business_address2: '',
    employer_business_address3: '',
    employer_business_address_zip: '',
    telephone_no: '',
    current_position: '',
    nature_of_business: '',
    institutional_employer_category: '',
    years_in_work_business: '',
    employer_date_employed: '',
    gross_monthly_income: '',
    payday: '',
    save_for_reuse: false,
    saved_co_maker_id: '',
    saved_co_maker_label: '',
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
        address_barangay: person.address_barangay ?? '',
        address2: person.address2 ?? '',
        address3: person.address3 ?? '',
        address_zip: person.address_zip ?? '',
        length_of_stay: person.length_of_stay ?? '',
        housing_status: person.housing_status ?? '',
        cell_no: person.cell_no ?? '',
        civil_status: person.civil_status ?? '',
        sex: person.sex ?? '',
        educational_attainment: person.educational_attainment ?? '',
        number_of_children: toStringValue(person.number_of_children, {
            emptyIfZero: false,
        }),
        spouse_name: person.spouse_name ?? '',
        spouse_birthdate: person.spouse_birthdate ?? '',
        spouse_cell_no: person.spouse_cell_no ?? '',
        employment_type: person.employment_type ?? '',
        employer_business_name: person.employer_business_name ?? '',
        employer_business_address1: person.employer_business_address1 ?? '',
        employer_business_address_barangay:
            person.employer_business_address_barangay ?? '',
        employer_business_address2: person.employer_business_address2 ?? '',
        employer_business_address3: person.employer_business_address3 ?? '',
        employer_business_address_zip:
            person.employer_business_address_zip ?? '',
        telephone_no: person.telephone_no ?? '',
        current_position: person.current_position ?? '',
        nature_of_business: person.nature_of_business ?? '',
        institutional_employer_category:
            person.institutional_employer_category ?? '',
        years_in_work_business: person.years_in_work_business ?? '',
        employer_date_employed: person.employer_date_employed ?? '',
        gross_monthly_income: toStringValue(person.gross_monthly_income),
        payday: person.payday ?? '',
    };
};

const PROCESSING_AGE_ISSUE_THRESHOLD_DAYS = 3;

const documentResultStatusOrder = [
    'generated_current',
    'generated_stale',
    'ready_to_generate',
    'not_applicable',
    'generation_failed',
    'awaiting_member_confirmation',
    'incomplete',
] as const;

// Category B — application-data corrections, edited inline per card.
type LoanInfoFormState = {
    typecode: string;
    kind_of_loan: string;
    other_loan_type_name: string;
    requested_amount: string;
    requested_term: string;
    loan_purpose: string;
    availment_status: string;
};

type EditableSection =
    | 'loan_request'
    | 'applicant'
    | 'co_maker_1'
    | 'co_maker_2'
    | null;

const toLoanInfoForm = (request: LoanRequestDetail): LoanInfoFormState => ({
    typecode: request.typecode ?? '',
    kind_of_loan: request.kind_of_loan ?? '',
    other_loan_type_name: request.other_loan_type_name ?? '',
    requested_amount: toStringValue(request.requested_amount),
    requested_term: toStringValue(request.requested_term),
    loan_purpose: request.loan_purpose ?? '',
    availment_status: request.availment_status ?? '',
});

export default function StaffLoanRequestShow({
    loanRequest,
    applicant,
    coMakerOne,
    coMakerTwo,
    auditTrail,
    eligibleOfficers,
    loanManagers,
    loanTypes,
    dataSections,
    dataSectionDefinitions,
    cycleState,
    documentChecklist,
    conditions,
    notificationHistory,
    workflowPermissions,
    workflowContext,
    workflowHealth,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [currentRequest, setCurrentRequest] =
        useState<LoanRequestDetail>(loanRequest);
    const packageZipDownload = useApprovedDocumentPackageDownload(
        currentRequest.id,
        staffApprovedDocumentPackageApi,
    );
    const [currentApplicant, setCurrentApplicant] =
        useState<LoanRequestPersonData | null>(applicant);
    const [currentCoMakerOne, setCurrentCoMakerOne] =
        useState<LoanRequestPersonData | null>(coMakerOne);
    const [currentCoMakerTwo, setCurrentCoMakerTwo] =
        useState<LoanRequestPersonData | null>(coMakerTwo);
    const [currentAuditTrail, setCurrentAuditTrail] =
        useState<LoanRequestAuditEntry[]>(auditTrail);
    const [currentConditions, setCurrentConditions] =
        useState<LoanRequestConditions>(conditions);
    const [conditionPendingKey, setConditionPendingKey] = useState<
        string | null
    >(null);
    const [currentEligibleOfficers, setCurrentEligibleOfficers] =
        useState<LoanRequestAssignmentOfficerOption[]>(eligibleOfficers);
    const [currentDataSections, setCurrentDataSections] =
        useState<LoanRequestDataSections>(dataSections);
    const [currentCycleState, setCurrentCycleState] =
        useState<LoanRequestCycleState>(cycleState);
    const [currentDocumentChecklist, setCurrentDocumentChecklist] =
        useState<LoanRequestDocumentChecklistItem[]>(documentChecklist);
    // Live-previews applicability as the Employer Classification dropdown
    // changes, ahead of an actual processing-details save (which still
    // replaces the whole checklist via onUpdated below).
    const applyDocumentChecklistPreview = (
        updates: {
            key: string;
            is_applicable: boolean;
            unavailable_reason: string | null;
        }[],
    ) => {
        setCurrentDocumentChecklist((current) =>
            current.map((document) => {
                const update = updates.find(
                    (item) => item.key === document.key,
                );

                return update
                    ? {
                          ...document,
                          is_applicable: update.is_applicable,
                          unavailable_reason: update.unavailable_reason,
                      }
                    : document;
            }),
        );
    };
    const [currentNotificationHistory, setCurrentNotificationHistory] =
        useState<LoanRequestNotificationHistoryItem[]>(notificationHistory);
    const [currentWorkflowHealth, setCurrentWorkflowHealth] =
        useState<LoanRequestWorkflowHealth>(workflowHealth);
    const [lastDocumentResults, setLastDocumentResults] = useState<
        LoanRequestDocumentChecklistItem[] | null
    >(null);
    const [editingSection, setEditingSection] = useState<EditableSection>(null);
    const [processingPreview, setProcessingPreview] =
        useState<RecommendationPreviewState | null>(null);
    const [processingEditSignal, setProcessingEditSignal] = useState(0);
    const [tab, setTab] = useReviewTab();
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
    const [memberFieldSearch, setMemberFieldSearch] = useState('');
    const [openMemberFieldGroups, setOpenMemberFieldGroups] = useState<
        string[]
    >([]);
    const [loanInfoForm, setLoanInfoForm] = useState<LoanInfoFormState>(() =>
        toLoanInfoForm(loanRequest),
    );
    const [loanInfoReason, setLoanInfoReason] = useState('');
    const [applicantForm, setApplicantForm] =
        useState<LoanRequestPersonFormData>(() => toPersonForm(applicant));
    const [applicantReason, setApplicantReason] = useState('');
    const [coMakerOneForm, setCoMakerOneForm] =
        useState<LoanRequestPersonFormData>(() => toPersonForm(coMakerOne));
    const [coMakerOneReason, setCoMakerOneReason] = useState('');
    const [coMakerTwoForm, setCoMakerTwoForm] =
        useState<LoanRequestPersonFormData>(() => toPersonForm(coMakerTwo));
    const [coMakerTwoReason, setCoMakerTwoReason] = useState('');
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
        lastErrors: workflowLastErrors,
        clearLastError: clearWorkflowLastError,
    } = useLoanRequestWorkflow({
        onUpdated: (result) => {
            setCurrentRequest(result.loanRequest);
            setCurrentApplicant(result.applicant);
            setCurrentCoMakerOne(result.coMakerOne);
            setCurrentCoMakerTwo(result.coMakerTwo);
            setCurrentAuditTrail(result.auditTrail);
            setCurrentEligibleOfficers(result.eligibleOfficers);
            setCurrentDataSections(result.dataSections);
            setCurrentCycleState(result.cycleState);
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
    const openSectionEdit = (section: Exclude<EditableSection, null>) => {
        if (section === 'loan_request') {
            setLoanInfoForm(toLoanInfoForm(currentRequest));
            setLoanInfoReason('');
        } else if (section === 'applicant') {
            setApplicantForm(toPersonForm(currentApplicant));
            setApplicantReason('');
        } else if (section === 'co_maker_1') {
            setCoMakerOneForm(toPersonForm(currentCoMakerOne));
            setCoMakerOneReason('');
        } else {
            setCoMakerTwoForm(toPersonForm(currentCoMakerTwo));
            setCoMakerTwoReason('');
        }

        setEditingSection(section);
    };

    const closeSectionEdit = () => setEditingSection(null);

    const hasWorkflowPermission = (
        permission: LoanRequestWorkflowPermission,
    ): boolean => workflowPermissions.includes(permission);

    useEffect(() => {
        if (
            loanRequest.applicant_loan_status === null ||
            !loanRequest.applicant_loan_status.requires_attention
        ) {
            return;
        }

        const requestId = loanRequest.id;

        // Best-effort audit logging -- never surface the global "Access
        // denied" toast for this fire-and-forget background call. The page
        // itself already passed the identical view-authorization check, so
        // a failure here shouldn't alarm a user who can plainly see the page.
        adminApi.logLoanRequestWarningViewed(requestId).catch(() => {});
    }, [loanRequest.id, loanRequest.applicant_loan_status]);

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
    const normalizedDesignatedManagerId = normalizeId(
        currentRequest.designated_manager_id,
    );
    const isDesignatedManager =
        normalizedDesignatedManagerId === null ||
        normalizedDesignatedManagerId === normalizedActorUserId;
    const documentResultSummary =
        lastDocumentResults === null
            ? []
            : documentResultStatusOrder
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
    const workflowHealthIssues = {
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
        workflowFailedJobs: currentWorkflowHealth.workflow_failed_job_count > 0,
    };
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
    const showProcessingSection = ![
        'draft',
        'pending_co_maker_signatures',
        'submitted',
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
    const canManagerCorrect =
        !isOwnRequest &&
        hasWorkflowPermission('loan.correct') &&
        currentRequest.status === 'recommended_for_approval' &&
        isDesignatedManager;
    const canCorrectApplication = canUpdateProcessing || canManagerCorrect;
    const canCorrectProcessingPostApproval =
        !isOwnRequest &&
        hasWorkflowPermission('loan.correct') &&
        isDesignatedManager &&
        ['approved', 'converted_to_loan'].includes(currentRequest.status ?? '');
    const canGenerateDocuments =
        canUpdateProcessing ||
        (!isOwnRequest &&
            hasWorkflowPermission('loan.review') &&
            ['approved', 'converted_to_loan'].includes(
                currentRequest.status ?? '',
            )) ||
        (!isOwnRequest &&
            hasWorkflowPermission('loan.approve') &&
            currentRequest.status === 'recommended_for_approval' &&
            isDesignatedManager);
    const canRecommendApproval =
        !isOwnRequest &&
        currentRequest.status === 'under_review' &&
        hasWorkflowPermission('loan.recommend_approval') &&
        normalizedAssignedProcessorId === normalizedActorUserId;
    const canWorkflowApprove =
        !isOwnRequest &&
        currentRequest.status === 'recommended_for_approval' &&
        hasWorkflowPermission('loan.approve') &&
        isDesignatedManager;
    const canWorkflowDecline =
        !isOwnRequest &&
        currentRequest.status === 'recommended_for_approval' &&
        hasWorkflowPermission('loan.decline') &&
        isDesignatedManager;
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
            ((hasWorkflowPermission('loan.approve') ||
                hasWorkflowPermission('loan.decline')) &&
                isDesignatedManager));
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
    const memberFieldDefinitions = Object.entries(
        dataSectionDefinitions,
    ).flatMap(([sectionKey, section]) =>
        Object.entries(section.fields)
            .filter(([, field]) => field.owner === 'member')
            .map(([fieldKey, field]) => ({
                fieldKey,
                sectionKey,
                field,
            })),
    );
    const memberFieldGroupsMap = new Map<
        string,
        typeof memberFieldDefinitions
    >();

    memberFieldDefinitions.forEach((item) => {
        // 'health' and 'health_glapi' render as a single merged "Health
        // Insurance Questionnaire" group — there is no separate
        // "Health declarations" concept. Beneficiary fields ('insurance')
        // live inside the wizard's single "Dependents" step (not a separate
        // "Insurance" step), so fold them into 'dependents' too — this loop
        // visits 'insurance' before 'dependents' (SECTION_LABELS order),
        // which naturally puts beneficiary fields first in the merged group.
        const sectionKey =
            item.sectionKey === 'health'
                ? 'health_glapi'
                : item.sectionKey === 'insurance'
                  ? 'dependents'
                  : item.sectionKey;
        const existing = memberFieldGroupsMap.get(sectionKey) ?? [];
        existing.push(item);
        memberFieldGroupsMap.set(sectionKey, existing);
    });

    // Labels/order here mirror the current loan-request wizard's step
    // naming (loan-request-wizard-steps.ts), not the legacy
    // LoanRequestDataService::SECTION_LABELS wording used elsewhere.
    const memberFieldGroupLabels: Record<string, string> = {
        dependents: 'Dependents & Beneficiaries',
        health_glapi: 'Health Insurance Questionnaire',
        banking: 'Loan Disbursement & Repayment',
        declarations: 'Declarations',
    };
    const memberFieldPriorityOrder = [
        'dependents',
        'health_glapi',
        'banking',
        'declarations',
    ];
    const memberFieldPriorityKeys = memberFieldPriorityOrder.filter((key) =>
        memberFieldGroupsMap.has(key),
    );
    const memberFieldRemainingKeys = Array.from(memberFieldGroupsMap.keys())
        .filter((key) => !memberFieldPriorityOrder.includes(key))
        .sort((a, b) =>
            (dataSectionDefinitions[a]?.label ?? a).localeCompare(
                dataSectionDefinitions[b]?.label ?? b,
            ),
        );

    const memberFieldGroups = [
        ...memberFieldPriorityKeys,
        ...memberFieldRemainingKeys,
    ].map((sectionKey) => ({
        sectionKey,
        label:
            memberFieldGroupLabels[sectionKey] ??
            dataSectionDefinitions[sectionKey]?.label ??
            sectionKey,
        items: memberFieldGroupsMap.get(sectionKey) ?? [],
    }));

    const memberFieldSearchQuery = memberFieldSearch.trim().toLowerCase();
    const visibleMemberFieldGroups = memberFieldSearchQuery
        ? memberFieldGroups
              .map((group) => ({
                  ...group,
                  items: group.items.filter((item) =>
                      item.field.label
                          .toLowerCase()
                          .includes(memberFieldSearchQuery),
                  ),
              }))
              .filter((group) => group.items.length > 0)
        : memberFieldGroups;

    const updateLoanInfoField = (
        field: keyof LoanInfoFormState,
        value: string,
    ) => {
        setLoanInfoForm((current) => ({ ...current, [field]: value }));
    };

    const loanInfoSelectedLoanTypeLabel =
        loanTypes.find((option) => option.typecode === loanInfoForm.typecode)
            ?.label ?? null;
    const isLoanInfoMicroBusinessLoan = isMicroBusinessLoanLabel(
        loanInfoSelectedLoanTypeLabel,
    );
    const isLoanInfoOtherLoan = loanInfoForm.typecode === OTHER_LOAN_TYPECODE;

    const updateLoanInfoType = (typecode: string) => {
        const label =
            loanTypes.find((option) => option.typecode === typecode)?.label ??
            null;

        setLoanInfoForm((current) => ({
            ...current,
            typecode,
            kind_of_loan: isMicroBusinessLoanLabel(label)
                ? current.kind_of_loan
                : '',
            other_loan_type_name:
                typecode === OTHER_LOAN_TYPECODE
                    ? current.other_loan_type_name
                    : '',
        }));
    };

    const updateApplicantField = (
        field: keyof LoanRequestPersonFormData,
        value: string,
    ) => {
        setApplicantForm((current) => ({ ...current, [field]: value }));
    };

    const updateCoMakerOneField = (
        field: keyof LoanRequestPersonFormData,
        value: string,
    ) => {
        setCoMakerOneForm((current) => ({ ...current, [field]: value }));
    };

    const updateCoMakerTwoField = (
        field: keyof LoanRequestPersonFormData,
        value: string,
    ) => {
        setCoMakerTwoForm((current) => ({ ...current, [field]: value }));
    };

    const submitLoanInfoCorrection = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        const result = await updateProcessingDetails(currentRequest.id, {
            reason: loanInfoReason,
            loan_request: loanInfoForm,
        });

        if (result) {
            closeSectionEdit();
        }
    };

    const submitApplicantCorrection = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        const result = await updateProcessingDetails(currentRequest.id, {
            reason: applicantReason,
            applicant: applicantForm,
        });

        if (result) {
            closeSectionEdit();
        }
    };

    const submitCoMakerOneCorrection = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        const result = await updateProcessingDetails(currentRequest.id, {
            reason: coMakerOneReason,
            co_maker_1: coMakerOneForm,
        });

        if (result) {
            closeSectionEdit();
        }
    };

    const submitCoMakerTwoCorrection = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        const result = await updateProcessingDetails(currentRequest.id, {
            reason: coMakerTwoReason,
            co_maker_2: coMakerTwoForm,
        });

        if (result) {
            closeSectionEdit();
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
            setMemberFieldSearch('');
            setOpenMemberFieldGroups([]);
        }
    };

    const submitGenerateDocuments = async (
        documentKey?: LoanRequestDocumentKey,
        silent = false,
    ) => {
        return generateDocuments(
            currentRequest.id,
            documentKey ? { document_key: documentKey } : {},
            { silent },
        );
    };

    const submitGenerateSelectedDocuments = async (
        documentKeys: string[],
        onDocumentSettled?: (documentKey: string) => void,
    ) => {
        let successCount = 0;
        let failureCount = 0;

        for (const documentKey of documentKeys) {
            const result = await submitGenerateDocuments(
                documentKey as LoanRequestDocumentKey,
                true,
            );

            if (result) {
                successCount += 1;
            } else {
                failureCount += 1;
            }

            onDocumentSettled?.(documentKey);
        }

        if (failureCount === 0) {
            showSuccessToast(
                `Document generation completed (${successCount} of ${documentKeys.length}).`,
            );
        } else if (successCount === 0) {
            showErrorToast(
                null,
                `Failed to generate ${failureCount} document${failureCount === 1 ? '' : 's'}.`,
            );
        } else {
            showErrorToast(
                null,
                `Generated ${successCount} of ${documentKeys.length} documents; ${failureCount} failed.`,
            );
        }
    };

    const submittedAt = currentRequest.submitted_at
        ? formatDate(currentRequest.submitted_at)
        : null;

    const processingWorkflowActions = {
        rejectDuringProcessing: canRejectDuringProcessing
            ? {
                  show: true,
                  isProcessing: isWorkflowProcessing,
                  onSubmit: (payload: {
                      rejection_category: string;
                      rejection_category_other?: string | null;
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
    };

    const isManagerViewer =
        hasWorkflowPermission('loan.approve') ||
        hasWorkflowPermission('loan.decline');
    const managerStageAlert = isManagerViewer
        ? (() => {
              const status = currentRequest.status ?? '';
              const processorName =
                  currentRequest.assigned_processor?.name ??
                  currentRequest.assigned_officer?.name ??
                  null;

              if (
                  [
                      'pending_review',
                      'under_review',
                      'needs_revision',
                      'awaiting_member_information',
                  ].includes(status)
              ) {
                  return {
                      tone: 'pending' as const,
                      title: 'Not ready for your review yet',
                      description: processorName
                          ? `${processorName} is currently reviewing this request.`
                          : 'Waiting for a Loan Processor to pick this up.',
                  };
              }

              if (status === 'recommended_for_approval') {
                  return {
                      tone: 'ready' as const,
                      title: 'Ready for your decision',
                      description:
                          'Review the package below and Approve or Decline.',
                  };
              }

              return null;
          })()
        : null;

    const banking = (currentDataSections.banking ??
        {}) as LoanRequestBankingSectionValues;
    const bankingFacts = [
        ['Release method', banking.release_method],
        ['Repayment method', banking.payment_option],
        [
            'Account no.',
            banking.release_account_detail?.account_number ??
                banking.payment_account_detail?.account_number,
        ],
    ].flatMap(([label, value]) =>
        value ? [{ label: label as string, value: `${value}` }] : [],
    );
    const categoryCondition = currentConditions.available
        ? currentConditions.items.find(
              (item) => item.key === 'employer_category',
          )
        : undefined;
    // Conditions only gate while they can be signed off; when the table is
    // not deployed (or the viewer can't verify) approval is never blocked on them.
    const conditionsGate =
        currentConditions.available && currentConditions.can_verify
            ? {
                  done: currentConditions.items.filter((item) => item.verified)
                      .length,
                  total: currentConditions.items.length,
              }
            : null;
    const attention = buildAttentionRows({
        categoryMismatch: institutionalEmployerCategoryMismatch(
            currentApplicant?.institutional_employer_category,
            currentApplicant?.employer_business_name,
            currentApplicant?.employment_type,
            currentApplicant?.nature_of_business,
        ),
        categoryConfirmed:
            conditionsGate === null
                ? null
                : (categoryCondition?.verified ?? false),
        canEditCategory: showProcessingSection && canUpdateProcessing,
        documents: currentDocumentChecklist,
        health: currentWorkflowHealth,
        processingAgeTargetDays: PROCESSING_AGE_ISSUE_THRESHOLD_DAYS,
        memberAction:
            currentRequest.member_action_type !== null
                ? { message: currentRequest.member_action_message }
                : null,
        problemLoanMessage:
            currentRequest.applicant_loan_status?.requires_attention &&
            currentRequest.applicant_loan_status.problem_loans.length > 0
                ? (currentRequest.applicant_loan_status.warning_message ??
                  'Applicant has problematic loans')
                : null,
        managerStage: managerStageAlert,
    });
    const gates = buildRecommendGates({
        conditions: conditionsGate,
        blockingCount: attention.blockingCount,
        exceptionRowCount: attention.rows.filter((row) => row.tone !== 'info')
            .length,
        documents: currentDocumentChecklist,
        // Same scope as the server's document rule (v2 only).
        enforced: isV2Workflow,
    });
    const blockedReason = canRecommendApproval ? gates.blockedReason : null;
    const toggleCondition = async (key: string, verified: boolean) => {
        setConditionPendingKey(key);

        try {
            const result = await adminApi.updateLoanRequestCondition(
                currentRequest.id,
                key,
                verified,
            );

            setCurrentConditions((current) => ({
                ...current,
                ...result.conditions,
            }));
            setCurrentAuditTrail(result.auditTrail);
        } catch (error) {
            showErrorToast(error, 'Could not update the condition.');
        } finally {
            setConditionPendingKey(null);
        }
    };
    const railStage = [
        'pending_review',
        'under_review',
        'needs_revision',
        'awaiting_member_information',
    ].includes(currentRequest.status ?? '')
        ? ('processing' as const)
        : ['recommended_for_approval', 'awaiting_member_acceptance'].includes(
                currentRequest.status ?? '',
            )
          ? ('handed-off' as const)
          : ('other' as const);
    const scrollToSection = (id: string) =>
        document.getElementById(id)?.scrollIntoView({
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)')
                .matches
                ? 'auto'
                : 'smooth',
            block: 'start',
        });
    // Panels stay mounted while hidden, so the target exists as soon as the
    // tab is shown; scroll after that paint.
    const goToTab = (next: ReviewTabId, sectionId?: string) => {
        setTab(next);

        if (sectionId) {
            window.setTimeout(() => scrollToSection(sectionId), 60);
        }
    };
    // Cancelled keeps the step it was cancelled from (last transition into it).
    const cancelledFromStatus =
        currentRequest.status === 'cancelled'
            ? ([...currentAuditTrail]
                  .filter((entry) => entry.to_status === 'cancelled')
                  .sort((a, b) =>
                      (b.created_at ?? '').localeCompare(a.created_at ?? ''),
                  )[0]?.from_status ?? null)
            : null;

    const workflowProps: LoanRequestWorkflowProps = {
        claim:
            canClaim && !canStartReview
                ? {
                      show: true,
                      isProcessing: isWorkflowProcessing,
                      onSubmit: () => claimLoanRequest(currentRequest.id),
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
                      reassignLoanRequest(currentRequest.id, payload),
              }
            : undefined,
        returnToQueue: canReturnToQueue
            ? {
                  show: true,
                  isProcessing: isWorkflowProcessing,
                  onSubmit: (payload) =>
                      returnLoanRequestToQueue(currentRequest.id, payload),
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
                      approveLoanRequest(currentRequest.id, payload),
              }
            : undefined,
        decline: canWorkflowDecline
            ? {
                  show: true,
                  isProcessing: isWorkflowProcessing,
                  onSubmit: (payload) =>
                      declineLoanRequest(currentRequest.id, payload),
              }
            : undefined,
        ...processingWorkflowActions,
    };

    const documentResultsAlert =
        lastDocumentResults !== null ? (
            <Alert className="border-sky-500/30 bg-sky-500/10">
                <AlertTitle>Document generation results</AlertTitle>
                <AlertDescription>
                    <p>
                        {lastDocumentResults.length} document
                        {lastDocumentResults.length === 1 ? '' : 's'} refreshed
                        from the latest generation run.
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
        ) : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan request" />
            <LoanRequestDecisionHeader
                reference={currentRequest.reference}
                status={currentRequest.status}
                details={[
                    personName(currentApplicant),
                    displayText(currentRequest.loan_type_label_snapshot),
                    submittedAt
                        ? `Submitted ${submittedAt}`
                        : 'Not submitted yet',
                    assignedProcessorId === null
                        ? 'Unassigned'
                        : `Assigned to ${
                              currentRequest.assigned_processor?.name ??
                              currentRequest.assigned_officer?.name ??
                              'processor'
                          }`,
                ]}
                age={{
                    days: currentWorkflowHealth.processing_age_days,
                    targetDays: PROCESSING_AGE_ISSUE_THRESHOLD_DAYS,
                }}
                blockedNote={blockedReason}
                tabs={
                    <LoanRequestReviewTabs
                        tab={tab}
                        onSelect={setTab}
                        counts={{
                            documents: currentDocumentChecklist.filter(
                                (document) => document.is_applicable,
                            ).length,
                            activity: currentAuditTrail.length,
                        }}
                    />
                }
            >
                <LoanRequestWorkflowActions
                    layout="header"
                    loanRequest={currentRequest}
                    workflow={workflowProps}
                    memberAction={{
                        show: canRequestMemberAction,
                        onSelect: () => setIsMemberActionDialogOpen(true),
                    }}
                    recommendBlockedReason={blockedReason}
                />
            </LoanRequestDecisionHeader>
            <section className="mx-auto mt-6 mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <LoanRequestProgressCard
                    status={currentRequest.status}
                    previousStatus={cancelledFromStatus}
                />
            </section>
            <section className="mx-auto mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div className="min-w-0">
                        <ReviewTabPanel id="overview" tab={tab}>
                            <LoanRequestAttentionCard
                                rows={attention.rows}
                                blockingCount={attention.blockingCount}
                                loanStatus={
                                    currentRequest.applicant_loan_status
                                }
                                onAction={(key) => {
                                    if (key === 'edit-category') {
                                        setProcessingEditSignal((n) => n + 1);
                                        goToTab(
                                            'overview',
                                            'processing-details',
                                        );
                                    } else {
                                        goToTab(
                                            'documents',
                                            'document-checklist',
                                        );
                                    }
                                }}
                            />
                            {showProcessingSection ? (
                                <LoanRequestRecommendationSummary
                                    loanRequest={currentRequest}
                                    applicant={currentApplicant}
                                    processing={
                                        currentDataSections.processing ?? {}
                                    }
                                    preview={processingPreview}
                                    categoryConfirmation={
                                        categoryCondition
                                            ? {
                                                  verified:
                                                      categoryCondition.verified,
                                                  by: categoryCondition.verified_by,
                                              }
                                            : null
                                    }
                                />
                            ) : null}
                            <div className="grid grid-cols-[repeat(auto-fit,minmax(min(340px,100%),1fr))] items-start gap-4">
                                {editingSection === 'loan_request' ? (
                                    <LoanRequestSectionCard
                                        title="Loan Information"
                                        description="Update the verified request details used throughout the document package."
                                        className="col-span-full"
                                    >
                                        <form
                                            className="space-y-6"
                                            onSubmit={submitLoanInfoCorrection}
                                        >
                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="loan_info_typecode">
                                                        Loan type
                                                    </Label>
                                                    <Select
                                                        value={
                                                            loanInfoForm.typecode ||
                                                            undefined
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            updateLoanInfoType(
                                                                value,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            id="loan_info_typecode"
                                                            className="w-full"
                                                        >
                                                            <SelectValue placeholder="Select loan type" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {loanTypes.map(
                                                                (option) => (
                                                                    <SelectItem
                                                                        key={
                                                                            option.typecode
                                                                        }
                                                                        value={
                                                                            option.typecode
                                                                        }
                                                                    >
                                                                        {
                                                                            option.label
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                {isLoanInfoMicroBusinessLoan && (
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="loan_info_kind_of_loan">
                                                            Kind of loan
                                                        </Label>
                                                        <Select
                                                            value={
                                                                loanInfoForm.kind_of_loan ||
                                                                undefined
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                updateLoanInfoField(
                                                                    'kind_of_loan',
                                                                    value,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger
                                                                id="loan_info_kind_of_loan"
                                                                className="w-full"
                                                            >
                                                                <SelectValue placeholder="Select kind of loan" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {KIND_OF_LOAN_OPTIONS.map(
                                                                    (
                                                                        option,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                option
                                                                            }
                                                                            value={
                                                                                option
                                                                            }
                                                                        >
                                                                            {
                                                                                option
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                )}
                                                {isLoanInfoOtherLoan && (
                                                    <div className="grid gap-2 md:col-span-2">
                                                        <Label htmlFor="loan_info_other_loan_type_name">
                                                            Other loan type name
                                                        </Label>
                                                        <Input
                                                            id="loan_info_other_loan_type_name"
                                                            value={
                                                                loanInfoForm.other_loan_type_name
                                                            }
                                                            onChange={(event) =>
                                                                updateLoanInfoField(
                                                                    'other_loan_type_name',
                                                                    event.target
                                                                        .value,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                )}
                                                <div className="grid gap-2">
                                                    <Label htmlFor="loan_info_requested_amount">
                                                        Requested amount
                                                    </Label>
                                                    <CurrencyInput
                                                        id="loan_info_requested_amount"
                                                        value={
                                                            loanInfoForm.requested_amount
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            updateLoanInfoField(
                                                                'requested_amount',
                                                                value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="loan_info_requested_term">
                                                        Requested term
                                                    </Label>
                                                    <MonthsInput
                                                        id="loan_info_requested_term"
                                                        value={
                                                            loanInfoForm.requested_term
                                                        }
                                                        onChange={(value) =>
                                                            updateLoanInfoField(
                                                                'requested_term',
                                                                value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2 md:col-span-2">
                                                    <Label htmlFor="loan_info_loan_purpose">
                                                        Loan purpose
                                                    </Label>
                                                    <Input
                                                        id="loan_info_loan_purpose"
                                                        value={
                                                            loanInfoForm.loan_purpose
                                                        }
                                                        onChange={(event) =>
                                                            updateLoanInfoField(
                                                                'loan_purpose',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2 md:col-span-2">
                                                    <Label htmlFor="loan_info_availment_status">
                                                        Availment status
                                                    </Label>
                                                    <Input
                                                        id="loan_info_availment_status"
                                                        value={
                                                            loanInfoForm.availment_status
                                                        }
                                                        onChange={(event) =>
                                                            updateLoanInfoField(
                                                                'availment_status',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="loan_info_reason">
                                                    Reason for correction
                                                </Label>
                                                <textarea
                                                    id="loan_info_reason"
                                                    className={
                                                        textareaClassName
                                                    }
                                                    required
                                                    value={loanInfoReason}
                                                    onChange={(event) =>
                                                        setLoanInfoReason(
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="flex items-center justify-end gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={closeSectionEdit}
                                                    disabled={
                                                        isWorkflowProcessing
                                                    }
                                                >
                                                    Cancel
                                                </Button>
                                                <Button
                                                    type="submit"
                                                    disabled={
                                                        isWorkflowProcessing
                                                    }
                                                >
                                                    Save
                                                </Button>
                                            </div>
                                        </form>
                                    </LoanRequestSectionCard>
                                ) : (
                                    <div
                                        key="loan_request-display"
                                        className="animate-in duration-200 fade-in slide-in-from-top-2"
                                    >
                                        <LoanRequestLoanInformationCard
                                            loanRequest={currentRequest}
                                            extraFacts={bankingFacts}
                                            headerAction={
                                                canCorrectApplication ? (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className={
                                                            sectionEditButtonClassName
                                                        }
                                                        disabled={
                                                            isWorkflowProcessing
                                                        }
                                                        onClick={() =>
                                                            openSectionEdit(
                                                                'loan_request',
                                                            )
                                                        }
                                                    >
                                                        <Pencil />
                                                        Edit
                                                    </Button>
                                                ) : undefined
                                            }
                                        />
                                    </div>
                                )}
                                <LoanRequestApplicantSnapshot
                                    applicant={currentApplicant}
                                    onFullProfile={() => goToTab('applicant')}
                                />
                            </div>

                            {showProcessingSection ? (
                                <div
                                    id="processing-details"
                                    className="scroll-mt-40"
                                >
                                    <ProcessingDetailsPanel
                                        loanRequest={currentRequest}
                                        applicant={currentApplicant}
                                        dataSections={currentDataSections}
                                        dataSectionDefinitions={
                                            dataSectionDefinitions
                                        }
                                        cycleState={currentCycleState}
                                        canUpdateProcessing={
                                            canUpdateProcessing ||
                                            canCorrectProcessingPostApproval ||
                                            canWorkflowApprove
                                        }
                                        isProcessing={isWorkflowProcessing}
                                        updateProcessingDetails={
                                            updateProcessingDetails
                                        }
                                        loanManagers={loanManagers}
                                        saveError={
                                            workflowLastErrors[
                                                currentRequest.id
                                            ]?.action ===
                                            'updateProcessingDetails'
                                                ? workflowLastErrors[
                                                      currentRequest.id
                                                  ]
                                                : null
                                        }
                                        onDismissSaveError={() =>
                                            clearWorkflowLastError(
                                                currentRequest.id,
                                            )
                                        }
                                        onDocumentChecklistPreview={
                                            applyDocumentChecklistPreview
                                        }
                                        onPreviewChange={setProcessingPreview}
                                        openEditSignal={processingEditSignal}
                                    />
                                </div>
                            ) : null}
                            <LoanRequestConditionsCard
                                conditions={currentConditions}
                                pendingKey={conditionPendingKey}
                                onToggle={toggleCondition}
                            />
                        </ReviewTabPanel>
                        <ReviewTabPanel id="applicant" tab={tab}>
                            <div
                                id="applicant-details"
                                className="scroll-mt-40"
                            >
                                {editingSection === 'applicant' ? (
                                    <LoanRequestSectionCard
                                        title="Applicant"
                                        description="Correct verified applicant data when supported by the processing record."
                                    >
                                        <form
                                            className="space-y-6"
                                            onSubmit={submitApplicantCorrection}
                                        >
                                            <LoanRequestPersonalFields
                                                prefix="applicant"
                                                values={applicantForm}
                                                errors={{}}
                                                includeSpouse
                                                includeChildren
                                                portal={false}
                                                onChange={updateApplicantField}
                                            />
                                            <Separator className="bg-border/40" />
                                            <LoanRequestWorkFields
                                                prefix="applicant"
                                                values={applicantForm}
                                                errors={{}}
                                                portal={false}
                                                onChange={updateApplicantField}
                                            />
                                            <div className="grid gap-2">
                                                <Label htmlFor="applicant_reason">
                                                    Reason for correction
                                                </Label>
                                                <textarea
                                                    id="applicant_reason"
                                                    className={
                                                        textareaClassName
                                                    }
                                                    required
                                                    value={applicantReason}
                                                    onChange={(event) =>
                                                        setApplicantReason(
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="flex items-center justify-end gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={closeSectionEdit}
                                                    disabled={
                                                        isWorkflowProcessing
                                                    }
                                                >
                                                    Cancel
                                                </Button>
                                                <Button
                                                    type="submit"
                                                    disabled={
                                                        isWorkflowProcessing
                                                    }
                                                >
                                                    Save
                                                </Button>
                                            </div>
                                        </form>
                                    </LoanRequestSectionCard>
                                ) : (
                                    <div
                                        key="applicant-display"
                                        className="animate-in duration-200 fade-in slide-in-from-top-2"
                                    >
                                        <LoanRequestApplicantPanel
                                            applicant={currentApplicant}
                                            headerAction={
                                                canCorrectApplication ? (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className={
                                                            sectionEditButtonClassName
                                                        }
                                                        disabled={
                                                            isWorkflowProcessing
                                                        }
                                                        onClick={() =>
                                                            openSectionEdit(
                                                                'applicant',
                                                            )
                                                        }
                                                    >
                                                        <Pencil />
                                                        Edit
                                                    </Button>
                                                ) : undefined
                                            }
                                        />
                                    </div>
                                )}
                            </div>
                        </ReviewTabPanel>
                        <ReviewTabPanel id="co-makers" tab={tab}>
                            {editingSection === 'co_maker_1' ||
                            editingSection === 'co_maker_2' ? (
                                <LoanRequestSectionCard
                                    title={
                                        editingSection === 'co_maker_1'
                                            ? 'Co-maker 1'
                                            : 'Co-maker 2'
                                    }
                                    description="Update verified co-maker information when corrections are confirmed."
                                >
                                    <form
                                        className="space-y-6"
                                        onSubmit={
                                            editingSection === 'co_maker_1'
                                                ? submitCoMakerOneCorrection
                                                : submitCoMakerTwoCorrection
                                        }
                                    >
                                        <LoanRequestPersonalFields
                                            prefix={editingSection}
                                            values={
                                                editingSection === 'co_maker_1'
                                                    ? coMakerOneForm
                                                    : coMakerTwoForm
                                            }
                                            errors={{}}
                                            portal={false}
                                            onChange={
                                                editingSection === 'co_maker_1'
                                                    ? updateCoMakerOneField
                                                    : updateCoMakerTwoField
                                            }
                                        />
                                        <Separator className="bg-border/40" />
                                        <LoanRequestWorkFields
                                            prefix={editingSection}
                                            values={
                                                editingSection === 'co_maker_1'
                                                    ? coMakerOneForm
                                                    : coMakerTwoForm
                                            }
                                            errors={{}}
                                            portal={false}
                                            onChange={
                                                editingSection === 'co_maker_1'
                                                    ? updateCoMakerOneField
                                                    : updateCoMakerTwoField
                                            }
                                        />
                                        <div className="grid gap-2">
                                            <Label htmlFor="co_maker_reason">
                                                Reason for correction
                                            </Label>
                                            <textarea
                                                id="co_maker_reason"
                                                className={textareaClassName}
                                                required
                                                value={
                                                    editingSection ===
                                                    'co_maker_1'
                                                        ? coMakerOneReason
                                                        : coMakerTwoReason
                                                }
                                                onChange={(event) =>
                                                    (editingSection ===
                                                        'co_maker_1'
                                                        ? setCoMakerOneReason
                                                        : setCoMakerTwoReason)(
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                        <div className="flex items-center justify-end gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={closeSectionEdit}
                                                disabled={isWorkflowProcessing}
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={isWorkflowProcessing}
                                            >
                                                Save
                                            </Button>
                                        </div>
                                    </form>
                                </LoanRequestSectionCard>
                            ) : (
                                <div
                                    key="co-makers-display"
                                    className="animate-in duration-200 fade-in slide-in-from-top-2"
                                >
                                    <div className="space-y-4">
                                        <LoanRequestCoMakerCard
                                            number={1}
                                            person={currentCoMakerOne}
                                            action={
                                                canCorrectApplication ? (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className={
                                                            sectionEditButtonClassName
                                                        }
                                                        disabled={
                                                            isWorkflowProcessing
                                                        }
                                                        onClick={() =>
                                                            openSectionEdit(
                                                                'co_maker_1',
                                                            )
                                                        }
                                                    >
                                                        <Pencil />
                                                        Edit
                                                    </Button>
                                                ) : undefined
                                            }
                                        />
                                        <LoanRequestCoMakerCard
                                            number={2}
                                            person={currentCoMakerTwo}
                                            action={
                                                canCorrectApplication ? (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className={
                                                            sectionEditButtonClassName
                                                        }
                                                        disabled={
                                                            isWorkflowProcessing
                                                        }
                                                        onClick={() =>
                                                            openSectionEdit(
                                                                'co_maker_2',
                                                            )
                                                        }
                                                    >
                                                        <Pencil />
                                                        Edit
                                                    </Button>
                                                ) : undefined
                                            }
                                        />
                                    </div>
                                </div>
                            )}
                        </ReviewTabPanel>
                        <ReviewTabPanel id="documents" tab={tab}>
                            {documentResultsAlert}
                            <div
                                id="document-checklist"
                                className="scroll-mt-40"
                            >
                                <LoanRequestDocumentChecklistCard
                                    documentChecklist={currentDocumentChecklist}
                                    generatedDocumentBaseHref={`/staff/loan-requests/${currentRequest.id}/documents/generated`}
                                    canGenerateDocuments={canGenerateDocuments}
                                    isProcessing={isWorkflowProcessing}
                                    onGenerate={(
                                        documentKeys,
                                        onDocumentSettled,
                                    ) =>
                                        submitGenerateSelectedDocuments(
                                            documentKeys,
                                            onDocumentSettled,
                                        )
                                    }
                                    onRegenerate={async (documentKey) => {
                                        await submitGenerateDocuments(
                                            documentKey as LoanRequestDocumentKey,
                                        );
                                    }}
                                    packageZipDownload={packageZipDownload}
                                    lockFinalizedDocuments={[
                                        'approved',
                                        'converted_to_loan',
                                    ].includes(currentRequest.status ?? '')}
                                    processingDetailsSaved={
                                        !currentRequest.is_first_processing_save
                                    }
                                />
                            </div>
                            {showWibsTrackingSection ? (
                                <LoanRequestSectionCard
                                    title="WIBS Tracking"
                                    description="Official loan tracking in the WIBS system."
                                    icon={Truck}
                                    className="border-border bg-card shadow-card"
                                    contentClassName="space-y-4"
                                >
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

                                    {currentRequest.status ===
                                    'converted_to_loan' ? (
                                        <div className="space-y-2">
                                            <p className="text-sm text-muted-foreground">
                                                Forward this loan to WIBS for
                                                encoding.
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
                                                            setIsWibsSubmitting(
                                                                false,
                                                            ),
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
                                                        setWibsReference(
                                                            e.target.value,
                                                        )
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
                                                            setIsWibsSubmitting(
                                                                false,
                                                            ),
                                                    },
                                                );
                                            }}
                                        >
                                            <div className="space-y-1">
                                                <Label htmlFor="wibs_release_date">
                                                    Release Date
                                                </Label>
                                                <DateInputWithPicker
                                                    id="wibs_release_date"
                                                    name="wibs_release_date"
                                                    value={wibsReleaseDate}
                                                    onChange={
                                                        setWibsReleaseDate
                                                    }
                                                    required
                                                    aria-label="Choose release date"
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
                                                Confirm that the loan has been
                                                released to the member.
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
                                </LoanRequestSectionCard>
                            ) : null}
                        </ReviewTabPanel>
                        <ReviewTabPanel id="activity" tab={tab}>
                            <LoanRequestActivityTab
                                auditTrail={currentAuditTrail}
                                notifications={currentNotificationHistory}
                            />
                        </ReviewTabPanel>
                    </div>
                    <aside className="min-w-0 space-y-4">
                        <LoanRequestReadyCard
                            stage={railStage}
                            gated={isV2Workflow}
                            gates={gates}
                        />
                        <LoanRequestStatusRailCard
                            status={currentRequest.status}
                            isV2={isV2Workflow}
                            assignedTo={
                                assignedProcessorId === null
                                    ? null
                                    : (currentRequest.assigned_processor
                                          ?.name ??
                                      currentRequest.assigned_officer?.name ??
                                      'Assigned processor')
                            }
                            ageDays={currentWorkflowHealth.processing_age_days}
                            ageTargetDays={PROCESSING_AGE_ISSUE_THRESHOLD_DAYS}
                            notificationsSent={
                                currentNotificationHistory.filter(
                                    (event) => event.status === 'sent',
                                ).length
                            }
                            pdfHref={
                                ['submitted', 'declined', 'cancelled'].includes(
                                    currentRequest.status ?? '',
                                )
                                    ? pdfHref
                                    : null
                            }
                        />
                        <LoanRequestHealthCard
                            issueCount={workflowHealthIssueCount}
                            ageDays={currentWorkflowHealth.processing_age_days}
                            ageIssue={workflowHealthIssues.processingAge}
                            pendingMemberAction={
                                currentWorkflowHealth.pending_member_action
                            }
                        />
                    </aside>
                </div>
            </section>

            {/* Keeps the mobile action bar from covering the last card. */}
            <div aria-hidden className="h-24 sm:hidden" />

            <Sheet
                open={isMemberActionDialogOpen}
                onOpenChange={setIsMemberActionDialogOpen}
            >
                <SheetContent
                    side="right"
                    className="flex w-full flex-col gap-0 sm:max-w-xl"
                >
                    <SheetHeader>
                        <SheetTitle>Request Member Action</SheetTitle>
                        <SheetDescription>
                            Ask the member for a correction or for additional
                            information, and record exactly which fields need
                            their attention.
                        </SheetDescription>
                    </SheetHeader>
                    <form
                        className="flex flex-1 flex-col space-y-5 overflow-hidden px-4"
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
                        <div className="flex flex-1 flex-col space-y-3 overflow-hidden pb-4">
                            <p className="text-sm font-medium">
                                Fields requiring member action
                            </p>
                            <Input
                                type="text"
                                placeholder="Search fields…"
                                value={memberFieldSearch}
                                onChange={(event) =>
                                    setMemberFieldSearch(event.target.value)
                                }
                            />
                            <div className="flex-1 overflow-y-auto">
                                <Accordion
                                    type="multiple"
                                    value={Array.from(
                                        new Set([
                                            ...openMemberFieldGroups,
                                            ...visibleMemberFieldGroups
                                                .filter(
                                                    (group) =>
                                                        memberFieldSearchQuery !==
                                                            '' ||
                                                        group.items.some(
                                                            (item) =>
                                                                selectedMemberFields.includes(
                                                                    item.fieldKey,
                                                                ),
                                                        ),
                                                )
                                                .map(
                                                    (group) => group.sectionKey,
                                                ),
                                        ]),
                                    )}
                                    onValueChange={setOpenMemberFieldGroups}
                                >
                                    {visibleMemberFieldGroups.map((group) => {
                                        const selectedCount =
                                            group.items.filter((item) =>
                                                selectedMemberFields.includes(
                                                    item.fieldKey,
                                                ),
                                            ).length;

                                        return (
                                            <AccordionItem
                                                key={group.sectionKey}
                                                value={group.sectionKey}
                                            >
                                                <AccordionTrigger className="text-xs font-semibold tracking-wide text-muted-foreground uppercase hover:no-underline">
                                                    <span className="flex items-center gap-2">
                                                        {group.label}
                                                        {selectedCount > 0 ? (
                                                            <Badge variant="secondary">
                                                                {selectedCount}{' '}
                                                                selected
                                                            </Badge>
                                                        ) : null}
                                                    </span>
                                                </AccordionTrigger>
                                                <AccordionContent>
                                                    <div className="grid gap-3 md:grid-cols-2">
                                                        {group.items.map(
                                                            (item) => (
                                                                <label
                                                                    key={
                                                                        item.fieldKey
                                                                    }
                                                                    className="flex items-start gap-3 rounded-lg border border-border bg-muted/10 p-3 text-sm"
                                                                >
                                                                    <Checkbox
                                                                        checked={selectedMemberFields.includes(
                                                                            item.fieldKey,
                                                                        )}
                                                                        onCheckedChange={(
                                                                            checked,
                                                                        ) =>
                                                                            setSelectedMemberFields(
                                                                                (
                                                                                    current,
                                                                                ) =>
                                                                                    checked ===
                                                                                    true
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
                                                                    <span>
                                                                        {
                                                                            item
                                                                                .field
                                                                                .label
                                                                        }
                                                                    </span>
                                                                </label>
                                                            ),
                                                        )}
                                                    </div>
                                                </AccordionContent>
                                            </AccordionItem>
                                        );
                                    })}
                                </Accordion>
                            </div>
                        </div>
                        <SheetFooter className="px-0">
                            <Button
                                type="submit"
                                disabled={isWorkflowProcessing}
                            >
                                Send Member Action Request
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    setIsMemberActionDialogOpen(false)
                                }
                            >
                                Cancel
                            </Button>
                        </SheetFooter>
                    </form>
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
