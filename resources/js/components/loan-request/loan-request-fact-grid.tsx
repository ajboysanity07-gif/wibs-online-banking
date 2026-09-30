import { cn } from '@/lib/utils';

export type LoanRequestFact = { label: string; value: string };

/** Compact label/value grid: 1 column on mobile, 2 from sm, 1px dividers. */
export function LoanRequestFactGrid({
    facts,
    className,
}: {
    facts: LoanRequestFact[];
    className?: string;
}) {
    return (
        <dl
            className={cn(
                'grid grid-cols-1 gap-px overflow-hidden rounded-[10px] border border-border bg-border sm:grid-cols-2',
                className,
            )}
        >
            {facts.map((fact) => (
                <div
                    key={fact.label}
                    className="min-w-0 bg-muted px-3 py-[9px]"
                >
                    <dt className="text-[11px] font-semibold tracking-widest text-muted-foreground uppercase">
                        {fact.label}
                    </dt>
                    <dd className="mt-px text-sm font-semibold [overflow-wrap:anywhere]">
                        {fact.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
