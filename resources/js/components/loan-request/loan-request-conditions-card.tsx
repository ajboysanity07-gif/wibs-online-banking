import { Check } from 'lucide-react';
import { formatDateTime } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { LoanRequestConditions } from '@/types/loan-requests';

type Props = {
    conditions: LoanRequestConditions;
    /** Condition key currently being saved, if any. */
    pendingKey: string | null;
    onToggle: (key: string, verified: boolean) => void;
};

/** Sign-off list; each tick records the actor and time and is audited server-side. */
export function LoanRequestConditionsCard({
    conditions,
    pendingKey,
    onToggle,
}: Props) {
    const done = conditions.items.filter((item) => item.verified).length;
    const readOnly = !conditions.can_verify;

    return (
        <section
            id="conditions-to-clear"
            className="scroll-mt-40 rounded-xl border border-border bg-card p-5 shadow-card"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="text-[17px] font-semibold">
                    Conditions to clear
                </h2>
                {conditions.available ? (
                    <span className="text-[13px] font-bold text-muted-foreground">
                        {done} of {conditions.items.length} verified
                    </span>
                ) : null}
            </div>
            {conditions.available ? (
                <>
                    <p className="mt-0.5 mb-2 text-[13px] text-muted-foreground">
                        Each item is signed off with your name and time, and
                        recorded in the audit trail. Recommend approval unlocks
                        when all are verified.
                        {readOnly
                            ? ' Only the assigned Loan Processor can sign these off.'
                            : ''}
                    </p>
                    <ul>
                        {conditions.items.map((item) => (
                            <li
                                key={item.key}
                                className="border-t border-border"
                            >
                                <label
                                    className={cn(
                                        'flex min-h-11 items-center gap-3 py-2 lg:min-h-9',
                                        readOnly
                                            ? 'cursor-default'
                                            : 'cursor-pointer',
                                    )}
                                >
                                    <input
                                        type="checkbox"
                                        className="peer sr-only"
                                        checked={item.verified}
                                        disabled={
                                            readOnly || pendingKey === item.key
                                        }
                                        onChange={(event) =>
                                            onToggle(
                                                item.key,
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    <span
                                        aria-hidden
                                        className={cn(
                                            'grid size-6 shrink-0 place-items-center rounded-md border-[1.5px] peer-focus-visible:ring-2 peer-focus-visible:ring-ring/50',
                                            item.verified
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-input bg-card',
                                            pendingKey === item.key &&
                                                'opacity-60',
                                        )}
                                    >
                                        {item.verified ? (
                                            <Check className="size-4" />
                                        ) : null}
                                    </span>
                                    <span className="min-w-0 flex-1 text-sm font-medium">
                                        {item.label}
                                    </span>
                                    {item.verified ? (
                                        <span className="shrink-0 text-right text-xs text-muted-foreground">
                                            {item.verified_by ?? 'Staff'}
                                            {item.verified_at
                                                ? ` · ${formatDateTime(item.verified_at)}`
                                                : ''}
                                        </span>
                                    ) : null}
                                </label>
                            </li>
                        ))}
                    </ul>
                </>
            ) : (
                <p className="mt-1 text-[13px] text-muted-foreground">
                    Condition sign-off is pending deployment. It does not block
                    recommending approval until it is available.
                </p>
            )}
        </section>
    );
}
