import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { getApiErrorMessage } from '@/lib/api';
import { adminApi } from '@/lib/api/admin';
import type { MemberLoanScheduleResponse } from '@/types/admin';

type MemberLoanScheduleState = {
    data: MemberLoanScheduleResponse;
    loading: boolean;
    error: string | null;
};

type MemberLoanScheduleOptions = {
    enabled?: boolean;
    initial?: MemberLoanScheduleResponse;
};

const buildEmptyResponse = (): MemberLoanScheduleResponse => ({
    items: [],
});

export function useMemberLoanSchedule(
    memberId: number | null,
    loanNumber: string | number | null,
    options?: MemberLoanScheduleOptions,
) {
    const initialKey = `${memberId ?? 'unknown'}-${loanNumber ?? 'unknown'}`;
    const emptyResponse = useMemo(() => buildEmptyResponse(), []);
    const initialData = options?.initial ?? emptyResponse;

    const [state, setState] = useState<MemberLoanScheduleState>({
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
            if (!memberId || loanNumber === null || loanNumber === undefined) {
                return null;
            }

            setState((current) => ({ ...current, loading: true, error: null }));

            try {
                const data = await adminApi.getMemberLoanSchedule(
                    memberId,
                    loanNumber,
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
                            'Unable to load the schedule right now.',
                        ),
                    }));
                }
                return null;
            }
        },
        [loanNumber, memberId],
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

        if (options?.initial && !didSkipInitialFetch.current.skipped) {
            didSkipInitialFetch.current.skipped = true;
            return;
        }

        const controller = new AbortController();
        void refresh(controller.signal);

        return () => {
            controller.abort();
        };
    }, [initialKey, loanNumber, memberId, options?.enabled, options?.initial, refresh]);

    return {
        items: state.data.items,
        loading: state.loading,
        error: state.error,
        refresh,
    };
}
