import { useCallback, useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import type { LoanRequestWorkflowResult } from '@/types/loan-requests';

export type LoanRequestWorkflowAction =
    | 'startReview'
    | 'requestRevision'
    | 'reject'
    | 'recommendApproval'
    | 'approve'
    | 'decline'
    | 'convertToLoan';

export type LoanRequestStartReviewPayload = {
    remarks?: string | null;
};

export type LoanRequestRequestRevisionPayload = {
    remarks: string;
};

export type LoanRequestRejectPayload = {
    rejection_reason: string;
};

export type LoanRequestRecommendApprovalPayload = {
    review_remarks?: string | null;
};

export type LoanRequestWorkflowApprovePayload = {
    approved_amount: number | string;
    approved_term: number | string;
    approved_interest_rate?: number | string | null;
    approval_remarks?: string | null;
};

export type LoanRequestWorkflowDeclinePayload = {
    decline_reason: string;
};

export type LoanRequestConvertToLoanPayload = {
    remarks?: string | null;
};

type LoanRequestWorkflowPayload =
    | LoanRequestStartReviewPayload
    | LoanRequestRequestRevisionPayload
    | LoanRequestRejectPayload
    | LoanRequestRecommendApprovalPayload
    | LoanRequestWorkflowApprovePayload
    | LoanRequestWorkflowDeclinePayload
    | LoanRequestConvertToLoanPayload;

type LoanRequestWorkflowOptions = {
    onUpdated?: (
        result: LoanRequestWorkflowResult,
        action: LoanRequestWorkflowAction,
    ) => void;
};

const successCopy: Record<LoanRequestWorkflowAction, string> = {
    startReview: 'Loan request moved to under review.',
    requestRevision: 'Revision request sent successfully.',
    reject: 'Loan request rejected successfully.',
    recommendApproval: 'Loan request recommended for approval.',
    approve: 'Loan request approved successfully.',
    decline: 'Loan request declined successfully.',
    convertToLoan: 'Approved request converted to a loan.',
};

const errorCopy: Record<LoanRequestWorkflowAction, string> = {
    startReview: 'Failed to start reviewing the loan request.',
    requestRevision: 'Failed to request a revision.',
    reject: 'Failed to reject the loan request.',
    recommendApproval: 'Failed to recommend the request for approval.',
    approve: 'Failed to approve the loan request.',
    decline: 'Failed to decline the loan request.',
    convertToLoan: 'Failed to convert the request to a loan.',
};

export function useLoanRequestWorkflow(
    options?: LoanRequestWorkflowOptions,
) {
    const [processingIds, setProcessingIds] = useState<Record<number, boolean>>(
        {},
    );

    const runAction = useCallback(
        async (
            loanRequestId: number,
            action: LoanRequestWorkflowAction,
            payload: LoanRequestWorkflowPayload = {},
        ) => {
            setProcessingIds((current) => ({
                ...current,
                [loanRequestId]: true,
            }));

            const toastId = `loan-request-workflow-${action}-${loanRequestId}`;

            try {
                const result = await (async (): Promise<LoanRequestWorkflowResult> => {
                    if (action === 'startReview') {
                        return adminApi.startLoanRequestReview(
                            loanRequestId,
                            payload as LoanRequestStartReviewPayload,
                        );
                    }

                    if (action === 'requestRevision') {
                        return adminApi.requestLoanRequestRevision(
                            loanRequestId,
                            payload as LoanRequestRequestRevisionPayload,
                        );
                    }

                    if (action === 'reject') {
                        return adminApi.rejectLoanRequestForWorkflow(
                            loanRequestId,
                            payload as LoanRequestRejectPayload,
                        );
                    }

                    if (action === 'recommendApproval') {
                        return adminApi.recommendLoanRequestApproval(
                            loanRequestId,
                            payload as LoanRequestRecommendApprovalPayload,
                        );
                    }

                    if (action === 'approve') {
                        return adminApi.approveLoanRequestForWorkflow(
                            loanRequestId,
                            payload as LoanRequestWorkflowApprovePayload,
                        );
                    }

                    if (action === 'decline') {
                        return adminApi.declineLoanRequestForWorkflow(
                            loanRequestId,
                            payload as LoanRequestWorkflowDeclinePayload,
                        );
                    }

                    return adminApi.convertLoanRequestToLoan(
                        loanRequestId,
                        payload as LoanRequestConvertToLoanPayload,
                    );
                })();

                showSuccessToast(successCopy[action], { id: toastId });
                options?.onUpdated?.(result, action);

                return result;
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
        processingIds,
        startReview: (
            loanRequestId: number,
            payload: LoanRequestStartReviewPayload = {},
        ) => runAction(loanRequestId, 'startReview', payload),
        requestRevision: (
            loanRequestId: number,
            payload: LoanRequestRequestRevisionPayload,
        ) => runAction(loanRequestId, 'requestRevision', payload),
        rejectLoanRequest: (
            loanRequestId: number,
            payload: LoanRequestRejectPayload,
        ) => runAction(loanRequestId, 'reject', payload),
        recommendApproval: (
            loanRequestId: number,
            payload: LoanRequestRecommendApprovalPayload = {},
        ) => runAction(loanRequestId, 'recommendApproval', payload),
        approveLoanRequest: (
            loanRequestId: number,
            payload: LoanRequestWorkflowApprovePayload,
        ) => runAction(loanRequestId, 'approve', payload),
        declineLoanRequest: (
            loanRequestId: number,
            payload: LoanRequestWorkflowDeclinePayload,
        ) => runAction(loanRequestId, 'decline', payload),
        convertToLoan: (
            loanRequestId: number,
            payload: LoanRequestConvertToLoanPayload = {},
        ) => runAction(loanRequestId, 'convertToLoan', payload),
    };
}
