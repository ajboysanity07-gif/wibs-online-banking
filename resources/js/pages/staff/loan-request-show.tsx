import { Head, router, usePage } from '@inertiajs/react';
import { Bell, CheckCircle2, Clock, HeartPulse } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
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
import { LoanRequestDocumentChecklistCard } from '@/components/loan-request/loan-request-document-checklist-card';
import {
    LoanRequestPersonalFields,
    LoanRequestWorkFields,
} from '@/components/loan-request/loan-request-fields';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import { LoanStatusWarning } from '@/components/loan-request/loan-status-warning';
import { MonthsInput } from '@/components/loan-request/numeric-adorned-inputs';
import {
    ProcessingDetailsPanel,
    textareaClassName,
    toStringValue,
} from '@/components/loan-request/processing-details-panel';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useLoanRequestWorkflow } from '@/hooks/admin/use-loan-request-workflow';
import { useApprovedDocumentPackageDownload } from '@/hooks/loan-request/use-approved-document-package-download';
import AppLayout from '@/layouts/app-layout';
import { adminApi } from '@/lib/api/admin';
import { staffApprovedDocumentPackageApi } from '@/lib/api/approved-document-package';
import { formatDate, formatDateTime } from '@/lib/formatters';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import {
    approvedDocuments as requestsApprovedDocuments,
    index as requestsIndex,
    pdf as requestsPdf,
    show as requestsShow,
} from '@/routes/staff/loan-requests';
import {
    affidavitUndertaking as requestsAffidavitUndertakingDocument,
    applicationForm as requestsApplicationFormDocument,
    authorityToDeduct as requestsAuthorityToDeductDocument,
    authorization as requestsAuthorizationDocument,
    depedSalaryDeductionWaiver as requestsDepedSalaryDeductionWaiverDocument,
    disclosureStatement as requestsDisclosureStatementDocument,
    generali as requestsGeneraliDocument,
    generaliApplicationForm as requestsGeneraliApplicationFormDocument,
    grepalife as requestsGrepalifeDocument,
    loanInformation as requestsLoanInformationDocument,
    loanSecurityAgreement as requestsLoanSecurityAgreementDocument,
    pensionDeductionWaiver as requestsPensionDeductionWaiverDocument,
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
    LoanManagerOption,
    LoanRequestAuditEntry,
    LoanRequestAssignmentOfficerOption,
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
} from '@/types/loan-requests';

type Props = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    auditTrail: LoanRequestAuditEntry[];
    eligibleOfficers: LoanRequestAssignmentOfficerOption[];
    loanManagers: LoanManagerOption[];
    dataSections: LoanRequestDataSections;
    dataSectionDefinitions: LoanRequestDataSectionDefinitions;
    cycleState: LoanRequestCycleState;
    documentChecklist: LoanRequestDocumentChecklistItem[];
    memberAction: LoanRequestMemberAction;
    notificationHistory: LoanRequestNotificationHistoryItem[];
    workflowPermissions: LoanRequestWorkflowPermission[];
    workflowContext: LoanRequestWorkflowContext;
    workflowHealth: LoanRequestWorkflowHealth;
};

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
    spouse_age: '',
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
        address2: person.address2 ?? '',
        address3: person.address3 ?? '',
        address_zip: person.address_zip ?? '',
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
        employer_business_address_zip:
            person.employer_business_address_zip ?? '',
        telephone_no: person.telephone_no ?? '',
        current_position: person.current_position ?? '',
        nature_of_business: person.nature_of_business ?? '',
        years_in_work_business: person.years_in_work_business ?? '',
        employer_date_employed: person.employer_date_employed ?? '',
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
};

