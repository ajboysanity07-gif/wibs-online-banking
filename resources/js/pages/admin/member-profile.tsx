import { Head, Link } from '@inertiajs/react';
import { Banknote, PiggyBank } from 'lucide-react';
import { useMemo } from 'react';
import { MemberAccountSummaryCard } from '@/components/member-account-summary-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DataTablePagination,
    DataTablePaginationSkeleton,
} from '@/components/ui/data-table-pagination';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { TableSkeleton } from '@/components/ui/table-skeleton';
import {
    MemberAccountsProvider,
    useMemberAccounts,
} from '@/hooks/admin/use-member-accounts';
import { useMemberDetails } from '@/hooks/admin/use-member-details';
import { useUpdateMemberStatus } from '@/hooks/admin/use-update-member-status';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDate, formatDateTime } from '@/lib/formatters';
import { dashboard } from '@/routes/admin';
import {
    loans as memberLoans,
    savings as memberSavings,
    show as showMember,
} from '@/routes/admin/members';
import { index as membersIndex } from '@/routes/admin/watchlist';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberAccountActionsResponse,
    MemberAccountsSummary,
    MemberDetail,
    MemberRecentAccountAction,
    MemberRecentAccountActionSource,
    MemberStatusValue,
} from '@/types/admin';

type MemberSeed = {
    user_id: number;
    username: string;
    email: string;
    acctno: string | null;
    status: MemberStatusValue | null;
    created_at: string | null;
};

type Props = {
    member: MemberSeed;
    accountsSummary: MemberAccountsSummary;
    recentAccountActions: MemberAccountActionsResponse;
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

const getInitials = (value: string): string => {
    const parts = value.trim().split(/\s+/).slice(0, 2);

    if (parts.length === 0) {
        return 'U';
    }

    return parts.map((part) => part[0]?.toUpperCase() ?? '').join('');
};

const sourceVariant = (source?: MemberRecentAccountActionSource | null) => {
    if (source === 'LOAN') {
        return 'default';
    }

    if (source === 'SAV') {
        return 'secondary';
    }

    return 'outline';
};

const accountActionsSkeletonColumns = [
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-16', cellClassName: 'w-20' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-16', cellClassName: 'w-16' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
];

const MobileAccountActionSkeleton = () => (
    <div className="rounded-lg border border-border bg-card p-4">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-2">
                <Skeleton className="h-4 w-24" />
                <Skeleton className="h-3 w-20" />
            </div>
            <Skeleton className="h-5 w-12" />
        </div>
        <div className="mt-3 space-y-2 rounded-md border border-border/60 bg-muted/40 p-3">
            {Array.from({ length: 3 }).map((_, index) => (
                <div
                    key={`action-meta-${index}`}
                    className="flex items-center justify-between"
                >
                    <Skeleton className="h-3 w-20" />
                    <Skeleton className="h-4 w-24" />
                </div>
            ))}
        </div>
        <div className="mt-3">
            <Skeleton className="h-3 w-24" />
        </div>
    </div>
);

const MobileAccountActionSkeletonList = ({ rows = 3 }: { rows?: number }) => (
    <div className="space-y-3">
        {Array.from({ length: rows }).map((_, index) => (
            <MobileAccountActionSkeleton
                key={`mobile-action-skeleton-${index}`}
            />
        ))}
    </div>
);

const MobileAccountActionCard = ({
    action,
}: {
    action: MemberRecentAccountAction;
}) => (
    <div className="rounded-lg border border-border bg-card p-4">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-1">
                <p className="text-sm font-semibold">
                    {action.ln_sv_number ?? '--'}
                </p>
                <p className="text-xs text-muted-foreground">
                    {action.transaction_type ?? '--'}
                </p>
            </div>
            <Badge variant={sourceVariant(action.source)}>
                {action.source ?? '--'}
            </Badge>
        </div>
        <div className="mt-3 rounded-md border border-border/60 bg-muted/40 p-3">
            <div className="flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Amount</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatCurrency(action.amount)}
                </span>
            </div>
            <div className="mt-2 flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Movement</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatCurrency(action.movement)}
                </span>
            </div>
            <div className="mt-2 flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Balance</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatCurrency(action.balance)}
                </span>
            </div>
        </div>
        <p className="mt-3 text-xs text-muted-foreground">
            Date: {formatDate(action.date_in)}
        </p>
    </div>
);

function LoansAndSavingsSummarySection() {
    const {
        memberId,
        acctno,
        summary,
        summaryLoading,
        summaryError,
        refreshSummary,
    } = useMemberAccounts();

    const loansHref = memberId ? memberLoans(memberId).url : '#';
    const savingsHref = memberId ? memberSavings(memberId).url : '#';
    const actionDisabled = !acctno || !memberId;

    return (
        <section className="space-y-4">
            <div className="space-y-1">
                <h2 className="text-xl font-semibold">Loans and Savings</h2>
                <p className="text-sm text-muted-foreground">
                    Quick snapshot of loan and savings activity.
                </p>
            </div>
            {!acctno ? (
                <Alert>
                    <AlertTitle>Account number missing</AlertTitle>
                    <AlertDescription>
                        Add an account number to view loan and savings details.
                    </AlertDescription>
                </Alert>
            ) : null}
            {summaryError ? (
                <Alert variant="destructive">
                    <AlertTitle>Unable to load summary</AlertTitle>
                    <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <span>{summaryError}</span>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => void refreshSummary()}
                        >
                            Retry
                        </Button>
                    </AlertDescription>
                </Alert>
            ) : null}
            <div className="grid gap-4 md:grid-cols-2">
                <MemberAccountSummaryCard
                    title="Loans"
                    subtitle="Loan portfolio snapshot"
                    primaryLabel="Total Outstanding Loan Balance"
                    primaryValue={formatCurrency(summary?.loanBalanceLeft)}
                    secondaryLabel="Last Loan Transaction"
                    secondaryValue={formatDate(
                        summary?.lastLoanTransactionDate,
                    )}
                    icon={Banknote}
                    accent="primary"
                    actionLabel="View all"
                    actionHref={loansHref}
                    actionDisabled={actionDisabled}
                    loading={summaryLoading}
                />
                <MemberAccountSummaryCard
                    title="Savings"
                    subtitle="Savings overview"
                    primaryLabel="Total Current Savings"
                    primaryValue={formatCurrency(
                        summary?.currentSavingsBalance,
                    )}
                    secondaryLabel="Last Savings Transaction"
                    secondaryValue={formatDate(
                        summary?.lastSavingsTransactionDate,
                    )}
                    icon={PiggyBank}
                    accent="accent"
                    actionLabel="View all"
                    actionHref={savingsHref}
                    actionDisabled={actionDisabled}
                    loading={summaryLoading}
                />
            </div>
        </section>
    );
}

