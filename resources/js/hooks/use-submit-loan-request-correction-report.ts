import type { AxiosResponse } from 'axios';
import { useCallback, useState } from 'react';
import client from '@/lib/api/client';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import type { LoanRequestCorrectionReportPayload } from '@/types/loan-requests';

type ApiResponse<T> = {
    ok: boolean;
    data: T;
};

type LoanRequestCorrectionReportResponse = {
    report: {
        id: number;
        status: string;
    };
};

const unwrap = <T>(response: AxiosResponse<ApiResponse<T>>): T => {
    if (!response.data?.data) {
        throw new Error('Unexpected response from the server.');
    }

    return response.data.data;
};

type SubmitLoanRequestCorrectionReportOptions = {
    onSubmitted?: () => void;
};

export function useSubmitLoanRequestCorrectionReport(
    options?: SubmitLoanRequestCorrectionReportOptions,
) {
    const [processingIds, setProcessingIds] = useState<Record<number, boolean>>(
        {},
    );

    const submitReport = useCallback(
        async (
            loanRequestId: number,
            payload: LoanRequestCorrectionReportPayload,
        ) => {
            setProcessingIds((current) => ({
                ...current,
                [loanRequestId]: true,
            }));

            const toastId = `loan-request-correction-report-${loanRequestId}`;

            try {
                const response =
                    await client.post<ApiResponse<LoanRequestCorrectionReportResponse>>(
                        `/client/loans/requests/${loanRequestId}/correction-reports`,
                        payload,
                    );

                unwrap(response);

                showSuccessToast('Correction report sent to admin.', {
                    id: toastId,
                });
                options?.onSubmitted?.();

                return true;
            } catch (error) {
                showErrorToast(error, 'Failed to send correction report.', {
                    id: toastId,
                });

                return false;
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
        submitReport,
        processingIds,
    };
}
