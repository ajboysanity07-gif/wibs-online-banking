import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Plus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { AllLoansTable } from '@/components/loans/all-loans-table';
import { LoanCards } from '@/components/loans/loan-cards';
import { LoanPaymentsList } from '@/components/loans/loan-payments-list';
import { formatShortDate, paymentLoanLabel } from '@/components/loans/loan-presentation';
import { LoanSummaryStats } from '@/components/loans/loan-summary-stats';
import { MemberDetailPageHeader } from '@/components/member-detail-page-header';
import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { MemberAccountAlert } from '@/features/member-accounts/components/member-account-alert';
import AppLayout from '@/layouts/app-layout';
import {
    dashboard as clientDashboard,
    loanPayments,
    loanSchedule,
    loans as clientLoans,
} from '@/routes/client';
import { create as createLoanRequest } from '@/routes/client/loan-requests';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberAccountsSummary,
    MemberLoan,
    MemberRecentLoanPayment,
    PaginationMeta,
} from '@/types/admin';

type MemberSummary = {
    name: string;
    acctno: string | null;
};

type Props = {
    member: MemberSummary;
    summary: MemberAccountsSummary | null;
    summaryError?: string | null;
    loans: { items: MemberLoan[]; meta: PaginationMeta } | null;
    loansError?: string | null;
    payments: MemberRecentLoanPayment[] | null;
    paymentsError?: string | null;
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
    payments,
    paymentsError = null,
}: Props) {
    const [isPaging, setIsPaging] = useState(false);
    const items = loans?.items ?? [];
    const meta = loans?.meta ?? fallbackMeta;
    const isLoading = isPaging || (loans === null && !loansError);
    const canNavigate = Boolean(member.acctno);

    const reloadPage = (nextPage: number) => {
        setIsPaging(true);
        router.get(
            clientLoans().url,
            { page: nextPage },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setIsPaging(false);
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

    const handleViewSchedule = (loanNumber: string) => {
        router.get(loanSchedule(loanNumber).url);
    };

    const handleViewPayments = (loanNumber: string) => {
        router.get(loanPayments(loanNumber).url);
    };

    const handlePayNow = (loanNumber: string) => {
        router.get(loanPayments(loanNumber).url);
    };

    const handleRequestReceipt = (payment: MemberRecentLoanPayment) => {
        toast(
            `Preparing receipt for the ${paymentLoanLabel(payment)} payment on ${formatShortDate(payment.date)}…`,
            { position: 'bottom-center' },
        );
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Loans', href: clientLoans().url },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loans" />
            <PageShell size="wide">
                <MemberDetailPageHeader
                    title="Loans"
                    subtitle="Track your active loans and payment history."
                    meta={
                        <Badge
                            variant="outline"
                            className="bg-card font-semibold tabular-nums"
                        >
                            Account No: {member.acctno ?? '--'}
                        </Badge>
                    }
                    actions={
                        <>
                            <Button asChild>
                                <Link href={createLoanRequest().url}>
                                    <Plus aria-hidden="true" />
                                    Apply for a loan
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={clientDashboard().url}>
                                    <ArrowLeft aria-hidden="true" />
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

                <LoanSummaryStats summary={summary} loans={items} />

                <LoanCards
                    loans={items}
                    isLoading={isLoading}
                    error={loansError}
                    onRetry={handleRetry}
                    onViewSchedule={canNavigate ? handleViewSchedule : undefined}
                    onViewPayments={canNavigate ? handleViewPayments : undefined}
                    onPayNow={canNavigate ? handlePayNow : undefined}
                />

                <LoanPaymentsList
                    payments={payments}
                    error={paymentsError}
                    onRetry={handleRetry}
                    onRequestReceipt={handleRequestReceipt}
                />

                <AllLoansTable
                    loans={items}
                    meta={meta}
                    isLoading={isLoading}
                    error={loansError}
                    onRetry={handleRetry}
                    onPageChange={handlePageChange}
                />
            </PageShell>
        </AppLayout>
    );
}