import type { ColumnDef } from '@tanstack/react-table';
import { useMemo } from 'react';
import {
    MemberMobileCard,
    MemberMobileCardSkeleton,
} from '@/components/member-mobile-card';
import { MemberRecordsCard } from '@/components/member-records-card';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/ui/data-table';
import {
    DataTablePagination,
    DataTablePaginationSkeleton,
} from '@/components/ui/data-table-pagination';
import { TableSkeleton } from '@/components/ui/table-skeleton';
import { formatCurrency, formatDate } from '@/lib/formatters';
import { getSavingsMovementMeta } from '@/lib/savings-ledger';
import type {
    MemberSavingsLedgerEntry,
    PaginationMeta,
} from '@/types/admin';

type MemberSavingsLedgerCardProps = {
    items: MemberSavingsLedgerEntry[];
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

const MobileSavingsCard = ({
    savings,
}: {
    savings: MemberSavingsLedgerEntry;
}) => {
    const movementMeta = getSavingsMovementMeta(savings);

    return (
        <MemberMobileCard
            title={
                <span className="inline-flex flex-wrap items-center gap-2">
                    <Badge variant={movementMeta.variant}>
                        {movementMeta.label}
                    </Badge>
                </span>
            }
            subtitle={renderSavingsType(savings.svtype)}
            valueLabel="Balance"
            value={formatCurrency(savings.balance)}
            meta={[
                {
                    label: 'Transaction date',
                    value: formatDate(savings.date_in),
                },
                { label: 'Deposit', value: formatCurrency(savings.deposit) },
                {
                    label: 'Withdrawal',
                    value: formatCurrency(savings.withdrawal),
                },
            ]}
        />
    );
};

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
            ? 'Loading savings...'
            : 'No savings transactions found.');
    const showSkeletonState =
        showSkeleton ?? (isUpdating && items.length === 0);

    const columns = useMemo<ColumnDef<MemberSavingsLedgerEntry>[]>(
        () => [
            {
                accessorKey: 'date_in',
                header: 'Transaction Date',
                cell: ({ row }) => formatDate(row.original.date_in),
            },
            {
                id: 'movement',
                header: 'Movement',
                cell: ({ row }) => {
                    const movementMeta = getSavingsMovementMeta(
                        row.original,
                    );

                    return (
                        <Badge variant={movementMeta.variant}>
                            {movementMeta.label}
                        </Badge>
                    );
                },
            },
            {
                accessorKey: 'svtype',
                header: 'Type',
                cell: ({ row }) => renderSavingsType(row.original.svtype),
            },
            {
                accessorKey: 'deposit',
                header: 'Deposit',
                cell: ({ row }) => formatCurrency(row.original.deposit),
            },
            {
                accessorKey: 'withdrawal',
                header: 'Withdrawal',
                cell: ({ row }) => formatCurrency(row.original.withdrawal),
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
            title="Savings"
            description="Savings ledger activity with pagination."
            isUpdating={isUpdating}
            error={error}
            errorTitle="Unable to load savings"
            onRetry={onRetry}
            showSkeleton={showSkeletonState}
            skeletonMobile={<MobileSavingsCardSkeletonList rows={4} />}
            skeletonDesktop={
                <TableSkeleton
                    columns={savingsTableSkeletonColumns}
                    rows={meta.perPage}
                    className="rounded-md border"
                    tableClassName="min-w-[840px]"
                />
            }
            mobileWrapperClassName="space-y-3"
            mobileContent={
                items.length === 0 ? (
                    <div className="rounded-md border border-border bg-muted/40 px-4 py-6 text-center text-sm text-muted-foreground">
                        {savingsEmptyMessage}
                    </div>
                ) : (
                    items.map((savingsRow, index) => (
                        <MobileSavingsCard
                            key={`${savingsRow.svnumber ?? 'savings'}-${savingsRow.date_in ?? index}`}
                            savings={savingsRow}
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
