import { useCallback, useEffect, useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import type { MemberDetail } from '@/types/admin';

type MemberDetailsState = {
    member: MemberDetail | null;
    loading: boolean;
    error: string | null;
};

export function useMemberDetails(userId: number | null, initial?: MemberDetail) {
    const [state, setState] = useState<MemberDetailsState>({
        member: initial ?? null,
        loading: false,
        error: null,
    });

    const refresh = useCallback(async (signal?: AbortSignal) => {
        if (!userId) {
            return null;
        }

        setState((current) => ({ ...current, loading: true, error: null }));

        try {
            const member = await adminApi.getMemberDetail(userId, signal);
            setState({ member, loading: false, error: null });
            return member;
        } catch {
            setState((current) => ({
                ...current,
                loading: false,
                error: 'Unable to load this member right now.',
            }));
            return null;
        }
    }, [userId]);

    useEffect(() => {
        const controller = new AbortController();
        // eslint-disable-next-line react-hooks/set-state-in-effect -- initial fetch intentionally updates state.
        void refresh(controller.signal);

        return () => {
            controller.abort();
        };
    }, [refresh]);

    const setMember = useCallback((member: MemberDetail) => {
        setState({ member, loading: false, error: null });
    }, []);

    return {
        member: state.member,
        loading: state.loading,
        error: state.error,
        refresh,
        setMember,
    };
}
