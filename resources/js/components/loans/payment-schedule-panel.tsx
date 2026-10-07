import { CalendarClock } from 'lucide-react';
import { useMemo, useState } from 'react';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { MemberLoan, MemberLoanScheduleEntry } from '@/types/admin';
import {
    currencyOrDash,
    formatShortDate,
    loanRepaidAmount,
    loanRepaidPercent,
} from './loan-presentation';
import {
    paymentScheduleRows,
    scheduleMonthlyDue,
    schedulePaymentsLeft,
    scheduleStatusClasses,
    scheduleStatusLabel,
    type PaymentStatusPill,
} from './payment-presentation';

const SCHEDULE_FILTERS: Array<{
    value: 'all' | PaymentStatusPill;
    label: string;
}> = [
    { value: 'all', label: 'All' },
    { value: 'paid', label: 'Paid' },
    { value: 'due', label: 'Due' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'upcoming', label: 'Scheduled' },
];

type PaymentSchedulePanelProps = {
    loan: MemberLoan;
    schedule: MemberLoanScheduleEntry[];
};

const FIGURE_GRID_COLUMNS = 'grid grid-cols-2 gap-x-4 gap-y-3 lg:grid-cols-5';

function Figure({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <p className="text-[11px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                {label}
            </p>
            <p className="truncate text-[17px] font-bold tabular-nums">
                {value}
            </p>
        </div>
    );
}

