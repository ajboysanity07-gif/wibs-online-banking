export type AdminMetrics = {
    pendingCount: number;
    activeCount: number;
    totalCount: number;
    requestsCount: number | null;
    lastSync: string | null;
};

export type MemberStatusValue = 'pending' | 'active' | 'suspended';

export type MemberStatusFilter = MemberStatusValue | 'all';

export type MemberSort = 'newest' | 'oldest';

export type PendingApprovalRow = {
    user_id: number;
    member_name: string;
    username: string;
    email: string;
    acctno: string | null;
    created_at: string | null;
    status: MemberStatusValue | null;
};

export type PendingApprovalPreview = PendingApprovalRow;

export type RequestPreview = {
    id: number | null;
    member_name: string | null;
    status: string | null;
    created_at: string | null;
    summary: string | null;
};

export type DashboardSummary = {
    metrics: AdminMetrics;
    pendingApprovals: PendingApprovalPreview[];
    requests: RequestPreview[];
};

export type PaginationMeta = {
    page: number;
    perPage: number;
    total: number;
    lastPage: number;
};

export type PaginatedResponse<T> = {
    items: T[];
    meta: PaginationMeta;
};

export type MemberLoan = {
    lnnumber: string | number | null;
    lntype: string | null;
    principal: number | null;
    balance: number | null;
    lastmove: string | null;
    initial: number | null;
};

export type MemberSavings = {
    svnumber: string | number | null;
    svtype: string | null;
    mortuary: number | null;
    balance: number | null;
    wbalance: number | null;
    lastmove: string | null;
};

export type MemberSavingsLedgerEntry = {
    svnumber: string | number | null;
    svtype: string | null;
    date_in: string | null;
    deposit: number | null;
    withdrawal: number | null;
    balance: number | null;
};

export type MemberRecentAccountActionSource = 'LOAN' | 'SAV';

export type MemberRecentAccountAction = {
    acctno: string | null;
    ln_sv_number: string | null;
    date_in: string | null;
    transaction_type: string | null;
    amount: number | null;
    movement: number | null;
    balance: number | null;
    source: MemberRecentAccountActionSource | null;
    principal: number | null;
    deposit: number | null;
    withdrawal: number | null;
    payments: number | null;
    debit: number | null;
};

export type MemberAccountsSummary = {
    loanBalanceLeft: number;
    currentPersonalSavings: number;
    currentSavingsBalance: number;
    lastLoanTransactionDate: string | null;
    lastSavingsTransactionDate: string | null;
    recentLoans: MemberLoan[];
    recentSavings: MemberSavings[];
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

export type MemberLoanPaymentsResponse = PaginatedResponse<MemberLoanPayment> & {
    filters: MemberLoanPaymentsFilters;
    openingBalance?: number | null;
    closingBalance?: number | null;
};

export type PendingApprovalsResponse = {
    rows: PendingApprovalRow[];
    meta: PaginationMeta;
};

export type MemberSummary = {
    user_id: number;
    member_name: string;
    username: string;
    email: string;
    acctno: string | null;
    status: MemberStatusValue | null;
    created_at: string | null;
    reviewed_at: string | null;
};

export type MemberReviewedBy = {
    user_id: number;
    name: string;
};

export type MemberDetail = {
    user_id: number;
    member_name?: string | null;
    username: string;
    email: string;
    phoneno: string | null;
    acctno: string | null;
    status: MemberStatusValue | null;
    created_at: string | null;
    reviewed_at: string | null;
    reviewed_by: MemberReviewedBy | null;
    avatar_url: string | null;
};

export type MembersMeta = PaginationMeta & {
    status: MemberStatusValue | null;
    sort: MemberSort;
};

export type MembersResponse = {
    items: MemberSummary[];
    meta: MembersMeta;
};

export type MemberAccountActionsResponse =
    PaginatedResponse<MemberRecentAccountAction>;

export type MemberLoansResponse = PaginatedResponse<MemberLoan>;

export type MemberSavingsLedgerResponse =
    PaginatedResponse<MemberSavingsLedgerEntry>;

export type RequestsResponse = {
    items: RequestPreview[];
    meta: PaginationMeta & {
        query: string | null;
        available: boolean;
        message: string | null;
    };
};

export type MemberStatusAction = 'approve' | 'suspend' | 'reactivate';
