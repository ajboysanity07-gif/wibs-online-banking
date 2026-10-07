import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { FileText } from 'lucide-react';
import { useMemo, useState } from 'react';
import { RequestsPager } from '@/components/loan-request/loan-request-queue-page';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { PageShell } from '@/components/page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import {
    TableSkeleton,
    type TableSkeletonColumn,
} from '@/components/ui/table-skeleton';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/formatters';
import type { BreadcrumbItem } from '@/types';
import type { ReportedRequestsResponse, RequestPreview } from '@/types/admin';

const labelClass =
    'text-[11px] font-bold tracking-[0.08em] text-muted-foreground uppercase';

const formatCountLabel = (count: number, label: string): string => {
    return count === 1 ? `${count} ${label}` : `${count} ${label}s`;
};

const formatReportedAt = (value?: string | null): string => {
    if (!value) {
        return '--';
    }

    return formatDateTime(value);
};

const buildColumns = (
    requestHref: (requestId: number) => string,
): ColumnDef<RequestPreview>[] => [
    {
        accessorKey: 'reference',
        meta: { priority: 'detail' },
        header: 'Reference',
        cell: ({ row }) => row.original.reference ?? '--',
    },
    {
        accessorKey: 'member_name',
        meta: { priority: 'title', text: (row) => row.member_name ?? '' },
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
        meta: { priority: 'badge' },
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
        meta: { priority: 'detail' },
        header: 'Reported issue',
        cell: ({ row }) => row.original.latest_correction_report_issue ?? '--',
    },
    {
        accessorKey: 'latest_correction_report_correct_information',
        meta: { priority: 'detail' },
        header: 'Correct information',
        cell: ({ row }) =>
            row.original.latest_correction_report_correct_information ?? '--',
    },
    {
        accessorKey: 'latest_correction_report_reported_at',
        meta: { priority: 'detail' },
        header: 'Reported at',
        cell: ({ row }) =>
            formatReportedAt(row.original.latest_correction_report_reported_at),
    },
    {
        id: 'action',
        meta: { priority: 'action', label: 'Action' },
        header: () => <div className="flex justify-end">Action</div>,
        cell: ({ row }) => {
            const requestId = row.original.id;

            if (!requestId) {
                return <div className="flex justify-end">--</div>;
            }

            return (
                <div className="flex justify-end">
                    <Button asChild size="sm" variant="outline">
                        <Link href={requestHref(requestId)}>View request</Link>
                    </Button>
                </div>
            );
        },
    },
];

const reportedRequestsTableSkeletonColumns: TableSkeletonColumn[] = [
    { headerClassName: 'w-24', cellClassName: 'w-28' },
    { headerClassName: 'w-32', cellClassName: 'w-40' },
    { headerClassName: 'w-24', cellClassName: 'w-36' },
    { headerClassName: 'w-40', cellClassName: 'w-48' },
    { headerClassName: 'w-40', cellClassName: 'w-48' },
    { headerClassName: 'w-32', cellClassName: 'w-32' },
    { headerClassName: 'w-24', cellClassName: 'w-24', align: 'right' },
];

type ReportedRequestsData = {
    items: RequestPreview[];
    meta: ReportedRequestsResponse['meta'];
    loading: boolean;
    error: string | null;
    warning: string | null;
};

type ReportedRequestsPageProps = {
    breadcrumbs: BreadcrumbItem[];
    useReportedList: (params: {
        search: string;
        page: number;
        perPage: number;
    }) => ReportedRequestsData;
    requestHref: (requestId: number) => string;
    backHref: string;
};

