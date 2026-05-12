import { Link } from '@inertiajs/react';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DataTablePagination,
    DataTablePaginationSkeleton,
} from '@/components/ui/data-table-pagination';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    TableSkeleton,
    type TableSkeletonColumn,
} from '@/components/ui/table-skeleton';
import type { MemberRecentAccountAction, MemberRecentAccountActionSource } from '@/features/member-accounts/types';
import { formatCurrency, formatDate } from '@/lib/formatters';
import type { PaginationMeta } from '@/types/pagination';

type MemberRecentAccountActionsCardProps = {
    acctno: string | null;
    actions: MemberRecentAccountAction[];
    meta: PaginationMeta;
    loading?: boolean;
    error?: string | null;
    onRetry?: () => void;
    onPageChange?: (page: number) => void;
    resolveActionHref?: (action: MemberRecentAccountAction) => string | null;
};

const accountActionsSkeletonColumns = (
    showActions: boolean,
): TableSkeletonColumn[] => [
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-16', cellClassName: 'w-20' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-16', cellClassName: 'w-16' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    ...(showActions
        ? [{ headerClassName: 'w-12', cellClassName: 'h-8 w-20', align: 'right' }]
        : []),
];

const sourceVariant = (source?: MemberRecentAccountActionSource | null) => {
    if (source === 'LOAN') {
        return 'default';
    }

    if (source === 'SAV') {
        return 'secondary';
    }

    return 'outline';
};

const sourceLabel = (source?: MemberRecentAccountActionSource | null) => {
    if (source === 'LOAN') {
        return 'Loan';
    }

    if (source === 'SAV') {
        return 'Loan Security';
    }

    return source ?? '--';
};

const MobileAccountActionSkeleton = () => (
    <SurfaceCard variant="default" padding="sm">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-2">
                <Skeleton className="h-4 w-24" />
                <Skeleton className="h-3 w-20" />
            </div>
            <Skeleton className="h-5 w-12" />
        </div>
        <div className="mt-3 space-y-2 rounded-xl border border-border/30 bg-muted/30 p-3">
            {Array.from({ length: 3 }).map((_, index) => (
                <div
                    key={`action-meta-${index}`}
                    className="flex items-center justify-between"
                >
                    <Skeleton className="h-3 w-20" />
                    <Skeleton className="h-4 w-24" />
                </div>
            ))}
        </div>
        <div className="mt-3">
            <Skeleton className="h-3 w-24" />
        </div>
    </SurfaceCard>
);

const MobileAccountActionSkeletonList = ({ rows = 3 }: { rows?: number }) => (
    <div className="space-y-3">
        {Array.from({ length: rows }).map((_, index) => (
            <MobileAccountActionSkeleton
                key={`mobile-action-skeleton-${index}`}
            />
        ))}
    </div>
);

const MobileAccountActionCard = ({
    action,
    actionHref,
}: {
    action: MemberRecentAccountAction;
    actionHref: string | null;
}) => (
    <SurfaceCard variant="default" padding="sm">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-1">
                <p className="text-sm font-semibold">
                    {action.ln_sv_number ?? '--'}
                </p>
                <p className="text-xs text-muted-foreground">
                    {action.transaction_type ?? '--'}
                </p>
            </div>
            <Badge variant={sourceVariant(action.source)}>
                {sourceLabel(action.source)}
            </Badge>
        </div>
        <div className="mt-3 rounded-xl border border-border/30 bg-muted/30 p-3">
            <div className="flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Control ID</span>
                <span className="text-sm font-medium tabular-nums">
                    {action.control_no ?? '--'}
                </span>
            </div>
            <div className="flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Amount</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatCurrency(action.amount)}
                </span>
            </div>
            <div className="mt-2 flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Movement</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatCurrency(action.movement)}
                </span>
            </div>
            <div className="mt-2 flex items-center justify-between text-xs">
                <span className="text-muted-foreground">Balance</span>
                <span className="text-sm font-medium tabular-nums">
                    {formatCurrency(action.balance)}
                </span>
            </div>
        </div>
        <p className="mt-3 text-xs text-muted-foreground">
            Date: {formatDate(action.date_in)}
        </p>
        {actionHref !== null ? (
            <Button asChild size="sm" variant="outline" className="mt-3 w-full">
                <Link href={actionHref}>View transaction</Link>
            </Button>
        ) : null}
    </SurfaceCard>
);

