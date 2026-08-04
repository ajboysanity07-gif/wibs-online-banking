import { useCallback, useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import type { LoanRequestWorkflowResult } from '@/types/loan-requests';

export type LoanRequestWorkflowAction =
    | 'claim'
    | 'assign'
    | 'reassign'
    | 'returnToQueue'
    | 'startReview'
    | 'requestRevision'
    | 'reject'
    | 'updateProcessingDetails'
    | 'requestMemberAction'
    | 'rejectDuringProcessing'
    | 'generateDocuments'
    | 'recommendApproval'
    | 'approve'
    | 'decline'
    | 'returnForProcessing'
    | 'reopen'
    | 'upgradeWorkflow';

export type LoanRequestClaimPayload = Record<string, never>;

export type LoanRequestAssignmentPayload = {
    officer_user_id: number;
    reason: string;
};

export type LoanRequestReturnToQueuePayload = {
    reason: string;
};

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
    approved_payment_frequency?: string | null;
    approval_remarks?: string | null;
};

export type LoanRequestWorkflowDeclinePayload = {
    decline_category: string;
    decline_reason: string;
};

export type LoanRequestProcessingDetailsPayload = {
    reason: string;
    loan_request?: Record<string, unknown>;
    applicant?: Record<string, unknown>;
    co_maker_1?: Record<string, unknown>;
    co_maker_2?: Record<string, unknown>;
    processing?: Record<string, unknown>;
    recommended_amount?: number | string | null;
    recommended_term?: number | string | null;
    recommended_interest_rate?: number | string | null;
    recommended_payment_frequency?: string | null;
};

export type LoanRequestMemberActionPayload = {
    action_type: 'needs_revision' | 'awaiting_member_information';
    message: string;
    reason: string;
    field_keys?: string[];
};

export type LoanRequestRejectDuringProcessingPayload = {
    rejection_category: string;
    rejection_category_other?: string | null;
    member_visible_reason: string;
};

export type LoanRequestGenerateDocumentsPayload = {
    document_key?: string | null;
};

export type LoanRequestReturnForProcessingPayload = {
    reason: string;
};

export type LoanRequestReopenPayload = {
    reason: string;
    retain_assignment?: boolean;
};

export type LoanRequestUpgradeWorkflowPayload = {
    reason: string;
};

type LoanRequestWorkflowPayload =
    | LoanRequestClaimPayload
    | LoanRequestAssignmentPayload
    | LoanRequestReturnToQueuePayload
    | LoanRequestStartReviewPayload
    | LoanRequestRequestRevisionPayload
    | LoanRequestRejectPayload
    | LoanRequestProcessingDetailsPayload
    | LoanRequestMemberActionPayload
    | LoanRequestRejectDuringProcessingPayload
    | LoanRequestGenerateDocumentsPayload
    | LoanRequestRecommendApprovalPayload
    | LoanRequestWorkflowApprovePayload
    | LoanRequestWorkflowDeclinePayload
    | LoanRequestReturnForProcessingPayload
    | LoanRequestReopenPayload
    | LoanRequestUpgradeWorkflowPayload;

type LoanRequestWorkflowOptions = {
    onUpdated?: (
        result: LoanRequestWorkflowResult,
        action: LoanRequestWorkflowAction,
    ) => void;
};

export type LoanRequestWorkflowRunOptions = {
    // Suppresses the per-call success/error toast. Used when a caller is
    // looping through several sequential requests (e.g. bulk document
    // generation) and wants to show a single summary toast once the whole
    // batch finishes instead of one toast per request.
    silent?: boolean;
};

const successCopy: Record<LoanRequestWorkflowAction, string> = {
    claim: 'Loan request claimed successfully.',
    assign: 'Loan request assigned successfully.',
    reassign: 'Loan request reassigned successfully.',
    returnToQueue: 'Loan request returned to the queue.',
    startReview: 'Loan request moved to under review.',
    requestRevision: 'Revision request sent successfully.',
    reject: 'Loan request rejected successfully.',
    updateProcessingDetails: 'Processing details saved successfully.',
    requestMemberAction: 'Member action requested successfully.',
    rejectDuringProcessing: 'Loan request rejected during processing.',
    generateDocuments: 'Document generation completed.',
    recommendApproval: 'Loan request recommended for approval.',
    approve: 'Loan request approved successfully.',
    decline: 'Loan request declined successfully.',
    returnForProcessing: 'Loan request returned for processing.',
    reopen: 'Loan request reopened successfully.',
    upgradeWorkflow: 'Workflow upgraded successfully.',
};

const errorCopy: Record<LoanRequestWorkflowAction, string> = {
    claim: 'Failed to claim the loan request.',
    assign: 'Failed to assign the loan request.',
    reassign: 'Failed to reassign the loan request.',
    returnToQueue: 'Failed to return the loan request to the queue.',
    startReview: 'Failed to start reviewing the loan request.',
    requestRevision: 'Failed to request a revision.',
    reject: 'Failed to reject the loan request.',
    updateProcessingDetails: 'Failed to save processing details.',
    requestMemberAction: 'Failed to request member action.',
    rejectDuringProcessing:
        'Failed to reject the loan request during processing.',
    generateDocuments: 'Failed to generate the required documents.',
    recommendApproval: 'Failed to recommend the request for approval.',
    approve: 'Failed to approve the loan request.',
    decline: 'Failed to decline the loan request.',
    returnForProcessing: 'Failed to return the request for processing.',
    reopen: 'Failed to reopen the rejected request.',
    upgradeWorkflow: 'Failed to upgrade the workflow.',
};

