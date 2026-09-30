import { cn } from '@/lib/utils';

export type LoanRequestFact = { label: string; value: string };

/** Label/value grid: 1 column on mobile, auto-fill 190px columns from sm. */
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
                'grid grid-cols-1 gap-x-[18px] gap-y-3.5 sm:grid-cols-[repeat(auto-fill,minmax(190px,1fr))]',
                className,
            )}
        >
            {facts.map((fact) => (
                <div key={fact.label} className="min-w-0">
                    <dt className="text-xs text-muted-foreground">
                        {fact.label}
                    </dt>
                    <dd className="text-sm font-semibold [overflow-wrap:anywhere]">
                        {fact.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}
