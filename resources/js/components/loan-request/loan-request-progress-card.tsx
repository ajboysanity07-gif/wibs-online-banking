import { Check } from 'lucide-react';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import {
    PROGRESS_STEPS,
    resolveLoanRequestProgress,
} from '@/lib/loan-request-progress';
import { cn } from '@/lib/utils';
import type { LoanRequestStatusValue } from '@/types/loan-requests';

type Props = {
    status: LoanRequestStatusValue | null;
    /** Status before `cancelled`, so a cancelled request keeps its step. */
    previousStatus?: LoanRequestStatusValue | null;
};

export function LoanRequestProgressCard({ status, previousStatus }: Props) {
    const { step, held } = resolveLoanRequestProgress(status, previousStatus);
    const total = PROGRESS_STEPS.length;
    const isDone = (index: number) => index < step;
    const isCurrent = (index: number) => index === step;

    return (
        <div className="rounded-xl border border-border bg-card px-4 py-4 shadow-card sm:px-6">
            <ol className="hidden sm:flex">
                {PROGRESS_STEPS.map((label, index) => (
                    <li
                        key={label}
                        aria-current={isCurrent(index) ? 'step' : undefined}
                        className="relative flex flex-1 flex-col items-center gap-1.5"
                    >
                        {index < total - 1 ? (
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'absolute top-3.5 left-1/2 h-[3px] w-full rounded-full',
                                    isDone(index) ? 'bg-primary' : 'bg-border',
                                )}
                            />
                        ) : null}
                        <span
                            className={cn(
                                'relative grid size-7 place-items-center rounded-full border-2 text-xs font-bold',
                                isDone(index) &&
                                    'border-primary bg-primary text-primary-foreground',
                                isCurrent(index) &&
                                    'border-accent bg-accent text-accent-foreground',
                                !isDone(index) &&
                                    !isCurrent(index) &&
                                    'border-input bg-card text-muted-foreground',
                            )}
                        >
                            {isDone(index) ? (
                                <Check className="size-4" />
                            ) : (
                                index + 1
                            )}
                        </span>
                        <span
                            className={cn(
                                'text-center text-xs',
                                isCurrent(index)
                                    ? 'font-bold text-foreground'
                                    : 'font-medium text-muted-foreground',
                            )}
                        >
                            {label}
                        </span>
                    </li>
                ))}
            </ol>
            {held || step < 0 ? (
                <div className="mt-3 hidden items-center gap-2 text-sm text-muted-foreground sm:flex">
                    Current status
                    <LoanRequestStatusBadge status={status} />
                </div>
            ) : null}

            <div className="sm:hidden">
                <div className="flex items-center justify-between gap-2 text-sm">
                    <LoanRequestStatusBadge status={status} />
                    <span className="text-muted-foreground">
                        {step < 0
                            ? 'Step unknown'
                            : `Step ${Math.min(step + 1, total)} of ${total}`}
                    </span>
                </div>
                <div className="mt-2 flex gap-1" aria-hidden="true">
                    {PROGRESS_STEPS.map((label, index) => (
                        <span
                            key={label}
                            className={cn(
                                'h-[5px] flex-1 rounded-full',
                                isDone(index)
                                    ? 'bg-primary'
                                    : isCurrent(index)
                                      ? 'bg-accent'
                                      : 'bg-border',
                            )}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
}
