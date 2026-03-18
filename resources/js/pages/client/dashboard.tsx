import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { MemberAccountsSummarySection } from '@/components/member-accounts-summary-section';
import { MemberRecentAccountActionsCard } from '@/components/member-recent-account-actions-card';
import { MemberProfileDetailsCard } from '@/components/member-profile-details-card';
import { MemberProfileHeader } from '@/components/member-profile-header';
import { MemberStatusCard } from '@/components/member-status-card';
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
        name: auth.user.name ?? auth.user.username ?? auth.user.email ?? 'Member',
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Member profile" />
            <div className="flex flex-col gap-6 p-4">
                <MemberProfileHeader
                    name={currentMember.name}
                    subtitle="Account status and profile details."
                    avatarUrl={currentMember.avatar_url}
                    avatarFallback={getInitials(currentMember.name) || 'U'}
                />

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <MemberProfileDetailsCard
                            title="Member details"
                            description="Portal profile information and contact details."
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
                                    value: currentMember.reviewed_by?.name ?? '--',
                                },
                                {
                                    label: 'Reviewed at',
                                    value: formatDateTime(
                                        currentMember.reviewed_at ?? null,
                                    ),
                                },
                            ]}
                        />
                    </div>
                    <MemberStatusCard
                        statusLabel={getMemberStatusLabel(
                            currentMember.status,
                        )}
                        statusVariant={getMemberStatusVariant(
                            currentMember.status,
                        )}
                    />
                </div>

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
            </div>
        </AppLayout>
    );
}
