import { Link } from '@inertiajs/react';
import { Ban, Calendar, Download, PencilLine, Printer } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { LoanRequestAuditTrail } from '@/components/loan-request/loan-request-audit-trail';
import {
    LoanRequestWorkflowActions,
    type LoanRequestWorkflowProps,
} from '@/components/loan-request/loan-request-workflow-actions';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { PageShell } from '@/components/page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    composeAddress,
    formatCurrency,
    formatDate,
    formatDateTime,
    formatDisplayText,
} from '@/lib/formatters';
import { showErrorToast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import type {
    LoanRequestAuditEntry,
    LoanRequestAuditTrailAudience,
    LoanRequestDetail,
    LoanRequestPersonData,
    LoanRequestStatusValue,
} from '@/types/loan-requests';

type Props = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    backHref: string;
    backLabel: string;
    pdfHref: string;
    printHref: string;
    approvedDocumentHrefs?: ApprovedDocumentHrefs | null;
    correctedRequestHref?: string | null;
    auditTrail: LoanRequestAuditEntry[];
    auditTrailAudience?: LoanRequestAuditTrailAudience;
    decision?: DecisionProps;
    cancellation?: CancellationProps;
    correction?: CorrectionProps;
    correctedCopy?: CorrectedCopyProps;
    workflow?: LoanRequestWorkflowProps;
};

type ApprovedDocumentHrefs = {
    applicationForm: string;
    grepalife: string;
    loanSecurityAgreement: string;
    planOfPayment: string;
    undertakingBarangay: string;
    affidavitUndertaking: string;
    authorization: string;
    packageZip?: string | null;
};

const personName = (person?: LoanRequestPersonData | null): string => {
    if (!person) {
        return '--';
    }

    return [person.first_name, person.middle_name, person.last_name]
        .map((value) => formatDisplayText(value))
        .map((value) => value.trim())
        .filter((value) => value !== '')
        .join(' ');
};

type DetailRowProps = {
    label: string;
    value: string;
    className?: string;
};

const displayValue = (value?: string | number | null): string => {
    if (value === null || value === undefined) {
        return '--';
    }

    const stringValue = `${value}`.trim();

    return stringValue !== '' ? stringValue : '--';
};

const displayText = (value?: string | null): string => {
    const normalized = formatDisplayText(value);

    return normalized !== '' ? normalized : '--';
};

const resolveAddress = (person?: LoanRequestPersonData | null): string => {
    if (!person) {
        return '';
    }

    const composed = composeAddress(
        person.address1,
        person.address2,
        person.address3,
    );

    return composed !== '' ? composed : (person.address ?? '');
};

const resolveEmployerBusinessAddress = (
    person?: LoanRequestPersonData | null,
): string => {
    if (!person) {
        return '';
    }

    const composed = composeAddress(
        person.employer_business_address1,
        person.employer_business_address2,
        person.employer_business_address3,
    );

    return composed !== ''
        ? composed
        : (person.employer_business_address ?? '');
};

const displayCurrency = (value?: string | number | null): string => {
    if (value === null || value === undefined || `${value}`.trim() === '') {
        return '--';
    }

    const numericValue = Number(value);

    return Number.isNaN(numericValue)
        ? `${value}`
        : formatCurrency(numericValue);
};

const displayDateValue = (value?: string | null): string =>
    value ? formatDate(value) : '--';

const DetailRow = ({ label, value, className }: DetailRowProps) => (
    <div className={cn('space-y-1', className)}>
        <p className="text-xs text-muted-foreground">{label}</p>
        <p className="text-sm leading-relaxed font-medium">{value}</p>
    </div>
);

const statusLabels: Record<LoanRequestStatusValue, string> = {
    draft: 'Draft',
    submitted: 'Submitted',
    pending_review: 'Pending Review',
    pending_co_maker_signatures: 'Pending Co-Maker Signatures',
    under_review: 'Under Review',
    needs_revision: 'Needs Revision',
    recommended_for_approval: 'Recommended for Approval',
    rejected: 'Rejected',
    approved: 'Approved',
    declined: 'Declined',
    converted_to_loan: 'Converted to Loan',
    cancelled: 'Cancelled',
};

const statusDescriptions: Record<LoanRequestStatusValue, string> = {
    draft: 'Complete the form and submit when you are ready.',
    submitted: 'This legacy request has already been submitted for review.',
    pending_review: 'This request is waiting for a loan officer to start the review.',
    pending_co_maker_signatures:
        'This request is still waiting for the required co-maker signatures.',
    under_review: 'A loan officer is actively reviewing this request.',
    needs_revision:
        'The member needs to revise the request before review can continue.',
    recommended_for_approval:
        'The officer review is complete and the request is waiting for manager approval.',
    rejected: 'This request was rejected during officer review.',
    approved: 'This request is approved and can now be converted into a loan.',
    declined: 'This request was declined after manager review.',
    converted_to_loan:
        'This request has already been converted into an actual loan record.',
    cancelled:
        'This request was cancelled and remains available as read-only history.',
};

const resolveTimelineSteps = (
    status: LoanRequestStatusValue,
    showApprovedCancellationHistory: boolean,
): LoanRequestStatusValue[] => {
    if (status === 'needs_revision') {
        return ['draft', 'pending_review', 'under_review', 'needs_revision'];
    }

    if (status === 'rejected') {
        return ['draft', 'pending_review', 'under_review', 'rejected'];
    }

    if (status === 'declined') {
        return [
            'draft',
            'pending_review',
            'under_review',
            'recommended_for_approval',
            'declined',
        ];
    }

    if (status === 'recommended_for_approval') {
        return [
            'draft',
            'pending_review',
            'under_review',
            'recommended_for_approval',
        ];
    }

    if (status === 'approved') {
        return [
            'draft',
            'pending_review',
            'under_review',
            'recommended_for_approval',
            'approved',
        ];
    }

    if (status === 'converted_to_loan') {
        return [
            'draft',
            'pending_review',
            'under_review',
            'recommended_for_approval',
            'approved',
            'converted_to_loan',
        ];
    }

    if (status === 'cancelled') {
        return showApprovedCancellationHistory
            ? [
                  'draft',
                  'pending_review',
                  'under_review',
                  'approved',
                  'cancelled',
              ]
            : ['draft', 'pending_review', 'under_review', 'cancelled'];
    }

    if (status === 'submitted') {
        return ['draft', 'submitted'];
    }

    if (status === 'pending_co_maker_signatures') {
        return ['draft', 'pending_co_maker_signatures'];
    }

    if (status === 'pending_review') {
        return ['draft', 'pending_review'];
    }

    if (status === 'under_review') {
        return ['draft', 'pending_review', 'under_review'];
    }

    return ['draft'];
};

