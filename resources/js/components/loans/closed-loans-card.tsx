import { Link } from '@inertiajs/react';
import { Award } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Button, buttonVariants } from '@/components/ui/button';
import { formatDate } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { MemberLoan } from '@/types/admin';
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

function closedLoansConfig(items: MemberLoan[]): ListToolsConfig<MemberLoan> {
    return {
        searchPlaceholder: 'Search closed loans',
        searchAriaLabel: 'Search closed loans by loan no or type',
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
                key: 'type',
                label: 'Loan type',
                options: deriveOptions(items, (item) => item.lntype),
            },
        ],
        filterMatch: (item, _dimension, value) =>
            String(item.lntype ?? '') === value,
        sortFields: [
            { key: 'lastmove', label: 'Date closed', kind: 'date' },
            { key: 'lnnumber', label: 'Loan no', kind: 'text' },
            { key: 'lntype', label: 'Loan type', kind: 'text' },
        ],
        sortValue: (item, sortKey) => {
            switch (sortKey) {
                case 'lastmove':
                    return item.lastmove ?? null;
                case 'lnnumber':
                    return item.lnnumber ?? null;
                case 'lntype':
                    return item.lntype ?? null;
                default:
                    return null;
            }
        },
    };
}

export function ClosedLoansCard({ loans }: { loans: MemberLoan[] }) {
    const [tools, setTools] = useState(() =>
        initialListToolsValue('type', 'lastmove', 'desc'),
    );
    const [page, setPage] = useState(1);

    const handleToolsChange = useCallback((next: typeof tools) => {
        setTools(next);
        setPage(1);
    }, []);

    const config = useMemo(() => closedLoansConfig(loans), [loans]);
    const closedLoans = useMemo(
        () => loans.filter((loan) => (loan.balance ?? 0) <= 0),
        [loans],
    );
    const visible = useMemo(
        () => applyListTools(closedLoans, tools, config),
        [closedLoans, tools, config],
    );
    const lastPage = Math.max(1, Math.ceil(visible.length / PER_PAGE));
    const currentPage = Math.min(page, lastPage);
    const pageItems = visible.slice(
        (currentPage - 1) * PER_PAGE,
        currentPage * PER_PAGE,
    );
    const summary = describeListTools(tools, config);
    const narrowed = isToolsNarrowed(tools);

    const clearFilter = () =>
        handleToolsChange({ ...tools, filterValue: 'all', search: '' });

    return (
        <SurfaceCard
            variant="default"
            padding="none"
            className="overflow-hidden"
        >
            <div className="border-b border-border px-[18px] py-4">
                <SectionHeader
                    title="Closed loans"
                    description="Loans you have fully paid. Statements, schedules and certificates live on each loan's own page."
                    titleClassName="text-[16px] font-bold"
                    className="sm:items-end"
                    actionsClassName="w-full sm:w-auto sm:max-w-[480px] sm:flex-1"
                    actions={
                        <ListTools
                            config={config}
                            value={tools}
                            onChange={handleToolsChange}
                            filterKey="closed"
                            searchClearAriaLabel="Clear closed loan search"
                        />
                    }
                />
            </div>

            {visible.length === 0 ? (
                <div className="flex flex-col items-center gap-2.5 px-[18px] py-10 text-center">
                    <svg
                        width="28"
                        height="28"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        aria-hidden="true"
                        className="text-muted-foreground"
                    >
                        <circle cx="12" cy="12" r="10" />
                        <path d="m8 12 3 3 5-6" />
                    </svg>
                    <p className="text-[16px] font-bold text-card-foreground">
                        {narrowed
                            ? 'No matching closed loans.'
                            : 'No closed loans.'}
                    </p>
                    <p className="text-[15px] text-muted-foreground">
                        {narrowed
                            ? `Nothing matches ${summary}. Try widening the search or filter.`
                            : 'Fully paid loans stay listed here with their certificates.'}
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
            ) : (
                <>
                    <div>
                        {pageItems.map((loan) => (
                            <div
                                key={`closed-loan-${loan.lnnumber ?? ''}`}
                                className="flex items-center gap-3.5 border-b border-border px-[18px] py-[14px] last:border-b-0 max-sm:flex-wrap"
                            >
                                <span
                                    aria-hidden="true"
                                    className="grid h-10 w-10 shrink-0 place-items-center rounded-[8px] bg-muted/60 text-primary"
                                >
                                    <Award className="h-[18px] w-[18px]" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <span className="block text-[14.5px] font-semibold break-all">
                                        {loan.lnnumber ?? '--'}
                                    </span>
                                    <span className="mt-0.5 block text-[13px] text-muted-foreground">
                                        {loan.lntype ?? 'Loan'} · closed{' '}
                                        {formatDate(loan.lastmove)} ·
                                        certificate of full payment
                                    </span>
                                </div>
                                <Link
                                    href={`/client/loans/${encodeURIComponent(String(loan.lnnumber ?? '').trim())}/payments`}
                                    aria-label={`Open loan ${loan.lnnumber ?? '--'}`}
                                    className={cn(
                                        buttonVariants({
                                            size: 'sm',
                                            variant: 'outline',
                                        }),
                                        'shrink-0 max-sm:w-full',
                                    )}
                                >
                                    Open
                                </Link>
                            </div>
                        ))}
                    </div>

                    <div className="border-t border-border px-[18px] py-[14px]">
                        <ListPager
                            page={currentPage}
                            perPage={PER_PAGE}
                            total={visible.length}
                            noun="closed loans"
                            summary={summary}
                            paginationLabel="Closed loans pagination"
                            onPageChange={setPage}
                        />
                    </div>
                </>
            )}
        </SurfaceCard>
    );
}
