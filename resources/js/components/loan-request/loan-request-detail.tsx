import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    CalendarDays,
    Check,
    ChevronRight,
    Clock,
    Download,
    Info,
    PencilLine,
    Upload,
    Wallet,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';
import {
    buildApplicantCuratedFields,
    buildApplicantMoreFields,
    buildCoMakerCuratedFields,
    buildCoMakerMoreFields,
    personName,
    type PersonFieldSpec,
} from '@/components/loan-request/loan-request-detail-page';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
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
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import {
    formatCurrency,
    formatDate,
    formatDisplayText,
} from '@/lib/formatters';
import { estimateLoanRequest } from '@/lib/loan-request-estimate';
import {
    PROGRESS_STEPS,
    resolveLoanRequestProgress,
} from '@/lib/loan-request-progress';
import { cn } from '@/lib/utils';
import type {
    LoanRequestAuditEntry,
    LoanRequestDetail as LoanRequestDetailData,
    LoanRequestPersonData,
    LoanRequestStatusValue,
} from '@/types/loan-requests';

/** Groups party fields like the wizard's "About you" step; unlisted labels fall into Work & income. */
const PROFILE_SECTIONS: { title: string; labels: string[] }[] = [
    {
        title: 'Personal',
        labels: [
            'Nickname',
            'Birthdate',
            'Birthplace',
            'Sex',
            'Civil status',
            'Educational attainment',
        ],
    },
    {
        title: 'Contact & address',
        labels: [
            'Cell no.',
            'Telephone no.',
            'Address',
            'Length of stay',
            'Housing status',
        ],
    },
    {
        title: 'Family',
        labels: [
            'Number of children',
            'Spouse name',
            'Spouse age',
            'Spouse cell no.',
        ],
    },
    { title: 'Work & income', labels: [] },
];

