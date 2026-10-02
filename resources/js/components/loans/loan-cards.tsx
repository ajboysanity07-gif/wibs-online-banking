import { AlertCircle, Landmark } from 'lucide-react';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCurrency, formatDate } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { MemberLoan } from '@/types/admin';
import {
    formatLoanNumber,
    formatShortDate,
    isLoanActive,
    isLoanOverdue,
    loanRepaidAmount,
    loanRepaidPercent,
    loanScheduleRows,
    loanTypeLabel,
} from './loan-presentation';

type LoanCardsProps = {
    loans: MemberLoan[];
    isLoading?: boolean;
    error?: string | null;
    onRetry?: () => void;
    onViewSchedule?: (loanNumber: string) => void;
    onViewPayments?: (loanNumber: string) => void;
    onPayNow?: (loanNumber: string) => void;
};

const FIGURE_GRID_COLUMNS = 'grid grid-cols-2 gap-x-4 gap-y-3';

function Figure({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <p className="text-[11px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                {label}
            </p>
            <p className="truncate text-[15px] font-bold tabular-nums">{value}</p>
        </div>
    );
}

function RepaymentProgress({ loan }: { loan: MemberLoan }) {
    const percent = loanRepaidPercent(loan);
    const repaid = loanRepaidAmount(loan);

    return (
        <div className="flex flex-col gap-1.5">
            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-[13px] text-muted-foreground">
                <span>
                    Repaid: <strong className="text-card-foreground">{percent}%</strong>
                </span>
                <span className="tabular-nums">
                    <strong className="text-card-foreground">
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
                className="h-2 w-full overflow-hidden rounded-full bg-muted"
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
    const rows = loanScheduleRows(loan);

    if (rows.length === 0) {
        return null;
    }

    return (
        <details className="group border-t border-border pt-2">
            <summary className="flex min-h-11 cursor-pointer list-none items-center gap-1.5 text-[13.5px] font-semibold text-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none">
                View next {rows.length} payments
            </summary>
            <ul className="mt-2 flex flex-col">
                {rows.map((row) => (
                    <li
                        key={row.date}
                        className="flex items-center justify-between gap-3 border-t border-border py-2"
                    >
                        <span className="min-w-0">
                            <span className="block text-[13.5px] font-semibold tabular-nums">
                                {formatShortDate(row.date)}
                            </span>
                            <span
                                className={cn(
                                    'block text-xs',
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
        </details>
    );
}

function LoanCard({
    loan,
    onViewSchedule,
    onViewPayments,
    onPayNow,
}: {
    loan: MemberLoan;
    onViewSchedule?: (loanNumber: string) => void;
    onViewPayments?: (loanNumber: string) => void;
    onPayNow?: (loanNumber: string) => void;
}) {
    const loanNumber = formatLoanNumber(loan.lnnumber);
    const overdue = isLoanOverdue(loan);
    const dueDate = formatDate(loan.dueDate);

    return (
        <SurfaceCard
            variant="muted"
            padding="md"
            className="flex min-w-0 flex-col gap-3 rounded-[10px]"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="truncate text-[16px] font-bold tabular-nums">
                        {loanNumber}
                    </p>
                    <Badge
                        variant="outline"
                        className="mt-1.5 bg-card text-muted-foreground"
                    >
                        {loanTypeLabel(loan.lntype)}
                    </Badge>
                </div>
                {overdue ? (
                    <Badge className="shrink-0 bg-destructive text-destructive-foreground">
                        Overdue
                    </Badge>
                ) : (
                    <Badge className="shrink-0 border border-border bg-primary/10 text-primary">
                        Active
                    </Badge>
                )}
            </div>

            <div
                className={cn(
                    'flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-card p-3',
                    overdue && 'border-destructive bg-destructive/10',
                )}
            >
                <div className="min-w-0">
                    <p
                        className={cn(
                            'text-[11px] font-semibold tracking-[0.05em] uppercase',
                            overdue ? 'text-destructive' : 'text-muted-foreground',
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
                            overdue ? 'text-destructive' : 'text-muted-foreground',
                        )}
                    >
                        {overdue ? 'Overdue since ' : 'Due '}
                        {dueDate}
                    </p>
                </div>
                {overdue ? (
                    <Badge className="shrink-0 bg-destructive text-destructive-foreground">
                        Overdue
                    </Badge>
                ) : null}
            </div>

            <div className={cn(FIGURE_GRID_COLUMNS, 'rounded-lg border border-border bg-card p-3')}>
                <Figure label="Principal" value={formatCurrency(loan.principal ?? 0)} />
                <Figure label="Balance" value={formatCurrency(loan.balance ?? 0)} />
                <Figure label="Last payment" value={formatDate(loan.lastmove)} />
                <Figure label="Monthly due" value={formatCurrency(loan.monthlyDue ?? 0)} />
            </div>

            <RepaymentProgress loan={loan} />

            <ScheduleDisclosure loan={loan} />

            <div className="flex flex-wrap gap-2 border-t border-border pt-3">
                {onViewSchedule ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => onViewSchedule(loanNumber)}
                    >
                        Schedule
                    </Button>
                ) : null}
                {onViewPayments ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => onViewPayments(loanNumber)}
                    >
                        Payments
                    </Button>
                ) : null}
                {onPayNow && (loan.balance ?? 0) > 0 ? (
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => onPayNow(loanNumber)}
                    >
                        Pay Now
                    </Button>
                ) : null}
            </div>
        </SurfaceCard>
    );
}

function LoanCardsSkeleton() {
    return (
        <div aria-busy="true" className="flex flex-col gap-3.5">
            {Array.from({ length: 3 }).map((_, index) => (
                <SurfaceCard
                    key={`loan-card-skeleton-${index}`}
                    variant="muted"
                    padding="md"
                    className="flex flex-col gap-3"
                >
                    <div className="flex items-start justify-between gap-3">
                        <div className="flex flex-col gap-2">
                            <Skeleton className="h-5 w-32" />
                            <Skeleton className="h-5 w-28 rounded-full" />
                        </div>
                        <Skeleton className="h-5 w-16 rounded-md" />
                    </div>
                    <Skeleton className="h-[74px] w-full rounded-lg" />
                    <Skeleton className="h-24 w-full rounded-lg" />
                    <Skeleton className="h-2 w-full rounded-full" />
                </SurfaceCard>
            ))}
        </div>
    );
}

function StateBox({
    icon: Icon,
    title,
    description,
    action,
}: {
    icon: typeof AlertCircle;
    title: string;
    description: string;
    action?: { label: string; onClick: () => void };
}) {
    return (
        <div className="flex flex-col items-center gap-2.5 px-4 py-10 text-center">
            <Icon aria-hidden="true" className="h-7 w-7 text-muted-foreground" />
            <p className="text-base font-bold">{title}</p>
            <p className="text-sm text-muted-foreground">{description}</p>
            {action ? (
                <Button type="button" size="sm" variant="outline" onClick={action.onClick}>
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
    onViewSchedule,
    onViewPayments,
    onPayNow,
}: LoanCardsProps) {
    const activeLoans = loans.filter(isLoanActive);

    return (
        <SurfaceCard variant="default" padding="md" className="flex flex-col gap-5">
            <SectionHeader
                title="Your loans"
                description="Balance, repayment progress and next payment for your active loans."
                titleClassName="text-lg font-semibold"
            />

            {error ? (
                <StateBox
                    icon={AlertCircle}
                    title="Unable to load loans"
                    description="Something went wrong while fetching your loan records."
                    action={onRetry ? { label: 'Try again', onClick: onRetry } : undefined}
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
                <div className="grid grid-cols-1 gap-3.5 lg:grid-cols-2 2xl:grid-cols-3">
                    {activeLoans.map((loan) => (
                        <LoanCard
                            key={`loan-card-${formatLoanNumber(loan.lnnumber)}`}
                            loan={loan}
                            onViewSchedule={onViewSchedule}
                            onViewPayments={onViewPayments}
                            onPayNow={onPayNow}
                        />
                    ))}
                </div>
            )}
        </SurfaceCard>
    );
}