import { useEffect, useState } from 'react';
import { DetailsLink } from '@/components/loans/details-link';
import { Button } from '@/components/ui/button';
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
import type { MemberRecentAccountAction } from '@/features/member-accounts/types';
import { formatCurrency, formatDate, MASKED_AMOUNT } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { PaginationMeta } from '@/types/pagination';

export type AccountActionsQuery = {
    page: number;
    perPage: number;
    source: 'all' | 'LOAN' | 'SAV';
    search: string;
};

const ROW_OPTIONS = [5, 10, 25];
const SOURCES: Array<{ value: AccountActionsQuery['source']; label: string }> =
    [
        { value: 'all', label: 'All' },
        { value: 'LOAN', label: 'Loan' },
        { value: 'SAV', label: 'Loan Security' },
    ];

const labelClass =
    'text-[11px] font-bold uppercase tracking-[0.08em] text-muted-foreground';
const fieldClass =
    'min-h-10 rounded-lg border border-input bg-card px-3 py-2 text-sm text-foreground';
const headClass =
    'sticky top-0 z-[1] whitespace-nowrap border-b border-border bg-muted px-3.5 py-2.5 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-muted-foreground max-[900px]:px-2.5';
const cellClass =
    'whitespace-nowrap border-b border-border px-3.5 py-3 align-middle group-last:border-b-0 max-[900px]:px-2.5';

function SourceBadge({ source }: { source: string | null }) {
    const label =
        source === 'SAV' ? 'Loan Security' : source === 'LOAN' ? 'Loan' : '--';

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-[3px] text-xs leading-normal font-bold whitespace-nowrap',
                source === 'SAV'
                    ? 'border border-input bg-muted text-foreground'
                    : 'bg-primary text-primary-foreground',
            )}
        >
            {label}
        </span>
    );
}

// Pages collapse to `1 … 4 5 6 … 40` so a long ledger never wraps the footer.
function pageItems(page: number, pages: number): Array<number | 'gap'> {
    if (pages <= 7) {
        return Array.from({ length: pages }, (_, index) => index + 1);
    }

    const items: Array<number | 'gap'> = [1];

    if (page > 3) {
        items.push('gap');
    }

    for (
        let n = Math.max(2, page - 1);
        n <= Math.min(pages - 1, page + 1);
        n++
    ) {
        items.push(n);
    }

    if (page < pages - 2) {
        items.push('gap');
    }

    items.push(pages);

    return items;
}

const pageButtonClass = (current = false) =>
    cn(
        'h-auto min-h-9 min-w-9 rounded-lg border px-2.5 py-1.5 text-[13px] font-semibold disabled:opacity-45 md:h-auto',
        current
            ? 'border-primary bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground'
            : 'border-input bg-card hover:bg-muted hover:text-foreground',
    );

