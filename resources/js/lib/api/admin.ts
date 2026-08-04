import type { AxiosResponse } from 'axios';
import client from '@/lib/api/client';
import {
    history as superadminStaffHistoryRoute,
    index as superadminStaffIndexRoute,
    reactivate as superadminStaffReactivateRoute,
    resetPassword as superadminStaffResetPasswordRoute,
    store as superadminStaffStoreRoute,
    suspend as superadminStaffSuspendRoute,
} from '@/routes/spa/superadmin/staff';
import { update as superadminStaffRoleUpdateRoute } from '@/routes/spa/superadmin/staff/roles';
import {
    claim as workflowClaimRoute,
    approve as workflowApproveRoute,
    decline as workflowDeclineRoute,
    recommendApproval as workflowRecommendApprovalRoute,
    reject as workflowRejectRoute,
    requestRevision as workflowRequestRevisionRoute,
    returnToQueue as workflowReturnToQueueRoute,
    startReview as workflowStartReviewRoute,
} from '@/routes/spa/workflow/loan-requests';
import { update as workflowAssignmentUpdateRoute } from '@/routes/spa/workflow/loan-requests/assignment';
import type {
    DashboardSummary,
    EditableStaffRoleName,
    MemberAccountActionsResponse,
    MemberAccountsSummary,
    MemberDetail,
    MemberLoansResponse,
    MemberLoanPaymentsResponse,
    MemberLoanScheduleResponse,
    MemberLoanSecurityLedgerResponse,
    MemberStatusAction,
    MembersResponse,
    ReportedRequestsResponse,
    RequestsResponse,
    StaffAccount,
    StaffDirectoryResponse,
    StaffHistoryEntry,
} from '@/types/admin';
import type {
    LoanRequestCorrectionReport,
    LoanRequestCorrectionReportDismissPayload,
    LoanRequestCorrectionPayload,
    LoanRequestCorrectionResult,
    LoanRequestDecisionResult,
    LoanRequestCancellationResult,
    LoanRequestWorkflowResult,
} from '@/types/loan-requests';

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

