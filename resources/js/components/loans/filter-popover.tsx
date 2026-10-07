import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useRef,
    useState,
    type ReactNode,
} from 'react';
import { createPortal } from 'react-dom';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    type ListToolsFilterDimension,
    type ListToolsSortField,
    type ListToolsValue,
} from './list-tools';

export type FilterPopoverConfig = {
    filterDimensions: ListToolsFilterDimension[];
    sortFields: ListToolsSortField[];
};

type FilterPopoverEntry = {
    config: FilterPopoverConfig;
    value: ListToolsValue;
    onChange: (next: ListToolsValue) => void;
};

type FilterPopoverRequest = {
    key: string;
    anchor: HTMLElement;
};

type FilterPopoverContextValue = {
    register: (key: string, entry: FilterPopoverEntry) => void;
    unregister: (key: string) => void;
    toggle: (key: string, anchor: HTMLElement) => void;
    close: () => void;
    openRequest: FilterPopoverRequest | null;
};

const FilterPopoverContext = createContext<FilterPopoverContextValue | null>(
    null,
);

/**
 * One shared popover element for every filter menu on the page. It is
 * portalled to document.body and positioned fixed, so card rounding
 * (overflow) can never clip it and opening it never pushes content down.
 */
export function FilterPopoverProvider({ children }: { children: ReactNode }) {
    const [entries, setEntries] = useState(
        () => new Map<string, FilterPopoverEntry>(),
    );
    const [openRequest, setOpenRequest] = useState<FilterPopoverRequest | null>(
        null,
    );

    const register = useCallback((key: string, entry: FilterPopoverEntry) => {
        setEntries((previous) => {
            const next = new Map(previous);
            next.set(key, entry);

            return next;
        });
    }, []);

    const unregister = useCallback((key: string) => {
        setEntries((previous) => {
            if (!previous.has(key)) {
                return previous;
            }

            const next = new Map(previous);
            next.delete(key);

            return next;
        });
        setOpenRequest((current) => (current?.key === key ? null : current));
    }, []);

    const toggle = useCallback((key: string, anchor: HTMLElement) => {
        setOpenRequest((current) =>
            current?.key === key ? null : { key, anchor },
        );
    }, []);

    const close = useCallback(() => setOpenRequest(null), []);

    return (
        <FilterPopoverContext.Provider
            value={{ register, unregister, toggle, close, openRequest }}
        >
            {children}
            <FilterPopoverOutlet
                key={openRequest?.key ?? 'closed'}
                entries={entries}
                openRequest={openRequest}
                onClose={close}
            />
        </FilterPopoverContext.Provider>
    );
}

function usePopoverContext(): FilterPopoverContextValue {
    const context = useContext(FilterPopoverContext);

    if (!context) {
        throw new Error(
            'useFilterPopover must be used inside a FilterPopoverProvider.',
        );
    }

    return context;
}

/**
 * Connects one list's filter state to the shared popover. The list keeps
 * owning its own state; this only registers it while mounted.
 */
export function useFilterPopover(
    key: string,
    config: FilterPopoverConfig,
    value: ListToolsValue,
    onChange: (next: ListToolsValue) => void,
): { isOpen: boolean; onToggleFilter: (anchor: HTMLElement) => void } {
    const { register, unregister, toggle, openRequest } = usePopoverContext();
    const onChangeRef = useRef(onChange);

    useEffect(() => {
        onChangeRef.current = onChange;
    }, [onChange]);

    const stableOnChange = useCallback(
        (next: ListToolsValue) => onChangeRef.current(next),
        [],
    );

    useEffect(() => {
        register(key, { config, value, onChange: stableOnChange });

        return () => unregister(key);
    }, [key, config, value, stableOnChange, register, unregister]);

    return {
        isOpen: openRequest?.key === key,
        onToggleFilter: (anchor: HTMLElement) => toggle(key, anchor),
    };
}

function orderOptions(
    sortFields: ListToolsSortField[],
    sortBy: string,
): Array<{ value: 'asc' | 'desc'; label: string }> {
    const active = sortFields.find((field) => field.key === sortBy);

    if (active?.kind === 'text') {
        return [
            { value: 'desc', label: 'Z – A' },
            { value: 'asc', label: 'A – Z' },
        ];
    }

    return [
        { value: 'desc', label: 'Highest first' },
        { value: 'asc', label: 'Lowest first' },
    ];
}

