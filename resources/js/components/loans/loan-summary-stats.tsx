import type { LucideIcon } from 'lucide-react';
import { Banknote, CalendarClock, Clock, FileText } from 'lucide-react';
import { SurfaceCard } from '@/components/surface-card';
import { cn } from '@/lib/utils';
import type { MemberAccountsSummary, MemberLoan } from '@/types/admin';
import {
    currencyOrDash,
    formatShortDate,
    isLoanOverdue,
    loanTypeLabel,
    nextLoanDue,
    outstandingBalance,
    countActiveLoans,
} from './loan-presentation';

type LoanSummaryStatsProps = {
    summary: MemberAccountsSummary | null;
    loans: MemberLoan[];
};

type StatCardProps = {
    title: string;
    value: string;
    helper: string;
    icon: LucideIcon;
    tone?: 'primary' | 'plain' | 'due' | 'overdue';
    compactValue?: boolean;
};

const toneClasses: Record<NonNullable<StatCardProps['tone']>, string> = {
    primary: 'border-primary bg-primary text-primary-foreground',
    plain: 'border-border bg-card text-card-foreground',
    due: 'border-border bg-card text-card-foreground',
    overdue: 'border-destructive bg-destructive/10 text-destructive',
};

function StatCard({
    title,
    value,
    helper,
    icon: Icon,
    tone = 'plain',
    compactValue = false,
}: StatCardProps) {
    const isPrimary = tone === 'primary';
    const isOverdue = tone === 'overdue';

    return (
        <SurfaceCard
            variant="default"
            padding="none"
            className={cn('min-w-0 rounded-[10px] p-[18px]', toneClasses[tone])}
        >
            <div className="flex flex-col gap-2">
                <div className="flex items-center justify-between gap-2.5">
                    <p
                        className={cn(
                            'text-[13px] leading-[1.35] font-semibold',
                            tone === 'plain' && 'text-muted-foreground',
                        )}
                    >
                        {title}
                    </p>
                    <Icon
                        aria-hidden="true"
                        className={cn(
                            'h-5 w-5 shrink-0 opacity-85',
                            isPrimary
                                ? 'text-primary-foreground'
                                : isOverdue
                                  ? 'text-destructive'
                                  : 'text-muted-foreground',
                        )}
                    />
                </div>
                <p
                    className={cn(
                        'font-bold tracking-[-0.01em] whitespace-nowrap tabular-nums',
                        compactValue
                            ? 'text-[clamp(20px,2.4vw,26px)]'
                            : 'text-[clamp(26px,3.2vw,34px)]',
                    )}
                >
                    {value}
                </p>
                <p
                    className={cn(
                        'text-[13px] leading-[1.4] opacity-90',
                        isPrimary && 'text-primary-foreground',
                        tone === 'plain' && 'text-muted-foreground',
                    )}
                >
                    {helper}
                </p>
            </div>
        </SurfaceCard>
    );
}

export function LoanSummaryStats({ summary, loans }: LoanSummaryStatsProps) {
    const due = nextLoanDue(loans);
    const dueOverdue = due !== null && isLoanOverdue(due);

    return (
        <section
            aria-label="Loan summary"
            className="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-3.5"
        >
            <StatCard
                title="Total Outstanding Loan Balance"
                value={currencyOrDash(outstandingBalance(loans))}
                helper="Sum of outstanding loan balances."
                icon={Banknote}
                tone="primary"
            />
            <StatCard
                title="Next Payment Due"
                value={due === null ? '—' : currencyOrDash(due.monthlyDue)}
                helper={
                    due === null
                        ? 'No payments due.'
                        : `${dueOverdue ? 'Overdue · ' : 'Due '}${formatShortDate(due.dueDate)} · ${loanTypeLabel(due.lntype)}`
                }
                icon={CalendarClock}
                tone={dueOverdue ? 'overdue' : due === null ? 'plain' : 'due'}
            />
            <StatCard
                title="Active Loans"
                value={`${countActiveLoans(loans)} of ${loans.length}`}
                helper="Loans with an outstanding balance."
                icon={FileText}
            />
            <StatCard
                title="Last Loan Transaction"
                value={formatShortDate(
                    summary?.lastLoanTransactionDate ?? null,
                )}
                helper="Most recent loan activity date."
                icon={Clock}
                compactValue
            />
        </section>
    );
}
