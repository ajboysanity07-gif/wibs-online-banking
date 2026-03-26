import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { MemberListCardSkeleton } from '@/components/member-list-card-skeleton';
import { PageHero } from '@/components/page-hero';
import { PageShell } from '@/components/page-shell';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
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
import {
    TableSkeleton,
    type TableSkeletonColumn,
} from '@/components/ui/table-skeleton';
import { useMembers } from '@/hooks/admin/use-members';
import AppLayout from '@/layouts/app-layout';
import { show as showMember } from '@/routes/admin/members';
import { index as membersIndex } from '@/routes/admin/watchlist';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberSort,
    MemberStatusFilter,
    MemberStatusValue,
    MemberSummary,
} from '@/types/admin';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Members',
        href: membersIndex().url,
    },
];

const formatDate = (value?: string | null): string => {
    if (!value) {
        return '--';
    }

    return new Date(value).toLocaleDateString();
};

const statusVariant = (status?: MemberStatusValue | null) => {
    if (status === 'active') {
        return 'default';
    }

    if (status === 'pending') {
        return 'secondary';
    }

    if (status === 'suspended') {
        return 'destructive';
    }

    return 'outline';
};

const statusLabel = (status?: MemberStatusValue | null) => {
    if (status === 'active') {
        return 'Active';
    }

    if (status === 'pending') {
        return 'Pending';
    }

    if (status === 'suspended') {
        return 'Suspended';
    }

    return 'Unknown';
};

const columns: ColumnDef<MemberSummary>[] = [
    {
        accessorKey: 'member_name',
        header: 'Member',
        cell: ({ row }) => (
            <div className="flex flex-col">
                <span className="font-medium">{row.original.member_name}</span>
                {row.original.member_name !== row.original.username ? (
                    <span className="text-xs text-muted-foreground">
                        {row.original.username}
                    </span>
                ) : null}
            </div>
        ),
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
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => (
            <Badge variant={statusVariant(row.original.status)}>
                {statusLabel(row.original.status)}
            </Badge>
        ),
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
        cell: ({ row }) => formatDate(row.original.created_at),
    },
    {
        id: 'actions',
        header: '',
        cell: ({ row }) => (
            <div className="text-right">
                <Button asChild type="button" size="sm" variant="outline">
                    <Link href={showMember(row.original.user_id).url}>
                        Open profile
                    </Link>
                </Button>
            </div>
        ),
    },
];

