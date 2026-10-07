import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { LoanEstimate, LoanEstimateLimits } from '@/types/loan-requests';

const money = (value: number | null | undefined): string =>
    value === null || value === undefined ? '--' : formatCurrency(value);

const percent = (rate: number | null): string =>
    rate === null ? '' : ` (${Math.round(rate * 10000) / 100}%)`;

/** "You receive about" + "Monthly payment, about" tiles. */
export function LoanEstimateFigures({
    estimate,
    isLoading = false,
    tone = 'tile',
}: {
    estimate: LoanEstimate | null;
    isLoading?: boolean;
    /** `tile` = shaded boxes (calculator, Loan details); `plain` = overview card. */
    tone?: 'tile' | 'plain';
}) {
    const tiles = [
        {
            label: 'You receive about',
            value: money(estimate?.net_proceeds_raw),
            className: 'text-primary',
        },
        {
            label: 'Monthly payment, about',
            value: money(estimate?.monthly_amortization_raw),
            className: '',
        },
    ];

    return (
        <dl
            aria-live="polite"
            aria-busy={isLoading}
            className="grid gap-3 sm:grid-cols-2"
        >
            {tiles.map((tile) => (
                <div
                    key={tile.label}
                    className={cn(
                        tone === 'tile' && 'rounded-lg bg-muted px-3.5 py-3',
                    )}
                >
                    <dt className="text-xs font-bold text-muted-foreground">
                        {tile.label}
                    </dt>
                    <dd
                        className={cn(
                            'text-[22px] font-bold tabular-nums transition-opacity duration-150 motion-reduce:transition-none',
                            tile.className,
                            isLoading && 'opacity-60',
                        )}
                    >
                        {tile.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

/** What the estimate leaves out, so a member never mistakes it for an offer. */
export function loanEstimateNote(limits: LoanEstimateLimits): string {
    const excluded = [
        limits.includesInterest ? null : 'interest',
        limits.includesServiceCharge ? null : 'service charge',
    ].filter(Boolean);

    return excluded.length > 0
        ? `Estimate after loan security, insurance, documentary stamp and notarial fee. Your loan processor sets the ${excluded.join(' and ')}, which ${excluded.length > 1 ? 'are' : 'is'} not included yet. A loan processor confirms the final terms.`
        : 'Estimate after interest, service charge, loan security, insurance, documentary stamp and notarial fee. A loan processor confirms the final terms.';
}

/** Collapsible deduction breakdown. */
export function LoanEstimateBreakdown({
    amount,
    estimate,
}: {
    amount: number;
    estimate: LoanEstimate | null;
}) {
    const [open, setOpen] = useState(false);

    if (!estimate) {
        return null;
    }

    const rows: { label: string; value: string; strong?: boolean }[] = [
        { label: 'Loan amount', value: money(amount), strong: true },
        ...(estimate.service_charge_amount_raw !== null
            ? [
                  {
                      label: `Service charge${percent(estimate.service_charge_rate)}`,
                      value: money(estimate.service_charge_amount_raw),
                  },
              ]
            : []),
        {
            label: `Loan security${percent(estimate.loan_security_rate)}`,
            value: money(estimate.loan_security_amount_raw),
        },
        {
            label: 'Insurance premium',
            value: money(estimate.insurance_premium_raw),
        },
        {
            label: 'Documentary stamp',
            value: money(estimate.documentary_stamp_amount_raw),
        },
        {
            label: 'Notarial fee',
            value: money(estimate.notarial_fee_raw),
        },
        // Due-date loans deduct interest upfront (LoanFiguresCalculator).
        ...(estimate.finance_charge_total_raw !== null &&
        estimate.finance_charge_total_raw !==
            (estimate.service_charge_amount_raw ?? 0)
            ? [
                  {
                      label: 'Advance interest',
                      value: money(estimate.interest_not_deducted_raw),
                  },
              ]
            : []),
        {
            label: 'Total deductions',
            value: money(estimate.deductions_total_raw),
            strong: true,
        },
        {
            label: `Per payment (${estimate.amortization_count ?? '--'} payments)`,
            value: money(estimate.amortization_total_raw),
        },
    ];

    return (
        <div>
            <Button variant="link"
                type="button"
                aria-expanded={open}
                onClick={() => setOpen((current) => !current)}
                className="h-auto md:h-auto justify-start gap-0 whitespace-normal rounded-none px-0 py-0 has-[>svg]:px-0 font-normal inline-flex min-h-11 items-center gap-1 text-sm font-bold text-primary underline underline-offset-4"
            >
                {open ? 'Hide breakdown' : 'Show how this is calculated'}
                <ChevronDown
                    aria-hidden="true"
                    className={cn(
                        'size-4 transition-transform duration-150 motion-reduce:transition-none',
                        open && 'rotate-180',
                    )}
                />
            </Button>
            {open ? (
                <dl className="mt-1 text-sm">
                    {rows.map((row) => (
                        <div
                            key={row.label}
                            className={cn(
                                'flex justify-between gap-2 py-1.5',
                                row.strong
                                    ? 'border-t border-border font-bold first:border-t-0'
                                    : 'pl-3 text-muted-foreground',
                            )}
                        >
                            <dt>{row.label}</dt>
                            <dd className="tabular-nums">{row.value}</dd>
                        </div>
                    ))}
                </dl>
            ) : null}
        </div>
    );
}

/** Inline warnings for the calculator and Loan details. */
export function loanEstimateWarnings({
    amount,
    term,
    maxTerm,
    grossMonthlyIncome,
    estimate,
}: {
    amount: string;
    term: string;
    maxTerm: number;
    grossMonthlyIncome: string;
    estimate: LoanEstimate | null;
}): string[] {
    const warnings: string[] = [];
    const termValue = Number(term);
    const income = Number(grossMonthlyIncome);

    if (!(Number(amount) >= 1)) {
        warnings.push('Enter a loan amount.');
    }

    if (!Number.isInteger(termValue) || termValue < 1 || termValue > maxTerm) {
        warnings.push(`Term must be between 1 and ${maxTerm} months.`);
    }

    // Same check as the processor's guaranteed net take-home pay (income
    // minus the monthly amortization) going to zero or below.
    if (
        income > 0 &&
        estimate?.monthly_amortization_raw != null &&
        estimate.monthly_amortization_raw >= income
    ) {
        warnings.push(
            'Your estimated monthly payment is more than your gross monthly income. A processor may recommend a lower amount or a longer term.',
        );
    }

    return warnings;
}

export function LoanEstimateWarnings({ warnings }: { warnings: string[] }) {
    return warnings.map((warning) => (
        <p
            key={warning}
            role="status"
            className="rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm font-semibold text-amber-900 dark:text-amber-100"
        >
            {warning}
        </p>
    ));
}
