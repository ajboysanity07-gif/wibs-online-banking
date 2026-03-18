import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { MemberListCardSkeleton } from '@/components/member-list-card-skeleton';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import {
    DataTablePagination,
    DataTablePaginationSkeleton,
} from '@/components/ui/data-table-pagination';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { TableSkeleton, type TableSkeletonColumn } from '@/components/ui/table-skeleton';
import { usePendingApprovals } from '@/hooks/admin/use-pending-approvals';
import { useUpdateMemberStatus } from '@/hooks/admin/use-update-member-status';
import AppLayout from '@/layouts/app-layout';
import { pending } from '@/routes/admin/users';
import type { BreadcrumbItem } from '@/types';
import type { PendingApprovalRow } from '@/types/admin';

type PendingSort = 'newest' | 'oldest';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pending approvals',
        href: pending().url,
    },
];

const statusVariant = (status?: string | null) => {
    if (status === 'active') {
        return 'default';
    }

    if (status === 'pending') {
        return 'secondary';
    }

    return 'outline';
};

const statusLabel = (status?: string | null) => {
    if (status === 'active') {
        return 'Active';
    }

    if (status === 'pending') {
        return 'Pending';
    }

    return 'Unknown';
};

const formatDate = (value?: string | null): string => {
    if (!value) {
        return '--';
    }

    return new Date(value).toLocaleDateString();
};

const columns = (
    processingIds: Record<number, boolean>,
    onApprove: (userId: number) => void,
): ColumnDef<PendingApprovalRow>[] => [
    {
        accessorKey: 'member_name',
        header: 'Member',
        cell: ({ row }) => {
            const member = row.original;

            return (
                <div className="flex flex-col">
                    <span className="font-medium">{member.member_name}</span>
                    {member.member_name !== member.username ? (
                        <span className="text-xs text-muted-foreground">
                            {member.username}
                        </span>
                    ) : null}
                </div>
            );
        },
    },
    {
        accessorKey: 'acctno',
        header: 'Account No',
        cell: ({ row }) => row.original.acctno ?? '--',
    },
    {
        accessorKey: 'email',
        header: 'Email',
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
        cell: ({ row }) => formatDate(row.original.created_at),
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => (
            <Badge variant={statusVariant(row.original.status)}>
                {statusLabel(row.original.status)}
            </Badge>
        ),
    },
    {
        id: 'actions',
        header: '',
        cell: ({ row }) => (
            <div className="text-right">
                <Button
                    type="button"
                    size="sm"
                    disabled={
                        processingIds[row.original.user_id] ||
                        row.original.status !== 'pending'
                    }
                    onClick={() => onApprove(row.original.user_id)}
                >
                    Approve
                </Button>
            </div>
        ),
    },
];

const pendingTableSkeletonColumns: TableSkeletonColumn[] = [
    { headerClassName: 'w-28', cellClassName: 'w-32' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-32', cellClassName: 'w-36' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-16', cellClassName: 'w-16' },
    { headerClassName: 'w-12', cellClassName: 'h-8 w-20', align: 'right' },
];

const MobilePendingApprovalCard = ({
    row,
    isProcessing,
    onApprove,
}: {
    row: PendingApprovalRow;
    isProcessing: boolean;
    onApprove: (userId: number) => void;
}) => (
    <div className="rounded-lg border border-border bg-card p-4">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-1">
                <p className="text-sm font-semibold">{row.member_name}</p>
                {row.member_name !== row.username ? (
                    <p className="text-xs text-muted-foreground">
                        {row.username}
                    </p>
                ) : null}
            </div>
            <Badge variant={statusVariant(row.status)}>
                {statusLabel(row.status)}
            </Badge>
        </div>
        <div className="mt-3 space-y-2 rounded-md border border-border/60 bg-muted/40 p-3 text-xs">
            <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Account No</span>
                <span className="text-sm font-medium">
                    {row.acctno ?? '--'}
                </span>
            </div>
            <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Email</span>
                <span className="text-sm font-medium">{row.email}</span>
            </div>
            <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Created</span>
                <span className="text-sm font-medium">
                    {formatDate(row.created_at)}
                </span>
            </div>
        </div>
        <div className="mt-3">
            <Button
                type="button"
                size="sm"
                className="w-full sm:w-auto"
                disabled={isProcessing || row.status !== 'pending'}
                onClick={() => onApprove(row.user_id)}
            >
                Approve
            </Button>
        </div>
    </div>
);

export default function PendingUsers() {
    const [search, setSearch] = useState('');
    const [sort, setSort] = useState<PendingSort>('newest');
    const [page, setPage] = useState(1);
    const [perPage] = useState(10);
    const [refreshKey, setRefreshKey] = useState(0);

    const { rows, meta, loading, error } = usePendingApprovals({
        search,
        sort,
        page,
        perPage,
        refreshKey,
    });

    const { updateStatus, processingIds } = useUpdateMemberStatus({
        onUpdated: () => {
            setRefreshKey((current) => current + 1);
        },
    });

    const tableColumns = columns(processingIds, (userId) =>
        updateStatus(userId, 'approve'),
    );
    const showSkeleton = loading && rows.length === 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pending approvals" />
            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <Input
                        value={search}
                        placeholder="Search by account no, username, or email"
                        className="sm:w-72"
                        onChange={(event) => {
                            setSearch(event.target.value);
                            setPage(1);
                        }}
                    />
                    <div className="flex items-center gap-2">
                        <Select
                            value={sort}
                            onValueChange={(value) => {
                                setSort(value as PendingSort);
                                setPage(1);
                            }}
                        >
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="Sort" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="newest">
                                    Newest first
                                </SelectItem>
                                <SelectItem value="oldest">
                                    Oldest first
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        {loading ? (
                            <span className="text-xs text-muted-foreground">
                                Updating...
                            </span>
                        ) : null}
                    </div>
                </div>

                {error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Unable to load approvals</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                {showSkeleton ? (
                    <>
                        <div className="space-y-3 md:hidden" aria-busy="true">
                            {Array.from({ length: 4 }).map((_, index) => (
                                <MemberListCardSkeleton
                                    key={`pending-skeleton-${index}`}
                                />
                            ))}
                        </div>
                        <div className="hidden md:block" aria-busy="true">
                            <TableSkeleton
                                columns={pendingTableSkeletonColumns}
                                rows={perPage}
                                className="rounded-md border"
                            />
                        </div>
                    </>
                ) : (
                    <>
                        <div className="space-y-3 md:hidden">
                            {rows.length === 0 ? (
                                <div className="rounded-md border border-border bg-muted/40 px-4 py-6 text-center text-sm text-muted-foreground">
                                    No pending approvals.
                                </div>
                            ) : (
                                rows.map((row) => (
                                    <MobilePendingApprovalCard
                                        key={row.user_id}
                                        row={row}
                                        isProcessing={
                                            processingIds[row.user_id] ??
                                            false
                                        }
                                        onApprove={(userId) =>
                                            updateStatus(userId, 'approve')
                                        }
                                    />
                                ))
                            )}
                        </div>
                        <div className="hidden md:block">
                            <DataTable
                                columns={tableColumns}
                                data={rows}
                                emptyMessage="No pending approvals."
                            />
                        </div>
                    </>
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
