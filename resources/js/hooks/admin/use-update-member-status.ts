import { useCallback, useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import { adminToastCopy, showErrorToast, showSuccessToast } from '@/lib/toast';
import type { MemberDetail, MemberStatusAction } from '@/types/admin';

type StatusOptions = {
    onUpdated?: (member: MemberDetail, action: MemberStatusAction) => void;
};

const successCopy: Record<MemberStatusAction, string> = {
    suspend: adminToastCopy.success.suspended('member'),
    reactivate: adminToastCopy.success.reactivated('member'),
};

const errorCopy: Record<MemberStatusAction, string> = {
    suspend: adminToastCopy.error.updatedStatus(),
    reactivate: adminToastCopy.error.updatedStatus(),
};

export function useUpdateMemberStatus(options?: StatusOptions) {
    const [processingIds, setProcessingIds] = useState<Record<number, boolean>>(
        {},
    );

    const updateStatus = useCallback(
        async (userId: number, action: MemberStatusAction) => {
            setProcessingIds((current) => ({ ...current, [userId]: true }));
            const toastId = `member-status-${action}-${userId}`;

            try {
                const member = await adminApi.updateMemberStatus(
                    userId,
                    action,
                );
                showSuccessToast(successCopy[action], { id: toastId });
                options?.onUpdated?.(member, action);
                return member;
            } catch (error) {
                showErrorToast(error, errorCopy[action], { id: toastId });
                return null;
            } finally {
                setProcessingIds((current) => {
                    const next = { ...current };
                    delete next[userId];
                    return next;
                });
            }
        },
        [options],
    );

    return {
        updateStatus,
        processingIds,
    };
}
