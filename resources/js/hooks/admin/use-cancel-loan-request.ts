import { useCallback, useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import type { LoanRequestDetail } from '@/types/loan-requests';

export type LoanRequestCancellationPayload = {
    cancellation_reason: string;
};

type LoanRequestCancellationOptions = {
    onUpdated?: (loanRequest: LoanRequestDetail) => void;
};

export function useCancelLoanRequest(options?: LoanRequestCancellationOptions) {
    const [processingIds, setProcessingIds] = useState<Record<number, boolean>>(
        {},
    );

    const cancelLoanRequest = useCallback(
        async (
            loanRequestId: number,
            payload: LoanRequestCancellationPayload,
        ) => {
            setProcessingIds((current) => ({
                ...current,
                [loanRequestId]: true,
            }));

            const toastId = `loan-request-cancel-${loanRequestId}`;

            try {
                const loanRequest = await adminApi.cancelLoanRequest(
                    loanRequestId,
                    payload,
                );

                showSuccessToast('Loan request cancelled successfully.', {
                    id: toastId,
                });
                options?.onUpdated?.(loanRequest);

                return loanRequest;
            } catch (error) {
                showErrorToast(error, 'Failed to cancel loan request.', {
                    id: toastId,
                });

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
        cancelLoanRequest,
        processingIds,
    };
}
