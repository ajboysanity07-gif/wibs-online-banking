import { useEffect, useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import type { PendingApprovalsResponse } from '@/types/admin';

type PendingApprovalsParams = {
    search: string;
    sort: 'newest' | 'oldest';
    page: number;
    perPage: number;
    refreshKey?: number;
};

type PendingApprovalsState = {
    data: PendingApprovalsResponse;
    loading: boolean;
    error: string | null;
};

const emptyResponse: PendingApprovalsResponse = {
    rows: [],
    meta: { page: 1, perPage: 10, total: 0, lastPage: 1 },
};

export function usePendingApprovals(
    params: PendingApprovalsParams,
    initial?: PendingApprovalsResponse,
) {
    const [state, setState] = useState<PendingApprovalsState>({
        data: initial ?? emptyResponse,
        loading: false,
        error: null,
    });

    useEffect(() => {
        const controller = new AbortController();
        const timeout = setTimeout(async () => {
            setState((current) => ({
                ...current,
                loading: true,
                error: null,
            }));
            const trimmedSearch = params.search.trim();

            try {
                const data = await adminApi.getPendingApprovals(
                    {
                        search:
                            trimmedSearch !== '' ? trimmedSearch : undefined,
                        sort: params.sort,
                        page: params.page,
                        perPage: params.perPage,
                    },
                    controller.signal,
                );

                setState({ data, loading: false, error: null });
            } catch {
                if (!controller.signal.aborted) {
                    setState((current) => ({
                        ...current,
                        loading: false,
                        error: 'Unable to load pending approvals right now.',
                    }));
                }
            }
        }, 350);

        return () => {
            controller.abort();
            clearTimeout(timeout);
        };
    }, [
        params.page,
        params.perPage,
        params.refreshKey,
        params.search,
        params.sort,
    ]);

    return {
        rows: state.data.rows,
        meta: state.data.meta,
        loading: state.loading,
        error: state.error,
    };
}
