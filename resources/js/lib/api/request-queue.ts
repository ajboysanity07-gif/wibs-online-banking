import type { AxiosResponse } from 'axios';
import client from '@/lib/api/client';
import { index as staffLoanRequestsIndex } from '@/routes/spa/staff/loan-requests';
import type { RequestsResponse } from '@/types/admin';

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

export type RequestQueueWorkspace = 'admin' | 'staff';

export type RequestQueueQueryParams = {
    search?: string;
    loanType?: string;
    status?: string;
    reported?: boolean;
    minAmount?: number;
    maxAmount?: number;
    page?: number;
    perPage?: number;
};

const requestQueueEndpoints: Record<RequestQueueWorkspace, string> = {
    admin: '/spa/admin/requests',
    staff: staffLoanRequestsIndex().url,
};

export const requestQueueApi = {
    async getRequests(
        workspace: RequestQueueWorkspace,
        params: RequestQueueQueryParams,
        signal?: AbortSignal,
    ): Promise<RequestsResponse> {
        const response = await client.get<ApiResponse<RequestsResponse>>(
            requestQueueEndpoints[workspace],
            {
                params,
                signal,
            },
        );

        return unwrap(response);
    },
};
