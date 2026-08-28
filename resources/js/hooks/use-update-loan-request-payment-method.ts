import type { AxiosResponse } from 'axios';
import { useState } from 'react';
import client from '@/lib/api/client';
import { showErrorToast, showSuccessToast } from '@/lib/toast';

export type UpdateLoanRequestPaymentMethodPayload = {
    release_method?: string | null;
    release_saved_account_id?: number | null;
    payment_option?: string | null;
    payment_saved_account_id?: number | null;
};

type ApiResponse<T> = {
    ok: boolean;
    data: T;
};

const unwrap = <T>(response: AxiosResponse<ApiResponse<T>>): T => {
    if (response.data?.data === undefined) {
        throw new Error('Unexpected response from the server.');
    }

    return response.data.data;
};

export function useUpdateLoanRequestPaymentMethod() {
    const [isSaving, setIsSaving] = useState(false);

    const updatePaymentMethod = async (
        loanRequestId: number,
        payload: UpdateLoanRequestPaymentMethodPayload,
    ) => {
        setIsSaving(true);

        try {
            const response = await client.patch<ApiResponse<unknown>>(
                `/client/loans/requests/${loanRequestId}/payment-method`,
                payload,
            );

            const result = unwrap(response);
            showSuccessToast('Payment method updated successfully.');

            return result;
        } catch (error) {
            showErrorToast(error, 'Failed to update payment method.');

            return null;
        } finally {
            setIsSaving(false);
        }
    };

    return {
        updatePaymentMethod,
        isSaving,
    };
}
