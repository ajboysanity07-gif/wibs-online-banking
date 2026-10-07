import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { CalendarClock, CreditCard } from 'lucide-react';
import { useMemo } from 'react';
import { MemberMobileCardSkeleton } from '@/components/member-mobile-card';
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
    { headerClassName: 'w-12', cellClassName: 'h-8 w-40', align: 'right' },
];

const MobileLoanCardSkeletonList = ({ rows = 4 }: { rows?: number }) => (
    <div className="space-y-3">
        {Array.from({ length: rows }).map((_, index) => (
            <MemberMobileCardSkeleton
                key={`loan-card-skeleton-${index}`}
                actionCount={3}
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
                meta: {
                    priority: 'title',
                    text: (row) => String(row.lnnumber ?? ''),
                },
                header: 'Loan No',
                cell: ({ row }) => row.original.lnnumber ?? '--',
            },
            {
                accessorKey: 'lntype',
                meta: { priority: 'detail' },
                header: 'Type',
                cell: ({ row }) => row.original.lntype ?? '--',
            },
            {
                accessorKey: 'principal',
                meta: { priority: 'detail' },
                header: 'Principal',
                cell: ({ row }) => formatCurrency(row.original.principal),
            },
            {
                accessorKey: 'balance',
                meta: { priority: 'amount' },
                header: 'Balance',
                cell: ({ row }) => formatCurrency(row.original.balance),
            },
            {
                accessorKey: 'lastmove',
                meta: { priority: 'detail' },
                header: 'Last move',
                cell: ({ row }) => formatDate(row.original.lastmove),
            },
            {
                accessorKey: 'initial',
                meta: { priority: 'detail' },
                header: 'Initial',
                cell: ({ row }) => formatCurrency(row.original.initial),
            },
            {
                id: 'actions',
                meta: { priority: 'action', label: 'Actions' },
                header: '',
                cell: ({ row }) => {
                    const scheduleHref = buildScheduleHref(
                        row.original.lnnumber,
                    );
                    const paymentsHref = buildPaymentsHref(
                        row.original.lnnumber,
                    );

                    return (
                        <div className="flex flex-wrap items-center justify-end gap-2">
                            <LoanActionButton
                                href={scheduleHref}
                                label="Schedule"
                                icon={CalendarClock}
                                disabled={
                                    !canNavigate || !row.original.lnnumber
                                }
                            />
                            <LoanActionButton
                                href={paymentsHref}
                                label="Payment"
                                icon={CreditCard}
                                disabled={
                                    !canNavigate || !row.original.lnnumber
                                }
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
                    className="rounded-xl border border-border bg-card"
                    tableClassName="min-w-[980px]"
                />
            }
            mobileWrapperClassName="space-y-3"
            body={
                <div className="md:overflow-x-auto">
                    <DataTable
                        columns={columns}
                        data={items}
                        className="md:min-w-[1080px]"
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
