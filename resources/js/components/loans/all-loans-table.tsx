import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
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
import { formatCurrency, formatDate } from '@/lib/formatters';
import type { MemberLoan, PaginationMeta } from '@/types/admin';

type AllLoansTableProps = {
    loans: MemberLoan[];
    meta: PaginationMeta;
    isLoading?: boolean;
    error?: string | null;
    onRetry?: () => void;
    onPageChange: (page: number) => void;
};

const NUMERIC_HEAD_CLASS = 'text-right';
const NUMERIC_CELL_CLASS = 'text-right tabular-nums';

function TableSkeletonRows({ rows }: { rows: number }) {
    return (
        <div aria-busy="true" className="flex flex-col">
            {Array.from({ length: Math.min(rows, 10) }).map((_, index) => (
                <TableRow key={`all-loans-skeleton-${index}`}>
                    <TableCell>
                        <Skeleton className="h-4 w-28" />
                    </TableCell>
                    <TableCell>
                        <Skeleton className="h-4 w-36" />
                    </TableCell>
                    <TableCell className={NUMERIC_CELL_CLASS}>
                        <Skeleton className="h-4 w-24" />
                    </TableCell>
                    <TableCell className={NUMERIC_CELL_CLASS}>
                        <Skeleton className="h-4 w-24" />
                    </TableCell>
                    <TableCell>
                        <Skeleton className="h-4 w-28" />
                    </TableCell>
                </TableRow>
            ))}
        </div>
    );
}

export function AllLoansTable({
    loans,
    meta,
    isLoading = false,
    error = null,
    onRetry,
    onPageChange,
}: AllLoansTableProps) {
    const showSkeleton = isLoading && loans.length === 0;

    return (
        <SurfaceCard
            variant="default"
            padding="md"
            className="hidden flex-col gap-5 md:flex"
        >
            <SectionHeader
                title="All loans"
                description="Full loan list with pagination."
                titleClassName="text-lg font-semibold"
            />

            {error ? (
                <div className="flex flex-col items-center gap-2.5 px-4 py-10 text-center">
                    <p className="text-base font-bold">Unable to load loans</p>
                    <p className="text-sm text-muted-foreground">
                        Something went wrong while fetching your loan records.
                    </p>
                    {onRetry ? (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={onRetry}
                        >
                            Try again
                        </Button>
                    ) : null}
                </div>
            ) : (
                <Table className="min-w-[640px]">
                    <TableHeader>
                        <TableRow>
                            <TableHead scope="col">Loan No</TableHead>
                            <TableHead scope="col">Type</TableHead>
                            <TableHead scope="col" className={NUMERIC_HEAD_CLASS}>
                                Principal
                            </TableHead>
                            <TableHead scope="col" className={NUMERIC_HEAD_CLASS}>
                                Balance
                            </TableHead>
                            <TableHead scope="col">Last payment</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {showSkeleton ? (
                            <TableSkeletonRows rows={meta.perPage} />
                        ) : loans.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={5}
                                    className="h-24 text-center text-sm text-muted-foreground"
                                >
                                    Your loan records will be listed here.
                                </TableCell>
                            </TableRow>
                        ) : (
                            loans.map((loan, index) => (
                                <TableRow
                                    key={`all-loans-${loan.lnnumber ?? index}`}
                                >
                                    <TableCell className="font-bold tabular-nums">
                                        {String(loan.lnnumber ?? '--')}
                                    </TableCell>
                                    <TableCell>{loan.lntype ?? '--'}</TableCell>
                                    <TableCell className={NUMERIC_CELL_CLASS}>
                                        {formatCurrency(loan.principal)}
                                    </TableCell>
                                    <TableCell
                                        className={`${NUMERIC_CELL_CLASS} font-bold`}
                                    >
                                        {formatCurrency(loan.balance)}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {formatDate(loan.lastmove)}
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            )}

            {showSkeleton ? (
                <DataTablePaginationSkeleton />
            ) : (
                <DataTablePagination
                    page={meta.page}
                    perPage={meta.perPage}
                    total={meta.total}
                    onPageChange={onPageChange}
                />
            )}
        </SurfaceCard>
    );
}