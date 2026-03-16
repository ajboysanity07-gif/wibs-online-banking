import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { getApiErrorMessage } from '@/lib/api';
import { adminApi } from '@/lib/api/admin';
import type {
    MemberSavingsLedgerResponse,
    PaginationMeta,
} from '@/types/admin';

type MemberSavingsState = {
    data: MemberSavingsLedgerResponse;
    loading: boolean;
    error: string | null;
};

type MemberSavingsOptions = {
    enabled?: boolean;
    initial?: MemberSavingsLedgerResponse;
};

const buildEmptyResponse = (perPage: number): MemberSavingsLedgerResponse => ({
    items: [],
    meta: {
        page: 1,
        perPage,
        total: 0,
        lastPage: 1,
    },
});

export function useMemberSavings(
    memberId: number | null,
    page: number,
    perPage = 10,
    options?: MemberSavingsOptions,
) {
    const initialKey = `${memberId ?? 'unknown'}`;
    const emptyResponse = useMemo(
        () => buildEmptyResponse(perPage),
        [perPage],
    );
    const initialData = options?.initial ?? emptyResponse;

    const [state, setState] = useState<MemberSavingsState>({
        data: initialData,
        loading: false,
        error: null,
    });
    const didSkipInitialFetch = useRef({
        key: initialKey,
        skipped: false,
    });

    if (didSkipInitialFetch.current.key !== initialKey) {
        didSkipInitialFetch.current = { key: initialKey, skipped: false };
    }

    const refresh = useCallback(
        async (signal?: AbortSignal) => {
            if (!memberId) {
                return null;
            }

            setState((current) => ({ ...current, loading: true, error: null }));

            try {
                const data = await adminApi.getMemberSavings(
                    memberId,
                    { page, perPage },
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
                            'Unable to load savings right now.',
                        ),
                    }));
                }
                return null;
            }
        },
        [memberId, page, perPage],
    );

    useEffect(() => {
        if (options?.enabled === false || !memberId) {
            return;
        }

        if (
            options?.initial &&
            !didSkipInitialFetch.current.skipped &&
            page === options.initial.meta.page &&
            perPage === options.initial.meta.perPage
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
        initialKey,
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
        loading: state.loading,
        error: state.error,
        refresh,
    };
}
