import { ArrowDownLeft, ArrowUpRight, X } from 'lucide-react';
import { useState } from 'react';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    MemberAccountActionsTable,
    type AccountActionsQuery,
} from '@/features/member-accounts/components/member-account-actions-table';
import type { MemberRecentAccountAction } from '@/features/member-accounts/types';
import { formatCurrency, formatDate, MASKED_AMOUNT } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { PaginationMeta } from '@/types/pagination';

type Movement = {
    key: string;
    label: string;
    amount: number;
    incoming: boolean;
};

// Direction comes straight from the ledger columns: loan principal and loan
// security deposits add to the account (in); payments and withdrawals take
// from it (out).
const movementsFor = (action: MemberRecentAccountAction): Movement[] => {
    const type = action.transaction_type ?? 'Transaction';
    const parts: Array<[string, number | null, boolean]> = [
        ['principal', action.principal, true],
        ['deposit', action.deposit, true],
        ['payments', action.payments, false],
        ['withdrawal', action.withdrawal, false],
    ];

    return parts
        .filter(([, amount]) => (amount ?? 0) > 0)
        .map(([key, amount, incoming]) => ({
            key,
            label: type,
            amount: amount ?? 0,
            incoming,
        }));
};

export function MemberRecentTransactions({
    actions,
    hideAmounts = false,
    limit = 5,
    meta,
    loading = false,
    error = null,
    onRetry,
    onQueryChange,
    resolveActionHref,
}: {
    actions: MemberRecentAccountAction[];
    hideAmounts?: boolean;
    limit?: number;
    meta: PaginationMeta;
    loading?: boolean;
    error?: string | null;
    onRetry?: () => void;
    onQueryChange: (query: AccountActionsQuery) => void;
    resolveActionHref?: (action: MemberRecentAccountAction) => string | null;
}) {
    const [showAll, setShowAll] = useState(false);
    const [query, setQuery] = useState<AccountActionsQuery>({
        page: 1,
        perPage: 5,
        source: 'all',
        search: '',
    });
    const changeQuery = (next: AccountActionsQuery) => {
        setQuery(next);
        onQueryChange(next);
    };
    const rows = actions
        .flatMap((action, index) =>
            movementsFor(action).map((movement) => ({
                ...movement,
                id: `${action.ln_sv_number ?? index}-${movement.key}`,
                number: action.ln_sv_number,
                date: action.date_in,
            })),
        )
        .slice(0, limit);

    return (
        <>
            <SurfaceCard padding="lg" className="space-y-4">
                <SectionHeader
                    title="Recent activity"
                    description="Latest account actions"
                    titleClassName="text-lg"
                    actions={
                        meta.total > 0 || error ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setShowAll(true)}
                            >
                                Show all
                            </Button>
                        ) : null
                    }
                />
                {rows.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No recent activity.
                    </p>
                ) : null}
                <ul className="divide-y divide-border">
                    {rows.map((row) => {
                        const Icon = row.incoming
                            ? ArrowDownLeft
                            : ArrowUpRight;

                        return (
                            <li
                                key={row.id}
                                className="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                            >
                                <span
                                    aria-hidden="true"
                                    className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-primary"
                                >
                                    <Icon className="size-4" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold">
                                        {row.label}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {row.number ?? '--'} ·{' '}
                                        {formatDate(row.date)}
                                    </p>
                                </div>
                                <p
                                    className={cn(
                                        'text-sm font-bold tabular-nums',
                                        row.incoming &&
                                            'text-emerald-700 dark:text-emerald-400',
                                    )}
                                >
                                    <span className="sr-only">
                                        {row.incoming ? 'In' : 'Out'}{' '}
                                    </span>
                                    {row.incoming ? '+' : '-'}
                                    {hideAmounts
                                        ? MASKED_AMOUNT
                                        : formatCurrency(row.amount)}
                                </p>
                            </li>
                        );
                    })}
                </ul>
            </SurfaceCard>
            <Dialog open={showAll} onOpenChange={setShowAll}>
                <DialogContent className="grid max-h-[min(86vh,880px)] w-[min(1040px,100%)] grid-rows-[auto_auto_minmax(0,1fr)_auto] gap-0 overflow-hidden p-0 sm:max-w-[1040px] max-sm:h-full max-sm:max-h-none max-sm:w-full max-sm:max-w-none max-sm:rounded-none max-sm:border-0 [&>button:last-child]:hidden">
                    <DialogHeader className="flex-row items-start gap-3 border-b border-border px-5 pt-[18px] pb-3.5 max-sm:p-4">
                        <div className="min-w-0 flex-1">
                            <DialogTitle className="text-[17px] leading-[1.3] font-bold">
                                All account activity
                            </DialogTitle>
                            <DialogDescription className="mt-0.5 text-[13px]">
                                Latest loan and loan security movements.
                            </DialogDescription>
                        </div>
                        <DialogClose
                            aria-label="Close all account activity"
                            className="grid size-9 flex-none place-items-center rounded-lg border border-border bg-card hover:bg-muted"
                        >
                            <X className="size-4" />
                        </DialogClose>
                    </DialogHeader>
                    <MemberAccountActionsTable
                        hideAmounts={hideAmounts}
                        actions={actions}
                        meta={meta}
                        query={query}
                        loading={loading}
                        error={error}
                        onRetry={onRetry}
                        onQueryChange={changeQuery}
                        resolveActionHref={resolveActionHref}
                    />
                </DialogContent>
            </Dialog>
        </>
    );
}
