import { Head, Link } from '@inertiajs/react';
import type { ColumnDef, RowSelectionState } from '@tanstack/react-table';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Eye,
    MoreHorizontal,
    SlidersHorizontal,
    UserCheck,
    UserCog,
    UserPlus,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { AssignOfficerDialog } from '@/components/loan-request/assign-officer-dialog';
import { BulkCancelDialog } from '@/components/loan-request/bulk-cancel-dialog';
import {
    LoanRequestPageHero,
    LoanRequestSearchBox,
    LoanRequestStatusFilters,
    LoanRequestSummaryCards,
    type LoanRequestStatusFilterOption,
} from '@/components/loan-request/loan-request-page-sections';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { CurrencyInput } from '@/components/loan-request/numeric-adorned-inputs';
import { PageShell } from '@/components/page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable } from '@/components/ui/data-table';
import {
    DataTablePagination,
    DataTablePaginationSkeleton,
} from '@/components/ui/data-table-pagination';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
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
import { formatCurrency } from '@/lib/formatters';
import { type LoanRequestQueueStatusFilter } from '@/lib/loan-request-queue';
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
    initialStatusFilter?: LoanRequestQueueStatusFilter;
};

const formatDate = (value?: string | null): string => {
    if (!value) {
        return '--';
    }

    return new Date(value).toLocaleDateString();
};

