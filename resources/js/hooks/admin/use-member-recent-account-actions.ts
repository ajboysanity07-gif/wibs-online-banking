import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { getApiErrorMessage } from '@/lib/api';
import { adminApi } from '@/lib/api/admin';
import type {
    MemberAccountActionsResponse,
    PaginationMeta,
} from '@/types/admin';

type MemberAccountActionsState = {
    data: MemberAccountActionsResponse;
    loading: boolean;
    error: string | null;
};

type MemberAccountActionsOptions = {
    enabled?: boolean;
    initial?: MemberAccountActionsResponse;
};

const buildEmptyResponse = (perPage: number): MemberAccountActionsResponse => ({
    items: [],
    meta: {
        page: 1,
        perPage,
        total: 0,
        lastPage: 1,
    },
});

export function useMemberRecentAccountActions(
    memberId: number | null,
    page: number,
    perPage = 5,
    options?: MemberAccountActionsOptions,
) {
    const initialKey = `${memberId ?? 'unknown'}`;
    const emptyResponse = useMemo(() => buildEmptyResponse(perPage), [perPage]);
    const initialData = options?.initial ?? emptyResponse;

    const [state, setState] = useState<MemberAccountActionsState>({
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
                const data = await adminApi.getMemberAccountActions(
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
                            'Unable to load account actions right now.',
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
