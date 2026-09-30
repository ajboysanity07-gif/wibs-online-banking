import { useState } from 'react';
import { LoanStatusWarning } from '@/components/loan-request/loan-status-warning';
import { Button } from '@/components/ui/button';
import type {
    AttentionActionKey,
    AttentionRow,
    AttentionTone,
} from '@/lib/loan-request-attention';
import { cn } from '@/lib/utils';
import type { LoanStatusSummaryForStaff } from '@/types/loan-requests';

type Props = {
    rows: AttentionRow[];
    blockingCount: number;
    /** Expanded by the "View loans" action of the problem-loan row. */
    loanStatus: LoanStatusSummaryForStaff | null;
    onAction: (key: Exclude<AttentionActionKey, 'view-loan-details'>) => void;
};

const dotClassName: Record<AttentionTone, string> = {
    blocking: 'bg-destructive',
    warning: 'bg-amber-500',
    info: 'bg-sky-500',
};

export function LoanRequestAttentionCard({
    rows,
    blockingCount,
    loanStatus,
    onAction,
}: Props) {
    const [showLoans, setShowLoans] = useState(false);

    return (
        <section
            aria-label="Needs your attention"
            className="overflow-hidden rounded-xl border border-border bg-card shadow-card"
        >
            <div className="flex items-center justify-between gap-2.5 px-5 py-3.5">
                <h2 className="text-[17px] font-semibold">
                    Needs your attention
                </h2>
                <span
                    className={cn(
                        'rounded-full px-2.5 py-0.5 text-xs font-bold',
                        blockingCount > 0
                            ? 'bg-destructive/10 text-destructive'
                            : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-200',
                    )}
                >
                    {blockingCount > 0
                        ? `${blockingCount} to fix`
                        : 'All clear'}
                </span>
            </div>
            {rows.length === 0 ? (
                <p className="border-t border-border px-5 py-3 text-sm text-muted-foreground">
                    Nothing needs your attention on this request.
                </p>
            ) : (
                rows.map((row) => {
                    const action = row.action;

                    return (
                        <div key={row.key} className="border-t border-border">
                            <div className="flex flex-wrap items-center gap-x-3 gap-y-2 px-5 py-3">
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'size-2.5 shrink-0 rounded-full',
                                        dotClassName[row.tone],
                                    )}
                                />
                                <div className="min-w-0 flex-1 basis-56">
                                    <p className="text-sm font-bold">
                                        {row.title}
                                    </p>
                                    <p className="text-[13px] text-muted-foreground">
                                        {row.description}
                                    </p>
                                </div>
                                {action ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="min-h-11 border-primary font-bold text-primary lg:min-h-9"
                                        onClick={() =>
                                            action.key === 'view-loan-details'
                                                ? setShowLoans((open) => !open)
                                                : onAction(action.key)
                                        }
                                    >
                                        {action.key === 'view-loan-details' &&
                                        showLoans
                                            ? 'Hide loans'
                                            : action.label}
                                    </Button>
                                ) : null}
                                {row.tag ? (
                                    <span className="rounded-md bg-amber-500/10 px-2 py-0.5 text-xs font-bold text-amber-700 dark:text-amber-200">
                                        {row.tag}
                                    </span>
                                ) : null}
                            </div>
                            {row.key === 'problem-loans' && showLoans ? (
                                <div className="px-5 pb-3">
                                    <LoanStatusWarning
                                        loanStatus={loanStatus}
                                    />
                                </div>
                            ) : null}
                        </div>
                    );
                })
            )}
        </section>
    );
}
