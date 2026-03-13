import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Clock, Eye, PiggyBank } from 'lucide-react';
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
import { useMemberSavings } from '@/hooks/admin/use-member-savings';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDate } from '@/lib/formatters';
import { dashboard } from '@/routes/admin';
import { savings as memberSavings, show as showMember } from '@/routes/admin/members';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberAccountsSummary,
    MemberSavings,
    MemberSavingsResponse,
} from '@/types/admin';

type MemberSummary = {
    user_id: number;
    member_name: string | null;
    acctno: string | null;
};

type Props = {
    member: MemberSummary;
    summary: MemberAccountsSummary;
    savings: MemberSavingsResponse;
};

const savingsTableSkeletonColumns = [
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-16', cellClassName: 'w-20' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-12', cellClassName: 'h-8 w-28', align: 'right' },
];

const MobileSavingsCardSkeleton = () => (
    <div className="rounded-lg border border-border bg-card p-4">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-2">
                <Skeleton className="h-4 w-24" />
                <Skeleton className="h-3 w-20" />
            </div>
            <div className="space-y-2 text-right">
                <Skeleton className="ml-auto h-3 w-24" />
                <Skeleton className="ml-auto h-6 w-20" />
            </div>
        </div>
        <div className="mt-3 space-y-2 rounded-md border border-border/60 bg-muted/40 p-3">
            {Array.from({ length: 3 }).map((_, index) => (
                <div
                    key={`savings-card-meta-${index}`}
                    className="flex items-center justify-between"
                >
                    <Skeleton className="h-3 w-20" />
                    <Skeleton className="h-4 w-24" />
                </div>
            ))}
        </div>
        <div className="mt-3">
            <Skeleton className="h-8 w-full sm:w-28" />
        </div>
    </div>
);

const MobileSavingsCardSkeletonList = ({ rows = 4 }: { rows?: number }) => (
    <div className="space-y-3">
        {Array.from({ length: rows }).map((_, index) => (
            <MobileSavingsCardSkeleton
                key={`savings-card-skeleton-${index}`}
            />
        ))}
    </div>
);

const MobileSavingsCard = ({
    savings,
    viewSavingsAvailable,
}: {
    savings: MemberSavings;
    viewSavingsAvailable: boolean;
}) => (
    <div className="rounded-lg border border-border bg-card p-4">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-1">
                <p className="text-sm font-semibold">
                    {savings.svnumber ?? '--'}
                </p>
                <p className="text-xs text-muted-foreground">
                    {savings.svtype ?? '--'}
                </p>
            </div>
            <div className="text-right">
                <p className="text-xs text-muted-foreground">Withdrawable</p>
                <p className="text-lg font-semibold tabular-nums">
                    {formatCurrency(savings.wbalance)}
                </p>
            </div>
        </div>
        <div className="mt-3 rounded-md border border-border/60 bg-muted/40 p-3">
            <div className="flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Last move</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatDate(savings.lastmove)}
                </span>
            </div>
            <div className="mt-2 flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Balance</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatCurrency(savings.balance)}
                </span>
            </div>
            <div className="mt-2 flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Mortuary</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatCurrency(savings.mortuary)}
                </span>
            </div>
        </div>
        <div className="mt-3">
            <Button
                type="button"
                size="sm"
                variant="outline"
                className="w-full sm:w-auto"
                disabled={!viewSavingsAvailable}
                title="View savings flow not available yet."
            >
                <Eye />
                View savings
            </Button>
        </div>
    </div>
);

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

    const viewSavingsAvailable = false;

    const columns = useMemo<ColumnDef<MemberSavings>[]>(
        () => [
            {
                accessorKey: 'svnumber',
                header: 'Savings No',
                cell: ({ row }) => row.original.svnumber ?? '--',
            },
            {
                accessorKey: 'svtype',
                header: 'Type',
                cell: ({ row }) => row.original.svtype ?? '--',
            },
            {
                accessorKey: 'mortuary',
                header: 'Mortuary',
                cell: ({ row }) => formatCurrency(row.original.mortuary),
            },
            {
                accessorKey: 'balance',
                header: 'Balance',
                cell: ({ row }) => formatCurrency(row.original.balance),
            },
            {
                accessorKey: 'wbalance',
                header: 'Withdrawable',
                cell: ({ row }) => formatCurrency(row.original.wbalance),
            },
            {
                accessorKey: 'lastmove',
                header: 'Last move',
                cell: ({ row }) => formatDate(row.original.lastmove),
            },
            {
                id: 'actions',
                header: '',
                cell: () => (
                    <div className="flex items-center justify-end">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={!viewSavingsAvailable}
                            title="View savings flow not available yet."
                        >
                            <Eye />
                            View savings
                        </Button>
                    </div>
                ),
            },
        ],
        [viewSavingsAvailable],
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin Dashboard', href: dashboard().url },
        { title: 'Member profile', href: showMember(member.user_id).url },
        { title: 'Savings', href: memberSavings(member.user_id).url },
    ];
    const currentSavings = formatCurrency(summary.currentSavingsBalance);
    const lastSavingsTransaction = formatDate(
        summary.lastSavingsTransactionDate,
    );
    const savingsEmptyMessage = loading
        ? 'Loading savings...'
        : 'No savings found.';
    const showSkeleton = loading && items.length === 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Member Savings" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold">
                            Member Savings
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Savings overview for{' '}
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
                            Add an account number to view savings details.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-4 md:grid-cols-2">
                    <MemberDetailPrimaryCard
                        title="Total Current Savings"
                        value={currentSavings}
                        helper="Sum of current savings balances."
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

                <Card>
                    <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle>Savings</CardTitle>
                            <CardDescription>
                                Full savings list with pagination.
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
                                <AlertTitle>Unable to load savings</AlertTitle>
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
                                    <MobileSavingsCardSkeletonList rows={4} />
                                </div>
                                <div className="hidden md:block" aria-busy="true">
                                    <TableSkeleton
                                        columns={savingsTableSkeletonColumns}
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
                                            {savingsEmptyMessage}
                                        </div>
                                    ) : (
                                        items.map((savingsRow, index) => (
                                            <MobileSavingsCard
                                                key={
                                                    savingsRow.svnumber ??
                                                    `savings-${index}`
                                                }
                                                savings={savingsRow}
                                                viewSavingsAvailable={
                                                    viewSavingsAvailable
                                                }
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
                                            emptyMessage={savingsEmptyMessage}
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
