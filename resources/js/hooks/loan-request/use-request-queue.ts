import { useEffect, useState } from 'react';
import {
    requestQueueApi,
    type RequestQueueWorkspace,
} from '@/lib/api/request-queue';
import type { RequestsResponse } from '@/types/admin';

export type RequestQueueParams = {
    workspace: RequestQueueWorkspace;
    search: string;
    page: number;
    perPage: number;
    loanType?: string | null;
    status?: string | null;
    assignment?: 'unassigned' | 'mine' | 'all' | null;
    officerId?: number | null;
    reported?: boolean;
    minAmount?: number;
    maxAmount?: number;
    sortBy?: string | null;
    sortDirection?: 'asc' | 'desc' | null;
};

const emptyResponse: RequestsResponse = {
    items: [],
    meta: {
        page: 1,
        perPage: 10,
        total: 0,
        lastPage: 1,
        query: null,
        available: true,
        message: null,
        loanTypes: [],
        openCorrectionReports: 0,
        assignmentFilters: [],
        assignmentOfficers: [],
        sortBy: null,
        sortDirection: null,
    },
};

export function useRequestQueue(params: RequestQueueParams) {
    const [state, setState] = useState({
        data: emptyResponse,
        loading: false,
        error: null as string | null,
    });
    const [reloadToken, setReloadToken] = useState(0);

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
                const data = await requestQueueApi.getRequests(
                    params.workspace,
                    {
                        search:
                            trimmedSearch !== '' ? trimmedSearch : undefined,
                        loanType: params.loanType ?? undefined,
                        status: params.status ?? undefined,
                        assignment: params.assignment ?? undefined,
                        officerId: params.officerId ?? undefined,
                        reported: params.reported ?? undefined,
                        minAmount: params.minAmount ?? undefined,
                        maxAmount: params.maxAmount ?? undefined,
                        sortBy: params.sortBy ?? undefined,
                        sortDirection: params.sortDirection ?? undefined,
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
                        error: 'Unable to load requests right now.',
                    }));
                }
            }
        }, 350);

        return () => {
            controller.abort();
            clearTimeout(timeout);
        };
    }, [
        params.loanType,
        params.maxAmount,
        params.minAmount,
        params.officerId,
        params.page,
        params.perPage,
        params.reported,
        params.search,
        params.assignment,
        params.status,
        params.sortBy,
        params.sortDirection,
        params.workspace,
        reloadToken,
    ]);

    return {
        items: state.data.items,
        meta: state.data.meta,
        loading: state.loading,
        error: state.error,
        warning:
            state.data.meta.available === false
                ? state.data.meta.message
                : null,
        refetch: () => setReloadToken((token) => token + 1),
    };
}
