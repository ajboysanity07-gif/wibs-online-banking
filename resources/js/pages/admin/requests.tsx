import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import {
    LoanRequestPageHero,
    LoanRequestSearchBox,
    LoanRequestStatusFilters,
    LoanRequestSummaryCards,
    type LoanRequestStatusFilterOption,
} from '@/components/loan-request/loan-request-page-sections';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { PageShell } from '@/components/page-shell';
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
import { Skeleton } from '@/components/ui/skeleton';
import { TableSkeleton } from '@/components/ui/table-skeleton';
import { useRequests } from '@/hooks/admin/use-requests';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/formatters';
import { index as requestsIndex, show as requestsShow } from '@/routes/admin/requests';
import type { BreadcrumbItem } from '@/types';
import type { RequestPreview } from '@/types/admin';
import type { LoanRequestStatusValue } from '@/types/loan-requests';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Requests',
        href: requestsIndex().url,
    },
];

type AdminStatusFilter =
    | 'all'
    | 'under_review'
    | 'approved'
    | 'declined'
    | 'cancelled';

const statusLabels: Record<Exclude<AdminStatusFilter, 'all'>, string> = {
    under_review: 'Under review',
    approved: 'Approved',
    declined: 'Declined',
    cancelled: 'Cancelled',
};

const statusOptions: Array<LoanRequestStatusFilterOption<AdminStatusFilter>> = [
    { value: 'all', label: 'All' },
    { value: 'under_review', label: 'Under review' },
    { value: 'approved', label: 'Approved' },
    { value: 'declined', label: 'Declined' },
    { value: 'cancelled', label: 'Cancelled' },
];

const formatDate = (value?: string | null): string => {
    if (!value) {
        return '--';
    }

    return new Date(value).toLocaleDateString();
};

const parseAmount = (value: string): number | undefined => {
    const trimmed = value.trim();

    if (trimmed === '') {
        return undefined;
    }

    const parsed = Number(trimmed);

    return Number.isFinite(parsed) ? parsed : undefined;
};

const formatCountLabel = (count: number, label: string): string => {
    return count === 1 ? `${count} ${label}` : `${count} ${label}s`;
};

const normalizeStatus = (
    status: LoanRequestStatusValue | null,
): LoanRequestStatusValue | null => {
    if (status === 'submitted') {
        return 'under_review';
    }

    return status;
};

const columns: ColumnDef<RequestPreview>[] = [
    {
        accessorKey: 'reference',
        header: 'Reference',
        cell: ({ row }) => row.original.reference ?? '--',
    },
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
        cell: ({ row }) => (
            <LoanRequestStatusBadge status={row.original.status} />
        ),
    },
    {
        accessorKey: 'submitted_at',
        header: 'Submitted',
        cell: ({ row }) =>
            formatDate(row.original.submitted_at ?? row.original.created_at),
    },
    {
        id: 'action',
        header: () => <div className="flex justify-end">Action</div>,
        cell: ({ row }) => {
            const requestId = row.original.id;

            if (!requestId) {
                return <div className="flex justify-end">--</div>;
            }

            return (
                <div className="flex justify-end">
                    <Button asChild size="sm" variant="outline">
                        <Link href={requestsShow(requestId).url}>
                            View request
                        </Link>
                    </Button>
                </div>
            );
        },
    },
];

const requestsTableSkeletonColumns = [
    { headerClassName: 'w-24', cellClassName: 'w-28' },
    { headerClassName: 'w-28', cellClassName: 'w-32' },
    { headerClassName: 'w-28', cellClassName: 'w-32' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-16', cellClassName: 'w-20' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-24', cellClassName: 'w-24', align: 'right' },
];