type SummaryStatProps = {
    label: string;
    value: string;
};

type DecisionProps = {
    show?: boolean;
    canDecide?: boolean;
    isProcessing?: boolean;
    blockedMessage?: string | null;
    approverName?: string | null;
    onApprove?: (payload: LoanRequestApprovePayload) => void;
    onDecline?: (payload: LoanRequestDeclinePayload) => void;
};

type CancellationProps = {
    show?: boolean;
    isProcessing?: boolean;
    reasonRequired?: boolean;
    actionLabel?: string;
    dialogTitle?: string;
    dialogDescription?: string;
    confirmLabel?: string;
    dismissLabel?: string;
    reasonLabel?: string;
    reasonPrefill?: string | null;
    dialogEventName?: string | null;
    onSubmit?: (
        payload: LoanRequestCancellationPayload,
    ) => Promise<LoanRequestDetail | { loanRequest: LoanRequestDetail } | null>;
};

type CorrectionProps = {
    show?: boolean;
    isProcessing?: boolean;
    onEdit?: () => void;
};

type CorrectedCopyProps = {
    show?: boolean;
    isProcessing?: boolean;
    buttonLabel?: string;
    dialogTitle?: string;
    dialogDescription?: string;
    confirmLabel?: string;
    onCreate?: (
        payload: LoanRequestCorrectedCopyPayload,
    ) => Promise<{ loanRequest: { id: number } } | null>;
};

type LoanRequestApprovePayload = {
    approved_amount: string;
    approved_term: string;
    decision_notes?: string | null;
};

type LoanRequestDeclinePayload = {
    decision_notes?: string | null;
};

type LoanRequestCancellationPayload = {
    cancellation_reason?: string | null;
};

type LoanRequestCorrectedCopyPayload = {
    correction_reason: string;
};

const SummaryStat = ({ label, value }: SummaryStatProps) => (
    <div className="rounded-xl border border-border/20 bg-muted/10 p-3">
        <p className="text-xs text-muted-foreground">{label}</p>
        <p className="text-sm font-semibold text-foreground">{value}</p>
    </div>
);

type PersonPanelProps = {
    title: string;
    person: LoanRequestPersonData | null;
};

const PersonPanel = ({ title, person }: PersonPanelProps) => (
    <div className="rounded-xl border border-border/20 bg-muted/10 p-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
            <p className="text-sm font-semibold text-foreground">{title}</p>
            <span className="text-xs text-muted-foreground">
                {person ? 'Details captured' : 'No details available'}
            </span>
        </div>
        <div className="mt-4 grid gap-4 md:grid-cols-2">
            <DetailRow label="Name" value={personName(person)} />
            <DetailRow label="Cell no." value={displayValue(person?.cell_no)} />
            <DetailRow
                label="Address"
                value={displayText(resolveAddress(person))}
            />
            <DetailRow
                label="Employer/Business"
                value={displayText(person?.employer_business_name)}
            />
        </div>
    </div>
);

const textareaClassName =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex min-h-[112px] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50';

const defaultApprovalBlockedMessage =
    'Please save the correction before approving this admin-corrected request.';

