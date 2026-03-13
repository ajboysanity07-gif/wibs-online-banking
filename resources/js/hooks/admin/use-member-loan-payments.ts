import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { getApiErrorMessage } from '@/lib/api';
import { adminApi } from '@/lib/api/admin';
import type {
    MemberLoanPaymentsFilters,
    MemberLoanPaymentsResponse,
    PaginationMeta,
} from '@/types/admin';

type MemberLoanPaymentsState = {
    data: MemberLoanPaymentsResponse;
    loading: boolean;
    error: string | null;
};

type MemberLoanPaymentsOptions = {
    enabled?: boolean;
    initial?: MemberLoanPaymentsResponse;
};

const buildEmptyResponse = (
    perPage: number,
    filters: MemberLoanPaymentsFilters,
): MemberLoanPaymentsResponse => ({
    items: [],
    meta: {
        page: 1,
        perPage,
        total: 0,
        lastPage: 1,
    },
    filters,
    openingBalance: null,
    closingBalance: null,
});

const buildFilterKey = (filters: MemberLoanPaymentsFilters) =>
    `${filters.range}-${filters.start ?? 'none'}-${filters.end ?? 'none'}`;

export function useMemberLoanPayments(
    memberId: number | null,
    loanNumber: string | number | null,
    page: number,
    perPage: number,
    filters: MemberLoanPaymentsFilters,
    options?: MemberLoanPaymentsOptions,
) {
    const initialKey = `${memberId ?? 'unknown'}-${loanNumber ?? 'unknown'}`;
    const emptyResponse = useMemo(
        () => buildEmptyResponse(perPage, filters),
        [filters, perPage],
    );
    const initialData = options?.initial ?? emptyResponse;
    const filterKey = buildFilterKey(filters);

    const [state, setState] = useState<MemberLoanPaymentsState>({
        data: initialData,
        loading: false,
        error: null,
    });
    const didSkipInitialFetch = useRef({
        key: `${initialKey}-${filterKey}`,
        skipped: false,
    });

    if (didSkipInitialFetch.current.key !== `${initialKey}-${filterKey}`) {
        didSkipInitialFetch.current = {
            key: `${initialKey}-${filterKey}`,
            skipped: false,
        };
    }

    const refresh = useCallback(
        async (signal?: AbortSignal) => {
            if (!memberId || loanNumber === null || loanNumber === undefined) {
                return null;
            }

            setState((current) => ({ ...current, loading: true, error: null }));

            try {
                const data = await adminApi.getMemberLoanPayments(
                    memberId,
                    loanNumber,
                    {
                        page,
                        perPage,
                        range: filters.range,
                        start: filters.start,
                        end: filters.end,
                    },
                    signal,
                );
                setState({ data, loading: false, error: null });
                return data;
            } catch (error) {
                if (!signal?.aborted) {
                    setState((current) => ({
                        ...current,
                        loading: false,
                        error: getApiErrorMessage(
                            error,
                            'Unable to load payments right now.',
                        ),
                    }));
                }
                return null;
            }
        },
        [filters.end, filters.range, filters.start, loanNumber, memberId, page, perPage],
    );

    useEffect(() => {
        if (
            options?.enabled === false ||
            !memberId ||
            loanNumber === null ||
            loanNumber === undefined
        ) {
            return;
        }

        if (
            options?.initial &&
            !didSkipInitialFetch.current.skipped &&
            page === options.initial.meta.page &&
            perPage === options.initial.meta.perPage &&
            buildFilterKey(options.initial.filters) === filterKey
        ) {
            didSkipInitialFetch.current.skipped = true;
            return;
        }

        const controller = new AbortController();
        void refresh(controller.signal);

        return () => {
            controller.abort();
        };
    }, [
        filterKey,
        initialKey,
        loanNumber,
        memberId,
        options?.enabled,
        options?.initial,
        page,
        perPage,
        refresh,
    ]);

    return {
        items: state.data.items,
        meta: state.data.meta as PaginationMeta,
        filters: state.data.filters,
        openingBalance: state.data.openingBalance ?? null,
        closingBalance: state.data.closingBalance ?? null,
        loading: state.loading,
        error: state.error,
        refresh,
    };
}
