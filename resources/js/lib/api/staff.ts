import type { AxiosResponse } from 'axios';
import client from '@/lib/api/client';
import type { MembersResponse } from '@/types/admin';

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

type MembersQueryParams = {
    search?: string;
    registration?: string | null;
    sort?: string;
    page?: number;
    perPage?: number;
};

export const staffApi = {
    async getMembers(
        params: MembersQueryParams,
        signal?: AbortSignal,
    ): Promise<MembersResponse> {
        const response = await client.get<ApiResponse<MembersResponse>>(
            '/spa/staff/members',
            { params, signal },
        );

        return unwrap(response);
    },
};
