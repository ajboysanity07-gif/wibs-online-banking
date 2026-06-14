import { useEffect, useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import type {
    EditableStaffRoleName,
    StaffDirectoryResponse,
} from '@/types/admin';

type StaffDirectoryParams = {
    search: string;
    role: EditableStaffRoleName | 'all';
    access: 'all' | 'active' | 'suspended';
    page: number;
    perPage: number;
    refreshKey?: number;
};

type StaffDirectoryState = {
    data: StaffDirectoryResponse;
    loading: boolean;
    error: string | null;
};

type StaffDirectoryOptions = {
    enabled?: boolean;
    debounceMs?: number;
};

const emptyResponse: StaffDirectoryResponse = {
    items: [],
    meta: {
        search: null,
        role: null,
        access: null,
        page: 1,
        perPage: 10,
        total: 0,
        lastPage: 1,
    },
};

export function useStaffDirectory(
    params: StaffDirectoryParams,
    initial?: StaffDirectoryResponse,
    options?: StaffDirectoryOptions,
) {
    const [state, setState] = useState<StaffDirectoryState>({
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
                const data = await adminApi.getStaff(
                    {
                        search:
                            trimmedSearch !== '' ? trimmedSearch : undefined,
                        role: params.role === 'all' ? null : params.role,
                        access:
                            params.access === 'all' ? null : params.access,
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
                        error: 'Unable to load staff accounts right now.',
                    }));
                }
            }
        }, delay);

        return () => {
            controller.abort();
            clearTimeout(timeout);
        };
    }, [
        params.access,
        params.page,
        params.perPage,
        params.refreshKey,
        params.role,
        params.search,
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
