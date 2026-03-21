import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { DataTable } from '@/components/ui/data-table';
import {
    DataTablePagination,
    DataTablePaginationSkeleton,
} from '@/components/ui/data-table-pagination';
import { Input } from '@/components/ui/input';
import { TableSkeleton } from '@/components/ui/table-skeleton';
import { useRequests } from '@/hooks/admin/use-requests';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/formatters';
import { index as requestsIndex } from '@/routes/admin/requests';
import type { BreadcrumbItem } from '@/types';
import type { RequestPreview } from '@/types/admin';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Requests',
        href: requestsIndex().url,
    },
];

const formatDate = (value?: string | null): string => {
    if (!value) {
        return '--';
    }

    return new Date(value).toLocaleDateString();
};

const columns: ColumnDef<RequestPreview>[] = [
    {
        accessorKey: 'member_name',
        header: 'Member',
        cell: ({ row }) => row.original.member_name ?? '--',
    },
    {
        accessorKey: 'loan_type',
        header: 'Loan type',
        cell: ({ row }) => row.original.loan_type ?? '--',
    },
    {
        accessorKey: 'requested_amount',
        header: 'Amount',
        cell: ({ row }) =>
            row.original.requested_amount !== null &&
            row.original.requested_amount !== undefined
                ? formatCurrency(Number(row.original.requested_amount))
                : '--',
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => row.original.status ?? '--',
    },
    {
        accessorKey: 'submitted_at',
        header: 'Submitted',
        cell: ({ row }) =>
            formatDate(row.original.submitted_at ?? row.original.created_at),
    },
];

const requestsTableSkeletonColumns = [
    { headerClassName: 'w-28', cellClassName: 'w-32' },
    { headerClassName: 'w-28', cellClassName: 'w-32' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-16', cellClassName: 'w-20' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
];

export default function RequestsPage() {
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [perPage] = useState(10);
    const { items, meta, loading, error } = useRequests({
        search,
        page,
        perPage,
    });
    const showSkeleton = loading && items.length === 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Requests" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <Input
                        value={search}
                        placeholder="Search requests"
                        className="sm:w-72"
                        onChange={(event) => {
                            setSearch(event.target.value);
                            setPage(1);
                        }}
                    />
                    {loading ? (
                        <span className="text-xs text-muted-foreground">
                            Updating...
                        </span>
                    ) : null}
                </div>

                {error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Unable to load requests</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                {showSkeleton ? (
                    <TableSkeleton
                        columns={requestsTableSkeletonColumns}
                        rows={perPage}
                        className="rounded-md border"
                    />
                ) : (
                    <DataTable
                        columns={columns}
                        data={items}
                        emptyMessage={
                            meta.available
                                ? 'No requests found.'
                                : meta.message ??
                                  'Requests module coming soon.'
                        }
                    />
                )}
                {showSkeleton ? (
                    <DataTablePaginationSkeleton />
                ) : (
                    <DataTablePagination
                        page={meta.page}
                        perPage={meta.perPage}
                        total={meta.total}
                        onPageChange={(nextPage) => setPage(nextPage)}
                    />
                )}
            </div>
        </AppLayout>
    );
}
