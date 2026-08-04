import { Head, router, usePage } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { AdminLoanRequestCorrectionDialog } from '@/components/loan-request/admin-loan-request-correction-dialog';
import { LoanRequestDetailPage } from '@/components/loan-request/loan-request-detail-page';
import { LoanRequestDocumentChecklistCard } from '@/components/loan-request/loan-request-document-checklist-card';
import { ProcessingDetailsPanel } from '@/components/loan-request/processing-details-panel';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useCancelLoanRequest } from '@/hooks/admin/use-cancel-loan-request';
import { useCorrectLoanRequest } from '@/hooks/admin/use-correct-loan-request';
import { useCreateAdminCorrectedLoanRequest } from '@/hooks/admin/use-create-admin-corrected-loan-request';
import { useDismissLoanRequestCorrectionReport } from '@/hooks/admin/use-dismiss-loan-request-correction-report';
import { useLoanRequestWorkflow } from '@/hooks/admin/use-loan-request-workflow';
import { useUpdateLoanRequestDecision } from '@/hooks/admin/use-update-loan-request-decision';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/formatters';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import {
    approvedDocuments as requestsApprovedDocuments,
    index as requestsIndex,
    pdf as requestsPdf,
    show as requestsShow,
} from '@/routes/admin/requests';
import {
    affidavitUndertaking as requestsAffidavitUndertakingDocument,
    applicationForm as requestsApplicationFormDocument,
    authorityToDeduct as requestsAuthorityToDeductDocument,
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
} from '@/routes/admin/requests/documents';
import type { BreadcrumbItem } from '@/types';
import type { Auth } from '@/types/auth';
import type {
    LoanManagerOption,
    LoanRequestAssignmentOfficerOption,
    LoanRequestAuditEntry,
    LoanRequestCorrectionReport,
    LoanRequestCorrectionPayload,
    LoanRequestDataSectionDefinitions,
    LoanRequestDataSections,
    LoanRequestDetail,
    LoanRequestDocumentChecklistItem,
    LoanRequestDocumentKey,
    LoanRequestWorkflowPermission,
    LoanRequestPersonData,
    LoanTypeOption,
} from '@/types/loan-requests';

type DecisionState = {
    canDecide: boolean;
    canCancel: boolean;
    isOwnRequest: boolean;
    blockedMessage?: string | null;
    approverName?: string | null;
};

const buildCancellationReasonPrefill = (
    report: LoanRequestCorrectionReport,
): string =>
    `Member reported incorrect details: ${report.issue_description}. Correct information: ${report.correct_information}.`;

const latestOpenReport = (
    reports: LoanRequestCorrectionReport[],
): LoanRequestCorrectionReport | null =>
    reports.find((report) => report.status === 'open') ?? null;

const latestCorrectionReportContext = (
    reports: LoanRequestCorrectionReport[],
): LoanRequestCorrectionReport | null =>
    latestOpenReport(reports) ?? reports[0] ?? null;

const resolveCancellationReasonPrefill = (
    reports: LoanRequestCorrectionReport[],
    fallback: string | null = null,
): string | null => {
    const openReport = latestOpenReport(reports);

    return openReport ? buildCancellationReasonPrefill(openReport) : fallback;
};

type Props = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    decision: DecisionState;
    auditTrail: LoanRequestAuditEntry[];
    workflowPermissions: LoanRequestWorkflowPermission[];
    loanTypes: LoanTypeOption[];
    correctionReports: LoanRequestCorrectionReport[];
    openCorrectionReportCancellationReason: string | null;
    openCorrectionOnLoad: boolean;
    eligibleOfficers: LoanRequestAssignmentOfficerOption[];
    loanManagers: LoanManagerOption[];
    dataSections: LoanRequestDataSections;
    dataSectionDefinitions: LoanRequestDataSectionDefinitions;
    documentChecklist: LoanRequestDocumentChecklistItem[];
};

