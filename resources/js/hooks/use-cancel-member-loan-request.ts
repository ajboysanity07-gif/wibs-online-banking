import type { AxiosResponse } from 'axios';
import { useCallback, useState } from 'react';
import client from '@/lib/api/client';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import type { LoanRequestMemberCancellationResult } from '@/types/loan-requests';

type ApiResponse<T> = {
    ok: boolean;
    data: T;
};

type LoanRequestCancellationPayload = {
    cancellation_reason?: string | null;
};

const unwrap = <T>(response: AxiosResponse<ApiResponse<T>>): T => {
    if (!response.data?.data) {
        throw new Error('Unexpected response from the server.');
    }

    return response.data.data;
};

type CancelMemberLoanRequestOptions = {
    onUpdated?: (result: LoanRequestMemberCancellationResult) => void;
};

export function useCancelMemberLoanRequest(
    options?: CancelMemberLoanRequestOptions,
) {
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

            const toastId = `member-loan-request-cancel-${loanRequestId}`;

            try {
                const response = await client.patch<
                    ApiResponse<LoanRequestMemberCancellationResult>
                >(`/client/loans/requests/${loanRequestId}/cancel`, payload);

                const result = unwrap(response);

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
