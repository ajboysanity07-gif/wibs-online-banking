import axios from 'axios';
import { useEffect, useRef, useState } from 'react';
import api, { getApiErrorMessage } from '@/lib/api';

export type LocationSuggestion = {
    code: string;
    name: string;
    type: 'barangay' | 'city' | 'municipality' | 'province';
    province: string | null;
    region: string | null;
    label: string;
    value: string;
};

export type LocationSearchState = {
    query: string;
    setQuery: (value: string) => void;
    setSelectedValue: (value: string) => void;
    selectedValue: string;
    suggestions: LocationSuggestion[];
    allSuggestions: LocationSuggestion[];
    open: boolean;
    status: 'idle' | 'loading' | 'error';
    error: string | null;
    handleFocus: () => void;
    handleBlur: () => void;
    handleSelect: (suggestion: LocationSuggestion) => void;
    openResults: () => void;
    minLength: number;
    reload: () => void;
};

export const LOCATION_QUERY_MIN = 2;
export const LOCATION_DEBOUNCE_MS = 300;
export const LOCATION_RESULT_LIMIT = 15;

type LocationSearchOptions = {
    initialQuery: string;
    searchUrl: string;
    params?: Record<string, string | number | null | undefined>;
    minLength?: number;
    limit?: number;
    debounceMs?: number;
    clientFilter?: boolean;
};

const matchesAllTokens = (name: string, query: string): boolean => {
    const tokens = query
        .toLowerCase()
        .split(/\s+/)
        .filter((token) => token !== '');
    const lowerName = name.toLowerCase();

    return tokens.every((token) => lowerName.includes(token));
};

const stringifyParams = (
    params?: Record<string, string | number | null | undefined>,
): string => JSON.stringify(params ?? null);

