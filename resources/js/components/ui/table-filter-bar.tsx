import { SlidersHorizontal, Search } from 'lucide-react';
import { type ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

type TableSearchBoxProps = {
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    label?: string;
    resultsText?: string;
    actions?: ReactNode;
};

/**
 * Reusable search input + results label, meant to sit alongside a
 * TableFilterPopover in the `actions` slot. Shared across table pages
 * (loan requests, staff management, ...) so filter UX stays consistent.
 */
export function TableSearchBox({
    value,
    onChange,
    placeholder,
    label = 'Search',
    resultsText,
    actions,
}: TableSearchBoxProps) {
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

type TableFilterPopoverProps = {
    filterCount: number;
    onClearFilters: () => void;
    clearDisabled?: boolean;
    children: ReactNode;
    triggerLabel?: string;
    align?: 'start' | 'end';
    contentClassName?: string;
};

/**
 * "Filters" popover trigger with an active-filter-count badge, plus a
 * standard "Clear filters" footer. Pass the filter fields (Selects,
 * comboboxes, etc.) as children.
 */
export function TableFilterPopover({
    filterCount,
    onClearFilters,
    clearDisabled,
    children,
    triggerLabel = 'Filters',
    align = 'end',
    contentClassName = 'w-80 sm:w-96',
}: TableFilterPopoverProps) {
    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button type="button" variant="outline" size="sm" className="h-10">
                    <SlidersHorizontal className="h-4 w-4" />
                    {triggerLabel}
                    {filterCount > 0 ? (
                        <Badge variant="secondary" className="ml-1 px-1.5">
                            {filterCount}
                        </Badge>
                    ) : null}
                </Button>
            </PopoverTrigger>
            <PopoverContent align={align} className={contentClassName}>
                <div className="space-y-4">
                    {children}

                    <div className="flex justify-end border-t border-border/40 pt-3">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={clearDisabled ?? filterCount === 0}
                            onClick={onClearFilters}
                        >
                            Clear filters
                        </Button>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}

type TableFilterFieldProps = {
    label: string;
    htmlFor?: string;
    children: ReactNode;
};

/** Labeled wrapper for a single filter control inside a TableFilterPopover. */
export function TableFilterField({
    label,
    htmlFor,
    children,
}: TableFilterFieldProps) {
    return (
        <div className="space-y-1">
            <label
                className="text-xs font-medium text-muted-foreground"
                htmlFor={htmlFor}
            >
                {label}
            </label>
            {children}
        </div>
    );
}
