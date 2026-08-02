import { AlertTriangle, ChevronDown, ChevronUp } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { formatCurrency, formatDate } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { LoanStatusSummaryForStaff, ProblemLoan } from '@/types/loan-requests';

type LoanStatusWarningProps = {
    loanStatus: LoanStatusSummaryForStaff | null;
    className?: string;
};

const loanStatusBadgeVariant = (loan: ProblemLoan) => {
    if (loan.lnstatus === 'IIL') {
        return 'destructive';
    }

    if (loan.lnstatus === 'PDL') {
        return 'secondary';
    }

    return 'outline';
};

export function LoanStatusWarning({
    loanStatus,
    className,
}: LoanStatusWarningProps) {
    const [open, setOpen] = useState(false);

    if (
        loanStatus === null ||
        !loanStatus.requires_attention ||
        loanStatus.problem_loans.length === 0
    ) {
        return null;
    }

    const { problem_loans: problemLoans } = loanStatus;

    return (
        <div
            className={cn(
                'rounded-2xl border border-amber-500/40 bg-amber-500/10 p-5',
                className,
            )}
            role="alert"
        >
            <div className="flex items-start gap-3">
                <AlertTriangle className="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <div className="min-w-0 flex-1 space-y-2">
                    <p className="text-sm font-semibold text-amber-900 dark:text-amber-100">
                        {loanStatus.warning_message ?? 'Applicant has problematic loans'}
                    </p>
                    <div className="flex flex-wrap items-center gap-2 text-xs">
                        {loanStatus.total_past_due > 0 ? (
                            <Badge variant="secondary">
                                {loanStatus.total_past_due} past due ·{' '}
                                {formatCurrency(loanStatus.past_due_balance_total)}
                            </Badge>
                        ) : null}
                        {loanStatus.total_litigation > 0 ? (
                            <Badge variant="destructive">
                                {loanStatus.total_litigation} in litigation ·{' '}
                                {formatCurrency(loanStatus.litigation_balance_total)}
                            </Badge>
                        ) : null}
                    </div>
                    <Collapsible open={open} onOpenChange={setOpen}>
                        <CollapsibleTrigger className="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground underline-offset-4 hover:underline">
                            {open ? (
                                <ChevronUp className="size-3.5" />
                            ) : (
                                <ChevronDown className="size-3.5" />
                            )}
                            {open ? 'Hide loan details' : 'View loan details'}
                        </CollapsibleTrigger>
                        <CollapsibleContent className="mt-3 space-y-2">
                            {problemLoans.map((loan) => (
                                <div
                                    key={loan.lnnumber}
                                    className="rounded-xl border border-border/40 bg-card/60 p-3"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="space-y-1">
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
                                                {formatDate(loan.date_rel)} · Matures{' '}
                                                {formatDate(loan.date_mat)}
                                            </p>
                                        </div>
                                        <div className="text-right text-xs">
                                            <p className="text-muted-foreground">
                                                Principal:{' '}
                                                <span className="font-medium text-foreground">
                                                    {formatCurrency(loan.principal)}
                                                </span>
                                            </p>
                                            <p className="text-muted-foreground">
                                                Balance:{' '}
                                                <span className="font-medium text-foreground">
                                                    {formatCurrency(loan.balance)}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </CollapsibleContent>
                    </Collapsible>
                    <p className="pt-1 text-xs italic text-muted-foreground">
                        Soft warning — you may approve or decline based on your
                        assessment and organizational policies.
                    </p>
                </div>
            </div>
        </div>
    );
}
