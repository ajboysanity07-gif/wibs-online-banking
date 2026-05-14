import { useCallback, useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import type {
    LoanRequestCorrectionReport,
    LoanRequestCorrectionReportDismissPayload,
} from '@/types/loan-requests';

type DismissCorrectionReportResult = {
    report: LoanRequestCorrectionReport;
    correctionReports: LoanRequestCorrectionReport[];
};

type DismissCorrectionReportOptions = {
    onDismissed?: (result: DismissCorrectionReportResult) => void;
};

export function useDismissLoanRequestCorrectionReport(
    options?: DismissCorrectionReportOptions,
) {
    const [processingIds, setProcessingIds] = useState<Record<number, boolean>>(
        {},
    );

    const dismissCorrectionReport = useCallback(
        async (
            loanRequestId: number,
            reportId: number,
            payload: LoanRequestCorrectionReportDismissPayload,
        ) => {
            setProcessingIds((current) => ({
                ...current,
                [reportId]: true,
            }));

            const toastId = `loan-request-correction-report-dismiss-${reportId}`;

            try {
                const result = await adminApi.dismissLoanRequestCorrectionReport(
                    loanRequestId,
                    reportId,
                    payload,
                );

                showSuccessToast('Correction report dismissed.', {
                    id: toastId,
                });
                options?.onDismissed?.(result);

                return result;
            } catch (error) {
                showErrorToast(error, 'Failed to dismiss correction report.', {
                    id: toastId,
                });

                return null;
            } finally {
                setProcessingIds((current) => {
                    const next = { ...current };
                    delete next[reportId];

                    return next;
                });
            }
        },
        [options],
    );

    return {
        dismissCorrectionReport,
        processingIds,
    };
}
