import { Link } from '@inertiajs/react';
import { Ban, Calendar, Download, PencilLine, Printer } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { PageShell } from '@/components/page-shell';
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
    formatDisplayText,
} from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type {
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
    decision?: DecisionProps;
    correction?: CorrectionProps;
    correctedCopy?: CorrectedCopyProps;
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

    return composed !== '' ? composed : person.address ?? '';
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
        : person.employer_business_address ?? '';
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
    under_review: 'Under review',
    approved: 'Approved',
    declined: 'Declined',
    cancelled: 'Cancelled',
};

const statusDescriptions: Record<LoanRequestStatusValue, string> = {
    draft: 'Complete the form and submit when you are ready.',
    submitted: 'Your request has been submitted for review.',
    under_review: 'We are currently reviewing your request.',
    approved: 'Your request is approved. We will contact you next.',
    declined: 'Your request was declined. You may reapply anytime.',
    cancelled: 'This approved request was cancelled and remains available for audit.',
};

const statusSteps: LoanRequestStatusValue[] = [
    'draft',
    'under_review',
    'approved',
    'declined',
];

type SummaryStatProps = {
    label: string;
    value: string;
};

type DecisionProps = {
    show?: boolean;
    canDecide?: boolean;
    isProcessing?: boolean;
    isCancelling?: boolean;
    blockedMessage?: string | null;
    onApprove?: (payload: LoanRequestApprovePayload) => void;
    onDecline?: (payload: LoanRequestDeclinePayload) => void;
    onCancelApproved?: (
        payload: LoanRequestCancellationPayload,
    ) => Promise<LoanRequestDetail | null>;
};

type CorrectionProps = {
    show?: boolean;
    isProcessing?: boolean;
    onEdit?: () => void;
};

