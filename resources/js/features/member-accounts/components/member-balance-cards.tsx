import { Link } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { MemberAccountsSummary } from '@/features/member-accounts/types';
import { formatCurrency, formatDate } from '@/lib/formatters';

type MemberBalanceCardsProps = {
    acctno: string | null;
    summary: MemberAccountsSummary | null;
    loading?: boolean;
    error?: string | null;
    onRetry?: () => void;
    loansHref: string;
    loanSecurityHref: string;
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
}: MemberBalanceCardsProps) {
    const repaid = getRepaidPercent(summary);
    const disabled = !acctno;

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
            <div className="grid gap-4 md:grid-cols-2">
                <div className="flex flex-col gap-4 rounded-3xl bg-primary p-6 text-primary-foreground">
                    <span className="text-sm font-semibold">
                        Outstanding balance
                    </span>
                    {loading ? (
                        <Skeleton className="h-10 w-48 bg-primary-foreground/25" />
                    ) : (
                        <p className="text-4xl font-bold tracking-tight tabular-nums">
                            {formatCurrency(summary?.loanBalanceLeft)}
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
                            variant="secondary"
                            className="rounded-full bg-accent text-accent-foreground hover:bg-accent/90"
                        >
                            {disabled ? (
                                'View loans'
                            ) : (
                                <Link href={loansHref}>View loans</Link>
                            )}
                        </Button>
                    </div>
                </div>
                <div className="flex flex-col gap-4 rounded-3xl border bg-card p-6 text-card-foreground">
                    <span className="text-sm font-semibold text-muted-foreground">
                        Loan security
                    </span>
                    {loading ? (
                        <Skeleton className="h-10 w-48" />
                    ) : (
                        <p className="text-4xl font-bold tracking-tight tabular-nums">
                            {formatCurrency(
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
                            className="rounded-full"
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
            </div>
        </section>
    );
}
