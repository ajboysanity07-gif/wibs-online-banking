import { Search, X } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useFilterPopover } from './filter-popover';

export type ListToolsValue = {
    search: string;
    filterBy: string;
    filterValue: string;
    sortBy: string;
    order: 'asc' | 'desc';
};

export type ListToolsSortField = {
    key: string;
    label: string;
    kind: 'text' | 'number' | 'date';
};

export type ListToolsFilterDimension = {
    key: string;
    label: string;
    options: Array<{ value: string; label: string }>;
};

export type ListToolsConfig<T> = {
    searchPlaceholder: string;
    searchAriaLabel: string;
    searchMatch: (item: T, query: string) => boolean;
    filterDimensions: ListToolsFilterDimension[];
    filterMatch: (item: T, dimension: string, value: string) => boolean;
    sortFields: ListToolsSortField[];
    sortValue: (item: T, sortKey: string) => string | number | null;
};

export const initialListToolsValue = (
    firstDim: string,
    sortBy = '',
    order: 'asc' | 'desc' = 'asc',
): ListToolsValue => ({
    search: '',
    filterBy: firstDim,
    filterValue: 'all',
    sortBy,
    order,
});

/** Filter or search set — sort alone never counts as narrowed. */
export function isToolsNarrowed(value: ListToolsValue): boolean {
    return value.filterValue !== 'all' || value.search.trim() !== '';
}

/** "Status: Active · search \"staff\"" — appended to pager count lines. */
export function describeListTools<T>(
    value: ListToolsValue,
    config: ListToolsConfig<T>,
): string {
    const parts: string[] = [];
    const dimension = config.filterDimensions.find(
        (item) => item.key === value.filterBy,
    );

    if (value.filterValue !== 'all') {
        const label = dimension?.options.find(
            (option) => option.value === value.filterValue,
        )?.label;

        parts.push(
            `${dimension?.label ?? 'Filter'}: ${label ?? value.filterValue}`,
        );
    }

    if (value.search.trim() !== '') {
        parts.push(`search "${value.search.trim()}"`);
    }

    return parts.join(' · ');
}

export function ListTools<T>({
    config,
    value,
    onChange,
    filterKey,
    searchClearAriaLabel = 'Clear search',
}: {
    config: ListToolsConfig<T>;
    value: ListToolsValue;
    onChange: (next: ListToolsValue) => void;
    filterKey: string;
    searchClearAriaLabel?: string;
}) {
    const triggerRef = useRef<HTMLButtonElement>(null);
    const searchRef = useRef<HTMLInputElement>(null);
    const { isOpen, onToggleFilter } = useFilterPopover(
        filterKey,
        config,
        value,
        onChange,
    );

    const activeDimension = config.filterDimensions.find(
        (dimension) => dimension.key === value.filterBy,
    );
    const activeValueLabel = activeDimension?.options.find(
        (option) => option.value === value.filterValue,
    )?.label;
    const badgeVisible = value.filterValue !== 'all';

    const update = (patch: Partial<ListToolsValue>) =>
        onChange({ ...value, ...patch });

    return (
        <div className="flex w-full flex-wrap items-center gap-2">
            <div className="relative min-w-[min(100%,220px)] flex-1">
                <Search
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    ref={searchRef}
                    type="search"
                    value={value.search}
                    aria-label={config.searchAriaLabel}
                    placeholder={config.searchPlaceholder}
                    onChange={(event) => update({ search: event.target.value })}
                    className="h-10 w-full rounded-[8px] border-border pr-9 pl-9 text-[14.5px] text-card-foreground shadow-none md:h-10 md:text-[14.5px] [&::-webkit-search-cancel-button]:hidden"
                />
                {value.search !== '' ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={searchClearAriaLabel}
                        onClick={() => {
                            update({ search: '' });
                            searchRef.current?.focus();
                        }}
                        className="absolute top-1/2 right-2.5 size-6 -translate-y-1/2 rounded-full text-muted-foreground hover:bg-transparent hover:text-card-foreground md:size-6"
                    >
                        <X aria-hidden="true" className="h-4 w-4" />
                    </Button>
                ) : null}
            </div>

            <Button
                ref={triggerRef}
                type="button"
                variant="outline"
                aria-expanded={isOpen}
                aria-haspopup="dialog"
                onClick={() =>
                    triggerRef.current && onToggleFilter(triggerRef.current)
                }
                className="min-h-10 px-3 text-[13.5px] max-sm:w-full"
            >
                <svg
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    aria-hidden="true"
                >
                    <path d="M3 6h18M6 12h12M10 18h4" />
                </svg>
                Filter
                {badgeVisible ? (
                    <span className="ml-1 rounded-full bg-primary px-2 py-0.5 text-[11px] font-bold text-primary-foreground tabular-nums">
                        {activeValueLabel ?? value.filterValue}
                    </span>
                ) : null}
            </Button>
        </div>
    );
}

export function applyListTools<T>(
    items: readonly T[],
    value: ListToolsValue,
    config: ListToolsConfig<T>,
): T[] {
    const query = value.search.trim().toLowerCase();
    let visible = items.filter((item) => {
        if (query !== '' && !config.searchMatch(item, query)) {
            return false;
        }

        if (
            value.filterValue !== 'all' &&
            !config.filterMatch(item, value.filterBy, value.filterValue)
        ) {
            return false;
        }

        return true;
    });

    if (value.sortBy !== '') {
        const sortField = config.sortFields.find(
            (field) => field.key === value.sortBy,
        );

        if (sortField) {
            const direction = value.order === 'asc' ? 1 : -1;

            visible = [...visible].sort((left, right) => {
                const a = config.sortValue(left, value.sortBy);
                const b = config.sortValue(right, value.sortBy);

                if (a === null || a === undefined) {
                    return b === null || b === undefined ? 0 : 1;
                }

                if (b === null || b === undefined) {
                    return -1;
                }

                if (typeof a === 'number' && typeof b === 'number') {
                    return (a - b) * direction;
                }

                return String(a).localeCompare(String(b)) * direction;
            });
        }
    }

    return visible;
}

/** Derive filter options from the data so only values the member actually has appear. */
export function deriveOptions<T>(
    items: readonly T[],
    keyOf: (item: T) => string | null | undefined,
    labelOf?: (value: string) => string,
    direction: 'asc' | 'desc' = 'asc',
): Array<{ value: string; label: string }> {
    const seen = new Map<string, string>();

    for (const item of items) {
        const raw = keyOf(item);

        if (raw === null || raw === undefined || raw.trim() === '') {
            continue;
        }

        const value = raw.trim();

        if (!seen.has(value)) {
            seen.set(value, labelOf ? labelOf(value) : value);
        }
    }

    const sorted = [...seen.entries()].sort(([a], [b]) => a.localeCompare(b));

    if (direction === 'desc') {
        sorted.reverse();
    }

    return sorted.map(([value, label]) => ({ value, label }));
}