type CorrectedCopyProps = {
    isProcessing?: boolean;
    onCreate?: () => void;
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
    cancellation_reason: string;
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

export function LoanRequestDetailPage({
    loanRequest,
    applicant,
    coMakerOne,
    coMakerTwo,
    backHref,
    backLabel,
    pdfHref,
    printHref,
    decision,
    correction,
    correctedCopy,
}: Props) {
    const submittedAt = loanRequest.submitted_at
        ? formatDate(loanRequest.submitted_at)
        : null;
    const normalizedStatus =
        loanRequest.status === 'submitted'
            ? 'under_review'
            : loanRequest.status;
    const statusForTimeline = (normalizedStatus ??
        'draft') as LoanRequestStatusValue;
    const timelineSteps =
        statusForTimeline === 'cancelled'
            ? ([
                  'draft',
                  'under_review',
                  'approved',
                  'cancelled',
              ] as LoanRequestStatusValue[])
            : statusSteps;
    const currentStatusIndex = Math.max(
        0,
        timelineSteps.indexOf(statusForTimeline),
    );
    const canDownloadPdf =
        normalizedStatus === 'under_review' ||
        normalizedStatus === 'approved' ||
        normalizedStatus === 'declined' ||
        normalizedStatus === 'cancelled';
    const showCorrectionAction =
        normalizedStatus === 'under_review' && (correction?.show ?? false);
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
    const showDecision = decision?.show ?? false;
    const showDecisionForm =
        showDecision &&
        decision?.canDecide &&
        normalizedStatus === 'under_review';
    const showDecisionSummary =
        showDecision &&
        (normalizedStatus === 'approved' ||
            normalizedStatus === 'declined' ||
            normalizedStatus === 'cancelled');
    const showCancellationAction =
        showDecision &&
        normalizedStatus === 'approved' &&
        typeof decision?.onCancelApproved === 'function';
    const showCorrectedCopyAction =
        normalizedStatus === 'cancelled' &&
        typeof correctedCopy?.onCreate === 'function';
    const blockedMessage =
        normalizedStatus === 'under_review'
            ? decision?.blockedMessage ?? null
            : null;
    const [isApprovalDialogOpen, setIsApprovalDialogOpen] = useState(false);
    const [approvalConfirmed, setApprovalConfirmed] = useState(false);
    const [isCancellationDialogOpen, setIsCancellationDialogOpen] =
        useState(false);
    const [isCorrectedCopyDialogOpen, setIsCorrectedCopyDialogOpen] =
        useState(false);
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
    const approvedAmountValue = displayCurrency(loanRequest.approved_amount);
    const approvedTermValue =
        loanRequest.approved_term !== null &&
        loanRequest.approved_term !== undefined &&
        `${loanRequest.approved_term}`.trim() !== ''
            ? `${loanRequest.approved_term} months`
            : '--';
    const decisionNotesValue = displayText(loanRequest.decision_notes);
    const cancelledBy = loanRequest.cancelled_by?.name ?? '--';
    const cancelledAt = displayDateValue(loanRequest.cancelled_at);
    const cancellationReasonValue = displayText(
        loanRequest.cancellation_reason,
    );

    const closeApprovalDialog = (force = false) => {
        if (decision?.isProcessing && !force) {
            return;
        }

        setIsApprovalDialogOpen(false);
        setApprovalConfirmed(false);
    };

    const closeCancellationDialog = (force = false) => {
        if (decision?.isCancelling && !force) {
            return;
        }

        setIsCancellationDialogOpen(false);
        setCancellationReason('');
        setCancellationReasonError(null);
    };

    const closeCorrectedCopyDialog = (force = false) => {
        if (correctedCopy?.isProcessing && !force) {
            return;
        }

        setIsCorrectedCopyDialogOpen(false);
    };

    const submitCancellation = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const reason = cancellationReason.trim();

        if (reason === '') {
            setCancellationReasonError('Cancellation reason is required.');
            return;
        }

        setCancellationReasonError(null);

        const updated = await decision?.onCancelApproved?.({
            cancellation_reason: reason,
        });

        if (updated) {
            closeCancellationDialog(true);
        }
    };

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
                            Review the submitted snapshot of your loan request,
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
                                {statusDescriptions[statusForTimeline]}
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
                                        const isCurrent =
                                            status === statusForTimeline;
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
                                                                  ? 'h-3.5 w-3.5 border-primary bg-primary ring-4 ring-primary/20 shadow-sm shadow-primary/40'
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
                                                        {
                                                            statusLabels[
                                                                status
                                                            ]
                                                        }
                                                    </p>
                                                    {isCurrent ? (
                                                        <p className="text-xs text-muted-foreground">
                                                            {
                                                                statusDescriptions[
                                                                    statusForTimeline
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
                                Status updates are based on the data you
                                submitted for review.
                            </div>
                        </CardContent>
                    </Card>

                    {showDecision ? (
                        <Card className="border-border/30 bg-card/50 shadow-sm">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Decision
                                </CardTitle>
                                <CardDescription>
                                    Capture the approval or decline details.
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
                                                className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex min-h-[96px] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50"
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
                                                onClick={() => {
                                                    setApprovalConfirmed(false);
                                                    setIsApprovalDialogOpen(
                                                        true,
                                                    );
                                                }}
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
                                            Only requests under review can be
                                            decided.
                                        </p>
                                    </>
                                ) : showDecisionSummary ? (
                                    <div className="space-y-3 text-sm">
                                        <DetailRow
                                            label="Status"
                                            value={
                                                statusLabels[
                                                    statusForTimeline
                                                ] ?? '--'
                                            }
                                        />
                                        <DetailRow
                                            label="Reviewed by"
                                            value={reviewedBy}
                                        />
                                        <DetailRow
                                            label="Reviewed at"
                                            value={reviewedAt}
                                        />
                                        {statusForTimeline === 'approved' ||
                                        statusForTimeline === 'cancelled' ? (
                                            <>
                                                <DetailRow
                                                    label="Approved amount"
                                                    value={
                                                        approvedAmountValue
                                                    }
                                                />
                                                <DetailRow
                                                    label="Approved term"
                                                    value={approvedTermValue}
                                                />
                                            </>
                                        ) : null}
                                        <DetailRow
                                            label="Decision notes"
                                            value={decisionNotesValue}
                                        />
                                        {statusForTimeline === 'cancelled' ? (
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
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {blockedMessage ??
                                            'Decision details will appear once the request is reviewed.'}
                                    </p>
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
                            <CardTitle className="text-base">
                                Actions
                            </CardTitle>
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
                            {canDownloadPdf ? (
                                <div className="space-y-3">
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        <Button
                                            asChild
                                            className="w-full justify-center"
                                        >
                                            <a href={pdfHref}>
                                                <Download />
                                                Download PDF
                                            </a>
                                        </Button>
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="w-full justify-center"
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
                                        <p className="text-xs text-muted-foreground sm:col-span-2">
                                            PDFs include the exact snapshot
                                            submitted for review.
                                        </p>
                                    </div>
                                    <Separator className="bg-border/40" />
                                </div>
                            ) : null}
                            {showCancellationAction ? (
                                <div className="space-y-3">
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        className="w-full justify-center"
                                        disabled={decision?.isCancelling}
                                        onClick={() =>
                                            setIsCancellationDialogOpen(true)
                                        }
                                    >
                                        <Ban />
                                        Cancel Approved Request
                                    </Button>
                                    <Separator className="bg-border/40" />
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
                                        Create Corrected Request
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
                            {statusForTimeline === 'draft'
                                ? 'Finish the application and submit to begin the review.'
                                : statusForTimeline === 'under_review'
                                  ? 'Our team will review your request and notify you of the outcome.'
                                  : statusForTimeline === 'approved'
                                    ? 'You will receive next-step instructions from the loans team.'
                                    : statusForTimeline === 'declined'
                                      ? 'Contact support if you would like to discuss your request.'
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
                        <DialogTitle>Confirm Approval</DialogTitle>
                        <DialogDescription>
                            Please carefully review the loan details, applicant
                            information, and co-maker information before
                            approving this request. Once approved, the request
                            details cannot be edited directly. If wrong
                            information is found after approval, the approved
                            request must be cancelled and the member must
                            create a corrected request.
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
                                I confirm that I have reviewed the loan
                                details, applicant details, and co-maker
                                details before approving.
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
                                decision?.isProcessing ||
                                !approvalConfirmed
                            }
                            onClick={() => {
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
                            Confirm Approval
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
                        <DialogTitle>Cancel Approved Request</DialogTitle>
                        <DialogDescription>
                            This keeps the approved request as read-only
                            history.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitCancellation}>
                        <div className="space-y-2">
                            <Label htmlFor="cancellation_reason">
                                Cancellation reason
                            </Label>
                            <textarea
                                id="cancellation_reason"
                                className={textareaClassName}
                                maxLength={1000}
                                required
                                value={cancellationReason}
                                disabled={decision?.isCancelling}
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
                                disabled={decision?.isCancelling}
                                onClick={closeCancellationDialog}
                            >
                                Keep Request
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={decision?.isCancelling}
                            >
                                <Ban />
                                Cancel Request
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
                        <DialogTitle>Create Corrected Request</DialogTitle>
                        <DialogDescription>
                            This will create a new draft copied from this
                            cancelled request. You can review and correct the
                            details before submitting it again.
                        </DialogDescription>
                    </DialogHeader>
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
                            type="button"
                            disabled={correctedCopy?.isProcessing}
                            onClick={() => {
                                correctedCopy?.onCreate?.();
                                closeCorrectedCopyDialog(true);
                            }}
                        >
                            Create Draft
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </PageShell>
    );
}
