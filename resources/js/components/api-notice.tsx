import { useEffect } from 'react';
import { showErrorToast } from '@/lib/toast';

type NoticeType = 'forbidden' | 'session-expired';

const noticeContent: Record<NoticeType, { title: string; message: string }> = {
    forbidden: {
        title: 'Access denied',
        message: 'You do not have access to perform this action.',
    },
    'session-expired': {
        title: 'Session expired',
        message: 'Your session has expired. Please refresh and try again.',
    },
};

export default function ApiNotice() {
    useEffect(() => {
        const showNotice = (type: NoticeType) => {
            const content = noticeContent[type];
            showErrorToast(null, content.title, {
                id: `api-${type}`,
                description: content.message,
            });
        };

        const handleForbidden = () => {
            showNotice('forbidden');
        };

        const handleSessionExpired = () => {
            showNotice('session-expired');
        };

        window.addEventListener('api:forbidden', handleForbidden);
        window.addEventListener('api:session-expired', handleSessionExpired);

        return () => {
            window.removeEventListener('api:forbidden', handleForbidden);
            window.removeEventListener(
                'api:session-expired',
                handleSessionExpired,
            );
        };
    }, []);

    return null;
}
