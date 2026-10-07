import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo } from 'react';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { MemberMobileCardSkeleton } from '@/components/member-mobile-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import {
    TableSkeleton,
    type TableSkeletonColumn,
} from '@/components/ui/table-skeleton';
import { formatCurrency, formatDate } from '@/lib/formatters';
import {
    create as loanRequestCreate,
    show as loanRequestShow,
} from '@/routes/client/loan-requests';
import type { LoanRequestListItem } from '@/types/loan-requests';

type LoanRequestRecordsCardProps = {
    items: LoanRequestListItem[];
    totalCount?: number;
    isUpdating?: boolean;
    error?: string | null;
    onRetry?: () => void;
};

const requestTableSkeletonColumns: TableSkeletonColumn[] = [
    { headerClassName: 'w-24', cellClassName: 'w-32' },
    { headerClassName: 'w-32', cellClassName: 'w-40' },
    { headerClassName: 'w-24', cellClassName: 'w-28' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-28', cellClassName: 'w-32' },
    { headerClassName: 'w-28', cellClassName: 'w-32' },
    { headerClassName: 'w-16', cellClassName: 'w-28', align: 'right' },
];

const resolveReference = (request: LoanRequestListItem): string => {
    const reference = request.reference?.trim();

    return reference && reference !== '' ? reference : '--';
};

const resolveLoanTypeLabel = (request: LoanRequestListItem): string => {
    if (request.loan_type_label_snapshot) {
        return request.loan_type_label_snapshot;
    }

    if (request.typecode) {
        return request.typecode;
    }

    return '--';
};

const resolveAmount = (request: LoanRequestListItem): string => {
    if (
        request.requested_amount === null ||
        request.requested_amount === undefined
    ) {
        return '--';
    }

    const amountValue = Number(request.requested_amount);

    if (!Number.isFinite(amountValue) || amountValue <= 0) {
        return '--';
    }

    return formatCurrency(amountValue);
};

const resolveAssignedOfficer = (request: LoanRequestListItem): string =>
    request.assigned_officer?.name ?? 'Unassigned';

const resolveTimestamp = (request: LoanRequestListItem): string =>
    formatDate(
        request.status === 'draft'
            ? request.updated_at
            : (request.submitted_at ?? request.updated_at),
    );

const LoanRequestActionButton = ({
    href,
    label,
    variant,
}: {
    href: string;
    label: string;
    variant: 'outline' | 'ghost';
}) => (
    <Button asChild size="sm" variant={variant} className="w-full sm:w-auto">
        <Link href={href}>{label}</Link>
    </Button>
);

export function LoanRequestRecordsCard({
    items,
    totalCount = items.length,
    isUpdating = false,
    error = null,
    onRetry,
}: LoanRequestRecordsCardProps) {
    const showSkeletonState = isUpdating && items.length === 0;
    const showEmptyState = !showSkeletonState && !error && items.length === 0;

    const columns = useMemo<ColumnDef<LoanRequestListItem>[]>(
        () => [
            {
                id: 'reference',
                meta: { priority: 'detail' },
                header: 'Reference',
                cell: ({ row }) => (
                    <span className="font-bold">
                        {resolveReference(row.original)}
                    </span>
                ),
            },
            {
                id: 'loan_type',
                meta: {
                    priority: 'title',
                    text: (row) => resolveLoanTypeLabel(row),
                },
                header: 'Loan type',
                cell: ({ row }) => resolveLoanTypeLabel(row.original),
            },
            {
                id: 'amount',
                meta: { priority: 'amount' },
                header: 'Amount',
                cell: ({ row }) => (
                    <span className="font-semibold tabular-nums">
                        {resolveAmount(row.original)}
                    </span>
                ),
            },
            {
                id: 'submitted',
                meta: { priority: 'detail' },
                header: 'Submitted',
                cell: ({ row }) => (
                    <span className="font-semibold tabular-nums">
                        {resolveTimestamp(row.original)}
                        {row.original.status === 'draft' ? (
                            <span className="block text-xs font-normal text-muted-foreground">
                                Updated
                            </span>
                        ) : null}
                    </span>
                ),
            },
            {
                id: 'assigned_officer',
                meta: { priority: 'detail' },
                header: 'Processor',
                cell: ({ row }) => resolveAssignedOfficer(row.original),
            },
            {
                id: 'status',
                meta: { priority: 'badge' },
                header: 'Status',
                cell: ({ row }) => (
                    <LoanRequestStatusBadge status={row.original.status} />
                ),
            },
            {
                id: 'actions',
                meta: { priority: 'action', label: 'Action' },
                header: 'Action',
                cell: ({ row }) => {
                    const isDraft = row.original.status === 'draft';

                    return (
                        <div className="flex items-center justify-end">
                            <LoanRequestActionButton
                                href={
                                    isDraft
                                        ? loanRequestCreate().url
                                        : loanRequestShow(row.original.id).url
                                }
                                label={isDraft ? 'Continue draft' : 'View'}
                                variant={isDraft ? 'outline' : 'ghost'}
                            />
                        </div>
                    );
                },
            },
        ],
        [],
    );

    return (
        <Card className="gap-0 overflow-hidden py-0">
            <div className="flex flex-wrap items-baseline gap-2 px-5 pt-[18px] pb-4">
                <h2 className="text-base font-bold">Loan requests</h2>
                <span className="text-[13px] text-muted-foreground">
                    {items.length === totalCount
                        ? `${totalCount} request${totalCount === 1 ? '' : 's'}`
                        : `${items.length} of ${totalCount} requests`}
                </span>
                <span className="ml-auto flex items-center gap-2">
                    {isUpdating ? (
                        <span className="text-xs text-muted-foreground">
                            Updating...
                        </span>
                    ) : null}
                    <Button asChild size="sm" variant="ghost">
                        <Link href={loanRequestCreate().url}>
                            Start new request
                        </Link>
                    </Button>
                </span>
            </div>
            {error ? (
                <Alert variant="destructive" className="mx-5 mb-4 w-auto">
                    <AlertTitle>Unable to load loan requests</AlertTitle>
                    <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <span>{error}</span>
                        {onRetry ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={onRetry}
                            >
                                Retry
                            </Button>
                        ) : null}
                    </AlertDescription>
                </Alert>
            ) : null}
            {showSkeletonState ? (
                <div aria-busy="true">
                    <div className="space-y-3 px-4 pb-4 md:hidden">
                        <MemberMobileCardSkeleton actionCount={1} />
                        <MemberMobileCardSkeleton actionCount={1} />
                    </div>
                    <div className="hidden overflow-x-auto md:block">
                        <TableSkeleton
                            columns={requestTableSkeletonColumns}
                            rows={4}
                            tableClassName="min-w-[920px] bg-transparent"
                        />
                    </div>
                </div>
            ) : showEmptyState ? (
                <div className="border-t border-border px-6 py-8 text-center">
                    <p className="text-sm font-medium">No loan requests yet.</p>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Start a new application when you are ready.
                    </p>
                    <div className="mt-4 flex justify-center">
                        <Button asChild>
                            <Link href={loanRequestCreate().url}>
                                Request loan
                            </Link>
                        </Button>
                    </div>
                </div>
            ) : items.length === 0 ? null : (
                <div className="md:overflow-x-auto">
                    <DataTable
                        columns={columns}
                        data={items}
                        className="rounded-none border-0 border-t border-border bg-transparent md:min-w-[920px]"
                        emptyMessage="No loan requests found."
                    />
                </div>
            )}
        </Card>
    );
}
