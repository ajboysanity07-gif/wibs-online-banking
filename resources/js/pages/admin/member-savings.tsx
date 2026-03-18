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
import { useMemberSavings } from '@/hooks/admin/use-member-savings';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDate } from '@/lib/formatters';
import { savings as memberSavings, show as showMember } from '@/routes/admin/members';
import { index as membersIndex } from '@/routes/admin/watchlist';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberAccountsSummary,
    MemberSavingsLedgerResponse,
} from '@/types/admin';

type MemberSummary = {
    user_id: number;
    member_name: string | null;
    acctno: string | null;
};

type Props = {
    member: MemberSummary;
    summary: MemberAccountsSummary;
    savings: MemberSavingsLedgerResponse;
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

    const {
        items,
        meta,
        loading,
        error,
        refresh,
    } = useMemberSavings(member.user_id, page, perPage, {
        initial: savings,
        enabled: true,
    });

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
        { title: 'Savings', href: memberSavings(member.user_id).url },
    ];
    const currentSavings = formatCurrency(summary.currentPersonalSavings);
    const lastSavingsTransaction = formatDate(
        summary.lastSavingsTransactionDate,
    );
    const savingsEmptyMessage = loading
        ? 'Loading savings...'
        : 'No savings transactions found.';
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
            <Head title="Member Savings" />
            <div className="flex flex-col gap-6 p-4">
                <MemberDetailPageHeader
                    title="Member Savings"
                    subtitle={`Savings overview for ${member.member_name ?? 'this member'}.`}
                    meta={
                        <span className="inline-flex flex-wrap items-center gap-2">
                            <span>Account No: {member.acctno ?? '--'}</span>
                            <span aria-hidden="true">|</span>
                            <span className="inline-flex flex-wrap items-center gap-2">
                                <span>Savings No:</span>
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
                        description="Add an account number to view savings details."
                    />
                ) : null}

                <div className="grid gap-4 md:grid-cols-2">
                    <MemberDetailPrimaryCard
                        title="Personal Savings Balance"
                        value={currentSavings}
                        helper="Latest personal savings ledger balance."
                        icon={PiggyBank}
                        accent="accent"
                    />
                    <MemberDetailSupportingCard
                        title="Last Savings Transaction"
                        description="Most recent savings activity date."
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
            </div>
        </AppLayout>
    );
}
