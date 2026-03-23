import type { ChangeEvent } from 'react';
import { Input } from '@/components/ui/input';
import type {
    LocationSearchState,
    LocationSuggestion,
} from '@/hooks/use-location-search';

type Props = {
    id: string;
    search: LocationSearchState;
    name?: string;
    placeholder?: string;
    required?: boolean;
    autoComplete?: string;
    ariaLabel?: string;
    inputClassName?: string;
    readOnly?: boolean;
    disabled?: boolean;
    onValueChange?: (value: string) => void;
    onSelect?: (suggestion: LocationSuggestion) => void;
    loadingMessage?: string;
    errorMessage?: string;
    emptyMessage?: string;
    promptMessage?: string;
};

const DEFAULT_LOADING_MESSAGE = 'Searching location suggestions...';
const DEFAULT_ERROR_MESSAGE =
    'Location suggestions are temporarily unavailable.';
const DEFAULT_EMPTY_MESSAGE = 'No matching places found.';

const buildPromptMessage = (minLength: number): string =>
    `Type at least ${minLength} characters to search cities and municipalities.`;

export function LocationAutocompleteInput({
    id,
    search,
    name,
    placeholder,
    required = false,
    autoComplete = 'off',
    ariaLabel,
    inputClassName,
    readOnly = false,
    disabled = false,
    onValueChange,
    onSelect,
    loadingMessage,
    errorMessage,
    emptyMessage,
    promptMessage,
}: Props) {
    const minLength = search.minLength;
    const isInteractive = !readOnly && !disabled;

    const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
        if (!isInteractive) {
            return;
        }

        const nextValue = event.target.value;

        search.setQuery(nextValue);
        search.openResults();
        onValueChange?.(nextValue);
    };

    const handleSelect = (suggestion: LocationSuggestion) => {
        if (!isInteractive) {
            return;
        }

        search.handleSelect(suggestion);
        onValueChange?.(suggestion.value);
        onSelect?.(suggestion);
    };

    return (
        <div className="relative">
            <Input
                id={id}
                name={name}
                className={inputClassName}
                value={search.query}
                required={required}
                placeholder={placeholder}
                aria-label={ariaLabel}
                autoComplete={autoComplete}
                readOnly={readOnly}
                disabled={disabled}
                onChange={handleChange}
                onFocus={isInteractive ? search.handleFocus : undefined}
                onBlur={isInteractive ? search.handleBlur : undefined}
            />

            {isInteractive && search.open && (
                <div className="absolute z-20 mt-2 w-full rounded-md border border-border/70 bg-background/95 p-2 text-sm shadow-lg backdrop-blur">
                    {search.status === 'loading' && (
                        <p className="px-2 py-1 text-muted-foreground">
                            {loadingMessage ?? DEFAULT_LOADING_MESSAGE}
                        </p>
                    )}

                    {search.status === 'error' && (
                        <p className="px-2 py-1 text-amber-600">
                            {search.error ??
                                errorMessage ??
                                DEFAULT_ERROR_MESSAGE}
                        </p>
                    )}

                    {search.status === 'idle' &&
                        search.query.trim().length < minLength && (
                            <p className="px-2 py-1 text-muted-foreground">
                                {promptMessage ?? buildPromptMessage(minLength)}
                            </p>
                        )}

                    {search.status === 'idle' &&
                        search.query.trim().length >= minLength &&
                        search.suggestions.length === 0 && (
                            <p className="px-2 py-1 text-muted-foreground">
                                {emptyMessage ?? DEFAULT_EMPTY_MESSAGE}
                            </p>
                        )}

                    {search.suggestions.length > 0 && (
                        <div className="max-h-60 space-y-1 overflow-auto">
                            {search.suggestions.map((suggestion) => (
                                <button
                                    key={suggestion.code}
                                    type="button"
                                    className="flex w-full flex-col gap-1 rounded-md px-2 py-2 text-left transition hover:bg-muted/70 focus-visible:bg-muted/70 focus-visible:outline-hidden"
                                    onMouseDown={(event) => {
                                        event.preventDefault();
                                    }}
                                    onClick={() => handleSelect(suggestion)}
                                >
                                    <span className="text-sm font-medium">
                                        {suggestion.label}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {suggestion.type === 'city'
                                            ? 'City'
                                            : 'Municipality'}
                                    </span>
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
