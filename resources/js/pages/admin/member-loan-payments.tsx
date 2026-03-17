import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    Banknote,
    CalendarCheck,
    Clock,
    Download,
    Filter,
    Receipt,
} from 'lucide-react';
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
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { TableSkeleton } from '@/components/ui/table-skeleton';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useMemberLoanPayments } from '@/hooks/admin/use-member-loan-payments';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDate } from '@/lib/formatters';
import {
    loanPayments,
    loanPaymentsExport,
    loanSchedule,
    loans as memberLoans,
    show as showMember,
} from '@/routes/admin/members';
import { index as membersIndex } from '@/routes/admin/watchlist';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberLoan,
    MemberLoanPayment,
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

const paymentTableSkeletonColumns = [
    { headerClassName: 'w-24', cellClassName: 'w-28' },
    { headerClassName: 'w-24', cellClassName: 'w-28' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
];

const MobilePaymentCardSkeleton = () => (
    <div className="rounded-lg border border-border bg-card p-4">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-2">
                <Skeleton className="h-4 w-28" />
                <Skeleton className="h-3 w-24" />
            </div>
            <div className="space-y-2 text-right">
                <Skeleton className="ml-auto h-3 w-16" />
                <Skeleton className="ml-auto h-6 w-20" />
            </div>
        </div>
        <div className="mt-3 space-y-2 rounded-md border border-border/60 bg-muted/40 p-3">
            {Array.from({ length: 2 }).map((_, index) => (
                <div
                    key={`payment-card-meta-${index}`}
                    className="flex items-center justify-between"
                >
                    <Skeleton className="h-3 w-20" />
                    <Skeleton className="h-4 w-24" />
                </div>
            ))}
        </div>
    </div>
);

const MobilePaymentCardSkeletonList = ({ rows = 4 }: { rows?: number }) => (
    <div className="space-y-3">
        {Array.from({ length: rows }).map((_, index) => (
            <MobilePaymentCardSkeleton key={`payment-card-${index}`} />
        ))}
    </div>
);

const MobilePaymentCard = ({ payment }: { payment: MemberLoanPayment }) => (
    <div className="rounded-lg border border-border bg-card p-4">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-1">
                <p className="text-sm font-semibold">
                    {payment.reference_no ?? payment.control_no ?? '--'}
                </p>
                <p className="text-xs text-muted-foreground">
                    {formatDate(payment.date_in)}
                </p>
            </div>
            <div className="text-right">
                <p className="text-xs text-muted-foreground">Payment</p>
                <p className="text-lg font-semibold tabular-nums">
                    {formatCurrency(payment.payment_amount)}
                </p>
            </div>
        </div>
        <div className="mt-3 rounded-md border border-border/60 bg-muted/40 p-3">
            <div className="flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Balance</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatCurrency(payment.balance)}
                </span>
            </div>
            <div className="mt-2 flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Principal</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatCurrency(payment.principal)}
                </span>
            </div>
        </div>
    </div>
);

const presetRanges: Array<{ value: MemberLoanPaymentsFilters['range']; label: string }> = [
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

    const columns = useMemo<ColumnDef<MemberLoanPayment>[]>(
        () => [
            {
                accessorKey: 'date_in',
                header: 'Transaction Date',
                cell: ({ row }) => formatDate(row.original.date_in),
            },
            {
                accessorKey: 'reference_no',
                header: 'Reference No',
                cell: ({ row }) =>
                    row.original.reference_no ?? row.original.control_no ?? '--',
            },
            {
                accessorKey: 'principal',
                header: 'Principal',
                cell: ({ row }) => formatCurrency(row.original.principal),
            },
            {
                accessorKey: 'payment_amount',
                header: 'Payment',
                cell: ({ row }) => formatCurrency(row.original.payment_amount),
            },
            {
                accessorKey: 'balance',
                header: 'Balance',
                cell: ({ row }) => formatCurrency(row.original.balance),
            },
        ],
        [],
    );

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

    const buildExportUrl = (format: 'pdf' | 'csv' | 'xlsx') =>
        loanPaymentsExport(
            { user: member.user_id, loanNumber: loanNumber ?? '' },
            {
                query: {
                    format,
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
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold">
                            Loan Payments
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {member.member_name ?? 'Member'} - Loan{' '}
                            {loan.lnnumber ?? '--'}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Account No: {member.acctno ?? '--'} | Loan Type:{' '}
                            {loan.lntype ?? '--'}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <ToggleGroup
                            type="single"
                            value="payments"
                            variant="outline"
                            size="sm"
                            className="rounded-md bg-muted/40 p-1"
                            aria-label="Loan detail views"
                        >
                            {canNavigate ? (
                                <ToggleGroupItem
                                    value="schedule"
                                    asChild
                                    className="data-[state=on]:font-semibold"
                                >
                                    <Link
                                        href={
                                            loanSchedule({
                                                user: member.user_id,
                                                loanNumber: loanNumber ?? '',
                                            }).url
                                        }
                                    >
                                        Schedule
                                    </Link>
                                </ToggleGroupItem>
                            ) : (
                                <ToggleGroupItem
                                    value="schedule"
                                    disabled
                                    className="data-[state=on]:font-semibold"
                                >
                                    Schedule
                                </ToggleGroupItem>
                            )}
                            {canNavigate ? (
                                <ToggleGroupItem
                                    value="payments"
                                    asChild
                                    className="data-[state=on]:font-semibold"
                                >
                                    <Link
                                        href={
                                            loanPayments({
                                                user: member.user_id,
                                                loanNumber: loanNumber ?? '',
                                            }).url
                                        }
                                        aria-current="page"
                                    >
                                        Payments
                                        <span className="sr-only">
                                            {' '}
                                            (current)
                                        </span>
                                    </Link>
                                </ToggleGroupItem>
                            ) : (
                                <ToggleGroupItem
                                    value="payments"
                                    disabled
                                    className="data-[state=on]:font-semibold"
                                >
                                    Payments
                                </ToggleGroupItem>
                            )}
                        </ToggleGroup>
                        <Button asChild variant="outline" size="sm">
                            <Link href={memberLoans(member.user_id).url}>
                                Back to loans
                            </Link>
                        </Button>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={showMember(member.user_id).url}>
                                Back to profile
                            </Link>
                        </Button>
                    </div>
                </div>

                {!member.acctno || !loanNumber ? (
                    <Alert>
                        <AlertTitle>Loan not available</AlertTitle>
                        <AlertDescription>
                            This member needs a valid loan number and account
                            number before payments can be displayed.
                        </AlertDescription>
                    </Alert>
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

                <Card>
                    <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle>Payment Filters</CardTitle>
                            <CardDescription>
                                Filter and export loan payments.
                            </CardDescription>
                        </div>
                        {loading ? (
                            <span className="text-xs text-muted-foreground">
                                Updating...
                            </span>
                        ) : null}
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <Filter className="h-4 w-4 text-muted-foreground" />
                            {presetRanges.map((preset) => (
                                <Button
                                    key={preset.value}
                                    type="button"
                                    size="sm"
                                    variant={
                                        filters.range === preset.value
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() => updateRange(preset.value)}
                                >
                                    {preset.label}
                                </Button>
                            ))}
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div className="space-y-2">
                                <p className="text-xs font-medium text-muted-foreground">
                                    Start date
                                </p>
                                <Input
                                    type="date"
                                    value={filters.start ?? ''}
                                    onChange={(event) =>
                                        updateStart(event.target.value)
                                    }
                                    disabled={filters.range !== 'custom'}
                                />
                            </div>
                            <div className="space-y-2">
                                <p className="text-xs font-medium text-muted-foreground">
                                    End date
                                </p>
                                <Input
                                    type="date"
                                    value={filters.end ?? ''}
                                    onChange={(event) =>
                                        updateEnd(event.target.value)
                                    }
                                    disabled={filters.range !== 'custom'}
                                />
                            </div>
                            <div className="rounded-md border border-border/60 bg-muted/40 p-3">
                                <p className="text-xs text-muted-foreground">
                                    Opening / Closing
                                </p>
                                <p className="text-sm font-medium tabular-nums">
                                    {formatCurrency(openingBalance)} /{' '}
                                    {formatCurrency(closingBalance)}
                                </p>
                            </div>
                        </div>
                        {filters.range === 'custom' && !filtersReady ? (
                            <p className="text-xs text-muted-foreground">
                                Select a start and end date to apply the custom
                                range.
                            </p>
                        ) : null}
                        <div className="flex flex-wrap items-center gap-2">
                            <Download className="h-4 w-4 text-muted-foreground" />
                            <Button
                                asChild
                                size="sm"
                                variant="outline"
                                disabled={!filtersReady || !loanNumber}
                            >
                                <a href={buildExportUrl('pdf')}>Export PDF</a>
                            </Button>
                            <Button
                                asChild
                                size="sm"
                                variant="outline"
                                disabled={!filtersReady || !loanNumber}
                            >
                                <a href={buildExportUrl('csv')}>Export CSV</a>
                            </Button>
                            <Button
                                asChild
                                size="sm"
                                variant="outline"
                                disabled={!filtersReady || !loanNumber}
                            >
                                <a href={buildExportUrl('xlsx')}>Export Excel</a>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle>Loan Payments</CardTitle>
                            <CardDescription>
                                Payment ledger for this loan.
                            </CardDescription>
                        </div>
                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                            <Receipt className="h-4 w-4" />
                            <span>{meta.total} records</span>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {error ? (
                            <Alert variant="destructive">
                                <AlertTitle>Unable to load payments</AlertTitle>
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
                                    <MobilePaymentCardSkeletonList rows={4} />
                                </div>
                                <div className="hidden md:block" aria-busy="true">
                                    <TableSkeleton
                                        columns={paymentTableSkeletonColumns}
                                        rows={perPage}
                                        className="rounded-md border"
                                        tableClassName="min-w-[840px]"
                                    />
                                </div>
                            </>
                        ) : (
                            <>
                                <div className="space-y-3 md:hidden">
                                    {items.length === 0 ? (
                                        <div className="rounded-md border border-border bg-muted/40 px-4 py-6 text-center text-sm text-muted-foreground">
                                            No payments found for this period.
                                        </div>
                                    ) : (
                                        items.map((payment, index) => (
                                            <MobilePaymentCard
                                                key={
                                                    payment.reference_no ??
                                                    payment.control_no ??
                                                    `payment-${index}`
                                                }
                                                payment={payment}
                                            />
                                        ))
                                    )}
                                </div>
                                <div className="hidden md:block">
                                    <div className="overflow-x-auto">
                                        <DataTable
                                            columns={columns}
                                            data={items}
                                            className="min-w-[840px]"
                                            emptyMessage="No payments found for this period."
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