export function PaymentSchedulePanel({
    loan,
    schedule,
}: PaymentSchedulePanelProps) {
    const rows = paymentScheduleRows(schedule, loan.balance ?? 0);
    const [statusFilter, setStatusFilter] = useState<'all' | PaymentStatusPill>(
        'all',
    );
    const visibleRows = useMemo(
        () =>
            statusFilter === 'all'
                ? rows
                : rows.filter((row) => row.status === statusFilter),
        [rows, statusFilter],
    );

    if (rows.length === 0) {
        return (
            <SurfaceCard variant="default" padding="md" className="space-y-4">
                <SectionHeader
                    title="Repayment progress"
                    description="How far along this loan is."
                    titleClassName="text-base font-semibold"
                />
                <div className="flex flex-col items-center gap-2.5 px-4 py-10 text-center">
                    <CalendarClock
                        aria-hidden="true"
                        className="h-7 w-7 text-muted-foreground"
                    />
                    <p className="text-base font-bold">
                        No repayment schedule.
                    </p>
                    <p className="text-sm text-muted-foreground">
                        This loan has no amortization schedule recorded yet.
                    </p>
                </div>
            </SurfaceCard>
        );
    }

    const percent = loanRepaidPercent(loan);
    const repaid = loanRepaidAmount(loan);
    const monthlyDue = scheduleMonthlyDue(rows);
    const paymentsLeft = schedulePaymentsLeft(rows);
    const term = rows.length;
    const nextRow = rows.find(
        (row) => row.status === 'due' || row.status === 'overdue',
    );

    return (
        <div className="flex flex-col gap-4">
            <SurfaceCard variant="default" padding="md" className="space-y-4">
                <SectionHeader
                    title="Repayment progress"
                    description="How far along this loan is."
                    titleClassName="text-base font-semibold"
                />

                <div className={cn(FIGURE_GRID_COLUMNS)}>
                    <Figure
                        label="Principal"
                        value={currencyOrDash(loan.principal ?? 0)}
                    />
                    <Figure
                        label="Outstanding balance"
                        value={currencyOrDash(loan.balance ?? 0)}
                    />
                    <Figure
                        label="Monthly due"
                        value={currencyOrDash(monthlyDue)}
                    />
                    <Figure
                        label="Rate per month"
                        value={
                            loan.monthlyRate != null
                                ? `${loan.monthlyRate}%`
                                : '--'
                        }
                    />
                    <Figure
                        label="Payments left"
                        value={`${paymentsLeft} of ${term}`}
                    />
                </div>

                <div className="flex flex-col gap-1.5">
                    <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-[13px] text-muted-foreground">
                        <span>
                            Repaid:{' '}
                            <strong className="text-card-foreground">
                                {percent}%
                            </strong>
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
            </SurfaceCard>

            <SurfaceCard variant="default" padding="md" className="space-y-4">
                <SectionHeader
                    title="Amortization schedule"
                    description="Principal and interest breakdown for every installment."
                    actions={
                        <Badge
                            className={cn(
                                'shrink-0',
                                nextRow
                                    ? scheduleStatusClasses(nextRow.status)
                                    : 'border-primary/30 bg-primary/10 text-primary',
                            )}
                        >
                            {nextRow
                                ? `Next: ${formatShortDate(nextRow.date)}`
                                : 'Fully paid'}
                        </Badge>
                    }
                    titleClassName="text-base font-semibold"
                />

                <div
                    className="flex flex-wrap items-center gap-1.5"
                    role="group"
                    aria-label="Filter installments by status"
                >
                    {SCHEDULE_FILTERS.map((filter) => (
                        <Button
                            key={filter.value}
                            type="button"
                            variant="ghost"
                            aria-pressed={statusFilter === filter.value}
                            onClick={() => setStatusFilter(filter.value)}
                            className={cn(
                                'h-auto min-h-9 rounded-full border px-3.5 py-1 text-[13px] font-semibold md:h-auto',
                                statusFilter === filter.value
                                    ? 'border-primary bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground'
                                    : 'border-border bg-card text-muted-foreground hover:bg-muted hover:text-muted-foreground',
                            )}
                        >
                            {filter.label}
                        </Button>
                    ))}
                    <span className="ml-1 text-[12.5px] text-muted-foreground">
                        Showing {visibleRows.length} of {rows.length}{' '}
                        installments
                    </span>
                </div>

                <div className="max-h-[420px] overflow-y-auto">
                    {visibleRows.length === 0 ? (
                        <p
                            className="px-4 py-10 text-center text-sm text-muted-foreground"
                            role="status"
                        >
                            No installments match this filter.
                        </p>
                    ) : null}
                    <div className="flex flex-col">
                        {visibleRows.map((row) => (
                            <div
                                key={row.key}
                                className={cn(
                                    'flex flex-wrap items-center justify-between gap-3 border-b border-border px-1 py-3 last:border-b-0 md:px-0',
                                    row.status === 'due' &&
                                        'rounded-[10px] bg-primary/10 px-3 md:px-3',
                                )}
                            >
                                <div className="min-w-0">
                                    <p className="text-[14.5px] font-semibold tabular-nums">
                                        {formatShortDate(row.date)}
                                    </p>
                                    <p className="text-[12.5px] text-muted-foreground">
                                        Installment {row.installment} of {term}
                                    </p>
                                    {row.interest !== null ? (
                                        <p className="text-[12.5px] text-muted-foreground tabular-nums">
                                            Principal{' '}
                                            {formatCurrency(
                                                row.amount - row.interest,
                                            )}{' '}
                                            · Interest{' '}
                                            {formatCurrency(row.interest)}
                                            {row.balance !== null
                                                ? ` · Balance ${formatCurrency(row.balance)}`
                                                : ''}
                                        </p>
                                    ) : null}
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="text-[15px] font-bold whitespace-nowrap tabular-nums">
                                        {formatCurrency(row.amount)}
                                    </span>
                                    <Badge
                                        className={cn(
                                            'shrink-0 uppercase',
                                            scheduleStatusClasses(row.status),
                                        )}
                                    >
                                        {scheduleStatusLabel(row.status)}
                                    </Badge>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
                <p className="text-[12.5px] text-muted-foreground">
                    Amounts are estimates and may change with early or late
                    payments.
                </p>
            </SurfaceCard>
        </div>
    );
}