export function ReportedRequestsPage({
    breadcrumbs,
    useReportedList,
    requestHref,
    backHref,
}: ReportedRequestsPageProps) {
    const columns = useMemo(() => buildColumns(requestHref), [requestHref]);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [perPage] = useState(10);

    const searchValue = search.trim();
    const { items, meta, loading, error, warning } = useReportedList({
        search,
        page,
        perPage,
    });
    const showSkeleton = loading && items.length === 0;
    const totalResults = meta.total;
    const pageStart = totalResults > 0 ? (meta.page - 1) * meta.perPage + 1 : 0;
    const pageEnd =
        totalResults > 0 ? Math.min(meta.page * meta.perPage, totalResults) : 0;
    const resultsLabel = warning
        ? warning
        : totalResults > 0
          ? `Showing ${pageStart}-${pageEnd} of ${formatCountLabel(
                totalResults,
                'reported request',
            )}`
          : 'No reported requests';
    const emptyMessage = warning
        ? warning
        : searchValue !== ''
          ? 'No reported requests match the current search.'
          : 'No reported requests';
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reported Requests" />
            <PageShell size="wide">
                <section className="flex flex-wrap items-start gap-5">
                    <div>
                        <p className="text-[11px] font-bold tracking-[0.14em] text-primary uppercase">
                            Correction reports
                        </p>
                        <h1 className="mt-1 text-[22px] leading-tight font-bold sm:text-[26px]">
                            Reported Requests
                        </h1>
                        <p className="mt-1.5 max-w-prose text-sm text-muted-foreground">
                            Review member-reported request details, verify the
                            correct information, and resolve each report.
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <Badge className="border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-200">
                                {formatCountLabel(
                                    meta.openCorrectionReports,
                                    'open report',
                                )}
                            </Badge>
                            <Badge variant="secondary">
                                {formatCountLabel(totalResults, 'report')}
                            </Badge>
                            {loading ? (
                                <Badge variant="secondary">Updating</Badge>
                            ) : null}
                        </div>
                    </div>
                    <div className="w-full pt-1.5 sm:ml-auto sm:w-auto">
                        <Button
                            asChild
                            variant="outline"
                            className="w-full sm:w-auto"
                        >
                            <Link href={backHref}>
                                <FileText />
                                Back to all requests
                            </Link>
                        </Button>
                    </div>
                </section>

                <Card
                    className="gap-0 overflow-hidden py-0"
                    aria-label="Search and filter reported requests"
                >
                    <div className="flex flex-wrap items-end gap-3 px-5 py-4">
                        <div className="grid min-w-[260px] flex-1 gap-1">
                            <Label
                                htmlFor="reported-requests-search"
                                className={labelClass}
                            >
                                Search
                            </Label>
                            <Input
                                id="reported-requests-search"
                                type="search"
                                value={search}
                                onChange={(event) => {
                                    setSearch(event.target.value);
                                    setPage(1);
                                }}
                                placeholder="Search by reference, member, account, reported issue, or correct information"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                setSearch('');
                                setPage(1);
                            }}
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

                {warning && !error ? (
                    <Alert>
                        <AlertTitle>Reported requests unavailable</AlertTitle>
                        <AlertDescription>{warning}</AlertDescription>
                    </Alert>
                ) : null}

                {error ? (
                    <Alert variant="destructive">
                        <AlertTitle>
                            Unable to load reported requests
                        </AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                <section className="overflow-hidden rounded-xl border border-border bg-card shadow-card">
                    <div className="flex flex-wrap items-baseline gap-2 px-5 pt-[18px] pb-4">
                        <h2 className="text-base font-bold">Results</h2>
                        <span className="text-[13px] text-muted-foreground">
                            {formatCountLabel(totalResults, 'report')}
                        </span>
                    </div>

                    <div>
                        <div>
                            {showSkeleton ? (
                                <TableSkeleton
                                    columns={
                                        reportedRequestsTableSkeletonColumns
                                    }
                                    rows={perPage}
                                    className="hidden md:block"
                                    tableClassName="bg-transparent"
                                />
                            ) : (
                                <DataTable
                                    columns={columns}
                                    data={items}
                                    emptyMessage={emptyMessage}
                                    className="rounded-none border-0 border-t border-border bg-transparent shadow-none"
                                />
                            )}
                        </div>

                        <div className="md:hidden">
                            {showSkeleton ? (
                                <div className="space-y-3 px-2 pt-4 pb-3">
                                    {Array.from({ length: 3 }).map(
                                        (_, index) => (
                                            <div
                                                key={`reported-request-skeleton-${index}`}
                                                className="rounded-xl border border-border bg-card p-4"
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
                            ) : null}
                        </div>
                    </div>

                    {showSkeleton ? null : (
                        <RequestsPager
                            page={meta.page}
                            perPage={meta.perPage}
                            total={meta.total}
                            onPageChange={setPage}
                        />
                    )}
                </section>
            </PageShell>
        </AppLayout>
    );
}
