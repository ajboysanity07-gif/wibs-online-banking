import { CalendarDays } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCurrency } from '@/lib/formatters';
import type { MemberRecentLoanPayment } from '@/types/admin';
import { DetailsLink } from './details-link';
import { ListPager } from './list-pager';
import {
    applyListTools,
    deriveOptions,
    describeListTools,
    initialListToolsValue,
    ListTools,
    type ListToolsConfig,
} from './list-tools';
import {
    formatMonthLabel,
    formatShortDate,
    paymentLoanLabel,
} from './loan-presentation';

type LoanPaymentsListProps = {
    payments: MemberRecentLoanPayment[] | null;
    isLoading?: boolean;
    error?: string | null;
    onRetry?: () => void;
};

const PER_PAGE = 5;

function paymentsConfig(
    rows: MemberRecentLoanPayment[],
): ListToolsConfig<MemberRecentLoanPayment> {
    return {
        searchPlaceholder: 'Search payments',
        searchAriaLabel: 'Search payments by loan no or date',
        searchMatch: (item, query) =>
            [
                item.lnnumber ?? '',
                item.lntype ?? '',
                item.date ?? '',
                formatShortDate(item.date),
                item.amount ?? '',
            ]
                .join(' ')
                .toLowerCase()
                .includes(query),
        filterDimensions: [
            {
                key: 'loan',
                label: 'Loan',
                options: deriveOptions(rows, (item) => item.lnnumber),
            },
            {
                key: 'month',
                label: 'Month',
                options: deriveOptions(
                    rows,
                    (item) => (item.date ? item.date.slice(0, 7) : null),
                    formatMonthLabel,
                    'desc',
                ),
            },
        ],
        filterMatch: (item, dimension, value) => {
            if (dimension === 'loan') {
                return String(item.lnnumber ?? '') === value;
            }

            return (item.date ?? '').slice(0, 7) === value;
        },
        sortFields: [
            { key: 'date', label: 'Date', kind: 'date' },
            { key: 'amount', label: 'Amount', kind: 'number' },
            { key: 'lnnumber', label: 'Loan no', kind: 'text' },
        ],
        sortValue: (item, sortKey) => {
            switch (sortKey) {
                case 'date':
                    return item.date ?? null;
                case 'amount':
                    return item.amount ?? null;
                case 'lnnumber':
                    return item.lnnumber ?? null;
                default:
                    return null;
            }
        },
    };
}

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
}: LoanPaymentsListProps) {
    const rows = useMemo(() => payments ?? [], [payments]);
    const [tools, setTools] = useState(() =>
        initialListToolsValue('loan', 'date', 'desc'),
    );
    const [page, setPage] = useState(1);

    const handleToolsChange = useCallback((next: typeof tools) => {
        setTools(next);
        setPage(1);
    }, []);

    const config = useMemo(() => paymentsConfig(rows), [rows]);
    const visible = useMemo(
        () => applyListTools(rows, tools, config),
        [rows, tools, config],
    );
    const lastPage = Math.max(1, Math.ceil(visible.length / PER_PAGE));
    const currentPage = Math.min(page, lastPage);
    const pageItems = visible.slice(
        (currentPage - 1) * PER_PAGE,
        currentPage * PER_PAGE,
    );
    const summary = describeListTools(tools, config);
    const showSkeleton = isLoading && rows.length === 0;

    return (
        <SurfaceCard
            variant="default"
            padding="none"
            className="overflow-hidden"
        >
            <div className="border-b border-border px-[18px] py-4">
                <SectionHeader
                    title={
                        <span className="inline-flex items-center gap-2 text-[16px]">
                            <CalendarDays
                                aria-hidden="true"
                                className="h-[18px] w-[18px] shrink-0 text-muted-foreground"
                            />
                            Payment history
                        </span>
                    }
                    description="Recent payments across all loans. Select a payment to open that loan's payment history."
                    titleClassName="text-lg font-semibold"
                    className="sm:items-end"
                    actionsClassName="w-full sm:w-auto sm:max-w-[480px] sm:flex-1"
                    actions={
                        <ListTools
                            config={config}
                            value={tools}
                            onChange={handleToolsChange}
                            filterKey="payments"
                            searchClearAriaLabel="Clear payment search"
                        />
                    }
                />
            </div>

            {error ? (
                <div className="flex flex-col items-center gap-2.5 px-[18px] py-10 text-center">
                    <p className="text-base font-bold">
                        Unable to load payments
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Something went wrong while fetching your payment
                        history.
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
            ) : showSkeleton ? (
                <div className="px-[18px] py-4">
                    <PaymentsSkeleton />
                </div>
            ) : rows.length === 0 ? (
                <div className="flex flex-col items-center gap-2.5 px-[18px] py-10 text-center">
                    <p className="text-base font-bold">No payments yet.</p>
                    <p className="text-sm text-muted-foreground">
                        Your loan payments will be listed here.
                    </p>
                </div>
            ) : (
                <div>
                    {pageItems.map((payment, index) => (
                        <div
                            key={`payment-${payment.lnnumber ?? index}-${payment.date ?? index}`}
                            className="grid grid-cols-1 items-center gap-1.5 border-b border-border px-[18px] py-[14px] last:border-b-0 hover:bg-muted sm:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto] sm:gap-3.5"
                        >
                            <div className="order-1 min-w-0">
                                <span className="block text-[14.5px] font-semibold tabular-nums">
                                    {formatShortDate(payment.date)}
                                </span>
                                <span className="block truncate text-[13px] text-muted-foreground">
                                    {paymentLoanLabel(payment)}
                                </span>
                            </div>

                            <div className="order-3 grid min-w-0 gap-0.5 sm:order-2">
                                <span className="block text-[13px] text-muted-foreground tabular-nums">
                                    Principal{' '}
                                    <strong className="font-bold whitespace-nowrap text-card-foreground">
                                        {formatCurrency(payment.principal)}
                                    </strong>
                                </span>
                                <span className="block text-[13px] text-muted-foreground tabular-nums">
                                    Interest{' '}
                                    <strong className="font-bold whitespace-nowrap text-card-foreground">
                                        {formatCurrency(payment.interest)}
                                    </strong>
                                </span>
                            </div>

                            <div className="order-2 flex w-full items-center justify-between gap-3 sm:order-3 sm:w-auto sm:justify-end">
                                <span className="text-[16px] font-bold whitespace-nowrap tabular-nums">
                                    {formatCurrency(payment.amount ?? 0)}
                                </span>
                                <DetailsLink
                                    href={`/client/loans/${encodeURIComponent(String(payment.lnnumber ?? '').trim())}/payments#history`}
                                    label={`Open the payment history of loan ${payment.lnnumber ?? '--'} for the payment on ${formatShortDate(payment.date)}`}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {!error && !showSkeleton ? (
                <div className="border-t border-border px-[18px] py-[14px]">
                    <ListPager
                        page={currentPage}
                        perPage={PER_PAGE}
                        total={visible.length}
                        noun="payments"
                        summary={summary}
                        paginationLabel="Payments pagination"
                        onPageChange={setPage}
                    />
                </div>
            ) : null}
        </SurfaceCard>
    );
}
