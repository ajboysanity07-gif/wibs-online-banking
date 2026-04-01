import { useCallback, useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import type { DashboardSummary } from '@/types/admin';

type DashboardState = {
    summary: DashboardSummary;
    loading: boolean;
    error: string | null;
};

const emptySummary: DashboardSummary = {
    metrics: {
        registeredCount: 0,
        unregisteredCount: 0,
        totalCount: 0,
        requestsCount: null,
        lastSync: null,
    },
    requests: [],
};

export function useAdminDashboard(initial?: DashboardSummary) {
    const [state, setState] = useState<DashboardState>({
        summary: initial ?? emptySummary,
        loading: false,
        error: null,
    });

    const refresh = useCallback(async () => {
        setState((current) => ({ ...current, loading: true, error: null }));

        try {
            const summary = await adminApi.getDashboardSummary();
            setState({ summary, loading: false, error: null });
            return summary;
        } catch {
            setState((current) => ({
                ...current,
                loading: false,
                error: 'Unable to load the latest dashboard data.',
            }));
            return null;
        }
    }, []);

    const setSummary = useCallback((summary: DashboardSummary) => {
        setState({ summary, loading: false, error: null });
    }, []);

    return {
        summary: state.summary,
        loading: state.loading,
        error: state.error,
        refresh,
        setSummary,
    };
}
