import type { SavedPaymentAccount } from '@/hooks/use-saved-payment-accounts';

export function maskAccountNumber(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const trimmed = value.trim();

    return trimmed.length > 4 ? `••${trimmed.slice(-4)}` : trimmed;
}

export function getAccountDisplayLabel(
    account: SavedPaymentAccount,
    method: string | null,
): string {
    if (account.has_custom_label && account.label) {
        return account.label;
    }

    const isAtm = method === 'ATM' || method === 'ATM Deduction';
    const masked = maskAccountNumber(
        isAtm ? account.atm_number : account.account_number,
    );
    const parts = [account.bank_name, masked].filter(Boolean);

    return parts.length > 0 ? parts.join(' ') : 'Saved account';
}
