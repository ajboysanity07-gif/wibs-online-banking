import { Head, Link, router } from '@inertiajs/react';
import { Banknote, Clock } from 'lucide-react';
import { useState } from 'react';
import { LoanRequestRecordsCard } from '@/components/loan-request/loan-request-records-card';
import { MemberAccountAlert } from '@/components/member-account-alert';
import { MemberDetailPageHeader } from '@/components/member-detail-page-header';
import {
    MemberDetailPrimaryCard,
    MemberDetailSupportingCard,
} from '@/components/member-detail-summary-cards';
import { MemberLoanRecordsCard } from '@/components/member-loan-records-card';
import { Button } from '@/components/ui/button';
import { PageShell } from '@/components/page-shell';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDate } from '@/lib/formatters';
import {
    dashboard as clientDashboard,
    loanPayments,
    loanSchedule,
    loans as clientLoans,
} from '@/routes/client';
import { create as loanRequestCreate } from '@/routes/client/loan-requests';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberAccountsSummary,
    MemberLoansResponse,
    PaginationMeta,
} from '@/types/admin';
import type { LoanRequestListResponse } from '@/types/loan-requests';

type MemberSummary = {
    name: string;
    acctno: string | null;
};

type Props = {
    member: MemberSummary;
    summary: MemberAccountsSummary | null;
    summaryError?: string | null;
    loans: MemberLoansResponse | null;
    loansError?: string | null;
    loanRequests: LoanRequestListResponse | null;
    loanRequestsError?: string | null;
};

const fallbackMeta: PaginationMeta = {
    page: 1,
    perPage: 10,
    total: 0,
    lastPage: 1,
};

export default function MemberLoans({
    member,
    summary,
    loans,
    loansError = null,
    loanRequests,
    loanRequestsError = null,
}: Props) {
    const [loading, setLoading] = useState(false);
    const items = loans?.items ?? [];
    const meta = loans?.meta ?? fallbackMeta;
    const summaryValue = summary ?? null;
    const isLoading = loading || (loans === null && !loansError);
    const loanEmptyMessage = isLoading ? 'Loading loans...' : 'No loans found.';
    const canNavigate = Boolean(member.acctno);
    const requestItems = loanRequests?.items ?? [];
    const isRequestsLoading =
        loanRequests === null && loanRequestsError === null;

    const reloadPage = (nextPage: number) => {
        setLoading(true);
        router.get(
            clientLoans().url,
            { page: nextPage },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setLoading(false);
                },
            },
        );
    };

    const handlePageChange = (nextPage: number) => {
        if (nextPage === meta.page) {
            return;
        }

        reloadPage(nextPage);
    };

    const handleRetry = () => {
        reloadPage(meta.page);
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Member profile', href: clientDashboard().url },
        { title: 'Loans', href: clientLoans().url },
    ];
    const loanBalance = formatCurrency(summaryValue?.loanBalanceLeft);
    const lastLoanTransaction = formatDate(
        summaryValue?.lastLoanTransactionDate,
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loans" />
            <PageShell>
                <MemberDetailPageHeader
                    title="Loans"
                    subtitle="Manage active loans and track new requests."
                    meta={`Account No: ${member.acctno ?? '--'}`}
                    actions={
                        <>
                            <Button asChild size="sm">
                                <Link href={loanRequestCreate().url}>
                                    Request loan
                                </Link>
                            </Button>
                            <Button asChild variant="ghost" size="sm">
                                <Link href={clientDashboard().url}>
                                    Back to profile
                                </Link>
                            </Button>
                        </>
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

                <LoanRequestRecordsCard
                    items={requestItems}
                    isUpdating={isRequestsLoading}
                    error={loanRequestsError}
                />

                <MemberLoanRecordsCard
                    items={items}
                    meta={meta}
                    isUpdating={isLoading}
                    error={loansError}
                    onRetry={handleRetry}
                    onPageChange={handlePageChange}
                    canNavigate={canNavigate}
                    emptyMessage={loanEmptyMessage}
                    buildScheduleHref={(loanNumber) =>
                        loanNumber ? loanSchedule(loanNumber).url : null
                    }
                    buildPaymentsHref={(loanNumber) =>
                        loanNumber ? loanPayments(loanNumber).url : null
                    }
                />
            </PageShell>
        </AppLayout>
    );
}
