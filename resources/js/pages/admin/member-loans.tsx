import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Banknote, CalendarClock, Clock, CreditCard } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    MemberDetailPrimaryCard,
    MemberDetailSupportingCard,
} from '@/components/member-detail-summary-cards';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import {
    DataTablePagination,
    DataTablePaginationSkeleton,
} from '@/components/ui/data-table-pagination';
import { Skeleton } from '@/components/ui/skeleton';
import { TableSkeleton } from '@/components/ui/table-skeleton';
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
import type {
    MemberAccountsSummary,
    MemberLoan,
    MemberLoansResponse,
} from '@/types/admin';

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

const loanTableSkeletonColumns = [
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-16', cellClassName: 'w-20' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-16', cellClassName: 'w-20' },
    { headerClassName: 'w-12', cellClassName: 'h-8 w-28', align: 'right' },
];

const MobileLoanCardSkeleton = () => (
    <div className="rounded-lg border border-border bg-card p-4">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-2">
                <Skeleton className="h-4 w-24" />
                <Skeleton className="h-3 w-20" />
            </div>
            <div className="space-y-2 text-right">
                <Skeleton className="ml-auto h-3 w-16" />
                <Skeleton className="ml-auto h-6 w-20" />
            </div>
        </div>
        <div className="mt-3 space-y-2 rounded-md border border-border/60 bg-muted/40 p-3">
            {Array.from({ length: 3 }).map((_, index) => (
                <div
                    key={`loan-card-meta-${index}`}
                    className="flex items-center justify-between"
                >
                    <Skeleton className="h-3 w-20" />
                    <Skeleton className="h-4 w-24" />
                </div>
            ))}
        </div>
        <div className="mt-3 flex flex-col gap-2 sm:flex-row">
            <Skeleton className="h-8 w-full sm:w-24" />
            <Skeleton className="h-8 w-full sm:w-24" />
        </div>
    </div>
);

const MobileLoanCardSkeletonList = ({ rows = 4 }: { rows?: number }) => (
    <div className="space-y-3">
        {Array.from({ length: rows }).map((_, index) => (
            <MobileLoanCardSkeleton key={`loan-card-skeleton-${index}`} />
        ))}
    </div>
);

