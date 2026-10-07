import { useRequestQueue } from '@/hooks/loan-request/use-request-queue';

type ReportedRequestsParams = {
    search: string;
    page: number;
    perPage: number;
};

export function useStaffReportedRequests(params: ReportedRequestsParams) {
    return useRequestQueue({ ...params, workspace: 'staff', reported: true });
}
