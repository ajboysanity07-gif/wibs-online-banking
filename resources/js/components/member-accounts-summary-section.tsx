import { Banknote, PiggyBank } from 'lucide-react';
import { MemberAccountSummaryCard } from '@/components/member-account-summary-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatDate } from '@/lib/formatters';
import type { MemberAccountsSummary } from '@/types/admin';

type SummaryAction = {
    label: string;
    href?: string;
    disabled?: boolean;
};

type MemberAccountsSummarySectionProps = {
    acctno: string | null;
    summary: MemberAccountsSummary | null;
    loading?: boolean;
    error?: string | null;
    onRetry?: () => void;
    loansAction?: SummaryAction;
    savingsAction?: SummaryAction;
};

export function MemberAccountsSummarySection({
    acctno,
    summary,
    loading = false,
    error = null,
    onRetry,
    loansAction,
    savingsAction,
}: MemberAccountsSummarySectionProps) {
    const handleRetry = () => {
        onRetry?.();
    };

    return (
        <section className="space-y-4">
            <div className="space-y-1">
                <h2 className="text-xl font-semibold">Loans and Savings</h2>
                <p className="text-sm text-muted-foreground">
                    Quick snapshot of loan and savings activity.
                </p>
            </div>
            {!acctno ? (
                <Alert>
                    <AlertTitle>Account number missing</AlertTitle>
                    <AlertDescription>
                        Add an account number to view loan and savings details.
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
                                onClick={handleRetry}
                            >
                                Retry
                            </Button>
                        ) : null}
                    </AlertDescription>
                </Alert>
            ) : null}
            <div className="grid gap-4 md:grid-cols-2">
                <MemberAccountSummaryCard
                    title="Loans"
                    subtitle="Loan portfolio snapshot"
                    primaryLabel="Total Outstanding Loan Balance"
                    primaryValue={formatCurrency(summary?.loanBalanceLeft)}
                    secondaryLabel="Last Loan Transaction"
                    secondaryValue={formatDate(
                        summary?.lastLoanTransactionDate,
                    )}
                    icon={Banknote}
                    accent="primary"
                    actionLabel={loansAction?.label}
                    actionHref={loansAction?.href}
                    actionDisabled={loansAction?.disabled}
                    loading={loading}
                />
                <MemberAccountSummaryCard
                    title="Savings"
                    subtitle="Savings overview"
                    primaryLabel="Total Current Savings"
                    primaryValue={formatCurrency(
                        summary?.currentSavingsBalance,
                    )}
                    secondaryLabel="Last Savings Transaction"
                    secondaryValue={formatDate(
                        summary?.lastSavingsTransactionDate,
                    )}
                    icon={PiggyBank}
                    accent="accent"
                    actionLabel={savingsAction?.label}
                    actionHref={savingsAction?.href}
                    actionDisabled={savingsAction?.disabled}
                    loading={loading}
                />
            </div>
        </section>
    );
}
