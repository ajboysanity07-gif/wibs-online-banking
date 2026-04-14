import type { AxiosResponse } from 'axios';
import client from '@/lib/api/client';
import type {
    NotificationItem,
    NotificationListResponse,
    NotificationReadAllResponse,
    NotificationReadResponse,
    NotificationUnreadCountResponse,
} from '@/types/notifications';

type ApiResponse<T> = {
    ok: boolean;
    data: T;
};

const unwrap = <T>(response: AxiosResponse<ApiResponse<T>>): T => {
    if (!response.data?.data) {
        throw new Error('Unexpected response from the server.');
    }

    return response.data.data;
};

export const notificationsApi = {
    async getUnreadCount(): Promise<number> {
        const response = await client.get<
            ApiResponse<NotificationUnreadCountResponse>
        >('/spa/notifications/unread-count');

        return unwrap(response).unreadCount;
    },
    async getNotifications(): Promise<NotificationItem[]> {
        const response = await client.get<
            ApiResponse<NotificationListResponse>
        >('/spa/notifications');

        return unwrap(response).items;
    },
    async markAsRead(id: string): Promise<NotificationReadResponse> {
        const response = await client.patch<
            ApiResponse<NotificationReadResponse>
        >(`/spa/notifications/${id}/read`);

        return unwrap(response);
    },
    async markAllAsRead(): Promise<NotificationReadAllResponse> {
        const response = await client.patch<
            ApiResponse<NotificationReadAllResponse>
        >('/spa/notifications/read-all');

        return unwrap(response);
    },
};
