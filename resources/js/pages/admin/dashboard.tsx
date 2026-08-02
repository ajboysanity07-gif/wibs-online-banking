import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { MemberListCardSkeleton } from '@/components/member-list-card-skeleton';
import { PageHero } from '@/components/page-hero';
import { PageShell } from '@/components/page-shell';
import { SurfaceCard } from '@/components/surface-card';
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
import { Input } from '@/components/ui/input';
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
import { index as requestsIndex } from '@/routes/admin/requests';
import { index as membersIndex } from '@/routes/admin/watchlist';
import type { BreadcrumbItem } from '@/types';
import type { DashboardSummary, MemberSummary } from '@/types/admin';
import type { DateRange, ReportingMetrics, StaffPerformanceRow } from '@/types/reports';

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
        title: 'Admin Dashboard',
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

const MobileMemberLookupCard = ({ member }: { member: MemberSummary }) => (
    <SurfaceCard variant="default" padding="sm">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-1">
                <p className="text-sm font-semibold">{member.member_name}</p>
                {member.username && member.member_name !== member.username ? (
                    <p className="text-xs text-muted-foreground">
                        {member.username}
                    </p>
                ) : null}
            </div>
            <Badge
                variant={getRegistrationStatusVariant(
                    member.registration_status,
                )}
            >
                {getRegistrationStatusLabel(member.registration_status)}
            </Badge>
        </div>
        <div className="mt-3 space-y-2 rounded-xl border border-border/30 bg-muted/30 p-3 text-xs">
            <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Account No</span>
                <span className="text-sm font-medium">
                    {member.acctno ?? '--'}
                </span>
            </div>
        </div>
        <div className="mt-3">
            <Button
                asChild
                size="sm"
                variant="outline"
                className="w-full sm:w-auto"
            >
                <Link href={showMember(member.member_id).url}>
                    Open profile
                </Link>
            </Button>
        </div>
    </SurfaceCard>
);

