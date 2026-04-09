import axios from 'axios';
import { useEffect, useRef, useState } from 'react';
import api, { getApiErrorMessage } from '@/lib/api';

export type LocationSuggestion = {
    code: string;
    name: string;
    type: 'city' | 'municipality' | 'province';
    province: string | null;
    region: string | null;
    label: string;
    value: string;
};

export type LocationSearchState = {
    query: string;
    setQuery: (value: string) => void;
    setSelectedValue: (value: string) => void;
    suggestions: LocationSuggestion[];
    open: boolean;
    status: 'idle' | 'loading' | 'error';
    error: string | null;
    handleFocus: () => void;
    handleBlur: () => void;
    handleSelect: (suggestion: LocationSuggestion) => void;
    openResults: () => void;
    minLength: number;
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
};

export const useLocationSearch = ({
    initialQuery,
    searchUrl,
    params,
    minLength = LOCATION_QUERY_MIN,
    limit = LOCATION_RESULT_LIMIT,
    debounceMs = LOCATION_DEBOUNCE_MS,
}: LocationSearchOptions): LocationSearchState => {
    const [query, setQueryState] = useState<string>(initialQuery);
    const [suggestions, setSuggestions] = useState<LocationSuggestion[]>([]);
    const [open, setOpen] = useState<boolean>(false);
    const [status, setStatus] = useState<'idle' | 'loading' | 'error'>('idle');
    const [error, setError] = useState<string | null>(null);
    const blurTimeoutRef = useRef<number | null>(null);

    const resetSearchState = () => {
        setSuggestions([]);
        setStatus('idle');
        setError(null);
    };

    const setQuery = (value: string) => {
        setQueryState(value);

        const trimmedValue = value.trim();

        if (trimmedValue.length < minLength) {
            resetSearchState();
            return;
        }

        setSuggestions([]);
        setStatus('loading');
        setError(null);
    };

    useEffect(() => {
        if (!open) {
            return;
        }

        const trimmedQuery = query.trim();

        if (trimmedQuery.length < minLength) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            try {
                const requestParams: Record<string, string | number> = {
                    search: trimmedQuery,
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

                setSuggestions(
                    Array.isArray(payload?.data) ? payload.data : [],
                );
                setStatus('idle');
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
        }, debounceMs);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [open, query, minLength, limit, debounceMs, searchUrl, params]);

    const handleFocus = () => {
        if (blurTimeoutRef.current !== null) {
            window.clearTimeout(blurTimeoutRef.current);
        }

        setOpen(true);

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
        }, 120);
    };

    const setSelectedValue = (value: string) => {
        setQueryState(value);
        setOpen(false);
        resetSearchState();
    };

    const handleSelect = (suggestion: LocationSuggestion) => {
        setSelectedValue(suggestion.value);
    };

    return {
        query,
        setQuery,
        setSelectedValue,
        suggestions,
        open,
        status,
        error,
        handleFocus,
        handleBlur,
        handleSelect,
        openResults: () => setOpen(true),
        minLength,
    };
};