export function useLoanRequestWorkflow(options?: LoanRequestWorkflowOptions) {
    const [processingIds, setProcessingIds] = useState<Record<number, boolean>>(
        {},
    );

    const runAction = useCallback(
        async (
            loanRequestId: number,
            action: LoanRequestWorkflowAction,
            payload: LoanRequestWorkflowPayload = {},
            runOptions?: LoanRequestWorkflowRunOptions,
        ) => {
            setProcessingIds((current) => ({
                ...current,
                [loanRequestId]: true,
            }));

            const toastId = `loan-request-workflow-${action}-${loanRequestId}`;

            try {
                const result =
                    await (async (): Promise<LoanRequestWorkflowResult> => {
                        if (action === 'claim') {
                            return adminApi.claimLoanRequest(
                                loanRequestId,
                                payload as LoanRequestClaimPayload,
                            );
                        }

                        if (action === 'assign') {
                            return adminApi.updateLoanRequestAssignment(
                                loanRequestId,
                                {
                                    ...(payload as LoanRequestAssignmentPayload),
                                    action: 'assign',
                                },
                            );
                        }

                        if (action === 'reassign') {
                            return adminApi.updateLoanRequestAssignment(
                                loanRequestId,
                                {
                                    ...(payload as LoanRequestAssignmentPayload),
                                    action: 'reassign',
                                },
                            );
                        }

                        if (action === 'returnToQueue') {
                            return adminApi.returnLoanRequestToQueue(
                                loanRequestId,
                                payload as LoanRequestReturnToQueuePayload,
                            );
                        }

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

                        if (action === 'updateProcessingDetails') {
                            return adminApi.updateLoanRequestProcessingDetails(
                                loanRequestId,
                                payload as LoanRequestProcessingDetailsPayload,
                            );
                        }

                        if (action === 'requestMemberAction') {
                            return adminApi.requestLoanRequestMemberAction(
                                loanRequestId,
                                payload as LoanRequestMemberActionPayload,
                            );
                        }

                        if (action === 'rejectDuringProcessing') {
                            return adminApi.rejectLoanRequestDuringProcessing(
                                loanRequestId,
                                payload as LoanRequestRejectDuringProcessingPayload,
                            );
                        }

                        if (action === 'generateDocuments') {
                            return adminApi.generateLoanRequestDocuments(
                                loanRequestId,
                                payload as LoanRequestGenerateDocumentsPayload,
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

                        if (action === 'returnForProcessing') {
                            return adminApi.returnLoanRequestForProcessing(
                                loanRequestId,
                                payload as LoanRequestReturnForProcessingPayload,
                            );
                        }

                        if (action === 'reopen') {
                            return adminApi.reopenLoanRequestForProcessing(
                                loanRequestId,
                                payload as LoanRequestReopenPayload,
                            );
                        }

                        if (action === 'upgradeWorkflow') {
                            return adminApi.upgradeLoanRequestWorkflow(
                                loanRequestId,
                                payload as LoanRequestUpgradeWorkflowPayload,
                            );
                        }

                        throw new Error(
                            `Unsupported workflow action: ${action}`,
                        );
                    })();

                if (!runOptions?.silent) {
                    showSuccessToast(successCopy[action], { id: toastId });
                }

                options?.onUpdated?.(result, action);

                return result;
            } catch (error) {
                if (!runOptions?.silent) {
                    showErrorToast(error, errorCopy[action], { id: toastId });
                }

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
        claimLoanRequest: (
            loanRequestId: number,
            payload: LoanRequestClaimPayload = {},
        ) => runAction(loanRequestId, 'claim', payload),
        assignLoanRequest: (
            loanRequestId: number,
            payload: LoanRequestAssignmentPayload,
        ) => runAction(loanRequestId, 'assign', payload),
        reassignLoanRequest: (
            loanRequestId: number,
            payload: LoanRequestAssignmentPayload,
        ) => runAction(loanRequestId, 'reassign', payload),
        returnLoanRequestToQueue: (
            loanRequestId: number,
            payload: LoanRequestReturnToQueuePayload,
        ) => runAction(loanRequestId, 'returnToQueue', payload),
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
        updateProcessingDetails: (
            loanRequestId: number,
            payload: LoanRequestProcessingDetailsPayload,
        ) => runAction(loanRequestId, 'updateProcessingDetails', payload),
        requestMemberAction: (
            loanRequestId: number,
            payload: LoanRequestMemberActionPayload,
        ) => runAction(loanRequestId, 'requestMemberAction', payload),
        rejectLoanRequestDuringProcessing: (
            loanRequestId: number,
            payload: LoanRequestRejectDuringProcessingPayload,
        ) => runAction(loanRequestId, 'rejectDuringProcessing', payload),
        generateDocuments: (
            loanRequestId: number,
            payload: LoanRequestGenerateDocumentsPayload = {},
            runOptions?: LoanRequestWorkflowRunOptions,
        ) => runAction(loanRequestId, 'generateDocuments', payload, runOptions),
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
        returnForProcessing: (
            loanRequestId: number,
            payload: LoanRequestReturnForProcessingPayload,
        ) => runAction(loanRequestId, 'returnForProcessing', payload),
        reopenLoanRequest: (
            loanRequestId: number,
            payload: LoanRequestReopenPayload,
        ) => runAction(loanRequestId, 'reopen', payload),
        upgradeWorkflow: (
            loanRequestId: number,
            payload: LoanRequestUpgradeWorkflowPayload,
        ) => runAction(loanRequestId, 'upgradeWorkflow', payload),
    };
}
