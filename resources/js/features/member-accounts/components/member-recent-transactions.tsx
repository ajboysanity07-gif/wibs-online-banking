import { ArrowDownLeft, ArrowUpRight } from 'lucide-react';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import type { MemberRecentAccountAction } from '@/features/member-accounts/types';
import { formatCurrency, formatDate, MASKED_AMOUNT } from '@/lib/formatters';
import { cn } from '@/lib/utils';

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
}: {
    actions: MemberRecentAccountAction[];
    hideAmounts?: boolean;
    limit?: number;
}) {
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

    if (rows.length === 0) {
        return null;
    }

    return (
        <SurfaceCard padding="lg" className="space-y-4">
            <SectionHeader
                title="Recent transactions"
                description="Latest money in and out of your loan and loan security accounts."
                titleClassName="text-lg"
            />
            <ul className="divide-y divide-border">
                {rows.map((row) => {
                    const Icon = row.incoming ? ArrowDownLeft : ArrowUpRight;

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
    );
}
