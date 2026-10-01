import { Minus, Plus } from 'lucide-react';
import {
    LoanEstimateBreakdown,
    LoanEstimateFigures,
    LoanEstimateWarnings,
    loanEstimateNote,
} from '@/components/loan-request/loan-application-estimate';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type {
    LoanEstimate,
    LoanEstimateLimits,
    LoanTypeOption,
} from '@/types/loan-requests';

type Props = {
    loanTypes: LoanTypeOption[];
    typecode: string;
    amount: string;
    term: string;
    limits: LoanEstimateLimits;
    estimate: LoanEstimate | null;
    isEstimating: boolean;
    warnings: string[];
    hasDraft: boolean;
    isSaving: boolean;
    onTypecodeChange: (value: string) => void;
    onAmountChange: (value: string) => void;
    onTermChange: (value: string) => void;
    onApply: () => void;
    onContinueDraft: () => void;
};

const AMOUNT_SLIDER_MIN = 1000;
const AMOUNT_SLIDER_STEP = 1000;

const sliderClassName =
    'h-11 w-full cursor-pointer accent-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';

export function LoanApplicationCalculator({
    loanTypes,
    typecode,
    amount,
    term,
    limits,
    estimate,
    isEstimating,
    warnings,
    hasDraft,
    isSaving,
    onTypecodeChange,
    onAmountChange,
    onTermChange,
    onApply,
    onContinueDraft,
}: Props) {
    const amountValue = Number(amount) || 0;
    const termValue = Number(term) || 0;
    const clampTerm = (value: number) =>
        String(Math.min(limits.maxTermMonths, Math.max(1, value)));

    return (
        <>
            <div className="space-y-1.5 px-0.5">
                <p className="text-xs font-bold tracking-[0.16em] text-primary uppercase">
                    Loan calculator
                </p>
                <h2 className="text-2xl font-bold tracking-tight sm:text-[30px]">
                    How much do you need?
                </h2>
                <p className="text-[15px] text-muted-foreground">
                    See what you would receive and pay before you apply. Nothing
                    is submitted yet.
                </p>
            </div>

            <section className="space-y-5 rounded-xl border border-border bg-card p-5 shadow-card">
                {loanTypes.length > 1 ? (
                    <fieldset>
                        <legend className="mb-2 text-sm font-semibold">
                            Loan type
                        </legend>
                        <div className="flex flex-wrap gap-2">
                            {loanTypes.map((type) => {
                                const active = type.typecode === typecode;

                                return (
                                    <button
                                        key={type.typecode}
                                        type="button"
                                        aria-pressed={active}
                                        onClick={() =>
                                            onTypecodeChange(type.typecode)
                                        }
                                        className={cn(
                                            'min-h-11 rounded-full border-[1.5px] px-4 text-sm font-semibold transition-colors duration-150 motion-reduce:transition-none',
                                            active
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-input bg-transparent text-foreground hover:bg-muted',
                                        )}
                                    >
                                        {type.label}
                                    </button>
                                );
                            })}
                        </div>
                    </fieldset>
                ) : null}

                <div>
                    <div className="mb-2 flex flex-wrap items-center justify-between gap-3">
                        <label
                            htmlFor="calculator_amount"
                            className="text-sm font-semibold"
                        >
                            Amount
                        </label>
                        <div className="flex h-12 w-56 max-w-full items-center gap-1.5 rounded-lg border-[1.5px] border-input bg-card px-3 focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
                            <span className="text-sm text-muted-foreground">
                                PHP
                            </span>
                            <input
                                id="calculator_amount"
                                inputMode="decimal"
                                autoComplete="off"
                                value={
                                    amountValue > 0
                                        ? Math.round(
                                              amountValue,
                                          ).toLocaleString('en-US')
                                        : amount
                                }
                                onChange={(event) =>
                                    onAmountChange(
                                        event.target.value.replace(
                                            /[^0-9.]/g,
                                            '',
                                        ),
                                    )
                                }
                                className="h-full min-w-0 flex-1 bg-transparent text-right text-xl font-bold tabular-nums outline-none"
                            />
                        </div>
                    </div>
                    <input
                        type="range"
                        aria-label="Amount slider"
                        min={AMOUNT_SLIDER_MIN}
                        max={limits.sliderMaxAmount}
                        step={AMOUNT_SLIDER_STEP}
                        value={Math.min(
                            limits.sliderMaxAmount,
                            Math.max(AMOUNT_SLIDER_MIN, amountValue),
                        )}
                        onChange={(event) => onAmountChange(event.target.value)}
                        className={sliderClassName}
                    />
                    <div className="flex justify-between text-xs text-muted-foreground">
                        <span>{formatCurrency(AMOUNT_SLIDER_MIN)}</span>
                        <span>{formatCurrency(limits.sliderMaxAmount)}</span>
                    </div>
                </div>

                <div>
                    <div className="mb-2 flex flex-wrap items-center justify-between gap-3">
                        <label
                            htmlFor="calculator_term"
                            className="text-sm font-semibold"
                        >
                            Term
                        </label>
                        <div className="flex items-center gap-1.5">
                            <Button
                                type="button"
                                variant="outline"
                                aria-label="Fewer months"
                                className="size-12 md:size-12"
                                disabled={termValue <= 1}
                                onClick={() =>
                                    onTermChange(clampTerm(termValue - 1))
                                }
                            >
                                <Minus />
                            </Button>
                            <div className="flex h-12 w-32 items-center gap-1.5 rounded-lg border-[1.5px] border-input bg-card px-3 focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
                                <input
                                    id="calculator_term"
                                    inputMode="numeric"
                                    autoComplete="off"
                                    value={term}
                                    onChange={(event) =>
                                        onTermChange(
                                            event.target.value.replace(
                                                /[^0-9]/g,
                                                '',
                                            ),
                                        )
                                    }
                                    className="h-full min-w-0 flex-1 bg-transparent text-right text-xl font-bold tabular-nums outline-none"
                                />
                                <span className="text-sm text-muted-foreground">
                                    months
                                </span>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                aria-label="More months"
                                className="size-12 md:size-12"
                                disabled={termValue >= limits.maxTermMonths}
                                onClick={() =>
                                    onTermChange(clampTerm(termValue + 1))
                                }
                            >
                                <Plus />
                            </Button>
                        </div>
                    </div>
                    <input
                        type="range"
                        aria-label="Term slider"
                        min={1}
                        max={limits.maxTermMonths}
                        step={1}
                        value={Math.min(
                            limits.maxTermMonths,
                            Math.max(1, termValue),
                        )}
                        onChange={(event) => onTermChange(event.target.value)}
                        className={sliderClassName}
                    />
                    <div className="flex justify-between text-xs text-muted-foreground">
                        <span>1 month</span>
                        <span>{limits.maxTermMonths} months</span>
                    </div>
                </div>

                <LoanEstimateFigures
                    estimate={estimate}
                    isLoading={isEstimating}
                />
                <LoanEstimateBreakdown
                    amount={amountValue}
                    estimate={estimate}
                />
                <LoanEstimateWarnings warnings={warnings} />
                <p className="text-xs text-muted-foreground">
                    {loanEstimateNote(limits)}
                </p>
            </section>

            <div className="flex flex-wrap items-center gap-4">
                <Button
                    type="button"
                    className="min-h-12 px-6 text-[15px] font-bold md:min-h-12"
                    disabled={
                        isSaving ||
                        !(amountValue >= 1) ||
                        termValue < 1 ||
                        termValue > limits.maxTermMonths
                    }
                    onClick={onApply}
                >
                    Apply with these terms
                </Button>
                {hasDraft ? (
                    <Button
                        type="button"
                        variant="link"
                        className="min-h-11 px-0 font-bold underline"
                        onClick={onContinueDraft}
                    >
                        Continue my saved draft
                    </Button>
                ) : null}
            </div>
        </>
    );
}
