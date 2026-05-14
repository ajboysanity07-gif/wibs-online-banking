import { useCallback, useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import type {
    LoanRequestCorrectionReport,
    LoanRequestDetail,
} from '@/types/loan-requests';

export type LoanRequestCancellationPayload = {
    cancellation_reason: string;
};

export type LoanRequestCancellationResult = {
    loanRequest: LoanRequestDetail;
    correctionReports: LoanRequestCorrectionReport[];
};

type LoanRequestCancellationOptions = {
    onUpdated?: (result: LoanRequestCancellationResult) => void;
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
                const result = await adminApi.cancelLoanRequest(
                    loanRequestId,
                    payload,
                );

                showSuccessToast('Loan request cancelled successfully.', {
                    id: toastId,
                });
                options?.onUpdated?.(result);

                return result;
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