export function MemberRecentAccountActionsCard({
    acctno,
    actions,
    meta,
    loading = false,
    error = null,
    onRetry,
    onPageChange,
    resolveActionHref,
}: MemberRecentAccountActionsCardProps) {
    const actionsEmpty = actions.length === 0;
    const showSkeleton = loading && actionsEmpty;
    const handleRetry = () => {
        onRetry?.();
    };
    const showActions = Boolean(resolveActionHref);
    const columnCount = showActions ? 9 : 8;

    return (
        <SurfaceCard variant="default" padding="lg" className="space-y-5">
            <SectionHeader
                title="Recent account actions"
                description="Latest loan and loan security movements."
                actions={
                    loading ? (
                        <Badge
                            variant="outline"
                            className="text-[0.65rem] uppercase tracking-[0.2em]"
                        >
                            Updating
                        </Badge>
                    ) : null
                }
                titleClassName="text-base font-semibold"
            />
            <div className="space-y-4">
                {!acctno ? (
                    <Alert>
                        <AlertTitle>Account number missing</AlertTitle>
                        <AlertDescription>
                            Add an account number to view loan and loan security
                            activity.
                        </AlertDescription>
                    </Alert>
                ) : null}
                {error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Unable to load account actions</AlertTitle>
                        <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <span>{error}</span>
                            {onRetry ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={handleRetry}
                                >
                                    Retry
                                </Button>
                            ) : null}
                        </AlertDescription>
                    </Alert>
                ) : null}
                {showSkeleton ? (
                    <>
                        <div className="md:hidden" aria-busy="true">
                            <MobileAccountActionSkeletonList rows={3} />
                        </div>
                        <div className="hidden md:block" aria-busy="true">
                            <TableSkeleton
                                columns={accountActionsSkeletonColumns(
                                    showActions,
                                )}
                                rows={meta.perPage}
                                className="rounded-xl border border-border/40 bg-card/60"
                                tableClassName="min-w-240"
                            />
                        </div>
                    </>
                ) : (
                    <>
                        <div className="md:hidden">
                            {actionsEmpty ? (
                                <div className="rounded-xl border border-border/40 bg-muted/30 px-4 py-6 text-center text-sm text-muted-foreground">
                                    No account activity available yet.
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {actions.map((action, index) => (
                                        <MobileAccountActionCard
                                            key={
                                                action.ln_sv_number ??
                                                `action-${index}`
                                            }
                                            action={action}
                                            actionHref={
                                                resolveActionHref?.(action) ??
                                                null
                                            }
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                        <div className="hidden rounded-xl border border-border/40 bg-card/60 md:block">
                            <Table className="min-w-240">
                                <TableHeader className="border-b border-border/40 text-muted-foreground">
                                    <TableRow>
                                        <TableHead>Number</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Source</TableHead>
                                        <TableHead>Control ID</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Movement</TableHead>
                                        <TableHead>Balance</TableHead>
                                        {showActions ? (
                                            <TableHead className="text-right">
                                                Action
                                            </TableHead>
                                        ) : null}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {actionsEmpty ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={columnCount}
                                                className="h-24 text-center text-sm text-muted-foreground"
                                            >
                                                No account activity available
                                                yet.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        actions.map((action, index) => (
                                            <TableRow
                                                key={
                                                    action.ln_sv_number ??
                                                    `action-${index}`
                                                }
                                                className="transition-colors hover:bg-muted/20"
                                            >
                                                <TableCell className="font-medium">
                                                    {action.ln_sv_number ??
                                                        '--'}
                                                </TableCell>
                                                <TableCell>
                                                    {formatDate(action.date_in)}
                                                </TableCell>
                                                <TableCell>
                                                    {action.transaction_type ??
                                                        '--'}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={sourceVariant(
                                                            action.source,
                                                        )}
                                                    >
                                                        {sourceLabel(
                                                            action.source,
                                                        )}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="tabular-nums">
                                                    {action.control_no ?? '--'}
                                                </TableCell>
                                                <TableCell>
                                                    {formatCurrency(
                                                        action.amount,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {formatCurrency(
                                                        action.movement,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {formatCurrency(
                                                        action.balance,
                                                    )}
                                                </TableCell>
                                                {showActions ? (
                                                    <TableCell className="text-right">
                                                        {resolveActionHref?.(
                                                            action,
                                                        ) ? (
                                                            <Button
                                                                asChild
                                                                size="sm"
                                                                variant="outline"
                                                            >
                                                                <Link
                                                                    href={
                                                                        resolveActionHref?.(
                                                                            action,
                                                                        ) as string
                                                                    }
                                                                >
                                                                    View
                                                                </Link>
                                                            </Button>
                                                        ) : (
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                disabled
                                                            >
                                                                Unavailable
                                                            </Button>
                                                        )}
                                                    </TableCell>
                                                ) : null}
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </>
                )}
                {!error && onPageChange ? (
                    showSkeleton ? (
                        <DataTablePaginationSkeleton />
                    ) : (
                        <DataTablePagination
                            page={meta.page}
                            perPage={meta.perPage}
                            total={meta.total}
                            onPageChange={onPageChange}
                        />
                    )
                ) : null}
            </div>
        </SurfaceCard>
    );
}