export const useLocationSearch = ({
    initialQuery,
    searchUrl,
    params,
    minLength = LOCATION_QUERY_MIN,
    limit = LOCATION_RESULT_LIMIT,
    debounceMs = LOCATION_DEBOUNCE_MS,
    clientFilter = false,
}: LocationSearchOptions): LocationSearchState => {
    const [query, setQueryState] = useState<string>(initialQuery);
    const [selectedValue, setSelectedValueState] =
        useState<string>(initialQuery);
    const [suggestions, setSuggestions] = useState<LocationSuggestion[]>([]);
    const [allSuggestions, setAllSuggestions] = useState<LocationSuggestion[]>(
        [],
    );
    const [open, setOpen] = useState<boolean>(false);
    const [status, setStatus] = useState<'idle' | 'loading' | 'error'>('idle');
    const [error, setError] = useState<string | null>(null);
    const blurTimeoutRef = useRef<number | null>(null);
    // Only a selection from the dropdown (or the initial server-provided
    // value) counts as "committed" -- free-typed text that was never
    // selected is reverted on blur so submitted data always comes from the
    // API, keeping address2/address3/zip uniform.
    const committedValueRef = useRef<string>(initialQuery);
    const allSuggestionsRef = useRef<LocationSuggestion[]>([]);
    // In client-filter mode the full option set is fetched once per unique
    // params key (with an empty query) and then filtered locally, so the list
    // shows all options on open instead of requiring typed characters.
    const allLoadedParamsKeyRef = useRef<string | null>(null);

    const resetSearchState = () => {
        setSuggestions([]);
        setStatus('idle');
        setError(null);
    };

    const applyClientFilter = (value: string) => {
        const pool = allSuggestionsRef.current;
        setSuggestions(
            pool.filter((suggestion) =>
                matchesAllTokens(suggestion.name, value),
            ),
        );
        setStatus('idle');
    };

    const setQuery = (value: string) => {
        setQueryState(value);

        if (clientFilter) {
            if (allSuggestionsRef.current.length > 0) {
                applyClientFilter(value);
            }

            return;
        }

        const trimmedValue = value.trim();

        if (trimmedValue.length < minLength) {
            resetSearchState();
            return;
        }

        setSuggestions([]);
        setStatus('loading');
        setError(null);
    };

    const consumerParamsKey = stringifyParams(params);

    useEffect(() => {
        if (!open) {
            return;
        }

        if (clientFilter) {
            const key = stringifyParams(params);

            if (allLoadedParamsKeyRef.current === key) {
                setSuggestions(
                    allSuggestionsRef.current.filter((suggestion) =>
                        matchesAllTokens(suggestion.name, query),
                    ),
                );
                setStatus('idle');

                return;
            }
        } else if (query.trim().length < minLength) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(
            async () => {
                try {
                    const requestQuery = clientFilter ? '' : query.trim();
                    const requestParams: Record<string, string | number> = {
                        search: requestQuery,
                        limit,
                    };

                    if (params) {
                        Object.entries(params).forEach(([key, value]) => {
                            if (value === null || value === undefined) {
                                return;
                            }

                            requestParams[key] = value;
                        });
                    }

                    const response = await api.get(searchUrl, {
                        params: requestParams,
                        signal: controller.signal,
                    });
                    const payload = response.data as {
                        available?: boolean;
                        data?: LocationSuggestion[];
                        message?: string;
                    };

                    if (payload?.available === false) {
                        setStatus('error');
                        setError(
                            payload.message ??
                                'Location suggestions are temporarily unavailable.',
                        );
                        setSuggestions([]);
                        return;
                    }

                    const data = Array.isArray(payload?.data)
                        ? payload.data
                        : [];

                    if (clientFilter) {
                        allSuggestionsRef.current = data;
                        setAllSuggestions(data);
                        allLoadedParamsKeyRef.current = stringifyParams(params);
                        setSuggestions(
                            data.filter((suggestion) =>
                                matchesAllTokens(suggestion.name, query),
                            ),
                        );
                        setStatus('idle');
                    } else {
                        setSuggestions(data);
                        setStatus('idle');
                    }
                } catch (fetchError) {
                    if (axios.isCancel(fetchError)) {
                        return;
                    }

                    setStatus('error');
                    setError(
                        getApiErrorMessage(
                            fetchError,
                            'Unable to load location suggestions.',
                        ),
                    );
                    setSuggestions([]);
                }
            },
            clientFilter ? 0 : debounceMs,
        );

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps -- params is intentionally tracked via the stable consumerParamsKey string, not by object identity
    }, [
        open,
        query,
        minLength,
        limit,
        debounceMs,
        searchUrl,
        consumerParamsKey,
        clientFilter,
    ]);

    const handleFocus = () => {
        if (blurTimeoutRef.current !== null) {
            window.clearTimeout(blurTimeoutRef.current);
        }

        setOpen(true);

        if (clientFilter) {
            if (allLoadedParamsKeyRef.current === stringifyParams(params)) {
                applyClientFilter(query);
            } else {
                setSuggestions([]);
                setStatus('loading');
                setError(null);
            }

            return;
        }

        const trimmedQuery = query.trim();

        if (trimmedQuery.length < minLength) {
            resetSearchState();
            return;
        }

        setSuggestions([]);
        setStatus('loading');
        setError(null);
    };

    const handleBlur = () => {
        blurTimeoutRef.current = window.setTimeout(() => {
            setOpen(false);

            if (query !== committedValueRef.current) {
                setQueryState(committedValueRef.current);
                setSelectedValueState(committedValueRef.current);
                resetSearchState();
            }
        }, 120);
    };

    const setSelectedValue = (value: string) => {
        committedValueRef.current = value;
        setQueryState(value);
        setSelectedValueState(value);
        setOpen(false);
        resetSearchState();
    };

    const handleSelect = (suggestion: LocationSuggestion) => {
        setSelectedValue(suggestion.value);
    };

    const reload = () => {
        allSuggestionsRef.current = [];
        setAllSuggestions([]);
        allLoadedParamsKeyRef.current = null;
    };

    return {
        query,
        setQuery,
        setSelectedValue,
        selectedValue,
        suggestions,
        allSuggestions,
        open,
        status,
        error,
        handleFocus,
        handleBlur,
        handleSelect,
        openResults: () => setOpen(true),
        minLength,
        reload,
    };
};
