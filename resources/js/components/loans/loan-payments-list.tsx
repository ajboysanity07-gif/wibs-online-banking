import { Download } from 'lucide-react';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCurrency } from '@/lib/formatters';
import type { MemberRecentLoanPayment } from '@/types/admin';
import {
    formatShortDate,
    loanTypeLabel,
    paymentLoanLabel,
} from './loan-presentation';

type LoanPaymentsListProps = {
    payments: MemberRecentLoanPayment[] | null;
    isLoading?: boolean;
    error?: string | null;
    onRetry?: () => void;
    onRequestReceipt?: (payment: MemberRecentLoanPayment) => void;
};

function PaymentsSkeleton() {
    return (
        <div aria-busy="true" className="flex flex-col">
            {Array.from({ length: 4 }).map((_, index) => (
                <div
                    key={`payment-skeleton-${index}`}
                    className="flex items-center gap-4 border-b border-border px-5 py-4 last:border-b-0"
                >
                    <div className="flex flex-1 flex-col gap-2">
                        <Skeleton className="h-4 w-28" />
                        <Skeleton className="h-3 w-40" />
                    </div>
                    <Skeleton className="hidden h-8 w-40 sm:block" />
                    <Skeleton className="h-4 w-24" />
                </div>
            ))}
        </div>
    );
}

export function LoanPaymentsList({
    payments,
    isLoading = false,
    error = null,
    onRetry,
    onRequestReceipt,
}: LoanPaymentsListProps) {
    const rows = payments ?? [];

    return (
        <SurfaceCard variant="default" padding="md" className="flex flex-col gap-5">
            <SectionHeader
                title="Payment history"
                description="Recent payments across all loans."
                titleClassName="text-lg font-semibold"
            />

            {error ? (
                <div className="flex flex-col items-center gap-2.5 px-4 py-10 text-center">
                    <p className="text-base font-bold">Unable to load payments</p>
                    <p className="text-sm text-muted-foreground">
                        Something went wrong while fetching your payment history.
                    </p>
                    {onRetry ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={onRetry}
                        >
                            Try again
                        </Button>
                    ) : null}
                </div>
            ) : isLoading && rows.length === 0 ? (
                <PaymentsSkeleton />
            ) : rows.length === 0 ? (
                <div className="flex flex-col items-center gap-2.5 px-4 py-10 text-center">
                    <p className="text-base font-bold">No payments yet.</p>
                    <p className="text-sm text-muted-foreground">
                        Your loan payments will be listed here.
                    </p>
                </div>
            ) : (
                <ul className="flex flex-col">
                    {rows.map((payment, index) => (
                        <li
                            key={`payment-${payment.lnnumber ?? index}-${payment.date ?? index}`}
                            className="grid grid-cols-1 items-center gap-2 border-b border-border px-5 py-4 transition-colors last:border-b-0 hover:bg-muted sm:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto] sm:gap-4"
                        >
                            <div className="min-w-0">
                                <span className="block text-[14.5px] font-semibold tabular-nums">
                                    {formatShortDate(payment.date)}
                                </span>
                                <span className="block truncate text-[13px] text-muted-foreground">
                                    {paymentLoanLabel(payment)}
                                </span>
                            </div>

                            <div className="flex min-w-0 flex-col gap-0.5 sm:gap-1">
                                <span className="block text-[13px] text-muted-foreground tabular-nums">
                                    Principal{' '}
                                    <strong className="text-card-foreground">
                                        {formatCurrency(payment.principal ?? 0)}
                                    </strong>
                                </span>
                                <span className="block text-[13px] text-muted-foreground tabular-nums">
                                    Interest{' '}
                                    <strong className="text-card-foreground">
                                        {formatCurrency(payment.interest ?? 0)}
                                    </strong>
                                </span>
                            </div>

                            <div className="flex items-center justify-between gap-3 sm:justify-end">
                                <span className="text-base font-bold whitespace-nowrap tabular-nums">
                                    {formatCurrency(payment.amount ?? 0)}
                                </span>
                                {onRequestReceipt ? (
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="outline"
                                        className="min-h-11 min-w-11"
                                        aria-label={`Download receipt for the ${payment.lnnumber ?? loanTypeLabel(payment.lntype)} payment on ${formatShortDate(payment.date)}`}
                                        onClick={() => onRequestReceipt(payment)}
                                    >
                                        <Download aria-hidden="true" />
                                    </Button>
                                ) : null}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </SurfaceCard>
    );
}