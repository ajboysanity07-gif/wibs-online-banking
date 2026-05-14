import { useEffect, useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import type { ReportedRequestsResponse } from '@/types/admin';

type ReportedRequestsParams = {
    search: string;
    page: number;
    perPage: number;
};

const emptyResponse: ReportedRequestsResponse = {
    items: [],
    meta: {
        page: 1,
        perPage: 10,
        total: 0,
        lastPage: 1,
        query: null,
        available: true,
        message: null,
        openCorrectionReports: 0,
    },
};

export function useReportedRequests(params: ReportedRequestsParams) {
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
                const data = await adminApi.getReportedRequests(
                    {
                        search:
                            trimmedSearch !== '' ? trimmedSearch : undefined,
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
                        error: 'Unable to load reported requests right now.',
                    }));
                }
            }
        }, 350);

        return () => {
            controller.abort();
            clearTimeout(timeout);
        };
    }, [params.page, params.perPage, params.search]);

    return {
        items: state.data.items,
        meta: state.data.meta,
        loading: state.loading,
        error: state.error,
    };
}
