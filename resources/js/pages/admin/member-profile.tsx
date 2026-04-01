import { Head, Link } from '@inertiajs/react';
import { MemberAccountsSummarySection } from '@/features/member-accounts/components/member-accounts-summary-section';
import { MemberProfileDetailsCard } from '@/components/member-profile-details-card';
import { MemberProfileHeader } from '@/components/member-profile-header';
import { MemberRecentAccountActionsCard } from '@/features/member-accounts/components/member-recent-account-actions-card';
import { MemberStatusCard } from '@/components/member-status-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PageShell } from '@/components/page-shell';
import {
    MemberAccountsProvider,
    useMemberAccounts,
} from '@/hooks/admin/use-member-accounts';
import { useMemberDetails } from '@/hooks/admin/use-member-details';
import { useUpdateMemberStatus } from '@/hooks/admin/use-update-member-status';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatDateTime } from '@/lib/formatters';
import {
    getMemberStatusLabel,
    getMemberStatusVariant,
} from '@/lib/member-status';
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

function LoansAndLoanSecuritySummarySection() {
    const {
        memberId,
        acctno,
        summary,
        summaryLoading,
        summaryError,
        refreshSummary,
    } = useMemberAccounts();

    const loansHref = memberId ? memberLoans(memberId).url : undefined;
    const loanSecurityHref = memberId ? memberSavings(memberId).url : undefined;
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
            loanSecurityAction={{
                label: 'View all',
                href: loanSecurityHref,
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
    const { member, loading, error, setMember } = useMemberDetails(
        initialMember.user_id,
        seededMember,
    );
    const currentMember = member ?? seededMember;
    const memberName = currentMember.member_name ?? currentMember.username;
    const getInitials = useInitials();

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
    const statusLabel = getMemberStatusLabel(currentMember.status);
    const statusVariant = getMemberStatusVariant(currentMember.status);

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
            <PageShell size="wide">
                <MemberProfileHeader
                    name={memberName}
                    subtitle="Account status and profile details."
                    avatarUrl={currentMember.avatar_url}
                    avatarFallback={getInitials(memberName) || 'U'}
                    statusBadge={
                        <Badge
                            variant={statusVariant}
                            className="text-[0.65rem] uppercase tracking-[0.2em]"
                        >
                            {statusLabel}
                        </Badge>
                    }
                    meta={
                        <>
                            <Badge variant="outline" className="bg-background/60">
                                Account No: {currentMember.acctno ?? '--'}
                            </Badge>
                            <Badge variant="outline" className="bg-background/60">
                                Username: {currentMember.username}
                            </Badge>
                        </>
                    }
                    accessory={
                        <>
                            <Button asChild variant="outline" size="sm">
                                <Link href={membersIndex().url}>
                                    All members
                                </Link>
                            </Button>
                            <Button asChild variant="ghost" size="sm">
                                <Link href={dashboard().url}>
                                    Back to dashboard
                                </Link>
                            </Button>
                        </>
                    }
                />

                {error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Unable to load member</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <MemberProfileDetailsCard
                            title="Member details"
                            description="Portal profile information and contact details."
                            items={[
                                { label: 'Member name', value: memberName },
                                {
                                    label: 'Username',
                                    value: currentMember.username,
                                },
                                { label: 'Email', value: currentMember.email },
                                {
                                    label: 'Phone',
                                    value: currentMember.phoneno ?? '--',
                                },
                                {
                                    label: 'Account No',
                                    value: currentMember.acctno ?? '--',
                                },
                                {
                                    label: 'Created',
                                    value: formatDate(currentMember.created_at),
                                },
                                {
                                    label: 'Reviewed by',
                                    value:
                                        currentMember.reviewed_by?.name ?? '--',
                                },
                                {
                                    label: 'Reviewed at',
                                    value: formatDateTime(
                                        currentMember.reviewed_at,
                                    ),
                                },
                            ]}
                        />
                    </div>
                    <MemberStatusCard
                        statusLabel={statusLabel}
                        statusVariant={statusVariant}
                        actions={
                            <>
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
                                        Activate
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
                            </>
                        }
                        helper={
                            loading ? 'Refreshing member status...' : undefined
                        }
                    />
                </div>

                <MemberAccountsProvider
                    memberId={currentMember.user_id}
                    acctno={currentMember.acctno}
                    initialSummary={accountsSummary}
                    initialActions={recentAccountActions}
                >
                    <LoansAndLoanSecuritySummarySection />
                    <RecentAccountActionsCard />
                </MemberAccountsProvider>
            </PageShell>
        </AppLayout>
    );
}
