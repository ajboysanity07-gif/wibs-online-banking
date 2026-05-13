import { Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
    emphasisClassName?: string;
};

type LoanRequestSummaryCardsProps = {
    items: LoanRequestSummaryCardItem[];
    helperText?: string;
};

type LoanRequestStatusFilterOption<TValue extends string> = {
    value: TValue;
    label: string;
};

type LoanRequestStatusFiltersProps<TValue extends string> = {
    options: Array<LoanRequestStatusFilterOption<TValue>>;
    activeValue: TValue;
    onChange: (value: TValue) => void;
};

type LoanRequestSearchBoxProps = {
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    label?: string;
    resultsText?: string;
    actions?: ReactNode;
};

export function LoanRequestPageHero({
    kicker,
    title,
    description,
    cta,
    badges,
}: LoanRequestPageHeroProps) {
    return (
        <section className="rounded-2xl border border-border/40 bg-card/60 p-6 shadow-sm sm:p-7">
            <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div className="space-y-2">
                    <p className="text-xs font-semibold tracking-[0.24em] text-muted-foreground uppercase">
                        {kicker}
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    <p className="max-w-3xl text-sm text-muted-foreground">
                        {description}
                    </p>
                    {badges ? (
                        <div className="flex flex-wrap gap-2">{badges}</div>
                    ) : null}
                </div>
                {cta ? <div className="self-start sm:self-auto">{cta}</div> : null}
            </div>
        </section>
    );
}

export function LoanRequestSummaryCards({
    items,
    helperText,
}: LoanRequestSummaryCardsProps) {
    return (
        <section className="space-y-2">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                {items.map((item) => (
                    <div
                        key={item.label}
                        className="rounded-xl border border-border/40 bg-card/40 px-4 py-3"
                    >
                        <p className="text-xs font-medium text-muted-foreground">
                            {item.label}
                        </p>
                        <p
                            className={cn(
                                'mt-1 text-2xl font-semibold text-foreground',
                                item.emphasisClassName,
                            )}
                        >
                            {item.value}
                        </p>
                    </div>
                ))}
            </div>
            {helperText ? (
                <p className="text-xs text-muted-foreground">{helperText}</p>
            ) : null}
        </section>
    );
}

export function LoanRequestStatusFilters<TValue extends string>({
    options,
    activeValue,
    onChange,
}: LoanRequestStatusFiltersProps<TValue>) {
    return (
        <div className="flex flex-wrap gap-2">
            {options.map((option) => (
                <Button
                    key={option.value}
                    type="button"
                    size="sm"
                    variant={activeValue === option.value ? 'default' : 'outline'}
                    onClick={() => onChange(option.value)}
                >
                    {option.label}
                </Button>
            ))}
        </div>
    );
}

export function LoanRequestSearchBox({
    value,
    onChange,
    placeholder,
    label = 'Search',
    resultsText,
    actions,
}: LoanRequestSearchBoxProps) {
    return (
        <div className="space-y-2">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div className="w-full sm:max-w-sm">
                    <label className="text-xs font-medium text-muted-foreground">
                        {label}
                    </label>
                    <div className="relative mt-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={value}
                            onChange={(event) => onChange(event.target.value)}
                            className="pl-9"
                            placeholder={placeholder}
                            aria-label={label}
                        />
                    </div>
                </div>
                {actions}
            </div>
            {resultsText ? (
                <p className="text-xs text-muted-foreground">{resultsText}</p>
            ) : null}
        </div>
    );
}

export type { LoanRequestStatusFilterOption };
