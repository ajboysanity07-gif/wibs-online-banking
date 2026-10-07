import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, EyeOff, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import LoanRequestController from '@/actions/App/Http/Controllers/Client/LoanRequestController';
import { MemberLoanRequestStatusCard } from '@/components/member/member-loan-request-status-card';
import { MemberQuickActions } from '@/components/member/member-quick-actions';
import { MemberProfileDetailsCard } from '@/components/member-profile-details-card';
import { MemberProfileHeader } from '@/components/member-profile-header';
import { PageShell } from '@/components/page-shell';
import { SurfaceCard } from '@/components/surface-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { AccountActionsQuery } from '@/features/member-accounts/components/member-account-actions-table';
import { MemberBalanceCards } from '@/features/member-accounts/components/member-balance-cards';
import { MemberRecentTransactions } from '@/features/member-accounts/components/member-recent-transactions';
import type { MemberRecentAccountAction } from '@/features/member-accounts/types';
import { useHideBalances } from '@/hooks/use-hide-balances';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/formatters';
import {
    getMemberStatusLabel,
    getMemberStatusVariant,
} from '@/lib/member-status';
import {
    dashboard as clientDashboard,
    loanPayments as clientLoanPayments,
    loans as clientLoans,
    savings as clientSavings,
} from '@/routes/client';
import type { Auth, BreadcrumbItem } from '@/types';
import type {
    MemberAccountActionsResponse,
    MemberAccountsSummary,
    PaginationMeta,
} from '@/types/admin';
import type {
    ActiveLoanRequestSummary,
    LoanStatusSummaryForMember,
} from '@/types/loan-requests';
import { security as securitySettings } from '@/routes/settings';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Member profile',
        href: clientDashboard().url,
    },
];

type MemberProfile = {
    name: string;
    username: string;
    email: string;
    phone: string | null;
    acctno: string | null;
    status: string | null;
    created_at: string | null;
    avatar_url: string | null;
};

type Props = {
    member?: MemberProfile | null;
    summary?: MemberAccountsSummary | null;
    summaryError?: string | null;
    recentAccountActions?: MemberAccountActionsResponse | null;
    recentAccountActionsError?: string | null;
    loanSummary?: LoanStatusSummaryForMember | null;
    activeDraft?: { id: number; updated_at: string | null } | null;
    activeRequest?: ActiveLoanRequestSummary | null;
};

type PageProps = {
    auth: Auth;
};

const fallbackActionsMeta: PaginationMeta = {
    page: 1,
    perPage: 5,
    total: 0,
    lastPage: 1,
};

