import { useCallback, useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import { showErrorToast, showSuccessToast } from '@/lib/toast';

type CreateAdminCorrectedCopyPayload = {
    correction_reason: string;
};

type CreateAdminCorrectedCopyResult = {
    loanRequest: {
        id: number;
        reference: string;
        url: string;
    };
};

type CreateAdminCorrectedCopyOptions = {
    onCreated?: (result: CreateAdminCorrectedCopyResult) => void;
};

export function useCreateAdminCorrectedLoanRequest(
    options?: CreateAdminCorrectedCopyOptions,
) {
    const [processingIds, setProcessingIds] = useState<Record<number, boolean>>(
        {},
    );

    const createAdminCorrectedCopy = useCallback(
        async (
            loanRequestId: number,
            payload: CreateAdminCorrectedCopyPayload,
        ) => {
            setProcessingIds((current) => ({
                ...current,
                [loanRequestId]: true,
            }));

            const toastId = `loan-request-admin-corrected-copy-${loanRequestId}`;

            try {
                const result = await adminApi.createAdminCorrectedCopy(
                    loanRequestId,
                    payload,
                );

                showSuccessToast('Corrected request created successfully.', {
                    id: toastId,
                });
                options?.onCreated?.(result);

                return result;
            } catch (error) {
                showErrorToast(error, 'Failed to create corrected request.', {
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
        createAdminCorrectedCopy,
        processingIds,
    };
}
