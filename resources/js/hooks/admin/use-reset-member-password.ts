import { useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import type { MemberDetail } from '@/types/admin';

type ResetMemberPasswordOptions = {
    onReset?: (member: MemberDetail, temporaryPassword: string) => void;
};

export function useResetMemberPassword(options?: ResetMemberPasswordOptions) {
    const [processingKeys, setProcessingKeys] = useState<
        Record<string, boolean>
    >({});

    const resetPassword = async (
        memberKey: string | number,
        reason: string,
    ) => {
        const key = String(memberKey);
        setProcessingKeys((current) => ({ ...current, [key]: true }));
        const toastId = `member-reset-password-${key}`;

        try {
            const result = await adminApi.resetMemberPassword(
                memberKey,
                reason,
            );
            showSuccessToast('Password reset.', { id: toastId });
            options?.onReset?.(result.member, result.temporary_password);
            return result;
        } catch (error) {
            showErrorToast(error, 'Failed to reset password.', {
                id: toastId,
            });
            return null;
        } finally {
            setProcessingKeys((current) => {
                const next = { ...current };
                delete next[key];
                return next;
            });
        }
    };

    return {
        resetPassword,
        processingKeys,
    };
}