type RequestsQueryParams = {
    search?: string;
    loanType?: string;
    status?: string;
    reported?: boolean;
    minAmount?: number;
    maxAmount?: number;
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

type StaffDirectoryQueryParams = {
    search?: string;
    role?: EditableStaffRoleName | null;
    access?: 'active' | 'suspended' | null;
    page?: number;
    perPage?: number;
};

type CreateStaffPayload = {
    username: string;
    email: string;
    phoneno?: string | null;
    password: string;
    password_confirmation: string;
    roles: EditableStaffRoleName[];
    reason: string;
};

type UpdateStaffRolePayload = {
    role: EditableStaffRoleName;
    operation: 'assign' | 'remove';
    reason: string;
};

type StaffAccessPayload = {
    reason: string;
};

type LoanRequestApprovePayload = {
    approved_amount: number | string;
    approved_term: number | string;
    decision_notes?: string | null;
};

type LoanRequestDeclinePayload = {
    decision_notes?: string | null;
};

type LoanRequestCancellationPayload = {
    cancellation_reason: string;
};

type LoanRequestCorrectionReportDismissResult = {
    report: LoanRequestCorrectionReport;
    correctionReports: LoanRequestCorrectionReport[];
};

type LoanRequestAdminCorrectedCopyPayload = {
    correction_reason: string;
};

type LoanRequestAdminCorrectedCopyResult = {
    loanRequest: {
        id: number;
        reference: string;
        url: string;
    };
};

type LoanRequestWorkflowStartReviewPayload = {
    remarks?: string | null;
};

type LoanRequestWorkflowClaimPayload = Record<string, never>;

type LoanRequestWorkflowAssignmentPayload = {
    action: 'assign' | 'reassign';
    officer_user_id: number;
    reason: string;
};

type LoanRequestWorkflowReturnToQueuePayload = {
    reason: string;
};

type LoanRequestWorkflowRequestRevisionPayload = {
    remarks: string;
};

type LoanRequestWorkflowRejectPayload = {
    rejection_reason: string;
};

type LoanRequestWorkflowRecommendApprovalPayload = {
    review_remarks?: string | null;
};

type LoanRequestWorkflowApprovePayload = {
    approved_amount: number | string;
    approved_term: number | string;
    approved_interest_rate?: number | string | null;
    approved_payment_frequency?: string | null;
    approval_remarks?: string | null;
};

type LoanRequestWorkflowDeclinePayload = {
    decline_category: string;
    decline_reason: string;
};

type LoanRequestWorkflowProcessingUpdatePayload = {
    reason: string;
    loan_request?: {
        typecode?: string | null;
        requested_amount?: number | string | null;
        requested_term?: number | string | null;
        loan_purpose?: string | null;
        availment_status?: string | null;
    };
    applicant?: Record<string, unknown>;
    co_maker_1?: Record<string, unknown>;
    co_maker_2?: Record<string, unknown>;
    processing?: Record<string, unknown>;
    recommended_amount?: number | string | null;
    recommended_term?: number | string | null;
    recommended_interest_rate?: number | string | null;
    recommended_payment_frequency?: string | null;
};

type LoanRequestRecommendationPreviewPayload = {
    recommended_amount?: number | string | null;
    recommended_term?: number | string | null;
    recommended_interest_rate?: number | string | null;
    recommended_payment_frequency?: string | null;
    service_charge_rate?: number | string | null;
    insurance_rate?: number | string | null;
    insurance_term?: number | string | null;
    loan_security_rate?: number | string | null;
    savings_rate?: number | string | null;
    documentary_stamp_rate?: number | string | null;
    notarial_fee?: number | string | null;
    other_charges_amount?: number | string | null;
    other_charges_description?: string | null;
    penalty_rate_per_month?: number | string | null;
};

type LoanRequestRecommendationPreviewFailureInformation = {
    message: string;
    blockers: string[];
};

type LoanRequestRecommendationPreviewResponse = {
    net_proceeds_raw: number | null;
    suggested_gnthp_raw: number | null;
    failure_information: LoanRequestRecommendationPreviewFailureInformation | null;
};

type LoanRequestRecommendationPreviewResult =
    LoanRequestRecommendationPreviewResponse;

type LoanRequestWorkflowMemberActionPayload = {
    action_type: 'needs_revision' | 'awaiting_member_information';
    message: string;
    reason: string;
    field_keys?: string[];
};

type LoanRequestWorkflowRejectDuringProcessingPayload = {
    rejection_category: string;
    rejection_category_other?: string | null;
    member_visible_reason: string;
};

type LoanRequestWorkflowGenerateDocumentsPayload = {
    document_key?: string | null;
};

type LoanRequestWorkflowReturnForProcessingPayload = {
    reason: string;
};

type LoanRequestWorkflowReopenPayload = {
    reason: string;
    retain_assignment?: boolean;
};

type LoanRequestWorkflowUpgradePayload = {
    reason: string;
};

type LoanRequestWorkflowResponse = LoanRequestWorkflowResult;

type StaffMutationResponse = {
    staff: StaffAccount;
    message?: string;
};

type StaffPasswordResetResponse = {
    staff: StaffAccount;
    temporary_password: string;
    message?: string;
};

type StaffHistoryResponse = {
    items: StaffHistoryEntry[];
};

type PromoteMemberPayload = {
    account_number: string;
    role: EditableStaffRoleName;
    reason: string;
};

export const adminApi = {
    async getDashboardSummary(): Promise<DashboardSummary> {
        const response =
            await client.get<ApiResponse<DashboardSummary>>(
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
        memberId: string | number,
        signal?: AbortSignal,
    ): Promise<MemberDetail> {
        const response = await client.get<
            ApiResponse<{ member: MemberDetail }>
        >(`/spa/admin/members/${memberId}`, { signal });

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
    async grantMemberAdminAccess(
        memberKey: string | number,
    ): Promise<MemberDetail> {
        const response = await client.patch<
            ApiResponse<{ member: MemberDetail }>
        >(`/spa/admin/members/${memberKey}/grant-admin`);

        return unwrap(response).member;
    },
    async revokeMemberAdminAccess(
        memberKey: string | number,
    ): Promise<MemberDetail> {
        const response = await client.patch<
            ApiResponse<{ member: MemberDetail }>
        >(`/spa/admin/members/${memberKey}/revoke-admin`);

        return unwrap(response).member;
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
    async getReportedRequests(
        params: Pick<RequestsQueryParams, 'search' | 'page' | 'perPage'>,
        signal?: AbortSignal,
    ): Promise<ReportedRequestsResponse> {
        const response = await client.get<
            ApiResponse<ReportedRequestsResponse>
        >('/spa/admin/requests/reported', {
            params,
            signal,
        });

        return unwrap(response);
    },
    async approveLoanRequest(
        loanRequestId: number,
        payload: LoanRequestApprovePayload,
    ): Promise<LoanRequestDecisionResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestDecisionResult>
        >(`/spa/admin/requests/${loanRequestId}/approve`, payload);

        return unwrap(response);
    },
    async declineLoanRequest(
        loanRequestId: number,
        payload: LoanRequestDeclinePayload,
    ): Promise<LoanRequestDecisionResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestDecisionResult>
        >(`/spa/admin/requests/${loanRequestId}/decline`, payload);

        return unwrap(response);
    },
    async correctLoanRequest(
        loanRequestId: number,
        payload: LoanRequestCorrectionPayload,
    ): Promise<LoanRequestCorrectionResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestCorrectionResult>
        >(`/spa/admin/requests/${loanRequestId}/corrections`, payload);

        return unwrap(response);
    },
    async cancelLoanRequest(
        loanRequestId: number,
        payload: LoanRequestCancellationPayload,
    ): Promise<LoanRequestCancellationResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestCancellationResult>
        >(`/spa/admin/requests/${loanRequestId}/cancel`, payload);

        return unwrap(response);
    },
    async dismissLoanRequestCorrectionReport(
        loanRequestId: number,
        reportId: number,
        payload: LoanRequestCorrectionReportDismissPayload,
    ): Promise<LoanRequestCorrectionReportDismissResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestCorrectionReportDismissResult>
        >(
            `/spa/admin/requests/${loanRequestId}/correction-reports/${reportId}/dismiss`,
            payload,
        );

        return unwrap(response);
    },
    async createAdminCorrectedCopy(
        loanRequestId: number,
        payload: LoanRequestAdminCorrectedCopyPayload,
    ): Promise<LoanRequestAdminCorrectedCopyResult> {
        const response = await client.post<
            ApiResponse<LoanRequestAdminCorrectedCopyResult>
        >(`/spa/admin/requests/${loanRequestId}/admin-corrected-copy`, payload);

        return unwrap(response);
    },
    async startLoanRequestReview(
        loanRequestId: number,
        payload: LoanRequestWorkflowStartReviewPayload = {},
    ): Promise<LoanRequestWorkflowResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestWorkflowResponse>
        >(workflowStartReviewRoute(loanRequestId).url, payload);

        return unwrap(response);
    },
    async claimLoanRequest(
        loanRequestId: number,
        payload: LoanRequestWorkflowClaimPayload = {},
    ): Promise<LoanRequestWorkflowResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestWorkflowResponse>
        >(workflowClaimRoute(loanRequestId).url, payload);

        return unwrap(response);
    },
    async updateLoanRequestAssignment(
        loanRequestId: number,
        payload: LoanRequestWorkflowAssignmentPayload,
    ): Promise<LoanRequestWorkflowResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestWorkflowResponse>
        >(workflowAssignmentUpdateRoute(loanRequestId).url, payload);

        return unwrap(response);
    },
    async returnLoanRequestToQueue(
        loanRequestId: number,
        payload: LoanRequestWorkflowReturnToQueuePayload,
    ): Promise<LoanRequestWorkflowResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestWorkflowResponse>
        >(workflowReturnToQueueRoute(loanRequestId).url, payload);

        return unwrap(response);
    },
    async requestLoanRequestRevision(
        loanRequestId: number,
        payload: LoanRequestWorkflowRequestRevisionPayload,
    ): Promise<LoanRequestWorkflowResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestWorkflowResponse>
        >(workflowRequestRevisionRoute(loanRequestId).url, payload);

        return unwrap(response);
    },
    async rejectLoanRequestForWorkflow(
        loanRequestId: number,
        payload: LoanRequestWorkflowRejectPayload,
    ): Promise<LoanRequestWorkflowResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestWorkflowResponse>
        >(workflowRejectRoute(loanRequestId).url, payload);

        return unwrap(response);
    },
    async recommendLoanRequestApproval(
        loanRequestId: number,
        payload: LoanRequestWorkflowRecommendApprovalPayload = {},
    ): Promise<LoanRequestWorkflowResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestWorkflowResponse>
        >(workflowRecommendApprovalRoute(loanRequestId).url, payload);

        return unwrap(response);
    },
    async approveLoanRequestForWorkflow(
        loanRequestId: number,
        payload: LoanRequestWorkflowApprovePayload,
    ): Promise<LoanRequestWorkflowResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestWorkflowResponse>
        >(workflowApproveRoute(loanRequestId).url, payload);

        return unwrap(response);
    },
    async declineLoanRequestForWorkflow(
        loanRequestId: number,
        payload: LoanRequestWorkflowDeclinePayload,
    ): Promise<LoanRequestWorkflowResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestWorkflowResponse>
        >(workflowDeclineRoute(loanRequestId).url, payload);

        return unwrap(response);
    },
    async updateLoanRequestProcessingDetails(
        loanRequestId: number,
        payload: LoanRequestWorkflowProcessingUpdatePayload,
    ): Promise<LoanRequestWorkflowResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestWorkflowResponse>
        >(
            `/spa/workflow/loan-requests/${loanRequestId}/processing-details`,
            payload,
        );

        return unwrap(response);
    },
    async previewLoanRequestProcessingDetails(
        loanRequestId: number,
        payload: LoanRequestRecommendationPreviewPayload,
    ): Promise<LoanRequestRecommendationPreviewResult> {
        const response = await client.post<
            ApiResponse<LoanRequestRecommendationPreviewResponse>
        >(
            `/spa/workflow/loan-requests/${loanRequestId}/processing-details/preview`,
            payload,
        );

        return unwrap(response);
    },
    async requestLoanRequestMemberAction(
        loanRequestId: number,
        payload: LoanRequestWorkflowMemberActionPayload,
    ): Promise<LoanRequestWorkflowResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestWorkflowResponse>
        >(
            `/spa/workflow/loan-requests/${loanRequestId}/request-member-action`,
            payload,
        );

        return unwrap(response);
    },
    async rejectLoanRequestDuringProcessing(
        loanRequestId: number,
        payload: LoanRequestWorkflowRejectDuringProcessingPayload,
    ): Promise<LoanRequestWorkflowResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestWorkflowResponse>
        >(
            `/spa/workflow/loan-requests/${loanRequestId}/reject-during-processing`,
            payload,
        );

        return unwrap(response);
    },
    async generateLoanRequestDocuments(
        loanRequestId: number,
        payload: LoanRequestWorkflowGenerateDocumentsPayload = {},
    ): Promise<LoanRequestWorkflowResult> {
        const response = await client.post<
            ApiResponse<LoanRequestWorkflowResponse>
        >(
            `/spa/workflow/loan-requests/${loanRequestId}/documents/generate`,
            payload,
        );

        return unwrap(response);
    },
    async returnLoanRequestForProcessing(
        loanRequestId: number,
        payload: LoanRequestWorkflowReturnForProcessingPayload,
    ): Promise<LoanRequestWorkflowResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestWorkflowResponse>
        >(
            `/spa/workflow/loan-requests/${loanRequestId}/return-for-processing`,
            payload,
        );

        return unwrap(response);
    },
    async reopenLoanRequestForProcessing(
        loanRequestId: number,
        payload: LoanRequestWorkflowReopenPayload,
    ): Promise<LoanRequestWorkflowResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestWorkflowResponse>
        >(`/spa/workflow/loan-requests/${loanRequestId}/reopen`, payload);

        return unwrap(response);
    },
    async upgradeLoanRequestWorkflow(
        loanRequestId: number,
        payload: LoanRequestWorkflowUpgradePayload,
    ): Promise<LoanRequestWorkflowResult> {
        const response = await client.patch<
            ApiResponse<LoanRequestWorkflowResponse>
        >(
            `/spa/workflow/loan-requests/${loanRequestId}/upgrade-workflow`,
            payload,
        );

        return unwrap(response);
    },
    async getMemberAccountsSummary(
        memberKey: string | number,
        signal?: AbortSignal,
    ): Promise<MemberAccountsSummary> {
        const response = await client.get<
            ApiResponse<{ summary: MemberAccountsSummary }>
        >(`/admin/api/members/${memberKey}/accounts/summary`, { signal });

        return unwrap(response).summary;
    },
    async getMemberAccountActions(
        memberKey: string | number,
        params: MemberAccountQueryParams,
        signal?: AbortSignal,
    ): Promise<MemberAccountActionsResponse> {
        const response = await client.get<
            ApiResponse<MemberAccountActionsResponse>
        >(`/admin/api/members/${memberKey}/accounts/actions`, {
            params,
            signal,
        });

        return unwrap(response);
    },
    async getMemberLoans(
        memberKey: string | number,
        params: MemberAccountQueryParams,
        signal?: AbortSignal,
    ): Promise<MemberLoansResponse> {
        const response = await client.get<ApiResponse<MemberLoansResponse>>(
            `/admin/api/members/${memberKey}/accounts/loans`,
            { params, signal },
        );

        return unwrap(response);
    },
    async getMemberSavings(
        memberKey: string | number,
        params: MemberAccountQueryParams,
        signal?: AbortSignal,
    ): Promise<MemberLoanSecurityLedgerResponse> {
        const response = await client.get<
            ApiResponse<MemberLoanSecurityLedgerResponse>
        >(`/admin/api/members/${memberKey}/accounts/savings`, {
            params,
            signal,
        });

        return unwrap(response);
    },
    async getMemberLoanSchedule(
        memberKey: string | number,
        loanNumber: string | number,
        signal?: AbortSignal,
    ): Promise<MemberLoanScheduleResponse> {
        const response = await client.get<
            ApiResponse<MemberLoanScheduleResponse>
        >(`/admin/api/members/${memberKey}/loans/${loanNumber}/schedule`, {
            signal,
        });

        return unwrap(response);
    },
    async getMemberLoanPayments(
        memberKey: string | number,
        loanNumber: string | number,
        params: MemberLoanPaymentsQueryParams,
        signal?: AbortSignal,
    ): Promise<MemberLoanPaymentsResponse> {
        const response = await client.get<
            ApiResponse<MemberLoanPaymentsResponse>
        >(`/admin/api/members/${memberKey}/loans/${loanNumber}/payments`, {
            params,
            signal,
        });

        return unwrap(response);
    },
    async getStaff(
        params: StaffDirectoryQueryParams,
        signal?: AbortSignal,
    ): Promise<StaffDirectoryResponse> {
        const response = await client.get<ApiResponse<StaffDirectoryResponse>>(
            superadminStaffIndexRoute().url,
            {
                params,
                signal,
            },
        );

        return unwrap(response);
    },
    async createStaff(
        payload: CreateStaffPayload,
    ): Promise<StaffMutationResponse> {
        const response = await client.post<ApiResponse<StaffMutationResponse>>(
            superadminStaffStoreRoute().url,
            payload,
        );

        return unwrap(response);
    },
    async updateStaffRole(
        userId: number,
        payload: UpdateStaffRolePayload,
    ): Promise<StaffAccount> {
        const response = await client.patch<ApiResponse<StaffMutationResponse>>(
            superadminStaffRoleUpdateRoute(userId).url,
            payload,
        );

        return unwrap(response).staff;
    },
    async suspendStaff(
        userId: number,
        payload: StaffAccessPayload,
    ): Promise<StaffAccount> {
        const response = await client.patch<ApiResponse<StaffMutationResponse>>(
            superadminStaffSuspendRoute(userId).url,
            payload,
        );

        return unwrap(response).staff;
    },
    async resetStaffPassword(
        userId: number,
        payload: StaffAccessPayload,
    ): Promise<StaffPasswordResetResponse> {
        const response = await client.patch<
            ApiResponse<StaffPasswordResetResponse>
        >(superadminStaffResetPasswordRoute(userId).url, payload);

        return unwrap(response);
    },
    async reactivateStaff(
        userId: number,
        payload: StaffAccessPayload,
    ): Promise<StaffAccount> {
        const response = await client.patch<ApiResponse<StaffMutationResponse>>(
            superadminStaffReactivateRoute(userId).url,
            payload,
        );

        return unwrap(response).staff;
    },
    async getStaffHistory(
        userId: number,
        limit = 50,
        signal?: AbortSignal,
    ): Promise<StaffHistoryEntry[]> {
        const response = await client.get<ApiResponse<StaffHistoryResponse>>(
            superadminStaffHistoryRoute(userId).url,
            {
                params: { limit },
                signal,
            },
        );

        return unwrap(response).items;
    },
    async logLoanRequestWarningViewed(
        loanRequestId: number,
    ): Promise<{ logged: boolean }> {
        const response = await client.post<ApiResponse<{ logged: boolean }>>(
            `/staff/loan-requests/${loanRequestId}/log-warning-viewed`,
            {},
        );

        return unwrap(response);
    },
    async lookupMemberForPromotion(
        accountNumber: string,
        signal?: AbortSignal,
    ): Promise<StaffAccount> {
        const response = await client.get<
            ApiResponse<{ member: StaffAccount }>
        >('/spa/superadmin/staff/member-lookup', {
            params: { account_number: accountNumber },
            signal,
        });

        return unwrap(response).member;
    },
    async searchMembers(
        query: string,
        signal?: AbortSignal,
    ): Promise<StaffAccount[]> {
        const params = query !== '' ? { query } : {};
        const response = await client.get<
            ApiResponse<{ members: StaffAccount[] }>
        >('/spa/superadmin/staff/search-members', { params, signal });

        return unwrap(response).members;
    },
    async promoteMember(
        payload: PromoteMemberPayload,
    ): Promise<StaffMutationResponse> {
        const response = await client.post<ApiResponse<StaffMutationResponse>>(
            '/spa/superadmin/staff/promote',
            payload,
        );

        return unwrap(response);
    },
};