function useReducedMotion(): boolean {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function ApplicationVolumeChart({ data }: { data: Record<string, number> }) {
    const reducedMotion = useReducedMotion();
    const chartData = Object.entries(data).map(([date, count]) => ({
        date: date.slice(5),
        count,
    }));

    return (
        <ResponsiveContainer width="100%" height={200}>
            <BarChart data={chartData} margin={{ top: 4, right: 4, left: -16, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
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

function applyDateRangeToggle(toggle: DateRangeToggle): void {
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

    router.get(
        dashboard().url,
        from ? { from, to } : {},
        { preserveScroll: true, replace: true },
    );
}

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
    const [memberSearch, setMemberSearch] = useState('');

    useEffect(() => {
        void refresh();
    }, [refresh]);

    const {
        items: lookupRows,
        loading: lookupLoading,
        error: lookupError,
    } = useMembers({
        search: memberSearch,
        registration: 'all',
        sort: 'newest',
        page: 1,
        perPage: 5,
    });

    const requestsPreview = summaryState.requests;
    const lookupEmptyMessage =
        memberSearch.trim() === ''
            ? 'Search by account number, username, email, or member name.'
            : 'No matching members found.';
    const requestsSkeleton = loading && requestsPreview.length === 0;
    const lookupSkeleton = lookupLoading && lookupRows.length === 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />
            <PageShell size="wide" className="gap-8">
                <PageHero
                    kicker="Admin"
                    title="Dashboard"
                    description="Members, requests, and account overview."
                    rightSlot={
                        <>
                            <Button asChild size="sm" variant="secondary">
                                <a href="#member-lookup">Member lookup</a>
                            </Button>
                            <Button asChild size="sm" variant="outline">
                                <a href="#requests">Recent requests</a>
                            </Button>
                            <Button asChild size="sm" variant="ghost">
                                <Link href={membersIndex().url}>
                                    All members
                                </Link>
                            </Button>
                            {loading ? (
                                <Badge variant="outline">Refreshing</Badge>
                            ) : null}
                        </>
                    }
                />

                {error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Dashboard sync failed</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardDescription>Registered members</CardDescription>
                            <CardTitle className="text-3xl">
                                {summaryState.metrics.registeredCount}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Members with portal access
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardDescription>Unregistered members</CardDescription>
                            <CardTitle className="text-3xl">
                                {summaryState.metrics.unregisteredCount}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Members without portal logins
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardDescription>Total members</CardDescription>
                            <CardTitle className="text-3xl">
                                {summaryState.metrics.totalCount}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                System of record member list
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardDescription>
                                Requests awaiting review
                            </CardDescription>
                            <CardTitle className="text-3xl">
                                {summaryState.metrics.requestsCount ?? '--'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Loan requests awaiting review
                            </p>
                        </CardContent>
                    </Card>
                    <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardDescription>WIBS Desktop sync</CardDescription>
                            <CardTitle className="text-2xl">
                                {summaryState.metrics.lastSync ?? '--'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                System of record processing
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {reportingMetrics ? (
                    <>
                        <Separator />
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 className="text-lg font-semibold">Loan workflow reporting</h2>
                                <p className="text-sm text-muted-foreground">Application volume and processing metrics.</p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {(['today', 'week', 'month', 'all'] as DateRangeToggle[]).map((t) => (
                                    <Button
                                        key={t}
                                        size="sm"
                                        variant="outline"
                                        onClick={() => applyDateRangeToggle(t)}
                                    >
                                        {t === 'today' ? 'Today' : t === 'week' ? 'This week' : t === 'month' ? 'This month' : 'All time'}
                                    </Button>
                                ))}
                                {canExport ? (
                                    <Button size="sm" variant="secondary" asChild>
                                        <Link href={reportsIndex().url}>View reports</Link>
                                    </Button>
                                ) : null}
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                                <CardHeader>
                                    <CardDescription>Pending applications</CardDescription>
                                    <CardTitle className="text-3xl">{reportingMetrics.pending_count}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">In-progress loan requests</p>
                                </CardContent>
                            </Card>
                            <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                                <CardHeader>
                                    <CardDescription>Approved</CardDescription>
                                    <CardTitle className="text-3xl">{reportingMetrics.approved_count}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">
                                        Approval rate: {reportingMetrics.approval_rate}%
                                    </p>
                                </CardContent>
                            </Card>
                            <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                                <CardHeader>
                                    <CardDescription>Avg processing days</CardDescription>
                                    <CardTitle className="text-3xl">
                                        {reportingMetrics.average_processing_days ?? '--'}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">Days from submission to decision</p>
                                </CardContent>
                            </Card>
                            <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                                <CardHeader>
                                    <CardDescription>Portfolio total</CardDescription>
                                    <CardTitle className="text-2xl">{formatCurrency(reportingMetrics.portfolio_total)}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">Sum of approved loan amounts</p>
                                </CardContent>
                            </Card>
                        </div>

                        {applicationVolume && Object.keys(applicationVolume).length > 0 ? (
                            <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                                <CardHeader>
                                    <CardTitle>Application volume</CardTitle>
                                    <CardDescription>Daily loan application submissions.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ApplicationVolumeChart data={applicationVolume} />
                                </CardContent>
                            </Card>
                        ) : null}

                        {staffPerformance && staffPerformance.length > 0 ? (
                            <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                                <CardHeader>
                                    <CardTitle>Staff workload</CardTitle>
                                    <CardDescription>Per-processor assignment and decision summary.</CardDescription>
                                </CardHeader>
                                <CardContent className="px-0">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="px-6">Processor</TableHead>
                                                <TableHead className="px-6">Assigned</TableHead>
                                                <TableHead className="px-6">Approved</TableHead>
                                                <TableHead className="px-6">Rejected</TableHead>
                                                <TableHead className="px-6">Avg days</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {staffPerformance.map((row) => (
                                                <TableRow key={row.processor_id}>
                                                    <TableCell className="px-6 font-medium">{row.name}</TableCell>
                                                    <TableCell className="px-6">{row.assigned}</TableCell>
                                                    <TableCell className="px-6">{row.approved}</TableCell>
                                                    <TableCell className="px-6">{row.rejected}</TableCell>
                                                    <TableCell className="px-6">{row.avg_days ?? '--'}</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </CardContent>
                            </Card>
                        ) : null}
                        <Separator />
                    </>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card
                        id="requests"
                        className="rounded-2xl border-border/40 bg-card/70 shadow-sm"
                    >
                        <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle>Recent requests</CardTitle>
                                <CardDescription>
                                    Latest submissions (read-only).
                                </CardDescription>
                            </div>
                            <Button asChild size="sm" variant="outline">
                                <Link href={requestsIndex().url}>See more</Link>
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
                                <Table>
                                    <TableHeader className="border-b border-border/60 text-muted-foreground">
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
                                                        {request.reference ?? '--'}
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
                            )}
                        </CardContent>
                    </Card>

                    <Card
                        id="member-lookup"
                        className="rounded-2xl border-border/40 bg-card/70 shadow-sm"
                    >
                        <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle>Member lookup</CardTitle>
                                <CardDescription>
                                    Search by account no, username, email, or
                                    member name.
                                </CardDescription>
                            </div>
                            <Button asChild size="sm" variant="outline">
                                <Link href={membersIndex().url}>
                                    View all members
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Input
                                value={memberSearch}
                                placeholder="Search members"
                                onChange={(event) =>
                                    setMemberSearch(event.target.value)
                                }
                            />
                            {lookupError ? (
                                <Alert variant="destructive">
                                    <AlertTitle>Lookup failed</AlertTitle>
                                    <AlertDescription>
                                        {lookupError}
                                    </AlertDescription>
                                </Alert>
                            ) : null}
                            {lookupSkeleton ? (
                                <>
                                    <div
                                        className="space-y-3 md:hidden"
                                        aria-busy="true"
                                    >
                                        {Array.from({ length: 3 }).map(
                                            (_, index) => (
                                                <MemberListCardSkeleton
                                                    key={`lookup-card-skeleton-${index}`}
                                                    metaRows={1}
                                                />
                                            ),
                                        )}
                                    </div>
                                    <div
                                        className="hidden md:block"
                                        aria-busy="true"
                                    >
                                        <TableSkeleton
                                            columns={
                                                memberLookupSkeletonColumns
                                            }
                                            rows={5}
                                            className="rounded-xl border border-border/40 bg-card/60"
                                        />
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div className="space-y-3 md:hidden">
                                        {lookupRows.length === 0 ? (
                                            <div className="rounded-xl border border-border/30 bg-muted/30 px-4 py-6 text-center text-sm text-muted-foreground">
                                                {lookupEmptyMessage}
                                            </div>
                                        ) : (
                                            lookupRows.map((member) => (
                                                <MobileMemberLookupCard
                                                    key={member.member_id}
                                                    member={member}
                                                />
                                            ))
                                        )}
                                    </div>
                                    <div className="hidden rounded-xl border border-border/40 bg-card/60 md:block">
                                        <Table>
                                            <TableHeader className="text-muted-foreground">
                                                <TableRow>
                                                    <TableHead>
                                                        Member
                                                    </TableHead>
                                                    <TableHead>
                                                        Account No
                                                    </TableHead>
                                                    <TableHead>
                                                        Registration
                                                    </TableHead>
                                                    <TableHead className="text-right">
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
                                                            <TableCell>
                                                                <div className="flex flex-col">
                                                                    <span className="font-medium">
                                                                        {
                                                                            member.member_name
                                                                        }
                                                                    </span>
                                                                    {member.username &&
                                                                    member.member_name !==
                                                                        member.username ? (
                                                                        <span className="text-xs text-muted-foreground">
                                                                            {
                                                                                member.username
                                                                            }
                                                                        </span>
                                                                    ) : null}
                                                                </div>
                                                            </TableCell>
                                                            <TableCell>
                                                                {member.acctno ??
                                                                    '--'}
                                                            </TableCell>
                                                            <TableCell>
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
                                                            <TableCell className="text-right">
                                                                <Button
                                                                    asChild
                                                                    size="sm"
                                                                    variant="outline"
                                                                >
                                                                    <Link
                                                                        href={
                                                                            showMember(
                                                                                member.member_id,
                                                                            )
                                                                                .url
                                                                        }
                                                                    >
                                                                        Open
                                                                        profile
                                                                    </Link>
                                                                </Button>
                                                            </TableCell>
                                                        </TableRow>
                                                    ))
                                                )}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </>
                            )}
                            {lookupLoading ? (
                                <p className="text-xs text-muted-foreground">
                                    Updating member lookup...
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>
                </div>
            </PageShell>
        </AppLayout>
    );
}