const PartyProfileSummary = ({ fields }: { fields: PersonFieldSpec[] }) => {
    const filled = fields.filter((field) => field.value !== '--');
    const sectionOf = (label: string) =>
        PROFILE_SECTIONS.find((section) => section.labels.includes(label)) ??
        PROFILE_SECTIONS[PROFILE_SECTIONS.length - 1];

    return (
        <div className="space-y-5">
            {PROFILE_SECTIONS.map((section) => {
                const items = filled.filter(
                    (field) => sectionOf(field.label) === section,
                );

                return items.length > 0 ? (
                    <section key={section.title} className="space-y-3">
                        <h3 className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                            {section.title}
                        </h3>
                        <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                            {items.map((item) => (
                                <div key={item.label} className="min-w-0">
                                    <dt className="text-xs text-muted-foreground">
                                        {item.label}
                                    </dt>
                                    <dd className="text-sm font-semibold wrap-break-word">
                                        {item.value}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </section>
                ) : null;
            })}
        </div>
    );
};

/** Member-facing caption per progress step; the last covers `released`. */
const STAGE_CAPTIONS = [
    'you are still completing your application.',
    'your application is in the queue and will be picked up by a loan processor shortly.',
    "a loan processor is reviewing your application and supporting documents. You'll move to manager approval once the review is complete.",
    'the loan manager is reviewing the recommended terms.',
    'your loan is approved and being prepared for release. Your branch will coordinate the schedule with you.',
    'your loan has been released.',
] as const;

const NEXT_STEPS = [
    {
        title: 'Review',
        description:
            'A loan processor reviews your application and documents, and may call you or your co-makers if they need more information.',
    },
    {
        title: 'Approval',
        description: 'The loan manager approves the final terms.',
    },
    {
        title: 'Signing & release',
        description:
            'You and your co-makers sign the documents in person when the loan is released.',
    },
] as const;

const ACTION_STATUSES: LoanRequestStatusValue[] = [
    'needs_revision',
    'awaiting_member_information',
    'awaiting_member_acceptance',
];

function statusMeaning(status: LoanRequestStatusValue | null): {
    meaning: string;
    window: string | null;
} {
    switch (status) {
        case 'submitted':
        case 'pending_review':
            return {
                meaning:
                    "Your application has been received and is waiting for a loan processor. We'll notify you as soon as there's an update — no action is needed from you right now.",
                window: 'Expected review within 3 working days',
            };
        case 'under_review':
            return {
                meaning:
                    "Your application is being reviewed by our team. We'll notify you as soon as a decision has been made — no action is needed from you right now.",
                window: 'Expected review within 3 working days',
            };
        case 'needs_revision':
        case 'awaiting_member_information':
            return {
                meaning:
                    'We need a few details from you before we can continue processing. Please complete the request below.',
                window: null,
            };
        case 'recommended_for_approval':
            return {
                meaning:
                    'Your application has been reviewed and recommended for approval. The loan manager will make the final decision.',
                window: 'Expected decision within 2 working days',
            };
        case 'awaiting_member_acceptance':
            return {
                meaning:
                    'The loan terms were revised. Please review and accept or decline them below so we can continue.',
                window: null,
            };
        case 'approved':
        case 'converted_to_loan':
        case 'for_wibs_encoding':
        case 'wibs_loan_created':
        case 'release_scheduled':
            return {
                meaning:
                    'Your loan is approved and is being prepared for release. Your branch will coordinate the release with you.',
                window: null,
            };
        case 'released':
            return {
                meaning: 'Your loan has been released.',
                window: null,
            };
        case 'rejected':
        case 'declined':
        case 'member_declined_terms':
            return {
                meaning:
                    'This request will not continue. See the activity below for details, or contact support if you have questions.',
                window: null,
            };
        case 'cancelled':
            return {
                meaning: 'This request was cancelled.',
                window: null,
            };
        default:
            return {
                meaning: 'Your application has not been submitted yet.',
                window: null,
            };
    }
}

const displayText = (value: string | null | undefined): string =>
    formatDisplayText(value) || '--';

function EstimateRow({
    label,
    value,
    result = false,
}: {
    label: string;
    value: string;
    result?: boolean;
}) {
    return (
        <div className="flex items-baseline justify-between gap-3 border-b border-border py-2 text-sm last:border-b-0">
            <dt
                className={cn(
                    'min-w-0',
                    result
                        ? 'font-semibold text-foreground'
                        : 'text-muted-foreground',
                )}
            >
                {label}
            </dt>
            <dd
                className={cn(
                    'text-right font-semibold whitespace-nowrap tabular-nums',
                    result && 'text-base font-bold text-primary',
                )}
            >
                {value}
            </dd>
        </div>
    );
}

function SectionLabel({ children }: { children: ReactNode }) {
    return (
        <p className="mt-4 mb-1 text-xs font-bold tracking-wider text-muted-foreground uppercase first:mt-0">
            {children}
        </p>
    );
}

type MethodProps = {
    label: string;
    value: string;
    accountLabel?: string;
    icon: typeof Upload;
    canChange: boolean;
    changeLabel: string;
    onChange: () => void;
};

function PaymentMethodField({
    label,
    value,
    accountLabel,
    icon: Icon,
    canChange,
    changeLabel,
    onChange,
}: MethodProps) {
    return (
        <div className="flex flex-col gap-1">
            <p className="text-sm font-semibold text-muted-foreground">
                {label}
            </p>
            <p className="flex items-center gap-2 text-base font-semibold">
                <Icon aria-hidden="true" className="size-4 text-primary" />
                {value || 'Not set'}
            </p>
            {accountLabel ? (
                <p className="text-sm text-muted-foreground">{accountLabel}</p>
            ) : null}
            {canChange ? (
                <Button
                    type="button"
                    variant="outline"
                    className="mt-2 min-h-11 w-full sm:w-fit"
                    onClick={onChange}
                >
                    {changeLabel}
                </Button>
            ) : null}
        </div>
    );
}

type PartyKey = 'applicant' | 'coMakerOne' | 'coMakerTwo';

type Party = {
    key: PartyKey;
    role: string;
    person: LoanRequestPersonData;
};

type Props = {
    loanRequest: LoanRequestDetailData;
    auditTrail: LoanRequestAuditEntry[];
    releaseMethod: string;
    repaymentMethod: string;
    releaseAccountLabel?: string;
    repaymentAccountLabel?: string;
    canChangePaymentMethod: boolean;
    onChangeReleaseMethod: () => void;
    onChangeRepaymentMethod: () => void;
    backHref: string;
    /** Null when the status does not allow a PDF download. */
    pdfHref: string | null;
    editHref: string | null;
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    cancellation: {
        show: boolean;
        isProcessing: boolean;
        /** Resolves true once the request is cancelled. */
        onConfirm: (reason: string | null) => Promise<boolean>;
    };
    /** Status-specific action cards, shown right under the stage path. */
    children?: ReactNode;
};

export function LoanRequestDetailView({
    loanRequest,
    auditTrail,
    releaseMethod,
    repaymentMethod,
    releaseAccountLabel,
    repaymentAccountLabel,
    canChangePaymentMethod,
    onChangeReleaseMethod,
    onChangeRepaymentMethod,
    backHref,
    pdfHref,
    editHref,
    applicant,
    coMakerOne,
    coMakerTwo,
    cancellation,
    children,
}: Props) {
    const [openParty, setOpenParty] = useState<PartyKey | null>(null);
    const [isCancelOpen, setIsCancelOpen] = useState(false);
    const [cancelReason, setCancelReason] = useState('');
    const parties = (
        [
            { key: 'applicant', role: 'Applicant', person: applicant },
            { key: 'coMakerOne', role: 'Co-maker 1', person: coMakerOne },
            { key: 'coMakerTwo', role: 'Co-maker 2', person: coMakerTwo },
        ] satisfies {
            key: PartyKey;
            role: string;
            person: LoanRequestPersonData | null;
        }[]
    ).filter((party): party is Party => party.person !== null);
    const selectedParty =
        parties.find((party) => party.key === openParty) ?? null;
    const confirmCancellation = async () => {
        const cancelled = await cancellation.onConfirm(
            cancelReason.trim() || null,
        );

        if (cancelled) {
            setIsCancelOpen(false);
            setCancelReason('');
        }
    };
    const { step, held } = resolveLoanRequestProgress(loanRequest.status);
    const { meaning, window: updateWindow } = statusMeaning(loanRequest.status);
    const amount = Number(loanRequest.requested_amount) || 0;
    const term = Number(loanRequest.requested_term) || 0;
    const estimate =
        amount > 0 && term > 0 ? estimateLoanRequest(amount, term) : null;
    const termLabel = term > 0 ? `${term} month${term === 1 ? '' : 's'}` : '--';
    const loanType = displayText(loanRequest.loan_type_label_snapshot);
    const submittedDate = loanRequest.submitted_at
        ? formatDate(loanRequest.submitted_at)
        : null;
    const needsAction =
        loanRequest.status !== null &&
        ACTION_STATUSES.includes(loanRequest.status);
    const currentNextStep = step <= 2 ? 0 : step === 3 ? 1 : 2;
    const activity = [...auditTrail].reverse();
    const snapshot: { label: string; value: string; wide?: boolean }[] = [
        { label: 'Loan type', value: loanType },
        { label: 'Requested term', value: termLabel },
        {
            label: 'Availment status',
            value: displayText(loanRequest.availment_status),
        },
        { label: 'Loan purpose', value: displayText(loanRequest.loan_purpose) },
        { label: 'Release method', value: releaseMethod || 'Not set' },
        { label: 'Repayment method', value: repaymentMethod || 'Not set' },
        {
            label: 'Submitted',
            value: `${submittedDate ?? 'Not submitted yet'} · ${loanRequest.reference}`,
            wide: true,
        },
    ];

    return (
        <div className="mx-auto mt-6 mb-6 flex w-full max-w-7xl flex-col gap-4 px-4 sm:px-6 lg:px-8">
            <section
                aria-labelledby="loan-request-title"
                className="flex flex-col gap-3 rounded-xl bg-primary px-5 py-6 text-primary-foreground sm:px-8 sm:py-7"
            >
                <p className="text-xs font-bold tracking-[0.12em] uppercase">
                    Loan request
                </p>
                <h1
                    id="loan-request-title"
                    className="text-3xl leading-tight font-bold tracking-tight break-all sm:text-4xl"
                >
                    {loanRequest.reference}
                </h1>
                <p className="max-w-[62ch] text-[15px]">{meaning}</p>
                <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                    {submittedDate ? (
                        <span className="inline-flex items-center gap-1.5">
                            <CalendarDays
                                aria-hidden="true"
                                className="size-3.5"
                            />
                            Submitted {submittedDate}
                        </span>
                    ) : null}
                    {updateWindow ? (
                        <span className="inline-flex items-center gap-1.5">
                            <Clock aria-hidden="true" className="size-3.5" />
                            {updateWindow}
                        </span>
                    ) : null}
                </div>
                <p className="self-start">
                    <span className="sr-only">Status: </span>
                    <LoanRequestStatusBadge
                        status={loanRequest.status}
                        className="rounded-md border-transparent bg-card px-3 py-1 text-[13px] font-bold text-card-foreground"
                    />
                </p>
            </section>

            <section
                aria-label="Application progress"
                className="flex flex-col gap-2.5"
            >
                <ol className="flex gap-1 overflow-x-auto [scrollbar-width:none]">
                    {PROGRESS_STEPS.map((label, index) => {
                        const done = index < step;
                        const current = index === step;

                        return (
                            <li
                                key={label}
                                aria-current={current ? 'step' : undefined}
                                className={cn(
                                    'flex min-h-11 flex-[1_0_132px] items-center justify-center gap-1.5 px-5 py-2 text-center text-[13px] font-semibold whitespace-nowrap',
                                    '[clip-path:polygon(0_0,calc(100%_-_13px)_0,100%_50%,calc(100%_-_13px)_100%,0_100%,13px_50%)]',
                                    'first:rounded-l-md first:[clip-path:polygon(0_0,calc(100%_-_13px)_0,100%_50%,calc(100%_-_13px)_100%,0_100%)]',
                                    'last:rounded-r-md last:[clip-path:polygon(0_0,100%_0,100%_100%,0_100%,13px_50%)]',
                                    done &&
                                        'bg-secondary text-secondary-foreground',
                                    current &&
                                        'bg-primary font-bold text-primary-foreground',
                                    !done &&
                                        !current &&
                                        'bg-muted text-muted-foreground',
                                )}
                            >
                                {done ? (
                                    <Check
                                        aria-hidden="true"
                                        className="size-3.5"
                                    />
                                ) : null}
                                {label}
                                {done ? (
                                    <span className="sr-only">(done)</span>
                                ) : current ? (
                                    <span className="sr-only">(current)</span>
                                ) : null}
                            </li>
                        );
                    })}
                </ol>
                {step >= 0 ? (
                    <p className="flex items-start gap-2.5 rounded-lg border border-border bg-card px-4 py-3 text-sm text-muted-foreground">
                        <Info
                            aria-hidden="true"
                            className="mt-0.5 size-4 shrink-0 text-primary"
                        />
                        <span>
                            <b className="text-foreground">
                                {PROGRESS_STEPS[step] ?? 'Released'}
                            </b>{' '}
                            —{' '}
                            {held
                                ? 'this request is on hold at this stage. See the status above for details.'
                                : STAGE_CAPTIONS[step]}
                        </span>
                    </p>
                ) : null}
            </section>

            {children}

            <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1.65fr)_minmax(0,1fr)]">
                <div className="flex min-w-0 flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <h2>What happens next</h2>
                            </CardTitle>
                            <CardDescription>
                                Here's the road from review to release.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <ol className="flex flex-col gap-3.5">
                                {NEXT_STEPS.map((item, index) => {
                                    const current = index === currentNextStep;

                                    return (
                                        <li
                                            key={item.title}
                                            aria-current={
                                                current ? 'step' : undefined
                                            }
                                            className="flex items-start gap-3"
                                        >
                                            <span
                                                aria-hidden="true"
                                                className={cn(
                                                    'grid size-8 shrink-0 place-items-center rounded-full text-sm font-bold',
                                                    current
                                                        ? 'bg-secondary text-secondary-foreground'
                                                        : 'bg-muted text-muted-foreground',
                                                )}
                                            >
                                                {index + 1}
                                            </span>
                                            <span>
                                                <span className="block text-[15px] font-semibold">
                                                    {item.title}
                                                    {current ? (
                                                        <span className="sr-only">
                                                            {' '}
                                                            (current step)
                                                        </span>
                                                    ) : null}
                                                </span>
                                                <span className="block text-sm text-muted-foreground">
                                                    {item.description}
                                                </span>
                                            </span>
                                        </li>
                                    );
                                })}
                            </ol>
                            <Alert className="border-secondary bg-secondary text-secondary-foreground">
                                <Info aria-hidden="true" />
                                <AlertTitle className="line-clamp-none">
                                    What we need from you:{' '}
                                    {needsAction
                                        ? 'action required.'
                                        : 'nothing right now.'}
                                </AlertTitle>
                                <AlertDescription className="text-secondary-foreground">
                                    {needsAction
                                        ? 'Please complete the request shown above so we can continue processing.'
                                        : 'We will text you if the processor has questions.'}
                                </AlertDescription>
                            </Alert>
                        </CardContent>
                    </Card>

                    {estimate ? (
                        <Card>
                            <CardHeader>
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <CardTitle>
                                        <h2>
                                            Estimated release &amp; repayment
                                        </h2>
                                    </CardTitle>
                                    <Badge variant="secondary">Estimates</Badge>
                                </div>
                                <CardDescription>
                                    Based on published rates for this loan.
                                    Final figures are confirmed before release.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <SectionLabel>
                                    At release (estimate)
                                </SectionLabel>
                                <dl>
                                    <EstimateRow
                                        label="Requested amount"
                                        value={formatCurrency(amount)}
                                    />
                                    <EstimateRow
                                        label="Service charge (2%, estimate)"
                                        value={`− ${formatCurrency(estimate.serviceCharge)}`}
                                    />
                                    <EstimateRow
                                        label="Documentary stamp (0.75%, estimate)"
                                        value={`− ${formatCurrency(estimate.documentaryStamp)}`}
                                    />
                                    <EstimateRow
                                        label="Loan security (2%, held as collateral, estimate)"
                                        value={`− ${formatCurrency(estimate.loanSecurity)}`}
                                    />
                                    <EstimateRow
                                        label="Insurance"
                                        value="Set by provider"
                                    />
                                    <EstimateRow
                                        label="Estimated amount you receive"
                                        value={formatCurrency(
                                            estimate.netProceeds,
                                        )}
                                        result
                                    />
                                </dl>
                                <SectionLabel>
                                    Repayment · {termLabel} term (estimate)
                                </SectionLabel>
                                <dl>
                                    <EstimateRow
                                        label="Principal"
                                        value={formatCurrency(amount)}
                                    />
                                    <EstimateRow
                                        label={`Interest (1.5% × ${termLabel}, estimate)`}
                                        value={formatCurrency(
                                            estimate.interest,
                                        )}
                                    />
                                    <EstimateRow
                                        label="Estimated total repayable"
                                        value={formatCurrency(
                                            estimate.totalRepayable,
                                        )}
                                        result
                                    />
                                </dl>
                                <p className="mt-3 rounded-lg bg-muted px-3.5 py-3 text-sm">
                                    <b>
                                        {term === 1
                                            ? 'One payment, '
                                            : `${term} monthly payments of about `}
                                        {formatCurrency(estimate.perPayment)}{' '}
                                        (estimate).
                                    </b>{' '}
                                    Your repayment method is set to{' '}
                                    {repaymentMethod || 'not set'}.
                                </p>
                                <p className="mt-2.5 text-xs leading-relaxed text-muted-foreground">
                                    Estimates use published rates: 2% service
                                    charge, 2% loan security, 0.75% documentary
                                    stamp, and 1.5% monthly interest. Insurance
                                    is set by the provider and deducted at
                                    release. These figures are estimates for
                                    guidance only.
                                </p>
                            </CardContent>
                        </Card>
                    ) : null}

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <h2 className="inline-flex items-center gap-2">
                                    <Wallet
                                        aria-hidden="true"
                                        className="size-4 text-primary"
                                    />
                                    Payment method
                                </h2>
                            </CardTitle>
                            <CardDescription>
                                {canChangePaymentMethod
                                    ? 'You can change how your loan is released and repaid while your request is still awaiting review.'
                                    : 'How your loan is released and repaid.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <PaymentMethodField
                                label="Release method"
                                value={releaseMethod}
                                accountLabel={releaseAccountLabel}
                                icon={Upload}
                                canChange={canChangePaymentMethod}
                                changeLabel="Change release method"
                                onChange={onChangeReleaseMethod}
                            />
                            <PaymentMethodField
                                label="Repayment method"
                                value={repaymentMethod}
                                accountLabel={repaymentAccountLabel}
                                icon={Download}
                                canChange={canChangePaymentMethod}
                                changeLabel="Change repayment method"
                                onChange={onChangeRepaymentMethod}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <h2>Activity</h2>
                            </CardTitle>
                            <CardDescription>
                                Everything that has happened on this request so
                                far.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {activity.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No activity yet.
                                </p>
                            ) : (
                                <ol className="flex flex-col">
                                    {activity.map((entry, index) => (
                                        <li
                                            key={entry.id}
                                            className="relative flex gap-3 pb-4 last:pb-0"
                                        >
                                            {index < activity.length - 1 ? (
                                                <span
                                                    aria-hidden="true"
                                                    className="absolute top-4 bottom-0 left-1.5 w-0.5 bg-border"
                                                />
                                            ) : null}
                                            <span
                                                aria-hidden="true"
                                                className={cn(
                                                    'relative mt-1 size-3.5 shrink-0 rounded-full ring-3 ring-card',
                                                    index === 0
                                                        ? 'bg-primary'
                                                        : 'bg-muted-foreground',
                                                )}
                                            />
                                            <span className="min-w-0">
                                                <span className="block text-sm font-semibold">
                                                    {entry.action_label}
                                                    {index === 0 ? (
                                                        <span className="sr-only">
                                                            {' '}
                                                            (latest)
                                                        </span>
                                                    ) : null}
                                                </span>
                                                {entry.reason ? (
                                                    <span className="block text-sm whitespace-pre-wrap text-muted-foreground">
                                                        {entry.reason}
                                                    </span>
                                                ) : null}
                                                <span className="block text-[13px] text-muted-foreground">
                                                    {formatDate(
                                                        entry.created_at,
                                                    )}
                                                </span>
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="flex min-w-0 flex-col gap-4">
                    <section
                        aria-label="Requested amount"
                        className="grid gap-0.5 rounded-lg bg-primary px-5 py-5 text-primary-foreground"
                    >
                        <span className="text-xs font-bold tracking-wider uppercase">
                            Requested amount
                        </span>
                        <span className="text-[clamp(26px,3.4vw,34px)] leading-tight font-bold whitespace-nowrap tabular-nums">
                            {formatCurrency(amount)}
                        </span>
                        <span className="text-[13px]">
                            {loanType} · {termLabel}
                        </span>
                    </section>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                <h2>Request snapshot</h2>
                            </CardTitle>
                            <CardDescription>
                                The details your processor is evaluating.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-2.5 sm:grid-cols-2">
                                {snapshot.map((item) => (
                                    <div
                                        key={item.label}
                                        className={cn(
                                            'min-w-0 rounded-lg bg-muted px-3 py-2.5',
                                            item.wide && 'sm:col-span-2',
                                        )}
                                    >
                                        <dt className="text-[13px] text-muted-foreground">
                                            {item.label}
                                        </dt>
                                        <dd className="mt-0.5 text-[15px] font-semibold [overflow-wrap:anywhere]">
                                            {item.value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </CardContent>
                    </Card>

                    {parties.length > 0 ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    <h2>Parties to the request</h2>
                                </CardTitle>
                                <CardDescription>
                                    Select a person to see their details.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ul className="flex flex-col gap-2">
                                    {parties.map((party) => {
                                        const name = personName(party.person);

                                        return (
                                            <li key={party.key}>
                                                <Button variant="ghost"
                                                    type="button"
                                                    className="h-auto md:h-auto justify-start gap-0 whitespace-normal rounded-none px-0 py-0 has-[>svg]:px-0 font-normal hover:bg-transparent hover:text-current flex min-h-11 w-full items-center gap-3 rounded-lg border border-border px-3 has-[>svg]:px-3 py-2.5 text-left transition-colors outline-none hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 motion-reduce:transition-none"
                                                    onClick={() =>
                                                        setOpenParty(party.key)
                                                    }
                                                >
                                                    <span
                                                        aria-hidden="true"
                                                        className="grid size-9 shrink-0 place-items-center rounded-full bg-secondary text-sm font-bold text-secondary-foreground"
                                                    >
                                                        {name.charAt(0)}
                                                    </span>
                                                    <span className="min-w-0 flex-1">
                                                        <span className="block text-xs font-bold tracking-wider text-muted-foreground uppercase">
                                                            {party.role}
                                                        </span>
                                                        <span className="block truncate text-sm font-semibold">
                                                            {name}
                                                            {party.person
                                                                .employer_business_name
                                                                ? ` · ${displayText(party.person.employer_business_name)}`
                                                                : ''}
                                                        </span>
                                                    </span>
                                                    <ChevronRight
                                                        aria-hidden="true"
                                                        className="size-4 shrink-0 text-muted-foreground"
                                                    />
                                                </Button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            </CardContent>
                        </Card>
                    ) : null}

                    <Card className="print:hidden">
                        <CardHeader>
                            <CardTitle>
                                <h2>Actions</h2>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2.5">
                            {pdfHref ? (
                                <Button asChild className="min-h-11 w-full">
                                    <a href={pdfHref}>
                                        <Download aria-hidden="true" />
                                        Download PDF
                                    </a>
                                </Button>
                            ) : null}
                            {editHref ? (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="min-h-11 w-full"
                                >
                                    <Link href={editHref}>
                                        <PencilLine aria-hidden="true" />
                                        Edit Application
                                    </Link>
                                </Button>
                            ) : null}
                            {cancellation.show ? (
                                <div className="flex flex-col gap-2.5 rounded-lg border border-destructive/40 p-3">
                                    <p className="text-xs font-bold tracking-wider text-destructive uppercase">
                                        Application action
                                    </p>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        className="min-h-11 w-full"
                                        disabled={cancellation.isProcessing}
                                        onClick={() => setIsCancelOpen(true)}
                                    >
                                        <Ban aria-hidden="true" />
                                        Cancel Application
                                    </Button>
                                </div>
                            ) : null}
                            <Separator />
                            <Button
                                asChild
                                variant="ghost"
                                className="min-h-11 w-full"
                            >
                                <Link href={backHref}>
                                    <ArrowLeft aria-hidden="true" />
                                    Back to loan requests
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Dialog
                open={selectedParty !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setOpenParty(null);
                    }
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{selectedParty?.role} profile</DialogTitle>
                        <DialogDescription>
                            Details submitted with this request.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedParty ? (
                        <PartyProfileSummary
                            fields={
                                selectedParty.key === 'applicant'
                                    ? [
                                          ...buildApplicantCuratedFields(
                                              selectedParty.person,
                                          ),
                                          ...buildApplicantMoreFields(
                                              selectedParty.person,
                                          ),
                                      ]
                                    : [
                                          ...buildCoMakerCuratedFields(
                                              selectedParty.person,
                                          ),
                                          ...buildCoMakerMoreFields(
                                              selectedParty.person,
                                          ),
                                      ]
                            }
                        />
                    ) : null}
                </DialogContent>
            </Dialog>

            <AlertDialog
                open={isCancelOpen}
                onOpenChange={(open) => {
                    if (!cancellation.isProcessing) {
                        setIsCancelOpen(open);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Cancel this application?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            This cancels {loanRequest.reference} before a final
                            decision is made. You can&apos;t undo this.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="cancellation_reason">
                            Reason (optional)
                        </Label>
                        <Textarea
                            id="cancellation_reason"
                            className="flex min-h-[96px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50"
                            maxLength={1000}
                            value={cancelReason}
                            disabled={cancellation.isProcessing}
                            onChange={(event) =>
                                setCancelReason(event.target.value)
                            }
                        />
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel
                            className="min-h-11"
                            disabled={cancellation.isProcessing}
                        >
                            Keep application
                        </AlertDialogCancel>
                        <Button
                            type="button"
                            variant="destructive"
                            className="min-h-11"
                            disabled={cancellation.isProcessing}
                            onClick={() => void confirmCancellation()}
                        >
                            {cancellation.isProcessing
                                ? 'Cancelling…'
                                : 'Cancel application'}
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
