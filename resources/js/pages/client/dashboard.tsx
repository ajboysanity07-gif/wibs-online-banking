import { Head, usePage } from '@inertiajs/react';
import { Banknote, PiggyBank } from 'lucide-react';
import { MemberAccountSummaryCard } from '@/components/member-account-summary-card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDate, formatDateTime } from '@/lib/formatters';
import { dashboard } from '@/routes';
import type { Auth, BreadcrumbItem } from '@/types';
import type { MemberAccountsSummary } from '@/types/admin';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Member profile',
        href: dashboard().url,
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
};

type PageProps = {
    auth: Auth;
};

const emptySummary: MemberAccountsSummary = {
    loanBalanceLeft: 0,
    currentPersonalSavings: 0,
    currentSavingsBalance: 0,
    lastLoanTransactionDate: null,
    lastSavingsTransactionDate: null,
    recentLoans: [],
    recentSavings: [],
};

const statusVariant = (status?: string | null) => {
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

const statusLabel = (status?: string | null) => {
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

export default function MemberProfile({ member, summary }: Props) {
    const { auth } = usePage<PageProps>().props;
    const getInitials = useInitials();
    const currentMember: MemberProfile = member ?? {
        name: auth.user.name ?? auth.user.username ?? auth.user.email ?? 'Member',
        username: auth.user.username ?? auth.user.email ?? '',
        email: auth.user.email,
        phone: auth.user.phoneno ?? null,
        acctno: null,
        status: null,
        created_at: auth.user.created_at ?? null,
        avatar_url: auth.user.avatar ?? null,
    };
    const currentSummary = summary ?? emptySummary;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Member profile" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <Avatar className="size-12">
                            <AvatarImage
                                src={currentMember.avatar_url ?? undefined}
                                alt={currentMember.name}
                            />
                            <AvatarFallback>
                                {getInitials(currentMember.name)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="space-y-1">
                            <h1 className="text-2xl font-semibold">
                                {currentMember.name}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Member profile overview and account snapshot.
                            </p>
                        </div>
                    </div>
                    <Badge variant={statusVariant(currentMember.status)}>
                        {statusLabel(currentMember.status)}
                    </Badge>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Profile details</CardTitle>
                        <CardDescription>
                            Account and contact information.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Member name
                            </p>
                            <p className="text-sm font-medium">
                                {currentMember.name}
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
                                {currentMember.phone ?? '--'}
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
                                Member since
                            </p>
                            <p className="text-sm font-medium">
                                {formatDateTime(currentMember.created_at)}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-2">
                    <MemberAccountSummaryCard
                        title="Loans"
                        subtitle="Loan portfolio snapshot"
                        primaryLabel="Total Outstanding Loan Balance"
                        primaryValue={formatCurrency(
                            currentSummary.loanBalanceLeft,
                        )}
                        secondaryLabel="Last Loan Transaction"
                        secondaryValue={formatDate(
                            currentSummary.lastLoanTransactionDate,
                        )}
                        icon={Banknote}
                        accent="primary"
                    />
                    <MemberAccountSummaryCard
                        title="Savings"
                        subtitle="Savings overview"
                        primaryLabel="Total Current Savings"
                        primaryValue={formatCurrency(
                            currentSummary.currentSavingsBalance,
                        )}
                        secondaryLabel="Last Savings Transaction"
                        secondaryValue={formatDate(
                            currentSummary.lastSavingsTransactionDate,
                        )}
                        icon={PiggyBank}
                        accent="accent"
                    />
                </div>
            </div>
        </AppLayout>
    );
}
