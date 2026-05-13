import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo } from 'react';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import {
    MemberMobileCard,
    MemberMobileCardSkeleton,
} from '@/components/member-mobile-card';
import { MemberRecordsCard } from '@/components/member-records-card';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import {
    TableSkeleton,
    type TableSkeletonColumn,
} from '@/components/ui/table-skeleton';
import { formatCurrency, formatDateTime } from '@/lib/formatters';
import {
    create as loanRequestCreate,
    show as loanRequestShow,
} from '@/routes/client/loan-requests';
import type { LoanRequestListItem } from '@/types/loan-requests';

type LoanRequestRecordsCardProps = {
    items: LoanRequestListItem[];
    isUpdating?: boolean;
    error?: string | null;
    onRetry?: () => void;
};

const requestTableSkeletonColumns: TableSkeletonColumn[] = [
    { headerClassName: 'w-24', cellClassName: 'w-32' },
    { headerClassName: 'w-32', cellClassName: 'w-40' },
    { headerClassName: 'w-24', cellClassName: 'w-28' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
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

const resolveTerm = (request: LoanRequestListItem): string => {
    if (
        request.requested_term === null ||
        request.requested_term === undefined
    ) {
        return '--';
    }

    const termValue = Number(request.requested_term);

    if (!Number.isFinite(termValue) || termValue <= 0) {
        return '--';
    }

    return `${termValue} months`;
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

const resolveTimestamp = (request: LoanRequestListItem): string => {
    if (request.status === 'draft') {
        return formatDateTime(request.updated_at);
    }

    return formatDateTime(request.submitted_at ?? request.updated_at);
};

const LoanRequestActionButton = ({
    href,
    label,
}: {
    href: string;
    label: string;
}) => (
    <Button asChild size="sm" variant="outline" className="w-full sm:w-auto">
        <Link href={href}>{label}</Link>
    </Button>
);

const LoanRequestMobileCard = ({
    request,
}: {
    request: LoanRequestListItem;
}) => {
    const isDraft = request.status === 'draft';
    const actionHref = isDraft
        ? loanRequestCreate().url
        : loanRequestShow(request.id).url;
    const actionLabel = isDraft ? 'Resume draft' : 'View request';

    return (
        <MemberMobileCard
            title={resolveLoanTypeLabel(request)}
            subtitle={resolveTerm(request)}
            valueLabel="Amount"
            value={resolveAmount(request)}
            meta={[
                {
                    label: 'Reference',
                    value: resolveReference(request),
                },
                {
                    label: 'Status',
                    value: (
                        <LoanRequestStatusBadge
                            status={request.status}
                            className="text-xs"
                        />
                    ),
                },
                {
                    label: isDraft ? 'Last saved' : 'Submitted',
                    value: resolveTimestamp(request),
                },
            ]}
            footer={
                <LoanRequestActionButton
                    href={actionHref}
                    label={actionLabel}
                />
            }
        />
    );
};

export function LoanRequestRecordsCard({
    items,
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
                header: 'Reference',
                cell: ({ row }) => resolveReference(row.original),
            },
            {
                id: 'loan_type',
                header: 'Loan type',
                cell: ({ row }) => resolveLoanTypeLabel(row.original),
            },
            {
                id: 'amount',
                header: 'Requested amount',
                cell: ({ row }) => resolveAmount(row.original),
            },
            {
                id: 'term',
                header: 'Requested term',
                cell: ({ row }) => resolveTerm(row.original),
            },
            {
                id: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <LoanRequestStatusBadge status={row.original.status} />
                ),
            },
            {
                id: 'updated',
                header: 'Updated',
                cell: ({ row }) => resolveTimestamp(row.original),
            },
            {
                id: 'actions',
                header: '',
                cell: ({ row }) => {
                    const isDraft = row.original.status === 'draft';
                    const actionHref = isDraft
                        ? loanRequestCreate().url
                        : loanRequestShow(row.original.id).url;
                    const actionLabel = isDraft
                        ? 'Resume draft'
                        : 'View request';

                    return (
                        <div className="flex items-center justify-end">
                            <LoanRequestActionButton
                                href={actionHref}
                                label={actionLabel}
                            />
                        </div>
                    );
                },
            },
        ],
        [],
    );

    return (
        <MemberRecordsCard
            title="Loan requests"
            description="Track your draft, submitted, approved, declined, and cancelled applications."
            headerAccessory={
                <Button asChild size="sm" variant="outline">
                    <Link href={loanRequestCreate().url}>Request loan</Link>
                </Button>
            }
            isUpdating={isUpdating}
            error={error}
            errorTitle="Unable to load loan requests"
            onRetry={onRetry}
            showSkeleton={showSkeletonState}
            skeletonMobile={
                <div className="space-y-3">
                    <MemberMobileCardSkeleton actionCount={1} />
                    <MemberMobileCardSkeleton actionCount={1} />
                </div>
            }
            skeletonDesktop={
                <TableSkeleton
                    columns={requestTableSkeletonColumns}
                    rows={4}
                    className="rounded-xl border border-border/40 bg-card/60"
                    tableClassName="min-w-[1040px]"
                />
            }
            body={
                showEmptyState ? (
                    <div className="rounded-xl border border-dashed border-border/50 bg-muted/20 px-6 py-8 text-center">
                        <p className="text-sm font-medium">
                            No loan requests yet.
                        </p>
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
                ) : undefined
            }
            mobileWrapperClassName="space-y-3"
            mobileContent={
                items.length === 0 ? null : (
                    <>
                        {items.map((request) => (
                            <LoanRequestMobileCard
                                key={request.id}
                                request={request}
                            />
                        ))}
                    </>
                )
            }
            desktopContent={
                items.length === 0 ? null : (
                    <div className="overflow-x-auto">
                        <DataTable
                            columns={columns}
                            data={items}
                            className="min-w-[1040px]"
                            emptyMessage="No loan requests found."
                        />
                    </div>
                )
            }
        />
    );
}
