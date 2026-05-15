import { useEffect, useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import type { RequestsResponse } from '@/types/admin';

type RequestsParams = {
    search: string;
    page: number;
    perPage: number;
    loanType?: string | null;
    status?: string | null;
    reported?: boolean;
    minAmount?: number;
    maxAmount?: number;
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
    },
};

export function useRequests(params: RequestsParams) {
    const [state, setState] = useState({
        data: emptyResponse,
        loading: false,
        error: null as string | null,
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
                const data = await adminApi.getRequests(
                    {
                        search:
                            trimmedSearch !== '' ? trimmedSearch : undefined,
                        loanType: params.loanType ?? undefined,
                        status: params.status ?? undefined,
                        reported: params.reported ?? undefined,
                        minAmount: params.minAmount ?? undefined,
                        maxAmount: params.maxAmount ?? undefined,
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
        params.page,
        params.perPage,
        params.reported,
        params.search,
        params.status,
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
    };
}
