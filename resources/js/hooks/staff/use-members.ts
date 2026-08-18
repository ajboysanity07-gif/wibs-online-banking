import { useEffect, useState } from 'react';
import { staffApi } from '@/lib/api/staff';
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
};

type MembersState = {
    data: MembersResponse;
    loading: boolean;
    error: string | null;
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

export function useStaffMembers(params: MembersParams) {
    const [state, setState] = useState<MembersState>({
        data: emptyResponse,
        loading: false,
        error: null,
    });

    useEffect(() => {
        const controller = new AbortController();
        const timeout = setTimeout(async () => {
            setState((current) => ({ ...current, loading: true, error: null }));
            const trimmedSearch = params.search.trim();

            try {
                const data = await staffApi.getMembers(
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
        }, 350);

        return () => {
            controller.abort();
            clearTimeout(timeout);
        };
    }, [
        params.page,
        params.perPage,
        params.search,
        params.sort,
        params.registration,
    ]);

    return {
        items: state.data.items,
        meta: state.data.meta,
        loading: state.loading,
        error: state.error,
    };
}
