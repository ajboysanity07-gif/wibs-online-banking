import { Head, Link } from '@inertiajs/react';
import { MemberProfileDetailsCard } from '@/components/member-profile-details-card';
import { MemberProfileHeader } from '@/components/member-profile-header';
import { MemberStatusCard } from '@/components/member-status-card';
import { PageShell } from '@/components/page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { MemberAccountsSummarySection } from '@/features/member-accounts/components/member-accounts-summary-section';
import { MemberRecentAccountActionsCard } from '@/features/member-accounts/components/member-recent-account-actions-card';
import {
    MemberAccountsProvider,
    useMemberAccounts,
} from '@/hooks/admin/use-member-accounts';
import { useMemberDetails } from '@/hooks/admin/use-member-details';
import { useUpdateMemberStatus } from '@/hooks/admin/use-update-member-status';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/formatters';
import {
    getMemberStatusLabel,
    getMemberStatusVariant,
    getRegistrationStatusLabel,
    getRegistrationStatusVariant,
} from '@/lib/member-status';
import { dashboard } from '@/routes/admin';
import {
    loanPayments,
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
} from '@/types/admin';

type Props = {
    member: MemberDetail;
    accountsSummary?: MemberAccountsSummary | null;
    recentAccountActions?: MemberAccountActionsResponse | null;
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
        memberId,
        acctno,
        actions,
        actionsMeta,
        actionsLoading,
        actionsError,
        setActionsPage,
        refreshActions,
    } = useMemberAccounts();

    const resolveActionHref = (action: MemberRecentAccountAction) => {
        if (
            !memberId ||
            action.source !== 'LOAN' ||
            action.number === null
        ) {
            return null;
        }

        return loanPayments({
            user: memberId,
            loanNumber: action.number,
        }).url;
    };

    return (
        <MemberRecentAccountActionsCard
            acctno={acctno}
            actions={actions}
            meta={actionsMeta}
            loading={actionsLoading}
            error={actionsError}
            onRetry={() => void refreshActions()}
            onPageChange={setActionsPage}
            resolveActionHref={resolveActionHref}
        />
    );
}

export default function MemberProfile({
    member: initialMember,
    accountsSummary = null,
    recentAccountActions = null,
}: Props) {
    const { member, loading, error, setMember } = useMemberDetails(
        initialMember.member_id,
        initialMember,
    );
    const currentMember = member ?? initialMember;
    const memberName =
        currentMember.member_name ??
        currentMember.username ??
        currentMember.email ??
        'Member';
    const getInitials = useInitials();

    const { updateStatus, processingIds } = useUpdateMemberStatus({
        onUpdated: (updated) => {
            setMember(updated);
        },
    });

    const isProcessing =
        currentMember.user_id !== null
            ? processingIds[currentMember.user_id]
            : false;
    const canSuspend =
        currentMember.user_id !== null &&
        currentMember.portal_status === 'active';
    const canReactivate =
        currentMember.user_id !== null &&
        currentMember.portal_status === 'suspended';
    const statusLabel = getMemberStatusLabel(currentMember.portal_status);
    const statusVariant = getMemberStatusVariant(currentMember.portal_status);
    const registrationLabel = getRegistrationStatusLabel(
        currentMember.registration_status,
    );
    const registrationVariant = getRegistrationStatusVariant(
        currentMember.registration_status,
    );

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Members',
            href: membersIndex().url,
        },
        {
            title: 'Member profile',
            href: showMember(initialMember.member_id).url,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Member profile" />
            <PageShell size="wide">
                <MemberProfileHeader
                    name={memberName}
                    subtitle="Profile details and portal access."
                    avatarUrl={currentMember.avatar_url}
                    avatarFallback={getInitials(memberName) || 'U'}
                    statusBadge={
                        <Badge
                            variant={registrationVariant}
                            className="text-[0.65rem] uppercase tracking-[0.2em]"
                        >
                            {registrationLabel}
                        </Badge>
                    }
                    meta={
                        <>
                            <Badge variant="outline" className="bg-background/60">
                                Account No: {currentMember.acctno ?? '--'}
                            </Badge>
                            <Badge variant="outline" className="bg-background/60">
                                Portal username:{' '}
                                {currentMember.username ?? '--'}
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
                                    label: 'Portal username',
                                    value: currentMember.username ?? '--',
                                },
                                {
                                    label: 'Email',
                                    value: currentMember.email ?? '--',
                                },
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
                            ]}
                        />
                    </div>
                    <MemberStatusCard
                        title="Portal access"
                        description="Suspend or restore portal access for this member."
                        statusLabel={statusLabel}
                        statusVariant={statusVariant}
                        actions={
                            <>
                                {canSuspend ? (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="destructive"
                                        disabled={isProcessing}
                                        onClick={() =>
                                            updateStatus(
                                                currentMember.user_id as number,
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
                                                currentMember.user_id as number,
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
                            loading
                                ? 'Refreshing member status...'
                                : currentMember.registration_status ===
                                      'unregistered'
                                  ? 'This member does not have a portal login yet.'
                                  : undefined
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
