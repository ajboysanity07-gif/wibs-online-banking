import type { PaginatedResponse, PaginationMeta } from './pagination';
import type { LoanRequestStatusValue } from './loan-requests';

export type { PaginatedResponse, PaginationMeta } from './pagination';
export type {
    MemberAccountActionsResponse,
    MemberAccountsSummary,
    MemberLoan,
    MemberLoanSecurity,
    MemberLoanSecurityLedgerEntry,
    MemberLoanSecurityLedgerResponse,
    MemberLoansResponse,
    MemberRecentAccountAction,
    MemberRecentAccountActionSource,
} from '@/features/member-accounts/types';

export type AdminMetrics = {
    registeredCount: number;
    unregisteredCount: number;
    totalCount: number;
    requestsCount: number | null;
    lastSync: string | null;
};

export type MemberStatusValue = 'active' | 'suspended';

export type MemberRegistrationStatus = 'registered' | 'unregistered';

export type MemberRegistrationFilter = MemberRegistrationStatus | 'all';

export type MemberSort = 'newest' | 'oldest';

export type AdminAccessLevel = 'member' | 'admin' | 'superadmin';

export type RequestPreview = {
    id: number | null;
    member_name: string | null;
    status: LoanRequestStatusValue | null;
    created_at: string | null;
    summary: string | null;
    loan_type: string | null;
    requested_amount: number | string | null;
    submitted_at: string | null;
};

export type DashboardSummary = {
    metrics: AdminMetrics;
    requests: RequestPreview[];
};

export type MemberLoanSummary = {
    balance: number;
    next_payment_date: string | null;
    last_payment_date: string | null;
};

export type MemberLoanScheduleEntry = {
    lnnumber: string | number | null;
    date_pay: string | null;
    amortization: number | null;
    interest: number | null;
    balance: number | null;
    control_no: string | number | null;
};

export type MemberLoanScheduleResponse = {
    items: MemberLoanScheduleEntry[];
};

export type MemberLoanPayment = {
    date_in: string | null;
    reference_no: string | null;
    loan_type: string | null;
    principal: number | null;
    payment_amount: number | null;
    debit: number | null;
    credit: number | null;
    balance: number | null;
    accrued_interest: number | null;
    status: string | null;
    remarks: string | null;
    control_no: string | number | null;
    transaction_no: string | number | null;
};

export type MemberLoanPaymentsFilters = {
    range: 'current_month' | 'current_year' | 'last_30_days' | 'all' | 'custom';
    start: string | null;
    end: string | null;
};

export type MemberLoanPaymentsResponse =
    PaginatedResponse<MemberLoanPayment> & {
        filters: MemberLoanPaymentsFilters;
        openingBalance?: number | null;
        closingBalance?: number | null;
    };

export type MemberSummary = {
    member_id: string;
    user_id: number | null;
    member_name: string;
    username: string | null;
    email: string | null;
    acctno: string | null;
    registration_status: MemberRegistrationStatus;
    portal_status: MemberStatusValue | null;
    created_at: string | null;
    reviewed_at: string | null;
};

export type MemberDetail = {
    member_id: string;
    user_id: number | null;
    member_name?: string | null;
    username: string | null;
    email: string | null;
    phoneno: string | null;
    acctno: string | null;
    registration_status: MemberRegistrationStatus;
    portal_status: MemberStatusValue | null;
    is_admin: boolean;
    is_superadmin: boolean;
    admin_access_level: AdminAccessLevel | null;
    created_at: string | null;
    avatar_url: string | null;
};

export type MembersMeta = PaginationMeta & {
    registration: MemberRegistrationStatus | null;
    sort: MemberSort;
};

export type MembersResponse = {
    items: MemberSummary[];
    meta: MembersMeta;
};

export type RequestsResponse = {
    items: RequestPreview[];
    meta: PaginationMeta & {
        query: string | null;
        available: boolean;
        message: string | null;
        loanTypes: string[];
    };
};

export type MemberStatusAction = 'suspend' | 'reactivate';

export type MemberAdminAccessAction = 'grant' | 'revoke';
