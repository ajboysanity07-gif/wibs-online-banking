import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { CalendarClock, CreditCard } from 'lucide-react';
import { useMemo } from 'react';
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
import {
    TableSkeleton,
    type TableSkeletonColumn,
} from '@/components/ui/table-skeleton';
import { formatCurrency, formatDate } from '@/lib/formatters';
import type { MemberLoan, PaginationMeta } from '@/types/admin';

type LoanHrefBuilder = (loanNumber: string | number | null) => string | null;

type MemberLoanRecordsCardProps = {
    items: MemberLoan[];
    meta: PaginationMeta;
    isUpdating?: boolean;
    error?: string | null;
    onRetry?: () => void;
    onPageChange: (page: number) => void;
    canNavigate: boolean;
    buildScheduleHref: LoanHrefBuilder;
    buildPaymentsHref: LoanHrefBuilder;
    emptyMessage?: string;
    showSkeleton?: boolean;
};

const loanTableSkeletonColumns: TableSkeletonColumn[] = [
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-16', cellClassName: 'w-20' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-16', cellClassName: 'w-20' },
    { headerClassName: 'w-12', cellClassName: 'h-8 w-28', align: 'right' },
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

const LoanActionButton = ({
    href,
    label,
    icon: Icon,
    disabled = false,
    className,
}: {
    href: string | null;
    label: string;
    icon: typeof CalendarClock;
    disabled?: boolean;
    className?: string;
}) => {
    if (disabled || !href) {
        return (
            <Button
                type="button"
                size="sm"
                variant="outline"
                className={className}
                disabled
            >
                <Icon />
                {label}
            </Button>
        );
    }

    return (
        <Button
            asChild
            type="button"
            size="sm"
            variant="outline"
            className={className}
        >
            <Link href={href}>
                <Icon />
                {label}
            </Link>
        </Button>
    );
};

const MobileLoanCard = ({
    loan,
    canNavigate,
    scheduleHref,
    paymentsHref,
}: {
    loan: MemberLoan;
    canNavigate: boolean;
    scheduleHref: string | null;
    paymentsHref: string | null;
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
                <LoanActionButton
                    href={scheduleHref}
                    label="Schedule"
                    icon={CalendarClock}
                    disabled={!canNavigate || !loan.lnnumber}
                    className="w-full sm:w-auto"
                />
                <LoanActionButton
                    href={paymentsHref}
                    label="Payment"
                    icon={CreditCard}
                    disabled={!canNavigate || !loan.lnnumber}
                    className="w-full sm:w-auto"
                />
            </div>
        }
    />
);

export function MemberLoanRecordsCard({
    items,
    meta,
    isUpdating = false,
    error = null,
    onRetry,
    onPageChange,
    canNavigate,
    buildScheduleHref,
    buildPaymentsHref,
    emptyMessage,
    showSkeleton,
}: MemberLoanRecordsCardProps) {
    const loanEmptyMessage =
        emptyMessage ?? (isUpdating ? 'Loading loans...' : 'No loans found.');
    const showSkeletonState =
        showSkeleton ?? (isUpdating && items.length === 0);

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
                cell: ({ row }) => {
                    const scheduleHref = buildScheduleHref(
                        row.original.lnnumber,
                    );
                    const paymentsHref = buildPaymentsHref(
                        row.original.lnnumber,
                    );

                    return (
                        <div className="flex items-center justify-end gap-2">
                            <LoanActionButton
                                href={scheduleHref}
                                label="Schedule"
                                icon={CalendarClock}
                                disabled={!canNavigate || !row.original.lnnumber}
                            />
                            <LoanActionButton
                                href={paymentsHref}
                                label="Payment"
                                icon={CreditCard}
                                disabled={!canNavigate || !row.original.lnnumber}
                            />
                        </div>
                    );
                },
            },
        ],
        [buildPaymentsHref, buildScheduleHref, canNavigate],
    );

    return (
        <MemberRecordsCard
            title="Loans"
            description="Full loan list with pagination."
            isUpdating={isUpdating}
            error={error}
            errorTitle="Unable to load loans"
            onRetry={onRetry}
            showSkeleton={showSkeletonState}
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
                            scheduleHref={buildScheduleHref(loan.lnnumber)}
                            paymentsHref={buildPaymentsHref(loan.lnnumber)}
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
                showSkeletonState ? (
                    <DataTablePaginationSkeleton />
                ) : (
                    <DataTablePagination
                        page={meta.page}
                        perPage={meta.perPage}
                        total={meta.total}
                        onPageChange={onPageChange}
                    />
                )
            }
        />
    );
}
