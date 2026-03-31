import { Head, Link } from '@inertiajs/react';
import { Clock, PiggyBank } from 'lucide-react';
import { useMemo, useState } from 'react';
import { MemberAccountAlert } from '@/components/member-account-alert';
import { MemberDetailPageHeader } from '@/components/member-detail-page-header';
import {
    MemberDetailPrimaryCard,
    MemberDetailSupportingCard,
} from '@/components/member-detail-summary-cards';
import { MemberSavingsLedgerCard } from '@/components/member-savings-ledger-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PageShell } from '@/components/page-shell';
import { useMemberSavings } from '@/hooks/admin/use-member-savings';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDate } from '@/lib/formatters';
import {
    savings as memberSavings,
    show as showMember,
} from '@/routes/admin/members';
import { index as membersIndex } from '@/routes/admin/watchlist';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberAccountsSummary,
    MemberLoanSecurityLedgerResponse,
} from '@/types/admin';

type MemberSummary = {
    user_id: number;
    member_name: string | null;
    acctno: string | null;
};

type Props = {
    member: MemberSummary;
    summary: MemberAccountsSummary;
    savings: MemberLoanSecurityLedgerResponse;
};

export default function MemberSavings({ member, summary, savings }: Props) {
    const memberKey = `${member.user_id}`;
    const [pageState, setPageState] = useState(() => ({
        memberKey,
        page: savings.meta.page,
    }));
    const page =
        pageState.memberKey === memberKey ? pageState.page : savings.meta.page;
    const perPage = savings.meta.perPage;
    const setPage = (nextPage: number) => {
        setPageState({ memberKey, page: nextPage });
    };

    const { items, meta, loading, error, refresh } = useMemberSavings(
        member.user_id,
        page,
        perPage,
        {
            initial: savings,
            enabled: true,
        },
    );

    const savingsNumbers = useMemo(() => {
        const uniqueNumbers = new Set<string>();

        items.forEach((item) => {
            if (item.svnumber === null || item.svnumber === '') {
                return;
            }

            uniqueNumbers.add(String(item.svnumber));
        });

        return Array.from(uniqueNumbers);
    }, [items]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Members', href: membersIndex().url },
        { title: 'Member profile', href: showMember(member.user_id).url },
        { title: 'Loan Security', href: memberSavings(member.user_id).url },
    ];
    const currentSavings = formatCurrency(summary.currentLoanSecurityBalance);
    const lastSavingsTransaction = formatDate(
        summary.lastLoanSecurityTransactionDate,
    );
    const savingsEmptyMessage = loading
        ? 'Loading loan security...'
        : 'No loan security transactions found.';
    const savingsNumberMeta =
        savingsNumbers.length === 0 ? (
            <span>--</span>
        ) : (
            <span className="inline-flex flex-wrap items-center gap-2">
                {savingsNumbers.map((number) => (
                    <Badge key={number} variant="outline">
                        {number}
                    </Badge>
                ))}
            </span>
        );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Member Loan Security" />
            <PageShell size="wide">
                <MemberDetailPageHeader
                    title="Member Loan Security"
                    subtitle={`Loan security overview for ${member.member_name ?? 'this member'}.`}
                    meta={
                        <span className="inline-flex flex-wrap items-center gap-2">
                            <span>Account No: {member.acctno ?? '--'}</span>
                            <span aria-hidden="true">|</span>
                            <span className="inline-flex flex-wrap items-center gap-2">
                                <span>Loan Security No:</span>
                                {savingsNumberMeta}
                            </span>
                        </span>
                    }
                    actions={
                        <Button asChild variant="ghost" size="sm">
                            <Link href={showMember(member.user_id).url}>
                                Back to profile
                            </Link>
                        </Button>
                    }
                />

                {!member.acctno ? (
                    <MemberAccountAlert
                        title="Account number missing"
                        description="Add an account number to view loan security details."
                    />
                ) : null}

                <div className="grid gap-4 md:grid-cols-2">
                    <MemberDetailPrimaryCard
                        title="Loan Security Balance"
                        value={currentSavings}
                        helper="Latest loan security ledger balance."
                        icon={PiggyBank}
                        accent="accent"
                    />
                    <MemberDetailSupportingCard
                        title="Last Loan Security Transaction"
                        description="Most recent loan security activity date."
                        value={lastSavingsTransaction}
                        icon={Clock}
                        accent="accent"
                    />
                </div>

                <MemberSavingsLedgerCard
                    items={items}
                    meta={meta}
                    isUpdating={loading}
                    error={error}
                    onRetry={() => void refresh()}
                    onPageChange={setPage}
                    emptyMessage={savingsEmptyMessage}
                />
            </PageShell>
        </AppLayout>
    );
}
