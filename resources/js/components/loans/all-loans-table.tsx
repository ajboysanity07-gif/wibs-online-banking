import { Table2 } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Button } from '@/components/ui/button';
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
import type { MemberLoan } from '@/types/admin';
import { DetailsLink } from './details-link';
import { ListPager } from './list-pager';
import {
    applyListTools,
    deriveOptions,
    describeListTools,
    initialListToolsValue,
    isToolsNarrowed,
    ListTools,
    type ListToolsConfig,
} from './list-tools';

const PER_PAGE = 5;

const loansConfig = (items: MemberLoan[]): ListToolsConfig<MemberLoan> => ({
    searchPlaceholder: 'Search loans',
    searchAriaLabel: 'Search loans by loan no or type',
    searchMatch: (item, query) =>
        [
            item.lnnumber ?? '',
            item.lntype ?? '',
            item.lastmove ?? '',
            item.balance ?? '',
            item.principal ?? '',
        ]
            .join(' ')
            .toLowerCase()
            .includes(query),
    filterDimensions: [
        {
            key: 'status',
            label: 'Status',
            options: [
                { value: 'active', label: 'Active' },
                { value: 'closed', label: 'Closed' },
            ],
        },
        {
            key: 'type',
            label: 'Loan type',
            options: deriveOptions(items, (item) => item.lntype),
        },
    ],
    filterMatch: (item, dimension, value) => {
        if (dimension === 'status') {
            return value === 'active'
                ? (item.balance ?? 0) > 0
                : (item.balance ?? 0) <= 0;
        }

        return String(item.lntype ?? '') === value;
    },
    sortFields: [
        { key: 'lastmove', label: 'Last payment', kind: 'date' },
        { key: 'lnnumber', label: 'Loan no', kind: 'text' },
        { key: 'lntype', label: 'Loan type', kind: 'text' },
        { key: 'principal', label: 'Principal', kind: 'number' },
        { key: 'balance', label: 'Balance', kind: 'number' },
    ],
    sortValue: (item, sortKey) => {
        switch (sortKey) {
            case 'lastmove':
                return item.lastmove ?? null;
            case 'lnnumber':
                return item.lnnumber ?? null;
            case 'lntype':
                return item.lntype ?? null;
            case 'principal':
                return item.principal ?? null;
            case 'balance':
                return item.balance ?? null;
            default:
                return null;
        }
    },
});

