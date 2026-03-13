import type { AxiosResponse } from 'axios';
import client from '@/lib/api/client';
import type {
    DashboardSummary,
    MemberAccountActionsResponse,
    MemberAccountsSummary,
    MemberDetail,
    MemberLoansResponse,
    MemberLoanPaymentsResponse,
    MemberLoanScheduleResponse,
    MemberSavingsResponse,
    MemberStatusAction,
    MembersResponse,
    PendingApprovalsResponse,
    RequestsResponse,
} from '@/types/admin';

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
    status?: string | null;
    sort?: string;
    page?: number;
    perPage?: number;
};

type PendingApprovalsQueryParams = {
    search?: string;
    sort?: string;
    page?: number;
    perPage?: number;
};

type RequestsQueryParams = {
    search?: string;
    page?: number;
    perPage?: number;
};

type MemberAccountQueryParams = {
    page?: number;
    perPage?: number;
};

type MemberLoanPaymentsQueryParams = {
    page?: number;
    perPage?: number;
    range?: string;
    start?: string | null;
    end?: string | null;
};

export const adminApi = {
    async getDashboardSummary(): Promise<DashboardSummary> {
        const response = await client.get<ApiResponse<DashboardSummary>>(
            '/spa/admin/summary',
        );

        return unwrap(response);
    },
    async getMembers(
        params: MembersQueryParams,
        signal?: AbortSignal,
    ): Promise<MembersResponse> {
        const response = await client.get<ApiResponse<MembersResponse>>(
            '/spa/admin/members',
            { params, signal },
        );

        return unwrap(response);
    },
    async getMemberDetail(
        userId: number,
        signal?: AbortSignal,
    ): Promise<MemberDetail> {
        const response = await client.get<ApiResponse<{ member: MemberDetail }>>(
            `/spa/admin/members/${userId}`,
            { signal },
        );

        return unwrap(response).member;
    },
    async updateMemberStatus(
        userId: number,
        action: MemberStatusAction,
    ): Promise<MemberDetail> {
        const response = await client.patch<
            ApiResponse<{ member: MemberDetail }>
        >(`/spa/admin/members/${userId}/${action}`);

        return unwrap(response).member;
    },
    async getPendingApprovals(
        params: PendingApprovalsQueryParams,
        signal?: AbortSignal,
    ): Promise<PendingApprovalsResponse> {
        const response = await client.get<ApiResponse<PendingApprovalsResponse>>(
            '/spa/admin/pending-approvals',
            { params, signal },
        );

        return unwrap(response);
    },
    async getRequests(
        params: RequestsQueryParams,
        signal?: AbortSignal,
    ): Promise<RequestsResponse> {
        const response = await client.get<ApiResponse<RequestsResponse>>(
            '/spa/admin/requests',
            { params, signal },
        );

        return unwrap(response);
    },
    async getMemberAccountsSummary(
        userId: number,
        signal?: AbortSignal,
    ): Promise<MemberAccountsSummary> {
        const response = await client.get<
            ApiResponse<{ summary: MemberAccountsSummary }>
        >(`/admin/api/members/${userId}/accounts/summary`, { signal });

        return unwrap(response).summary;
    },
    async getMemberAccountActions(
        userId: number,
        params: MemberAccountQueryParams,
        signal?: AbortSignal,
    ): Promise<MemberAccountActionsResponse> {
        const response = await client.get<
            ApiResponse<MemberAccountActionsResponse>
        >(`/admin/api/members/${userId}/accounts/actions`, { params, signal });

        return unwrap(response);
    },
    async getMemberLoans(
        userId: number,
        params: MemberAccountQueryParams,
        signal?: AbortSignal,
    ): Promise<MemberLoansResponse> {
        const response = await client.get<ApiResponse<MemberLoansResponse>>(
            `/admin/api/members/${userId}/accounts/loans`,
            { params, signal },
        );

        return unwrap(response);
    },
    async getMemberSavings(
        userId: number,
        params: MemberAccountQueryParams,
        signal?: AbortSignal,
    ): Promise<MemberSavingsResponse> {
        const response = await client.get<ApiResponse<MemberSavingsResponse>>(
            `/admin/api/members/${userId}/accounts/savings`,
            { params, signal },
        );

        return unwrap(response);
    },
    async getMemberLoanSchedule(
        userId: number,
        loanNumber: string | number,
        signal?: AbortSignal,
    ): Promise<MemberLoanScheduleResponse> {
        const response = await client.get<ApiResponse<MemberLoanScheduleResponse>>(
            `/admin/api/members/${userId}/loans/${loanNumber}/schedule`,
            { signal },
        );

        return unwrap(response);
    },
    async getMemberLoanPayments(
        userId: number,
        loanNumber: string | number,
        params: MemberLoanPaymentsQueryParams,
        signal?: AbortSignal,
    ): Promise<MemberLoanPaymentsResponse> {
        const response = await client.get<ApiResponse<MemberLoanPaymentsResponse>>(
            `/admin/api/members/${userId}/loans/${loanNumber}/payments`,
            { params, signal },
        );

        return unwrap(response);
    },
};