function FilterPopoverOutlet({
    entries,
    openRequest,
    onClose,
}: {
    entries: Map<string, FilterPopoverEntry>;
    openRequest: FilterPopoverRequest | null;
    onClose: () => void;
}) {
    const popoverRef = useRef<HTMLDivElement>(null);
    const [position, setPosition] = useState<{
        top: number;
        left: number;
        width?: number;
    } | null>(null);

    const entry = openRequest ? entries.get(openRequest.key) : undefined;

    const place = useCallback(() => {
        const popover = popoverRef.current;

        if (!openRequest || !popover) {
            return;
        }

        const trigger = openRequest.anchor.getBoundingClientRect();
        const gap = 8;
        const margin = 12;

        if (window.innerWidth < 768) {
            const width = Math.max(0, window.innerWidth - margin * 2);
            popover.style.width = `${width}px`;

            const height = popover.offsetHeight;
            const below = trigger.bottom + gap;
            const top =
                below + height + margin <= window.innerHeight ||
                below + 40 >= trigger.top
                    ? below
                    : Math.max(margin, trigger.top - gap - height);

            setPosition({ top, left: margin });

            return;
        }

        popover.style.width = '320px';

        const width = Math.min(320, window.innerWidth - margin * 2);
        const height = popover.offsetHeight;
        const below = trigger.bottom + gap;
        const top =
            below + height + margin <= window.innerHeight ||
            below + 40 >= trigger.top
                ? below
                : Math.max(margin, trigger.top - gap - height);
        const left = Math.min(
            Math.max(margin, trigger.right - width),
            window.innerWidth - width - margin,
        );

        setPosition({ top, left });
    }, [openRequest]);

    useEffect(() => {
        if (!openRequest) {
            return;
        }

        // Position after paint so the popover's measured size is real; the
        // initial style keeps it hidden until then. Listeners re-anchor it
        // on scroll/resize without ever moving card content.
        const frame = requestAnimationFrame(() => place());

        window.addEventListener('scroll', place, true);
        window.addEventListener('resize', place);

        const onPointerDown = (event: PointerEvent) => {
            const target = event.target as Node;

            if (
                popoverRef.current?.contains(target) ||
                (target as Element).closest?.('[data-slot="select-content"]') ||
                openRequest.anchor.contains(target)
            ) {
                return;
            }

            onClose();
        };

        const onKeyDown = (event: globalThis.KeyboardEvent) => {
            if (
                event.key === 'Escape' &&
                !document.querySelector('[data-slot="select-content"]')
            ) {
                onClose();
                openRequest.anchor.focus();
            }
        };

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            cancelAnimationFrame(frame);
            window.removeEventListener('scroll', place, true);
            window.removeEventListener('resize', place);
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [openRequest, entry?.value, onClose, place]);
    if (!openRequest || !entry || typeof document === 'undefined') {
        return null;
    }

    const { config, value, onChange } = entry;
    const activeDimension = config.filterDimensions.find(
        (dimension) => dimension.key === value.filterBy,
    );
    const activeValueLabel = activeDimension?.options.find(
        (option) => option.value === value.filterValue,
    )?.label;
    const narrowed = value.filterValue !== 'all' || value.search.trim() !== '';

    const summaryParts: string[] = [];

    if (value.filterValue !== 'all') {
        summaryParts.push(
            `${activeDimension?.label ?? 'Filter'}: ${activeValueLabel ?? value.filterValue}`,
        );
    }

    if (value.search.trim() !== '') {
        summaryParts.push(`search "${value.search.trim()}"`);
    }

    const update = (patch: Partial<ListToolsValue>) =>
        onChange({ ...value, ...patch });

    return createPortal(
        <div
            ref={popoverRef}
            role="dialog"
            aria-label="Filter and sort"
            className="fixed z-[70] rounded-[12px] border border-border bg-card p-3.5 shadow-[0_12px_32px_rgba(20,23,15,0.16),0_2px_6px_rgba(20,23,15,0.08)]"
            style={
                position
                    ? { top: position.top, left: position.left }
                    : { visibility: 'hidden', top: 0, left: 0 }
            }
        >
            <div className="flex flex-col gap-3">
                <div className="grid grid-cols-1 gap-2.5 min-[768px]:grid-cols-2">
                    <Label className="flex flex-col items-stretch gap-1 text-[13px] leading-normal font-semibold">
                        Filter by
                        <Select
                            value={value.filterBy}
                            onValueChange={(next) =>
                                update({
                                    filterBy: next,
                                    filterValue: 'all',
                                })
                            }
                        >
                            <SelectTrigger className="h-10 w-full rounded-[8px] px-2.5 text-[14px] font-normal">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent className="z-[80]">
                                {config.filterDimensions.map((dimension) => (
                                    <SelectItem
                                        key={dimension.key}
                                        value={dimension.key}
                                    >
                                        {dimension.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Label>

                    <Label className="flex flex-col items-stretch gap-1 text-[13px] leading-normal font-semibold">
                        Is
                        <Select
                            value={value.filterValue}
                            onValueChange={(next) =>
                                update({ filterValue: next })
                            }
                        >
                            <SelectTrigger className="h-10 w-full rounded-[8px] px-2.5 text-[14px] font-normal">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent className="z-[80]">
                                <SelectItem value="all">All</SelectItem>
                                {activeDimension?.options.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Label>

                    <Label className="flex flex-col items-stretch gap-1 text-[13px] leading-normal font-semibold">
                        Sort by
                        <Select
                            value={value.sortBy}
                            onValueChange={(next) => update({ sortBy: next })}
                        >
                            <SelectTrigger className="h-10 w-full rounded-[8px] px-2.5 text-[14px] font-normal">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent className="z-[80]">
                                {config.sortFields.map((field) => (
                                    <SelectItem
                                        key={field.key}
                                        value={field.key}
                                    >
                                        {field.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Label>

                    <Label className="flex flex-col items-stretch gap-1 text-[13px] leading-normal font-semibold">
                        Order
                        <Select
                            value={value.order}
                            onValueChange={(next) =>
                                update({
                                    order: next as 'asc' | 'desc',
                                })
                            }
                        >
                            <SelectTrigger className="h-10 w-full rounded-[8px] px-2.5 text-[14px] font-normal">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent className="z-[80]">
                                {orderOptions(
                                    config.sortFields,
                                    value.sortBy,
                                ).map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Label>
                </div>

                <div className="flex items-center justify-between gap-3">
                    <p className="text-[12.5px] text-muted-foreground">
                        {summaryParts.length > 0
                            ? summaryParts.join(' · ')
                            : 'No filter applied.'}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={!narrowed}
                        onClick={() =>
                            onChange({
                                ...value,
                                filterValue: 'all',
                                search: '',
                            })
                        }
                    >
                        Clear
                    </Button>
                </div>
            </div>
        </div>,
        document.body,
    );
}
