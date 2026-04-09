import { Head, Link, usePage } from '@inertiajs/react';
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
import { useUpdateMemberAdminAccess } from '@/hooks/admin/use-update-member-admin-access';
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
import type { Auth, BreadcrumbItem } from '@/types';
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

type PageProps = {
    auth: Auth;
};

function LoansAndLoanSecuritySummarySection() {
    const {
        memberKey,
        acctno,
        summary,
        summaryLoading,
        summaryError,
        refreshSummary,
    } = useMemberAccounts();

    const loansHref = memberKey ? memberLoans(memberKey).url : undefined;
    const loanSecurityHref = memberKey ? memberSavings(memberKey).url : undefined;
    const actionDisabled = !acctno || !memberKey;

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
        memberKey,
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
            !memberKey ||
            action.source !== 'LOAN' ||
            action.number === null
        ) {
            return null;
        }

        return loanPayments({
            user: memberKey,
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
    const { auth } = usePage<PageProps>().props;
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

    const { updateAdminAccess, processingKeys } = useUpdateMemberAdminAccess({
        onUpdated: (updated) => {
            setMember(updated);
        },
    });

    const isProcessing =
        currentMember.user_id !== null
            ? processingIds[currentMember.user_id]
            : false;
    const canManagePortalAccess =
        currentMember.user_id !== null && !currentMember.is_admin;
    const canSuspend =
        canManagePortalAccess && currentMember.portal_status === 'active';
    const canReactivate =
        canManagePortalAccess && currentMember.portal_status === 'suspended';
    const statusLabel = getMemberStatusLabel(currentMember.portal_status);
    const statusVariant = getMemberStatusVariant(currentMember.portal_status);
    const registrationLabel = getRegistrationStatusLabel(
        currentMember.registration_status,
    );
    const registrationVariant = getRegistrationStatusVariant(
        currentMember.registration_status,
    );
    const isSuperadmin = auth.isSuperadmin;
    const isSelf =
        currentMember.user_id !== null &&
        currentMember.user_id === auth.user.id;
    const adminAccessLevel = currentMember.admin_access_level;
    const adminAccessLabel =
        adminAccessLevel === 'superadmin'
            ? 'Superadmin'
            : adminAccessLevel === 'admin'
              ? 'Admin'
              : adminAccessLevel === 'member'
                ? 'Member'
                : 'Unregistered';
    const adminAccessVariant =
        adminAccessLevel === 'superadmin'
            ? 'secondary'
            : adminAccessLevel === 'admin'
              ? 'default'
              : 'outline';
    const canManageAdminAccess =
        isSuperadmin && currentMember.user_id !== null && !isSelf;
    const canGrantAdmin =
        canManageAdminAccess && adminAccessLevel === 'member';
    const canRevokeAdmin =
        canManageAdminAccess && adminAccessLevel === 'admin';
    const isAdminAccessProcessing =
        processingKeys[currentMember.member_id] ?? false;
    const portalAccessCard = (
        <MemberStatusCard
            title="Portal access"
            description="Suspend or restore portal access for this member."
            className={isSuperadmin ? undefined : 'h-full'}
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
                    : currentMember.registration_status === 'unregistered'
                      ? 'This member does not have a portal login yet.'
                      : currentMember.is_admin
                        ? 'Portal access for admins is managed separately.'
                        : undefined
            }
        />
    );
    const adminAccessCard = isSuperadmin ? (
        <MemberStatusCard
            title="Admin access"
            description="Promote or revoke admin access for this member."
            statusLabel={adminAccessLabel}
            statusVariant={adminAccessVariant}
            actions={
                <>
                    {canGrantAdmin ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="default"
                            disabled={isAdminAccessProcessing}
                            onClick={() =>
                                updateAdminAccess(
                                    currentMember.member_id,
                                    'grant',
                                )
                            }
                        >
                            Grant admin access
                        </Button>
                    ) : null}
                    {canRevokeAdmin ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            disabled={isAdminAccessProcessing}
                            onClick={() =>
                                updateAdminAccess(
                                    currentMember.member_id,
                                    'revoke',
                                )
                            }
                        >
                            Revoke admin access
                        </Button>
                    ) : null}
                </>
            }
            helper={
                isSelf
                    ? 'You cannot update your own admin access.'
                    : currentMember.registration_status === 'unregistered'
                      ? 'Admin access requires a portal account.'
                      : adminAccessLevel === 'superadmin'
                        ? 'Superadmin access is managed separately.'
                        : undefined
            }
        />
    ) : null;
    const memberDetailsCard = (
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

                <MemberAccountsProvider
                    memberKey={currentMember.member_id}
                    acctno={currentMember.acctno}
                    initialSummary={accountsSummary}
                    initialActions={recentAccountActions}
                >
                    <div className="grid items-stretch gap-4 lg:grid-cols-3">
                        <div className="lg:col-span-2">{memberDetailsCard}</div>
                        <div className={isSuperadmin ? 'space-y-4' : 'h-full'}>
                            {portalAccessCard}
                            {adminAccessCard}
                        </div>
                    </div>
                    <LoansAndLoanSecuritySummarySection />
                    <RecentAccountActionsCard />
                </MemberAccountsProvider>
            </PageShell>
        </AppLayout>
    );
}
