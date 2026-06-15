import type { LoanRequestStatusValue } from './loan-requests';
import type {
    LoanRequestAssignmentOfficerOption,
    LoanRequestAssignmentState,
} from './loan-requests';
import type { PaginatedResponse, PaginationMeta } from './pagination';

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
    reference: string | null;
    member_name: string | null;
    member_acctno: string | null;
    status: LoanRequestStatusValue | null;
    created_at: string | null;
    summary: string | null;
    loan_type: string | null;
    requested_amount: number | string | null;
    approved_amount: number | string | null;
    reviewed_at: string | null;
    submitted_at: string | null;
    assigned_officer: {
        user_id: number;
        name: string;
        display_code: string;
    } | null;
    assignment_state: LoanRequestAssignmentState | null;
    can_claim: boolean;
    can_assign: boolean;
    can_reassign: boolean;
    can_return_to_queue: boolean;
    has_open_correction_report: boolean;
    latest_correction_report_id: number | null;
    latest_correction_report_reported_at: string | null;
    latest_correction_report_issue: string | null;
    latest_correction_report_correct_information: string | null;
    latest_correction_report_supporting_note: string | null;
    latest_correction_report_reported_by: {
        user_id: number;
        name: string;
        acctno: string | null;
    } | null;
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
        openCorrectionReports: number;
        assignmentFilters?: Array<{
            value: 'unassigned' | 'mine' | 'all';
            label: string;
        }>;
        assignmentOfficers?: LoanRequestAssignmentOfficerOption[];
    };
};

export type ReportedRequestsResponse = {
    items: RequestPreview[];
    meta: PaginationMeta & {
        query: string | null;
        available: boolean;
        message: string | null;
        openCorrectionReports: number;
    };
};

export type MemberStatusAction = 'suspend' | 'reactivate';

export type MemberAdminAccessAction = 'grant' | 'revoke';

export type EditableStaffRoleName =
    | 'superadmin'
    | 'loan_officer'
    | 'loan_manager';

export type StaffRoleName = EditableStaffRoleName | 'member';

export type StaffAccessStatus = 'active' | 'suspended' | 'not_staff';

export type StaffRoleBadge = {
    name: StaffRoleName;
    label: string;
    editable: boolean;
};

export type StaffChangeSummary = {
    action: string;
    action_label: string;
    created_at: string | null;
    actor_name: string | null;
    actor_display_code: string | null;
};

export type StaffAccount = {
    user_id: number;
    display_code: string;
    display_name: string;
    username: string | null;
    email: string | null;
    phoneno: string | null;
    acctno: string | null;
    has_member_access: boolean;
    roles: StaffRoleBadge[];
    staff_access_status: StaffAccessStatus;
    last_change: StaffChangeSummary | null;
    created_at: string | null;
};

export type StaffHistoryActor = {
    display_code: string;
    name: string;
};

export type StaffHistoryRole = {
    name: StaffRoleName;
    label: string;
};

export type StaffHistoryEntry = {
    id: number;
    action: string;
    action_label: string;
    role_name: StaffRoleName | null;
    role_label: string | null;
    before_roles: StaffHistoryRole[];
    after_roles: StaffHistoryRole[];
    before_staff_status: StaffAccessStatus | null;
    after_staff_status: StaffAccessStatus | null;
    reason: string;
    metadata: Record<string, unknown> | null;
    created_at: string | null;
    actor: StaffHistoryActor | null;
};

export type StaffDirectoryMeta = PaginationMeta & {
    search: string | null;
    role: EditableStaffRoleName | null;
    access: Extract<StaffAccessStatus, 'active' | 'suspended'> | null;
};

export type StaffDirectoryResponse = {
    items: StaffAccount[];
    meta: StaffDirectoryMeta;
};
