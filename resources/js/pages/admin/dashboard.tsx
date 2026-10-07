import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    FileText,
    Flag,
    Loader2,
    RefreshCw,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { PageShell } from '@/components/page-shell';
import {
    ResponsiveDataList,
    type ResponsiveDataListColumn,
} from '@/components/responsive-data-list';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    TableSkeleton,
    type TableSkeletonColumn,
} from '@/components/ui/table-skeleton';
import { useAdminDashboard } from '@/hooks/admin/use-admin-dashboard';
import { useMembers } from '@/hooks/admin/use-members';
import AppLayout from '@/layouts/app-layout';
import {
    getRegistrationStatusLabel,
    getRegistrationStatusVariant,
} from '@/lib/member-status';
import { dashboard } from '@/routes/admin';
import { show as showMember } from '@/routes/admin/members';
import { index as reportsIndex } from '@/routes/admin/reports';
import {
    index as requestsIndex,
    reported as reportedRequestsIndex,
} from '@/routes/admin/requests';
import { index as membersIndex } from '@/routes/admin/watchlist';
import type { BreadcrumbItem } from '@/types';
import type { DashboardSummary } from '@/types/admin';
import type {
    DateRange,
    ReportingMetrics,
    StaffPerformanceRow,
} from '@/types/reports';

type DateRangeToggle = 'today' | 'week' | 'month' | 'all';

type Props = {
    summary: DashboardSummary;
    reportingMetrics?: ReportingMetrics;
    applicationVolume?: Record<string, number>;
    staffPerformance?: StaffPerformanceRow[];
    canExport?: boolean;
    dateRange?: DateRange;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin',
        href: dashboard().url,
    },
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const formatDate = (value?: string | null): string => {
    if (!value) {
        return '--';
    }

    return new Date(value).toLocaleDateString();
};

