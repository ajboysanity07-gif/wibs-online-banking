import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import { MemberListCardSkeleton } from '@/components/member-list-card-skeleton';
import { PageShell } from '@/components/page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import {
    DataTablePagination,
    DataTablePaginationSkeleton,
} from '@/components/ui/data-table-pagination';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    TableSkeleton,
    type TableSkeletonColumn,
} from '@/components/ui/table-skeleton';
import AppLayout from '@/layouts/app-layout';
import {
    getRegistrationStatusLabel,
    getRegistrationStatusVariant,
} from '@/lib/member-status';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberSort,
    MemberRegistrationFilter,
    MemberSummary,
    MembersResponse,
} from '@/types/admin';

const formatDate = (value?: string | null): string => {
    if (!value) {
        return '--';
    }

    return new Date(value).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const buildColumns = (
    memberHref: (memberId: string) => string,
): ColumnDef<MemberSummary>[] => [
    {
        accessorKey: 'member_name',
        meta: { priority: 'title', text: (row) => row.member_name ?? '' },
        header: 'Member',
        cell: ({ row }) => (
            <div className="flex flex-col">
                <span className="font-bold">{row.original.member_name}</span>
                {row.original.username &&
                row.original.member_name !== row.original.username ? (
                    <span className="text-xs text-muted-foreground">
                        {row.original.username}
                    </span>
                ) : null}
            </div>
        ),
    },
    {
        accessorKey: 'acctno',
        meta: { priority: 'detail' },
        header: 'Account No',
        cell: ({ row }) => (
            <span className="font-semibold tabular-nums">
                {row.original.acctno ?? '--'}
            </span>
        ),
    },
    {
        accessorKey: 'email',
        meta: { priority: 'detail' },
        header: 'Email',
        cell: ({ row }) => row.original.email ?? '--',
    },
    {
        accessorKey: 'registration_status',
        meta: { priority: 'badge' },
        header: 'Registration',
        cell: ({ row }) => (
            <Badge
                variant={getRegistrationStatusVariant(
                    row.original.registration_status,
                )}
            >
                {getRegistrationStatusLabel(row.original.registration_status)}
            </Badge>
        ),
    },
    {
        accessorKey: 'created_at',
        meta: { priority: 'detail' },
        header: 'Created',
        cell: ({ row }) => (
            <span className="font-semibold tabular-nums">
                {formatDate(row.original.created_at)}
            </span>
        ),
    },
    {
        id: 'actions',
        meta: { priority: 'detail', label: 'Action' },
        header: 'Action',
        cell: ({ row }) => (
            <Button asChild variant="ghost" size="sm" className="text-primary">
                <Link href={memberHref(row.original.member_id)}>
                    Open profile
                </Link>
            </Button>
        ),
    },
];

const membersTableSkeletonColumns: TableSkeletonColumn[] = [
    { headerClassName: 'w-28', cellClassName: 'w-32' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-32', cellClassName: 'w-36' },
    { headerClassName: 'w-16', cellClassName: 'w-16' },
];

type MembersDirectoryData = {
    items: MemberSummary[];
    meta: MembersResponse['meta'];
    loading: boolean;
    error: string | null;
};

type MembersDirectoryPageProps = {
    breadcrumbs: BreadcrumbItem[];
    useMemberList: (params: {
        search: string;
        registration: MemberRegistrationFilter;
        sort: MemberSort;
        page: number;
        perPage: number;
    }) => MembersDirectoryData;
    memberHref: (memberId: string) => string;
};

export function MembersDirectoryPage({
    breadcrumbs,
    useMemberList,
    memberHref,
}: MembersDirectoryPageProps) {
    const columns = useMemo(() => buildColumns(memberHref), [memberHref]);
    const [search, setSearch] = useState('');
    const [registration, setRegistration] =
        useState<MemberRegistrationFilter>('all');
    const [sort, setSort] = useState<MemberSort>('newest');
    const [page, setPage] = useState(1);
    const [perPage] = useState(10);

    const { items, meta, loading, error } = useMemberList({
        search,
        registration,
        sort,
        page,
        perPage,
    });
    const showSkeleton = loading && items.length === 0;
    const hasSearch = search.trim() !== '';
    const filterCount = [registration !== 'all', sort !== 'newest'].filter(
        Boolean,
    ).length;

    const totalResults = meta.total;
    const pageStart = totalResults > 0 ? (meta.page - 1) * meta.perPage + 1 : 0;
    const pageEnd =
        totalResults > 0 ? Math.min(meta.page * meta.perPage, totalResults) : 0;
    const resultsLabel =
        totalResults > 0
            ? `Showing ${pageStart}-${pageEnd} of ${totalResults} members`
            : 'No members found.';

    const clearFilters = () => {
        setSearch('');
        setRegistration('all');
        setSort('newest');
        setPage(1);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Members" />
            <PageShell size="wide">
                <section className="flex flex-wrap items-start gap-5">
                    <div>
                        <p className="text-[11px] font-bold tracking-[0.14em] text-primary uppercase">
                            Members
                        </p>
                        <h1 className="mt-1 text-[22px] leading-tight font-bold sm:text-[26px]">
                            Member directory
                        </h1>
                        <p className="mt-1.5 max-w-prose text-sm text-muted-foreground">
                            Find members, confirm registration status, and open
                            profiles.
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <Badge variant="secondary">
                                {totalResults.toLocaleString()} members
                            </Badge>
                            {filterCount > 0 ? (
                                <Badge variant="outline">
                                    {filterCount} filter
                                    {filterCount === 1 ? '' : 's'}
                                </Badge>
                            ) : null}
                            {hasSearch ? (
                                <Badge variant="outline">Search active</Badge>
                            ) : null}
                        </div>
                    </div>
                </section>

                <Card
                    className="gap-0 overflow-hidden py-0"
                    aria-label="Search and filter members"
                >
                    <div className="flex flex-wrap items-end gap-3 px-5 py-4">
                        <div className="grid min-w-[260px] flex-1 gap-1">
                            <Label
                                htmlFor="member-search"
                                className="text-[11px] font-bold tracking-[0.08em] text-muted-foreground uppercase"
                            >
                                Search members
                            </Label>
                            <Input
                                id="member-search"
                                type="search"
                                value={search}
                                onChange={(event) => {
                                    setSearch(event.target.value);
                                    setPage(1);
                                }}
                                placeholder="Search by account no, name, username, or email"
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label
                                htmlFor="member-registration"
                                className="text-[11px] font-bold tracking-[0.08em] text-muted-foreground uppercase"
                            >
                                Registration
                            </Label>
                            <Select
                                value={registration}
                                onValueChange={(value) => {
                                    setRegistration(
                                        value as MemberRegistrationFilter,
                                    );
                                    setPage(1);
                                }}
                            >
                                <SelectTrigger
                                    id="member-registration"
                                    className="w-full sm:w-40"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All</SelectItem>
                                    <SelectItem value="registered">
                                        Registered
                                    </SelectItem>
                                    <SelectItem value="unregistered">
                                        Unregistered
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1">
                            <Label
                                htmlFor="member-sort"
                                className="text-[11px] font-bold tracking-[0.08em] text-muted-foreground uppercase"
                            >
                                Sort
                            </Label>
                            <Select
                                value={sort}
                                onValueChange={(value) => {
                                    setSort(value as MemberSort);
                                    setPage(1);
                                }}
                            >
                                <SelectTrigger
                                    id="member-sort"
                                    className="w-full sm:w-40"
                                >
                                    <SelectValue />
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
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={clearFilters}
                        >
                            Clear filters
                        </Button>
                    </div>
                    <p
                        className="px-5 pb-4 text-[13px] text-muted-foreground"
                        role="status"
                    >
                        {resultsLabel}
                    </p>
                </Card>

                {error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Unable to load members</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                <Card className="gap-0 overflow-hidden py-0">
                    <div className="flex flex-wrap items-baseline gap-2 px-5 pt-[18px] pb-4">
                        <h2 className="text-base font-bold">Results</h2>
                        <span className="text-[13px] text-muted-foreground">
                            {totalResults.toLocaleString()}{' '}
                            {totalResults === 1 ? 'member' : 'members'}
                        </span>
                        {loading ? (
                            <span className="ml-auto text-xs text-muted-foreground">
                                Updating...
                            </span>
                        ) : null}
                    </div>
                    {showSkeleton ? (
                        <>
                            <div
                                className="space-y-3 px-4 pb-3 md:hidden"
                                aria-busy="true"
                            >
                                {Array.from({ length: 4 }).map((_, index) => (
                                    <MemberListCardSkeleton
                                        key={`member-skeleton-${index}`}
                                    />
                                ))}
                            </div>
                            <div className="hidden md:block" aria-busy="true">
                                <TableSkeleton
                                    columns={membersTableSkeletonColumns}
                                    rows={perPage}
                                    tableClassName="bg-transparent"
                                />
                            </div>
                        </>
                    ) : (
                        <DataTable
                            columns={columns}
                            data={items}
                            emptyMessage="No members found."
                            className="rounded-none border-0 border-t border-border bg-transparent"
                        />
                    )}
                </Card>

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
            </PageShell>
        </AppLayout>
    );
}