const MobileLoanCard = ({
    loan,
    memberId,
    canNavigate,
}: {
    loan: MemberLoan;
    memberId: number;
    canNavigate: boolean;
}) => (
    <div className="rounded-lg border border-border bg-card p-4">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-1">
                <p className="text-sm font-semibold">
                    {loan.lnnumber ?? '--'}
                </p>
                <p className="text-xs text-muted-foreground">
                    {loan.lntype ?? '--'}
                </p>
            </div>
            <div className="text-right">
                <p className="text-xs text-muted-foreground">Balance</p>
                <p className="text-lg font-semibold tabular-nums">
                    {formatCurrency(loan.balance)}
                </p>
            </div>
        </div>
        <div className="mt-3 rounded-md border border-border/60 bg-muted/40 p-3">
            <div className="flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Last move</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatDate(loan.lastmove)}
                </span>
            </div>
            <div className="mt-2 flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Principal</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatCurrency(loan.principal)}
                </span>
            </div>
            <div className="mt-2 flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Initial</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatCurrency(loan.initial)}
                </span>
            </div>
        </div>
        <div className="mt-3 flex flex-col gap-2 sm:flex-row">
            {canNavigate && loan.lnnumber ? (
                <Button
                    asChild
                    type="button"
                    size="sm"
                    variant="outline"
                    className="w-full sm:w-auto"
                >
                    <Link
                        href={
                            loanSchedule({
                                user: memberId,
                                loanNumber: loan.lnnumber,
                            }).url
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
                    <Link
                        href={
                            loanPayments({
                                user: memberId,
                                loanNumber: loan.lnnumber,
                            }).url
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
                    className="w-full sm:w-auto"
                    disabled
                >
                    <CreditCard />
                    Payment
                </Button>
            )}
        </div>
    </div>
);

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

    const {
        items,
        meta,
        loading,
        error,
        refresh,
    } = useMemberLoans(member.user_id, page, perPage, {
        initial: loans,
        enabled: true,
    });

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
                            <Button asChild type="button" size="sm" variant="outline">
                                <Link
                                    href={
                                        loanSchedule({
                                            user: member.user_id,
                                            loanNumber: row.original.lnnumber,
                                        }).url
                                    }
                                >
                                    <CalendarClock />
                                    Schedule
                                </Link>
                            </Button>
                        ) : (
                            <Button type="button" size="sm" variant="outline" disabled>
                                <CalendarClock />
                                Schedule
                            </Button>
                        )}
                        {canNavigate && row.original.lnnumber ? (
                            <Button asChild type="button" size="sm" variant="outline">
                                <Link
                                    href={
                                        loanPayments({
                                            user: member.user_id,
                                            loanNumber: row.original.lnnumber,
                                        }).url
                                    }
                                >
                                    <CreditCard />
                                    Payment
                                </Link>
                            </Button>
                        ) : (
                            <Button type="button" size="sm" variant="outline" disabled>
                                <CreditCard />
                                Payment
                            </Button>
                        )}
                    </div>
                ),
            },
        ],
        [canNavigate, member.user_id],
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Members', href: membersIndex().url },
        { title: 'Member profile', href: showMember(member.user_id).url },
        { title: 'Loans', href: memberLoans(member.user_id).url },
    ];
    const loanBalance = formatCurrency(summary.loanBalanceLeft);
    const lastLoanTransaction = formatDate(summary.lastLoanTransactionDate);
    const loanEmptyMessage = loading
        ? 'Loading loans...'
        : 'No loans found.';
    const showSkeleton = loading && items.length === 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Member Loans" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold">Member Loans</h1>
                        <p className="text-sm text-muted-foreground">
                            Loan portfolio for{' '}
                            {member.member_name ?? 'this member'}.
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Account No: {member.acctno ?? '--'}
                        </p>
                    </div>
                    <Button asChild variant="ghost" size="sm">
                        <Link href={showMember(member.user_id).url}>
                            Back to profile
                        </Link>
                    </Button>
                </div>

                {!member.acctno ? (
                    <Alert>
                        <AlertTitle>Account number missing</AlertTitle>
                        <AlertDescription>
                            Add an account number to view loan details.
                        </AlertDescription>
                    </Alert>
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

                <Card>
                    <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle>Loans</CardTitle>
                            <CardDescription>
                                Full loan list with pagination.
                            </CardDescription>
                        </div>
                        {loading ? (
                            <span className="text-xs text-muted-foreground">
                                Updating...
                            </span>
                        ) : null}
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {error ? (
                            <Alert variant="destructive">
                                <AlertTitle>Unable to load loans</AlertTitle>
                                <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <span>{error}</span>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => void refresh()}
                                    >
                                        Retry
                                    </Button>
                                </AlertDescription>
                            </Alert>
                        ) : null}
                        {showSkeleton ? (
                            <>
                                <div className="md:hidden" aria-busy="true">
                                    <MobileLoanCardSkeletonList rows={4} />
                                </div>
                                <div className="hidden md:block" aria-busy="true">
                                    <TableSkeleton
                                        columns={loanTableSkeletonColumns}
                                        rows={perPage}
                                        className="rounded-md border"
                                        tableClassName="min-w-[980px]"
                                    />
                                </div>
                            </>
                        ) : (
                            <>
                                <div className="space-y-3 md:hidden">
                                    {items.length === 0 ? (
                                        <div className="rounded-md border border-border bg-muted/40 px-4 py-6 text-center text-sm text-muted-foreground">
                                            {loanEmptyMessage}
                                        </div>
                                    ) : (
                                        items.map((loan, index) => (
                                            <MobileLoanCard
                                                key={
                                                    loan.lnnumber ??
                                                    `loan-${index}`
                                                }
                                                loan={loan}
                                                memberId={member.user_id}
                                                canNavigate={canNavigate}
                                            />
                                        ))
                                    )}
                                </div>
                                <div className="hidden md:block">
                                    <div className="overflow-x-auto">
                                        <DataTable
                                            columns={columns}
                                            data={items}
                                            className="min-w-[980px]"
                                            emptyMessage={loanEmptyMessage}
                                        />
                                    </div>
                                </div>
                            </>
                        )}
                        {!error ? (
                            showSkeleton ? (
                                <DataTablePaginationSkeleton />
                            ) : (
                                <DataTablePagination
                                    page={meta.page}
                                    perPage={meta.perPage}
                                    total={meta.total}
                                    onPageChange={setPage}
                                />
                            )
                        ) : null}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
