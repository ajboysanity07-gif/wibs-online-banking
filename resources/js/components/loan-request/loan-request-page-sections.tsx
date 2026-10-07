import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type LoanRequestPageHeroProps = {
    kicker: string;
    title: string;
    description: string;
    cta?: ReactNode;
    badges?: ReactNode;
};

type LoanRequestSummaryCardItem = {
    label: string;
    value: number | string;
    helper?: string;
};

type LoanRequestSummaryCardsProps = {
    items: LoanRequestSummaryCardItem[];
    helperText?: string;
};

type LoanRequestStatusFilterOption<TValue extends string> = {
    value: TValue;
    label: string;
};

type LoanRequestFilterChipsProps<TValue extends string> = {
    options: Array<LoanRequestStatusFilterOption<TValue>>;
    value: TValue;
    onChange: (value: TValue) => void;
    label: string;
};

export function LoanRequestPageHero({
    kicker,
    title,
    description,
    cta,
    badges,
}: LoanRequestPageHeroProps) {
    return (
        <section className="flex flex-wrap items-start gap-5">
            <div>
                <p className="text-[11px] font-bold tracking-[0.14em] text-primary uppercase">
                    {kicker}
                </p>
                <h1 className="mt-1 text-[22px] leading-tight font-bold sm:text-[26px]">
                    {title}
                </h1>
                <p className="mt-1.5 max-w-prose text-sm text-muted-foreground">
                    {description}
                </p>
                {badges ? (
                    <div className="mt-3 flex flex-wrap gap-2">{badges}</div>
                ) : null}
            </div>
            {cta ? (
                <div className="pt-1.5 max-sm:w-full sm:ml-auto">{cta}</div>
            ) : null}
        </section>
    );
}

export function LoanRequestSummaryCards({
    items,
    helperText,
}: LoanRequestSummaryCardsProps) {
    return (
        <section aria-label="Loan request summary" className="space-y-2.5">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-3">
                {items.map((item) => (
                    <div
                        key={item.label}
                        className="flex min-w-0 flex-col gap-1 rounded-lg border border-border bg-card p-4"
                    >
                        <span className="text-[13px] text-muted-foreground">
                            {item.label}
                        </span>
                        <span className="text-[22px] font-bold whitespace-nowrap tabular-nums">
                            {item.value}
                        </span>
                        {item.helper ? (
                            <span className="truncate text-[13px] text-muted-foreground">
                                {item.helper}
                            </span>
                        ) : null}
                    </div>
                ))}
            </div>
            {helperText ? (
                <p className="text-xs text-muted-foreground">{helperText}</p>
            ) : null}
        </section>
    );
}

export function LoanRequestFilterChips<TValue extends string>({
    options,
    value,
    onChange,
    label,
}: LoanRequestFilterChipsProps<TValue>) {
    return (
        <div role="group" aria-label={label} className="flex flex-wrap gap-2">
            {options.map((option) => {
                const active = option.value === value;

                return (
                    <button
                        key={option.value}
                        type="button"
                        aria-pressed={active}
                        onClick={() => onChange(option.value)}
                        className={cn(
                            'min-h-11 rounded-full border px-3.5 text-[13px] font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:outline-none',
                            active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-card text-foreground hover:bg-muted',
                        )}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}

export type { LoanRequestStatusFilterOption };
