import type { PaginatedResponse } from '@/types/pagination';

export type MemberLoan = {
    lnnumber: string | number | null;
    lntype: string | null;
    principal: number | null;
    balance: number | null;
    lastmove: string | null;
    initial: number | null;
};

export type MemberLoanSecurity = {
    svnumber: string | number | null;
    svtype: string | null;
    mortuary: number | null;
    balance: number | null;
    wbalance: number | null;
    lastmove: string | null;
};

export type MemberLoanSecurityLedgerEntry = {
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
    currentLoanSecurityBalance: number;
    currentLoanSecurityTotal: number;
    lastLoanTransactionDate: string | null;
    lastLoanSecurityTransactionDate: string | null;
    recentLoans: MemberLoan[];
    recentLoanSecurity: MemberLoanSecurity[];
};

export type MemberAccountActionsResponse =
    PaginatedResponse<MemberRecentAccountAction>;

export type MemberLoansResponse = PaginatedResponse<MemberLoan>;

export type MemberLoanSecurityLedgerResponse =
    PaginatedResponse<MemberLoanSecurityLedgerEntry>;
