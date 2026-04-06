import {
    createContext,
    useCallback,
    useContext,
    useMemo,
    useState,
} from 'react';

import { useMemberAccountsSummary } from '@/hooks/admin/use-member-accounts-summary';
import { useMemberRecentAccountActions } from '@/hooks/admin/use-member-recent-account-actions';
import type {
    MemberAccountActionsResponse,
    MemberAccountsSummary,
    PaginationMeta,
} from '@/types/admin';
import type { MemberRecentAccountAction } from '@/types/admin';

type MemberAccountsContextValue = {
    memberKey: string | number | null;
    acctno: string | null;
    summary: MemberAccountsSummary | null;
    summaryLoading: boolean;
    summaryError: string | null;
    actions: MemberRecentAccountAction[];
    actionsMeta: PaginationMeta;
    actionsLoading: boolean;
    actionsError: string | null;
    actionsPage: number;
    setActionsPage: (page: number) => void;
    loansDialogOpen: boolean;
    setLoansDialogOpen: (open: boolean) => void;
    savingsDialogOpen: boolean;
    setSavingsDialogOpen: (open: boolean) => void;
    refreshSummary: () => Promise<MemberAccountsSummary | null>;
    refreshActions: () => Promise<unknown>;
    refreshOverview: () => Promise<void>;
};

const MemberAccountsContext = createContext<MemberAccountsContextValue | null>(
    null,
);

type MemberAccountsProviderProps = {
    memberKey: string | number | null;
    acctno: string | null;
    initialSummary?: MemberAccountsSummary | null;
    initialActions?: MemberAccountActionsResponse | null;
    children: React.ReactNode;
};

export function MemberAccountsProvider({
    memberKey,
    acctno,
    initialSummary = null,
    initialActions = null,
    children,
}: MemberAccountsProviderProps) {
    const accountKey = `${memberKey ?? 'unknown'}-${acctno ?? 'unknown'}`;
    const [actionsPageState, setActionsPageState] = useState(() => ({
        memberKey: accountKey,
        page: 1,
    }));
    const [loansDialogOpen, setLoansDialogOpen] = useState(false);
    const [savingsDialogOpen, setSavingsDialogOpen] = useState(false);
    const enabled = Boolean(memberKey && acctno);
    const actionsPage =
        actionsPageState.memberKey === accountKey ? actionsPageState.page : 1;
    const setActionsPage = useCallback(
        (page: number) => {
            setActionsPageState({ memberKey: accountKey, page });
        },
        [accountKey],
    );

    const summaryState = useMemberAccountsSummary(memberKey, {
        enabled,
        initial: initialSummary,
    });
    const actionsState = useMemberRecentAccountActions(
        memberKey,
        actionsPage,
        5,
        {
            enabled,
            initial: initialActions ?? undefined,
        },
    );

    const refreshOverview = useCallback(async () => {
        await Promise.all([summaryState.refresh(), actionsState.refresh()]);
    }, [actionsState, summaryState]);

    const value = useMemo<MemberAccountsContextValue>(
        () => ({
            memberKey,
            acctno,
            summary: summaryState.summary,
            summaryLoading: summaryState.loading,
            summaryError: summaryState.error,
            actions: actionsState.items,
            actionsMeta: actionsState.meta,
            actionsLoading: actionsState.loading,
            actionsError: actionsState.error,
            actionsPage,
            setActionsPage,
            loansDialogOpen,
            setLoansDialogOpen,
            savingsDialogOpen,
            setSavingsDialogOpen,
            refreshSummary: summaryState.refresh,
            refreshActions: actionsState.refresh,
            refreshOverview,
        }),
        [
            acctno,
            actionsPage,
            actionsState,
            loansDialogOpen,
            memberKey,
            refreshOverview,
            savingsDialogOpen,
            setActionsPage,
            summaryState,
        ],
    );

    return (
        <MemberAccountsContext.Provider value={value}>
            {children}
        </MemberAccountsContext.Provider>
    );
}

export function useMemberAccounts() {
    const context = useContext(MemberAccountsContext);

    if (!context) {
        throw new Error(
            'useMemberAccounts must be used within a MemberAccountsProvider.',
        );
    }

    return context;
}
