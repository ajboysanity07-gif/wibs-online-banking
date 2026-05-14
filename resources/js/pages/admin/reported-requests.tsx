import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import {
    LoanRequestPageHero,
    LoanRequestSearchBox,
    LoanRequestSummaryCards,
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
import { Skeleton } from '@/components/ui/skeleton';
import { TableSkeleton } from '@/components/ui/table-skeleton';
import { useReportedRequests } from '@/hooks/admin/use-reported-requests';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/formatters';
import {
    index as requestsIndex,
    reported as requestsReported,
    show as requestsShow,
} from '@/routes/admin/requests';
import type { BreadcrumbItem } from '@/types';
import type { RequestPreview } from '@/types/admin';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Requests',
        href: requestsIndex().url,
    },
    {
        title: 'Reported Requests',
        href: requestsReported().url,
    },
];

const formatCountLabel = (count: number, label: string): string => {
    return count === 1 ? `${count} ${label}` : `${count} ${label}s`;
};

const formatReportedAt = (value?: string | null): string => {
    if (!value) {
        return '--';
    }

    return formatDateTime(value);
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
        cell: ({ row }) => {
            const memberName = row.original.member_name ?? '--';
            const memberAcctNo = row.original.member_acctno ?? '--';

            return (
                <div className="space-y-1">
                    <p className="text-sm font-medium">{memberName}</p>
                    <p className="text-xs text-muted-foreground">
                        Acct: {memberAcctNo}
                    </p>
                </div>
            );
        },
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => (
            <div className="flex flex-wrap items-center gap-2">
                <LoanRequestStatusBadge status={row.original.status} />
                <Badge
                    variant="outline"
                    className="border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-200"
                >
                    Correction reported
                </Badge>
            </div>
        ),
    },
    {
        accessorKey: 'latest_correction_report_issue',
        header: 'Reported issue',
        cell: ({ row }) =>
            row.original.latest_correction_report_issue ?? '--',
    },
    {
        accessorKey: 'latest_correction_report_correct_information',
        header: 'Correct information',
        cell: ({ row }) =>
            row.original.latest_correction_report_correct_information ?? '--',
    },
    {
        accessorKey: 'latest_correction_report_reported_at',
        header: 'Reported at',
        cell: ({ row }) =>
            formatReportedAt(
                row.original.latest_correction_report_reported_at,
            ),
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

const reportedRequestsTableSkeletonColumns = [
    { headerClassName: 'w-24', cellClassName: 'w-28' },
    { headerClassName: 'w-32', cellClassName: 'w-40' },
    { headerClassName: 'w-24', cellClassName: 'w-36' },
    { headerClassName: 'w-40', cellClassName: 'w-48' },
    { headerClassName: 'w-40', cellClassName: 'w-48' },
    { headerClassName: 'w-32', cellClassName: 'w-32' },
    { headerClassName: 'w-24', cellClassName: 'w-24', align: 'right' },
];

export default function ReportedRequestsPage() {
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [perPage] = useState(10);

    const searchValue = search.trim();
    const { items, meta, loading, error } = useReportedRequests({
        search,
        page,
        perPage,
    });
    const showSkeleton = loading && items.length === 0;
    const totalResults = meta.total;
    const pageStart =
        totalResults > 0 ? (meta.page - 1) * meta.perPage + 1 : 0;
    const pageEnd =
        totalResults > 0
            ? Math.min(meta.page * meta.perPage, totalResults)
            : 0;
    const resultsLabel = totalResults > 0
        ? `Showing ${pageStart}-${pageEnd} of ${formatCountLabel(
              totalResults,
              'reported request',
          )}`
        : 'No reported requests';
    const emptyMessage =
        searchValue !== ''
            ? 'No reported requests match the current search.'
            : 'No reported requests';
    const oldestPendingReportedAt = useMemo(() => {
        if (items.length === 0) {
            return '--';
        }

        const timestamps = items
            .map((item) => item.latest_correction_report_reported_at)
            .filter((value): value is string => Boolean(value))
            .map((value) => new Date(value).getTime())
            .filter((value) => Number.isFinite(value));

        if (timestamps.length === 0) {
            return '--';
        }

        const oldest = Math.min(...timestamps);

        return formatReportedAt(new Date(oldest).toISOString());
    }, [items]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reported Requests" />
            <PageShell size="wide">
                <LoanRequestPageHero
                    kicker="Reports"
                    title="Reported Requests"
                    description="Review approved loan requests that members reported for incorrect details."
                    badges={
                        <>
                            <Badge variant="secondary">
                                {formatCountLabel(
                                    meta.openCorrectionReports,
                                    'open report',
                                )}
                            </Badge>
                            {loading ? (
                                <Badge variant="outline">Updating</Badge>
                            ) : null}
                        </>
                    }
                />

                <LoanRequestSummaryCards
                    items={[
                        {
                            label: 'Open reports',
                            value: meta.openCorrectionReports,
                            emphasisClassName:
                                'text-amber-600 dark:text-amber-400',
                        },
                        {
                            label: 'Approved requests reported',
                            value: totalResults,
                        },
                        {
                            label: 'Oldest pending report',
                            value: oldestPendingReportedAt,
                        },
                    ]}
                    helperText="Admins should review each report from the request detail page before cancelling or dismissing."
                />

                <section className="rounded-2xl border border-border/40 bg-card/60 p-4 shadow-sm sm:p-5">
                    <LoanRequestSearchBox
                        value={search}
                        onChange={(nextSearch) => {
                            setSearch(nextSearch);
                            setPage(1);
                        }}
                        placeholder="Search by reference, member, account, reported issue, or correct information"
                        resultsText={resultsLabel}
                    />
                </section>

                {error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Unable to load reported requests</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                <section className="overflow-hidden rounded-2xl border border-border/40 bg-card/60 shadow-sm">
                    <div className="border-b border-border/40 bg-card/70 px-4 py-4 sm:px-6">
                        <h2 className="text-lg font-semibold">
                            Reported request queue
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {resultsLabel}
                        </p>
                    </div>

                    <div className="px-2 pb-2 sm:px-4 sm:pb-4">
                        <div className="hidden md:block">
                            {showSkeleton ? (
                                <TableSkeleton
                                    columns={
                                        reportedRequestsTableSkeletonColumns
                                    }
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
                                                key={`reported-request-skeleton-${index}`}
                                                className="rounded-xl border border-border/40 bg-card/50 p-4"
                                            >
                                                <div className="flex items-center justify-between gap-4">
                                                    <Skeleton className="h-4 w-32" />
                                                    <Skeleton className="h-5 w-20" />
                                                </div>
                                                <div className="mt-4 space-y-2">
                                                    <Skeleton className="h-3 w-full" />
                                                    <Skeleton className="h-3 w-10/12" />
                                                    <Skeleton className="h-3 w-11/12" />
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
                                                `${item.reference ?? 'reported-request'}-${index}`
                                            }
                                            className="rounded-xl border border-border/40 bg-card/60 p-4 shadow-sm"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <p className="text-sm font-semibold text-foreground">
                                                        {item.reference ?? '--'}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {item.member_name ?? '--'}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Acct:{' '}
                                                        {item.member_acctno ??
                                                            '--'}
                                                    </p>
                                                </div>
                                                <div className="flex flex-wrap justify-end gap-1">
                                                    <LoanRequestStatusBadge
                                                        status={item.status}
                                                        className="text-[0.65rem]"
                                                    />
                                                    <Badge
                                                        variant="outline"
                                                        className="border-amber-500/30 bg-amber-500/10 text-[0.65rem] text-amber-700 dark:text-amber-200"
                                                    >
                                                        Correction reported
                                                    </Badge>
                                                </div>
                                            </div>
                                            <div className="mt-4 space-y-3 text-xs">
                                                <div>
                                                    <p className="text-muted-foreground">
                                                        Reported issue
                                                    </p>
                                                    <p className="text-sm text-foreground">
                                                        {item.latest_correction_report_issue ??
                                                            '--'}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground">
                                                        Correct information
                                                    </p>
                                                    <p className="text-sm text-foreground">
                                                        {item.latest_correction_report_correct_information ??
                                                            '--'}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground">
                                                        Reported at
                                                    </p>
                                                    <p className="text-sm text-foreground">
                                                        {formatReportedAt(
                                                            item.latest_correction_report_reported_at,
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
                                <div className="px-4 pb-6 pt-6 text-center">
                                    <p className="text-sm font-medium text-foreground">
                                        No reported requests
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Member correction reports will appear
                                        here for admin review.
                                    </p>
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