export default function MemberProfile({
    member,
    summary,
    summaryError = null,
    recentAccountActions,
    recentAccountActionsError = null,
    loanSummary,
    activeDraft = null,
    activeRequest = null,
}: Props) {
    const { auth } = usePage<PageProps>().props;
    const getInitials = useInitials();
    const [actionsLoading, setActionsLoading] = useState(false);
    const [balancesHidden, toggleBalancesHidden] = useHideBalances();
    const currentMember: MemberProfile = member ?? {
        name:
            auth.user.name ?? auth.user.username ?? auth.user.email ?? 'Member',
        username: auth.user.username ?? auth.user.email ?? '',
        email: auth.user.email,
        phone: auth.user.phoneno ?? null,
        acctno: null,
        status: null,
        created_at: auth.user.created_at ?? null,
        avatar_url: auth.user.avatar ?? null,
    };
    const actionsMeta = recentAccountActions?.meta ?? fallbackActionsMeta;
    const actionsItems = recentAccountActions?.items ?? [];
    const summaryValue = summary ?? null;
    const summaryLoading = summaryValue === null && !summaryError;
    const actionsLoadingState =
        actionsLoading || (!recentAccountActions && !recentAccountActionsError);

    const [actionsQuery, setActionsQuery] = useState<AccountActionsQuery>({
        page: 1,
        perPage: 5,
        source: 'all',
        search: '',
    });

    const reloadActions = (query: AccountActionsQuery) => {
        setActionsQuery(query);
        setActionsLoading(true);
        router.get(
            clientDashboard().url,
            {
                actions_page: query.page,
                actions_per_page: query.perPage,
                actions_source:
                    query.source === 'all' ? undefined : query.source,
                actions_search: query.search.trim() || undefined,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setActionsLoading(false);
                },
            },
        );
    };

    const resolveActionHref = (action: MemberRecentAccountAction) => {
        if (action.source === 'LOAN' && action.number !== null) {
            return clientLoanPayments({ loanNumber: action.number }).url;
        }

        return action.source === 'SAV' ? clientSavings().url : null;
    };

    const handleRetry = () => {
        reloadActions(actionsQuery);
    };
    const firstName = currentMember.name.trim().split(' ')[0] || 'there';
    const statusLabel = getMemberStatusLabel(currentMember.status);
    const statusVariant = getMemberStatusVariant(currentMember.status);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Member profile" />
            <PageShell>
                <MemberProfileHeader
                    name={`Hi, ${firstName}`}
                    subtitle="Here's where your loans stand today."
                    accessory={
                        <Button asChild variant="accent" className="font-bold">
                            <Link href={LoanRequestController.create().url}>
                                Apply for a loan
                            </Link>
                        </Button>
                    }
                    avatarUrl={currentMember.avatar_url}
                    avatarFallback={getInitials(currentMember.name) || 'U'}
                    statusBadge={
                        <Badge
                            variant={statusVariant}
                            className="text-[0.65rem] tracking-[0.2em] uppercase"
                        >
                            {statusLabel}
                        </Badge>
                    }
                    meta={
                        <>
                            <Badge variant="outline" className="bg-card">
                                Account No: {currentMember.acctno ?? '--'}
                            </Badge>
                            <Badge variant="outline" className="bg-card">
                                Username: {currentMember.username}
                            </Badge>
                        </>
                    }
                />

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-lg font-semibold">Your accounts</h2>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        aria-pressed={balancesHidden}
                        onClick={toggleBalancesHidden}
                    >
                        {balancesHidden ? (
                            <Eye className="size-4" />
                        ) : (
                            <EyeOff className="size-4" />
                        )}
                        {balancesHidden ? 'Show balances' : 'Hide balances'}
                    </Button>
                </div>

                <MemberBalanceCards
                    hideBalances={balancesHidden}
                    acctno={currentMember.acctno}
                    summary={summaryValue}
                    loanSummary={loanSummary}
                    loading={summaryLoading}
                    error={summaryError}
                    onRetry={handleRetry}
                    loansHref={clientLoans().url}
                    loanSecurityHref={clientSavings().url}
                />

                <MemberQuickActions />

                <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                    <MemberRecentTransactions
                        actions={actionsItems}
                        hideAmounts={balancesHidden}
                        meta={actionsMeta}
                        loading={actionsLoadingState}
                        error={recentAccountActionsError}
                        onRetry={handleRetry}
                        onQueryChange={reloadActions}
                        resolveActionHref={resolveActionHref}
                    />
                    <MemberLoanRequestStatusCard
                        activeRequest={activeRequest}
                        activeDraft={activeDraft}
                        hideAmounts={balancesHidden}
                    />
                </div>

                <section
                    aria-labelledby="profile-summary-heading"
                    className="space-y-3"
                >
                    <h2
                        id="profile-summary-heading"
                        className="text-lg font-semibold"
                    >
                        Profile summary
                    </h2>
                    <MemberProfileDetailsCard
                        title="Member details"
                        description="Portal profile information and contact details."
                        className="border-border bg-card shadow-none"
                        contentClassName="sm:grid-cols-2 lg:grid-cols-3"
                        itemClassName="border-border bg-muted"
                        items={[
                            { label: 'Member name', value: currentMember.name },
                            {
                                label: 'Username',
                                value: currentMember.username,
                            },
                            { label: 'Email', value: currentMember.email },
                            {
                                label: 'Phone',
                                value: currentMember.phone ?? '--',
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
                </section>

                <SurfaceCard
                    padding="md"
                    className="flex flex-wrap items-center gap-4"
                >
                    <span
                        aria-hidden="true"
                        className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-primary"
                    >
                        <ShieldCheck className="size-4" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-semibold">Stay safe</p>
                        <p className="text-sm text-muted-foreground">
                            WIBS never asks for your password by phone or email.
                        </p>
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <Link href={securitySettings().url}>
                            Review security settings
                        </Link>
                    </Button>
                </SurfaceCard>
            </PageShell>
        </AppLayout>
    );
}
