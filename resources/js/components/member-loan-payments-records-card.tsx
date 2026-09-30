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
import type { MemberLoanPayment, PaginationMeta } from '@/types/admin';

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
    <div className="rounded-xl border border-border bg-card p-4">
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
        <div className="mt-3 space-y-2 rounded-xl border border-border bg-muted/30 p-3">
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
                meta: {
                    priority: 'title',
                    text: (row) => formatDate(row.date_in),
                },
                header: 'Transaction Date',
                cell: ({ row }) => formatDate(row.original.date_in),
            },
            {
                accessorKey: 'reference_no',
                meta: { priority: 'detail' },
                header: 'Reference No',
                cell: ({ row }) =>
                    row.original.reference_no ??
                    row.original.control_no ??
                    '--',
            },
            {
                accessorKey: 'principal',
                meta: { priority: 'detail' },
                header: 'Principal',
                cell: ({ row }) => formatCurrency(row.original.principal),
            },
            {
                accessorKey: 'payment_amount',
                meta: { priority: 'amount' },
                header: 'Payment',
                cell: ({ row }) => formatCurrency(row.original.payment_amount),
            },
            {
                accessorKey: 'balance',
                meta: { priority: 'detail' },
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
                    className="rounded-xl border border-border bg-card"
                    tableClassName="min-w-[840px]"
                />
            }
            mobileWrapperClassName="space-y-3"
            body={
                <div className="md:overflow-x-auto">
                    <DataTable
                        columns={columns}
                        data={items}
                        className="md:min-w-[840px]"
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
