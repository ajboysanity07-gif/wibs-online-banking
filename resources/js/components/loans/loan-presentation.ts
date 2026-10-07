import { formatCurrency } from '@/lib/formatters';
import type { MemberLoan, MemberRecentLoanPayment } from '@/types/admin';

const DAY_IN_MS = 86_400_000;

const toIsoDate = (value: string | null): string | null => {
    if (!value) {
        return null;
    }

    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime())
        ? null
        : parsed.toISOString().slice(0, 10);
};

export const todayIso = (): string => new Date().toISOString().slice(0, 10);

export const isLoanActive = (loan: Pick<MemberLoan, 'balance'>): boolean =>
    (loan.balance ?? 0) > 0;

export const isLoanOverdue = (loan: Pick<MemberLoan, 'dueDate'>): boolean => {
    const dueDate = toIsoDate(loan.dueDate);

    return dueDate !== null && dueDate < todayIso();
};

/** Percentage of the original principal already repaid, clamped to 0-100. */
export const loanRepaidPercent = (
    loan: Pick<MemberLoan, 'principal' | 'balance'>,
): number => {
    const principal = loan.principal ?? 0;

    if (principal <= 0) {
        return 0;
    }

    const repaid = Math.max(0, principal - (loan.balance ?? 0));

    return Math.min(100, Math.round((repaid / principal) * 100));
};

export const loanRepaidAmount = (
    loan: Pick<MemberLoan, 'principal' | 'balance'>,
): number => Math.max(0, (loan.principal ?? 0) - (loan.balance ?? 0));

/** The loan with the earliest upcoming (or overdue) due date among active loans. */
export const nextLoanDue = <T extends Pick<MemberLoan, 'dueDate' | 'balance'>>(
    loans: readonly T[],
): T | null => {
    let next: T | null = null;
    let nextDate = '';

    for (const loan of loans) {
        if (!isLoanActive(loan)) {
            continue;
        }

        const dueDate = toIsoDate(loan.dueDate);

        if (dueDate === null) {
            continue;
        }

        if (next === null || dueDate < nextDate) {
            next = loan;
            nextDate = dueDate;
        }
    }

    return next;
};

export const countActiveLoans = (loans: readonly MemberLoan[]): number =>
    loans.filter(isLoanActive).length;

/** Builds the next three installment rows shown by the collapsible schedule. */
export const loanScheduleRows = (
    loan: Pick<MemberLoan, 'dueDate' | 'balance' | 'monthlyDue'>,
    limit = 3,
): Array<{ date: string; amount: number; overdue: boolean }> => {
    const dueDate = toIsoDate(loan.dueDate);
    const monthlyDue = loan.monthlyDue ?? 0;

    if (!isLoanActive(loan) || dueDate === null || monthlyDue <= 0) {
        return [];
    }

    const rows: Array<{ date: string; amount: number; overdue: boolean }> = [];
    const cursor = new Date(`${dueDate}T00:00:00`);
    const today = todayIso();

    for (let index = 0; index < limit; index += 1) {
        const iso = [
            cursor.getFullYear(),
            String(cursor.getMonth() + 1).padStart(2, '0'),
            String(cursor.getDate()).padStart(2, '0'),
        ].join('-');

        rows.push({ date: iso, amount: monthlyDue, overdue: iso < today });

        cursor.setMonth(cursor.getMonth() + 1);
    }

    return rows;
};

export const formatShortDate = (value: string | null): string => {
    if (!value) {
        return '--';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return '--';
    }

    return parsed.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

export const daysBetween = (isoDate: string, today: string): number =>
    Math.round(
        (new Date(`${isoDate}T00:00:00`).getTime() -
            new Date(`${today}T00:00:00`).getTime()) /
            DAY_IN_MS,
    );

export const loanTypeLabel = (loanType: string | null): string =>
    loanType && loanType.trim() !== '' ? loanType : 'Loan';

export const paymentLoanLabel = (
    payment: Pick<MemberRecentLoanPayment, 'lnnumber' | 'lntype'>,
): string => {
    const number =
        payment.lnnumber && payment.lnnumber !== '' ? payment.lnnumber : '--';

    return `${number} · ${loanTypeLabel(payment.lntype)}`;
};

export const formatLoanNumber = (
    loanNumber: string | number | null,
): string => {
    if (loanNumber === null || loanNumber === undefined) {
        return '--';
    }

    const trimmed = String(loanNumber).trim();

    return trimmed === '' ? '--' : trimmed;
};

export const outstandingBalance = (loans: readonly MemberLoan[]): number =>
    loans.reduce((total, loan) => total + (loan.balance ?? 0), 0);

export const currencyOrDash = (value: number | null | undefined): string =>
    value === null || value === undefined ? '—' : formatCurrency(value);

/** 'YYYY-MM' → 'September 2026'; passes anything else through untouched. */ export const formatMonthLabel =
    (month: string): string => {
        const match = /^(\d{4})-(\d{2})$/.exec(month.trim());

        if (!match) {
            return month;
        }

        const date = new Date(Number(match[1]), Number(match[2]) - 1, 1);

        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
        });
    };

/** Monthly interest rate label ('2.5% per month'); dash when unknown. */
export const formatRateLabel = (rate: number | null | undefined): string => {
    if (rate === null || rate === undefined || Number.isNaN(rate)) {
        return '—';
    }

    const trimmed = String(Math.round(rate * 100) / 100);

    return `${trimmed}% per month`;
};

/** Compact monthly rate for loan-card figs ('2.5%/mo'); dash when unknown. */
export const formatRateShort = (rate: number | null | undefined): string => {
    if (rate === null || rate === undefined || Number.isNaN(rate)) {
        return '—';
    }

    const trimmed = String(Math.round(rate * 100) / 100);

    return `${trimmed}%/mo`;
};

/**
 * Loan term in months. Prefers the core-banking term when it looks like a
 * month count; the legacy column sometimes holds days, so absurd values are
 * ignored rather than displayed.
 */
export const loanTermMonths = (
    loan: Pick<MemberLoan, 'termMonths'>,
): number | null => {
    const term = loan.termMonths ?? null;

    if (term === null || !Number.isInteger(term) || term < 1 || term > 360) {
        return null;
    }

    return term;
};

/**
 * Installments left: ceil(balance / monthlyDue) capped by the term, or 0
 * without a monthly due — mirrors the reference card math.
 */
export const loanPaymentsLeft = (
    loan: Pick<MemberLoan, 'balance' | 'monthlyDue' | 'termMonths'>,
): number => {
    const monthlyDue = loan.monthlyDue ?? 0;

    if (!isLoanActive(loan) || monthlyDue <= 0) {
        return 0;
    }

    const remaining = Math.ceil((loan.balance ?? 0) / monthlyDue);
    const term = loanTermMonths(loan);

    return term === null
        ? Math.max(0, remaining)
        : Math.min(term, Math.max(0, remaining));
};
