import { useCallback, useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import { adminToastCopy, showErrorToast, showSuccessToast } from '@/lib/toast';
import type { MemberDetail, MemberAdminAccessAction } from '@/types/admin';

type AdminAccessOptions = {
    onUpdated?: (member: MemberDetail, action: MemberAdminAccessAction) => void;
};

const successCopy: Record<MemberAdminAccessAction, string> = {
    grant: adminToastCopy.success.enabled('admin access'),
    revoke: adminToastCopy.success.disabled('admin access'),
};

const errorCopy: Record<MemberAdminAccessAction, string> = {
    grant: adminToastCopy.error.enabled('admin access'),
    revoke: adminToastCopy.error.disabled('admin access'),
};

export function useUpdateMemberAdminAccess(options?: AdminAccessOptions) {
    const [processingKeys, setProcessingKeys] = useState<
        Record<string, boolean>
    >({});

    const updateAdminAccess = useCallback(
        async (memberKey: string | number, action: MemberAdminAccessAction) => {
            const key = String(memberKey);
            setProcessingKeys((current) => ({ ...current, [key]: true }));
            const toastId = `member-admin-access-${action}-${key}`;

            try {
                const member =
                    action === 'grant'
                        ? await adminApi.grantMemberAdminAccess(memberKey)
                        : await adminApi.revokeMemberAdminAccess(memberKey);
                showSuccessToast(successCopy[action], { id: toastId });
                options?.onUpdated?.(member, action);
                return member;
            } catch (error) {
                showErrorToast(error, errorCopy[action], { id: toastId });
                return null;
            } finally {
                setProcessingKeys((current) => {
                    const next = { ...current };
                    delete next[key];
                    return next;
                });
            }
        },
        [options],
    );

    return {
        updateAdminAccess,
        processingKeys,
    };
}
