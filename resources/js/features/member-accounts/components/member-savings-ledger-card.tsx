import type { ColumnDef } from '@tanstack/react-table';
import { useMemo } from 'react';
import { MemberMobileCardSkeleton } from '@/components/member-mobile-card';
import { MemberRecordsCard } from '@/components/member-records-card';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/ui/data-table';
import {
    DataTablePagination,
    DataTablePaginationSkeleton,
} from '@/components/ui/data-table-pagination';
import { TableSkeleton } from '@/components/ui/table-skeleton';
import type { MemberLoanSecurityLedgerEntry } from '@/features/member-accounts/types';
import { formatCurrency, formatDate } from '@/lib/formatters';
import { getSavingsMovementMeta } from '@/lib/savings-ledger';
import type { PaginationMeta } from '@/types/pagination';

type MemberSavingsLedgerCardProps = {
    items: MemberLoanSecurityLedgerEntry[];
    meta: PaginationMeta;
    isUpdating?: boolean;
    error?: string | null;
    onRetry?: () => void;
    onPageChange: (page: number) => void;
    emptyMessage?: string;
    showSkeleton?: boolean;
};

const savingsTableSkeletonColumns = [
    { headerClassName: 'w-24', cellClassName: 'w-32' },
    { headerClassName: 'w-16', cellClassName: 'w-20' },
    { headerClassName: 'w-16', cellClassName: 'w-20' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
];

const renderSavingsType = (value?: string | null) => {
    if (!value) {
        return '--';
    }

    return <Badge variant="outline">{value}</Badge>;
};

const MobileSavingsCardSkeletonList = ({ rows = 4 }: { rows?: number }) => (
    <div className="space-y-3">
        {Array.from({ length: rows }).map((_, index) => (
            <MemberMobileCardSkeleton
                key={`savings-card-skeleton-${index}`}
                valueLabelClassName="w-24"
            />
        ))}
    </div>
);

export function MemberSavingsLedgerCard({
    items,
    meta,
    isUpdating = false,
    error = null,
    onRetry,
    onPageChange,
    emptyMessage,
    showSkeleton,
}: MemberSavingsLedgerCardProps) {
    const savingsEmptyMessage =
        emptyMessage ??
        (isUpdating
            ? 'Loading loan security...'
            : 'No loan security transactions found.');
    const showSkeletonState =
        showSkeleton ?? (isUpdating && items.length === 0);

    const columns = useMemo<ColumnDef<MemberLoanSecurityLedgerEntry>[]>(
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
                id: 'movement',
                meta: { priority: 'badge' },
                header: 'Movement',
                cell: ({ row }) => {
                    const movementMeta = getSavingsMovementMeta(row.original);

                    return (
                        <Badge variant={movementMeta.variant}>
                            {movementMeta.label}
                        </Badge>
                    );
                },
            },
            {
                accessorKey: 'svtype',
                meta: { priority: 'detail' },
                header: 'Type',
                cell: ({ row }) => renderSavingsType(row.original.svtype),
            },
            {
                accessorKey: 'deposit',
                meta: { priority: 'detail' },
                header: 'Deposit',
                cell: ({ row }) => formatCurrency(row.original.deposit),
            },
            {
                accessorKey: 'withdrawal',
                meta: { priority: 'detail' },
                header: 'Withdrawal',
                cell: ({ row }) => formatCurrency(row.original.withdrawal),
            },
            {
                accessorKey: 'balance',
                meta: { priority: 'amount' },
                header: 'Balance',
                cell: ({ row }) => formatCurrency(row.original.balance),
            },
        ],
        [],
    );

    return (
        <MemberRecordsCard
            title="Loan Security"
            description="Loan security ledger activity with pagination."
            isUpdating={isUpdating}
            error={error}
            errorTitle="Unable to load loan security"
            onRetry={onRetry}
            showSkeleton={showSkeletonState}
            skeletonMobile={<MobileSavingsCardSkeletonList rows={4} />}
            skeletonDesktop={
                <TableSkeleton
                    columns={savingsTableSkeletonColumns}
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
                        emptyMessage={savingsEmptyMessage}
                    />
                </div>
            }
            footer={
                showSkeletonState ? (
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
        />
    );
}
