import { Link } from '@inertiajs/react';
import { AlertCircle, Landmark } from 'lucide-react';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { formatCurrency, formatDate } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { MemberLoan } from '@/types/admin';
import {
    formatLoanNumber,
    formatRateShort,
    formatShortDate,
    isLoanActive,
    isLoanOverdue,
    loanPaymentsLeft,
    loanRepaidAmount,
    loanRepaidPercent,
    loanScheduleRows,
    loanTermMonths,
    loanTypeLabel,
} from './loan-presentation';

type LoanCardsProps = {
    loans: MemberLoan[];
    isLoading?: boolean;
    error?: string | null;
    onRetry?: () => void;
};

function Figure({
    label,
    value,
    small = false,
}: {
    label: string;
    value: string;
    small?: boolean;
}) {
    return (
        <div className="min-w-0">
            <p className="text-[12px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                {label}
            </p>
            <p
                className={cn(
                    'truncate font-bold tabular-nums',
                    small ? 'text-[15px]' : 'text-[16px]',
                )}
            >
                {value}
            </p>
        </div>
    );
}

function RepaymentProgress({ loan }: { loan: MemberLoan }) {
    const percent = loanRepaidPercent(loan);
    const repaid = loanRepaidAmount(loan);

    return (
        <div className="grid gap-1.5">
            <div className="flex justify-between gap-2.5 text-[13px] text-muted-foreground tabular-nums">
                <span>
                    Repaid:{' '}
                    <strong className="font-bold whitespace-nowrap text-card-foreground">
                        {percent}%
                    </strong>
                </span>
                <span>
                    <strong className="font-bold whitespace-nowrap text-card-foreground">
                        {formatCurrency(repaid)}
                    </strong>{' '}
                    of {formatCurrency(loan.principal ?? 0)}
                </span>
            </div>
            <div
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={percent}
                aria-label={`${percent} percent of principal repaid`}
                className="h-2 w-full overflow-hidden rounded-full bg-black/15 dark:bg-white/20"
            >
                <div
                    className="h-full rounded-full bg-accent transition-[width] duration-300 motion-reduce:transition-none"
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}

function ScheduleDisclosure({ loan }: { loan: MemberLoan }) {
    const rows = loanScheduleRows(
        loan,
        Math.min(3, Math.max(0, loanPaymentsLeft(loan))),
    );

    if (rows.length === 0) {
        return null;
    }

    return (
        <Collapsible className="border-t border-border pt-2.5">
            <CollapsibleTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    className="h-auto min-h-8 justify-start gap-1.5 px-0 py-0 text-[13.5px] font-semibold text-primary hover:bg-transparent hover:text-primary md:h-auto"
                >
                    View next {rows.length} payments
                </Button>
            </CollapsibleTrigger>
            <CollapsibleContent asChild>
                <ul className="mt-2 grid">
                    {rows.map((row) => (
                        <li
                            key={row.date}
                            className="flex items-center justify-between gap-2.5 border-t border-border py-2"
                        >
                            <span className="min-w-0">
                                <span className="block text-[13.5px] font-semibold tabular-nums">
                                    {formatShortDate(row.date)}
                                </span>
                                <span
                                    className={cn(
                                        'block text-[12px]',
                                        row.overdue
                                            ? 'font-bold text-destructive'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {row.overdue ? 'Overdue' : 'Upcoming'}
                                </span>
                            </span>
                            <span className="text-[14.5px] font-bold whitespace-nowrap tabular-nums">
                                {formatCurrency(row.amount)}
                            </span>
                        </li>
                    ))}
                </ul>
            </CollapsibleContent>
        </Collapsible>
    );
}

function LoanCard({ loan }: { loan: MemberLoan }) {
    const loanNumber = formatLoanNumber(loan.lnnumber);
    const overdue = isLoanOverdue(loan);
    const dueDate = formatDate(loan.dueDate);
    const term = loanTermMonths(loan);
    const left = loanPaymentsLeft(loan);

    return (
        <article className="flex min-w-0 flex-col gap-3 rounded-[10px] border border-border bg-muted p-4">
            <div className="flex items-start justify-between gap-2.5">
                <div className="min-w-0">
                    <p className="truncate text-[16px] font-bold tabular-nums">
                        {loanNumber}
                    </p>
                    <span className="mt-1.5 inline-flex items-center rounded-full border border-border bg-card px-2.5 py-[3px] text-[12.5px] font-semibold text-muted-foreground">
                        {loanTypeLabel(loan.lntype)}
                    </span>
                </div>
                <span className="inline-flex shrink-0 items-center rounded-[6px] border border-primary/30 bg-primary/10 px-2.5 py-1 text-[12px] font-bold tracking-[0.04em] text-primary uppercase">
                    Active
                </span>
            </div>

            <div
                className={cn(
                    'flex flex-wrap items-center gap-2.5 rounded-lg border border-border bg-card p-3',
                    overdue && 'border-destructive bg-destructive/10',
                )}
            >
                <div className="min-w-0 flex-1">
                    <p
                        className={cn(
                            'text-[12px] font-semibold tracking-[0.05em] uppercase',
                            overdue
                                ? 'text-destructive'
                                : 'text-muted-foreground',
                        )}
                    >
                        Next payment due
                    </p>
                    <p
                        className={cn(
                            'text-[19px] font-bold whitespace-nowrap tabular-nums',
                            overdue && 'text-destructive',
                        )}
                    >
                        {formatCurrency(loan.monthlyDue ?? 0)}
                    </p>
                    <p
                        className={cn(
                            'text-[13px]',
                            overdue
                                ? 'text-destructive'
                                : 'text-muted-foreground',
                        )}
                    >
                        {overdue ? 'Overdue since ' : 'Due '}
                        {dueDate}
                    </p>
                    {loan.penalty ? (
                        <p className="text-[12px]">
                            Includes {formatCurrency(loan.penalty)} penalty
                        </p>
                    ) : null}
                </div>
                {overdue ? (
                    <span className="inline-flex shrink-0 items-center rounded-[6px] border border-destructive bg-destructive/10 px-2.5 py-1 text-[12px] font-bold tracking-[0.04em] text-destructive uppercase">
                        Overdue
                    </span>
                ) : null}
            </div>

            <div className="grid grid-cols-2 gap-x-3.5 gap-y-2.5 rounded-lg border border-border bg-card p-3">
                <Figure
                    label="Principal"
                    value={formatCurrency(loan.principal ?? 0)}
                />
                <Figure
                    label="Balance"
                    value={formatCurrency(loan.balance ?? 0)}
                />
                <Figure
                    label="Last payment"
                    value={formatDate(loan.lastmove)}
                    small
                />
                <Figure
                    label="Rate"
                    value={formatRateShort(loan.monthlyRate)}
                />
                <Figure
                    label="Payments left"
                    value={term === null ? `${left}` : `${left} of ${term}`}
                />
            </div>

            <RepaymentProgress loan={loan} />

            <ScheduleDisclosure loan={loan} />

            <Link
                href={`/client/loans/${encodeURIComponent(loanNumber)}/payments`}
                aria-label={`View payments for loan ${loanNumber}`}
                className={cn(
                    buttonVariants({ variant: 'outline' }),
                    'min-h-10 w-full',
                )}
            >
                View payments
            </Link>
        </article>
    );
}

function LoanCardsSkeleton() {
    return (
        <div aria-busy="true" className="grid gap-3">
            {Array.from({ length: 3 }).map((_, index) => (
                <div
                    key={`loan-card-skeleton-${index}`}
                    className="h-[84px] animate-pulse rounded-[10px] bg-muted"
                />
            ))}
        </div>
    );
}

function StateBox({
    icon: Icon,
    title,
    description,
    danger = false,
    action,
}: {
    icon: typeof AlertCircle;
    title: string;
    description: string;
    danger?: boolean;
    action?: { label: string; onClick: () => void };
}) {
    return (
        <div className="flex flex-col items-center gap-2.5 px-[18px] py-10 text-center">
            <Icon
                aria-hidden="true"
                className={cn(
                    'h-7 w-7',
                    danger ? 'text-destructive' : 'text-muted-foreground',
                )}
            />
            <p className="text-[16px] font-bold text-card-foreground">
                {title}
            </p>
            <p className="text-[15px] text-muted-foreground">{description}</p>
            {action ? (
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={action.onClick}
                >
                    {action.label}
                </Button>
            ) : null}
        </div>
    );
}

export function LoanCards({
    loans,
    isLoading = false,
    error = null,
    onRetry,
}: LoanCardsProps) {
    const activeLoans = loans.filter(isLoanActive);

    return (
        <SurfaceCard
            variant="default"
            padding="none"
            className="overflow-hidden"
        >
            <div className="border-b border-border px-[18px] py-4">
                <SectionHeader
                    title="Your loans"
                    description="Balance, repayment progress and next payment for your active loans."
                    titleClassName="text-[16px] font-bold"
                />
            </div>

            <div className="p-[18px]">
                {error ? (
                    <StateBox
                        icon={AlertCircle}
                        title="Unable to load loans"
                        description="Something went wrong while fetching your loan records."
                        danger
                        action={
                            onRetry
                                ? { label: 'Try again', onClick: onRetry }
                                : undefined
                        }
                    />
                ) : isLoading && activeLoans.length === 0 ? (
                    <LoanCardsSkeleton />
                ) : activeLoans.length === 0 ? (
                    <StateBox
                        icon={Landmark}
                        title="No active loans."
                        description="Paid loans remain listed in the All loans table below."
                    />
                ) : (
                    <div className="grid grid-cols-[repeat(auto-fill,minmax(300px,1fr))] gap-3.5">
                        {activeLoans.map((loan) => (
                            <LoanCard
                                key={`loan-card-${formatLoanNumber(loan.lnnumber)}`}
                                loan={loan}
                            />
                        ))}
                    </div>
                )}
            </div>
        </SurfaceCard>
    );
}
