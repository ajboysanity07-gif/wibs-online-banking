import { useEffect, useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import type {
    MemberRegistrationFilter,
    MemberSort,
    MembersResponse,
} from '@/types/admin';

type MembersParams = {
    search: string;
    registration: MemberRegistrationFilter;
    sort: MemberSort;
    page: number;
    perPage: number;
    refreshKey?: number;
};

type MembersState = {
    data: MembersResponse;
    loading: boolean;
    error: string | null;
};

type MembersOptions = {
    enabled?: boolean;
    debounceMs?: number;
};

const emptyResponse: MembersResponse = {
    items: [],
    meta: {
        registration: null,
        sort: 'newest',
        page: 1,
        perPage: 10,
        total: 0,
        lastPage: 1,
    },
};

export function useMembers(
    params: MembersParams,
    initial?: MembersResponse,
    options?: MembersOptions,
) {
    const [state, setState] = useState<MembersState>({
        data: initial ?? emptyResponse,
        loading: false,
        error: null,
    });

    useEffect(() => {
        if (options?.enabled === false) {
            return;
        }

        const controller = new AbortController();
        const delay = options?.debounceMs ?? 350;
        const timeout = setTimeout(async () => {
            setState((current) => ({ ...current, loading: true, error: null }));
            const trimmedSearch = params.search.trim();

            try {
                const data = await adminApi.getMembers(
                    {
                        search:
                            trimmedSearch !== '' ? trimmedSearch : undefined,
                        registration:
                            params.registration === 'all'
                                ? undefined
                                : params.registration,
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
                        error: 'Unable to load members right now.',
                    }));
                }
            }
        }, delay);

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
        params.registration,
        options?.debounceMs,
        options?.enabled,
    ]);

    return {
        items: state.data.items,
        meta: state.data.meta,
        loading: state.loading,
        error: state.error,
    };
}
