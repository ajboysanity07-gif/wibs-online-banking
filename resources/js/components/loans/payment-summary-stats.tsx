import type { LucideIcon } from 'lucide-react';
import { Banknote, CalendarClock, Clock, ShieldCheck } from 'lucide-react';
import { SurfaceCard } from '@/components/surface-card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCurrency } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { MemberLoan, MemberLoanSummary } from '@/types/admin';
import {
    currencyOrDash,
    formatShortDate,
    isLoanActive,
    isLoanOverdue,
    loanRepaidAmount,
    loanRepaidPercent,
    loanTypeLabel,
} from './loan-presentation';

type PaymentSummaryStatsProps = {
    loan: MemberLoan;
    summary: MemberLoanSummary;
    securityBalance?: number;
    isLoading?: boolean;
};

type StatCardProps = {
    title: string;
    value: string;
    helper: string;
    icon: LucideIcon;
    tone?: 'primary' | 'plain' | 'overdue';
    compactValue?: boolean;
};

const toneClasses: Record<NonNullable<StatCardProps['tone']>, string> = {
    primary: 'border-primary bg-primary text-primary-foreground',
    plain: 'border-border bg-card text-card-foreground',
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
            padding="md"
            className={cn('min-w-0', toneClasses[tone])}
        >
            <div className="flex flex-col gap-2">
                <div className="flex items-center justify-between gap-3">
                    <p
                        className={cn(
                            'text-[13px] font-semibold',
                            !isPrimary && 'text-muted-foreground',
                        )}
                    >
                        {title}
                    </p>
                    <Icon
                        aria-hidden="true"
                        className={cn(
                            'h-5 w-5 shrink-0',
                            isPrimary
                                ? 'text-primary-foreground/85'
                                : isOverdue
                                  ? 'text-destructive'
                                  : 'text-muted-foreground',
                        )}
                    />
                </div>
                <p
                    className={cn(
                        'font-bold tracking-tight tabular-nums',
                        compactValue
                            ? 'text-[22px] sm:text-[26px]'
                            : 'text-[26px] sm:text-[34px]',
                    )}
                >
                    {value}
                </p>
                <p
                    className={cn(
                        'text-[13px] leading-snug',
                        isPrimary
                            ? 'text-primary-foreground/90'
                            : 'text-muted-foreground',
                    )}
                >
                    {helper}
                </p>
            </div>
        </SurfaceCard>
    );
}

function PaymentSummaryStatsSkeleton() {
    return (
        <section
            aria-label="Loan payment summary"
            className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            {Array.from({ length: 4 }).map((_, index) => (
                <SurfaceCard
                    key={`payment-stat-skeleton-${index}`}
                    variant="default"
                    padding="md"
                >
                    <div className="flex flex-col gap-3" aria-busy="true">
                        <Skeleton className="h-3 w-28" />
                        <Skeleton className="h-8 w-32" />
                        <Skeleton className="h-3 w-36" />
                    </div>
                </SurfaceCard>
            ))}
        </section>
    );
}

export function PaymentSummaryStats({
    loan,
    summary,
    securityBalance = 0,
    isLoading = false,
}: PaymentSummaryStatsProps) {
    if (isLoading) {
        return <PaymentSummaryStatsSkeleton />;
    }

    const balance = summary.balance;
    const nextPaymentDate = isLoanActive({ balance })
        ? (summary.next_payment_date ?? loan.dueDate)
        : null;
    const overdue = isLoanOverdue({ dueDate: nextPaymentDate });
    const hasNextPayment = nextPaymentDate !== null;

    return (
        <section
            aria-label="Loan payment summary"
            className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <StatCard
                title="Outstanding Balance"
                value={currencyOrDash(balance)}
                helper={`${loanRepaidPercent(loan)}% repaid · ${formatCurrency(
                    loanRepaidAmount(loan),
                )} of ${formatCurrency(loan.principal ?? 0)} principal`}
                icon={Banknote}
                tone="primary"
            />
            <StatCard
                title="Next Payment Due"
                value={hasNextPayment ? currencyOrDash(loan.monthlyDue) : '—'}
                helper={
                    hasNextPayment
                        ? `${overdue ? 'Overdue since ' : 'Due '}${formatShortDate(nextPaymentDate)} · ${loanTypeLabel(loan.lntype)}`
                        : 'No payments due.'
                }
                icon={CalendarClock}
                tone={overdue ? 'overdue' : 'plain'}
                compactValue
            />
            <StatCard
                title="Last Payment"
                value={formatShortDate(
                    summary.last_payment_date ?? loan.lastmove,
                )}
                helper={
                    (summary.last_payment_date ?? loan.lastmove)
                        ? 'Most recent posted payment.'
                        : 'No payment recorded yet.'
                }
                icon={Clock}
                compactValue
            />

            <StatCard
                title="Loan Security Contribution"
                value={currencyOrDash(securityBalance)}
                helper="Held against your loan balance."
                icon={ShieldCheck}
                compactValue
            />
        </section>
    );
}
