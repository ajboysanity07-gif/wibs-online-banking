import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { MemberAccountsSummarySection } from '@/components/member-accounts-summary-section';
import { MemberProfileDetailsCard } from '@/components/member-profile-details-card';
import { MemberProfileHeader } from '@/components/member-profile-header';
import { MemberRecentAccountActionsCard } from '@/components/member-recent-account-actions-card';
import { MemberStatusCard } from '@/components/member-status-card';
import { PageShell } from '@/components/page-shell';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Badge } from '@/components/ui/badge';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatDateTime } from '@/lib/formatters';
import {
    getMemberStatusLabel,
    getMemberStatusVariant,
} from '@/lib/member-status';
import {
    dashboard as clientDashboard,
    loans as clientLoans,
    savings as clientSavings,
} from '@/routes/client';
import type { Auth, BreadcrumbItem } from '@/types';
import type {
    MemberAccountActionsResponse,
    MemberAccountsSummary,
    PaginationMeta,
} from '@/types/admin';

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
    reviewed_by?: { user_id: number; name: string } | null;
    reviewed_at?: string | null;
    avatar_url: string | null;
};

type Props = {
    member?: MemberProfile | null;
    summary?: MemberAccountsSummary | null;
    summaryError?: string | null;
    recentAccountActions?: MemberAccountActionsResponse | null;
    recentAccountActionsError?: string | null;
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
}: Props) {
    const { auth } = usePage<PageProps>().props;
    const getInitials = useInitials();
    const [actionsLoading, setActionsLoading] = useState(false);
    const currentMember: MemberProfile = member ?? {
        name:
            auth.user.name ?? auth.user.username ?? auth.user.email ?? 'Member',
        username: auth.user.username ?? auth.user.email ?? '',
        email: auth.user.email,
        phone: auth.user.phoneno ?? null,
        acctno: null,
        status: null,
        created_at: auth.user.created_at ?? null,
        reviewed_by: null,
        reviewed_at: null,
        avatar_url: auth.user.avatar ?? null,
    };
    const actionsMeta = recentAccountActions?.meta ?? fallbackActionsMeta;
    const actionsItems = recentAccountActions?.items ?? [];
    const summaryValue = summary ?? null;
    const summaryLoading = summaryValue === null && !summaryError;
    const actionsLoadingState =
        actionsLoading || (!recentAccountActions && !recentAccountActionsError);

    const reloadWithActionsPage = (page: number) => {
        setActionsLoading(true);
        router.get(
            clientDashboard().url,
            { actions_page: page },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setActionsLoading(false);
                },
            },
        );
    };

    const handleActionsPageChange = (page: number) => {
        reloadWithActionsPage(page);
    };

    const handleRetry = () => {
        reloadWithActionsPage(actionsMeta.page);
    };
    const canNavigate = Boolean(currentMember.acctno);
    const statusLabel = getMemberStatusLabel(currentMember.status);
    const statusVariant = getMemberStatusVariant(currentMember.status);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Member profile" />
            <PageShell>
                <MemberProfileHeader
                    name={currentMember.name}
                    subtitle="Account status and profile details."
                    avatarUrl={currentMember.avatar_url}
                    avatarFallback={getInitials(currentMember.name) || 'U'}
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
                />

                <SurfaceCard variant="default" padding="lg" className="space-y-6">
                    <SectionHeader
                        title="Profile summary"
                        description="Key account details and access status."
                        titleClassName="text-lg"
                    />
                    <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                        <MemberProfileDetailsCard
                            title="Member details"
                            description="Portal profile information and contact details."
                            className="border-border/30 bg-background/60 shadow-none"
                            itemClassName="border-border/20 bg-muted/15"
                            items={[
                                {
                                    label: 'Member name',
                                    value: currentMember.name,
                                },
                                {
                                    label: 'Username',
                                    value: currentMember.username,
                                },
                                {
                                    label: 'Email',
                                    value: currentMember.email,
                                },
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
                                {
                                    label: 'Reviewed by',
                                    value:
                                        currentMember.reviewed_by?.name ?? '--',
                                },
                                {
                                    label: 'Reviewed at',
                                    value: formatDateTime(
                                        currentMember.reviewed_at ?? null,
                                    ),
                                },
                            ]}
                        />
                        <MemberStatusCard
                            className="border-border/30 bg-background/60 shadow-none"
                            statusLabel={statusLabel}
                            statusVariant={statusVariant}
                        />
                    </div>
                </SurfaceCard>

                <MemberAccountsSummarySection
                    acctno={currentMember.acctno}
                    summary={summaryValue}
                    loading={summaryLoading}
                    error={summaryError}
                    onRetry={handleRetry}
                    loansAction={{
                        label: 'View all',
                        href: clientLoans().url,
                        disabled: !canNavigate,
                    }}
                    savingsAction={{
                        label: 'View all',
                        href: clientSavings().url,
                        disabled: !canNavigate,
                    }}
                />

                <MemberRecentAccountActionsCard
                    acctno={currentMember.acctno}
                    actions={actionsItems}
                    meta={actionsMeta}
                    loading={actionsLoadingState}
                    error={recentAccountActionsError}
                    onRetry={handleRetry}
                    onPageChange={handleActionsPageChange}
                />
            </PageShell>
        </AppLayout>
    );
}