export function AllLoansTable({
    loans,
    isLoading = false,
    error = null,
    onRetry,
}: {
    loans: MemberLoan[];
    isLoading?: boolean;
    error?: string | null;
    onRetry?: () => void;
}) {
    const [tools, setTools] = useState(() =>
        initialListToolsValue('status', 'lastmove', 'desc'),
    );
    const [page, setPage] = useState(1);

    const handleToolsChange = useCallback((next: typeof tools) => {
        setTools(next);
        setPage(1);
    }, []);

    const config = useMemo(() => loansConfig(loans), [loans]);
    const visible = useMemo(
        () => applyListTools(loans, tools, config),
        [loans, tools, config],
    );
    const lastPage = Math.max(1, Math.ceil(visible.length / PER_PAGE));
    const currentPage = Math.min(page, lastPage);
    const pageItems = visible.slice(
        (currentPage - 1) * PER_PAGE,
        currentPage * PER_PAGE,
    );
    const showSkeleton = isLoading && loans.length === 0;
    const summary = describeListTools(tools, config);
    const narrowed = isToolsNarrowed(tools);

    const clearFilter = () =>
        handleToolsChange({ ...tools, filterValue: 'all', search: '' });

    return (
        <SurfaceCard
            variant="default"
            padding="none"
            className="hidden overflow-hidden md:block"
        >
            <div className="border-b border-border px-[18px] py-4">
                <SectionHeader
                    title={
                        <span className="inline-flex items-center gap-2 text-[16px]">
                            <Table2
                                aria-hidden="true"
                                className="h-[18px] w-[18px] shrink-0 text-muted-foreground"
                            />
                            All loans
                        </span>
                    }
                    description="Select a loan's details to open its payment records."
                    titleClassName="text-lg font-semibold"
                    className="sm:items-end"
                    actionsClassName="w-full sm:w-auto sm:max-w-[480px] sm:flex-1"
                    actions={
                        <ListTools
                            config={config}
                            value={tools}
                            onChange={handleToolsChange}
                            filterKey="loans"
                            searchClearAriaLabel="Clear loan search"
                        />
                    }
                />
            </div>

            {error ? (
                <div className="flex flex-col items-center gap-2.5 px-[18px] py-10 text-center">
                    <p className="text-[16px] font-bold text-card-foreground">
                        Unable to load loans
                    </p>
                    <p className="text-[15px] text-muted-foreground">
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
                <div className="overflow-x-auto">
                    <Table className="min-w-[640px] text-[14.5px]">
                        <TableHeader>
                            <TableRow>
                                <TableHead
                                    scope="col"
                                    className="h-auto border-b border-border bg-transparent px-[14px] py-[10px] text-[12px] tracking-[0.06em]"
                                >
                                    Loan No
                                </TableHead>
                                <TableHead
                                    scope="col"
                                    className="h-auto border-b border-border bg-transparent px-[14px] py-[10px] text-[12px] tracking-[0.06em]"
                                >
                                    Type
                                </TableHead>
                                <TableHead
                                    scope="col"
                                    className="h-auto border-b border-border bg-transparent px-[14px] py-[10px] text-right text-[12px] tracking-[0.06em]"
                                >
                                    Principal
                                </TableHead>
                                <TableHead
                                    scope="col"
                                    className="h-auto border-b border-border bg-transparent px-[14px] py-[10px] text-right text-[12px] tracking-[0.06em]"
                                >
                                    Balance
                                </TableHead>
                                <TableHead
                                    scope="col"
                                    className="h-auto border-b border-border bg-transparent px-[14px] py-[10px] text-[12px] tracking-[0.06em]"
                                >
                                    Last payment
                                </TableHead>
                                <TableHead
                                    scope="col"
                                    className="h-auto border-b border-border bg-transparent px-[14px] py-[10px] text-right text-[12px] tracking-[0.06em]"
                                >
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {showSkeleton ? (
                                <TableSkeletonRows rows={PER_PAGE} />
                            ) : visible.length === 0 ? null : (
                                pageItems.map((loan, index) => (
                                    <TableRow
                                        key={`all-loans-${loan.lnnumber ?? index}`}
                                        className="hover:bg-muted"
                                    >
                                        <TableCell className="px-[14px] py-[12px] font-bold whitespace-nowrap tabular-nums">
                                            {String(loan.lnnumber ?? '--')}
                                        </TableCell>
                                        <TableCell className="px-[14px] py-[12px] whitespace-nowrap">
                                            {loan.lntype ?? '--'}
                                        </TableCell>
                                        <TableCell className="px-[14px] py-[12px] text-right whitespace-nowrap tabular-nums">
                                            {formatCurrency(loan.principal)}
                                        </TableCell>
                                        <TableCell className="px-[14px] py-[12px] text-right font-bold whitespace-nowrap tabular-nums">
                                            {formatCurrency(loan.balance)}
                                        </TableCell>
                                        <TableCell className="px-[14px] py-[12px] whitespace-nowrap tabular-nums">
                                            {formatDate(loan.lastmove)}
                                        </TableCell>
                                        <TableCell className="px-[14px] py-[12px] text-right">
                                            <div className="inline-flex flex-wrap justify-end gap-1.5">
                                                <DetailsLink
                                                    href={`/client/loans/${encodeURIComponent(String(loan.lnnumber ?? '').trim())}/payments`}
                                                    label={`View details of loan ${loan.lnnumber ?? '--'}`}
                                                />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                    {!showSkeleton && visible.length === 0 ? (
                        <div className="flex flex-col items-center gap-2.5 px-[18px] py-10 text-center">
                            <p className="text-[16px] font-bold text-card-foreground">
                                {narrowed
                                    ? 'No matching loans.'
                                    : 'No loans found.'}
                            </p>
                            <p className="text-[15px] text-muted-foreground">
                                {narrowed
                                    ? `Nothing matches ${summary}. Try widening the search or filter.`
                                    : 'Your loan records will be listed here.'}
                            </p>
                            {narrowed ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={clearFilter}
                                >
                                    Clear filter
                                </Button>
                            ) : null}
                        </div>
                    ) : null}
                </div>
            )}

            {error ? null : (
                <div className="border-t border-border px-[18px] py-[14px]">
                    <ListPager
                        page={currentPage}
                        perPage={PER_PAGE}
                        total={visible.length}
                        noun="loans"
                        summary={summary}
                        paginationLabel="Loans pagination"
                        onPageChange={setPage}
                        statusOverride={
                            showSkeleton ? 'Loading loans…' : undefined
                        }
                    />
                </div>
            )}
        </SurfaceCard>
    );
}

function TableSkeletonRows({ rows }: { rows: number }) {
    return (
        <>
            {Array.from({ length: Math.min(rows, 10) }).map((_, index) => (
                <TableRow key={`all-loans-skeleton-${index}`}>
                    <TableCell>
                        <Skeleton className="h-4 w-28" />
                    </TableCell>
                    <TableCell>
                        <Skeleton className="h-4 w-36" />
                    </TableCell>
                    <TableCell className="text-right">
                        <Skeleton className="ml-auto h-4 w-24" />
                    </TableCell>
                    <TableCell className="text-right">
                        <Skeleton className="ml-auto h-4 w-24" />
                    </TableCell>
                    <TableCell>
                        <Skeleton className="h-4 w-28" />
                    </TableCell>
                    <TableCell>
                        <Skeleton className="h-4 w-16" />
                    </TableCell>
                </TableRow>
            ))}
        </>
    );
}
