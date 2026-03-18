import type { ColumnDef } from '@tanstack/react-table';
import { Receipt } from 'lucide-react';
import { useMemo } from 'react';
import { MemberRecordsCard } from '@/components/member-records-card';
import { DataTable } from '@/components/ui/data-table';
import {
    DataTablePagination,
    DataTablePaginationSkeleton,
} from '@/components/ui/data-table-pagination';
import { Skeleton } from '@/components/ui/skeleton';
import { TableSkeleton } from '@/components/ui/table-skeleton';
import { formatCurrency, formatDate } from '@/lib/formatters';
import type {
    MemberLoanPayment,
    PaginationMeta,
} from '@/types/admin';

type MemberLoanPaymentsRecordsCardProps = {
    items: MemberLoanPayment[];
    meta: PaginationMeta;
    isUpdating?: boolean;
    error?: string | null;
    onRetry?: () => void;
    onPageChange: (page: number) => void;
    emptyMessage?: string;
    showSkeleton?: boolean;
};

const paymentTableSkeletonColumns = [
    { headerClassName: 'w-24', cellClassName: 'w-28' },
    { headerClassName: 'w-24', cellClassName: 'w-28' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
];

const MobilePaymentCardSkeleton = () => (
    <div className="rounded-lg border border-border bg-card p-4">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-2">
                <Skeleton className="h-4 w-28" />
                <Skeleton className="h-3 w-24" />
            </div>
            <div className="space-y-2 text-right">
                <Skeleton className="ml-auto h-3 w-16" />
                <Skeleton className="ml-auto h-6 w-20" />
            </div>
        </div>
        <div className="mt-3 space-y-2 rounded-md border border-border/60 bg-muted/40 p-3">
            {Array.from({ length: 2 }).map((_, index) => (
                <div
                    key={`payment-card-meta-${index}`}
                    className="flex items-center justify-between"
                >
                    <Skeleton className="h-3 w-20" />
                    <Skeleton className="h-4 w-24" />
                </div>
            ))}
        </div>
    </div>
);

const MobilePaymentCardSkeletonList = ({ rows = 4 }: { rows?: number }) => (
    <div className="space-y-3">
        {Array.from({ length: rows }).map((_, index) => (
            <MobilePaymentCardSkeleton key={`payment-card-${index}`} />
        ))}
    </div>
);

const MobilePaymentCard = ({ payment }: { payment: MemberLoanPayment }) => (
    <div className="rounded-lg border border-border bg-card p-4">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-1">
                <p className="text-sm font-semibold">
                    {payment.reference_no ?? payment.control_no ?? '--'}
                </p>
                <p className="text-xs text-muted-foreground">
                    {formatDate(payment.date_in)}
                </p>
            </div>
            <div className="text-right">
                <p className="text-xs text-muted-foreground">Payment</p>
                <p className="text-lg font-semibold tabular-nums">
                    {formatCurrency(payment.payment_amount)}
                </p>
            </div>
        </div>
        <div className="mt-3 rounded-md border border-border/60 bg-muted/40 p-3">
            <div className="flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Balance</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatCurrency(payment.balance)}
                </span>
            </div>
            <div className="mt-2 flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Principal</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatCurrency(payment.principal)}
                </span>
            </div>
        </div>
    </div>
);

export function MemberLoanPaymentsRecordsCard({
    items,
    meta,
    isUpdating = false,
    error = null,
    onRetry,
    onPageChange,
    emptyMessage,
    showSkeleton,
}: MemberLoanPaymentsRecordsCardProps) {
    const paymentsEmptyMessage =
        emptyMessage ?? 'No payments found for this period.';
    const showSkeletonState =
        showSkeleton ?? (isUpdating && items.length === 0);

    const columns = useMemo<ColumnDef<MemberLoanPayment>[]>(
        () => [
            {
                accessorKey: 'date_in',
                header: 'Transaction Date',
                cell: ({ row }) => formatDate(row.original.date_in),
            },
            {
                accessorKey: 'reference_no',
                header: 'Reference No',
                cell: ({ row }) =>
                    row.original.reference_no ??
                    row.original.control_no ??
                    '--',
            },
            {
                accessorKey: 'principal',
                header: 'Principal',
                cell: ({ row }) => formatCurrency(row.original.principal),
            },
            {
                accessorKey: 'payment_amount',
                header: 'Payment',
                cell: ({ row }) => formatCurrency(row.original.payment_amount),
            },
            {
                accessorKey: 'balance',
                header: 'Balance',
                cell: ({ row }) => formatCurrency(row.original.balance),
            },
        ],
        [],
    );

    return (
        <MemberRecordsCard
            title="Loan Payments"
            description="Payment ledger for this loan."
            isUpdating={isUpdating}
            error={error}
            errorTitle="Unable to load payments"
            onRetry={onRetry}
            showSkeleton={showSkeletonState}
            headerAccessory={
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Receipt className="h-4 w-4" />
                    <span>{meta.total} records</span>
                </div>
            }
            skeletonMobile={<MobilePaymentCardSkeletonList rows={4} />}
            skeletonDesktop={
                <TableSkeleton
                    columns={paymentTableSkeletonColumns}
                    rows={meta.perPage}
                    className="rounded-md border"
                    tableClassName="min-w-[840px]"
                />
            }
            mobileWrapperClassName="space-y-3"
            mobileContent={
                items.length === 0 ? (
                    <div className="rounded-md border border-border bg-muted/40 px-4 py-6 text-center text-sm text-muted-foreground">
                        {paymentsEmptyMessage}
                    </div>
                ) : (
                    items.map((payment, index) => (
                        <MobilePaymentCard
                            key={
                                payment.reference_no ??
                                payment.control_no ??
                                `payment-${index}`
                            }
                            payment={payment}
                        />
                    ))
                )
            }
            desktopContent={
                <div className="overflow-x-auto">
                    <DataTable
                        columns={columns}
                        data={items}
                        className="min-w-[840px]"
                        emptyMessage={paymentsEmptyMessage}
                    />
                </div>
            }
            footer={
                error ? null : showSkeletonState ? (
                    <DataTablePaginationSkeleton />
                ) : (
                    <DataTablePagination
                        page={meta.page}
                        perPage={meta.perPage}
                        total={meta.total}
                        onPageChange={onPageChange}
                    />
                )
            }
            showFooterWhenError={false}
        />
    );
}