export function LoanRequestDetailPage({
    loanRequest,
    applicant,
    coMakerOne,
    coMakerTwo,
    backHref,
    backLabel,
    pdfHref,
    printHref,
    approvedDocumentHrefs = null,
    correctedRequestHref = null,
    auditTrail,
    auditTrailAudience = 'staff',
    decision,
    cancellation,
    correction,
    correctedCopy,
    workflow,
}: Props) {
    const submittedAt = loanRequest.submitted_at
        ? formatDate(loanRequest.submitted_at)
        : null;
    const statusValue = (loanRequest.status ?? 'draft') as LoanRequestStatusValue;
    const showApprovedCancellationHistory =
        statusValue === 'cancelled' &&
        (loanRequest.approved_by !== null ||
            loanRequest.approved_amount !== null ||
            loanRequest.approved_term !== null);
    const timelineSteps = resolveTimelineSteps(
        statusValue,
        showApprovedCancellationHistory,
    );
    const currentStatusIndex = Math.max(
        0,
        timelineSteps.indexOf(statusValue),
    );
    const canDownloadPdf =
        statusValue === 'submitted' ||
        statusValue === 'pending_review' ||
        statusValue === 'under_review' ||
        statusValue === 'approved' ||
        statusValue === 'converted_to_loan' ||
        statusValue === 'declined' ||
        statusValue === 'cancelled';
    const showCorrectionAction =
        statusValue === 'under_review' && (correction?.show ?? false);
    const amount = displayCurrency(loanRequest.requested_amount);
    const loanTypeLabel = displayText(loanRequest.loan_type_label_snapshot);
    const requestedTerm =
        loanRequest.requested_term !== null &&
        loanRequest.requested_term !== undefined &&
        `${loanRequest.requested_term}`.trim() !== ''
            ? `${loanRequest.requested_term} months`
            : '--';
    const availmentStatus = displayValue(loanRequest.availment_status);
    const loanPurpose = displayText(loanRequest.loan_purpose);
    const submittedLabel = submittedAt
        ? `Submitted ${submittedAt}`
        : 'Not submitted yet';
    const showDecisionForm =
        (decision?.show ?? false) &&
        decision?.canDecide &&
        statusValue === 'under_review';
    const blockedMessage =
        statusValue === 'under_review' ? (decision?.blockedMessage ?? null) : null;
    const approvalBlockedMessage = blockedMessage;
    const canApprove = showDecisionForm && approvalBlockedMessage === null;
    const showDecisionSummary =
        statusValue === 'needs_revision' ||
        statusValue === 'recommended_for_approval' ||
        statusValue === 'rejected' ||
        statusValue === 'approved' ||
        statusValue === 'declined' ||
        statusValue === 'converted_to_loan' ||
        statusValue === 'cancelled' ||
        loanRequest.reviewed_by !== null ||
        (loanRequest.review_remarks ?? '').trim() !== '' ||
        loanRequest.approved_by !== null ||
        loanRequest.declined_by !== null;
    const showCancellationAction =
        (cancellation?.show ?? false) &&
        typeof cancellation?.onSubmit === 'function';
    const showApprovedDocuments =
        (statusValue === 'approved' || statusValue === 'converted_to_loan') &&
        approvedDocumentHrefs !== null;
    const showDownloadPdfAction = canDownloadPdf && !showApprovedDocuments;
    const showPrintAction = canDownloadPdf;
    const approvedDocumentItems = showApprovedDocuments
        ? [
              {
                  label: 'Application Form PDF',
                  href: approvedDocumentHrefs.applicationForm,
                  format: 'PDF',
              },
              {
                  label: 'GREPALIFE PDF',
                  href: approvedDocumentHrefs.grepalife,
                  format: 'PDF',
              },
              {
                  label: 'Loan Security Agreement PDF',
                  href: approvedDocumentHrefs.loanSecurityAgreement,
                  format: 'PDF',
              },
              {
                  label: 'Plan of Payment Excel',
                  href: approvedDocumentHrefs.planOfPayment,
                  format: 'XLSX',
              },
              {
                  label: 'Undertaking - Barangay PDF',
                  href: approvedDocumentHrefs.undertakingBarangay,
                  format: 'PDF',
              },
              {
                  label: 'Affidavit of Undertaking PDF',
                  href: approvedDocumentHrefs.affidavitUndertaking,
                  format: 'PDF',
              },
               {
                   label: 'Authorization PDF',
                   href: approvedDocumentHrefs.authorization,
                   format: 'PDF',
               },
          ]
        : [];
    const showCorrectedCopyAction =
        statusValue === 'cancelled' &&
        (correctedCopy?.show ?? true) &&
        typeof correctedCopy?.onCreate === 'function';
    const showCancelledNotice = statusValue === 'cancelled';
    const [isApprovalDialogOpen, setIsApprovalDialogOpen] = useState(false);
    const [approvalConfirmed, setApprovalConfirmed] = useState(false);
    const [isCancellationDialogOpen, setIsCancellationDialogOpen] =
        useState(false);
    const [isCorrectedCopyDialogOpen, setIsCorrectedCopyDialogOpen] =
        useState(false);
    const [correctedCopyReason, setCorrectedCopyReason] = useState('');
    const [correctedCopyReasonError, setCorrectedCopyReasonError] = useState<
        string | null
    >(null);
    const [cancellationReason, setCancellationReason] = useState('');
    const [cancellationReasonError, setCancellationReasonError] = useState<
        string | null
    >(null);
    const [approvedAmount, setApprovedAmount] = useState(() => {
        const initial =
            loanRequest.approved_amount ?? loanRequest.requested_amount ?? '';
        return `${initial}`.trim() !== '' ? `${initial}` : '';
    });
    const [approvedTerm, setApprovedTerm] = useState(() => {
        const initial =
            loanRequest.approved_term ?? loanRequest.requested_term ?? '';
        return `${initial}`.trim() !== '' ? `${initial}` : '';
    });
    const [decisionNotes, setDecisionNotes] = useState(() =>
        loanRequest.decision_notes ? loanRequest.decision_notes : '',
    );
    const reviewedBy = loanRequest.reviewed_by?.name ?? '--';
    const reviewedAt = displayDateValue(loanRequest.reviewed_at);
    const assignedOfficer = loanRequest.assigned_officer?.name ?? '--';
    const reviewDecisionValue = displayText(loanRequest.review_decision);
    const reviewRemarksValue = displayText(loanRequest.review_remarks);
    const rejectedBy = loanRequest.rejected_by?.name ?? '--';
    const rejectedAt = displayDateValue(loanRequest.rejected_at);
    const rejectionReasonValue = displayText(loanRequest.rejection_reason);
    const approvalSignerName = decision?.approverName?.trim() || '--';
    const approvedBy = loanRequest.approved_by?.name ?? '--';
    const approvedAt = displayDateValue(loanRequest.approved_at);
    const approvedAmountValue = displayCurrency(loanRequest.approved_amount);
    const approvedTermValue =
        loanRequest.approved_term !== null &&
        loanRequest.approved_term !== undefined &&
        `${loanRequest.approved_term}`.trim() !== ''
            ? `${loanRequest.approved_term} months`
            : '--';
    const approvedInterestRateValue = displayValue(
        loanRequest.approved_interest_rate,
    );
    const approvalRemarksValue = displayText(loanRequest.approval_remarks);
    const decisionNotesValue = displayText(loanRequest.decision_notes);
    const declinedBy = loanRequest.declined_by?.name ?? '--';
    const declinedAt = displayDateValue(loanRequest.declined_at);
    const declineReasonValue = displayText(loanRequest.decline_reason);
    const cancelledBy = loanRequest.cancelled_by?.name ?? '--';
    const cancelledAt = displayDateValue(loanRequest.cancelled_at);
    const cancellationReasonValue = displayText(
        loanRequest.cancellation_reason,
    );
    const correctedRequestReference = displayText(
        loanRequest.corrected_request_reference,
    );
    const correctedRequestStatus = displayText(
        loanRequest.corrected_request_status,
    );

    useEffect(() => {
        const nextAmount =
            loanRequest.approved_amount ?? loanRequest.requested_amount ?? '';
        const nextTerm =
            loanRequest.approved_term ?? loanRequest.requested_term ?? '';

        setApprovedAmount(`${nextAmount}`.trim() !== '' ? `${nextAmount}` : '');
        setApprovedTerm(`${nextTerm}`.trim() !== '' ? `${nextTerm}` : '');
        setDecisionNotes(loanRequest.decision_notes ?? '');
    }, [
        loanRequest.approved_amount,
        loanRequest.approved_term,
        loanRequest.decision_notes,
        loanRequest.requested_amount,
        loanRequest.requested_term,
    ]);

    const closeApprovalDialog = (force = false) => {
        if (decision?.isProcessing && !force) {
            return;
        }

        setIsApprovalDialogOpen(false);
        setApprovalConfirmed(false);
    };

    const closeCancellationDialog = (force = false) => {
        if (cancellation?.isProcessing && !force) {
            return;
        }

        setIsCancellationDialogOpen(false);
        setCancellationReason('');
        setCancellationReasonError(null);
    };

    const openCancellationDialog = (prefill?: string | null) => {
        const normalizedPrefill = prefill?.trim() ?? '';
        setCancellationReason(normalizedPrefill);
        setCancellationReasonError(null);
        setIsCancellationDialogOpen(true);
    };

    const closeCorrectedCopyDialog = (force = false) => {
        if (correctedCopy?.isProcessing && !force) {
            return;
        }

        setIsCorrectedCopyDialogOpen(false);
        setCorrectedCopyReason('');
        setCorrectedCopyReasonError(null);
    };

    const submitCancellation = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const reason = cancellationReason.trim();
        const reasonRequired = cancellation?.reasonRequired ?? false;

        if (reasonRequired && reason === '') {
            setCancellationReasonError('Cancellation reason is required.');
            return;
        }

        setCancellationReasonError(null);

        const updated = await cancellation?.onSubmit?.({
            cancellation_reason: reason !== '' ? reason : null,
        });

        if (updated) {
            closeCancellationDialog(true);
        }
    };

    const submitCorrectedCopy = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const reason = correctedCopyReason.trim();

        if (reason === '') {
            setCorrectedCopyReasonError('Correction reason is required.');
            return;
        }

        setCorrectedCopyReasonError(null);

        const result = await correctedCopy?.onCreate?.({
            correction_reason: reason,
        });

        if (result) {
            closeCorrectedCopyDialog(true);
        }
    };

    const openApprovalDialog = () => {
        if (!canApprove) {
            showErrorToast(
                approvalBlockedMessage ?? defaultApprovalBlockedMessage,
                approvalBlockedMessage ?? defaultApprovalBlockedMessage,
            );
            return;
        }

        setApprovalConfirmed(false);
        setIsApprovalDialogOpen(true);
    };

    useEffect(() => {
        const eventName = cancellation?.dialogEventName?.trim() ?? '';

        if (eventName === '' || typeof window === 'undefined') {
            return;
        }

        const listener = (event: Event) => {
            const fallbackPrefill = cancellation?.reasonPrefill ?? null;

            if (!(event instanceof CustomEvent)) {
                openCancellationDialog(fallbackPrefill);
                return;
            }

            const detail = event.detail as { prefill?: string } | undefined;
            const prefill =
                typeof detail?.prefill === 'string' ? detail.prefill : null;

            openCancellationDialog(prefill ?? fallbackPrefill);
        };

        window.addEventListener(eventName, listener);

        return () => {
            window.removeEventListener(eventName, listener);
        };
    }, [cancellation?.dialogEventName, cancellation?.reasonPrefill]);

    return (
        <PageShell size="wide" className="gap-8">
            <div className="rounded-2xl border border-border/40 bg-card/60 p-6 shadow-sm sm:p-7 lg:p-8">
                <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-3">
                        <p className="text-xs font-semibold tracking-[0.24em] text-muted-foreground uppercase">
                            Loan request
                        </p>
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-3xl font-semibold tracking-tight">
                                Request {loanRequest.reference}
                            </h1>
                            <LoanRequestStatusBadge
                                status={loanRequest.status}
                                className="text-xs"
                            />
                        </div>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <Calendar className="h-4 w-4" />
                            <span>{submittedLabel}</span>
                        </div>
                        <p className="max-w-2xl text-sm text-muted-foreground">
                            Review the submitted snapshot of this loan request,
                            including the applicant and co-maker details used
                            for evaluation.
                        </p>
                    </div>
                    <div className="grid w-full gap-3 sm:max-w-md sm:grid-cols-2">
                        <SummaryStat label="Requested amount" value={amount} />
                        <SummaryStat label="Loan type" value={loanTypeLabel} />
                        <SummaryStat
                            label="Requested term"
                            value={requestedTerm}
                        />
                        <SummaryStat
                            label="Availment status"
                            value={availmentStatus}
                        />
                    </div>
                </div>
            </div>

            {showCancelledNotice ? (
                <Alert className="border-amber-500/35 bg-amber-500/10 text-foreground">
                    <Ban className="size-4 text-amber-700 dark:text-amber-200" />
                    <AlertTitle className="line-clamp-none">
                        This request was cancelled
                    </AlertTitle>
                    <AlertDescription className="w-full gap-3 text-foreground/90">
                        <p>
                            Please review the cancellation reason before
                            continuing with any next steps. If a corrected
                            request is needed, use the cancelled request as a
                            reference and resubmit with the right details.
                        </p>
                        <div className="w-full rounded-lg border border-amber-500/35 bg-background/85 px-3 py-2">
                            <p className="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                                Cancellation reason
                            </p>
                            <p className="mt-1 text-sm font-medium text-foreground">
                                {cancellationReasonValue}
                            </p>
                        </div>
                        {loanRequest.corrected_request_id !== null ? (
                            <div className="w-full rounded-lg border border-amber-500/35 bg-background/85 px-3 py-2">
                                <p className="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase">
                                    Corrected request
                                </p>
                                <p className="mt-1 text-sm font-medium text-foreground">
                                    {correctedRequestHref ? (
                                        <Link
                                            href={correctedRequestHref}
                                            className="underline underline-offset-2"
                                        >
                                            {correctedRequestReference}
                                        </Link>
                                    ) : (
                                        correctedRequestReference
                                    )}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Status: {correctedRequestStatus}
                                </p>
                            </div>
                        ) : null}
                    </AlertDescription>
                </Alert>
            ) : null}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <div className="space-y-6">
                    <Card className="border-border/30 bg-card/60 shadow-sm">
                        <CardHeader>
                            <CardTitle>Loan details</CardTitle>
                            <CardDescription>
                                Key request information captured at submission.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <DetailRow
                                    label="Loan type"
                                    value={loanTypeLabel}
                                />
                                <DetailRow
                                    label="Requested amount"
                                    value={amount}
                                />
                                <DetailRow
                                    label="Requested term"
                                    value={requestedTerm}
                                />
                                <DetailRow
                                    label="Availment status"
                                    value={availmentStatus}
                                />
                            </div>
                            <Separator className="bg-border/40" />
                            <DetailRow
                                label="Loan purpose"
                                value={loanPurpose}
                            />
                        </CardContent>
                    </Card>

                    <Card className="border-border/30 bg-card/60 shadow-sm">
                        <CardHeader>
                            <CardTitle>Applicant</CardTitle>
                            <CardDescription>
                                Primary borrower details from the request.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6 lg:grid-cols-2">
                            <div className="space-y-3">
                                <p className="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                    Personal
                                </p>
                                <DetailRow
                                    label="Full name"
                                    value={personName(applicant)}
                                />
                                <DetailRow
                                    label="Cell no."
                                    value={displayValue(applicant?.cell_no)}
                                />
                                <DetailRow
                                    label="Address"
                                    value={displayText(
                                        resolveAddress(applicant),
                                    )}
                                />
                                <DetailRow
                                    label="Birthdate"
                                    value={displayDateValue(
                                        applicant?.birthdate,
                                    )}
                                />
                                <DetailRow
                                    label="Civil status"
                                    value={displayValue(
                                        applicant?.civil_status,
                                    )}
                                />
                                <DetailRow
                                    label="Number of children"
                                    value={displayValue(
                                        applicant?.number_of_children,
                                    )}
                                />
                            </div>
                            <div className="space-y-3">
                                <p className="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                    Work & finances
                                </p>
                                <DetailRow
                                    label="Employment type"
                                    value={displayValue(
                                        applicant?.employment_type,
                                    )}
                                />
                                <DetailRow
                                    label="Employer/Business"
                                    value={displayText(
                                        applicant?.employer_business_name,
                                    )}
                                />
                                <DetailRow
                                    label="Business address"
                                    value={displayText(
                                        resolveEmployerBusinessAddress(
                                            applicant,
                                        ),
                                    )}
                                />
                                <DetailRow
                                    label="Current position"
                                    value={displayText(
                                        applicant?.current_position,
                                    )}
                                />
                                <DetailRow
                                    label="Gross monthly income"
                                    value={displayCurrency(
                                        applicant?.gross_monthly_income,
                                    )}
                                />
                                <DetailRow
                                    label="Payday"
                                    value={displayValue(applicant?.payday)}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/30 bg-card/60 shadow-sm">
                        <CardHeader>
                            <CardTitle>Co-makers</CardTitle>
                            <CardDescription>
                                Supporting borrowers tied to this request.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <PersonPanel
                                title="Co-maker 1"
                                person={coMakerOne}
                            />
                            <PersonPanel
                                title="Co-maker 2"
                                person={coMakerTwo}
                            />
                        </CardContent>
                    </Card>

                    <LoanRequestAuditTrail
                        entries={auditTrail}
                        audience={auditTrailAudience}
                    />
                </div>

                <div className="space-y-4 lg:sticky lg:top-24">
                    <Card className="border-border/30 bg-card/50 shadow-sm">
                        <CardHeader className="space-y-2">
                            <div className="flex items-center justify-between gap-2">
                                <CardTitle className="text-base">
                                    Request status
                                </CardTitle>
                                <LoanRequestStatusBadge
                                    status={loanRequest.status}
                                />
                            </div>
                            <CardDescription>
                                {statusDescriptions[statusValue]}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="relative">
                                <span
                                    aria-hidden="true"
                                    className="absolute left-3 w-px rounded-full bg-primary/50"
                                    style={{
                                        top: '0.3125rem',
                                        bottom: '0.3125rem',
                                    }}
                                />
                                <div className="space-y-5">
                                    {timelineSteps.map((status, index) => {
                                        const isCurrent = status === statusValue;
                                        const isComplete =
                                            index < currentStatusIndex;

                                        return (
                                            <div
                                                key={status}
                                                className="flex gap-2.5"
                                            >
                                                <div className="flex w-6 items-start justify-center">
                                                    <span
                                                        className={cn(
                                                            'relative z-10 rounded-full border transition-colors',
                                                            isComplete
                                                                ? 'h-2.5 w-2.5 border-primary/70 bg-primary/60'
                                                                : isCurrent
                                                                  ? 'h-3.5 w-3.5 border-primary bg-primary shadow-sm ring-4 shadow-primary/40 ring-primary/20'
                                                                  : 'h-2 w-2 border-border/50 bg-card',
                                                        )}
                                                    />
                                                </div>
                                                <div className="space-y-1">
                                                    <p
                                                        className={cn(
                                                            'text-sm',
                                                            isCurrent
                                                                ? 'font-semibold text-foreground'
                                                                : isComplete
                                                                  ? 'font-medium text-foreground/70'
                                                                  : 'font-medium text-muted-foreground/70',
                                                        )}
                                                    >
                                                        {statusLabels[status]}
                                                    </p>
                                                    {isCurrent ? (
                                                        <p className="text-xs text-muted-foreground">
                                                            {
                                                                statusDescriptions[
                                                                    statusValue
                                                                ]
                                                            }
                                                        </p>
                                                    ) : null}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                            <div className="rounded-lg border border-border/30 bg-muted/10 p-3 text-xs text-muted-foreground">
                                Workflow actions stay in sync with the current
                                request status and your server-side access.
                            </div>
                        </CardContent>
                    </Card>

                    {showDecisionForm || showDecisionSummary ? (
                        <Card className="border-border/30 bg-card/50 shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Workflow details
                                </CardTitle>
                                <CardDescription>
                                    Review the latest workflow notes and
                                    decision details for this request.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {showDecisionForm ? (
                                    <>
                                        <div className="space-y-2">
                                            <Label htmlFor="approved_amount">
                                                Approved amount
                                            </Label>
                                            <Input
                                                id="approved_amount"
                                                type="number"
                                                inputMode="decimal"
                                                min="1"
                                                step="0.01"
                                                placeholder="Enter approved amount"
                                                value={approvedAmount}
                                                onChange={(event) =>
                                                    setApprovedAmount(
                                                        event.target.value,
                                                    )
                                                }
                                                disabled={
                                                    decision?.isProcessing
                                                }
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="approved_term">
                                                Approved term (months)
                                            </Label>
                                            <Input
                                                id="approved_term"
                                                type="number"
                                                inputMode="numeric"
                                                min="1"
                                                step="1"
                                                placeholder="Enter approved term"
                                                value={approvedTerm}
                                                onChange={(event) =>
                                                    setApprovedTerm(
                                                        event.target.value,
                                                    )
                                                }
                                                disabled={
                                                    decision?.isProcessing
                                                }
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="decision_notes">
                                                Decision notes
                                            </Label>
                                            <textarea
                                                id="decision_notes"
                                                className="flex min-h-[96px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50"
                                                placeholder="Add optional notes for the member"
                                                value={decisionNotes}
                                                onChange={(event) =>
                                                    setDecisionNotes(
                                                        event.target.value,
                                                    )
                                                }
                                                disabled={
                                                    decision?.isProcessing
                                                }
                                            />
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                onClick={openApprovalDialog}
                                                disabled={
                                                    decision?.isProcessing
                                                }
                                            >
                                                Approve
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    decision?.onDecline?.({
                                                        decision_notes:
                                                            decisionNotes
                                                                ? decisionNotes
                                                                : null,
                                                    })
                                                }
                                                disabled={
                                                    decision?.isProcessing
                                                }
                                            >
                                                Decline
                                            </Button>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {approvalBlockedMessage ??
                                                'Only requests under review can be decided.'}
                                        </p>
                                    </>
                                ) : (
                                    <div className="space-y-3 text-sm">
                                        <DetailRow
                                            label="Status"
                                            value={statusLabels[statusValue] ?? '--'}
                                        />
                                        {loanRequest.assigned_officer !== null ? (
                                            <DetailRow
                                                label="Assigned officer"
                                                value={assignedOfficer}
                                            />
                                        ) : null}
                                        <DetailRow
                                            label="Reviewed by"
                                            value={reviewedBy}
                                        />
                                        <DetailRow
                                            label="Reviewed at"
                                            value={reviewedAt}
                                        />
                                        {loanRequest.review_decision ? (
                                            <DetailRow
                                                label="Review decision"
                                                value={reviewDecisionValue}
                                            />
                                        ) : null}
                                        {loanRequest.review_remarks ? (
                                            <DetailRow
                                                label={
                                                    statusValue ===
                                                    'needs_revision'
                                                        ? 'Revision remarks'
                                                        : 'Review remarks'
                                                }
                                                value={reviewRemarksValue}
                                            />
                                        ) : null}
                                        {statusValue === 'rejected' ? (
                                            <>
                                                <DetailRow
                                                    label="Rejected by"
                                                    value={rejectedBy}
                                                />
                                                <DetailRow
                                                    label="Rejected at"
                                                    value={rejectedAt}
                                                />
                                                <DetailRow
                                                    label="Rejection reason"
                                                    value={rejectionReasonValue}
                                                />
                                            </>
                                        ) : null}
                                        {statusValue === 'approved' ||
                                        statusValue === 'converted_to_loan' ||
                                        (statusValue === 'cancelled' &&
                                            showApprovedCancellationHistory) ? (
                                            <>
                                                <DetailRow
                                                    label="Approved by"
                                                    value={approvedBy}
                                                />
                                                <DetailRow
                                                    label="Approved at"
                                                    value={approvedAt}
                                                />
                                                <DetailRow
                                                    label="Approved amount"
                                                    value={approvedAmountValue}
                                                />
                                                <DetailRow
                                                    label="Approved term"
                                                    value={approvedTermValue}
                                                />
                                                {loanRequest.approved_interest_rate !==
                                                null ? (
                                                    <DetailRow
                                                        label="Approved interest rate"
                                                        value={
                                                            approvedInterestRateValue
                                                        }
                                                    />
                                                ) : null}
                                                {loanRequest.approval_remarks ? (
                                                    <DetailRow
                                                        label="Approval remarks"
                                                        value={
                                                            approvalRemarksValue
                                                        }
                                                    />
                                                ) : null}
                                            </>
                                        ) : null}
                                        {loanRequest.decision_notes ? (
                                            <DetailRow
                                                label="Decision notes"
                                                value={decisionNotesValue}
                                            />
                                        ) : null}
                                        {statusValue === 'declined' ? (
                                            <>
                                                <DetailRow
                                                    label="Declined by"
                                                    value={declinedBy}
                                                />
                                                <DetailRow
                                                    label="Declined at"
                                                    value={declinedAt}
                                                />
                                                <DetailRow
                                                    label="Decline reason"
                                                    value={declineReasonValue}
                                                />
                                            </>
                                        ) : null}
                                        {statusValue === 'cancelled' ? (
                                            <>
                                                <DetailRow
                                                    label="Cancelled by"
                                                    value={cancelledBy}
                                                />
                                                <DetailRow
                                                    label="Cancelled at"
                                                    value={cancelledAt}
                                                />
                                                <DetailRow
                                                    label="Cancellation reason"
                                                    value={
                                                        cancellationReasonValue
                                                    }
                                                />
                                            </>
                                        ) : null}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ) : null}

                    <Card className="border-border/30 bg-card/50 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Quick facts
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <DetailRow
                                label="Requested amount"
                                value={amount}
                            />
                            <DetailRow
                                label="Loan type"
                                value={loanTypeLabel}
                            />
                            <DetailRow
                                label="Requested term"
                                value={requestedTerm}
                            />
                            <DetailRow
                                label="Availment status"
                                value={availmentStatus}
                            />
                        </CardContent>
                    </Card>

                    <Card className="border-border/30 bg-card/50 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-base">Actions</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {showCorrectionAction ? (
                                <div className="space-y-3">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="w-full justify-start"
                                        disabled={correction?.isProcessing}
                                        onClick={correction?.onEdit}
                                    >
                                        <PencilLine />
                                        Edit request details
                                    </Button>
                                    <Separator className="bg-border/40" />
                                </div>
                            ) : null}
                            <LoanRequestWorkflowActions
                                loanRequest={loanRequest}
                                workflow={workflow}
                            />
                            {showPrintAction ? (
                                <div className="space-y-3">
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="w-full justify-start"
                                    >
                                        <a
                                            href={printHref}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <Printer />
                                            Print application
                                        </a>
                                    </Button>
                                    <Separator className="bg-border/40" />
                                </div>
                            ) : null}
                            {showDownloadPdfAction ? (
                                <div className="space-y-3">
                                    <Button
                                        asChild
                                        className="w-full justify-start"
                                    >
                                        <a href={pdfHref}>
                                            <Download />
                                            Download PDF
                                        </a>
                                    </Button>
                                    <Separator className="bg-border/40" />
                                </div>
                            ) : null}
                            {showApprovedDocuments ? (
                                <div className="space-y-3">
                                    <div className="space-y-1">
                                        <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                            Approved documents
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Download each approved loan document
                                            individually, or download all as
                                            ZIP.
                                        </p>
                                    </div>
                                    <div className="grid gap-2">
                                        {approvedDocumentItems.map(
                                            (document) => (
                                                <Button
                                                    key={document.label}
                                                    asChild
                                                    variant="outline"
                                                    className="h-11 w-full justify-start px-3"
                                                >
                                                    <a
                                                        href={document.href}
                                                        className="flex w-full min-w-0 items-center gap-2"
                                                    >
                                                        <Download className="size-4 shrink-0" />
                                                        <span className="min-w-0 flex-1 truncate text-left text-sm">
                                                            {document.label}
                                                        </span>
                                                        <span className="shrink-0 rounded-full border border-border/60 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">
                                                            {document.format}
                                                        </span>
                                                    </a>
                                                </Button>
                                            ),
                                        )}
                                        {approvedDocumentHrefs.packageZip ? (
                                            <Button
                                                asChild
                                                className="mt-1 h-11 w-full justify-start px-3 shadow-sm"
                                            >
                                                <a
                                                    href={
                                                        approvedDocumentHrefs.packageZip
                                                    }
                                                    className="flex w-full min-w-0 items-center gap-2"
                                                >
                                                    <Download className="size-4 shrink-0" />
                                                    <span className="min-w-0 flex-1 text-left text-sm font-semibold">
                                                        Download All as ZIP
                                                    </span>
                                                </a>
                                            </Button>
                                        ) : null}
                                    </div>
                                    <Separator className="bg-border/40" />
                                </div>
                            ) : null}
                            {showCancellationAction ? (
                                <div className="space-y-3 rounded-lg border border-destructive/30 bg-destructive/5 p-3">
                                    <p className="text-xs font-semibold tracking-wide text-destructive uppercase">
                                        {statusValue === 'approved'
                                            ? 'Danger zone'
                                            : 'Application action'}
                                    </p>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        className="w-full justify-start"
                                        disabled={cancellation?.isProcessing}
                                        onClick={() =>
                                            openCancellationDialog(
                                                cancellation?.reasonPrefill ??
                                                    null,
                                            )
                                        }
                                    >
                                        <Ban />
                                        {cancellation?.actionLabel ??
                                            'Cancel Application'}
                                    </Button>
                                </div>
                            ) : null}
                            {showCorrectedCopyAction ? (
                                <div className="space-y-3">
                                    <Button
                                        type="button"
                                        className="w-full justify-center"
                                        disabled={correctedCopy?.isProcessing}
                                        onClick={() =>
                                            setIsCorrectedCopyDialogOpen(true)
                                        }
                                    >
                                        {correctedCopy?.buttonLabel ??
                                            'Create Admin-Corrected Request'}
                                    </Button>
                                    <Separator className="bg-border/40" />
                                </div>
                            ) : null}
                            <Button
                                asChild
                                variant="ghost"
                                className="w-full justify-start"
                            >
                                <Link href={backHref}>{backLabel}</Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="border-border/20 bg-card/30">
                        <CardHeader>
                            <CardTitle className="text-base">
                                What happens next
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            {statusValue === 'draft'
                                ? 'Finish the application and submit to begin the review.'
                                : statusValue === 'pending_review'
                                  ? 'A loan officer can now pick this request up and start the review.'
                                  : statusValue === 'under_review'
                                    ? 'The request is under active review by a loan officer.'
                                    : statusValue === 'needs_revision'
                                      ? 'The member needs to update the request before it can continue.'
                                      : statusValue ===
                                          'recommended_for_approval'
                                        ? 'A loan manager can now approve or decline this request.'
                                        : statusValue === 'approved'
                                          ? 'The request can now be converted into an actual loan.'
                                          : statusValue ===
                                              'converted_to_loan'
                                            ? 'The request is already linked to a created loan record.'
                                            : statusValue === 'declined' ||
                                                statusValue === 'rejected'
                                              ? 'Contact support if you need to discuss the final decision.'
                                              : 'This request remains available as read-only history.'}
                        </CardContent>
                    </Card>
                </div>
            </div>
            <Dialog
                open={isApprovalDialogOpen}
                onOpenChange={(open) => {
                    if (open) {
                        setApprovalConfirmed(false);
                        setIsApprovalDialogOpen(true);
                        return;
                    }

                    closeApprovalDialog();
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Approve Request</DialogTitle>
                        <DialogDescription>
                            You are approving this loan request as{' '}
                            {approvalSignerName}. Signatures will be collected
                            physically upon loan release.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="rounded-xl border border-border/30 bg-muted/10 p-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <DetailRow
                                    label="Approved amount"
                                    value={displayCurrency(approvedAmount)}
                                />
                                <DetailRow
                                    label="Approved term"
                                    value={
                                        approvedTerm.trim() !== ''
                                            ? `${approvedTerm} months`
                                            : '--'
                                    }
                                />
                            </div>
                        </div>
                        <div className="rounded-xl border border-border/30 bg-card/60 p-4">
                            <div className="space-y-1">
                                <p className="text-sm font-semibold text-foreground">
                                    Approving admin
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {approvalSignerName}
                                </p>
                            </div>
                            <div className="mt-4 rounded-xl border border-dashed border-border/60 bg-muted/10 p-4">
                                <p className="text-sm text-muted-foreground">
                                    Signatures will be collected physically upon
                                    loan release.
                                </p>
                            </div>
                        </div>
                        <div className="flex items-start gap-3 rounded-xl border border-border/30 bg-muted/10 p-4">
                            <Checkbox
                                id="confirm_approval"
                                checked={approvalConfirmed}
                                onCheckedChange={(checked) =>
                                    setApprovalConfirmed(checked === true)
                                }
                            />
                            <Label
                                htmlFor="confirm_approval"
                                className="text-sm leading-relaxed"
                            >
                                I confirm that I have reviewed the loan details,
                                applicant details, and co-maker details before
                                approving this request.
                            </Label>
                        </div>
                    </div>
                    <DialogFooter className="gap-2 sm:gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={decision?.isProcessing}
                            onClick={() => closeApprovalDialog()}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={
                                decision?.isProcessing || !approvalConfirmed
                            }
                            onClick={() => {
                                if (!canApprove) {
                                    showErrorToast(
                                        approvalBlockedMessage ??
                                            defaultApprovalBlockedMessage,
                                        approvalBlockedMessage ??
                                            defaultApprovalBlockedMessage,
                                    );
                                    closeApprovalDialog(true);
                                    return;
                                }

                                decision?.onApprove?.({
                                    approved_amount: approvedAmount,
                                    approved_term: approvedTerm,
                                    decision_notes: decisionNotes
                                        ? decisionNotes
                                        : null,
                                });

                                closeApprovalDialog(true);
                            }}
                        >
                            Approve Request
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <Dialog
                open={isCancellationDialogOpen}
                onOpenChange={(open) => {
                    if (open) {
                        setIsCancellationDialogOpen(true);
                        return;
                    }

                    closeCancellationDialog();
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {cancellation?.dialogTitle ?? 'Cancel Application'}
                        </DialogTitle>
                        <DialogDescription>
                            {cancellation?.dialogDescription ??
                                'This will mark the application as cancelled.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitCancellation}>
                        <div className="space-y-2">
                            <Label htmlFor="cancellation_reason">
                                {cancellation?.reasonLabel ??
                                    'Cancellation reason'}
                            </Label>
                            <textarea
                                id="cancellation_reason"
                                className={textareaClassName}
                                maxLength={1000}
                                required={cancellation?.reasonRequired}
                                value={cancellationReason}
                                disabled={cancellation?.isProcessing}
                                onChange={(event) => {
                                    setCancellationReason(event.target.value);
                                    setCancellationReasonError(null);
                                }}
                            />
                            <div className="flex items-start justify-between gap-3">
                                <InputError
                                    message={cancellationReasonError ?? ''}
                                />
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {cancellationReason.length}/1000
                                </span>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={cancellation?.isProcessing}
                                onClick={() => closeCancellationDialog()}
                            >
                                {cancellation?.dismissLabel ??
                                    'Keep Application'}
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={cancellation?.isProcessing}
                            >
                                <Ban />
                                {cancellation?.confirmLabel ??
                                    'Cancel Application'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog
                open={isCorrectedCopyDialogOpen}
                onOpenChange={(open) => {
                    if (open) {
                        setIsCorrectedCopyDialogOpen(true);
                        return;
                    }

                    closeCorrectedCopyDialog();
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {correctedCopy?.dialogTitle ??
                                'Create Admin-Corrected Request'}
                        </DialogTitle>
                        <DialogDescription>
                            {correctedCopy?.dialogDescription ??
                                'This will create a new corrected request copied from the cancelled request. The cancelled request will remain read-only for audit history.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitCorrectedCopy}>
                        <div className="space-y-2">
                            <Label htmlFor="correction_reason">
                                Correction reason
                            </Label>
                            <textarea
                                id="correction_reason"
                                className={textareaClassName}
                                maxLength={1000}
                                required
                                value={correctedCopyReason}
                                disabled={correctedCopy?.isProcessing}
                                onChange={(event) => {
                                    setCorrectedCopyReason(event.target.value);
                                    setCorrectedCopyReasonError(null);
                                }}
                            />
                            <div className="flex items-start justify-between gap-3">
                                <InputError
                                    message={correctedCopyReasonError ?? ''}
                                />
                                <span className="ml-auto text-xs text-muted-foreground">
                                    {correctedCopyReason.length}/1000
                                </span>
                            </div>
                        </div>
                        <DialogFooter className="gap-2 sm:gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={correctedCopy?.isProcessing}
                                onClick={() => closeCorrectedCopyDialog()}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={correctedCopy?.isProcessing}
                            >
                                {correctedCopy?.confirmLabel ??
                                    'Create Corrected Request'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </PageShell>
    );
}
