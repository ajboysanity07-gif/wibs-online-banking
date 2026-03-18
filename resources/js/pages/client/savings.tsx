import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Clock, PiggyBank } from 'lucide-react';
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
import { dashboard as clientDashboard, savings as clientSavings } from '@/routes/client';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberAccountsSummary,
    MemberSavingsLedgerEntry,
    MemberSavingsLedgerResponse,
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
    savings: MemberSavingsLedgerResponse | null;
    savingsError?: string | null;
};

const savingsTableSkeletonColumns = [
    { headerClassName: 'w-24', cellClassName: 'w-32' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-16', cellClassName: 'w-20' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
];

const MobileSavingsCardSkeletonList = ({ rows = 4 }: { rows?: number }) => (
    <div className="space-y-3">
        {Array.from({ length: rows }).map((_, index) => (
            <MemberMobileCardSkeleton
                key={`savings-card-skeleton-${index}`}
                valueLabelClassName="w-24"
            />
        ))}
    </div>
);

const MobileSavingsCard = ({
    savings,
}: {
    savings: MemberSavingsLedgerEntry;
}) => (
    <MemberMobileCard
        title={savings.svnumber ?? '--'}
        subtitle={savings.svtype ?? '--'}
        valueLabel="Balance"
        value={formatCurrency(savings.balance)}
        meta={[
            {
                label: 'Transaction date',
                value: formatDate(savings.date_in),
            },
            { label: 'Deposit', value: formatCurrency(savings.deposit) },
            {
                label: 'Withdrawal',
                value: formatCurrency(savings.withdrawal),
            },
        ]}
    />
);

const fallbackMeta: PaginationMeta = {
    page: 1,
    perPage: 10,
    total: 0,
    lastPage: 1,
};

export default function MemberSavings({
    member,
    summary,
    savings,
    savingsError = null,
}: Props) {
    const [loading, setLoading] = useState(false);
    const items = savings?.items ?? [];
    const meta = savings?.meta ?? fallbackMeta;
    const summaryValue = summary ?? null;
    const isLoading = loading || (savings === null && !savingsError);
    const showSkeleton = isLoading && items.length === 0;
    const savingsEmptyMessage = isLoading
        ? 'Loading savings...'
        : 'No savings transactions found.';

    const columns = useMemo<ColumnDef<MemberSavingsLedgerEntry>[]>(
        () => [
            {
                accessorKey: 'date_in',
                header: 'Transaction Date',
                cell: ({ row }) => formatDate(row.original.date_in),
            },
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
                accessorKey: 'deposit',
                header: 'Deposit',
                cell: ({ row }) => formatCurrency(row.original.deposit),
            },
            {
                accessorKey: 'withdrawal',
                header: 'Withdrawal',
                cell: ({ row }) => formatCurrency(row.original.withdrawal),
            },
            {
                accessorKey: 'balance',
                header: 'Balance',
                cell: ({ row }) => formatCurrency(row.original.balance),
            },
        ],
        [],
    );

    const reloadPage = (nextPage: number) => {
        setLoading(true);
        router.get(
            clientSavings().url,
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
        { title: 'Savings', href: clientSavings().url },
    ];
    const currentSavings = formatCurrency(summaryValue?.currentPersonalSavings);
    const lastSavingsTransaction = formatDate(
        summaryValue?.lastSavingsTransactionDate,
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Savings" />
            <div className="flex flex-col gap-6 p-4">
                <MemberDetailPageHeader
                    title="Savings"
                    subtitle="Your savings ledger activity."
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
                        description="Add an account number to view savings details."
                    />
                ) : null}

                <div className="grid gap-4 md:grid-cols-2">
                    <MemberDetailPrimaryCard
                        title="Personal Savings Balance"
                        value={currentSavings}
                        helper="Latest personal savings balance."
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

                <MemberRecordsCard
                    title="Savings"
                    description="Savings ledger activity with pagination."
                    isUpdating={isLoading}
                    error={savingsError}
                    errorTitle="Unable to load savings"
                    onRetry={handleRetry}
                    showSkeleton={showSkeleton}
                    skeletonMobile={<MobileSavingsCardSkeletonList rows={4} />}
                    skeletonDesktop={
                        <TableSkeleton
                            columns={savingsTableSkeletonColumns}
                            rows={meta.perPage}
                            className="rounded-md border"
                            tableClassName="min-w-[840px]"
                        />
                    }
                    mobileWrapperClassName="space-y-3"
                    mobileContent={
                        items.length === 0 ? (
                            <div className="rounded-md border border-border bg-muted/40 px-4 py-6 text-center text-sm text-muted-foreground">
                                {savingsEmptyMessage}
                            </div>
                        ) : (
                            items.map((savingsRow, index) => (
                                <MobileSavingsCard
                                    key={`${savingsRow.svnumber ?? 'savings'}-${savingsRow.date_in ?? index}`}
                                    savings={savingsRow}
                                />
                            ))
                        )
                    }
                    desktopContent={
                        <div className="overflow-x-auto">
                            <DataTable
                                columns={columns}
                                data={items}
                                className="min-w-[840px]"
                                emptyMessage={savingsEmptyMessage}
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