export default function StaffLoanRequestShow({
    loanRequest,
    applicant,
    coMakerOne,
    coMakerTwo,
    auditTrail,
    eligibleOfficers,
    loanManagers,
    dataSections,
    dataSectionDefinitions,
    cycleState,
    documentChecklist,
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
    const [currentEligibleOfficers, setCurrentEligibleOfficers] =
        useState<LoanRequestAssignmentOfficerOption[]>(eligibleOfficers);
    const [currentDataSections, setCurrentDataSections] =
        useState<LoanRequestDataSections>(dataSections);
    const [currentCycleState, setCurrentCycleState] =
        useState<LoanRequestCycleState>(cycleState);
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
    const [isPayoutDetailsDialogOpen, setIsPayoutDetailsDialogOpen] =
        useState(false);
    const [payoutPaymentOption, setPayoutPaymentOption] = useState('');
    const [payoutAtmNumber, setPayoutAtmNumber] = useState('');
    const [payoutAtmHolderName, setPayoutAtmHolderName] = useState('');
    const [payoutPaymentBankName, setPayoutPaymentBankName] = useState('');
    const [payoutPaymentAccountName, setPayoutPaymentAccountName] =
        useState('');
    const [payoutPaymentAccountNumber, setPayoutPaymentAccountNumber] =
        useState('');
    const [payoutPaymentAccountType, setPayoutPaymentAccountType] =
        useState('');
    const [payoutPaymentAtmNumber, setPayoutPaymentAtmNumber] = useState('');
    const [payoutPaymentBankBranch, setPayoutPaymentBankBranch] = useState('');
    const [payoutPaymentAtmHolderName, setPayoutPaymentAtmHolderName] =
        useState('');
    const [payoutReason, setPayoutReason] = useState('');
    const [selectedMemberFields, setSelectedMemberFields] = useState<string[]>(
        [],
    );
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
        updatePayoutDetails,
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
                  generali: requestsGeneraliDocument(currentRequest.id).url,
                  authorityToDeduct: requestsAuthorityToDeductDocument(
                      currentRequest.id,
                  ).url,
                  depedSalaryDeductionWaiver:
                      requestsDepedSalaryDeductionWaiverDocument(
                          currentRequest.id,
                      ).url,
                  pensionDeductionWaiver:
                      requestsPensionDeductionWaiverDocument(currentRequest.id)
                          .url,
                  generaliApplicationForm:
                      requestsGeneraliApplicationFormDocument(currentRequest.id)
                          .url,
                  authorization: requestsAuthorizationDocument(
                      currentRequest.id,
                  ).url,
                  packageZip: requestsApprovedDocuments(currentRequest.id).url,
              }
            : null;

    const [correctionSource, setCorrectionSource] = useState({
        request: currentRequest,
        applicant: currentApplicant,
        coMakerOne: currentCoMakerOne,
        coMakerTwo: currentCoMakerTwo,
    });

    if (
        correctionSource.request !== currentRequest ||
        correctionSource.applicant !== currentApplicant ||
        correctionSource.coMakerOne !== currentCoMakerOne ||
        correctionSource.coMakerTwo !== currentCoMakerTwo
    ) {
        setCorrectionSource({
            request: currentRequest,
            applicant: currentApplicant,
            coMakerOne: currentCoMakerOne,
            coMakerTwo: currentCoMakerTwo,
        });
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
        });
    }

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
    const canUpdatePayoutDetails = canUpdateProcessing;
    const isProcessingStage = [
        'pending_review',
        'under_review',
        'needs_revision',
        'awaiting_member_information',
        'recommended_for_approval',
        'awaiting_member_acceptance',
    ].includes(currentRequest.status ?? '');
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
        // "Health declarations" concept.
        const sectionKey =
            item.sectionKey === 'health' ? 'health_glapi' : item.sectionKey;
        const existing = memberFieldGroupsMap.get(sectionKey) ?? [];
        existing.push(item);
        memberFieldGroupsMap.set(sectionKey, existing);
    });

    const memberFieldPriorityOrder = [
        'insurance',
        'health_glapi',
        'banking',
        'barangay',
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
        label: dataSectionDefinitions[sectionKey]?.label ?? sectionKey,
        items: memberFieldGroupsMap.get(sectionKey) ?? [],
    }));

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

    const submitCorrectionData = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const result = await updateProcessingDetails(currentRequest.id, {
            reason: correctionForm.reason,
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

    const openPayoutDetailsDialog = () => {
        const bankingSection = dataSections.banking ?? {};
        setPayoutPaymentOption(String(bankingSection.payment_option ?? ''));
        setPayoutAtmNumber(String(bankingSection.payout_atm_number ?? ''));
        setPayoutAtmHolderName(
            String(bankingSection.payout_atm_holder_name ?? ''),
        );
        setPayoutPaymentBankName(
            String(bankingSection.payment_bank_name ?? ''),
        );
        setPayoutPaymentAccountName(
            String(bankingSection.payment_account_name ?? ''),
        );
        setPayoutPaymentAccountNumber(
            String(bankingSection.payment_account_number ?? ''),
        );
        setPayoutPaymentAccountType(
            String(bankingSection.payment_account_type ?? ''),
        );
        setPayoutPaymentAtmNumber(
            String(bankingSection.payment_atm_number ?? ''),
        );
        setPayoutPaymentBankBranch(
            String(bankingSection.payment_bank_branch ?? ''),
        );
        setPayoutPaymentAtmHolderName(
            String(bankingSection.payment_atm_holder_name ?? ''),
        );
        setPayoutReason('');
        setIsPayoutDetailsDialogOpen(true);
    };

    const submitPayoutDetails = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const result = await updatePayoutDetails(currentRequest.id, {
            payment_option: payoutPaymentOption,
            payout_atm_number: payoutAtmNumber || null,
            payout_atm_holder_name: payoutAtmHolderName || null,
            payment_bank_name: payoutPaymentBankName || null,
            payment_account_name: payoutPaymentAccountName || null,
            payment_account_number: payoutPaymentAccountNumber || null,
            payment_account_type: payoutPaymentAccountType || null,
            payment_atm_number: payoutPaymentAtmNumber || null,
            payment_bank_branch: payoutPaymentBankBranch || null,
            payment_atm_holder_name: payoutPaymentAtmHolderName || null,
            reason: payoutReason,
        });

        if (result) {
            setIsPayoutDetailsDialogOpen(false);
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
                        {currentRequest.member_action_requested_by ? (
                            <p className="mt-1 text-xs text-muted-foreground">
                                Requested by{' '}
                                {currentRequest.member_action_requested_by.name}
                            </p>
                        ) : null}
                    </AlertDescription>
                </Alert>
            ) : null}
            {canUpdateProcessing || canManagerCorrect ? (
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
            {canUpdatePayoutDetails ? (
                <Button
                    type="button"
                    variant="outline"
                    className="w-full justify-start"
                    disabled={isWorkflowProcessing}
                    onClick={openPayoutDetailsDialog}
                >
                    Update Payout & Repayment Details
                </Button>
            ) : null}
        </>
    );

    const sidebarFooterContent = (
        <>
            <LoanRequestAuditTrail
                entries={currentAuditTrail}
                audience="staff"
                compact
            />
            <Card className={readOnlyCardClassName}>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <HeartPulse className="size-4 text-muted-foreground" />
                        Workflow health
                    </CardTitle>
                </CardHeader>
                <CardContent className="grid grid-cols-2 gap-x-6 gap-y-4">
                    <div
                        className={`col-span-2 rounded-lg border px-3 py-2 text-sm font-medium ${
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
                            {currentWorkflowHealth.processing_age_days === null
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
                            {currentWorkflowHealth.failed_document_count}
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
                            {currentWorkflowHealth.notification_failure_count}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs tracking-[0.18em] text-muted-foreground uppercase">
                            Workflow failed jobs
                        </p>
                        <p
                            className={`mt-2 text-2xl ${workflowHealthIssues.workflowFailedJobs ? 'font-bold text-rose-600 dark:text-rose-400' : 'font-semibold'}`}
                        >
                            {currentWorkflowHealth.workflow_failed_job_count}
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
                                                {event.status ?? 'unknown'}
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
                                        <p>Attempts: {event.attempt_count}</p>
                                        <p>Retries: {event.retry_count}</p>
                                        <p>
                                            Reminders: {event.reminder_attempts}
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
                <LoanStatusWarning
                    loanStatus={currentRequest.applicant_loan_status}
                    className="mt-4"
                />
                {managerStageAlert ? (
                    <Alert
                        className={`mt-4 border-2 shadow-sm ${
                            managerStageAlert.tone === 'ready'
                                ? 'border-emerald-500/40 bg-emerald-500/10 text-foreground'
                                : 'border-amber-500/40 bg-amber-500/10 text-foreground'
                        }`}
                    >
                        {managerStageAlert.tone === 'ready' ? (
                            <CheckCircle2 className="size-4 text-emerald-700 dark:text-emerald-300" />
                        ) : (
                            <Clock className="size-4 text-amber-700 dark:text-amber-200" />
                        )}
                        <AlertTitle className="line-clamp-none text-base font-semibold">
                            {managerStageAlert.title}
                        </AlertTitle>
                        <AlertDescription className="text-foreground/90">
                            {managerStageAlert.description}
                        </AlertDescription>
                    </Alert>
                ) : null}
            </section>
            <section className="mx-auto mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div className="space-y-6">
                        <LoanRequestApplicantCard
                            applicant={currentApplicant}
                        />
                        <LoanRequestCoMakersCard
                            coMakerOne={currentCoMakerOne}
                            coMakerTwo={currentCoMakerTwo}
                        />
                        {showProcessingSection ? (
                            <ProcessingDetailsPanel
                                loanRequest={currentRequest}
                                applicant={currentApplicant}
                                dataSections={currentDataSections}
                                dataSectionDefinitions={dataSectionDefinitions}
                                cycleState={currentCycleState}
                                canUpdateProcessing={canUpdateProcessing}
                                isProcessing={isWorkflowProcessing}
                                updateProcessingDetails={
                                    updateProcessingDetails
                                }
                                loanManagers={loanManagers}
                            />
                        ) : null}
                        <LoanRequestDocumentChecklistCard
                            documentChecklist={currentDocumentChecklist}
                            generatedDocumentBaseHref={`/staff/loan-requests/${currentRequest.id}/documents/generated`}
                            canGenerateDocuments={canGenerateDocuments}
                            isProcessing={isWorkflowProcessing}
                            onGenerate={(documentKeys, onDocumentSettled) =>
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
                        {showWibsTrackingSection ? (
                            <Card className="border-border/30 bg-card/70 shadow-sm">
                                <CardHeader>
                                    <CardTitle>WIBS Tracking</CardTitle>
                                    <CardDescription>
                                        Official loan tracking in the WIBS
                                        system.
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
                            documentChecklistAvailable={isProcessingStage}
                            showApprovedDocumentList={false}
                            approvedDocumentHrefs={approvedDocumentHrefs}
                            auditTrail={currentAuditTrail}
                            auditTrailAudience="staff"
                            workflow={{
                                claim:
                                    canClaim && !canStartReview
                                        ? {
                                              show: true,
                                              isProcessing:
                                                  isWorkflowProcessing,
                                              onSubmit: () =>
                                                  claimLoanRequest(
                                                      currentRequest.id,
                                                  ),
                                          }
                                        : undefined,
                                assign: canAssign
                                    ? {
                                          show: true,
                                          isProcessing: isWorkflowProcessing,
                                          officerOptions:
                                              currentEligibleOfficers,
                                          onSubmit: (payload) =>
                                              assignLoanRequest(
                                                  currentRequest.id,
                                                  payload,
                                              ),
                                      }
                                    : undefined,
                                reassign: canReassign
                                    ? {
                                          show: true,
                                          isProcessing: isWorkflowProcessing,
                                          officerOptions:
                                              currentEligibleOfficers,
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
                                              startReview(
                                                  currentRequest.id,
                                                  payload,
                                              ),
                                      }
                                    : undefined,
                                requestRevision: canRequestRevision
                                    ? {
                                          show: true,
                                          isProcessing: isWorkflowProcessing,
                                          onSubmit: (payload) =>
                                              requestRevision(
                                                  currentRequest.id,
                                                  payload,
                                              ),
                                      }
                                    : undefined,
                                reject: canReject
                                    ? {
                                          show: true,
                                          isProcessing: isWorkflowProcessing,
                                          onSubmit: (payload) =>
                                              rejectLoanRequest(
                                                  currentRequest.id,
                                                  payload,
                                              ),
                                      }
                                    : undefined,
                                recommendApproval: canRecommendApproval
                                    ? {
                                          show: true,
                                          isProcessing: isWorkflowProcessing,
                                          onSubmit: (payload) =>
                                              recommendApproval(
                                                  currentRequest.id,
                                                  payload,
                                              ),
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
                            sidebarFooter={sidebarFooterContent}
                        />
                    </div>
                </div>
            </section>

            <Sheet
                open={isCorrectionDialogOpen}
                onOpenChange={setIsCorrectionDialogOpen}
            >
                <SheetContent side="right" className="sm:max-w-2xl">
                    <SheetHeader>
                        <SheetTitle>Correct Application Data</SheetTitle>
                        <SheetDescription>
                            Correct the member-supplied request, applicant, and
                            co-maker details when supported by a record. Provide
                            a reason and information source for the audit trail.
                        </SheetDescription>
                    </SheetHeader>
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
                                        <MonthsInput
                                            id="correction_requested_term"
                                            value={
                                                correctionForm.loan_request
                                                    .requested_term
                                            }
                                            onChange={(value) =>
                                                updateCorrectionDetailField(
                                                    'requested_term',
                                                    value,
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
                                        portal={false}
                                        onChange={updateCorrectionPersonField(
                                            'applicant',
                                        )}
                                    />
                                    <Separator className="bg-border/40" />
                                    <LoanRequestWorkFields
                                        prefix="applicant"
                                        values={correctionForm.applicant}
                                        errors={{}}
                                        portal={false}
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
                                        portal={false}
                                        onChange={updateCorrectionPersonField(
                                            'co_maker_1',
                                        )}
                                    />
                                    <Separator className="bg-border/40" />
                                    <LoanRequestWorkFields
                                        prefix="co_maker_1"
                                        values={correctionForm.co_maker_1}
                                        errors={{}}
                                        portal={false}
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
                                        portal={false}
                                        onChange={updateCorrectionPersonField(
                                            'co_maker_2',
                                        )}
                                    />
                                    <Separator className="bg-border/40" />
                                    <LoanRequestWorkFields
                                        prefix="co_maker_2"
                                        values={correctionForm.co_maker_2}
                                        errors={{}}
                                        portal={false}
                                        onChange={updateCorrectionPersonField(
                                            'co_maker_2',
                                        )}
                                    />
                                </div>
                            </LoanRequestSectionCard>

                            <Separator className="bg-border/40" />

                            <div className="grid gap-2">
                                <Label htmlFor="correction_reason">
                                    Remarks
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

                            <div className="flex items-center justify-end gap-2">
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
                            </div>
                        </div>
                    </form>
                </SheetContent>
            </Sheet>

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

            <Dialog
                open={isPayoutDetailsDialogOpen}
                onOpenChange={setIsPayoutDetailsDialogOpen}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            Update Payout & Repayment Details
                        </DialogTitle>
                        <DialogDescription>
                            Update the member&apos;s payout/ATM details and
                            repayment method directly on their behalf (e.g. when
                            they let you know by phone or in person). The member
                            will be notified of this change; it does not require
                            them to log back in.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-5" onSubmit={submitPayoutDetails}>
                        <div className="grid gap-2">
                            <Label htmlFor="payout_payment_option">
                                Repayment method
                            </Label>
                            <select
                                id="payout_payment_option"
                                className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm"
                                required
                                value={payoutPaymentOption}
                                onChange={(event) =>
                                    setPayoutPaymentOption(event.target.value)
                                }
                            >
                                <option value="" disabled>
                                    Select repayment method
                                </option>
                                <option value="Salary Deduction">
                                    Salary Deduction
                                </option>
                                <option value="ATM Deduction">
                                    ATM Deduction
                                </option>
                                <option value="Check">Check</option>
                                <option value="Cash">Cash</option>
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="payout_atm_number">
                                Payout ATM card number
                            </Label>
                            <Input
                                id="payout_atm_number"
                                value={payoutAtmNumber}
                                required={
                                    payoutPaymentOption === 'ATM Deduction'
                                }
                                onChange={(event) =>
                                    setPayoutAtmNumber(event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="payout_atm_holder_name">
                                Payout ATM card holder name (if not the
                                borrower)
                            </Label>
                            <Input
                                id="payout_atm_holder_name"
                                value={payoutAtmHolderName}
                                onChange={(event) =>
                                    setPayoutAtmHolderName(event.target.value)
                                }
                            />
                        </div>
                        {payoutPaymentOption === 'ATM Deduction' ? (
                            <div className="grid gap-4 rounded-md border border-input p-4">
                                <p className="text-sm font-medium">
                                    Repayment account details
                                </p>
                                <div className="grid gap-2">
                                    <Label htmlFor="payment_bank_name">
                                        Repayment bank name
                                    </Label>
                                    <Input
                                        id="payment_bank_name"
                                        value={payoutPaymentBankName}
                                        required
                                        onChange={(event) =>
                                            setPayoutPaymentBankName(
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="payment_account_name">
                                        Repayment account name
                                    </Label>
                                    <Input
                                        id="payment_account_name"
                                        value={payoutPaymentAccountName}
                                        required
                                        onChange={(event) =>
                                            setPayoutPaymentAccountName(
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="payment_account_number">
                                        Repayment account number
                                    </Label>
                                    <Input
                                        id="payment_account_number"
                                        value={payoutPaymentAccountNumber}
                                        required
                                        onChange={(event) =>
                                            setPayoutPaymentAccountNumber(
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="payment_account_type">
                                        Repayment account type
                                    </Label>
                                    <Input
                                        id="payment_account_type"
                                        value={payoutPaymentAccountType}
                                        required
                                        onChange={(event) =>
                                            setPayoutPaymentAccountType(
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="payment_atm_number">
                                        Repayment ATM card number
                                    </Label>
                                    <Input
                                        id="payment_atm_number"
                                        value={payoutPaymentAtmNumber}
                                        required
                                        onChange={(event) =>
                                            setPayoutPaymentAtmNumber(
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="payment_bank_branch">
                                        Repayment bank branch
                                    </Label>
                                    <Input
                                        id="payment_bank_branch"
                                        value={payoutPaymentBankBranch}
                                        onChange={(event) =>
                                            setPayoutPaymentBankBranch(
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="payment_atm_holder_name">
                                        Repayment ATM card holder name (if not
                                        the borrower)
                                    </Label>
                                    <Input
                                        id="payment_atm_holder_name"
                                        value={payoutPaymentAtmHolderName}
                                        onChange={(event) =>
                                            setPayoutPaymentAtmHolderName(
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                        ) : null}
                        <div className="grid gap-2">
                            <Label htmlFor="payout_reason">
                                Internal reason
                            </Label>
                            <textarea
                                id="payout_reason"
                                className={textareaClassName}
                                rows={3}
                                required
                                value={payoutReason}
                                onChange={(event) =>
                                    setPayoutReason(event.target.value)
                                }
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    setIsPayoutDetailsDialogOpen(false)
                                }
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={isWorkflowProcessing}
                            >
                                Save Payout Details
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
