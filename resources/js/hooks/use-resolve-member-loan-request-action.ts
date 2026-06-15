import type { AxiosResponse } from 'axios';
import { useCallback, useState } from 'react';
import client from '@/lib/api/client';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import type { LoanRequestMemberActionResolutionResult } from '@/types/loan-requests';

type ApiResponse<T> = {
    ok: boolean;
    data: T;
};

type ResolveMemberActionPayload = {
    decision?: 'accept' | 'decline';
    reason?: string | null;
    insurance?: Record<string, unknown>;
    health?: Record<string, unknown>;
    authorization?: Record<string, unknown>;
    banking?: Record<string, unknown>;
    barangay?: Record<string, unknown>;
    declarations?: Record<string, unknown>;
};

const unwrap = <T>(response: AxiosResponse<ApiResponse<T>>): T => {
    if (!response.data?.data) {
        throw new Error('Unexpected response from the server.');
    }

    return response.data.data;
};

type ResolveMemberLoanRequestActionOptions = {
    onUpdated?: (result: LoanRequestMemberActionResolutionResult) => void;
};

export function useResolveMemberLoanRequestAction(
    options?: ResolveMemberLoanRequestActionOptions,
) {
    const [processingIds, setProcessingIds] = useState<Record<number, boolean>>(
        {},
    );

    const resolveAction = useCallback(
        async (
            loanRequestId: number,
            payload: ResolveMemberActionPayload,
            successMessage: string,
        ) => {
            setProcessingIds((current) => ({
                ...current,
                [loanRequestId]: true,
            }));

            const toastId = `member-loan-request-action-${loanRequestId}`;

            try {
                const response = await client.patch<
                    ApiResponse<LoanRequestMemberActionResolutionResult>
                >(`/client/loans/requests/${loanRequestId}/resolve-action`, payload);

                const result = unwrap(response);

                showSuccessToast(successMessage, {
                    id: toastId,
                });
                options?.onUpdated?.(result);

                return result;
            } catch (error) {
                showErrorToast(
                    error,
                    'Failed to complete the requested loan-request action.',
                    {
                        id: toastId,
                    },
                );

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
        resolveAction,
        processingIds,
    };
}
