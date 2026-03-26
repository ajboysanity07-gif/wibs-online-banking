import { Head } from '@inertiajs/react';
import { Banknote, CalendarCheck, Clock, Download, Printer } from 'lucide-react';
import { useState } from 'react';
import { MemberAccountAlert } from '@/components/member-account-alert';
import {
    MemberDetailPrimaryCard,
    MemberDetailSupportingCard,
} from '@/components/member-detail-summary-cards';
import { MemberLoanDetailHeader } from '@/components/member-loan-detail-header';
import { MemberLoanPaymentsFiltersCard } from '@/components/member-loan-payments-filters-card';
import { MemberLoanPaymentsRecordsCard } from '@/components/member-loan-payments-records-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useMemberLoanPayments } from '@/hooks/admin/use-member-loan-payments';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDate } from '@/lib/formatters';
import {
    loanPayments,
    loanPaymentsExport,
    loanPaymentsPrint,
    loanSchedule,
    loans as memberLoans,
    show as showMember,
} from '@/routes/admin/members';
import { index as membersIndex } from '@/routes/admin/watchlist';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberLoan,
    MemberLoanPaymentsFilters,
    MemberLoanPaymentsResponse,
    MemberLoanSummary,
} from '@/types/admin';

type MemberSummary = {
    user_id: number;
    member_name: string | null;
    acctno: string | null;
};

type Props = {
    member: MemberSummary;
    loan: MemberLoan;
    summary: MemberLoanSummary;
    payments: MemberLoanPaymentsResponse;
};

const presetRanges: Array<{
    value: MemberLoanPaymentsFilters['range'];
    label: string;
}> = [
    { value: 'current_month', label: 'Current Month' },
    { value: 'current_year', label: 'Current Year' },
    { value: 'last_30_days', label: 'Last 30 Days' },
    { value: 'all', label: 'All Transactions' },
    { value: 'custom', label: 'Custom Range' },
];

