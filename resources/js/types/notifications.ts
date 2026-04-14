import type { LoanRequestStatusValue } from '@/types/loan-requests';

export type NotificationPayload = {
    type: string;
    loan_request_id: number;
    reference: string;
    status: LoanRequestStatusValue | string;
    title: string;
    message: string;
    decision_notes: string | null;
    reviewed_at: string | null;
};

export type NotificationItem = {
    id: string;
    data: NotificationPayload;
    read_at: string | null;
    created_at: string | null;
};

export type NotificationListResponse = {
    items: NotificationItem[];
};

export type NotificationUnreadCountResponse = {
    unreadCount: number;
};

export type NotificationReadResponse = {
    notification: NotificationItem;
    unreadCount: number;
};

export type NotificationReadAllResponse = {
    unreadCount: number;
    readAt: string;
};
