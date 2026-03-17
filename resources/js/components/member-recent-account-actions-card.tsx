import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { TableSkeleton } from '@/components/ui/table-skeleton';
import { formatCurrency, formatDate } from '@/lib/formatters';
import type {
    MemberRecentAccountAction,
    MemberRecentAccountActionSource,
    PaginationMeta,
} from '@/types/admin';

type MemberRecentAccountActionsCardProps = {
    acctno: string | null;
    actions: MemberRecentAccountAction[];
    meta: PaginationMeta;
    loading?: boolean;
    error?: string | null;
    onRetry?: () => void;
    onPageChange?: (page: number) => void;
};

const accountActionsSkeletonColumns = [
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-16', cellClassName: 'w-20' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-16', cellClassName: 'w-16' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
    { headerClassName: 'w-20', cellClassName: 'w-24' },
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

const MobileAccountActionSkeleton = () => (
    <div className="rounded-lg border border-border bg-card p-4">
        <div className="flex items-start justify-between gap-3">
            <div className="space-y-2">
                <Skeleton className="h-4 w-24" />
                <Skeleton className="h-3 w-20" />
            </div>
            <Skeleton className="h-5 w-12" />
        </div>
        <div className="mt-3 space-y-2 rounded-md border border-border/60 bg-muted/40 p-3">
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
    </div>
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
}: {
    action: MemberRecentAccountAction;
}) => (
    <div className="rounded-lg border border-border bg-card p-4">
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
                {action.source ?? '--'}
            </Badge>
        </div>
        <div className="mt-3 rounded-md border border-border/60 bg-muted/40 p-3">
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
    </div>
);

export function MemberRecentAccountActionsCard({
    acctno,
    actions,
    meta,
    loading = false,
    error = null,
    onRetry,
    onPageChange,
}: MemberRecentAccountActionsCardProps) {
    const actionsEmpty = actions.length === 0;
    const showSkeleton = loading && actionsEmpty;
    const handleRetry = () => {
        onRetry?.();
    };

    return (
        <Card>
            <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle>Recent account actions</CardTitle>
                    <CardDescription>
                        Latest loan and savings movements.
                    </CardDescription>
                </div>
                {loading ? (
                    <span className="text-xs text-muted-foreground">
                        Updating...
                    </span>
                ) : null}
            </CardHeader>
            <CardContent className="space-y-4">
                {!acctno ? (
                    <Alert>
                        <AlertTitle>Account number missing</AlertTitle>
                        <AlertDescription>
                            Add an account number to view loan and savings
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
                                columns={accountActionsSkeletonColumns}
                                rows={meta.perPage}
                                className="rounded-md border"
                                tableClassName="min-w-215"
                            />
                        </div>
                    </>
                ) : (
                    <>
                        <div className="md:hidden">
                            {actionsEmpty ? (
                                <div className="rounded-md border border-border bg-muted/40 px-4 py-6 text-center text-sm text-muted-foreground">
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
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                        <div className="hidden rounded-md border md:block">
                            <Table className="min-w-215">
                                <TableHeader className="text-muted-foreground">
                                    <TableRow>
                                        <TableHead>Number</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Source</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Movement</TableHead>
                                        <TableHead>Balance</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {actionsEmpty ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
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
                                            >
                                                <TableCell className="font-medium">
                                                    {action.ln_sv_number ??
                                                        '--'}
                                                </TableCell>
                                                <TableCell>
                                                    {formatDate(
                                                        action.date_in,
                                                    )}
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
                                                        {action.source ?? '--'}
                                                    </Badge>
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
            </CardContent>
        </Card>
    );
}