export default function MemberLoanPayments({
    member,
    loan,
    summary,
    payments,
}: Props) {
    const loanNumber = loan.lnnumber ?? null;
    const memberKey = `${member.user_id}-${loanNumber ?? 'unknown'}`;
    const [pageState, setPageState] = useState(() => ({
        memberKey,
        page: payments.meta.page,
    }));
    const page =
        pageState.memberKey === memberKey ? pageState.page : payments.meta.page;
    const perPage = payments.meta.perPage;
    const setPage = (nextPage: number) => {
        setPageState({ memberKey, page: nextPage });
    };

    const [filters, setFilters] = useState<MemberLoanPaymentsFilters>(
        payments.filters,
    );

    const filtersReady =
        filters.range !== 'custom' ||
        (Boolean(filters.start) && Boolean(filters.end));

    const {
        items,
        meta,
        loading,
        error,
        refresh,
        openingBalance,
        closingBalance,
    } = useMemberLoanPayments(
        member.user_id,
        loanNumber,
        page,
        perPage,
        filters,
        {
            initial: payments,
            enabled: Boolean(member.acctno && loanNumber && filtersReady),
        },
    );

    const summaryBalance = formatCurrency(summary.balance);
    const nextPayment = summary.next_payment_date
        ? formatDate(summary.next_payment_date)
        : 'No upcoming schedule';
    const lastPayment = summary.last_payment_date
        ? formatDate(summary.last_payment_date)
        : 'No payment recorded yet';
    const showSkeleton = loading && items.length === 0;
    const canNavigate = Boolean(member.acctno && loanNumber);
    const scheduleHref = loanNumber
        ? loanSchedule({
              user: member.user_id,
              loanNumber,
          }).url
        : null;
    const paymentsHref = loanNumber
        ? loanPayments({
              user: member.user_id,
              loanNumber,
          }).url
        : null;
    const backToLoansHref = memberLoans(member.user_id).url;
    const backToProfileHref = showMember(member.user_id).url;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Members', href: membersIndex().url },
        { title: 'Member profile', href: showMember(member.user_id).url },
        { title: 'Loans', href: memberLoans(member.user_id).url },
        { title: 'Payments', href: '#' },
    ];

    const updateRange = (range: MemberLoanPaymentsFilters['range']) => {
        setFilters((current) => ({
            range,
            start: range === 'custom' ? current.start : null,
            end: range === 'custom' ? current.end : null,
        }));
        setPage(1);
    };

    const updateStart = (value: string) => {
        setFilters((current) => ({ ...current, start: value || null }));
        setPage(1);
    };

    const updateEnd = (value: string) => {
        setFilters((current) => ({ ...current, end: value || null }));
        setPage(1);
    };

    const buildExportUrl = (download?: boolean) =>
        loanPaymentsExport(
            { user: member.user_id, loanNumber: loanNumber ?? '' },
            {
                query: {
                    format: 'pdf',
                    range: filters.range,
                    start: filters.start ?? undefined,
                    end: filters.end ?? undefined,
                    download: download ? 1 : undefined,
                },
            },
        ).url;

    const buildPrintUrl = () =>
        loanPaymentsPrint(
            { user: member.user_id, loanNumber: loanNumber ?? '' },
            {
                query: {
                    range: filters.range,
                    start: filters.start ?? undefined,
                    end: filters.end ?? undefined,
                },
            },
        ).url;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan Payments" />
            <div className="flex flex-col gap-6 p-4">
                <MemberLoanDetailHeader
                    title="Loan Payments"
                    subtitle={`${member.member_name ?? 'Member'} - Loan ${loan.lnnumber ?? '--'}`}
                    meta={`Account No: ${member.acctno ?? '--'} | Loan Type: ${loan.lntype ?? '--'}`}
                    currentView="payments"
                    scheduleHref={scheduleHref}
                    paymentsHref={paymentsHref}
                    canNavigate={canNavigate}
                    backToLoansHref={backToLoansHref}
                    backToProfileHref={backToProfileHref}
                />

                {!member.acctno || !loanNumber ? (
                    <MemberAccountAlert
                        title="Loan not available"
                        description="This member needs a valid loan number and account number before payments can be displayed."
                    />
                ) : null}

                {showSkeleton ? (
                    <div className="grid gap-4 md:grid-cols-3">
                        {Array.from({ length: 3 }).map((_, index) => (
                            <Card key={`summary-skeleton-${index}`}>
                                <CardContent className="space-y-3 p-6">
                                    <Skeleton className="h-3 w-24" />
                                    <Skeleton className="h-8 w-32" />
                                    <Skeleton className="h-3 w-28" />
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-3">
                        <MemberDetailPrimaryCard
                            title="Outstanding Loan Balance"
                            value={summaryBalance}
                            helper="Current balance for this loan."
                            icon={Banknote}
                            accent="primary"
                        />
                        <MemberDetailSupportingCard
                            title="Next Payment Date"
                            description="Nearest scheduled payment date."
                            value={nextPayment}
                            icon={CalendarCheck}
                            accent="primary"
                        />
                        <MemberDetailSupportingCard
                            title="Last Payment Date"
                            description="Most recent payment recorded."
                            value={lastPayment}
                            icon={Clock}
                            accent="accent"
                        />
                    </div>
                )}

                <MemberLoanPaymentsFiltersCard
                    filters={filters}
                    presets={presetRanges}
                    isUpdating={loading}
                    description="Filter and export loan payments."
                    openingBalance={openingBalance}
                    closingBalance={closingBalance}
                    onRangeChange={updateRange}
                    onStartChange={updateStart}
                    onEndChange={updateEnd}
                    footer={
                        <>
                            <Button
                                asChild
                                size="sm"
                                disabled={!filtersReady || !loanNumber}
                            >
                                <a href={buildExportUrl(true)}>
                                    <Download />
                                    Download Pdf
                                </a>
                            </Button>
                            <Button
                                asChild
                                size="sm"
                                variant="outline"
                                disabled={!filtersReady || !loanNumber}
                            >
                                <a
                                    href={buildPrintUrl()}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Printer />
                                    Print
                                </a>
                            </Button>
                        </>
                    }
                />

                <MemberLoanPaymentsRecordsCard
                    items={items}
                    meta={meta}
                    isUpdating={loading}
                    error={error}
                    onRetry={() => void refresh()}
                    onPageChange={setPage}
                    showSkeleton={showSkeleton}
                />
            </div>
        </AppLayout>
    );
}