export default function LoanRequestShow({
    loanRequest,
    applicant,
    coMakerOne,
    coMakerTwo,
    decision,
    auditTrail,
    workflowPermissions,
    loanTypes,
    correctionReports,
    openCorrectionReportCancellationReason,
    openCorrectionOnLoad,
    eligibleOfficers,
    loanManagers,
    dataSections,
    dataSectionDefinitions,
    documentChecklist,
}: Props) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [currentRequest, setCurrentRequest] =
        useState<LoanRequestDetail>(loanRequest);
    const [currentEligibleOfficers, setCurrentEligibleOfficers] =
        useState<LoanRequestAssignmentOfficerOption[]>(eligibleOfficers);
    const [currentApplicant, setCurrentApplicant] =
        useState<LoanRequestPersonData | null>(applicant);
    const [currentCoMakerOne, setCurrentCoMakerOne] =
        useState<LoanRequestPersonData | null>(coMakerOne);
    const [currentCoMakerTwo, setCurrentCoMakerTwo] =
        useState<LoanRequestPersonData | null>(coMakerTwo);
    const [currentCorrectionReports, setCurrentCorrectionReports] =
        useState<LoanRequestCorrectionReport[]>(correctionReports);
    const [currentAuditTrail, setCurrentAuditTrail] =
        useState<LoanRequestAuditEntry[]>(auditTrail);
    const [currentDataSections, setCurrentDataSections] =
        useState<LoanRequestDataSections>(dataSections);
    const [currentDocumentChecklist, setCurrentDocumentChecklist] =
        useState<LoanRequestDocumentChecklistItem[]>(documentChecklist);
    const shouldAutoOpenCorrection =
        openCorrectionOnLoad &&
        loanRequest.requires_correction_before_approval &&
        !decision.isOwnRequest;
    const [isCorrectionOpen, setIsCorrectionOpen] = useState(
        shouldAutoOpenCorrection,
    );
    const [isDismissDialogOpen, setIsDismissDialogOpen] = useState(false);
    const [dismissNotes, setDismissNotes] = useState('');
    const [selectedReport, setSelectedReport] =
        useState<LoanRequestCorrectionReport | null>(null);
    const [cancellationReasonPrefill, setCancellationReasonPrefill] = useState<
        string | null
    >(
        resolveCancellationReasonPrefill(
            correctionReports,
            openCorrectionReportCancellationReason,
        ),
    );
    const { updateDecision, processingIds } = useUpdateLoanRequestDecision({
        onUpdated: (result) => {
            setCurrentRequest(result.loanRequest);
            setCurrentCorrectionReports(result.correctionReports);
            setCurrentAuditTrail(result.auditTrail);
        },
    });
    const {
        claimLoanRequest,
        assignLoanRequest,
        reassignLoanRequest,
        startReview,
        requestRevision,
        rejectLoanRequest,
        updateProcessingDetails,
        generateDocuments,
        recommendApproval,
        approveLoanRequest,
        declineLoanRequest,
        processingIds: workflowProcessingIds,
    } = useLoanRequestWorkflow({
        onUpdated: (result) => {
            setCurrentRequest(result.loanRequest);
            setCurrentApplicant(result.applicant);
            setCurrentCoMakerOne(result.coMakerOne);
            setCurrentCoMakerTwo(result.coMakerTwo);
            setCurrentCorrectionReports(result.correctionReports);
            setCurrentAuditTrail(result.auditTrail);
            setCurrentEligibleOfficers(result.eligibleOfficers);
            setCurrentDataSections(result.dataSections);
            setCurrentDocumentChecklist(result.documentChecklist);
            setCancellationReasonPrefill(
                resolveCancellationReasonPrefill(result.correctionReports),
            );
        },
    });
    const {
        correctLoanRequest,
        processingIds: correctionProcessingIds,
        errors: correctionErrors,
        clearErrors: clearCorrectionErrors,
    } = useCorrectLoanRequest({
        onUpdated: (updated) => {
            setCurrentRequest(updated.loanRequest);
            setCurrentApplicant(updated.applicant);
            setCurrentCoMakerOne(updated.coMakerOne);
            setCurrentCoMakerTwo(updated.coMakerTwo);
            setCurrentAuditTrail(updated.auditTrail);
            setIsCorrectionOpen(false);
        },
    });
    const { cancelLoanRequest, processingIds: cancellationProcessingIds } =
        useCancelLoanRequest({
            onUpdated: (updated) => {
                setCurrentRequest(updated.loanRequest);
                setCurrentCorrectionReports(updated.correctionReports);
                setCurrentAuditTrail(updated.auditTrail);
                setCancellationReasonPrefill(
                    resolveCancellationReasonPrefill(updated.correctionReports),
                );
            },
        });
    const { dismissCorrectionReport, processingIds: dismissProcessingIds } =
        useDismissLoanRequestCorrectionReport({
            onDismissed: (result) => {
                setCurrentCorrectionReports(result.correctionReports);
                setCancellationReasonPrefill(
                    resolveCancellationReasonPrefill(result.correctionReports),
                );
                setIsDismissDialogOpen(false);
                setDismissNotes('');
                setSelectedReport(null);
            },
        });
    const {
        createAdminCorrectedCopy,
        processingIds: adminCorrectedCopyProcessingIds,
    } = useCreateAdminCorrectedLoanRequest({
        onCreated: (result) => {
            if (typeof window === 'undefined') {
                router.visit(result.loanRequest.url);
                return;
            }

            const correctedRequestUrl = new URL(
                result.loanRequest.url,
                window.location.origin,
            );
            correctedRequestUrl.searchParams.set('openCorrection', '1');

            router.visit(
                `${correctedRequestUrl.pathname}${correctedRequestUrl.search}${correctedRequestUrl.hash}`,
            );
        },
    });
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Requests', href: requestsIndex().url },
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
                  packageZip: requestsApprovedDocuments(currentRequest.id).url,
              }
            : null;
    const hasWorkflowPermission = (
        permission: LoanRequestWorkflowPermission,
    ): boolean => workflowPermissions.includes(permission);
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
    const canUpdateProcessing =
        !decision.isOwnRequest &&
        hasWorkflowPermission('loan.review') &&
        normalizedAssignedProcessorId === normalizedActorUserId &&
        [
            'pending_review',
            'under_review',
            'needs_revision',
            'awaiting_member_information',
        ].includes(currentRequest.status ?? '');
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
    const canGenerateDocuments =
        canUpdateProcessing ||
        (!decision.isOwnRequest &&
            hasWorkflowPermission('loan.review') &&
            ['approved', 'converted_to_loan'].includes(
                currentRequest.status ?? '',
            ));
    const canDecide =
        currentRequest.status === 'under_review' && decision.canDecide;
    const canStartReview =
        !decision.isOwnRequest &&
        currentRequest.status === 'pending_review' &&
        hasWorkflowPermission('loan.review');
    const canClaim = !decision.isOwnRequest && currentRequest.can_claim;
    const canAssign =
        currentRequest.can_assign && currentEligibleOfficers.length > 0;
    const canReassign =
        currentRequest.can_reassign &&
        currentEligibleOfficers.some(
            (officer) => officer.user_id !== currentRequest.assigned_officer_id,
        );
    const canRequestRevision =
        !decision.isOwnRequest &&
        (currentRequest.status === 'pending_review' ||
            currentRequest.status === 'under_review') &&
        hasWorkflowPermission('loan.request_revision');
    const canReject =
        !decision.isOwnRequest &&
        (currentRequest.status === 'pending_review' ||
            currentRequest.status === 'under_review') &&
        hasWorkflowPermission('loan.reject');
    const canRecommendApproval =
        !decision.isOwnRequest &&
        currentRequest.status === 'under_review' &&
        hasWorkflowPermission('loan.recommend_approval');
    const canWorkflowApprove =
        !decision.isOwnRequest &&
        currentRequest.status === 'recommended_for_approval' &&
        hasWorkflowPermission('loan.approve');
    const canWorkflowDecline =
        !decision.isOwnRequest &&
        currentRequest.status === 'recommended_for_approval' &&
        hasWorkflowPermission('loan.decline');
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
    const canCorrect =
        currentRequest.status === 'under_review' && !decision.isOwnRequest;
    const requiresCorrectionBeforeApproval =
        currentRequest.requires_correction_before_approval;
    const canCreateAdminCorrectedCopy =
        currentRequest.status === 'cancelled' &&
        currentRequest.corrected_request_id === null;
    const correctionReportContext = latestCorrectionReportContext(
        currentCorrectionReports,
    );
    const blockedMessage =
        currentRequest.status === 'under_review'
            ? (decision.blockedMessage ?? null)
            : null;
    const isCorrecting = correctionProcessingIds[currentRequest.id] ?? false;
    const isWorkflowProcessing =
        workflowProcessingIds[currentRequest.id] ?? false;
    const isCreatingAdminCorrectedCopy =
        adminCorrectedCopyProcessingIds[currentRequest.id] ?? false;
    const correctedRequestHref =
        currentRequest.corrected_request_id !== null
            ? requestsShow(currentRequest.corrected_request_id).url
            : null;
    const hasCorrectionReports = currentCorrectionReports.length > 0;
    const hasOpenCorrectionReport = currentCorrectionReports.some(
        (report) => report.status === 'open',
    );
    const cancellationDialogEventName = `loan-request-cancel-open-${currentRequest.id}`;
    const showCancellationAction =
        decision.canCancel &&
        (currentRequest.status === 'pending_review' ||
            currentRequest.status === 'under_review' ||
            currentRequest.status === 'approved');
    const cancellationActionLabel =
        currentRequest.status === 'approved'
            ? 'Cancel Approved Request'
            : 'Cancel Application';
    const cancellationDialogTitle =
        currentRequest.status === 'approved'
            ? 'Cancel Approved Request'
            : 'Cancel Application';
    const cancellationDialogDescription =
        currentRequest.status === 'approved'
            ? 'This keeps the approved request as read-only history.'
            : 'This will stop the application before a final decision and notify the member.';
    const cancellationConfirmLabel =
        currentRequest.status === 'approved'
            ? 'Cancel Approved Request'
            : 'Cancel Application';
    const statusTone: Record<string, string> = {
        open: 'bg-amber-500/10 text-amber-700 dark:text-amber-200',
        resolved: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-200',
        dismissed: 'bg-rose-500/10 text-rose-700 dark:text-rose-200',
    };

    useEffect(() => {
        if (!shouldAutoOpenCorrection || typeof window === 'undefined') {
            return;
        }

        const currentUrl = new URL(window.location.href);

        if (!currentUrl.searchParams.has('openCorrection')) {
            return;
        }

        currentUrl.searchParams.delete('openCorrection');
        window.history.replaceState(
            window.history.state,
            '',
            `${currentUrl.pathname}${currentUrl.search}${currentUrl.hash}`,
        );
    }, [shouldAutoOpenCorrection]);

    const handleCorrectionOpenChange = (open: boolean) => {
        if (open) {
            clearCorrectionErrors();
        }

        setIsCorrectionOpen(open);
    };

    const handleCorrectionSubmit = (payload: LoanRequestCorrectionPayload) => {
        void correctLoanRequest(currentRequest.id, payload);
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

    const submitGenerateSelectedDocuments = async (documentKeys: string[]) => {
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

    const openCancellationDialogFromReport = (
        report: LoanRequestCorrectionReport,
    ) => {
        const prefill = buildCancellationReasonPrefill(report);

        setCancellationReasonPrefill(prefill);
        window.dispatchEvent(
            new CustomEvent(cancellationDialogEventName, {
                detail: { prefill },
            }),
        );
    };

    const openDismissDialog = (report: LoanRequestCorrectionReport) => {
        setSelectedReport(report);
        setDismissNotes('');
        setIsDismissDialogOpen(true);
    };

    const submitDismissReport = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (selectedReport === null) {
            return;
        }

        await dismissCorrectionReport(currentRequest.id, selectedReport.id, {
            admin_notes: dismissNotes.trim() || null,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan request" />
            {requiresCorrectionBeforeApproval ? (
                <section className="mx-auto mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Alert className="border-amber-500/35 bg-amber-500/10 text-foreground">
                        <CircleAlert className="size-4 text-amber-700 dark:text-amber-200" />
                        <div className="flex w-full flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div className="space-y-1">
                                <AlertTitle>
                                    Correction required before approval
                                </AlertTitle>
                                <AlertDescription>
                                    This request was created from a cancelled
                                    request. Review and save the corrected
                                    details before approving.
                                </AlertDescription>
                            </div>
                            <Button
                                type="button"
                                className="shrink-0"
                                disabled={isCorrecting}
                                onClick={() => handleCorrectionOpenChange(true)}
                            >
                                Continue correction
                            </Button>
                        </div>
                    </Alert>
                </section>
            ) : null}
            {hasCorrectionReports ? (
                <section className="mx-auto mb-6 w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Card className="border-amber-500/25 bg-amber-500/[0.06]">
                        <CardHeader>
                            <div className="flex items-start gap-3">
                                <div className="rounded-full bg-amber-500/10 p-2 text-amber-700 dark:text-amber-200">
                                    <CircleAlert className="size-4" />
                                </div>
                                <div className="space-y-1">
                                    <CardTitle>
                                        Member reported incorrect details
                                    </CardTitle>
                                    <CardDescription>
                                        Review reported issues before cancelling
                                        and creating an admin-corrected request.
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {currentCorrectionReports.map((report) => {
                                const isOpen = report.status === 'open';
                                const reporterName =
                                    report.reported_by?.name ?? '--';
                                const reporterAcctNo =
                                    report.reported_by?.acctno ?? '--';
                                const reportedAt = formatDateTime(
                                    report.reported_at,
                                );
                                const isDismissing =
                                    dismissProcessingIds[report.id] ?? false;

                                return (
                                    <div
                                        key={report.id}
                                        className="rounded-lg border border-border/50 bg-background/80 p-4"
                                    >
                                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                            <Badge
                                                variant="secondary"
                                                className={
                                                    statusTone[report.status]
                                                }
                                            >
                                                {report.status}
                                            </Badge>
                                            <span className="text-xs text-muted-foreground">
                                                Reported at: {reportedAt}
                                            </span>
                                        </div>
                                        <div className="grid gap-3 text-sm md:grid-cols-2">
                                            <div>
                                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                    Reported issue
                                                </p>
                                                <p className="mt-1 whitespace-pre-wrap">
                                                    {report.issue_description}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                    Correct information
                                                </p>
                                                <p className="mt-1 whitespace-pre-wrap">
                                                    {report.correct_information}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="mt-3 grid gap-3 text-sm md:grid-cols-2">
                                            <div>
                                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                    Supporting note/proof
                                                </p>
                                                <p className="mt-1 whitespace-pre-wrap">
                                                    {report.supporting_note ??
                                                        '--'}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                    Reported by
                                                </p>
                                                <p className="mt-1">
                                                    {reporterName} (Acct:{' '}
                                                    {reporterAcctNo})
                                                </p>
                                            </div>
                                        </div>
                                        {isOpen ? (
                                            <div className="mt-4 flex flex-wrap gap-2">
                                                {currentRequest.status ===
                                                'approved' ? (
                                                    <Button
                                                        type="button"
                                                        variant="destructive"
                                                        onClick={() =>
                                                            openCancellationDialogFromReport(
                                                                report,
                                                            )
                                                        }
                                                    >
                                                        Cancel approved request
                                                    </Button>
                                                ) : null}
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={isDismissing}
                                                    onClick={() =>
                                                        openDismissDialog(
                                                            report,
                                                        )
                                                    }
                                                >
                                                    Dismiss report
                                                </Button>
                                            </div>
                                        ) : null}
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                    {hasOpenCorrectionReport &&
                    currentRequest.status !== 'approved' ? (
                        <Alert className="mt-3 border-amber-500/30 bg-amber-500/10">
                            <CircleAlert className="size-4 text-amber-700 dark:text-amber-200" />
                            <AlertTitle>
                                Open correction report found
                            </AlertTitle>
                            <AlertDescription>
                                Open reports can be dismissed now. If the
                                request should stop entirely, use the separate
                                cancellation action instead of declining it.
                            </AlertDescription>
                        </Alert>
                    ) : null}
                </section>
            ) : null}
            <LoanRequestDetailPage
                loanRequest={currentRequest}
                applicant={currentApplicant}
                coMakerOne={currentCoMakerOne}
                coMakerTwo={currentCoMakerTwo}
                backHref={requestsIndex().url}
                backLabel="Back to requests"
                pdfHref={pdfHref}
                documentChecklistAvailable={isProcessingStage}
                showApprovedDocumentList={false}
                approvedDocumentHrefs={approvedDocumentHrefs}
                correctedRequestHref={correctedRequestHref}
                auditTrail={currentAuditTrail}
                auditTrailAudience="staff"
                stageAlert={managerStageAlert}
                processingDetails={
                    showProcessingSection ? (
                        <>
                            <ProcessingDetailsPanel
                                loanRequest={currentRequest}
                                applicant={currentApplicant}
                                dataSections={currentDataSections}
                                dataSectionDefinitions={dataSectionDefinitions}
                                canUpdateProcessing={canUpdateProcessing}
                                isProcessing={isWorkflowProcessing}
                                updateProcessingDetails={
                                    updateProcessingDetails
                                }
                                loanManagers={loanManagers}
                            />
                            <LoanRequestDocumentChecklistCard
                                documentChecklist={currentDocumentChecklist}
                                generatedDocumentBaseHref={`/admin/requests/${currentRequest.id}/documents/generated`}
                                canGenerateDocuments={canGenerateDocuments}
                                isProcessing={isWorkflowProcessing}
                                onGenerate={(documentKeys) =>
                                    submitGenerateSelectedDocuments(
                                        documentKeys,
                                    )
                                }
                                onRegenerate={async (documentKey) => {
                                    await submitGenerateDocuments(
                                        documentKey as LoanRequestDocumentKey,
                                    );
                                }}
                                packageZipHref={
                                    approvedDocumentHrefs?.packageZip ?? null
                                }
                                lockFinalizedDocuments={[
                                    'approved',
                                    'converted_to_loan',
                                ].includes(currentRequest.status ?? '')}
                            />
                        </>
                    ) : undefined
                }
                correction={{
                    show: canCorrect,
                    isProcessing: isCorrecting,
                    onEdit: () => handleCorrectionOpenChange(true),
                }}
                correctedCopy={
                    canCreateAdminCorrectedCopy
                        ? {
                              isProcessing: isCreatingAdminCorrectedCopy,
                              buttonLabel: 'Create Admin-Corrected Request',
                              dialogTitle: 'Create Admin-Corrected Request',
                              dialogDescription:
                                  'This will create a new corrected request copied from the cancelled request. The cancelled request will remain read-only for audit history.',
                              onCreate: (payload) =>
                                  createAdminCorrectedCopy(
                                      currentRequest.id,
                                      payload,
                                  ),
                          }
                        : undefined
                }
                decision={{
                    show: true,
                    canDecide,
                    blockedMessage,
                    approverName: decision.approverName ?? null,
                    isProcessing: processingIds[currentRequest.id] ?? false,
                    onApprove: (payload) =>
                        updateDecision(currentRequest.id, 'approve', payload),
                    onDecline: (payload) =>
                        updateDecision(currentRequest.id, 'decline', payload),
                }}
                cancellation={{
                    show: showCancellationAction,
                    isProcessing:
                        cancellationProcessingIds[currentRequest.id] ?? false,
                    reasonRequired: true,
                    actionLabel: cancellationActionLabel,
                    dialogTitle: cancellationDialogTitle,
                    dialogDescription: cancellationDialogDescription,
                    confirmLabel: cancellationConfirmLabel,
                    dismissLabel: 'Keep Request',
                    reasonLabel: 'Cancellation reason',
                    reasonPrefill: cancellationReasonPrefill,
                    dialogEventName: cancellationDialogEventName,
                    onSubmit: (payload) =>
                        cancelLoanRequest(currentRequest.id, {
                            cancellation_reason:
                                payload.cancellation_reason ?? '',
                        }),
                }}
                workflow={{
                    claim:
                        canClaim && !canStartReview
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
                }}
            />
            <AdminLoanRequestCorrectionDialog
                open={isCorrectionOpen}
                loanRequest={currentRequest}
                applicant={currentApplicant}
                coMakerOne={currentCoMakerOne}
                coMakerTwo={currentCoMakerTwo}
                loanTypes={loanTypes}
                dataSections={currentDataSections}
                dataSectionDefinitions={dataSectionDefinitions}
                correctionReportContext={correctionReportContext}
                errors={correctionErrors}
                isProcessing={isCorrecting}
                onOpenChange={handleCorrectionOpenChange}
                onSubmit={handleCorrectionSubmit}
            />
            <Dialog
                open={isDismissDialogOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setIsDismissDialogOpen(false);
                        setSelectedReport(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Dismiss correction report</DialogTitle>
                        <DialogDescription>
                            Add optional notes about why this report is being
                            dismissed.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitDismissReport}>
                        <div className="space-y-2">
                            <Label htmlFor="dismiss_admin_notes">
                                Admin notes (optional)
                            </Label>
                            <textarea
                                id="dismiss_admin_notes"
                                className="flex min-h-[112px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50"
                                maxLength={2000}
                                value={dismissNotes}
                                disabled={
                                    selectedReport !== null &&
                                    (dismissProcessingIds[selectedReport.id] ??
                                        false)
                                }
                                onChange={(event) =>
                                    setDismissNotes(event.target.value)
                                }
                            />
                            <div className="text-right text-xs text-muted-foreground">
                                {dismissNotes.length}/2000
                            </div>
                        </div>
                        <DialogFooter className="gap-2 sm:gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsDismissDialogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={
                                    selectedReport === null ||
                                    (selectedReport !== null &&
                                        (dismissProcessingIds[
                                            selectedReport.id
                                        ] ??
                                            false))
                                }
                            >
                                Dismiss report
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
