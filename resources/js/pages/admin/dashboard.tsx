import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { MemberListCardSkeleton } from '@/components/member-list-card-skeleton';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { TableSkeleton, type TableSkeletonColumn } from '@/components/ui/table-skeleton';
import { useAdminDashboard } from '@/hooks/admin/use-admin-dashboard';
import { useMembers } from '@/hooks/admin/use-members';
import { useUpdateMemberStatus } from '@/hooks/admin/use-update-member-status';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import { show as showMember } from '@/routes/admin/members';
import { index as requestsIndex } from '@/routes/admin/requests';
import { pending } from '@/routes/admin/users';
import { index as membersIndex } from '@/routes/admin/watchlist';
import type { BreadcrumbItem } from '@/types';
import type {
    DashboardSummary,
    MemberStatusValue,
    MemberSummary,
} from '@/types/admin';

type Props = {
    summary: DashboardSummary;
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

const statusLabel = (status?: MemberStatusValue | null): string => {
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

const pendingApprovalsSkeletonColumns: TableSkeletonColumn[] = [
    { headerClassName: 'w-28', cellClassName: 'w-32' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-32', cellClassName: 'w-36' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-16', cellClassName: 'w-16' },
    { headerClassName: 'w-12', cellClassName: 'h-8 w-20', align: 'right' },
];

const requestPreviewSkeletonColumns: TableSkeletonColumn[] = [
    { headerClassName: 'w-28', cellClassName: 'w-32' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
];

const memberLookupSkeletonColumns: TableSkeletonColumn[] = [
    { headerClassName: 'w-28', cellClassName: 'w-32' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-16', cellClassName: 'w-16' },
    { headerClassName: 'w-12', cellClassName: 'h-8 w-24', align: 'right' },
];

const MobilePendingApprovalCard = ({
    user,
    isProcessing,
    onApprove,
}: {
    user: DashboardSummary['pendingApprovals'][number];
    isProcessing: boolean;
    onApprove: (userId: number) => void;
}) => (
    <div className="rounded-lg border border-border bg-card p-4">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-1">
                <p className="text-sm font-semibold">{user.member_name}</p>
                {user.member_name !== user.username ? (
                    <p className="text-xs text-muted-foreground">
                        {user.username}
                    </p>
                ) : null}
            </div>
            <Badge variant={statusVariant(user.status)}>
                {statusLabel(user.status)}
            </Badge>
        </div>
        <div className="mt-3 space-y-2 rounded-md border border-border/60 bg-muted/40 p-3 text-xs">
            <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Account No</span>
                <span className="text-sm font-medium">
                    {user.acctno ?? '--'}
                </span>
            </div>
            <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Email</span>
                <span className="text-sm font-medium">{user.email}</span>
            </div>
            <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Created</span>
                <span className="text-sm font-medium">
                    {formatDate(user.created_at)}
                </span>
            </div>
        </div>
        <div className="mt-3">
            <Button
                type="button"
                size="sm"
                className="w-full sm:w-auto"
                disabled={isProcessing || user.status !== 'pending'}
                onClick={() => onApprove(user.user_id)}
            >
                Approve
            </Button>
        </div>
    </div>
);

const MobileMemberLookupCard = ({ member }: { member: MemberSummary }) => (
    <div className="rounded-lg border border-border bg-card p-4">
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
        </div>
        <div className="mt-3">
            <Button
                asChild
                size="sm"
                variant="outline"
                className="w-full sm:w-auto"
            >
                <Link href={showMember(member.user_id).url}>
                    Open profile
                </Link>
            </Button>
        </div>
    </div>
);

export default function AdminDashboard({ summary }: Props) {
    const {
        summary: summaryState,
        refresh,
        setSummary,
        loading,
        error,
    } = useAdminDashboard(summary);
    const [memberSearch, setMemberSearch] = useState('');

    useEffect(() => {
        void refresh();
    }, [refresh]);

    const { updateStatus, processingIds } = useUpdateMemberStatus({
        onUpdated: (member, action) => {
            if (action !== 'approve') {
                return;
            }

            setSummary({
                ...summaryState,
                metrics: {
                    ...summaryState.metrics,
                    pendingCount: Math.max(
                        0,
                        summaryState.metrics.pendingCount - 1,
                    ),
                    activeCount: summaryState.metrics.activeCount + 1,
                },
                pendingApprovals: summaryState.pendingApprovals.filter(
                    (user) => user.user_id !== member.user_id,
                ),
            });
            void refresh();
        },
    });

    const { items: lookupRows, loading: lookupLoading, error: lookupError } =
        useMembers({
            search: memberSearch,
            status: 'all',
            sort: 'newest',
            page: 1,
            perPage: 5,
        });

    const pendingRows = summaryState.pendingApprovals;
    const requestsPreview = summaryState.requests;
    const lookupEmptyMessage =
        memberSearch.trim() === ''
            ? 'Search by account number, username, email, or member name.'
            : 'No matching members found.';
    const pendingSkeleton = loading && pendingRows.length === 0;
    const requestsSkeleton = loading && requestsPreview.length === 0;
    const lookupSkeleton = lookupLoading && lookupRows.length === 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold">
                            Admin Dashboard
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Member approvals, requests, and account overview.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild size="sm">
                            <a href="#pending-approvals">Pending approvals</a>
                        </Button>
                        <Button asChild size="sm" variant="secondary">
                            <a href="#member-lookup">Member lookup</a>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <a href="#requests">Recent requests</a>
                        </Button>
                        <Button asChild size="sm" variant="ghost">
                            <Link href={membersIndex().url}>All members</Link>
                        </Button>
                    </div>
                </div>

                {error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Dashboard sync failed</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <Card>
                        <CardHeader>
                            <CardDescription>
                                Pending member approvals
                            </CardDescription>
                            <CardTitle className="text-3xl">
                                {summaryState.metrics.pendingCount}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Awaiting review and activation
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Active members</CardDescription>
                            <CardTitle className="text-3xl">
                                {summaryState.metrics.activeCount}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Approved portal access
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Total portal users</CardDescription>
                            <CardTitle className="text-3xl">
                                {summaryState.metrics.totalCount}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Registered portal logins
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
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
                    <Card>
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

                <Card id="pending-approvals">
                    <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle>Pending member approvals</CardTitle>
                            <CardDescription>
                                Latest 5 registrations awaiting activation.
                            </CardDescription>
                        </div>
                        <Button asChild size="sm" variant="outline">
                            <Link href={pending().url}>See more</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="px-0">
                        {pendingSkeleton ? (
                            <>
                                <div
                                    className="space-y-3 px-6 md:hidden"
                                    aria-busy="true"
                                >
                                    {Array.from({ length: 3 }).map(
                                        (_, index) => (
                                            <MemberListCardSkeleton
                                                key={`pending-card-skeleton-${index}`}
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
                                            pendingApprovalsSkeletonColumns
                                        }
                                        rows={5}
                                        tableClassName="[&_td]:px-6 [&_th]:px-6"
                                    />
                                </div>
                            </>
                        ) : (
                            <>
                                <div className="space-y-3 px-6 md:hidden">
                                    {pendingRows.length === 0 ? (
                                        <div className="rounded-md border border-border bg-muted/40 px-4 py-6 text-center text-sm text-muted-foreground">
                                            No pending approvals.
                                        </div>
                                    ) : (
                                        pendingRows.map((user) => (
                                            <MobilePendingApprovalCard
                                                key={user.user_id}
                                                user={user}
                                                isProcessing={
                                                    processingIds[
                                                        user.user_id
                                                    ] ?? false
                                                }
                                                onApprove={(userId) =>
                                                    updateStatus(
                                                        userId,
                                                        'approve',
                                                    )
                                                }
                                            />
                                        ))
                                    )}
                                </div>
                                <div className="hidden md:block">
                                    <Table>
                                        <TableHeader className="border-b border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border">
                                            <TableRow>
                                                <TableHead className="px-6">
                                                    Member
                                                </TableHead>
                                                <TableHead className="px-6">
                                                    Account No
                                                </TableHead>
                                                <TableHead className="px-6">
                                                    Email
                                                </TableHead>
                                                <TableHead className="px-6">
                                                    Created
                                                </TableHead>
                                                <TableHead className="px-6">
                                                    Status
                                                </TableHead>
                                                <TableHead className="px-6 text-right">
                                                    Action
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {pendingRows.length === 0 ? (
                                                <TableRow>
                                                    <TableCell
                                                        className="px-6 py-6 text-center text-sm text-muted-foreground"
                                                        colSpan={6}
                                                    >
                                                        No pending approvals.
                                                    </TableCell>
                                                </TableRow>
                                            ) : (
                                                pendingRows.map((user) => (
                                                    <TableRow
                                                        key={user.user_id}
                                                        className="border-sidebar-border/70 dark:border-sidebar-border"
                                                    >
                                                        <TableCell className="px-6">
                                                            <div className="flex flex-col">
                                                                <span className="font-medium">
                                                                    {
                                                                        user.member_name
                                                                    }
                                                                </span>
                                                                {user.member_name !==
                                                                user.username ? (
                                                                    <span className="text-xs text-muted-foreground">
                                                                        {
                                                                            user.username
                                                                        }
                                                                    </span>
                                                                ) : null}
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="px-6">
                                                            {user.acctno ??
                                                                '--'}
                                                        </TableCell>
                                                        <TableCell className="px-6">
                                                            {user.email}
                                                        </TableCell>
                                                        <TableCell className="px-6">
                                                            {formatDate(
                                                                user.created_at,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="px-6">
                                                            <Badge
                                                                variant={statusVariant(
                                                                    user.status,
                                                                )}
                                                            >
                                                                {statusLabel(
                                                                    user.status,
                                                                )}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell className="px-6 text-right">
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                disabled={
                                                                    processingIds[
                                                                        user.user_id
                                                                    ] ||
                                                                    user.status !==
                                                                        'pending'
                                                                }
                                                                onClick={() =>
                                                                    updateStatus(
                                                                        user.user_id,
                                                                        'approve',
                                                                    )
                                                                }
                                                            >
                                                                Approve
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
                        {loading ? (
                            <p className="px-6 pt-3 text-xs text-muted-foreground">
                                Refreshing summary...
                            </p>
                        ) : null}
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card id="requests">
                        <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle>Recent requests</CardTitle>
                                <CardDescription>
                                    Latest submissions (read-only).
                                </CardDescription>
                            </div>
                            <Button asChild size="sm" variant="outline">
                                <Link href={requestsIndex().url}>
                                    See more
                                </Link>
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
                                                        <LoanRequestStatusBadge
                                                            status={request.status}
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

                    <Card id="member-lookup">
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
                                            columns={memberLookupSkeletonColumns}
                                            rows={5}
                                            className="rounded-md border"
                                        />
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div className="space-y-3 md:hidden">
                                        {lookupRows.length === 0 ? (
                                            <div className="rounded-md border border-border bg-muted/40 px-4 py-6 text-center text-sm text-muted-foreground">
                                                {lookupEmptyMessage}
                                            </div>
                                        ) : (
                                            lookupRows.map((member) => (
                                                <MobileMemberLookupCard
                                                    key={member.user_id}
                                                    member={member}
                                                />
                                            ))
                                        )}
                                    </div>
                                    <div className="hidden rounded-md border md:block">
                                        <Table>
                                            <TableHeader className="text-muted-foreground">
                                                <TableRow>
                                                    <TableHead>Member</TableHead>
                                                    <TableHead>
                                                        Account No
                                                    </TableHead>
                                                    <TableHead>Status</TableHead>
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
                                                            key={
                                                                member.user_id
                                                            }
                                                        >
                                                            <TableCell>
                                                                <div className="flex flex-col">
                                                                    <span className="font-medium">
                                                                        {
                                                                            member.member_name
                                                                        }
                                                                    </span>
                                                                    {member.member_name !==
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
                                                                    variant={statusVariant(
                                                                        member.status,
                                                                    )}
                                                                >
                                                                    {statusLabel(
                                                                        member.status,
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
                                                                                member.user_id,
                                                                            )
                                                                                .url
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
            </div>
        </AppLayout>
    );
}
