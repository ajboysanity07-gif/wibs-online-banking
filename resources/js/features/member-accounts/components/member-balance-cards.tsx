import { Link } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { MemberAccountsSummary } from '@/features/member-accounts/types';
import { formatCurrency, formatDate, MASKED_AMOUNT } from '@/lib/formatters';
import type { LoanStatusSummaryForMember } from '@/types/loan-requests';

type MemberBalanceCardsProps = {
    acctno: string | null;
    summary: MemberAccountsSummary | null;
    loading?: boolean;
    error?: string | null;
    onRetry?: () => void;
    loansHref: string;
    loanSecurityHref: string;
    hideBalances?: boolean;
    loanSummary?: LoanStatusSummaryForMember | null;
};

// ponytail: derived from the recent-loans window only; add a real repaid figure to the API if this drifts.
const getRepaidPercent = (summary: MemberAccountsSummary | null) => {
    const active = (summary?.recentLoans ?? []).filter(
        (loan) => (loan.balance ?? 0) > 0 && (loan.principal ?? 0) > 0,
    );
    const principal = active.reduce(
        (sum, loan) => sum + (loan.principal ?? 0),
        0,
    );

    if (principal <= 0) {
        return null;
    }

    const balance = active.reduce((sum, loan) => sum + (loan.balance ?? 0), 0);

    return Math.min(
        100,
        Math.max(0, Math.round((1 - balance / principal) * 100)),
    );
};

export function MemberBalanceCards({
    acctno,
    summary,
    loading = false,
    error = null,
    onRetry,
    loansHref,
    loanSecurityHref,
    hideBalances = false,
    loanSummary = null,
}: MemberBalanceCardsProps) {
    const repaid = getRepaidPercent(summary);
    const disabled = !acctno;
    const needsAttention =
        (loanSummary?.past_due_count ?? 0) +
            (loanSummary?.litigation_count ?? 0) >
        0;
    // lnstatus 'IIL'/'PDL' mark litigation/past-due; everything else is active.
    const activeLoanTypes = [
        ...new Set(
            (loanSummary?.loans ?? [])
                .filter(
                    (loan) =>
                        loan.lnstatus !== 'IIL' && loan.lnstatus !== 'PDL',
                )
                .map((loan) => loan.lntype)
                .filter(Boolean),
        ),
    ].join(' · ');

    return (
        <section className="space-y-5">
            {disabled ? (
                <Alert>
                    <AlertTitle>Account number missing</AlertTitle>
                    <AlertDescription>
                        Add an account number to view loan and loan security
                        details.
                    </AlertDescription>
                </Alert>
            ) : null}
            {error ? (
                <Alert variant="destructive">
                    <AlertTitle>Unable to load summary</AlertTitle>
                    <AlertDescription className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <span>{error}</span>
                        {onRetry ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={onRetry}
                            >
                                Retry
                            </Button>
                        ) : null}
                    </AlertDescription>
                </Alert>
            ) : null}
            <div className="grid grid-cols-1 items-stretch gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div className="flex min-w-0 flex-col gap-4 rounded-xl bg-primary p-6 text-primary-foreground shadow-card">
                    <span className="text-sm font-semibold">
                        Outstanding balance
                    </span>
                    {loading ? (
                        <Skeleton className="h-10 w-48 bg-primary-foreground/25" />
                    ) : (
                        <p className="text-[2.5rem] leading-none font-bold tracking-tight tabular-nums">
                            {hideBalances
                                ? MASKED_AMOUNT
                                : formatCurrency(summary?.loanBalanceLeft)}
                        </p>
                    )}
                    {repaid !== null ? (
                        <div className="space-y-2">
                            <div
                                role="progressbar"
                                aria-label="Loan repaid"
                                aria-valuenow={repaid}
                                aria-valuemin={0}
                                aria-valuemax={100}
                                className="h-2.5 overflow-hidden rounded-full bg-primary-foreground/25"
                            >
                                <div
                                    className="h-full rounded-full bg-accent"
                                    style={{ width: `${repaid}%` }}
                                />
                            </div>
                            <p className="text-sm">{repaid}% repaid</p>
                        </div>
                    ) : (
                        <p className="text-sm">
                            Last transaction:{' '}
                            {formatDate(summary?.lastLoanTransactionDate)}
                        </p>
                    )}
                    <div className="mt-auto flex flex-wrap gap-3">
                        <Button
                            asChild={!disabled}
                            disabled={disabled}
                            variant="accent"
                            className="font-bold"
                        >
                            {disabled ? (
                                'View loans'
                            ) : (
                                <Link href={loansHref}>View loans</Link>
                            )}
                        </Button>
                    </div>
                </div>
                <div className="flex min-w-0 flex-col gap-4 rounded-xl border border-border bg-card p-6 text-card-foreground shadow-card">
                    <span className="text-sm font-semibold text-muted-foreground">
                        Loan security
                    </span>
                    {loading ? (
                        <Skeleton className="h-10 w-48" />
                    ) : (
                        <p className="text-[2.5rem] leading-none font-bold tracking-tight tabular-nums">
                            {hideBalances
                                ? MASKED_AMOUNT
                                : formatCurrency(
                                      summary?.currentLoanSecurityBalance,
                                  )}
                        </p>
                    )}
                    <p className="text-sm text-muted-foreground">
                        Last transaction:{' '}
                        {formatDate(summary?.lastLoanSecurityTransactionDate)}
                    </p>
                    <div className="mt-auto">
                        <Button
                            asChild={!disabled}
                            disabled={disabled}
                            className="font-bold"
                        >
                            {disabled ? (
                                'View loan security'
                            ) : (
                                <Link href={loanSecurityHref}>
                                    View loan security
                                </Link>
                            )}
                        </Button>
                    </div>
                </div>
                {loanSummary ? (
                    <div className="flex min-w-0 flex-col gap-4 rounded-xl border border-border bg-card p-6 text-card-foreground shadow-card">
                        <span className="text-sm font-semibold text-muted-foreground">
                            Active loans
                        </span>
                        <p className="text-[2.5rem] leading-none font-bold tracking-tight tabular-nums">
                            {loanSummary.active_count}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {activeLoanTypes || 'No active loans'}
                        </p>
                        <div className="mt-auto flex flex-wrap items-center gap-3">
                            <Badge variant="secondary">
                                {needsAttention
                                    ? 'Needs attention'
                                    : 'On schedule'}
                            </Badge>
                            <Button
                                asChild={!disabled}
                                disabled={disabled}
                                variant="outline"
                                size="sm"
                            >
                                {disabled ? (
                                    'Loan ledger'
                                ) : (
                                    <Link href={loansHref}>Loan ledger</Link>
                                )}
                            </Button>
                        </div>
                    </div>
                ) : null}
            </div>
        </section>
    );
}
