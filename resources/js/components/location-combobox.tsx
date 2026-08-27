import { CheckIcon, ChevronsUpDownIcon } from 'lucide-react';
import type { ReactNode } from 'react';
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
    loadingMessage?: string;
    errorMessage?: string;
    emptyMessage?: string;
    promptMessage?: string;
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
    loadingMessage,
    errorMessage,
    emptyMessage,
    promptMessage,
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
            <PopoverTrigger asChild>
                <button
                    id={id}
                    type="button"
                    role="combobox"
                    aria-expanded={isInteractive && search.open}
                    aria-label={ariaLabel}
                    aria-required={required}
                    disabled={!isInteractive}
                    className={cn(
                        inputClassName,
                        'inline-flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                        'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                        !hasValue && 'text-muted-foreground',
                        readOnly &&
                            'pointer-events-none border-border/40 bg-muted/30 text-muted-foreground/80',
                    )}
                >
                    <span className="truncate">
                        {hasValue ? search.selectedValue : effectivePlaceholder}
                    </span>
                    <ChevronsUpDownIcon className="size-4 shrink-0 opacity-50" />
                </button>
            </PopoverTrigger>
            <PopoverContent
                portal={portal}
                align="start"
                className="w-[--radix-popover-trigger-width] p-0"
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