const parseAmount = (value: string): number | undefined => {
    const trimmed = value.trim();

    if (trimmed === '') {
        return undefined;
    }

    const parsed = Number(trimmed);

    return Number.isFinite(parsed) ? parsed : undefined;
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
        <button
            type="button"
            onClick={() => onToggle(column)}
            className="flex items-center gap-1 text-left font-medium hover:text-foreground"
        >
            {label}
            <Icon
                className={cn(
                    'h-3.5 w-3.5',
                    isActive ? 'text-foreground' : 'text-muted-foreground',
                )}
            />
        </button>
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
    const [minAmount, setMinAmount] = useState('');
    const [maxAmount, setMaxAmount] = useState('');
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

    const toggleSort = (column: string) => {
        if (sortBy === column) {
            setSortDirection((current) => (current === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortBy(column);
            setSortDirection('desc');
        }

        setPage(1);
    };

    const searchValue = search.trim();
    const minAmountValue = parseAmount(minAmount);
    const maxAmountValue = parseAmount(maxAmount);
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
        minAmount: minAmountValue,
        maxAmount: maxAmountValue,
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
                header: () => (
                    <SortableColumnHeader
                        label="Reference"
                        column="reference"
                        sortBy={sortBy}
                        sortDirection={sortDirection}
                        onToggle={toggleSort}
                    />
                ),
                cell: ({ row }) => row.original.reference ?? '--',
            },
            {
                accessorKey: 'member_name',
                header: 'Member',
                cell: ({ row }) => row.original.member_name ?? '--',
            },
            {
                accessorKey: 'assigned_officer',
                header: 'Assigned Loan Processor',
                cell: ({ row }) =>
                    row.original.assigned_officer?.name ?? 'Unassigned',
            },
            {
                accessorKey: 'loan_type',
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
                header: () => (
                    <SortableColumnHeader
                        label="Amount"
                        column="amount"
                        sortBy={sortBy}
                        sortDirection={sortDirection}
                        onToggle={toggleSort}
                    />
                ),
                cell: ({ row }) =>
                    row.original.requested_amount !== null &&
                    row.original.requested_amount !== undefined
                        ? formatCurrency(Number(row.original.requested_amount))
                        : '--',
            },
            {
                accessorKey: 'status',
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
                        <LoanRequestStatusBadge status={row.original.status} />
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
                header: () => (
                    <SortableColumnHeader
                        label="Last Activity"
                        column="submitted"
                        sortBy={sortBy}
                        sortDirection={sortDirection}
                        onToggle={toggleSort}
                    />
                ),
                cell: ({ row }) =>
                    formatDate(
                        row.original.last_activity_at ??
                            row.original.submitted_at ??
                            row.original.created_at,
                    ),
            },
            {
                id: 'action',
                header: () => <div className="flex justify-end">Action</div>,
                cell: ({ row }) => {
                    const requestId = row.original.id;

                    if (!requestId) {
                        return <div className="flex justify-end">--</div>;
                    }

                    return (
                        <div className="flex justify-end">
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
        ? searchValue !== '' ||
          statusFilter !== 'all' ||
          loanType !== null ||
          minAmountValue !== undefined ||
          maxAmountValue !== undefined
            ? 'No requests match the current filters.'
            : 'No requests found yet.'
        : (meta.message ?? 'Requests module coming soon.');
    const filterCount = [
        searchValue !== '' ? searchValue : null,
        loanType,
        statusFilter !== 'all' ? statusFilter : null,
        assignmentFilter,
        officerId,
        minAmountValue,
        maxAmountValue,
    ].filter((value) => value !== null && value !== undefined).length;
    const hasFilters = filterCount > 0;
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
    const summaryItems = [
        { label: 'Total', value: summaryCounts.total },
        {
            label: 'Pending Review',
            value: summaryCounts.pendingReview,
            emphasisClassName: 'text-amber-600 dark:text-amber-400',
        },
        {
            label: 'Under Review',
            value: summaryCounts.underReview,
            emphasisClassName: 'text-sky-600 dark:text-sky-400',
        },
        {
            label: 'Needs Revision',
            value: summaryCounts.needsRevision,
            emphasisClassName: 'text-orange-600 dark:text-orange-400',
        },
        {
            label: 'Recommended',
            value: summaryCounts.recommended,
            emphasisClassName: 'text-indigo-600 dark:text-indigo-400',
        },
        {
            label: 'Approved',
            value: summaryCounts.approved,
            emphasisClassName: 'text-emerald-600 dark:text-emerald-400',
        },
        {
            label: 'Converted',
            value: summaryCounts.converted,
            emphasisClassName: 'text-teal-600 dark:text-teal-400',
        },
        {
            label: 'Declined/Rejected',
            value: summaryCounts.declinedOrRejected,
            emphasisClassName: 'text-rose-600 dark:text-rose-400',
        },
        ...(showReportedSummary
            ? [
                  {
                      label: 'Reported',
                      value: summaryCounts.reported,
                      emphasisClassName: 'text-amber-600 dark:text-amber-400',
                  },
                  {
                      label: 'Open correction reports',
                      value: meta.openCorrectionReports,
                      emphasisClassName: 'text-amber-600 dark:text-amber-400',
                  },
              ]
            : []),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={headTitle} />
            <PageShell size="wide">
                <LoanRequestPageHero
                    kicker={heroKicker}
                    title={heroTitle}
                    description={heroDescription}
                    badges={
                        <>
                            <Badge variant="secondary">
                                {formatCountLabel(totalResults, 'request')}
                            </Badge>
                            {filterCount > 0 ? (
                                <Badge variant="outline">
                                    {formatCountLabel(
                                        filterCount,
                                        'active filter',
                                    )}
                                </Badge>
                            ) : null}
                            {loading ? (
                                <Badge variant="outline">Updating</Badge>
                            ) : null}
                        </>
                    }
                />

                <LoanRequestSummaryCards
                    items={summaryItems}
                    helperText={summaryHelperText}
                />

                <section className="rounded-2xl border border-border/40 bg-card/60 p-4 shadow-sm sm:p-5">
                    <div className="space-y-4">
                        <LoanRequestSearchBox
                            value={search}
                            onChange={(nextSearch) => {
                                setSearch(nextSearch);
                                setPage(1);
                            }}
                            placeholder="Search by account, member, loan type, or status"
                            resultsText={resultsLabel}
                            actions={
                                <Popover>
                                    <PopoverTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-10"
                                        >
                                            <SlidersHorizontal className="h-4 w-4" />
                                            Filters
                                            {filterCount > 0 ? (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-1 px-1.5"
                                                >
                                                    {filterCount}
                                                </Badge>
                                            ) : null}
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent
                                        align="end"
                                        className="w-80 sm:w-96"
                                    >
                                        <div className="space-y-4">
                                            <LoanRequestStatusFilters
                                                options={statusOptions}
                                                activeValue={statusFilter}
                                                onChange={(nextStatus) => {
                                                    setStatusFilter(nextStatus);
                                                    setPage(1);
                                                }}
                                            />

                                            {workspace === 'staff' &&
                                            (meta.assignmentFilters?.length ??
                                                0) > 0 ? (
                                                <div className="space-y-1">
                                                    <span className="text-xs font-medium text-muted-foreground">
                                                        Assignment
                                                    </span>
                                                    <Select
                                                        value={
                                                            assignmentFilter ??
                                                            'default'
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) => {
                                                            const nextAssignment =
                                                                value ===
                                                                'default'
                                                                    ? null
                                                                    : (value as
                                                                          | 'unassigned'
                                                                          | 'mine'
                                                                          | 'all');

                                                            setAssignmentFilter(
                                                                nextAssignment,
                                                            );

                                                            if (
                                                                nextAssignment !==
                                                                    null &&
                                                                nextAssignment !==
                                                                    'all'
                                                            ) {
                                                                setOfficerId(
                                                                    null,
                                                                );
                                                            }

                                                            setPage(1);
                                                        }}
                                                    >
                                                        <SelectTrigger aria-label="Assignment">
                                                            <SelectValue placeholder="Default view" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="default">
                                                                Default view
                                                            </SelectItem>
                                                            {meta.assignmentFilters?.map(
                                                                (option) => (
                                                                    <SelectItem
                                                                        key={
                                                                            option.value
                                                                        }
                                                                        value={
                                                                            option.value
                                                                        }
                                                                    >
                                                                        {
                                                                            option.label
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            ) : null}

                                            {workspace === 'staff' &&
                                            (meta.assignmentOfficers?.length ??
                                                0) > 0 &&
                                            (assignmentFilter === null ||
                                                assignmentFilter === 'all') ? (
                                                <div className="space-y-1">
                                                    <span className="text-xs font-medium text-muted-foreground">
                                                        Loan officer
                                                    </span>
                                                    <Select
                                                        value={
                                                            officerId !== null
                                                                ? `${officerId}`
                                                                : 'all'
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) => {
                                                            setOfficerId(
                                                                value === 'all'
                                                                    ? null
                                                                    : Number(
                                                                          value,
                                                                      ),
                                                            );
                                                            setPage(1);
                                                        }}
                                                    >
                                                        <SelectTrigger aria-label="Loan officer">
                                                            <SelectValue placeholder="All loan processors" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="all">
                                                                All loan
                                                                processors
                                                            </SelectItem>
                                                            {meta.assignmentOfficers?.map(
                                                                (officer) => (
                                                                    <SelectItem
                                                                        key={
                                                                            officer.user_id
                                                                        }
                                                                        value={`${officer.user_id}`}
                                                                    >
                                                                        {`${officer.name} - ${officer.active_assignment_count} active application${officer.active_assignment_count === 1 ? '' : 's'}${officer.has_workload_warning ? ' - High workload' : ''}`}
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            ) : null}

                                            <div className="space-y-1">
                                                <span className="text-xs font-medium text-muted-foreground">
                                                    Loan type
                                                </span>
                                                <Select
                                                    value={loanType ?? 'all'}
                                                    onValueChange={(value) => {
                                                        setLoanType(
                                                            value === 'all'
                                                                ? null
                                                                : value,
                                                        );
                                                        setPage(1);
                                                    }}
                                                >
                                                    <SelectTrigger aria-label="Loan type">
                                                        <SelectValue placeholder="All loan types" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="all">
                                                            All loan types
                                                        </SelectItem>
                                                        {loanTypeOptions.map(
                                                            (option) => (
                                                                <SelectItem
                                                                    key={option}
                                                                    value={
                                                                        option
                                                                    }
                                                                >
                                                                    {option}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="space-y-1">
                                                    <label
                                                        className="text-xs font-medium text-muted-foreground"
                                                        htmlFor={`${workspace}-requests-min-amount`}
                                                    >
                                                        Min amount
                                                    </label>
                                                    <CurrencyInput
                                                        id={`${workspace}-requests-min-amount`}
                                                        value={minAmount}
                                                        onValueChange={(
                                                            nextValue,
                                                        ) => {
                                                            setMinAmount(
                                                                nextValue,
                                                            );
                                                            setPage(1);
                                                        }}
                                                    />
                                                </div>

                                                <div className="space-y-1">
                                                    <label
                                                        className="text-xs font-medium text-muted-foreground"
                                                        htmlFor={`${workspace}-requests-max-amount`}
                                                    >
                                                        Max amount
                                                    </label>
                                                    <CurrencyInput
                                                        id={`${workspace}-requests-max-amount`}
                                                        value={maxAmount}
                                                        onValueChange={(
                                                            nextValue,
                                                        ) => {
                                                            setMaxAmount(
                                                                nextValue,
                                                            );
                                                            setPage(1);
                                                        }}
                                                    />
                                                </div>
                                            </div>

                                            <div className="flex justify-end border-t border-border/40 pt-3">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={!hasFilters}
                                                    onClick={() => {
                                                        setSearch('');
                                                        setLoanType(null);
                                                        setStatusFilter('all');
                                                        setAssignmentFilter(
                                                            null,
                                                        );
                                                        setOfficerId(null);
                                                        setMinAmount('');
                                                        setMaxAmount('');
                                                        setSortBy(null);
                                                        setSortDirection(
                                                            'desc',
                                                        );
                                                        setPage(1);
                                                    }}
                                                >
                                                    Clear filters
                                                </Button>
                                            </div>
                                        </div>
                                    </PopoverContent>
                                </Popover>
                            }
                        />
                    </div>
                </section>

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
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border/40 bg-card/60 p-4 shadow-sm">
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
                                {`Claim selected (${claimableSelectedIds.length})`}
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

                <section className="overflow-hidden rounded-2xl border border-border/40 bg-card/60 shadow-sm">
                    <div className="border-b border-border/40 bg-card/70 px-4 py-4 sm:px-6">
                        <h2 className="text-lg font-semibold">
                            Request results
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {resultsLabel}
                        </p>
                    </div>

                    <div className="px-2 pb-2 sm:px-4 sm:pb-4">
                        <div className="hidden md:block">
                            {showSkeleton ? (
                                <TableSkeleton
                                    columns={requestsTableSkeletonColumns}
                                    rows={perPage}
                                    className="pt-4"
                                    tableClassName="bg-transparent"
                                />
                            ) : (
                                <DataTable
                                    columns={columns}
                                    data={items}
                                    emptyMessage={emptyMessage}
                                    className="border-0 bg-transparent"
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
                                                className="rounded-xl border border-border/40 bg-card/50 p-4"
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
                            ) : items.length > 0 ? (
                                <div className="space-y-3 px-2 pt-4 pb-3">
                                    {items.map((item, index) => (
                                        <div
                                            key={
                                                item.id ??
                                                `${item.member_name ?? 'request'}-${index}`
                                            }
                                            className="rounded-xl border border-border/40 bg-card/60 p-4 shadow-sm"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <p className="text-sm font-semibold text-foreground">
                                                        {item.member_name ??
                                                            '--'}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {`Reference: ${item.reference ?? '--'}`}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {item.loan_type ??
                                                            'Loan type unavailable'}
                                                    </p>
                                                </div>
                                                <div className="flex flex-wrap justify-end gap-1">
                                                    <LoanRequestStatusBadge
                                                        status={item.status}
                                                        className="text-[0.65rem]"
                                                    />
                                                    {item.has_open_correction_report ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-amber-500/30 bg-amber-500/10 text-[0.65rem] text-amber-700 dark:text-amber-200"
                                                        >
                                                            Correction reported
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                            </div>
                                            <div className="mt-4 grid grid-cols-2 gap-3 text-xs">
                                                <div className="space-y-1">
                                                    <p className="text-muted-foreground">
                                                        Assignee
                                                    </p>
                                                    <p className="text-sm font-medium text-foreground">
                                                        {item.assigned_officer
                                                            ?.name ??
                                                            'Unassigned'}
                                                    </p>
                                                </div>
                                                <div className="space-y-1">
                                                    <p className="text-muted-foreground">
                                                        Amount
                                                    </p>
                                                    <p className="text-sm font-semibold text-foreground">
                                                        {item.requested_amount !==
                                                            null &&
                                                        item.requested_amount !==
                                                            undefined
                                                            ? formatCurrency(
                                                                  Number(
                                                                      item.requested_amount,
                                                                  ),
                                                              )
                                                            : '--'}
                                                    </p>
                                                </div>
                                                <div className="space-y-1">
                                                    <p className="text-muted-foreground">
                                                        Last Activity
                                                    </p>
                                                    <p className="text-sm font-medium text-foreground">
                                                        {formatDate(
                                                            item.last_activity_at ??
                                                                item.submitted_at ??
                                                                item.created_at,
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="mt-4 flex justify-end">
                                                {item.id ? (
                                                    <RequestRowActionsMenu
                                                        request={item}
                                                        requestId={item.id}
                                                        showRequestHref={
                                                            showRequestHref
                                                        }
                                                        officerOptions={
                                                            meta.assignmentOfficers ??
                                                            []
                                                        }
                                                        isProcessing={
                                                            processingIds[
                                                                item.id
                                                            ] ?? false
                                                        }
                                                        onClaim={() =>
                                                            claimLoanRequest(
                                                                item.id!,
                                                            )
                                                        }
                                                        onOpenAssign={(
                                                            officerOptions,
                                                        ) =>
                                                            setAssignmentDialog(
                                                                {
                                                                    requestId:
                                                                        item.id!,
                                                                    mode: 'assign',
                                                                    officerOptions,
                                                                },
                                                            )
                                                        }
                                                        onOpenReassign={(
                                                            officerOptions,
                                                            currentOfficerName,
                                                        ) =>
                                                            setAssignmentDialog(
                                                                {
                                                                    requestId:
                                                                        item.id!,
                                                                    mode: 'reassign',
                                                                    currentOfficerName,
                                                                    officerOptions,
                                                                },
                                                            )
                                                        }
                                                    />
                                                ) : null}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="px-4 pt-6 pb-6 text-center text-sm text-muted-foreground">
                                    {emptyMessage}
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                {showSkeleton ? (
                    <DataTablePaginationSkeleton />
                ) : (
                    <DataTablePagination
                        page={meta.page}
                        perPage={meta.perPage}
                        total={meta.total}
                        onPageChange={(nextPage) => setPage(nextPage)}
                    />
                )}
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
