import { useRequestQueue, type RequestQueueParams } from '@/hooks/loan-request/use-request-queue';

export type RequestsParams = Omit<RequestQueueParams, 'workspace'>;

export function useRequests(params: RequestsParams) {
    return useRequestQueue({
        ...params,
        workspace: 'staff',
    });
}