export default function RequestsPage() {
    const [search, setSearch] = useState('');
    const [loanType, setLoanType] = useState<string | null>(null);
    const [statusFilter, setStatusFilter] =
        useState<AdminStatusFilter>('all');
    const [minAmount, setMinAmount] = useState('');
    const [maxAmount, setMaxAmount] = useState('');
    const [page, setPage] = useState(1);
    const [perPage] = useState(10);

    const searchValue = search.trim();
    const minAmountValue = parseAmount(minAmount);
    const maxAmountValue = parseAmount(maxAmount);
    const status = statusFilter === 'all' ? null : statusFilter;
    const { items, meta, loading, error } = useRequests({
        search,
        page,
        perPage,
        loanType,
        status,
        minAmount: minAmountValue,
        maxAmount: maxAmountValue,
    });

    const showSkeleton = loading && items.length === 0;
    const loanTypeOptions = (
        meta.loanTypes.length > 0
            ? meta.loanTypes
            : Array.from(
                  new Set(
                      items
                          .map((item) => item.loan_type)
                          .filter((value): value is string => Boolean(value)),
                  ),
              )
    ).sort((left, right) => left.localeCompare(right));
    const totalResults = meta.total;
    const pageStart =
        totalResults > 0 ? (meta.page - 1) * meta.perPage + 1 : 0;
    const pageEnd =
        totalResults > 0
            ? Math.min(meta.page * meta.perPage, totalResults)
            : 0;
    const resultsLabel = meta.available
        ? totalResults > 0
            ? `Showing ${pageStart}-${pageEnd} of ${formatCountLabel(
                  totalResults,
                  'request',
              )}`
            : 'No requests found yet.'
        : (meta.message ?? 'Requests module coming soon.');
    const emptyMessage = meta.available
        ? searchValue !== '' ||
          status !== null ||
          loanType !== null ||
          minAmountValue !== undefined ||
          maxAmountValue !== undefined
            ? 'No requests match the current filters.'
            : 'No requests found yet.'
        : (meta.message ?? 'Requests module coming soon.');

    const filterCount = [
        searchValue !== '' ? searchValue : null,
        loanType,
        status,
        minAmountValue,
        maxAmountValue,
    ].filter((value) => value !== null && value !== undefined).length;
    const hasFilters = filterCount > 0;
    const summaryCounts = useMemo(
        () => ({
            total: totalResults,
            underReview: items.filter(
                (item) => normalizeStatus(item.status) === 'under_review',
            ).length,
            approved: items.filter(
                (item) => normalizeStatus(item.status) === 'approved',
            ).length,
            cancelled: items.filter(
                (item) => normalizeStatus(item.status) === 'cancelled',
            ).length,
            declined: items.filter(
                (item) => normalizeStatus(item.status) === 'declined',
            ).length,
        }),
        [items, totalResults],
    );
    const activeFilterBadges = [
        searchValue !== '' ? `Search: ${searchValue}` : null,
        loanType ? `Type: ${loanType}` : null,
        status ? `Status: ${statusLabels[status]}` : null,
        minAmountValue !== undefined
            ? `Min: ${formatCurrency(minAmountValue)}`
            : null,
        maxAmountValue !== undefined
            ? `Max: ${formatCurrency(maxAmountValue)}`
            : null,
    ].filter((value): value is string => Boolean(value));
    const shouldShowFiltersSummary =
        activeFilterBadges.length > 0 && meta.available;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Requests" />
            <PageShell size="wide">
                <LoanRequestPageHero
                    kicker="Requests"
                    title="Loan Requests"
                    description="Review member submissions, monitor status updates, and open full request details for printing or PDF export."
                    badges={
                        <>
                            <Badge variant="secondary">
                                {formatCountLabel(totalResults, 'request')}
                            </Badge>
                            {filterCount > 0 ? (
                                <Badge variant="outline">
                                    {formatCountLabel(
                                        filterCount,
                                        'active filter',
                                    )}
                                </Badge>
                            ) : null}
                            {loading ? (
                                <Badge variant="outline">Updating</Badge>
                            ) : null}
                        </>
                    }
                />

                <LoanRequestSummaryCards
                    items={[
                        { label: 'Total', value: summaryCounts.total },
                        {
                            label: 'Under review',
                            value: summaryCounts.underReview,
                            emphasisClassName:
                                'text-sky-600 dark:text-sky-400',
                        },
                        {
                            label: 'Approved',
                            value: summaryCounts.approved,
                            emphasisClassName:
                                'text-emerald-600 dark:text-emerald-400',
                        },
                        {
                            label: 'Cancelled',
                            value: summaryCounts.cancelled,
                            emphasisClassName: 'text-rose-600 dark:text-rose-400',
                        },
                        {
                            label: 'Declined',
                            value: summaryCounts.declined,
                            emphasisClassName:
                                'text-orange-600 dark:text-orange-400',
                        },
                    ]}
                    helperText="Status cards for Under review, Approved, Cancelled, and Declined reflect the current results page."
                />

                <section className="rounded-2xl border border-border/40 bg-card/60 p-4 shadow-sm sm:p-5">
                    <div className="space-y-4">
                        <LoanRequestStatusFilters
                            options={statusOptions}
                            activeValue={statusFilter}
                            onChange={(nextStatus) => {
                                setStatusFilter(nextStatus);
                                setPage(1);
                            }}
                        />

                        <LoanRequestSearchBox
                            value={search}
                            onChange={(nextSearch) => {
                                setSearch(nextSearch);
                                setPage(1);
                            }}
                            placeholder="Search by account, member, loan type, or status"
                            resultsText={resultsLabel}
                            actions={
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={!hasFilters}
                                    onClick={() => {
                                        setSearch('');
                                        setLoanType(null);
                                        setStatusFilter('all');
                                        setMinAmount('');
                                        setMaxAmount('');
                                        setPage(1);
                                    }}
                                >
                                    Clear filters
                                </Button>
                            }
                        />

                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div className="space-y-1">
                                <span className="text-xs font-medium text-muted-foreground">
                                    Loan type
                                </span>
                                <Select
                                    value={loanType ?? 'all'}
                                    onValueChange={(value) => {
                                        setLoanType(
                                            value === 'all' ? null : value,
                                        );
                                        setPage(1);
                                    }}
                                >
                                    <SelectTrigger aria-label="Loan type">
                                        <SelectValue placeholder="All loan types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All loan types
                                        </SelectItem>
                                        {loanTypeOptions.map((option) => (
                                            <SelectItem
                                                key={option}
                                                value={option}
                                            >
                                                {option}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <label
                                    className="text-xs font-medium text-muted-foreground"
                                    htmlFor="requests-min-amount"
                                >
                                    Min amount
                                </label>
                                <Input
                                    id="requests-min-amount"
                                    type="number"
                                    inputMode="decimal"
                                    min={0}
                                    step="0.01"
                                    placeholder="0.00"
                                    value={minAmount}
                                    onChange={(event) => {
                                        setMinAmount(event.target.value);
                                        setPage(1);
                                    }}
                                />
                            </div>

                            <div className="space-y-1">
                                <label
                                    className="text-xs font-medium text-muted-foreground"
                                    htmlFor="requests-max-amount"
                                >
                                    Max amount
                                </label>
                                <Input
                                    id="requests-max-amount"
                                    type="number"
                                    inputMode="decimal"
                                    min={0}
                                    step="0.01"
                                    placeholder="0.00"
                                    value={maxAmount}
                                    onChange={(event) => {
                                        setMaxAmount(event.target.value);
                                        setPage(1);
                                    }}
                                />
                            </div>
                        </div>

                        {shouldShowFiltersSummary ? (
                            <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                                {activeFilterBadges.map((label) => (
                                    <Badge
                                        key={label}
                                        variant="outline"
                                        className="bg-background/50"
                                    >
                                        {label}
                                    </Badge>
                                ))}
                            </div>
                        ) : null}
                    </div>
                </section>

                {error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Unable to load requests</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                <section className="overflow-hidden rounded-2xl border border-border/40 bg-card/60 shadow-sm">
                    <div className="border-b border-border/40 bg-card/70 px-4 py-4 sm:px-6">
                        <h2 className="text-lg font-semibold">Request results</h2>
                        <p className="text-sm text-muted-foreground">
                            {resultsLabel}
                        </p>
                    </div>

                    <div className="px-2 pb-2 sm:px-4 sm:pb-4">
                        <div className="hidden md:block">
                            {showSkeleton ? (
                                <TableSkeleton
                                    columns={requestsTableSkeletonColumns}
                                    rows={perPage}
                                    className="pt-4"
                                    tableClassName="bg-transparent"
                                />
                            ) : (
                                <DataTable
                                    columns={columns}
                                    data={items}
                                    emptyMessage={emptyMessage}
                                    className="border-0 bg-transparent"
                                />
                            )}
                        </div>

                        <div className="md:hidden">
                            {showSkeleton ? (
                                <div className="space-y-3 px-2 pb-3 pt-4">
                                    {Array.from({ length: 3 }).map(
                                        (_, index) => (
                                            <div
                                                key={`request-skeleton-${index}`}
                                                className="rounded-xl border border-border/40 bg-card/50 p-4"
                                            >
                                                <div className="flex items-center justify-between gap-4">
                                                    <Skeleton className="h-4 w-32" />
                                                    <Skeleton className="h-5 w-20" />
                                                </div>
                                                <div className="mt-4 grid grid-cols-2 gap-3">
                                                    <Skeleton className="h-3 w-24" />
                                                    <Skeleton className="h-3 w-24" />
                                                    <Skeleton className="h-3 w-20" />
                                                    <Skeleton className="h-3 w-24" />
                                                </div>
                                                <div className="mt-4 flex justify-end">
                                                    <Skeleton className="h-8 w-28" />
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </div>
                            ) : items.length > 0 ? (
                                <div className="space-y-3 px-2 pb-3 pt-4">
                                    {items.map((item, index) => (
                                        <div
                                            key={
                                                item.id ??
                                                `${item.member_name ?? 'request'}-${index}`
                                            }
                                            className="rounded-xl border border-border/40 bg-card/60 p-4 shadow-sm"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <p className="text-sm font-semibold text-foreground">
                                                        {item.member_name ?? '--'}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {`Reference: ${item.reference ?? '--'}`}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {item.loan_type ??
                                                            'Loan type unavailable'}
                                                    </p>
                                                </div>
                                                <LoanRequestStatusBadge
                                                    status={item.status}
                                                    className="text-[0.65rem]"
                                                />
                                            </div>
                                            <div className="mt-4 grid grid-cols-2 gap-3 text-xs">
                                                <div className="space-y-1">
                                                    <p className="text-muted-foreground">
                                                        Amount
                                                    </p>
                                                    <p className="text-sm font-semibold text-foreground">
                                                        {item.requested_amount !==
                                                            null &&
                                                        item.requested_amount !==
                                                            undefined
                                                            ? formatCurrency(
                                                                  Number(
                                                                      item.requested_amount,
                                                                  ),
                                                              )
                                                            : '--'}
                                                    </p>
                                                </div>
                                                <div className="space-y-1">
                                                    <p className="text-muted-foreground">
                                                        Submitted
                                                    </p>
                                                    <p className="text-sm font-medium text-foreground">
                                                        {formatDate(
                                                            item.submitted_at ??
                                                                item.created_at,
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="mt-4 flex justify-end">
                                                {item.id ? (
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <Link
                                                            href={
                                                                requestsShow(
                                                                    item.id,
                                                                ).url
                                                            }
                                                        >
                                                            View request
                                                        </Link>
                                                    </Button>
                                                ) : null}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="px-4 pb-6 pt-6 text-center text-sm text-muted-foreground">
                                    {emptyMessage}
                                </div>
                            )}
                        </div>
                    </div>
                </section>

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
