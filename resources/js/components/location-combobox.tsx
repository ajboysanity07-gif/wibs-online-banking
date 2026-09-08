import { CheckIcon, ChevronsUpDownIcon, XIcon } from 'lucide-react';
import type { MouseEvent, ReactNode } from 'react';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import type {
    LocationSearchState,
    LocationSuggestion,
} from '@/hooks/use-location-search';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    search: LocationSearchState;
    name?: string;
    placeholder?: string;
    required?: boolean;
    ariaLabel?: string;
    readOnly?: boolean;
    disabled?: boolean;
    portal?: boolean;
    inputClassName?: string;
    onValueChange?: (value: string) => void;
    onSelect?: (suggestion: LocationSuggestion) => void;
    onClear?: () => void;
    loadingMessage?: string;
    errorMessage?: string;
    emptyMessage?: string;
    promptMessage?: string;
    'aria-invalid'?: boolean;
};

const DEFAULT_LOADING_MESSAGE = 'Loading suggestions...';
const DEFAULT_ERROR_MESSAGE =
    'Location suggestions are temporarily unavailable.';
const DEFAULT_EMPTY_MESSAGE = 'No matching places found.';

const TYPE_LABELS: Record<LocationSuggestion['type'], string> = {
    province: 'Province',
    city: 'City',
    municipality: 'Municipality',
    barangay: 'Barangay',
};

export function LocationCombobox({
    id,
    search,
    name,
    placeholder = 'Select location',
    required = false,
    ariaLabel,
    readOnly = false,
    disabled = false,
    portal = true,
    inputClassName,
    onValueChange,
    onSelect,
    onClear,
    loadingMessage,
    errorMessage,
    emptyMessage,
    promptMessage,
    'aria-invalid': ariaInvalid,
}: Props) {
    const isInteractive = !readOnly && !disabled;
    const hasValue = search.selectedValue.trim() !== '';
    const effectivePlaceholder =
        disabled && promptMessage
            ? promptMessage
            : (placeholder ?? 'Select location');

    const handleSelect = (suggestion: LocationSuggestion) => {
        search.handleSelect(suggestion);
        onValueChange?.(suggestion.value);
        onSelect?.(suggestion);
    };

    const handleClear = (event: MouseEvent) => {
        event.preventDefault();
        event.stopPropagation();
        search.setSelectedValue('');
        onValueChange?.('');
        onClear?.();
    };

    const renderOptions = (): ReactNode => {
        if (search.status === 'loading') {
            return (
                <div className="py-6 text-center text-sm text-muted-foreground">
                    {loadingMessage ?? DEFAULT_LOADING_MESSAGE}
                </div>
            );
        }

        if (search.status === 'error') {
            return (
                <div className="py-6 text-center text-sm text-amber-600">
                    {search.error ?? errorMessage ?? DEFAULT_ERROR_MESSAGE}
                </div>
            );
        }

        if (search.suggestions.length === 0) {
            return (
                <CommandEmpty>
                    {emptyMessage ?? DEFAULT_EMPTY_MESSAGE}
                </CommandEmpty>
            );
        }

        return (
            <CommandGroup>
                {search.suggestions.map((suggestion) => (
                    <CommandItem
                        key={suggestion.code}
                        value={suggestion.value}
                        onSelect={() => handleSelect(suggestion)}
                    >
                        <CheckIcon
                            className={cn(
                                'mr-2 size-4',
                                suggestion.value === search.selectedValue
                                    ? 'opacity-100'
                                    : 'opacity-0',
                            )}
                        />
                        <span className="flex-1">{suggestion.label}</span>
                        {suggestion.type === 'barangay' ? null : (
                            <span className="text-xs text-muted-foreground">
                                {TYPE_LABELS[suggestion.type]}
                            </span>
                        )}
                    </CommandItem>
                ))}
            </CommandGroup>
        );
    };

    return (
        <Popover
            open={isInteractive && search.open}
            onOpenChange={(nextOpen) => {
                if (nextOpen) {
                    search.openResults();
                } else {
                    search.handleBlur();
                }
            }}
        >
            <div className="relative">
                <PopoverTrigger asChild>
                    <button
                        id={id}
                        type="button"
                        role="combobox"
                        aria-expanded={isInteractive && search.open}
                        aria-label={ariaLabel}
                        aria-required={required}
                        aria-invalid={ariaInvalid}
                        disabled={!isInteractive}
                        className={cn(
                            inputClassName,
                            'inline-flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                            'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                            'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                            !hasValue && 'text-muted-foreground',
                            hasValue && isInteractive && 'pr-14',
                            readOnly &&
                                'pointer-events-none border-border/40 bg-muted/30 text-muted-foreground/80',
                        )}
                    >
                        <span className="truncate">
                            {hasValue
                                ? search.selectedValue
                                : effectivePlaceholder}
                        </span>
                        {hasValue && isInteractive ? (
                            <span className="size-4 shrink-0" />
                        ) : (
                            <ChevronsUpDownIcon className="size-4 shrink-0 opacity-50" />
                        )}
                    </button>
                </PopoverTrigger>
                {hasValue && isInteractive ? (
                    <span className="pointer-events-none absolute top-1/2 right-3 flex -translate-y-1/2 items-center gap-1">
                        <button
                            type="button"
                            aria-label="Clear selection"
                            tabIndex={-1}
                            onClick={handleClear}
                            className="pointer-events-auto flex size-5 items-center justify-center rounded-sm text-muted-foreground hover:bg-accent hover:text-foreground"
                        >
                            <XIcon className="size-3.5" />
                        </button>
                        <ChevronsUpDownIcon className="size-4 shrink-0 opacity-50" />
                    </span>
                ) : null}
            </div>
            <PopoverContent
                portal={portal}
                align="start"
                className="w-[--radix-popover-trigger-width] p-0"
                onInteractOutside={(e) => {
                    if (!portal) e.preventDefault();
                }}
            >
                <Command>
                    <CommandInput
                        value={search.query}
                        onValueChange={(value) => {
                            search.setQuery(value);
                            search.openResults();
                        }}
                        placeholder={
                            hasValue
                                ? search.selectedValue
                                : effectivePlaceholder
                        }
                    />
                    <CommandList>{renderOptions()}</CommandList>
                </Command>
            </PopoverContent>
            {name ? (
                <input type="hidden" name={name} value={search.selectedValue} />
            ) : null}
        </Popover>
    );
}
