import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { Calendar, Download, Printer } from 'lucide-react';
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
    cancelled: 'This request was cancelled before completion.',
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
    blockedMessage?: string | null;
    onApprove?: (payload: LoanRequestApprovePayload) => void;
    onDecline?: (payload: LoanRequestDeclinePayload) => void;
};

type LoanRequestApprovePayload = {
    approved_amount: string;
    approved_term: string;
    decision_notes?: string | null;
};

type LoanRequestDeclinePayload = {
    decision_notes?: string | null;
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
    const statusTimelineKey =
        statusForTimeline === 'cancelled' ? 'declined' : statusForTimeline;
    const currentStatusIndex = Math.max(
        0,
        statusSteps.indexOf(statusTimelineKey),
    );
    const statusProgress =
        statusSteps.length > 1
            ? currentStatusIndex / (statusSteps.length - 1)
            : 0;
    const canDownloadPdf =
        normalizedStatus === 'under_review' ||
        normalizedStatus === 'approved' ||
        normalizedStatus === 'declined';
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
        (normalizedStatus === 'approved' || normalizedStatus === 'declined');
    const blockedMessage =
        normalizedStatus === 'under_review'
            ? decision?.blockedMessage ?? null
            : null;
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
                                    className="absolute left-3 w-px rounded-full bg-border/40"
                                    style={{
                                        top: '0.3125rem',
                                        bottom: '0.3125rem',
                                    }}
                                />
                                <span
                                    aria-hidden="true"
                                    className="absolute left-3 w-px rounded-full bg-primary/40"
                                    style={{
                                        top: '0.3125rem',
                                        height: `calc((100% - 0.625rem) * ${statusProgress})`,
                                    }}
                                />
                                <div className="space-y-6">
                                    {statusSteps.map((status, index) => {
                                        const isCurrent =
                                            status === statusTimelineKey;
                                        const isComplete =
                                            index < currentStatusIndex;

                                        return (
                                            <div
                                                key={status}
                                                className="flex gap-3"
                                            >
                                                <div className="flex w-6 items-start justify-center">
                                                    <span
                                                        className={cn(
                                                            'relative z-10 h-2.5 w-2.5 rounded-full border transition-colors',
                                                            isComplete
                                                                ? 'border-primary bg-primary shadow-sm shadow-primary/20'
                                                                : isCurrent
                                                                  ? 'border-primary/70 bg-card ring-2 ring-primary/20'
                                                                  : 'border-border/30 bg-muted/15',
                                                        )}
                                                    />
                                                </div>
                                                <div className="space-y-1">
                                                    <p
                                                        className={cn(
                                                            'text-sm font-medium',
                                                            isCurrent
                                                                ? 'text-foreground'
                                                                : isComplete
                                                                  ? 'text-foreground/70'
                                                                  : 'text-muted-foreground',
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
                                                onClick={() =>
                                                    decision?.onApprove?.({
                                                        approved_amount:
                                                            approvedAmount,
                                                        approved_term:
                                                            approvedTerm,
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
                                        {statusForTimeline === 'approved' ? (
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
                        <CardContent className="space-y-3">
                            {canDownloadPdf ? (
                                <Button asChild>
                                    <a href={pdfHref}>
                                        <Download />
                                        Download PDF
                                    </a>
                                </Button>
                            ) : null}
                            {canDownloadPdf ? (
                                <Button asChild variant="outline">
                                    <a
                                        href={printHref}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <Printer />
                                        Print application
                                    </a>
                                </Button>
                            ) : null}
                            <Button asChild variant="ghost">
                                <Link href={backHref}>{backLabel}</Link>
                            </Button>
                            <p className="text-xs text-muted-foreground">
                                PDFs include the exact snapshot submitted for
                                review.
                            </p>
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
                                      : 'If you need further assistance, reach out to support.'}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </PageShell>
    );
}