const membersTableSkeletonColumns: TableSkeletonColumn[] = [
    { headerClassName: 'w-28', cellClassName: 'w-32' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-32', cellClassName: 'w-36' },
    { headerClassName: 'w-16', cellClassName: 'w-16' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-12', cellClassName: 'h-8 w-24', align: 'right' },
];

const MobileMemberCard = ({ member }: { member: MemberSummary }) => (
    <SurfaceCard variant="default" padding="sm">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-1">
                <p className="text-sm font-semibold">{member.member_name}</p>
                {member.member_name !== member.username ? (
                    <p className="text-xs text-muted-foreground">
                        {member.username}
                    </p>
                ) : null}
            </div>
            <Badge variant={statusVariant(member.status)}>
                {statusLabel(member.status)}
            </Badge>
        </div>
        <div className="mt-3 space-y-2 rounded-md border border-border/60 bg-muted/40 p-3 text-xs">
            <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Account No</span>
                <span className="text-sm font-medium">
                    {member.acctno ?? '--'}
                </span>
            </div>
            <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Email</span>
                <span className="text-sm font-medium">{member.email}</span>
            </div>
            <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Created</span>
                <span className="text-sm font-medium">
                    {formatDate(member.created_at)}
                </span>
            </div>
        </div>
        <div className="mt-3">
            <Button
                asChild
                type="button"
                size="sm"
                variant="outline"
                className="w-full sm:w-auto"
            >
                <Link href={showMember(member.user_id).url}>Open profile</Link>
            </Button>
        </div>
    </SurfaceCard>
);

export default function MembersPage() {
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState<MemberStatusFilter>('all');
    const [sort, setSort] = useState<MemberSort>('newest');
    const [page, setPage] = useState(1);
    const [perPage] = useState(10);

    const { items, meta, loading, error } = useMembers({
        search,
        status,
        sort,
        page,
        perPage,
    });
    const showSkeleton = loading && items.length === 0;
    const searchValue = search.trim();
    const hasSearch = searchValue !== '';
    const hasStatus = status !== 'all';
    const hasSort = sort !== 'newest';
    const filterCount = [hasSearch, hasStatus, hasSort].filter(Boolean).length;
    const hasFilters = filterCount > 0;
    const totalResults = meta.total;
    const pageStart =
        totalResults > 0 ? (meta.page - 1) * meta.perPage + 1 : 0;
    const pageEnd =
        totalResults > 0
            ? Math.min(meta.page * meta.perPage, totalResults)
            : 0;
    const resultsLabel =
        totalResults > 0
            ? `Showing ${pageStart}-${pageEnd} of ${totalResults} members`
            : 'No members found.';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Members" />
            <PageShell size="wide">
                <PageHero
                    kicker="Members"
                    title="Member directory"
                    description="Find members, review account status, and open profiles."
                    badges={
                        <>
                            <Badge variant="secondary">
                                {totalResults} members
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
                        </>
                    }
                    rightSlot={
                        loading ? (
                            <Badge variant="outline">Updating</Badge>
                        ) : null
                    }
                />

                <SurfaceCard variant="default" padding="md">
                    <div className="flex flex-col gap-4">
                        <SectionHeader
                            title="Filters"
                            description="Search and segment members by status."
                            actions={
                                <>
                                    {filterCount > 0 ? (
                                        <span className="text-xs text-muted-foreground">
                                            {filterCount} filter
                                            {filterCount === 1 ? '' : 's'}{' '}
                                            active
                                        </span>
                                    ) : null}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        disabled={!hasFilters}
                                        onClick={() => {
                                            setSearch('');
                                            setStatus('all');
                                            setSort('newest');
                                            setPage(1);
                                        }}
                                    >
                                        Clear filters
                                    </Button>
                                </>
                            }
                        />
                        <div className="grid gap-3 md:grid-cols-[minmax(0,1.6fr)_minmax(0,0.8fr)_minmax(0,0.8fr)]">
                            <div className="space-y-1">
                                <label
                                    className="text-xs font-medium text-muted-foreground"
                                    htmlFor="members-search"
                                >
                                    Search
                                </label>
                                <Input
                                    id="members-search"
                                    value={search}
                                    placeholder="Search by account no, username, or email"
                                    onChange={(event) => {
                                        setSearch(event.target.value);
                                        setPage(1);
                                    }}
                                />
                            </div>
                            <div className="space-y-1">
                                <span className="text-xs font-medium text-muted-foreground">
                                    Status
                                </span>
                                <Select
                                    value={status}
                                    onValueChange={(value) => {
                                        setStatus(value as MemberStatusFilter);
                                        setPage(1);
                                    }}
                                >
                                    <SelectTrigger aria-label="Status">
                                        <SelectValue placeholder="Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All</SelectItem>
                                        <SelectItem value="pending">
                                            Pending
                                        </SelectItem>
                                        <SelectItem value="active">
                                            Active
                                        </SelectItem>
                                        <SelectItem value="suspended">
                                            Suspended
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1">
                                <span className="text-xs font-medium text-muted-foreground">
                                    Sort
                                </span>
                                <Select
                                    value={sort}
                                    onValueChange={(value) => {
                                        setSort(value as MemberSort);
                                        setPage(1);
                                    }}
                                >
                                    <SelectTrigger aria-label="Sort members">
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
                            </div>
                        </div>
                    </div>
                </SurfaceCard>

                {error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Unable to load members</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                <SurfaceCard
                    variant="default"
                    padding="none"
                    className="overflow-hidden"
                >
                    <div className="border-b border-border/40 bg-card/70 px-6 py-4">
                        <SectionHeader
                            title="Results"
                            description={resultsLabel}
                            titleClassName="text-lg"
                            actions={
                                loading ? (
                                    <span className="text-xs text-muted-foreground">
                                        Updating...
                                    </span>
                                ) : null
                            }
                        />
                    </div>
                    <div className="px-2 pb-2 sm:px-4 sm:pb-4">
                        {showSkeleton ? (
                            <>
                                <div
                                    className="space-y-3 px-2 pb-3 pt-4 md:hidden"
                                    aria-busy="true"
                                >
                                    {Array.from({ length: 4 }).map(
                                        (_, index) => (
                                            <MemberListCardSkeleton
                                                key={`member-skeleton-${index}`}
                                            />
                                        ),
                                    )}
                                </div>
                                <div
                                    className="hidden md:block"
                                    aria-busy="true"
                                >
                                    <TableSkeleton
                                        columns={membersTableSkeletonColumns}
                                        rows={perPage}
                                        className="pt-4"
                                        tableClassName="bg-transparent"
                                    />
                                </div>
                            </>
                        ) : (
                            <>
                                <div className="space-y-3 px-2 pb-3 pt-4 md:hidden">
                                    {items.length === 0 ? (
                                        <div className="rounded-md border border-border/60 bg-muted/40 px-4 py-6 text-center text-sm text-muted-foreground">
                                            No members found.
                                        </div>
                                    ) : (
                                        items.map((member) => (
                                            <MobileMemberCard
                                                key={member.user_id}
                                                member={member}
                                            />
                                        ))
                                    )}
                                </div>
                                <div className="hidden md:block">
                                    <DataTable
                                        columns={columns}
                                        data={items}
                                        emptyMessage="No members found."
                                        className="border-0 bg-transparent"
                                    />
                                </div>
                            </>
                        )}
                    </div>
                </SurfaceCard>

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
