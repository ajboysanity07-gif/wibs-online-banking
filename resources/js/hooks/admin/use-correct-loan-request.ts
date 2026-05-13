import axios from 'axios';
import { useCallback, useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import type {
    LoanRequestCorrectionPayload,
    LoanRequestCorrectionResult,
} from '@/types/loan-requests';

type ValidationErrors = Record<string, string | undefined>;

type LoanRequestCorrectionOptions = {
    onUpdated?: (result: LoanRequestCorrectionResult) => void;
};

type LaravelValidationPayload = {
    errors?: Record<string, string[] | string>;
};

const normalizeValidationErrors = (error: unknown): ValidationErrors => {
    if (!axios.isAxiosError(error) || error.response?.status !== 422) {
        return {};
    }

    const payload = error.response.data as LaravelValidationPayload | undefined;
    const errors = payload?.errors;

    if (!errors) {
        return {};
    }

    return Object.entries(errors).reduce<ValidationErrors>(
        (normalized, [field, messages]) => {
            normalized[field] = Array.isArray(messages)
                ? messages[0]
                : messages;

            return normalized;
        },
        {},
    );
};

export function useCorrectLoanRequest(options?: LoanRequestCorrectionOptions) {
    const [processingIds, setProcessingIds] = useState<Record<number, boolean>>(
        {},
    );
    const [errors, setErrors] = useState<ValidationErrors>({});

    const clearErrors = useCallback(() => setErrors({}), []);

    const correctLoanRequest = useCallback(
        async (
            loanRequestId: number,
            payload: LoanRequestCorrectionPayload,
        ) => {
            setProcessingIds((current) => ({
                ...current,
                [loanRequestId]: true,
            }));
            setErrors({});

            const toastId = `loan-request-correction-${loanRequestId}`;

            try {
                const result = await adminApi.correctLoanRequest(
                    loanRequestId,
                    payload,
                );

                showSuccessToast('Loan request details updated.', {
                    id: toastId,
                });
                options?.onUpdated?.(result);
                return result;
            } catch (error) {
                setErrors(normalizeValidationErrors(error));
                showErrorToast(error, 'Failed to correct loan request details.', {
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
        correctLoanRequest,
        processingIds,
        errors,
        clearErrors,
    };
}
