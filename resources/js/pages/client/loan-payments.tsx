import { Head, router } from '@inertiajs/react';
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
import { PageShell } from '@/components/page-shell';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDate } from '@/lib/formatters';
import {
    dashboard as clientDashboard,
    loanPayments,
    loanSchedule,
    loans as clientLoans,
} from '@/routes/client';
import loanPaymentsRoutes from '@/routes/client/loan-payments';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberLoan,
    MemberLoanPaymentsFilters,
    MemberLoanPaymentsResponse,
    MemberLoanSummary,
} from '@/types/admin';

type MemberSummary = {
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

export default function LoanPayments({
    member,
    loan,
    summary,
    payments,
}: Props) {
    const loanNumber = loan.lnnumber ?? null;
    const perPage = payments.meta.perPage;

    const [filters, setFilters] = useState<MemberLoanPaymentsFilters>(
        payments.filters,
    );
    const [loading, setLoading] = useState(false);

    const filtersReady =
        filters.range !== 'custom' ||
        (Boolean(filters.start) && Boolean(filters.end));

    const items = payments.items ?? [];
    const meta = payments.meta;
    const openingBalance = payments.openingBalance;
    const closingBalance = payments.closingBalance;
    const showSkeleton = loading && items.length === 0;
    const canNavigate = Boolean(member.acctno && loanNumber);
    const scheduleHref = loanNumber ? loanSchedule(loanNumber).url : null;
    const paymentsHref = loanNumber ? loanPayments(loanNumber).url : null;
    const backToLoansHref = clientLoans().url;
    const backToProfileHref = clientDashboard().url;

    const reloadPayments = (
        nextPage: number,
        nextFilters: MemberLoanPaymentsFilters,
    ) => {
        if (!loanNumber) {
            return;
        }

        setLoading(true);
        router.get(
            loanPayments(loanNumber).url,
            {
                page: nextPage,
                perPage,
                range: nextFilters.range,
                start: nextFilters.start ?? undefined,
                end: nextFilters.end ?? undefined,
            },
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
        if (nextPage === meta.page || !filtersReady) {
            return;
        }

        reloadPayments(nextPage, filters);
    };

    const updateRange = (range: MemberLoanPaymentsFilters['range']) => {
        const nextFilters = {
            range,
            start: range === 'custom' ? filters.start : null,
            end: range === 'custom' ? filters.end : null,
        };

        setFilters(nextFilters);

        if (range !== 'custom' || (nextFilters.start && nextFilters.end)) {
            reloadPayments(1, nextFilters);
        }
    };

    const updateStart = (value: string) => {
        const nextFilters = { ...filters, start: value || null };

        setFilters(nextFilters);

        if (
            filters.range !== 'custom' ||
            (nextFilters.start && nextFilters.end)
        ) {
            reloadPayments(1, nextFilters);
        }
    };

    const updateEnd = (value: string) => {
        const nextFilters = { ...filters, end: value || null };

        setFilters(nextFilters);

        if (
            filters.range !== 'custom' ||
            (nextFilters.start && nextFilters.end)
        ) {
            reloadPayments(1, nextFilters);
        }
    };

    const buildExportUrl = (download?: boolean) =>
        loanPaymentsRoutes.export(
            { loanNumber: loanNumber ?? '' },
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
        loanPaymentsRoutes.print(
            { loanNumber: loanNumber ?? '' },
            {
                query: {
                    range: filters.range,
                    start: filters.start ?? undefined,
                    end: filters.end ?? undefined,
                },
            },
        ).url;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Member profile', href: clientDashboard().url },
        { title: 'Loans', href: clientLoans().url },
        { title: 'Payments', href: '#' },
    ];

    const summaryBalance = formatCurrency(summary.balance);
    const nextPayment = summary.next_payment_date
        ? formatDate(summary.next_payment_date)
        : 'No upcoming schedule';
    const lastPayment = summary.last_payment_date
        ? formatDate(summary.last_payment_date)
        : 'No payment recorded yet';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan Payments" />
            <PageShell>
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
                            <Card
                                key={`summary-skeleton-${index}`}
                                className="rounded-2xl border-border/40 bg-card/70 shadow-sm"
                            >
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
                    onPageChange={handlePageChange}
                    showSkeleton={showSkeleton}
                />
            </PageShell>
        </AppLayout>
    );
}
