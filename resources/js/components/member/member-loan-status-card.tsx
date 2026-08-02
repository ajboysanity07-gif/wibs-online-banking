import { AlertTriangle, Landmark } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatCurrency, formatDate } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { LoanStatusSummaryForMember, ProblemLoan } from '@/types/loan-requests';

type MemberLoanStatusCardProps = {
    loanSummary: LoanStatusSummaryForMember;
    className?: string;
};

const loanStatusBadgeVariant = (loan: ProblemLoan) => {
    if (loan.lnstatus === 'IIL') {
        return 'destructive';
    }

    if (loan.lnstatus === 'PDL') {
        return 'secondary';
    }

    return 'default';
};

export function MemberLoanStatusCard({
    loanSummary,
    className,
}: MemberLoanStatusCardProps) {
    const hasLoans = loanSummary.total_loans > 0;
    const hasConcerns =
        loanSummary.past_due_count > 0 || loanSummary.litigation_count > 0;

    return (
        <Card
            className={cn(
                'rounded-2xl border-border/40 bg-card/70 shadow-sm',
                className,
            )}
        >
            <CardHeader className="space-y-2 pb-4">
                <CardTitle className="flex items-center gap-2 text-lg">
                    <Landmark className="size-4 text-muted-foreground" />
                    Your Loans
                </CardTitle>
                <CardDescription>
                    Current status of your loans on record.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col gap-4">
                <div className="flex items-center justify-between rounded-lg border border-border/30 bg-muted/20 px-3 py-2">
                    <span className="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                        Total balance
                    </span>
                    <span className="text-sm font-semibold">
                        {formatCurrency(loanSummary.total_balance)}
                    </span>
                </div>

                {!hasLoans ? (
                    <p className="text-sm text-muted-foreground">
                        You currently have no active loans.
                    </p>
                ) : (
                    <>
                        <div className="grid grid-cols-3 gap-2">
                            <div className="rounded-xl border border-border/30 bg-muted/10 p-3 text-center">
                                <p className="text-2xl font-semibold">
                                    {loanSummary.active_count}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Active
                                </p>
                                <p className="mt-1 text-[11px] text-muted-foreground">
                                    {formatCurrency(loanSummary.active_balance)}
                                </p>
                            </div>
                            <div
                                className={cn(
                                    'rounded-xl border p-3 text-center',
                                    loanSummary.past_due_count > 0
                                        ? 'border-orange-500/40 bg-orange-500/10 text-orange-700 dark:text-orange-200'
                                        : 'border-border/30 bg-muted/10',
                                )}
                            >
                                <p className="text-2xl font-semibold">
                                    {loanSummary.past_due_count}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Past Due
                                </p>
                                <p className="mt-1 text-[11px] text-muted-foreground">
                                    {formatCurrency(loanSummary.past_due_balance)}
                                </p>
                            </div>
                            <div
                                className={cn(
                                    'rounded-xl border p-3 text-center',
                                    loanSummary.litigation_count > 0
                                        ? 'border-rose-500/40 bg-rose-500/10 text-rose-700 dark:text-rose-200'
                                        : 'border-border/30 bg-muted/10',
                                )}
                            >
                                <p className="text-2xl font-semibold">
                                    {loanSummary.litigation_count}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    In Litigation
                                </p>
                                <p className="mt-1 text-[11px] text-muted-foreground">
                                    {formatCurrency(loanSummary.litigation_balance)}
                                </p>
                            </div>
                        </div>

                        <ul className="space-y-2">
                            {loanSummary.loans.map((loan) => (
                                <li
                                    key={loan.lnnumber}
                                    className="rounded-xl border border-border/40 bg-muted/10 p-3"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div className="space-y-0.5">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="text-sm font-semibold">
                                                    {loan.lnnumber}
                                                </p>
                                                <Badge
                                                    variant={loanStatusBadgeVariant(
                                                        loan,
                                                    )}
                                                    className="text-[0.65rem] uppercase tracking-[0.14em]"
                                                >
                                                    {loan.lnstatus_label}
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {loan.lntype || '--'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Released{' '}
                                                {formatDate(loan.date_rel)}
                                            </p>
                                        </div>
                                        <div className="text-right text-xs">
                                            <p className="text-muted-foreground">
                                                Balance:{' '}
                                                <span className="font-medium text-foreground">
                                                    {formatCurrency(loan.balance)}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </>
                )}

                {hasConcerns ? (
                    <p className="flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-800 dark:text-amber-200">
                        <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                        Please contact our office to discuss payment
                        arrangements.
                    </p>
                ) : null}
            </CardContent>
        </Card>
    );
}