function RecentAccountActionsCard() {
    const {
        acctno,
        actions,
        actionsMeta,
        actionsLoading,
        actionsError,
        setActionsPage,
        refreshActions,
    } = useMemberAccounts();

    const actionsEmpty = useMemo(() => actions.length === 0, [actions]);
    const showSkeleton = actionsLoading && actionsEmpty;

    return (
        <Card>
            <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle>Recent account actions</CardTitle>
                    <CardDescription>
                        Latest loan and savings movements.
                    </CardDescription>
                </div>
                {actionsLoading ? (
                    <span className="text-xs text-muted-foreground">
                        Updating...
                    </span>
                ) : null}
            </CardHeader>
            <CardContent className="space-y-4">
                {!acctno ? (
                    <Alert>
                        <AlertTitle>Account number missing</AlertTitle>
                        <AlertDescription>
                            Add an account number to view loan and savings
                            activity.
                        </AlertDescription>
                    </Alert>
                ) : null}
                {actionsError ? (
                    <Alert variant="destructive">
                        <AlertTitle>Unable to load account actions</AlertTitle>
                        <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <span>{actionsError}</span>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => void refreshActions()}
                            >
                                Retry
                            </Button>
                        </AlertDescription>
                    </Alert>
                ) : null}
                {showSkeleton ? (
                    <>
                        <div className="md:hidden" aria-busy="true">
                            <MobileAccountActionSkeletonList rows={3} />
                        </div>
                        <div className="hidden md:block" aria-busy="true">
                            <TableSkeleton
                                columns={accountActionsSkeletonColumns}
                                rows={actionsMeta.perPage}
                                className="rounded-md border"
                                tableClassName="min-w-215"
                            />
                        </div>
                    </>
                ) : (
                    <>
                        <div className="md:hidden">
                            {actionsEmpty ? (
                                <div className="rounded-md border border-border bg-muted/40 px-4 py-6 text-center text-sm text-muted-foreground">
                                    No account activity available yet.
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {actions.map((action, index) => (
                                        <MobileAccountActionCard
                                            key={
                                                action.ln_sv_number ??
                                                `action-${index}`
                                            }
                                            action={action}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                        <div className="hidden rounded-md border md:block">
                            <Table className="min-w-215">
                                <TableHeader className="text-muted-foreground">
                                    <TableRow>
                                        <TableHead>Number</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Source</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Movement</TableHead>
                                        <TableHead>Balance</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {actionsEmpty ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
                                                className="h-24 text-center text-sm text-muted-foreground"
                                            >
                                                No account activity available
                                                yet.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        actions.map((action, index) => (
                                            <TableRow
                                                key={
                                                    action.ln_sv_number ??
                                                    `action-${index}`
                                                }
                                            >
                                                <TableCell className="font-medium">
                                                    {action.ln_sv_number ??
                                                        '--'}
                                                </TableCell>
                                                <TableCell>
                                                    {formatDate(
                                                        action.date_in,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {action.transaction_type ??
                                                        '--'}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={sourceVariant(
                                                            action.source,
                                                        )}
                                                    >
                                                        {action.source ?? '--'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    {formatCurrency(
                                                        action.amount,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {formatCurrency(
                                                        action.movement,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {formatCurrency(
                                                        action.balance,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </>
                )}
                {!actionsError ? (
                    showSkeleton ? (
                        <DataTablePaginationSkeleton />
                    ) : (
                        <DataTablePagination
                            page={actionsMeta.page}
                            perPage={actionsMeta.perPage}
                            total={actionsMeta.total}
                            onPageChange={setActionsPage}
                        />
                    )
                ) : null}
            </CardContent>
        </Card>
    );
}

export default function MemberProfile({
    member: initialMember,
    accountsSummary,
    recentAccountActions,
}: Props) {
    const seededMember: MemberDetail = {
        user_id: initialMember.user_id,
        username: initialMember.username,
        email: initialMember.email,
        acctno: initialMember.acctno,
        status: initialMember.status,
        created_at: initialMember.created_at,
        member_name: initialMember.username,
        phoneno: null,
        reviewed_at: null,
        reviewed_by: null,
        avatar_url: null,
    };
    const {
        member,
        loading,
        error,
        setMember,
    } = useMemberDetails(initialMember.user_id, seededMember);
    const currentMember = member ?? seededMember;
    const memberName = currentMember.member_name ?? currentMember.username;

    const { updateStatus, processingIds } = useUpdateMemberStatus({
        onUpdated: (updated) => {
            setMember(updated);
        },
    });

    const isProcessing = processingIds[currentMember.user_id];
    const canApprove =
        currentMember.status === 'pending' || currentMember.status === null;
    const canSuspend = currentMember.status === 'active';
    const canReactivate = currentMember.status === 'suspended';

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Admin Dashboard',
            href: dashboard().url,
        },
        {
            title: 'Member profile',
            href: showMember(initialMember.user_id).url,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Member profile" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <Avatar className="size-12">
                            <AvatarImage src={currentMember.avatar_url ?? undefined} />
                            <AvatarFallback>
                                {getInitials(memberName)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="space-y-1">
                            <h1 className="text-2xl font-semibold">
                                {memberName}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Account status and profile details.
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href={membersIndex().url}>All members</Link>
                        </Button>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={dashboard().url}>
                                Back to dashboard
                            </Link>
                        </Button>
                    </div>
                </div>

                {error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Unable to load member</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Member details</CardTitle>
                            <CardDescription>
                                Portal profile information and contact details.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Member name
                                </p>
                                <p className="text-sm font-medium">
                                    {memberName}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Username
                                </p>
                                <p className="text-sm font-medium">
                                    {currentMember.username}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Email
                                </p>
                                <p className="text-sm font-medium">
                                    {currentMember.email}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Phone
                                </p>
                                <p className="text-sm font-medium">
                                    {currentMember.phoneno ?? '--'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Account No
                                </p>
                                <p className="text-sm font-medium">
                                    {currentMember.acctno ?? '--'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Created
                                </p>
                                <p className="text-sm font-medium">
                                    {formatDate(currentMember.created_at)}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Reviewed by
                                </p>
                                <p className="text-sm font-medium">
                                    {currentMember.reviewed_by?.name ?? '--'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Reviewed at
                                </p>
                                <p className="text-sm font-medium">
                                    {formatDateTime(
                                        currentMember.reviewed_at,
                                    )}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Account status</CardTitle>
                            <CardDescription>
                                Manage portal access state.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">
                                    Current status
                                </span>
                                <Badge
                                    variant={statusVariant(
                                        currentMember.status,
                                    )}
                                >
                                    {statusLabel(currentMember.status)}
                                </Badge>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {canApprove ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={isProcessing}
                                        onClick={() =>
                                            updateStatus(
                                                currentMember.user_id,
                                                'approve',
                                            )
                                        }
                                    >
                                        Approve
                                    </Button>
                                ) : null}
                                {canSuspend ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        disabled={isProcessing}
                                        onClick={() =>
                                            updateStatus(
                                                currentMember.user_id,
                                                'suspend',
                                            )
                                        }
                                    >
                                        Suspend
                                    </Button>
                                ) : null}
                                {canReactivate ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="secondary"
                                        disabled={isProcessing}
                                        onClick={() =>
                                            updateStatus(
                                                currentMember.user_id,
                                                'reactivate',
                                            )
                                        }
                                    >
                                        Reactivate
                                    </Button>
                                ) : null}
                            </div>
                            {loading ? (
                                <p className="text-xs text-muted-foreground">
                                    Refreshing member status...
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>
                </div>

                <MemberAccountsProvider
                    memberId={currentMember.user_id}
                    acctno={currentMember.acctno}
                    initialSummary={accountsSummary}
                    initialActions={recentAccountActions}
                >
                    <LoansAndSavingsSummarySection />
                    <RecentAccountActionsCard />
                </MemberAccountsProvider>
            </div>
        </AppLayout>
    );
}

