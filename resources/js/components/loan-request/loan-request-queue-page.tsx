import { Head, Link } from '@inertiajs/react';
import type { ColumnDef, RowSelectionState } from '@tanstack/react-table';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Flag,
    Eye,
    Loader2,
    MoreHorizontal,
    UserCheck,
    UserCog,
    UserPlus,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { AssignOfficerDialog } from '@/components/loan-request/assign-officer-dialog';
import { BulkCancelDialog } from '@/components/loan-request/bulk-cancel-dialog';
import type { LoanRequestStatusFilterOption } from '@/components/loan-request/loan-request-page-sections';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { PageShell } from '@/components/page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable } from '@/components/ui/data-table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    TableSkeleton,
    type TableSkeletonColumn,
} from '@/components/ui/table-skeleton';
import { useLoanRequestWorkflow } from '@/hooks/admin/use-loan-request-workflow';
import { useBulkLoanRequestActions } from '@/hooks/loan-request/use-bulk-loan-request-actions';
import { useRequestQueue } from '@/hooks/loan-request/use-request-queue';
import AppLayout from '@/layouts/app-layout';
import type { RequestQueueWorkspace } from '@/lib/api/request-queue';
import { type LoanRequestQueueStatusFilter } from '@/lib/loan-request-queue';
import { statusTones } from '@/lib/status-tones';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { RequestPreview } from '@/types/admin';
import type {
    LoanRequestAssignmentOfficerOption,
    LoanRequestBulkActionResult,
    LoanRequestStatusValue,
} from '@/types/loan-requests';

// Mirrors the backend's cancellable-status gate (LoanRequestDecisionService::
// isApproved()/isPendingDecision()) so ineligible rows can be disabled
// client-side. The backend remains the authority -- this is only used to
// reduce partial-failure noise in the bulk selection UI.
const cancellableStatuses: LoanRequestStatusValue[] = [
    'pending_co_maker_signatures',
    'submitted',
    'pending_review',
    'under_review',
    'needs_revision',
    'awaiting_member_information',
    'awaiting_member_acceptance',
    'approved',
];

type Props = {
    workspace: RequestQueueWorkspace;
    breadcrumbs: BreadcrumbItem[];
    headTitle: string;
    heroKicker: string;
    heroTitle: string;
    heroDescription: string;
    statusOptions: Array<
        LoanRequestStatusFilterOption<LoanRequestQueueStatusFilter>
    >;
    showRequestHref: (requestId: number) => string;
    summaryHelperText: string;
    showReportedSummary?: boolean;
    reportedQueueHref?: string;
    initialStatusFilter?: LoanRequestQueueStatusFilter;
};

const formatDate = (value?: string | null): string => {
    if (!value) {
        return '--';
    }

    return new Date(value)
        .toLocaleString('en-GB', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        })
        .replace(/\b(am|pm)\b/, (meridiem) => meridiem.toUpperCase());
};

const formatPeso = (value: number): string =>
    `₱ ${value.toLocaleString('en-PH', { maximumFractionDigits: 2 })}`;

// Mockup status palette (design/admin-requests.html): bg / ink / border per tone.
const statusToneClassNames: Partial<Record<LoanRequestStatusValue, string>> = {
    pending_review: statusTones.warn,
    under_review: statusTones.info,
    needs_revision: statusTones.hold,
    awaiting_member_information: statusTones.hold,
    recommended_for_approval: statusTones.act,
    awaiting_member_acceptance: statusTones.act,
    rejected: statusTones.bad,
    declined: statusTones.bad,
    member_declined_terms: statusTones.bad,
    approved: statusTones.ok,
    converted_to_loan: statusTones.ok,
    cancelled: statusTones.neutral,
};

const formatCountLabel = (count: number, label: string): string => {
    return count === 1 ? `${count} ${label}` : `${count} ${label}s`;
};

type RequestRowActionsMenuProps = {
    request: RequestPreview;
    requestId: number;
    showRequestHref: (requestId: number) => string;
    officerOptions: LoanRequestAssignmentOfficerOption[];
    isProcessing: boolean;
    onClaim: () => void;
    onOpenAssign: (
        officerOptions: LoanRequestAssignmentOfficerOption[],
    ) => void;
    onOpenReassign: (
        officerOptions: LoanRequestAssignmentOfficerOption[],
        currentOfficerName: string,
    ) => void;
};

