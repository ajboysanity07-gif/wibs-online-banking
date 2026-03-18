import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Banknote, CalendarClock, Clock, CreditCard } from 'lucide-react';
import { useMemo, useState } from 'react';
import { MemberAccountAlert } from '@/components/member-account-alert';
import { MemberDetailPageHeader } from '@/components/member-detail-page-header';
import {
    MemberDetailPrimaryCard,
    MemberDetailSupportingCard,
} from '@/components/member-detail-summary-cards';
import {
    MemberMobileCard,
    MemberMobileCardSkeleton,
} from '@/components/member-mobile-card';
import { MemberRecordsCard } from '@/components/member-records-card';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import {
    DataTablePagination,
    DataTablePaginationSkeleton,
} from '@/components/ui/data-table-pagination';
import { TableSkeleton } from '@/components/ui/table-skeleton';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDate } from '@/lib/formatters';
import {
    dashboard as clientDashboard,
    loanPayments,
    loanSchedule,
    loans as clientLoans,
} from '@/routes/client';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberAccountsSummary,
    MemberLoan,
    MemberLoansResponse,
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
    loans: MemberLoansResponse | null;
    loansError?: string | null;
};

const loanTableSkeletonColumns = [
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-16', cellClassName: 'w-20' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-16', cellClassName: 'w-20' },
    {
        headerClassName: 'w-12',
        cellClassName: 'h-8 w-28',
        align: 'right' as const,
    },
];

const MobileLoanCardSkeletonList = ({ rows = 4 }: { rows?: number }) => (
    <div className="space-y-3">
        {Array.from({ length: rows }).map((_, index) => (
            <MemberMobileCardSkeleton
                key={`loan-card-skeleton-${index}`}
                actionCount={2}
            />
        ))}
    </div>
);

