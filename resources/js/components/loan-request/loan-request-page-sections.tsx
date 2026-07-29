import { Check, ChevronsUpDown, Search } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
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
    const [open, setOpen] = useState(false);
    const activeOption = options.find(
        (option) => option.value === activeValue,
    );

    return (
        <div className="space-y-1">
            <span className="text-xs font-medium text-muted-foreground">
                Status
            </span>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        className="w-full justify-between font-normal sm:w-72"
                    >
                        {activeOption?.label ?? 'All'}
                        <ChevronsUpDown className="h-4 w-4 shrink-0 text-muted-foreground" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    align="start"
                    className="w-72 p-0"
                >
                    <Command>
                        <CommandInput placeholder="Search status..." />
                        <CommandList>
                            <CommandEmpty>No status found.</CommandEmpty>
                            <CommandGroup>
                                {options.map((option) => (
                                    <CommandItem
                                        key={option.value}
                                        value={option.label}
                                        onSelect={() => {
                                            onChange(option.value);
                                            setOpen(false);
                                        }}
                                    >
                                        <Check
                                            className={cn(
                                                'h-4 w-4',
                                                activeValue === option.value
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                        {option.label}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
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
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div className="w-full">
                    <label className="text-xs font-medium text-muted-foreground">
                        {label}
                    </label>
                    <div className="relative mt-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={value}
                            onChange={(event) => onChange(event.target.value)}
                            className="h-10 pl-9"
                            placeholder={placeholder}
                            aria-label={label}
                        />
                    </div>
                </div>
                {actions ? (
                    <div className="flex shrink-0 items-center gap-2">
                        {actions}
                    </div>
                ) : null}
            </div>
            {resultsText ? (
                <p className="text-xs text-muted-foreground">{resultsText}</p>
            ) : null}
        </div>
    );
}

export type { LoanRequestStatusFilterOption };