// Body of the "All account activity" dialog: filter tools, table (cards on
// mobile), and footer pager. Filtering and paging run on the server.
export function MemberAccountActionsTable({
    actions,
    meta,
    query,
    loading = false,
    error = null,
    hideAmounts = false,
    onRetry,
    onQueryChange,
    resolveActionHref,
}: {
    actions: MemberRecentAccountAction[];
    meta: PaginationMeta;
    query: AccountActionsQuery;
    loading?: boolean;
    error?: string | null;
    hideAmounts?: boolean;
    onRetry?: () => void;
    onQueryChange: (query: AccountActionsQuery) => void;
    resolveActionHref?: (action: MemberRecentAccountAction) => string | null;
}) {
    const [search, setSearch] = useState(query.search);
    const money = (value: number | null) =>
        hideAmounts ? MASKED_AMOUNT : formatCurrency(value);
    const showSkeleton = loading && actions.length === 0;
    const filtered = query.search.trim() !== '' || query.source !== 'all';
    const empty = !showSkeleton && actions.length === 0;
    const pages = Math.max(1, Math.ceil(meta.total / meta.perPage));
    const from = (meta.page - 1) * meta.perPage;

    useEffect(() => {
        if (search === query.search) {
            return;
        }

        const timer = setTimeout(
            () => onQueryChange({ ...query, search, page: 1 }),
            300,
        );

        return () => clearTimeout(timer);
    }, [search, query, onQueryChange]);

    const go = (page: number) =>
        onQueryChange({ ...query, page: Math.min(Math.max(1, page), pages) });

    const clearFilters = () => {
        setSearch('');
        onQueryChange({ ...query, search: '', source: 'all', page: 1 });
    };

    const status = empty
        ? 'No matching transactions'
        : `Showing ${from + 1}–${from + actions.length} of ${meta.total} transactions`;

    return (
        <>
            <div className="flex flex-wrap items-end gap-3.5 border-b border-border bg-muted px-5 py-3 max-sm:gap-2.5 max-sm:px-4 max-sm:py-2.5">
                <div className="grid min-w-0 gap-1 max-sm:flex-[1_1_100%]">
                    <Label className={labelClass} htmlFor="mxSearch">
                        Search
                    </Label>
                    <Input
                        id="mxSearch"
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Number or type"
                        autoComplete="off"
                        className={cn(
                            fieldClass,
                            'min-w-[220px] max-[900px]:min-w-[160px] max-sm:w-full max-sm:min-w-0',
                        )}
                    />
                </div>
                <div className="grid min-w-0 gap-1">
                    <span className={labelClass} id="mxSrcLbl">
                        Source
                    </span>
                    <div
                        className="flex flex-wrap gap-1.5"
                        role="group"
                        aria-labelledby="mxSrcLbl"
                    >
                        {SOURCES.map((source) => (
                            <Button
                                key={source.value}
                                type="button"
                                variant="ghost"
                                aria-pressed={query.source === source.value}
                                onClick={() =>
                                    onQueryChange({
                                        ...query,
                                        source: source.value,
                                        page: 1,
                                    })
                                }
                                className={cn(
                                    'h-auto min-h-10 rounded-full border px-3.5 py-2 text-[13px] font-semibold md:h-auto',
                                    query.source === source.value
                                        ? 'border-primary bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground'
                                        : 'border-input bg-card hover:bg-muted hover:text-foreground',
                                )}
                            >
                                {source.label}
                            </Button>
                        ))}
                    </div>
                </div>
                <div className="grid min-w-0 gap-1">
                    <Label className={labelClass} htmlFor="mxPerPage">
                        Rows
                    </Label>
                    <Select
                        value={String(query.perPage)}
                        onValueChange={(next) =>
                            onQueryChange({
                                ...query,
                                perPage: Number(next),
                                page: 1,
                            })
                        }
                    >
                        <SelectTrigger
                            id="mxPerPage"
                            className="h-auto min-h-10 w-auto rounded-lg px-3 py-2 text-sm text-foreground shadow-none"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent className="z-[80]">
                            {ROW_OPTIONS.map((rows) => (
                                <SelectItem key={rows} value={String(rows)}>
                                    {rows} per page
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="min-h-0 overflow-auto overscroll-contain">
                {error ? (
                    <div className="grid justify-items-center gap-2 px-5 py-12 text-center">
                        <span className="text-[15px] font-bold">
                            Unable to load account actions
                        </span>
                        <p className="text-sm text-muted-foreground">{error}</p>
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
                ) : empty ? (
                    <div className="grid justify-items-center gap-2 px-5 py-12 text-center">
                        <span className="text-[15px] font-bold">
                            {filtered
                                ? 'No matching activity'
                                : 'No account activity available yet.'}
                        </span>
                        {filtered ? (
                            <>
                                <p className="text-sm text-muted-foreground">
                                    Try a different search, or clear the
                                    filters.
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={clearFilters}
                                >
                                    Clear filters
                                </Button>
                            </>
                        ) : null}
                    </div>
                ) : (
                    <>
                        <table
                            className="w-full border-separate border-spacing-0 text-sm max-md:hidden"
                            aria-label="All account activity"
                        >
                            <thead>
                                <tr>
                                    {['Number', 'Date', 'Type', 'Source'].map(
                                        (label) => (
                                            <th
                                                key={label}
                                                scope="col"
                                                className={headClass}
                                            >
                                                {label}
                                            </th>
                                        ),
                                    )}
                                    {['Amount', 'Balance'].map((label) => (
                                        <th
                                            key={label}
                                            scope="col"
                                            className={`${headClass} text-right`}
                                        >
                                            {label}
                                        </th>
                                    ))}
                                    <th
                                        scope="col"
                                        className={`${headClass} text-right`}
                                    >
                                        <span className="sr-only">View</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {showSkeleton
                                    ? Array.from({ length: meta.perPage }).map(
                                          (_, index) => (
                                              <tr key={`skeleton-${index}`}>
                                                  {Array.from({
                                                      length: 7,
                                                  }).map((__, cell) => (
                                                      <td
                                                          key={cell}
                                                          className={cellClass}
                                                      >
                                                          <Skeleton className="h-4 w-20" />
                                                      </td>
                                                  ))}
                                              </tr>
                                          ),
                                      )
                                    : actions.map((action, index) => {
                                          const href =
                                              resolveActionHref?.(action);

                                          return (
                                              <tr
                                                  key={`${action.ln_sv_number ?? 'action'}-${index}`}
                                                  className="group hover:[&>td]:bg-muted"
                                              >
                                                  <td
                                                      className={`${cellClass} font-bold`}
                                                  >
                                                      {action.ln_sv_number ??
                                                          '--'}
                                                  </td>
                                                  <td className={cellClass}>
                                                      {formatDate(
                                                          action.date_in,
                                                      )}
                                                  </td>
                                                  <td
                                                      className={`${cellClass} min-w-[150px] !whitespace-normal`}
                                                  >
                                                      {action.transaction_type ??
                                                          '--'}
                                                  </td>
                                                  <td className={cellClass}>
                                                      <SourceBadge
                                                          source={action.source}
                                                      />
                                                  </td>
                                                  <td
                                                      className={`${cellClass} text-right font-semibold tabular-nums`}
                                                  >
                                                      {money(action.amount)}
                                                  </td>
                                                  <td
                                                      className={`${cellClass} text-right font-bold tabular-nums`}
                                                  >
                                                      {money(action.balance)}
                                                  </td>
                                                  <td
                                                      className={`${cellClass} text-right`}
                                                  >
                                                      {href ? (
                                                          <DetailsLink
                                                              href={href}
                                                              label={`View ${action.ln_sv_number ?? ''} details`}
                                                          >
                                                              View
                                                          </DetailsLink>
                                                      ) : null}
                                                  </td>
                                              </tr>
                                          );
                                      })}
                            </tbody>
                        </table>
                        <ul
                            className="m-0 hidden list-none p-0 max-md:block"
                            aria-label="All account activity"
                        >
                            {actions.map((action, index) => {
                                const href = resolveActionHref?.(action);

                                return (
                                    <li
                                        key={`${action.ln_sv_number ?? 'action'}-${index}`}
                                        className="grid gap-2 border-b border-border px-5 py-3.5 last:border-b-0 max-sm:px-4"
                                    >
                                        <div className="flex min-w-0 items-center gap-2">
                                            <SourceBadge
                                                source={action.source}
                                            />
                                            <span className="min-w-0 truncate text-xs font-bold tracking-[0.04em] text-muted-foreground">
                                                {action.transaction_type ??
                                                    '--'}
                                            </span>
                                            <span className="ml-auto flex-none text-xs text-muted-foreground">
                                                {formatDate(action.date_in)}
                                            </span>
                                        </div>
                                        <div className="truncate text-[13px] font-bold">
                                            {action.ln_sv_number ?? '--'}
                                        </div>
                                        <div className="flex items-end gap-3">
                                            <span className="grid flex-1 gap-0.5">
                                                <span className={labelClass}>
                                                    Amount
                                                </span>
                                                <span className="font-semibold tabular-nums">
                                                    {money(action.amount)}
                                                </span>
                                            </span>
                                            <span className="grid flex-1 gap-0.5">
                                                <span className={labelClass}>
                                                    Balance
                                                </span>
                                                <span className="font-bold tabular-nums">
                                                    {money(action.balance)}
                                                </span>
                                            </span>
                                            {href ? (
                                                <DetailsLink
                                                    href={href}
                                                    label={`View ${action.ln_sv_number ?? ''} details`}
                                                >
                                                    View
                                                </DetailsLink>
                                            ) : null}
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </>
                )}
            </div>

            <footer className="flex flex-wrap items-center gap-3 border-t border-border bg-muted px-5 py-3 text-[13px] text-muted-foreground max-sm:flex-col max-sm:items-stretch max-sm:gap-2 max-sm:px-4 max-sm:text-center">
                <span
                    className="font-semibold text-foreground"
                    role="status"
                    aria-live="polite"
                >
                    {status}
                </span>
                <span className="hidden font-semibold text-foreground max-sm:block">
                    Page {meta.page} of {pages}
                </span>
                <span className="flex-1 max-sm:hidden" />
                <nav
                    className="flex flex-wrap items-center gap-1.5 max-sm:hidden"
                    aria-label="Account activity pagination"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        className={pageButtonClass()}
                        disabled={meta.page <= 1}
                        onClick={() => go(meta.page - 1)}
                    >
                        Prev
                    </Button>
                    {pageItems(meta.page, pages).map((item, index) =>
                        item === 'gap' ? (
                            <span
                                key={`gap-${index}`}
                                aria-hidden="true"
                                className="px-0.5"
                            >
                                …
                            </span>
                        ) : (
                            <Button
                                key={item}
                                type="button"
                                variant="ghost"
                                aria-current={
                                    item === meta.page ? 'page' : undefined
                                }
                                className={pageButtonClass(item === meta.page)}
                                onClick={() => go(item)}
                            >
                                {item}
                            </Button>
                        ),
                    )}
                    <Button
                        type="button"
                        variant="ghost"
                        className={pageButtonClass()}
                        disabled={meta.page >= pages}
                        onClick={() => go(meta.page + 1)}
                    >
                        Next
                    </Button>
                </nav>
            </footer>
        </>
    );
}
