import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import {
    LoanRequestPersonalFields,
    LoanRequestWorkFields,
} from '@/components/loan-request/loan-request-fields';
import { LoanRequestDetailPage } from '@/components/loan-request/loan-request-detail-page';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import { useLoanRequestWorkflow } from '@/hooks/admin/use-loan-request-workflow';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@/components/ui/alert';
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
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/formatters';
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
    return {
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
    }[status] ?? 'border-border/60 bg-muted/20 text-muted-foreground';
};

const displayNotificationStatusTone = (status: string | null): string => {
    return {
        queued: 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-200',
        sending:
            'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-200',
        sent: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-200',
        failed:
            'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-200',
        skipped:
            'border-border/60 bg-muted/20 text-muted-foreground dark:text-muted-foreground',
    }[status ?? ''] ?? 'border-border/60 bg-muted/20 text-muted-foreground';
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

type ProcessingFormState = {
    loan_request: {
        requested_amount: string;
        requested_term: string;
        loan_purpose: string;
        availment_status: string;
    };
    applicant: LoanRequestPersonFormData;
    co_maker_1: LoanRequestPersonFormData;
    co_maker_2: LoanRequestPersonFormData;
    processing: Record<string, string | number | boolean | null>;
    recommended_amount: string;
    recommended_term: string;
    recommended_interest_rate: string;
    recommended_payment_frequency: string;
    recommendation_remarks: string;
    reason: string;
    information_source: string;
};

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
    const [isProcessingDialogOpen, setIsProcessingDialogOpen] =
        useState(false);
    const [isMemberActionDialogOpen, setIsMemberActionDialogOpen] =
        useState(false);
    const [isRejectDuringProcessingOpen, setIsRejectDuringProcessingOpen] =
        useState(false);
    const [isReturnForProcessingOpen, setIsReturnForProcessingOpen] =
        useState(false);
    const [isReopenDialogOpen, setIsReopenDialogOpen] = useState(false);
    const [isUpgradeDialogOpen, setIsUpgradeDialogOpen] = useState(false);
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
    const [rejectCategory, setRejectCategory] = useState('');
    const [rejectReason, setRejectReason] = useState('');
    const [returnForProcessingReason, setReturnForProcessingReason] =
        useState('');
    const [reopenReason, setReopenReason] = useState('');
    const [retainAssignmentOnReopen, setRetainAssignmentOnReopen] =
        useState(false);
    const [upgradeReason, setUpgradeReason] = useState('');
    const [processingForm, setProcessingForm] = useState<ProcessingFormState>({
        loan_request: {
            requested_amount: toStringValue(loanRequest.requested_amount),
            requested_term: toStringValue(loanRequest.requested_term),
            loan_purpose: loanRequest.loan_purpose ?? '',
            availment_status: loanRequest.availment_status ?? '',
        },
        applicant: toPersonForm(applicant),
        co_maker_1: toPersonForm(coMakerOne),
        co_maker_2: toPersonForm(coMakerTwo),
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
            loan_request: {
                requested_amount: toStringValue(currentRequest.requested_amount),
                requested_term: toStringValue(currentRequest.requested_term),
                loan_purpose: currentRequest.loan_purpose ?? '',
                availment_status: currentRequest.availment_status ?? '',
            },
            applicant: toPersonForm(currentApplicant),
            co_maker_1: toPersonForm(currentCoMakerOne),
            co_maker_2: toPersonForm(currentCoMakerTwo),
            processing: { ...currentDataSections.processing },
            recommended_amount: toStringValue(currentRequest.recommended_amount),
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
        currentApplicant,
        currentCoMakerOne,
        currentCoMakerTwo,
        currentDataSections.processing,
        currentRequest.availment_status,
        currentRequest.loan_purpose,
        currentRequest.recommendation_remarks,
        currentRequest.recommended_amount,
        currentRequest.recommended_interest_rate,
        currentRequest.recommended_payment_frequency,
        currentRequest.recommended_term,
        currentRequest.requested_amount,
        currentRequest.requested_term,
    ]);

    const hasWorkflowPermission = (
        permission: LoanRequestWorkflowPermission,
    ): boolean => workflowPermissions.includes(permission);
    const isOwnRequest = workflowContext.isOwnRequest;
    const actorUserId = auth.user.id;
    const assignedProcessorId =
        currentRequest.assigned_processor_id ?? currentRequest.assigned_officer_id;
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
    const isV2Workflow =
        currentRequest.workflow_version === 'document_workflow_v2';
    const canClaim = currentRequest.can_claim;
    const canStartReview =
        !isOwnRequest &&
        currentRequest.status === 'pending_review' &&
        hasWorkflowPermission('loan.review') &&
        (assignedProcessorId === null || assignedProcessorId === actorUserId);
    const canRequestRevision =
        !isV2Workflow &&
        !isOwnRequest &&
        (currentRequest.status === 'pending_review' ||
            currentRequest.status === 'under_review') &&
        hasWorkflowPermission('loan.request_revision') &&
        assignedProcessorId === actorUserId;
    const canReject =
        !isV2Workflow &&
        !isOwnRequest &&
        (currentRequest.status === 'pending_review' ||
            currentRequest.status === 'under_review') &&
        hasWorkflowPermission('loan.reject') &&
        assignedProcessorId === actorUserId;
    const canUpdateProcessing =
        !isOwnRequest &&
        hasWorkflowPermission('loan.review') &&
        assignedProcessorId === actorUserId &&
        [
            'pending_review',
            'under_review',
            'needs_revision',
            'awaiting_member_information',
        ].includes(currentRequest.status ?? '');
    const canRequestMemberAction = canUpdateProcessing;
    const canRejectDuringProcessing =
        isV2Workflow &&
        !isOwnRequest &&
        hasWorkflowPermission('loan.reject') &&
        assignedProcessorId === actorUserId &&
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
        assignedProcessorId === actorUserId;
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
            (officer) => officer.user_id !== assignedProcessorId,
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
        !['approved', 'declined', 'rejected', 'cancelled', 'converted_to_loan'].includes(
            currentRequest.status ?? '',
        );
    const isWorkflowProcessing =
        workflowProcessingIds[currentRequest.id] ?? false;
    const memberFieldDefinitions = useMemo(
        () =>
            Object.entries(dataSectionDefinitions)
                .flatMap(([sectionKey, section]) =>
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

    const updateProcessingPersonField =
        (personKey: 'applicant' | 'co_maker_1' | 'co_maker_2') =>
        (field: keyof LoanRequestPersonFormData, value: string) => {
            setProcessingForm((current) => ({
                ...current,
                [personKey]: {
                    ...current[personKey],
                    [field]: value,
                },
            }));
        };

    const updateProcessingDetailField = (
        field: keyof ProcessingFormState['loan_request'],
        value: string,
    ) => {
        setProcessingForm((current) => ({
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

    const submitProcessingDetails = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        const result = await updateProcessingDetails(currentRequest.id, {
            reason: processingForm.reason,
            information_source: processingForm.information_source,
            loan_request: processingForm.loan_request,
            applicant: processingForm.applicant,
            co_maker_1: processingForm.co_maker_1,
            co_maker_2: processingForm.co_maker_2,
            processing: processingForm.processing,
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
            setIsProcessingDialogOpen(false);
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

    const submitRejectDuringProcessing = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        const result = await rejectLoanRequestDuringProcessing(
            currentRequest.id,
            {
                rejection_category: rejectCategory,
                member_visible_reason: rejectReason,
            },
        );

        if (result) {
            setIsRejectDuringProcessingOpen(false);
            setRejectCategory('');
            setRejectReason('');
        }
    };

    const submitReturnForProcessing = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        const result = await returnForProcessing(currentRequest.id, {
            reason: returnForProcessingReason,
        });

        if (result) {
            setIsReturnForProcessingOpen(false);
            setReturnForProcessingReason('');
        }
    };

    const submitReopen = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const result = await reopenLoanRequest(currentRequest.id, {
            reason: reopenReason,
            retain_assignment: retainAssignmentOnReopen,
        });

        if (result) {
            setIsReopenDialogOpen(false);
            setReopenReason('');
            setRetainAssignmentOnReopen(false);
        }
    };

    const submitUpgradeWorkflow = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        const result = await upgradeWorkflow(currentRequest.id, {
            reason: upgradeReason,
        });

        if (result) {
            setIsUpgradeDialogOpen(false);
            setUpgradeReason('');
        }
    };

    const submitGenerateDocuments = async (
        documentKey?: LoanRequestDocumentKey,
    ) => {
        await generateDocuments(currentRequest.id, {
            document_key: documentKey ?? null,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan request" />
            <section className="mx-auto mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <Card className="border-border/30 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardTitle>Processing workspace</CardTitle>
                            <CardDescription>
                                Keep the request data, member follow-up, and
                                document package current before recommendation.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">
                                    Workflow:{' '}
                                    {currentRequest.workflow_version ===
                                    'document_workflow_v2'
                                        ? 'Document Workflow v2'
                                        : 'Legacy v1'}
                                </Badge>
                                {assignedProcessorId !== null ? (
                                    <Badge variant="secondary">
                                        Assigned Loan Processor
                                    </Badge>
                                ) : null}
                            </div>
                            {lastDocumentResults !== null ? (
                                <Alert className="border-sky-500/30 bg-sky-500/10">
                                    <AlertTitle>
                                        Document generation results
                                    </AlertTitle>
                                    <AlertDescription>
                                        <p>
                                            {lastDocumentResults.length}{' '}
                                            document
                                            {lastDocumentResults.length === 1
                                                ? ''
                                                : 's'}{' '}
                                            refreshed from the latest
                                            generation run.
                                        </p>
                                        {documentResultSummary.length > 0 ? (
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                {documentResultSummary.map(
                                                    (item) => (
                                                        <span
                                                            key={item.status}
                                                            className={`rounded-full border px-2 py-1 text-[11px] font-semibold ${displayChecklistStatusTone(item.status)}`}
                                                        >
                                                            {item.label}:{' '}
                                                            {item.count}
                                                        </span>
                                                    ),
                                                )}
                                            </div>
                                        ) : null}
                                    </AlertDescription>
                                </Alert>
                            ) : null}
                            {currentRequest.member_action_type !== null ? (
                                <Alert className="border-violet-500/30 bg-violet-500/10">
                                    <AlertTitle>
                                        Pending member action
                                    </AlertTitle>
                                    <AlertDescription>
                                        {currentRequest.member_action_message ??
                                            'This request is waiting for a member response.'}
                                    </AlertDescription>
                                </Alert>
                            ) : null}
                            <div className="grid gap-2 md:grid-cols-2">
                                {canUpdateProcessing ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={isWorkflowProcessing}
                                        onClick={() =>
                                            setIsProcessingDialogOpen(true)
                                        }
                                    >
                                        Edit Processing Details
                                    </Button>
                                ) : null}
                                {canRequestMemberAction ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={isWorkflowProcessing}
                                        onClick={() =>
                                            setIsMemberActionDialogOpen(true)
                                        }
                                    >
                                        Request Member Action
                                    </Button>
                                ) : null}
                                {canRejectDuringProcessing ? (
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        disabled={isWorkflowProcessing}
                                        onClick={() =>
                                            setIsRejectDuringProcessingOpen(
                                                true,
                                            )
                                        }
                                    >
                                        Reject During Processing
                                    </Button>
                                ) : null}
                                {canGenerateDocuments ? (
                                    <Button
                                        type="button"
                                        disabled={isWorkflowProcessing}
                                        onClick={() =>
                                            void submitGenerateDocuments()
                                        }
                                    >
                                        Generate All Required Documents
                                    </Button>
                                ) : null}
                                {canReturnForProcessing ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={isWorkflowProcessing}
                                        onClick={() =>
                                            setIsReturnForProcessingOpen(true)
                                        }
                                    >
                                        Return for Processing
                                    </Button>
                                ) : null}
                                {canReopenRejectedRequest ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={isWorkflowProcessing}
                                        onClick={() =>
                                            setIsReopenDialogOpen(true)
                                        }
                                    >
                                        Reopen Rejected Request
                                    </Button>
                                ) : null}
                                {canUpgradeWorkflow ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={isWorkflowProcessing}
                                        onClick={() =>
                                            setIsUpgradeDialogOpen(true)
                                        }
                                    >
                                        Upgrade to Document Workflow v2
                                    </Button>
                                ) : null}
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-border/30 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardTitle>Document checklist</CardTitle>
                            <CardDescription>
                                Every applicable document must be current
                                before recommendation.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {currentDocumentChecklist.map((document) => {
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

                                return (
                                    <div
                                        key={document.key}
                                        className="rounded-xl border border-border/40 bg-muted/10 p-4"
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="space-y-1">
                                                <p className="text-sm font-semibold">
                                                    {document.label}
                                                </p>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span
                                                        className={`rounded-full border px-2 py-1 text-[11px] font-semibold ${displayChecklistStatusTone(document.status)}`}
                                                    >
                                                        {document.status_label}
                                                    </span>
                                                    {document.template_version ? (
                                                        <span className="rounded-full border border-border/50 px-2 py-1 text-[11px] text-muted-foreground">
                                                            {document.template_version}
                                                        </span>
                                                    ) : null}
                                                    {document.generated_at ? (
                                                        <span className="text-xs text-muted-foreground">
                                                            Last generated:{' '}
                                                            {formatDateTime(
                                                                document.generated_at,
                                                            )}
                                                        </span>
                                                    ) : null}
                                                </div>
                                                <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                                                    <span>
                                                        Generated by:{' '}
                                                        {document.generated_by ??
                                                            '-'}
                                                    </span>
                                                    <span>
                                                        Generated version:{' '}
                                                        {document.generated_version ??
                                                            '-'}
                                                    </span>
                                                    <span>
                                                        Source version:{' '}
                                                        {document.source_version ??
                                                            '-'}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                {document.generated_filename ? (
                                                    <>
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="outline"
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
                                                        </Button>
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="outline"
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
                                                        </Button>
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            <a href={downloadHref}>
                                                                Download
                                                            </a>
                                                        </Button>
                                                    </>
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
                                        {document.blockers.length > 0 ? (
                                            <ul className="mt-3 space-y-1 text-xs text-muted-foreground">
                                                {document.blockers.map(
                                                    (blocker) => (
                                                        <li key={blocker}>
                                                            {blocker}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        ) : null}
                                        {document.failure_message ? (
                                            <p className="mt-3 text-xs text-rose-700 dark:text-rose-300">
                                                {document.failure_message}
                                            </p>
                                        ) : null}
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                </div>
            </section>
            <section className="mx-auto mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                    <Card className="border-border/30 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardTitle>Workflow health</CardTitle>
                            <CardDescription>
                                Operational state for this request and the
                                active workflow queues.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-2">
                            <div className="rounded-xl border border-border/40 bg-muted/10 p-4">
                                <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                    Processing age
                                </p>
                                <p className="mt-2 text-2xl font-semibold">
                                    {currentWorkflowHealth.processing_age_days ===
                                    null
                                        ? '-'
                                        : `${currentWorkflowHealth.processing_age_days}d`}
                                </p>
                            </div>
                            <div className="rounded-xl border border-border/40 bg-muted/10 p-4">
                                <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                    Pending member action
                                </p>
                                <p className="mt-2 text-2xl font-semibold">
                                    {currentWorkflowHealth.pending_member_action
                                        ? 'Yes'
                                        : 'No'}
                                </p>
                            </div>
                            <div className="rounded-xl border border-border/40 bg-muted/10 p-4">
                                <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                    Stale documents
                                </p>
                                <p className="mt-2 text-2xl font-semibold">
                                    {
                                        currentWorkflowHealth.stale_document_count
                                    }
                                </p>
                            </div>
                            <div className="rounded-xl border border-border/40 bg-muted/10 p-4">
                                <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                    Failed documents
                                </p>
                                <p className="mt-2 text-2xl font-semibold">
                                    {
                                        currentWorkflowHealth.failed_document_count
                                    }
                                </p>
                            </div>
                            <div className="rounded-xl border border-border/40 bg-muted/10 p-4">
                                <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                    Legacy blockers
                                </p>
                                <p className="mt-2 text-2xl font-semibold">
                                    {
                                        currentWorkflowHealth.legacy_blocker_count
                                    }
                                </p>
                            </div>
                            <div className="rounded-xl border border-border/40 bg-muted/10 p-4">
                                <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                    Notification failures
                                </p>
                                <p className="mt-2 text-2xl font-semibold">
                                    {
                                        currentWorkflowHealth.notification_failure_count
                                    }
                                </p>
                            </div>
                            <div className="rounded-xl border border-border/40 bg-muted/10 p-4 sm:col-span-2">
                                <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                    Workflow failed jobs
                                </p>
                                <p className="mt-2 text-2xl font-semibold">
                                    {
                                        currentWorkflowHealth.workflow_failed_job_count
                                    }
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card className="border-border/30 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardTitle>Notification history</CardTitle>
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
                                                <p>
                                                    Attempts:{' '}
                                                    {event.attempt_count}
                                                </p>
                                                <p>
                                                    Retries:{' '}
                                                    {event.retry_count}
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
            {hasWorkflowPermission('loan.wibs_encode') &&
            [
                'converted_to_loan',
                'for_wibs_encoding',
                'wibs_loan_created',
                'release_scheduled',
                'released',
            ].includes(currentRequest.status ?? '') ? (
                <section className="mx-auto mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
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
                                    {currentRequest.status === 'converted_to_loan'
                                        ? 'Converted to Loan'
                                        : currentRequest.status === 'for_wibs_encoding'
                                          ? 'For WIBS Encoding'
                                          : currentRequest.status === 'wibs_loan_created'
                                            ? 'WIBS Loan Created'
                                            : currentRequest.status === 'release_scheduled'
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
                                    {formatDateTime(currentRequest.wibs_released_at)}
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
                            ) : currentRequest.status === 'for_wibs_encoding' ? (
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
                            ) : currentRequest.status === 'wibs_loan_created' ? (
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
                            ) : currentRequest.status === 'release_scheduled' ? (
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
                </section>
            ) : null}
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
                }}
            />

            <Dialog
                open={isProcessingDialogOpen}
                onOpenChange={setIsProcessingDialogOpen}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
                    <DialogHeader>
                        <DialogTitle>Edit Processing Details</DialogTitle>
                        <DialogDescription>
                            Save verified request, applicant, co-maker, and
                            processing values with a reason and information
                            source.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-6" onSubmit={submitProcessingDetails}>
                        <LoanRequestSectionCard
                            title="Loan request details"
                            description="Update the verified request details used throughout the document package."
                        >
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="processing_requested_amount">
                                        Requested amount
                                    </Label>
                                    <Input
                                        id="processing_requested_amount"
                                        type="number"
                                        value={
                                            processingForm.loan_request
                                                .requested_amount
                                        }
                                        onChange={(event) =>
                                            updateProcessingDetailField(
                                                'requested_amount',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="processing_requested_term">
                                        Requested term
                                    </Label>
                                    <Input
                                        id="processing_requested_term"
                                        type="number"
                                        value={
                                            processingForm.loan_request
                                                .requested_term
                                        }
                                        onChange={(event) =>
                                            updateProcessingDetailField(
                                                'requested_term',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2 md:col-span-2">
                                    <Label htmlFor="processing_loan_purpose">
                                        Loan purpose
                                    </Label>
                                    <Input
                                        id="processing_loan_purpose"
                                        value={
                                            processingForm.loan_request
                                                .loan_purpose
                                        }
                                        onChange={(event) =>
                                            updateProcessingDetailField(
                                                'loan_purpose',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2 md:col-span-2">
                                    <Label htmlFor="processing_availment_status">
                                        Availment status
                                    </Label>
                                    <Input
                                        id="processing_availment_status"
                                        value={
                                            processingForm.loan_request
                                                .availment_status
                                        }
                                        onChange={(event) =>
                                            updateProcessingDetailField(
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
                                    values={processingForm.applicant}
                                    errors={{}}
                                    includeSpouse
                                    includeChildren
                                    onChange={updateProcessingPersonField(
                                        'applicant',
                                    )}
                                />
                                <Separator className="bg-border/40" />
                                <LoanRequestWorkFields
                                    prefix="applicant"
                                    values={processingForm.applicant}
                                    errors={{}}
                                    onChange={updateProcessingPersonField(
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
                                    values={processingForm.co_maker_1}
                                    errors={{}}
                                    onChange={updateProcessingPersonField(
                                        'co_maker_1',
                                    )}
                                />
                                <Separator className="bg-border/40" />
                                <LoanRequestWorkFields
                                    prefix="co_maker_1"
                                    values={processingForm.co_maker_1}
                                    errors={{}}
                                    onChange={updateProcessingPersonField(
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
                                    values={processingForm.co_maker_2}
                                    errors={{}}
                                    onChange={updateProcessingPersonField(
                                        'co_maker_2',
                                    )}
                                />
                                <Separator className="bg-border/40" />
                                <LoanRequestWorkFields
                                    prefix="co_maker_2"
                                    values={processingForm.co_maker_2}
                                    errors={{}}
                                    onChange={updateProcessingPersonField(
                                        'co_maker_2',
                                    )}
                                />
                            </div>
                        </LoanRequestSectionCard>

                        <LoanRequestSectionCard
                            title="Processing fields"
                            description="Update recommendation values and document-supporting processing details."
                        >
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="recommended_amount">
                                        Recommended amount
                                    </Label>
                                    <Input
                                        id="recommended_amount"
                                        type="number"
                                        value={
                                            processingForm.recommended_amount
                                        }
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
                                    <Label htmlFor="recommended_term">
                                        Recommended term
                                    </Label>
                                    <Input
                                        id="recommended_term"
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
                                    <Label htmlFor="recommended_interest_rate">
                                        Recommended interest rate
                                    </Label>
                                    <Input
                                        id="recommended_interest_rate"
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
                                    <Label htmlFor="recommended_payment_frequency">
                                        Payment frequency
                                    </Label>
                                    <Input
                                        id="recommended_payment_frequency"
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
                                <div className="grid gap-2 md:col-span-2">
                                    <Label htmlFor="recommendation_remarks">
                                        Recommendation remarks
                                    </Label>
                                    <textarea
                                        id="recommendation_remarks"
                                        className={textareaClassName}
                                        value={
                                            processingForm.recommendation_remarks
                                        }
                                        onChange={(event) =>
                                            setProcessingForm((current) => ({
                                                ...current,
                                                recommendation_remarks:
                                                    event.target.value,
                                            }))
                                        }
                                    />
                                </div>
                                {Object.entries(
                                    dataSectionDefinitions.processing.fields,
                                ).map(([fieldKey, field]) => (
                                    <div
                                        key={fieldKey}
                                        className="grid gap-2"
                                    >
                                        <Label htmlFor={`processing_${fieldKey}`}>
                                            {field.label}
                                        </Label>
                                        <Input
                                            id={`processing_${fieldKey}`}
                                            type={
                                                field.type === 'number' ||
                                                field.type === 'integer'
                                                    ? 'number'
                                                    : 'text'
                                            }
                                            step={
                                                field.type === 'number'
                                                    ? '0.01'
                                                    : undefined
                                            }
                                            value={
                                                processingForm.processing[
                                                    fieldKey
                                                ]
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
                                ))}
                            </div>
                        </LoanRequestSectionCard>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="processing_reason">Reason</Label>
                                <textarea
                                    id="processing_reason"
                                    className={textareaClassName}
                                    required
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
                                <Label htmlFor="information_source">
                                    Information source
                                </Label>
                                <Input
                                    id="information_source"
                                    required
                                    value={processingForm.information_source}
                                    onChange={(event) =>
                                        setProcessingForm((current) => ({
                                            ...current,
                                            information_source:
                                                event.target.value,
                                        }))
                                    }
                                />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsProcessingDialogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={isWorkflowProcessing}
                            >
                                Save Processing Details
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={isMemberActionDialogOpen}
                onOpenChange={setIsMemberActionDialogOpen}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Request Member Action</DialogTitle>
                        <DialogDescription>
                            Ask the member for a correction or for additional
                            information, and record exactly which fields need
                            their attention.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-5" onSubmit={submitMemberAction}>
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
                                    setMemberActionMessage(
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="member_action_reason">
                                Internal reason
                            </Label>
                            <textarea
                                id="member_action_reason"
                                className={textareaClassName}
                                required
                                value={memberActionReason}
                                onChange={(event) =>
                                    setMemberActionReason(event.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-3">
                            <p className="text-sm font-medium">
                                Fields requiring member action
                            </p>
                            <div className="grid gap-3 md:grid-cols-2">
                                {memberFieldDefinitions.map((item) => (
                                    <label
                                        key={item.fieldKey}
                                        className="flex items-start gap-3 rounded-lg border border-border/40 bg-muted/10 p-3 text-sm"
                                    >
                                        <Checkbox
                                            checked={selectedMemberFields.includes(
                                                item.fieldKey,
                                            )}
                                            onCheckedChange={(checked) =>
                                                setSelectedMemberFields(
                                                    (current) =>
                                                        checked === true
                                                            ? [
                                                                  ...current,
                                                                  item.fieldKey,
                                                              ]
                                                            : current.filter(
                                                                  (field) =>
                                                                      field !==
                                                                      item.fieldKey,
                                                              ),
                                                )
                                            }
                                        />
                                        <span>
                                            {item.field.label}
                                            <span className="block text-xs text-muted-foreground">
                                                {dataSectionDefinitions[
                                                    item.sectionKey
                                                ]?.label ?? item.sectionKey}
                                            </span>
                                        </span>
                                    </label>
                                ))}
                            </div>
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
                open={isRejectDuringProcessingOpen}
                onOpenChange={setIsRejectDuringProcessingOpen}
            >
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Reject During Processing</DialogTitle>
                        <DialogDescription>
                            This closes the request and records the substantive
                            rejection reason shown to the member.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={submitRejectDuringProcessing}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="reject_category">
                                Rejection category
                            </Label>
                            <Input
                                id="reject_category"
                                required
                                value={rejectCategory}
                                onChange={(event) =>
                                    setRejectCategory(event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="reject_reason">
                                Member-visible reason
                            </Label>
                            <textarea
                                id="reject_reason"
                                className={textareaClassName}
                                required
                                value={rejectReason}
                                onChange={(event) =>
                                    setRejectReason(event.target.value)
                                }
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    setIsRejectDuringProcessingOpen(false)
                                }
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={isWorkflowProcessing}
                            >
                                Confirm Rejection
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={isReturnForProcessingOpen}
                onOpenChange={setIsReturnForProcessingOpen}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Return for Processing</DialogTitle>
                        <DialogDescription>
                            Send this package back to the loan processor with a
                            required reason.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={submitReturnForProcessing}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="return_for_processing_reason">
                                Reason
                            </Label>
                            <textarea
                                id="return_for_processing_reason"
                                className={textareaClassName}
                                required
                                value={returnForProcessingReason}
                                onChange={(event) =>
                                    setReturnForProcessingReason(
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    setIsReturnForProcessingOpen(false)
                                }
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={isWorkflowProcessing}
                            >
                                Return for Processing
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={isReopenDialogOpen} onOpenChange={setIsReopenDialogOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Reopen Rejected Request</DialogTitle>
                        <DialogDescription>
                            Reopen this rejected request and optionally retain
                            the existing assignment.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitReopen}>
                        <div className="grid gap-2">
                            <Label htmlFor="reopen_reason">Reason</Label>
                            <textarea
                                id="reopen_reason"
                                className={textareaClassName}
                                required
                                value={reopenReason}
                                onChange={(event) =>
                                    setReopenReason(event.target.value)
                                }
                            />
                        </div>
                        <label className="flex items-start gap-3 rounded-lg border border-border/40 bg-muted/10 p-3 text-sm">
                            <Checkbox
                                checked={retainAssignmentOnReopen}
                                onCheckedChange={(checked) =>
                                    setRetainAssignmentOnReopen(
                                        checked === true,
                                    )
                                }
                            />
                            <span>Retain the current assignment on reopen</span>
                        </label>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsReopenDialogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={isWorkflowProcessing}
                            >
                                Reopen Request
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={isUpgradeDialogOpen}
                onOpenChange={setIsUpgradeDialogOpen}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Upgrade Legacy Workflow</DialogTitle>
                        <DialogDescription>
                            Upgrade this unapproved legacy request into the v2
                            document workflow with an audit reason.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitUpgradeWorkflow}>
                        <div className="grid gap-2">
                            <Label htmlFor="upgrade_reason">Reason</Label>
                            <textarea
                                id="upgrade_reason"
                                className={textareaClassName}
                                required
                                value={upgradeReason}
                                onChange={(event) =>
                                    setUpgradeReason(event.target.value)
                                }
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsUpgradeDialogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={isWorkflowProcessing}
                            >
                                Upgrade Workflow
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
