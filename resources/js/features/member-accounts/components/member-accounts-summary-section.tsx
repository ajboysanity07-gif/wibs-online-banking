import { Banknote, PiggyBank } from 'lucide-react';
import { MemberAccountSummaryCard } from '@/features/member-accounts/components/member-account-summary-card';
import { SectionHeader } from '@/components/section-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatDate } from '@/lib/formatters';
import type { MemberAccountsSummary } from '@/features/member-accounts/types';

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
    loanSecurityAction?: SummaryAction;
};

export function MemberAccountsSummarySection({
    acctno,
    summary,
    loading = false,
    error = null,
    onRetry,
    loansAction,
    loanSecurityAction,
}: MemberAccountsSummarySectionProps) {
    const handleRetry = () => {
        onRetry?.();
    };

    return (
        <section className="space-y-5">
            <SectionHeader
                title="Loans and Loan Security"
                description="Quick snapshot of loan and loan security activity."
                titleClassName="text-lg"
            />
            {!acctno ? (
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
                                onClick={handleRetry}
                            >
                                Retry
                            </Button>
                        ) : null}
                    </AlertDescription>
                </Alert>
            ) : null}
            <div className="grid gap-5 md:grid-cols-2">
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
                    title="Loan Security"
                    subtitle="Loan security overview"
                    primaryLabel="Loan Security Balance"
                    primaryValue={formatCurrency(
                        summary?.currentLoanSecurityBalance,
                    )}
                    secondaryLabel="Last Loan Security Transaction"
                    secondaryValue={formatDate(
                        summary?.lastLoanSecurityTransactionDate,
                    )}
                    icon={PiggyBank}
                    accent="accent"
                    actionLabel={loanSecurityAction?.label}
                    actionHref={loanSecurityAction?.href}
                    actionDisabled={loanSecurityAction?.disabled}
                    loading={loading}
                />
            </div>
        </section>
    );
}
