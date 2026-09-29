import { Head, Link, router, usePage } from '@inertiajs/react';
import { FileClock } from 'lucide-react';
import { useState } from 'react';
import LoanRequestController from '@/actions/App/Http/Controllers/Client/LoanRequestController';
import { MemberLoanStatusCard } from '@/components/member/member-loan-status-card';
import { MemberProfileDetailsCard } from '@/components/member-profile-details-card';
import { MemberProfileHeader } from '@/components/member-profile-header';
import { MemberStatusCard } from '@/components/member-status-card';
import { PageShell } from '@/components/page-shell';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { MemberBalanceCards } from '@/features/member-accounts/components/member-balance-cards';
import { MemberRecentAccountActionsCard } from '@/features/member-accounts/components/member-recent-account-actions-card';
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
import type { LoanStatusSummaryForMember } from '@/types/loan-requests';

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

                <SurfaceCard
                    variant="default"
                    padding="lg"
                    className="space-y-6"
                >
                    <SectionHeader
                        title="Profile summary"
                        description="Key account details and access status."
                        titleClassName="text-lg"
                    />
                    <div className="grid items-stretch gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                        <MemberProfileDetailsCard
                            title="Member details"
                            description="Portal profile information and contact details."
                            className="border-border bg-card shadow-none"
                            itemClassName="border-border bg-muted"
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
                            ]}
                        />
                        <MemberStatusCard
                            className="h-full border-border bg-card shadow-none"
                            statusLabel={statusLabel}
                            statusVariant={statusVariant}
                        />
                    </div>
                </SurfaceCard>

                <MemberBalanceCards
                    acctno={currentMember.acctno}
                    summary={summaryValue}
                    loading={summaryLoading}
                    error={summaryError}
                    onRetry={handleRetry}
                    loansHref={clientLoans().url}
                    loanSecurityHref={clientSavings().url}
                />

                {activeDraft ? (
                    <Card className="rounded-xl shadow-none">
                        <CardHeader className="space-y-2 pb-4">
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <FileClock className="size-4 text-muted-foreground" />
                                Continue your application
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-sm text-muted-foreground">
                                You have a loan request draft
                                {activeDraft.updated_at
                                    ? ` last saved ${formatDateTime(activeDraft.updated_at)}`
                                    : ''}
                                .
                            </p>
                            <Button asChild size="sm">
                                <Link href={LoanRequestController.create().url}>
                                    Resume
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : null}

                {loanSummary ? (
                    <MemberLoanStatusCard loanSummary={loanSummary} />
                ) : null}

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
