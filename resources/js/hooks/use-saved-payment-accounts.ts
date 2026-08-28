import type { AxiosResponse } from 'axios';
import { useCallback, useState } from 'react';
import client from '@/lib/api/client';
import { showErrorToast, showSuccessToast } from '@/lib/toast';

export type SavedPaymentAccount = {
    id: number;
    label: string;
    bank_name: string | null;
    account_number: string | null;
    last_used_at: string | null;
};

export type SavedPaymentAccountFormPayload = {
    label?: string | null;
    bank_name: string;
    account_name?: string | null;
    account_number: string;
    account_type?: string | null;
    atm_number?: string | null;
    bank_branch?: string | null;
    atm_holder_name?: string | null;
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

export function useSavedPaymentAccounts() {
    const [accounts, setAccounts] = useState<SavedPaymentAccount[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isSaving, setIsSaving] = useState(false);

    const loadAccounts = useCallback(async () => {
        setIsLoading(true);

        try {
            const response = await client.get<
                ApiResponse<SavedPaymentAccount[]>
            >('/client/saved-payment-accounts');

            setAccounts(unwrap(response));
        } catch (error) {
            showErrorToast(error, 'Failed to load saved payment accounts.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const createAccount = async (payload: SavedPaymentAccountFormPayload) => {
        setIsSaving(true);

        try {
            const response = await client.post<
                ApiResponse<SavedPaymentAccount>
            >('/client/saved-payment-accounts', payload);

            const created = unwrap(response);
            setAccounts((current) => [created, ...current]);
            showSuccessToast('Saved payment account added.');

            return created;
        } catch (error) {
            showErrorToast(error, 'Failed to save the payment account.');

            return null;
        } finally {
            setIsSaving(false);
        }
    };

    const deleteAccount = async (id: number) => {
        try {
            await client.delete(`/client/saved-payment-accounts/${id}`);
            setAccounts((current) =>
                current.filter((account) => account.id !== id),
            );
            showSuccessToast('Saved payment account removed.');

            return true;
        } catch (error) {
            showErrorToast(error, 'Failed to remove the payment account.');

            return false;
        }
    };

    return {
        accounts,
        isLoading,
        isSaving,
        loadAccounts,
        createAccount,
        deleteAccount,
    };
}
