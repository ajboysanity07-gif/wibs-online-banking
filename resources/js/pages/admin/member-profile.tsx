import { Head, Link } from '@inertiajs/react';
import { MemberAccountsSummarySection } from '@/components/member-accounts-summary-section';
import { MemberRecentAccountActionsCard } from '@/components/member-recent-account-actions-card';
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
    MemberAccountsProvider,
    useMemberAccounts,
} from '@/hooks/admin/use-member-accounts';
import { useMemberDetails } from '@/hooks/admin/use-member-details';
import { useUpdateMemberStatus } from '@/hooks/admin/use-update-member-status';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatDateTime } from '@/lib/formatters';
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

function LoansAndSavingsSummarySection() {
    const {
        memberId,
        acctno,
        summary,
        summaryLoading,
        summaryError,
        refreshSummary,
    } = useMemberAccounts();

    const loansHref = memberId ? memberLoans(memberId).url : undefined;
    const savingsHref = memberId ? memberSavings(memberId).url : undefined;
    const actionDisabled = !acctno || !memberId;

    return (
        <MemberAccountsSummarySection
            acctno={acctno}
            summary={summary}
            loading={summaryLoading}
            error={summaryError}
            onRetry={() => void refreshSummary()}
            loansAction={{
                label: 'View all',
                href: loansHref,
                disabled: actionDisabled,
            }}
            savingsAction={{
                label: 'View all',
                href: savingsHref,
                disabled: actionDisabled,
            }}
        />
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

    return (
        <MemberRecentAccountActionsCard
            acctno={acctno}
            actions={actions}
            meta={actionsMeta}
            loading={actionsLoading}
            error={actionsError}
            onRetry={() => void refreshActions()}
            onPageChange={setActionsPage}
        />
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
            title: 'Members',
            href: membersIndex().url,
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