const requestPreviewSkeletonColumns: TableSkeletonColumn[] = [
    { headerClassName: 'w-28', cellClassName: 'w-32' },
    { headerClassName: 'w-24', cellClassName: 'w-28' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
];

const memberLookupSkeletonColumns: TableSkeletonColumn[] = [
    { headerClassName: 'w-28', cellClassName: 'w-32' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-16', cellClassName: 'w-16' },
    { headerClassName: 'w-12', cellClassName: 'h-8 w-24', align: 'right' },
];

function useReducedMotion(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

function ApplicationVolumeChart({ data }: { data: Record<string, number> }) {
    const reducedMotion = useReducedMotion();
    const chartData = Object.entries(data).map(([date, count]) => ({
        date: date.slice(5),
        count,
    }));

    return (
        <ResponsiveContainer width="100%" height={200}>
            <BarChart
                data={chartData}
                margin={{ top: 4, right: 4, left: -16, bottom: 0 }}
            >
                <CartesianGrid
                    strokeDasharray="3 3"
                    stroke="var(--border)"
                    vertical={false}
                />
                <XAxis
                    dataKey="date"
                    tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                    axisLine={false}
                    tickLine={false}
                />
                <YAxis
                    allowDecimals={false}
                    tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                    axisLine={false}
                    tickLine={false}
                />
                <Tooltip
                    cursor={{ fill: 'var(--muted)', opacity: 0.5 }}
                    contentStyle={{
                        background: 'var(--card)',
                        border: '1px solid var(--border)',
                        borderRadius: '0.5rem',
                        fontSize: '12px',
                        color: 'var(--card-foreground)',
                    }}
                    formatter={(value) => [
                        typeof value === 'number' ? value : Number(value),
                        'Applications',
                    ]}
                />
                <Bar
                    dataKey="count"
                    fill="var(--chart-1)"
                    radius={[4, 4, 0, 0]}
                    isAnimationActive={!reducedMotion}
                />
            </BarChart>
        </ResponsiveContainer>
    );
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 0,
    }).format(value);
}

function buildDateRangeParams(toggle: DateRangeToggle): {
    from?: string;
    to?: string;
} {
    const today = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');
    const fmt = (d: Date) =>
        `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

    const to = fmt(today);
    let from: string | undefined;

    if (toggle === 'today') {
        from = to;
    } else if (toggle === 'week') {
        const d = new Date(today);
        d.setDate(d.getDate() - 6);
        from = fmt(d);
    } else if (toggle === 'month') {
        from = fmt(new Date(today.getFullYear(), today.getMonth(), 1));
    }

    return from ? { from, to } : {};
}

const dateRangeToggleLabels: Record<DateRangeToggle, string> = {
    today: 'Today',
    week: 'This week',
    month: 'This month',
    all: 'All time',
};

const memberLookupListColumns: ResponsiveDataListColumn[] = [
    { key: 'member', label: 'Member', priority: 'title' },
    { key: 'acctno', label: 'Account No', priority: 'detail' },
    { key: 'registration', label: 'Registration', priority: 'badge' },
    { key: 'action', label: 'Action', priority: 'action' },
];

const requestListColumns: ResponsiveDataListColumn[] = [
    { key: 'member', label: 'Member', priority: 'title' },
    { key: 'reference', label: 'Reference', priority: 'detail' },
    { key: 'status', label: 'Status', priority: 'badge' },
    { key: 'created', label: 'Created', priority: 'detail' },
];

const staffWorkloadListColumns: ResponsiveDataListColumn[] = [
    { key: 'name', label: 'Processor', priority: 'title' },
    { key: 'assigned', label: 'Assigned', priority: 'amount' },
    { key: 'approved', label: 'Approved', priority: 'detail' },
    { key: 'rejected', label: 'Rejected', priority: 'detail' },
    { key: 'avg_days', label: 'Avg days', priority: 'detail' },
];

export default function AdminDashboard({
    summary,
    reportingMetrics,
    applicationVolume,
    staffPerformance,
    canExport,
}: Props) {
    const {
        summary: summaryState,
        refresh,
        loading,
        error,
    } = useAdminDashboard(summary);
    const [pendingDateRangeToggle, setPendingDateRangeToggle] =
        useState<DateRangeToggle | null>(null);

    useEffect(() => {
        void refresh();
    }, [refresh]);

    const applyDateRangeToggle = (toggle: DateRangeToggle) => {
        setPendingDateRangeToggle(toggle);
        router.get(dashboard().url, buildDateRangeParams(toggle), {
            preserveScroll: true,
            replace: true,
            onFinish: () => setPendingDateRangeToggle(null),
        });
    };

    const {
        items: lookupRows,
        loading: lookupLoading,
        error: lookupError,
    } = useMembers({
        search: '',
        registration: 'unregistered',
        sort: 'newest',
        page: 1,
        perPage: 4,
    });

    const requestsPreview = summaryState.requests;
    const lookupEmptyMessage = 'No members are awaiting registration.';
    const requestsSkeleton = loading && requestsPreview.length === 0;
    const lookupSkeleton = lookupLoading && lookupRows.length === 0;

    const staffRows = staffPerformance ?? [];

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            statusLabel={loading ? 'Syncing…' : 'WIBS Desktop synced'}
        >
            <Head title="Admin Dashboard" />
            <PageShell size="wide" className="gap-8">
                <section className="flex flex-wrap items-start gap-5">
                    <div>
                        <p className="text-[11px] font-bold tracking-[0.14em] text-primary uppercase">
                            Admin
                        </p>
                        <h1 className="mt-1 text-[22px] leading-tight font-bold sm:text-[26px]">
                            Dashboard
                        </h1>
                        <p className="mt-1.5 max-w-prose text-sm text-muted-foreground">
                            Members, requests, and account overview.
                        </p>
                        {loading ? (
                            <div className="mt-3 flex flex-wrap gap-2">
                                <Badge variant="outline">Refreshing</Badge>
                            </div>
                        ) : null}
                    </div>
                    <div className="flex w-full flex-wrap gap-2 pt-1.5 sm:ml-auto sm:w-auto">
                        <Button
                            variant="outline"
                            disabled={loading}
                            onClick={() => void refresh()}
                            className="max-sm:flex-1"
                        >
                            <RefreshCw className="size-4" />
                            Refresh
                        </Button>
                        <Button asChild className="max-sm:flex-1">
                            <Link href={requestsIndex().url}>
                                Open request queue
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    </div>
                </section>

                {error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Dashboard sync failed</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <Card className="rounded-xl border-t-4 border-border border-t-primary bg-card shadow-card">
                        <CardHeader>
                            <CardDescription>
                                Registered members
                            </CardDescription>
                            <CardTitle className="text-3xl tabular-nums">
                                {summaryState.metrics.registeredCount}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Members with portal access
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="rounded-xl border-t-4 border-border border-t-primary bg-card shadow-card">
                        <CardHeader>
                            <CardDescription>
                                Unregistered members
                            </CardDescription>
                            <CardTitle className="text-3xl tabular-nums">
                                {summaryState.metrics.unregisteredCount}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Members without portal logins
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="rounded-xl border-t-4 border-border border-t-primary bg-card shadow-card">
                        <CardHeader>
                            <CardDescription>Total members</CardDescription>
                            <CardTitle className="text-3xl tabular-nums">
                                {summaryState.metrics.totalCount}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                System of record member list
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="rounded-xl border-t-4 border-border border-t-primary bg-card shadow-card">
                        <CardHeader>
                            <CardDescription>
                                Requests awaiting review
                            </CardDescription>
                            <CardTitle className="text-3xl tabular-nums">
                                {summaryState.metrics.requestsCount ?? '--'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Loan requests awaiting review
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card className="rounded-xl border-border bg-card shadow-card">
                    <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle>Members awaiting registration</CardTitle>
                            <CardDescription>
                                Members without portal logins.
                            </CardDescription>
                        </div>
                        <Button asChild size="sm" variant="ghost">
                            <Link href={membersIndex().url}>
                                Open directory
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="px-0">
                        {lookupError ? (
                            <Alert
                                variant="destructive"
                                className="mx-6 mb-4 w-auto"
                            >
                                <AlertTitle>Lookup failed</AlertTitle>
                                <AlertDescription>
                                    {lookupError}
                                </AlertDescription>
                            </Alert>
                        ) : null}
                        {lookupSkeleton ? (
                            <div aria-busy="true">
                                <TableSkeleton
                                    columns={memberLookupSkeletonColumns}
                                    rows={4}
                                    tableClassName="[&_td]:px-6 [&_th]:px-6"
                                />
                            </div>
                        ) : (
                            <ResponsiveDataList
                                columns={memberLookupListColumns}
                                rows={lookupRows.map((member) => ({
                                    id: String(member.member_id),
                                    initials: member.member_name ?? '',
                                    cells: {
                                        member: member.member_name,
                                        acctno: member.acctno ?? '--',
                                        registration: (
                                            <Badge
                                                variant={getRegistrationStatusVariant(
                                                    member.registration_status,
                                                )}
                                            >
                                                {getRegistrationStatusLabel(
                                                    member.registration_status,
                                                )}
                                            </Badge>
                                        ),
                                        action: (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    href={
                                                        showMember(
                                                            member.member_id,
                                                        ).url
                                                    }
                                                >
                                                    Open profile
                                                </Link>
                                            </Button>
                                        ),
                                    },
                                }))}
                                emptyMessage={lookupEmptyMessage}
                            >
                                <div className="overflow-x-auto">
                                    <Table>
                                        <TableHeader className="text-muted-foreground">
                                            <TableRow>
                                                <TableHead className="px-6">
                                                    Member
                                                </TableHead>
                                                <TableHead className="px-6">
                                                    Account No
                                                </TableHead>
                                                <TableHead className="px-6">
                                                    Registration
                                                </TableHead>
                                                <TableHead className="px-6 text-right">
                                                    Action
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {lookupRows.length === 0 ? (
                                                <TableRow>
                                                    <TableCell
                                                        colSpan={4}
                                                        className="h-24 text-center text-sm text-muted-foreground"
                                                    >
                                                        {lookupEmptyMessage}
                                                    </TableCell>
                                                </TableRow>
                                            ) : (
                                                lookupRows.map((member) => (
                                                    <TableRow
                                                        key={member.member_id}
                                                    >
                                                        <TableCell className="px-6 font-medium">
                                                            {member.member_name}
                                                        </TableCell>
                                                        <TableCell className="px-6">
                                                            {member.acctno ??
                                                                '--'}
                                                        </TableCell>
                                                        <TableCell className="px-6">
                                                            <Badge
                                                                variant={getRegistrationStatusVariant(
                                                                    member.registration_status,
                                                                )}
                                                            >
                                                                {getRegistrationStatusLabel(
                                                                    member.registration_status,
                                                                )}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell className="px-6 text-right">
                                                            <Button
                                                                asChild
                                                                size="sm"
                                                                variant="outline"
                                                            >
                                                                <Link
                                                                    href={
                                                                        showMember(
                                                                            member.member_id,
                                                                        ).url
                                                                    }
                                                                >
                                                                    Open profile
                                                                </Link>
                                                            </Button>
                                                        </TableCell>
                                                    </TableRow>
                                                ))
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                            </ResponsiveDataList>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                    <Card
                        id="requests"
                        className="rounded-xl border-border bg-card shadow-card"
                    >
                        <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle>Recent loan requests</CardTitle>
                                <CardDescription>
                                    Latest submissions.
                                </CardDescription>
                            </div>
                            <Button asChild size="sm" variant="ghost">
                                <Link href={requestsIndex().url}>View all</Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="px-0">
                            {requestsSkeleton ? (
                                <div aria-busy="true">
                                    <TableSkeleton
                                        columns={requestPreviewSkeletonColumns}
                                        rows={5}
                                        tableClassName="[&_td]:px-6 [&_th]:px-6"
                                    />
                                </div>
                            ) : requestsPreview.length === 0 ? (
                                <div className="px-6 py-6 text-center text-sm text-muted-foreground">
                                    No requests yet.
                                </div>
                            ) : (
                                <ResponsiveDataList
                                    columns={requestListColumns}
                                    rows={requestsPreview.map(
                                        (request, index) => ({
                                            id: request.id
                                                ? String(request.id)
                                                : `request-${index}`,
                                            initials: request.member_name ?? '',
                                            cells: {
                                                member:
                                                    request.member_name ?? '--',
                                                reference:
                                                    request.reference ?? '--',
                                                status: (
                                                    <LoanRequestStatusBadge
                                                        status={request.status}
                                                    />
                                                ),
                                                created: formatDate(
                                                    request.created_at,
                                                ),
                                            },
                                        }),
                                    )}
                                >
                                    <div className="overflow-x-auto">
                                        <Table>
                                            <TableHeader className="border-b border-border text-muted-foreground">
                                                <TableRow>
                                                    <TableHead className="px-6">
                                                        Member
                                                    </TableHead>
                                                    <TableHead className="px-6">
                                                        Reference
                                                    </TableHead>
                                                    <TableHead className="px-6">
                                                        Status
                                                    </TableHead>
                                                    <TableHead className="px-6">
                                                        Created
                                                    </TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {requestsPreview.map(
                                                    (request, index) => (
                                                        <TableRow
                                                            key={
                                                                request.id ??
                                                                `request-${index}`
                                                            }
                                                        >
                                                            <TableCell className="px-6 font-medium">
                                                                {request.member_name ??
                                                                    '--'}
                                                            </TableCell>
                                                            <TableCell className="px-6">
                                                                {request.reference ??
                                                                    '--'}
                                                            </TableCell>
                                                            <TableCell className="px-6">
                                                                <LoanRequestStatusBadge
                                                                    status={
                                                                        request.status
                                                                    }
                                                                />
                                                            </TableCell>
                                                            <TableCell className="px-6">
                                                                {formatDate(
                                                                    request.created_at,
                                                                )}
                                                            </TableCell>
                                                        </TableRow>
                                                    ),
                                                )}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </ResponsiveDataList>
                            )}
                        </CardContent>
                    </Card>
                    <Card className="rounded-xl border-border bg-card shadow-card">
                        <CardHeader>
                            <CardTitle>Processor workload</CardTitle>
                            <CardDescription>
                                Assignment and decision summary.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="px-0">
                            <ResponsiveDataList
                                columns={staffWorkloadListColumns}
                                emptyMessage="No processor activity yet."
                                rows={staffRows.map((row) => ({
                                    id: String(row.processor_id),
                                    initials: row.name,
                                    cells: {
                                        name: row.name,
                                        assigned: `${row.assigned} assigned`,
                                        approved: row.approved,
                                        rejected: row.rejected,
                                        avg_days: row.avg_days ?? '--',
                                    },
                                }))}
                            >
                                <div className="overflow-x-auto">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="px-6">
                                                    Processor
                                                </TableHead>
                                                <TableHead className="px-6">
                                                    Assigned
                                                </TableHead>
                                                <TableHead className="px-6">
                                                    Approved
                                                </TableHead>
                                                <TableHead className="px-6">
                                                    Rejected
                                                </TableHead>
                                                <TableHead className="px-6">
                                                    Avg days
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {staffRows.map((row) => (
                                                <TableRow
                                                    key={row.processor_id}
                                                >
                                                    <TableCell className="px-6 font-medium">
                                                        {row.name}
                                                    </TableCell>
                                                    <TableCell className="px-6">
                                                        {row.assigned}
                                                    </TableCell>
                                                    <TableCell className="px-6">
                                                        {row.approved}
                                                    </TableCell>
                                                    <TableCell className="px-6">
                                                        {row.rejected}
                                                    </TableCell>
                                                    <TableCell className="px-6">
                                                        {row.avg_days ?? '--'}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            </ResponsiveDataList>
                        </CardContent>
                    </Card>
                </div>

                <Card className="rounded-xl border-border bg-card shadow-card">
                    <CardHeader>
                        <CardTitle>Quick actions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                            {[
                                {
                                    label: 'Member directory',
                                    href: membersIndex().url,
                                    icon: Users,
                                },
                                {
                                    label: 'Loan request queue',
                                    href: requestsIndex().url,
                                    icon: FileText,
                                },
                                {
                                    label: 'Reported requests',
                                    href: reportedRequestsIndex().url,
                                    icon: Flag,
                                },
                            ].map(({ label, href, icon: Icon }) => (
                                <Button
                                    key={label}
                                    asChild
                                    variant="outline"
                                    className="h-11 justify-start"
                                >
                                    <Link href={href}>
                                        <Icon className="size-4" />
                                        {label}
                                    </Link>
                                </Button>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {reportingMetrics ? (
                    <>
                        <Separator />
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 className="text-lg font-semibold">
                                    Loan workflow reporting
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Application volume and processing metrics.
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {(
                                    [
                                        'today',
                                        'week',
                                        'month',
                                        'all',
                                    ] as DateRangeToggle[]
                                ).map((t) => (
                                    <Button
                                        key={t}
                                        size="sm"
                                        variant="outline"
                                        disabled={
                                            pendingDateRangeToggle !== null
                                        }
                                        onClick={() => applyDateRangeToggle(t)}
                                    >
                                        {pendingDateRangeToggle === t ? (
                                            <>
                                                <Loader2 className="size-4 animate-spin" />
                                                {dateRangeToggleLabels[t]}
                                            </>
                                        ) : (
                                            dateRangeToggleLabels[t]
                                        )}
                                    </Button>
                                ))}
                                {canExport ? (
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        asChild
                                    >
                                        <Link href={reportsIndex().url}>
                                            View reports
                                        </Link>
                                    </Button>
                                ) : null}
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <Card className="rounded-xl border-t-4 border-border border-t-primary bg-card shadow-card">
                                <CardHeader>
                                    <CardDescription>
                                        Pending applications
                                    </CardDescription>
                                    <CardTitle className="text-3xl tabular-nums">
                                        {reportingMetrics.pending_count}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">
                                        In-progress loan requests
                                    </p>
                                </CardContent>
                            </Card>
                            <Card className="rounded-xl border-t-4 border-border border-t-primary bg-card shadow-card">
                                <CardHeader>
                                    <CardDescription>Approved</CardDescription>
                                    <CardTitle className="text-3xl tabular-nums">
                                        {reportingMetrics.approved_count}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">
                                        Approval rate:{' '}
                                        {reportingMetrics.approval_rate}%
                                    </p>
                                </CardContent>
                            </Card>
                            <Card className="rounded-xl border-t-4 border-border border-t-primary bg-card shadow-card">
                                <CardHeader>
                                    <CardDescription>
                                        Avg processing days
                                    </CardDescription>
                                    <CardTitle className="text-3xl tabular-nums">
                                        {reportingMetrics.average_processing_days ??
                                            '--'}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">
                                        Days from submission to decision
                                    </p>
                                </CardContent>
                            </Card>
                            <Card className="rounded-xl border-t-4 border-border border-t-primary bg-card shadow-card">
                                <CardHeader>
                                    <CardDescription>
                                        Portfolio total
                                    </CardDescription>
                                    <CardTitle className="text-2xl tabular-nums">
                                        {formatCurrency(
                                            reportingMetrics.portfolio_total,
                                        )}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">
                                        Sum of approved loan amounts
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        {applicationVolume &&
                        Object.keys(applicationVolume).length > 0 ? (
                            <Card className="rounded-xl border-border bg-card shadow-card">
                                <CardHeader>
                                    <CardTitle>Application volume</CardTitle>
                                    <CardDescription>
                                        Daily loan application submissions.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ApplicationVolumeChart
                                        data={applicationVolume}
                                    />
                                </CardContent>
                            </Card>
                        ) : null}

                        <Separator />
                    </>
                ) : null}
            </PageShell>
        </AppLayout>
    );
}
