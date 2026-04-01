import { Head, Link } from '@inertiajs/react';
import { Banknote, Clock } from 'lucide-react';
import { useState } from 'react';
import { MemberAccountAlert } from '@/features/member-accounts/components/member-account-alert';
import { MemberDetailPageHeader } from '@/components/member-detail-page-header';
import {
    MemberDetailPrimaryCard,
    MemberDetailSupportingCard,
} from '@/components/member-detail-summary-cards';
import { MemberLoanRecordsCard } from '@/components/member-loan-records-card';
import { Button } from '@/components/ui/button';
import { PageShell } from '@/components/page-shell';
import { useMemberLoans } from '@/hooks/admin/use-member-loans';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDate } from '@/lib/formatters';
import {
    loanPayments,
    loanSchedule,
    loans as memberLoans,
    show as showMember,
} from '@/routes/admin/members';
import { index as membersIndex } from '@/routes/admin/watchlist';
import type { BreadcrumbItem } from '@/types';
import type { MemberAccountsSummary, MemberLoansResponse } from '@/types/admin';

type MemberSummary = {
    user_id: number;
    member_name: string | null;
    acctno: string | null;
};

type Props = {
    member: MemberSummary;
    summary: MemberAccountsSummary;
    loans: MemberLoansResponse;
};

export default function MemberLoans({ member, summary, loans }: Props) {
    const memberKey = `${member.user_id}`;
    const [pageState, setPageState] = useState(() => ({
        memberKey,
        page: loans.meta.page,
    }));
    const page =
        pageState.memberKey === memberKey ? pageState.page : loans.meta.page;
    const perPage = loans.meta.perPage;
    const setPage = (nextPage: number) => {
        setPageState({ memberKey, page: nextPage });
    };

    const { items, meta, loading, error, refresh } = useMemberLoans(
        member.user_id,
        page,
        perPage,
        {
            initial: loans,
            enabled: true,
        },
    );

    const canNavigate = Boolean(member.acctno);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Members', href: membersIndex().url },
        { title: 'Member profile', href: showMember(member.user_id).url },
        { title: 'Loans', href: memberLoans(member.user_id).url },
    ];
    const loanBalance = formatCurrency(summary.loanBalanceLeft);
    const lastLoanTransaction = formatDate(summary.lastLoanTransactionDate);
    const loanEmptyMessage = loading ? 'Loading loans...' : 'No loans found.';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Member Loans" />
            <PageShell size="wide">
                <MemberDetailPageHeader
                    title="Member Loans"
                    subtitle={`Loan portfolio for ${member.member_name ?? 'this member'}.`}
                    meta={`Account No: ${member.acctno ?? '--'}`}
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
                        description="Add an account number to view loan details."
                    />
                ) : null}

                <div className="grid gap-4 md:grid-cols-2">
                    <MemberDetailPrimaryCard
                        title="Total Outstanding Loan Balance"
                        value={loanBalance}
                        helper="Sum of outstanding loan balances."
                        icon={Banknote}
                        accent="primary"
                    />
                    <MemberDetailSupportingCard
                        title="Last Loan Transaction"
                        description="Most recent loan activity date."
                        value={lastLoanTransaction}
                        icon={Clock}
                        accent="primary"
                    />
                </div>

                <MemberLoanRecordsCard
                    items={items}
                    meta={meta}
                    isUpdating={loading}
                    error={error}
                    onRetry={() => void refresh()}
                    onPageChange={setPage}
                    canNavigate={canNavigate}
                    emptyMessage={loanEmptyMessage}
                    buildScheduleHref={(loanNumber) =>
                        loanNumber
                            ? loanSchedule({
                                  user: member.user_id,
                                  loanNumber,
                              }).url
                            : null
                    }
                    buildPaymentsHref={(loanNumber) =>
                        loanNumber
                            ? loanPayments({
                                  user: member.user_id,
                                  loanNumber,
                              }).url
                            : null
                    }
                />
            </PageShell>
        </AppLayout>
    );
}
