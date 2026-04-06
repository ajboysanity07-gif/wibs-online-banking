import { useCallback, useEffect, useRef, useState } from 'react';
import { getApiErrorMessage } from '@/lib/api';
import { adminApi } from '@/lib/api/admin';
import type { MemberAccountsSummary } from '@/types/admin';

type MemberAccountsSummaryState = {
    summary: MemberAccountsSummary | null;
    loading: boolean;
    error: string | null;
};

type MemberAccountsSummaryOptions = {
    enabled?: boolean;
    initial?: MemberAccountsSummary | null;
};

export function useMemberAccountsSummary(
    memberKey: string | number | null,
    options?: MemberAccountsSummaryOptions,
) {
    const initialSummary = options?.initial ?? null;
    const initialKey = `${memberKey ?? 'unknown'}`;
    const [state, setState] = useState<MemberAccountsSummaryState>({
        summary: initialSummary,
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
            if (!memberKey) {
                return null;
            }

            setState((current) => ({ ...current, loading: true, error: null }));

            try {
                const summary = await adminApi.getMemberAccountsSummary(
                    memberKey,
                    signal,
                );
                setState({ summary, loading: false, error: null });
                return summary;
            } catch (error) {
                if (!signal?.aborted) {
                    setState((current) => ({
                        ...current,
                        loading: false,
                        error: getApiErrorMessage(
                            error,
                            'Unable to load account summary right now.',
                        ),
                    }));
                }
                return null;
            }
        },
        [memberKey],
    );

    useEffect(() => {
        if (options?.enabled === false || !memberKey) {
            return;
        }

        if (initialSummary && !didSkipInitialFetch.current.skipped) {
            didSkipInitialFetch.current.skipped = true;
            return;
        }

        const controller = new AbortController();
        void refresh(controller.signal);

        return () => {
            controller.abort();
        };
    }, [initialKey, initialSummary, memberKey, options?.enabled, refresh]);

    return {
        summary: state.summary,
        loading: state.loading,
        error: state.error,
        refresh,
    };
}