function RequestRowActionsMenu({
    request,
    requestId,
    showRequestHref,
    officerOptions,
    isProcessing,
    onClaim,
    onOpenAssign,
    onOpenReassign,
}: RequestRowActionsMenuProps) {
    const reassignOptions = officerOptions.filter(
        (officer) => officer.user_id !== request.assigned_officer?.user_id,
    );
    const showClaim = request.can_claim;
    const showAssign =
        !request.assigned_officer &&
        request.can_assign &&
        officerOptions.length > 0;
    const showReassign =
        Boolean(request.assigned_officer) &&
        request.can_reassign &&
        reassignOptions.length > 0;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button type="button" variant="ghost" size="icon">
                    <MoreHorizontal className="h-4 w-4" />
                    <span className="sr-only">Request actions</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-52">
                <DropdownMenuItem asChild>
                    <Link href={showRequestHref(requestId)}>
                        <Eye />
                        View request
                    </Link>
                </DropdownMenuItem>
                {showClaim || showAssign || showReassign ? (
                    <DropdownMenuSeparator />
                ) : null}
                {showClaim ? (
                    <DropdownMenuItem
                        disabled={isProcessing}
                        onSelect={onClaim}
                    >
                        <UserCheck />
                        Claim request
                    </DropdownMenuItem>
                ) : null}
                {showAssign ? (
                    <DropdownMenuItem
                        disabled={isProcessing}
                        onSelect={() => onOpenAssign(officerOptions)}
                    >
                        <UserPlus />
                        Assign to...
                    </DropdownMenuItem>
                ) : null}
                {showReassign ? (
                    <DropdownMenuItem
                        disabled={isProcessing}
                        onSelect={() =>
                            onOpenReassign(
                                reassignOptions,
                                request.assigned_officer?.name ?? '',
                            )
                        }
                    >
                        <UserCog />
                        Reassign to...
                    </DropdownMenuItem>
                ) : null}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

type SortableColumnHeaderProps = {
    label: string;
    column: string;
    sortBy: string | null;
    sortDirection: 'asc' | 'desc';
    onToggle: (column: string) => void;
};

function SortableColumnHeader({
    label,
    column,
    sortBy,
    sortDirection,
    onToggle,
}: SortableColumnHeaderProps) {
    const isActive = sortBy === column;
    const Icon = isActive
        ? sortDirection === 'asc'
            ? ArrowUp
            : ArrowDown
        : ArrowUpDown;

    return (
        <Button
            variant="ghost"
            type="button"
            onClick={() => onToggle(column)}
            className="flex h-auto items-center justify-start gap-0 gap-1 rounded-none px-0 py-0 text-left font-medium font-normal whitespace-normal hover:bg-transparent hover:text-current hover:text-foreground has-[>svg]:px-0 md:h-auto"
        >
            {label}
            <Icon
                className={cn(
                    'h-3.5 w-3.5',
                    isActive ? 'text-foreground' : 'text-muted-foreground',
                )}
            />
        </Button>
    );
}

// Footer bar from the mockup: "Page x of y" + Prev / numbered pages / Next.
export function RequestsPager({
    page,
    perPage,
    total,
    onPageChange,
}: {
    page: number;
    perPage: number;
    total: number;
    onPageChange: (page: number) => void;
}) {
    const lastPage = Math.max(1, Math.ceil(total / perPage));
    const start = Math.max(1, Math.min(page - 2, lastPage - 4));
    const pages = Array.from(
        { length: Math.min(5, lastPage) },
        (_, index) => start + index,
    );

    return (
        <div className="flex flex-wrap items-center gap-2.5 border-t border-border bg-muted px-4 py-3 text-[13px] text-muted-foreground">
            <span className="font-semibold text-foreground">
                Page {page} of {lastPage}
            </span>
            <span className="flex-1" />
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={page <= 1}
                onClick={() => onPageChange(page - 1)}
                aria-label="Previous page"
            >
                Prev
            </Button>
            {pages.map((number) => (
                <Button
                    key={number}
                    type="button"
                    size="sm"
                    variant={number === page ? 'default' : 'outline'}
                    aria-current={number === page ? 'page' : undefined}
                    onClick={() => onPageChange(number)}
                >
                    {number}
                </Button>
            ))}
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={page >= lastPage}
                onClick={() => onPageChange(page + 1)}
                aria-label="Next page"
            >
                Next
            </Button>
        </div>
    );
}

const requestsTableSkeletonColumns: TableSkeletonColumn[] = [
    { headerClassName: 'w-24', cellClassName: 'w-28' },
    { headerClassName: 'w-28', cellClassName: 'w-32' },
    { headerClassName: 'w-28', cellClassName: 'w-32' },
    { headerClassName: 'w-28', cellClassName: 'w-32' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-16', cellClassName: 'w-20' },
    { headerClassName: 'w-20', cellClassName: 'w-20' },
    { headerClassName: 'w-24', cellClassName: 'w-24', align: 'right' },
];

export function LoanRequestQueuePage({
    workspace,
    breadcrumbs,
    headTitle,
    heroKicker,
    heroTitle,
    heroDescription,
    statusOptions,
    showRequestHref,
    summaryHelperText,
    showReportedSummary = false,
    reportedQueueHref,
    initialStatusFilter = 'all',
}: Props) {
    const [search, setSearch] = useState('');
    const [loanType, setLoanType] = useState<string | null>(null);
    const [statusFilter, setStatusFilter] =
        useState<LoanRequestQueueStatusFilter>(initialStatusFilter);
    const [assignmentFilter, setAssignmentFilter] = useState<
        'unassigned' | 'mine' | 'all' | null
    >(null);
    const [officerId, setOfficerId] = useState<number | null>(null);
    const [sortBy, setSortBy] = useState<string | null>(null);
    const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');
    const [page, setPage] = useState(1);
    const [perPage] = useState(10);
    const [assignmentDialog, setAssignmentDialog] = useState<{
        requestId: number;
        mode: 'assign' | 'reassign';
        currentOfficerName?: string | null;
        officerOptions: LoanRequestAssignmentOfficerOption[];
    } | null>(null);
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [bulkCancelDialogOpen, setBulkCancelDialogOpen] = useState(false);

    const toggleSort = useCallback(
        (column: string) => {
            if (sortBy === column) {
                setSortDirection((current) =>
                    current === 'asc' ? 'desc' : 'asc',
                );
            } else {
                setSortBy(column);
                setSortDirection('desc');
            }

            setPage(1);
        },
        [sortBy],
    );

    const searchValue = search.trim();
    const status =
        statusFilter === 'all' || statusFilter === 'reported'
            ? null
            : statusFilter;
    const reported = statusFilter === 'reported' ? true : undefined;
    const { items, meta, loading, error, warning, refetch } = useRequestQueue({
        workspace,
        search,
        page,
        perPage,
        loanType,
        status,
        assignment: assignmentFilter,
        officerId,
        reported,
        sortBy,
        sortDirection,
    });
    const {
        assignLoanRequest,
        reassignLoanRequest,
        claimLoanRequest,
        processingIds,
    } = useLoanRequestWorkflow({
        onUpdated: () => refetch(),
    });
    const {
        bulkClaim,
        bulkCancel,
        isSubmitting: isBulkSubmitting,
    } = useBulkLoanRequestActions();

    const selectedIds = useMemo(
        () =>
            Object.keys(rowSelection)
                .filter((id) => rowSelection[id])
                .map((id) => Number(id)),
        [rowSelection],
    );
    const itemsById = useMemo(
        () => new Map(items.map((item) => [item.id, item])),
        [items],
    );
    const claimableSelectedIds = useMemo(
        () => selectedIds.filter((id) => itemsById.get(id)?.can_claim === true),
        [selectedIds, itemsById],
    );
    const cancellableSelectedIds = useMemo(
        () =>
            selectedIds.filter((id) => {
                const status = itemsById.get(id)?.status;

                return (
                    status !== null &&
                    status !== undefined &&
                    cancellableStatuses.includes(status)
                );
            }),
        [selectedIds, itemsById],
    );

    const summarizeBulkResult = (
        result: LoanRequestBulkActionResult,
        actionLabel: string,
    ): void => {
        if (result.failed_count === 0) {
            showSuccessToast(
                `${result.succeeded_count} request${result.succeeded_count === 1 ? '' : 's'} ${actionLabel}.`,
            );

            return;
        }

        const failureSummary = result.failed
            .slice(0, 3)
            .map((failure) => `#${failure.id}: ${failure.message}`)
            .join(' ');

        if (result.succeeded_count === 0) {
            showErrorToast(
                new Error(failureSummary),
                `Failed to ${actionLabel} the selected requests.`,
                {
                    description:
                        result.failed_count > 3
                            ? `${failureSummary} (+${result.failed_count - 3} more)`
                            : failureSummary,
                },
            );

            return;
        }

        showSuccessToast(
            `${result.succeeded_count} ${actionLabel}, ${result.failed_count} failed.`,
            {
                description:
                    result.failed_count > 3
                        ? `${failureSummary} (+${result.failed_count - 3} more)`
                        : failureSummary,
            },
        );
    };

    const handleBulkClaim = async () => {
        if (claimableSelectedIds.length === 0) {
            return;
        }

        const result = await bulkClaim(claimableSelectedIds);

        if (result) {
            summarizeBulkResult(result, 'claimed');
            setRowSelection({});
            refetch();
        }
    };

    const handleBulkCancel = async (cancellationReason: string) => {
        if (cancellableSelectedIds.length === 0) {
            return;
        }

        const result = await bulkCancel(
            cancellableSelectedIds,
            cancellationReason,
        );

        if (result) {
            summarizeBulkResult(result, 'cancelled');
            setRowSelection({});
            refetch();
        }
    };

    // TanStack Table recreates its internal instance when the columns array
    // identity changes, so this must stay memoized.
    const columns = useMemo<ColumnDef<RequestPreview>[]>(
        () => [
            {
                id: 'select',
                meta: { priority: 'hidden' },
                header: ({ table }) => (
                    <Checkbox
                        aria-label="Select all eligible requests on this page"
                        checked={
                            table.getIsAllPageRowsSelected()
                                ? true
                                : table.getIsSomePageRowsSelected()
                                  ? 'indeterminate'
                                  : false
                        }
                        onCheckedChange={(value) =>
                            table.toggleAllPageRowsSelected(!!value)
                        }
                    />
                ),
                cell: ({ row }) => {
                    const request = row.original;
                    const selectable =
                        request.can_claim ||
                        cancellableStatuses.includes(
                            request.status as LoanRequestStatusValue,
                        );

                    if (!request.id || !selectable) {
                        return null;
                    }

                    return (
                        <Checkbox
                            aria-label={`Select request ${request.reference ?? request.id}`}
                            checked={row.getIsSelected()}
                            onCheckedChange={(value) =>
                                row.toggleSelected(!!value)
                            }
                        />
                    );
                },
            },
            {
                accessorKey: 'reference',
                meta: { priority: 'detail', label: 'Reference' },
                header: () => (
                    <SortableColumnHeader
                        label="Reference"
                        column="reference"
                        sortBy={sortBy}
                        sortDirection={sortDirection}
                        onToggle={toggleSort}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-semibold whitespace-nowrap tabular-nums">
                        {row.original.reference ?? '--'}
                    </span>
                ),
            },
            {
                accessorKey: 'member_name',
                meta: {
                    priority: 'title',
                    text: (row) => row.member_name ?? '',
                },
                header: 'Member',
                cell: ({ row }) => (
                    <span className="font-bold">
                        {row.original.member_name ?? '--'}
                    </span>
                ),
            },
            {
                accessorKey: 'assigned_officer',
                meta: { priority: 'detail' },
                header: 'Assigned Loan Processor',
                cell: ({ row }) =>
                    row.original.assigned_officer?.name ?? 'Unassigned',
            },
            {
                accessorKey: 'loan_type',
                meta: { priority: 'detail', label: 'Loan type' },
                header: () => (
                    <SortableColumnHeader
                        label="Loan type"
                        column="loanType"
                        sortBy={sortBy}
                        sortDirection={sortDirection}
                        onToggle={toggleSort}
                    />
                ),
                cell: ({ row }) => row.original.loan_type ?? '--',
            },
            {
                accessorKey: 'requested_amount',
                meta: { priority: 'amount', label: 'Amount' },
                header: () => (
                    <div className="flex justify-end">
                        <SortableColumnHeader
                            label="Amount"
                            column="amount"
                            sortBy={sortBy}
                            sortDirection={sortDirection}
                            onToggle={toggleSort}
                        />
                    </div>
                ),
                cell: ({ row }) => (
                    <div className="text-right font-semibold whitespace-nowrap tabular-nums">
                        {row.original.requested_amount !== null &&
                        row.original.requested_amount !== undefined
                            ? formatPeso(Number(row.original.requested_amount))
                            : '--'}
                    </div>
                ),
            },
            {
                accessorKey: 'status',
                meta: { priority: 'badge', label: 'Status' },
                header: () => (
                    <SortableColumnHeader
                        label="Status"
                        column="status"
                        sortBy={sortBy}
                        sortDirection={sortDirection}
                        onToggle={toggleSort}
                    />
                ),
                cell: ({ row }) => (
                    <div className="flex flex-wrap items-center gap-2">
                        <LoanRequestStatusBadge
                            status={row.original.status}
                            className={cn(
                                'px-2.5 py-0.5 font-bold',
                                row.original.status
                                    ? statusToneClassNames[row.original.status]
                                    : undefined,
                            )}
                        />
                        {row.original.has_open_correction_report ? (
                            <Badge
                                variant="outline"
                                className="border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-200"
                            >
                                Correction reported
                            </Badge>
                        ) : null}
                    </div>
                ),
            },
            {
                accessorKey: 'last_activity_at',
                meta: { priority: 'detail', label: 'Last activity' },
                header: () => (
                    <SortableColumnHeader
                        label="Last Activity"
                        column="submitted"
                        sortBy={sortBy}
                        sortDirection={sortDirection}
                        onToggle={toggleSort}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-semibold whitespace-nowrap tabular-nums">
                        {formatDate(
                            row.original.last_activity_at ??
                                row.original.submitted_at ??
                                row.original.created_at,
                        )}
                    </span>
                ),
            },
            {
                id: 'action',
                meta: { priority: 'action', label: 'Action' },
                header: () => <div className="flex justify-end">Action</div>,
                cell: ({ row }) => {
                    const requestId = row.original.id;

                    if (!requestId) {
                        return <div className="flex justify-end">--</div>;
                    }

                    return (
                        <div className="flex items-center justify-end gap-1">
                            <Button
                                asChild
                                variant="ghost"
                                size="sm"
                                className="text-primary"
                            >
                                <Link href={showRequestHref(requestId)}>
                                    Open
                                </Link>
                            </Button>
                            <RequestRowActionsMenu
                                request={row.original}
                                requestId={requestId}
                                showRequestHref={showRequestHref}
                                officerOptions={meta.assignmentOfficers ?? []}
                                isProcessing={processingIds[requestId] ?? false}
                                onClaim={() => claimLoanRequest(requestId)}
                                onOpenAssign={(officerOptions) =>
                                    setAssignmentDialog({
                                        requestId,
                                        mode: 'assign',
                                        officerOptions,
                                    })
                                }
                                onOpenReassign={(
                                    officerOptions,
                                    currentOfficerName,
                                ) =>
                                    setAssignmentDialog({
                                        requestId,
                                        mode: 'reassign',
                                        currentOfficerName,
                                        officerOptions,
                                    })
                                }
                            />
                        </div>
                    );
                },
            },
        ],
        [
            showRequestHref,
            meta.assignmentOfficers,
            processingIds,
            claimLoanRequest,
            sortBy,
            sortDirection,
            toggleSort,
        ],
    );

    const showSkeleton = loading && items.length === 0;
    const loanTypeOptions = (
        meta.loanTypes.length > 0
            ? meta.loanTypes
            : Array.from(
                  new Set(
                      items
                          .map((item) => item.loan_type)
                          .filter((value): value is string => Boolean(value)),
                  ),
              )
    ).sort((left, right) => left.localeCompare(right));
    const totalResults = meta.total;
    const pageStart = totalResults > 0 ? (meta.page - 1) * meta.perPage + 1 : 0;
    const pageEnd =
        totalResults > 0 ? Math.min(meta.page * meta.perPage, totalResults) : 0;
    const resultsLabel = meta.available
        ? totalResults > 0
            ? `Showing ${pageStart}-${pageEnd} of ${formatCountLabel(
                  totalResults,
                  'request',
              )}`
            : 'No requests found yet.'
        : (meta.message ?? 'Requests module coming soon.');
    const emptyMessage = meta.available
        ? searchValue !== '' || statusFilter !== 'all' || loanType !== null
            ? 'No requests match the current filters.'
            : 'No requests found yet.'
        : (meta.message ?? 'Requests module coming soon.');
    const summaryCountsByStatus = meta.statusCounts ?? {};
    const summaryCountFor = (...statuses: string[]) =>
        statuses.reduce(
            (sum, status) => sum + (summaryCountsByStatus[status] ?? 0),
            0,
        );

    const summaryCounts = {
        total: totalResults,
        pendingReview: summaryCountFor(
            'pending_review',
            'submitted',
            'pending_co_maker_signatures',
        ),
        underReview: summaryCountFor('under_review'),
        needsRevision: summaryCountFor('needs_revision'),
        recommended: summaryCountFor('recommended_for_approval'),
        approved: summaryCountFor('approved'),
        converted: summaryCountFor('converted_to_loan'),
        declinedOrRejected: summaryCountFor('declined', 'rejected'),
        reported: meta.openCorrectionReports ?? 0,
    };
    const summaryItems: Array<{
        label: string;
        value: number;
        filter: LoanRequestQueueStatusFilter;
        warn?: boolean;
    }> = [
        { label: 'Total', value: summaryCounts.total, filter: 'all' },
        {
            label: 'Pending Review',
            value: summaryCounts.pendingReview,
            filter: 'pending_review',
        },
        {
            label: 'Under Review',
            value: summaryCounts.underReview,
            filter: 'under_review',
        },
        {
            label: 'Needs Revision',
            value: summaryCounts.needsRevision,
            filter: 'needs_revision',
        },
        {
            label: 'Recommended',
            value: summaryCounts.recommended,
            filter: 'recommended_for_approval',
        },
        {
            label: 'Approved',
            value: summaryCounts.approved,
            filter: 'approved',
        },
        {
            label: 'Converted',
            value: summaryCounts.converted,
            filter: 'converted_to_loan',
        },
        {
            label: 'Declined/Rejected',
            value: summaryCounts.declinedOrRejected,
            filter: 'declined',
        },
        ...(showReportedSummary
            ? [
                  {
                      label: 'Reported',
                      value: summaryCounts.reported,
                      filter: 'reported' as const,
                      warn: true,
                  },
              ]
            : []),
    ];
    const labelClass =
        'text-[11px] font-bold tracking-[0.08em] text-muted-foreground uppercase';
    const clearFilters = () => {
        setSearch('');
        setLoanType(null);
        setStatusFilter('all');
        setAssignmentFilter(null);
        setOfficerId(null);
        setSortBy(null);
        setSortDirection('desc');
        setPage(1);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={headTitle} />
            <PageShell size="wide">
                <section className="flex flex-wrap items-start gap-5">
                    <div>
                        <p className="text-[11px] font-bold tracking-[0.14em] text-primary uppercase">
                            {heroKicker}
                        </p>
                        <h1 className="mt-1 text-[22px] leading-tight font-bold sm:text-[26px]">
                            {heroTitle}
                        </h1>
                        <p className="mt-1.5 max-w-prose text-sm text-muted-foreground">
                            {heroDescription}
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <Badge variant="secondary">
                                {formatCountLabel(totalResults, 'request')}
                            </Badge>
                            {showReportedSummary ? (
                                <Badge className="border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-200">
                                    {formatCountLabel(
                                        meta.openCorrectionReports ?? 0,
                                        'open correction report',
                                    )}
                                </Badge>
                            ) : null}
                            {loading ? (
                                <Badge variant="secondary">Updating</Badge>
                            ) : null}
                        </div>
                    </div>
                    {reportedQueueHref ? (
                        <div className="w-full pt-1.5 sm:ml-auto sm:w-auto">
                            <Button
                                asChild
                                variant="outline"
                                className="w-full sm:w-auto"
                            >
                                <Link href={reportedQueueHref}>
                                    <Flag />
                                    Open reported queue
                                </Link>
                            </Button>
                        </div>
                    ) : null}
                </section>

                <section aria-label="Status summary" className="space-y-2.5">
                    <div className="grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-3">
                        {summaryItems.map((item) => (
                            <button
                                key={item.label}
                                type="button"
                                aria-pressed={statusFilter === item.filter}
                                onClick={() => {
                                    setStatusFilter(item.filter);
                                    setPage(1);
                                }}
                                className="rounded-xl border border-border bg-card px-4 py-3.5 text-left shadow-card transition-colors hover:bg-muted aria-pressed:border-primary aria-pressed:ring-1 aria-pressed:ring-primary"
                            >
                                <span className="block text-[26px] leading-tight font-bold tabular-nums">
                                    {item.value}
                                </span>
                                <span
                                    className={cn(
                                        'mt-0.5 block text-[13px] text-muted-foreground',
                                        item.warn &&
                                            'font-semibold text-amber-700 dark:text-amber-400',
                                    )}
                                >
                                    {item.label}
                                </span>
                            </button>
                        ))}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {summaryHelperText}
                    </p>
                </section>

                <Card
                    className="gap-0 overflow-hidden py-0"
                    aria-label="Search and filter requests"
                >
                    <div className="flex flex-wrap items-end gap-3 px-5 py-4">
                        <div className="grid min-w-[260px] flex-1 gap-1">
                            <Label
                                htmlFor={`${workspace}-requests-search`}
                                className={labelClass}
                            >
                                Search
                            </Label>
                            <Input
                                id={`${workspace}-requests-search`}
                                type="search"
                                value={search}
                                onChange={(event) => {
                                    setSearch(event.target.value);
                                    setPage(1);
                                }}
                                placeholder="Search by account, member, loan type, or status"
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label
                                htmlFor={`${workspace}-requests-status`}
                                className={labelClass}
                            >
                                Status
                            </Label>
                            <Select
                                value={statusFilter}
                                onValueChange={(value) => {
                                    setStatusFilter(
                                        value as LoanRequestQueueStatusFilter,
                                    );
                                    setPage(1);
                                }}
                            >
                                <SelectTrigger
                                    id={`${workspace}-requests-status`}
                                    className="w-full sm:w-52"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {statusOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        {workspace === 'staff' &&
                        (meta.assignmentFilters?.length ?? 0) > 0 ? (
                            <div className="grid gap-1">
                                <Label className={labelClass}>Assignment</Label>
                                <Select
                                    value={assignmentFilter ?? 'default'}
                                    onValueChange={(value) => {
                                        const next =
                                            value === 'default'
                                                ? null
                                                : (value as
                                                      | 'unassigned'
                                                      | 'mine'
                                                      | 'all');

                                        setAssignmentFilter(next);

                                        if (next !== null && next !== 'all') {
                                            setOfficerId(null);
                                        }

                                        setPage(1);
                                    }}
                                >
                                    <SelectTrigger
                                        aria-label="Assignment"
                                        className="w-full sm:w-44"
                                    >
                                        <SelectValue placeholder="Default view" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="default">
                                            Default view
                                        </SelectItem>
                                        {meta.assignmentFilters?.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>
                        ) : null}
                        {(meta.assignmentOfficers?.length ?? 0) > 0 &&
                        (assignmentFilter === null ||
                            assignmentFilter === 'all') ? (
                            <div className="grid gap-1">
                                <Label className={labelClass}>
                                    Loan processor
                                </Label>
                                <Select
                                    value={
                                        officerId !== null
                                            ? `${officerId}`
                                            : 'all'
                                    }
                                    onValueChange={(value) => {
                                        setOfficerId(
                                            value === 'all'
                                                ? null
                                                : Number(value),
                                        );
                                        setPage(1);
                                    }}
                                >
                                    <SelectTrigger
                                        aria-label="Loan processor"
                                        className="w-full sm:w-52"
                                    >
                                        <SelectValue placeholder="All loan processors" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All loan processors
                                        </SelectItem>
                                        {meta.assignmentOfficers?.map(
                                            (officer) => (
                                                <SelectItem
                                                    key={officer.user_id}
                                                    value={`${officer.user_id}`}
                                                >
                                                    {officer.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>
                        ) : null}
                        <div className="grid gap-1">
                            <Label className={labelClass}>Loan type</Label>
                            <Select
                                value={loanType ?? 'all'}
                                onValueChange={(value) => {
                                    setLoanType(value === 'all' ? null : value);
                                    setPage(1);
                                }}
                            >
                                <SelectTrigger
                                    aria-label="Loan type"
                                    className="w-full sm:w-44"
                                >
                                    <SelectValue placeholder="All loan types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All loan types
                                    </SelectItem>
                                    {loanTypeOptions.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={clearFilters}
                        >
                            Clear filters
                        </Button>
                    </div>
                    <p
                        className="px-5 pb-4 text-[13px] text-muted-foreground"
                        role="status"
                    >
                        {resultsLabel}
                    </p>
                </Card>

                {warning && !error ? (
                    <Alert>
                        <AlertTitle>Requests unavailable</AlertTitle>
                        <AlertDescription>{warning}</AlertDescription>
                    </Alert>
                ) : null}

                {error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Unable to load requests</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                {selectedIds.length > 0 ? (
                    <div className="flex animate-in flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-4 shadow-card duration-150 fade-in slide-in-from-top-1">
                        <p className="text-sm font-medium">
                            {formatCountLabel(selectedIds.length, 'request')}{' '}
                            selected
                        </p>
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setRowSelection({})}
                                disabled={isBulkSubmitting}
                            >
                                Clear selection
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                disabled={
                                    claimableSelectedIds.length === 0 ||
                                    isBulkSubmitting
                                }
                                onClick={handleBulkClaim}
                            >
                                {isBulkSubmitting ? (
                                    <>
                                        <Loader2 className="size-4 animate-spin" />
                                        Claiming...
                                    </>
                                ) : (
                                    `Claim selected (${claimableSelectedIds.length})`
                                )}
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                disabled={
                                    cancellableSelectedIds.length === 0 ||
                                    isBulkSubmitting
                                }
                                onClick={() => setBulkCancelDialogOpen(true)}
                            >
                                {`Cancel selected (${cancellableSelectedIds.length})`}
                            </Button>
                        </div>
                    </div>
                ) : null}

                <section className="overflow-hidden rounded-xl border border-border bg-card shadow-card">
                    <div className="flex flex-wrap items-baseline gap-2 px-5 pt-[18px] pb-4">
                        <h2 className="text-base font-bold">Results</h2>
                        <span className="text-[13px] text-muted-foreground">
                            {formatCountLabel(totalResults, 'request')}
                        </span>
                    </div>

                    <div>
                        <div>
                            {showSkeleton ? (
                                <TableSkeleton
                                    columns={requestsTableSkeletonColumns}
                                    rows={perPage}
                                    className="hidden md:block"
                                    tableClassName="bg-transparent"
                                />
                            ) : (
                                <DataTable
                                    columns={columns}
                                    data={items}
                                    emptyMessage={emptyMessage}
                                    className="rounded-none border-0 border-t border-border bg-transparent shadow-none"
                                    getRowId={(item, index) =>
                                        String(item.id ?? index)
                                    }
                                    rowSelection={rowSelection}
                                    onRowSelectionChange={setRowSelection}
                                    enableRowSelection
                                />
                            )}
                        </div>

                        <div className="md:hidden">
                            {showSkeleton ? (
                                <div className="space-y-3 px-2 pt-4 pb-3">
                                    {Array.from({ length: 3 }).map(
                                        (_, index) => (
                                            <div
                                                key={`request-skeleton-${index}`}
                                                className="rounded-xl border border-border bg-card p-4"
                                            >
                                                <div className="flex items-center justify-between gap-4">
                                                    <Skeleton className="h-4 w-32" />
                                                    <Skeleton className="h-5 w-20" />
                                                </div>
                                                <div className="mt-4 grid grid-cols-2 gap-3">
                                                    <Skeleton className="h-3 w-24" />
                                                    <Skeleton className="h-3 w-24" />
                                                    <Skeleton className="h-3 w-20" />
                                                    <Skeleton className="h-3 w-24" />
                                                </div>
                                                <div className="mt-4 flex justify-end">
                                                    <Skeleton className="h-8 w-28" />
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </div>
                            ) : null}
                        </div>
                    </div>

                    {showSkeleton ? null : (
                        <RequestsPager
                            page={meta.page}
                            perPage={meta.perPage}
                            total={meta.total}
                            onPageChange={setPage}
                        />
                    )}
                </section>
            </PageShell>

            <AssignOfficerDialog
                open={assignmentDialog !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setAssignmentDialog(null);
                    }
                }}
                mode={assignmentDialog?.mode ?? 'assign'}
                officerOptions={assignmentDialog?.officerOptions ?? []}
                currentOfficerName={assignmentDialog?.currentOfficerName}
                isProcessing={
                    assignmentDialog
                        ? (processingIds[assignmentDialog.requestId] ?? false)
                        : false
                }
                onSubmit={(officerUserId, reason) => {
                    if (!assignmentDialog) {
                        return Promise.resolve(null);
                    }

                    const { requestId, mode } = assignmentDialog;

                    return mode === 'assign'
                        ? assignLoanRequest(requestId, {
                              officer_user_id: officerUserId,
                              reason,
                          })
                        : reassignLoanRequest(requestId, {
                              officer_user_id: officerUserId,
                              reason,
                          });
                }}
            />

            <BulkCancelDialog
                open={bulkCancelDialogOpen}
                onOpenChange={setBulkCancelDialogOpen}
                requestCount={cancellableSelectedIds.length}
                isProcessing={isBulkSubmitting}
                onSubmit={handleBulkCancel}
            />
        </AppLayout>
    );
}