const MobileLoanCard = ({
    loan,
    canNavigate,
}: {
    loan: MemberLoan;
    canNavigate: boolean;
}) => (
    <MemberMobileCard
        title={loan.lnnumber ?? '--'}
        subtitle={loan.lntype ?? '--'}
        valueLabel="Balance"
        value={formatCurrency(loan.balance)}
        meta={[
            { label: 'Last move', value: formatDate(loan.lastmove) },
            { label: 'Principal', value: formatCurrency(loan.principal) },
            { label: 'Initial', value: formatCurrency(loan.initial) },
        ]}
        footer={
            <div className="flex flex-col gap-2 sm:flex-row">
                {canNavigate && loan.lnnumber ? (
                    <Button
                        asChild
                        type="button"
                        size="sm"
                        variant="outline"
                        className="w-full sm:w-auto"
                    >
                        <Link href={loanSchedule(loan.lnnumber).url}>
                            <CalendarClock />
                            Schedule
                        </Link>
                    </Button>
                ) : (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="w-full sm:w-auto"
                        disabled
                    >
                        <CalendarClock />
                        Schedule
                    </Button>
                )}
                {canNavigate && loan.lnnumber ? (
                    <Button
                        asChild
                        type="button"
                        size="sm"
                        variant="outline"
                        className="w-full sm:w-auto"
                    >
                        <Link href={loanPayments(loan.lnnumber).url}>
                            <CreditCard />
                            Payment
                        </Link>
                    </Button>
                ) : (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="w-full sm:w-auto"
                        disabled
                    >
                        <CreditCard />
                        Payment
                    </Button>
                )}
            </div>
        }
    />
);

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
}: Props) {
    const [loading, setLoading] = useState(false);
    const items = loans?.items ?? [];
    const meta = loans?.meta ?? fallbackMeta;
    const summaryValue = summary ?? null;
    const isLoading = loading || (loans === null && !loansError);
    const showSkeleton = isLoading && items.length === 0;
    const loanEmptyMessage = isLoading ? 'Loading loans...' : 'No loans found.';
    const canNavigate = Boolean(member.acctno);

    const columns = useMemo<ColumnDef<MemberLoan>[]>(
        () => [
            {
                accessorKey: 'lnnumber',
                header: 'Loan No',
                cell: ({ row }) => row.original.lnnumber ?? '--',
            },
            {
                accessorKey: 'lntype',
                header: 'Type',
                cell: ({ row }) => row.original.lntype ?? '--',
            },
            {
                accessorKey: 'principal',
                header: 'Principal',
                cell: ({ row }) => formatCurrency(row.original.principal),
            },
            {
                accessorKey: 'balance',
                header: 'Balance',
                cell: ({ row }) => formatCurrency(row.original.balance),
            },
            {
                accessorKey: 'lastmove',
                header: 'Last move',
                cell: ({ row }) => formatDate(row.original.lastmove),
            },
            {
                accessorKey: 'initial',
                header: 'Initial',
                cell: ({ row }) => formatCurrency(row.original.initial),
            },
            {
                id: 'actions',
                header: '',
                cell: ({ row }) => (
                    <div className="flex items-center justify-end gap-2">
                        {canNavigate && row.original.lnnumber ? (
                            <Button
                                asChild
                                type="button"
                                size="sm"
                                variant="outline"
                            >
                                <Link
                                    href={
                                        loanSchedule(
                                            row.original.lnnumber,
                                        ).url
                                    }
                                >
                                    <CalendarClock />
                                    Schedule
                                </Link>
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled
                            >
                                <CalendarClock />
                                Schedule
                            </Button>
                        )}
                        {canNavigate && row.original.lnnumber ? (
                            <Button
                                asChild
                                type="button"
                                size="sm"
                                variant="outline"
                            >
                                <Link
                                    href={
                                        loanPayments(
                                            row.original.lnnumber,
                                        ).url
                                    }
                                >
                                    <CreditCard />
                                    Payment
                                </Link>
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                disabled
                            >
                                <CreditCard />
                                Payment
                            </Button>
                        )}
                    </div>
                ),
            },
        ],
        [canNavigate],
    );

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
    const lastLoanTransaction = formatDate(summaryValue?.lastLoanTransactionDate);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loans" />
            <div className="flex flex-col gap-6 p-4">
                <MemberDetailPageHeader
                    title="Loans"
                    subtitle="Your current loan portfolio."
                    meta={`Account No: ${member.acctno ?? '--'}`}
                    actions={
                        <Button asChild variant="ghost" size="sm">
                            <Link href={clientDashboard().url}>
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

                <MemberRecordsCard
                    title="Loans"
                    description="Full loan list with pagination."
                    isUpdating={isLoading}
                    error={loansError}
                    errorTitle="Unable to load loans"
                    onRetry={handleRetry}
                    showSkeleton={showSkeleton}
                    skeletonMobile={<MobileLoanCardSkeletonList rows={4} />}
                    skeletonDesktop={
                        <TableSkeleton
                            columns={loanTableSkeletonColumns}
                            rows={meta.perPage}
                            className="rounded-md border"
                            tableClassName="min-w-[980px]"
                        />
                    }
                    mobileWrapperClassName="space-y-3"
                    mobileContent={
                        items.length === 0 ? (
                            <div className="rounded-md border border-border bg-muted/40 px-4 py-6 text-center text-sm text-muted-foreground">
                                {loanEmptyMessage}
                            </div>
                        ) : (
                            items.map((loan, index) => (
                                <MobileLoanCard
                                    key={loan.lnnumber ?? `loan-${index}`}
                                    loan={loan}
                                    canNavigate={canNavigate}
                                />
                            ))
                        )
                    }
                    desktopContent={
                        <div className="overflow-x-auto">
                            <DataTable
                                columns={columns}
                                data={items}
                                className="min-w-[980px]"
                                emptyMessage={loanEmptyMessage}
                            />
                        </div>
                    }
                    footer={
                        showSkeleton ? (
                            <DataTablePaginationSkeleton />
                        ) : (
                            <DataTablePagination
                                page={meta.page}
                                perPage={meta.perPage}
                                total={meta.total}
                                onPageChange={handlePageChange}
                            />
                        )
                    }
                />
            </div>
        </AppLayout>
    );
}
