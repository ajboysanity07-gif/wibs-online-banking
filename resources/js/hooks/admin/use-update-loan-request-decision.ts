import { useCallback, useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import { adminToastCopy, showErrorToast, showSuccessToast } from '@/lib/toast';
import type { LoanRequestDetail } from '@/types/loan-requests';

export type LoanRequestDecisionAction = 'approve' | 'decline';

export type LoanRequestApprovePayload = {
    approved_amount: number | string;
    approved_term: number | string;
    decision_notes?: string | null;
};

export type LoanRequestDeclinePayload = {
    decision_notes?: string | null;
};

type LoanRequestDecisionOptions = {
    onUpdated?: (
        loanRequest: LoanRequestDetail,
        action: LoanRequestDecisionAction,
    ) => void;
};

const successCopy: Record<LoanRequestDecisionAction, string> = {
    approve: adminToastCopy.success.approved('loan request'),
    decline: adminToastCopy.success.declined('loan request'),
};

const errorCopy: Record<LoanRequestDecisionAction, string> = {
    approve: adminToastCopy.error.approved('loan request'),
    decline: adminToastCopy.error.declined('loan request'),
};

export function useUpdateLoanRequestDecision(
    options?: LoanRequestDecisionOptions,
) {
    const [processingIds, setProcessingIds] = useState<Record<number, boolean>>(
        {},
    );

    const updateDecision = useCallback(
        async (
            loanRequestId: number,
            action: LoanRequestDecisionAction,
            payload: LoanRequestApprovePayload | LoanRequestDeclinePayload,
        ) => {
            setProcessingIds((current) => ({
                ...current,
                [loanRequestId]: true,
            }));
            const toastId = `loan-request-decision-${action}-${loanRequestId}`;

            try {
                const loanRequest =
                    action === 'approve'
                        ? await adminApi.approveLoanRequest(
                              loanRequestId,
                              payload as LoanRequestApprovePayload,
                          )
                        : await adminApi.declineLoanRequest(loanRequestId, {
                              decision_notes:
                                  payload.decision_notes ?? null,
                          });

                showSuccessToast(successCopy[action], { id: toastId });
                options?.onUpdated?.(loanRequest, action);
                return loanRequest;
            } catch (error) {
                showErrorToast(error, errorCopy[action], { id: toastId });
                return null;
            } finally {
                setProcessingIds((current) => {
                    const next = { ...current };
                    delete next[loanRequestId];
                    return next;
                });
            }
        },
        [options],
    );

    return {
        updateDecision,
        processingIds,
    };
}
