import type { MemberLoanPayment, MemberLoanScheduleEntry } from '@/types/admin';

export type PaymentStatusPill = 'paid' | 'due' | 'overdue' | 'upcoming';

export type PaymentScheduleRow = {
    key: string;
    installment: number;
    date: string | null;
    amount: number;
    interest: number | null;
    balance: number | null;
    status: PaymentStatusPill;
};

export type PaymentAmountFilter = {
    min: number | null;
    max: number | null;
};

const todayIsoDate = (): string => {
    const now = new Date();

    return [
        now.getFullYear(),
        String(now.getMonth() + 1).padStart(2, '0'),
        String(now.getDate()).padStart(2, '0'),
    ].join('-');
};

const toIsoDay = (value?: string | null): string | null => {
    if (!value) {
        return null;
    }

    const day = value.slice(0, 10);

    return day === '' ? null : day;
};

export const paymentReference = (payment: MemberLoanPayment): string => {
    const reference =
        payment.reference_no ?? payment.control_no ?? payment.transaction_no;

    if (reference === null || reference === undefined) {
        return '--';
    }

    const trimmed = String(reference).trim();

    return trimmed === '' ? '--' : trimmed;
};

export const paymentAmount = (payment: MemberLoanPayment): number =>
    payment.payment_amount ?? 0;

export const paymentPrincipal = (payment: MemberLoanPayment): number | null =>
    payment.principal ?? null;

export const paymentInterest = (payment: MemberLoanPayment): number | null =>
    payment.accrued_interest ?? null;

const formatSplitLine = (value: number | null): string =>
    value === null ? '—' : `₱${value.toFixed(2)}`;

export const paymentStatusLabel = (payment: MemberLoanPayment): string => {
    const status = payment.status?.trim();

    return status === undefined || status === '' ? 'Posted' : status;
};

export const filterPaymentsByAmount = (
    payments: readonly MemberLoanPayment[],
    filter: PaymentAmountFilter,
): MemberLoanPayment[] =>
    payments.filter((payment) => {
        const amount = paymentAmount(payment);

        if (filter.min !== null && amount < filter.min) {
            return false;
        }

        if (filter.max !== null && amount > filter.max) {
            return false;
        }

        return true;
    });

export const paymentsCsv = (payments: readonly MemberLoanPayment[]): string => {
    const header = [
        'Transaction date',
        'Reference no',
        'Principal',
        'Interest',
        'Amount',
        'Balance',
    ].join(',');

    const rows = payments.map((payment) =>
        [
            payment.date_in ?? '',
            paymentReference(payment),
            paymentPrincipal(payment)?.toFixed(2) ?? '',
            paymentInterest(payment)?.toFixed(2) ?? '',
            paymentAmount(payment).toFixed(2),
            (payment.balance ?? 0).toFixed(2),
        ].join(','),
    );

    return [header, ...rows].join('\n');
};

export const receiptText = (
    payment: MemberLoanPayment,
    loan: { lnnumber: string | number | null; lntype: string | null },
    memberName: string,
): string => {
    const lines = [
        'MRDINC Portal — Payment Receipt',
        '',
        `Reference no: ${paymentReference(payment)}`,
        `Transaction date: ${payment.date_in ?? '--'}`,
        `Loan: ${loan.lnnumber ?? '--'} · ${loan.lntype ?? 'Loan'}`,
        `Member: ${memberName}`,
        '',
        `Amount paid: ₱${paymentAmount(payment).toFixed(2)}`,
        `Principal: ${formatSplitLine(paymentPrincipal(payment))}`,
        `Interest: ${formatSplitLine(paymentInterest(payment))}`,
        `Balance after payment: ₱${(payment.balance ?? 0).toFixed(2)}`,
        '',
        'Generated from the MRDINC member portal.',
    ];

    return lines.join('\n');
};

export const receiptFileName = (
    loanNumber: string | number | null,
    payment: MemberLoanPayment,
): string =>
    `receipt-${String(loanNumber ?? 'loan')}-${paymentReference(payment)}.txt`;

export const downloadTextFile = (
    fileName: string,
    contents: string,
    type = 'text/csv;charset=utf-8;',
): void => {
    const blob = new Blob([contents], { type });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = fileName;
    document.body.append(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
};

const sortedSchedule = (
    schedule: readonly MemberLoanScheduleEntry[],
): MemberLoanScheduleEntry[] =>
    [...schedule]
        .filter((entry) => toIsoDay(entry.date_pay) !== null)
        .sort((left, right) =>
            (toIsoDay(left.date_pay) ?? '').localeCompare(
                toIsoDay(right.date_pay) ?? '',
            ),
        );

/**
 * The schedule row where the loan stops being fully settled. Schedule Balance
 * is the remainder AFTER the installment, so rows whose balance sits below
 * the current balance are still owed; rows before that point are covered.
 */
const firstUnpaidIndex = (
    schedule: readonly MemberLoanScheduleEntry[],
    balance: number,
): number => {
    if (balance <= 0) {
        return schedule.length;
    }

    const index = schedule.findIndex(
        (entry) => (entry.balance ?? 0) < balance - 0.005,
    );

    return index === -1 ? schedule.length : index;
};

export const paymentScheduleRows = (
    schedule: readonly MemberLoanScheduleEntry[],
    balance: number | null,
): PaymentScheduleRow[] => {
    const entries = sortedSchedule(schedule);

    if (entries.length === 0) {
        return [];
    }

    const unpaidStart = firstUnpaidIndex(entries, balance ?? 0);
    const today = todayIsoDate();
    let dueAssigned = false;

    return entries.map((entry, index) => {
        const date = toIsoDay(entry.date_pay);
        let status: PaymentStatusPill;

        if (index < unpaidStart) {
            status = 'paid';
        } else if (date !== null && date < today) {
            status = 'overdue';
        } else if (!dueAssigned) {
            status = 'due';
            dueAssigned = true;
        } else {
            status = 'upcoming';
        }

        return {
            key: `${entry.control_no ?? 'row'}-${date ?? index}`,
            installment: index + 1,
            date,
            amount: entry.amortization ?? 0,
            interest: entry.interest ?? null,
            balance: entry.balance ?? null,
            status,
        };
    });
};

export const scheduleTerm = (
    schedule: readonly MemberLoanScheduleEntry[],
): number => schedule.length;

export const scheduleMonthlyDue = (
    rows: readonly PaymentScheduleRow[],
): number => {
    const unpaid = rows.find((row) => row.status !== 'paid');

    return unpaid?.amount ?? rows.at(-1)?.amount ?? 0;
};

export const schedulePaymentsLeft = (
    rows: readonly PaymentScheduleRow[],
): number => {
    const term = rows.length;

    if (term === 0) {
        return 0;
    }

    return Math.min(term, rows.filter((row) => row.status !== 'paid').length);
};

export const scheduleStatusLabel = (status: PaymentStatusPill): string => {
    if (status === 'paid') {
        return 'Paid';
    }

    if (status === 'due') {
        return 'Due';
    }

    if (status === 'overdue') {
        return 'Overdue';
    }

    return 'Scheduled';
};

export const scheduleStatusClasses = (status: PaymentStatusPill): string => {
    if (status === 'paid') {
        return 'border-primary/30 bg-primary/10 text-primary';
    }

    if (status === 'due') {
        return 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100';
    }

    if (status === 'overdue') {
        return 'border-destructive/40 bg-destructive/10 text-destructive';
    }

    return 'border-border bg-muted text-muted-foreground';
};
