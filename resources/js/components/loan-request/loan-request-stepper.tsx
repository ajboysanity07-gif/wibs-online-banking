import { Check } from 'lucide-react';
import {
    buildStepGroups,
    GROUP_META,
} from '@/components/loan-request/loan-request-step-indicator';
import type { LoanRequestWizardStep } from '@/components/loan-request/loan-request-wizard-steps';
import { cn } from '@/lib/utils';

type Props = {
    steps: LoanRequestWizardStep[];
    currentStep: number;
    highestStepReached: number;
    onStepClick: (index: number) => void;
};

const EMPTY_HIDDEN: ReadonlySet<string> = new Set();

/**
 * Top-level progress for the client wizard: one node per step group.
 * >= 640px horizontal numbered circles joined by a line; below that a
 * "Group / Step X of N" line above segmented progress bars.
 */
export function LoanRequestStepper({
    steps,
    currentStep,
    highestStepReached,
    onStepClick,
}: Props) {
    const groups = buildStepGroups(steps, GROUP_META, EMPTY_HIDDEN).map(
        (group) => ({
            ...group,
            isDone: group.steps.every((index) => index < currentStep),
            isCurrent: group.steps.includes(currentStep),
        }),
    );
    const currentGroupIndex = Math.max(
        0,
        groups.findIndex((group) => group.isCurrent),
    );

    return (
        <nav aria-label="Loan request progress" className="w-full">
            <div className="sm:hidden">
                <div className="mb-2 flex items-baseline justify-between gap-3">
                    <span className="truncate text-sm font-semibold">
                        {groups[currentGroupIndex]?.label}
                    </span>
                    <span className="shrink-0 text-xs text-muted-foreground">
                        Step {currentGroupIndex + 1} of {groups.length}
                    </span>
                </div>
                <div className="flex gap-1.5">
                    {groups.map((group) => (
                        <span
                            key={group.label}
                            className={cn(
                                'h-1.5 flex-1 rounded-full transition-[background-color] duration-[250ms] motion-reduce:transition-none',
                                group.isDone || group.isCurrent
                                    ? 'bg-primary'
                                    : 'bg-border',
                            )}
                        />
                    ))}
                </div>
            </div>

            <ol className="hidden sm:flex">
                {groups.map((group, index) => {
                    const canClick =
                        group.isDone ||
                        group.isCurrent ||
                        group.steps[0] <= highestStepReached;

                    return (
                        <li
                            key={group.label}
                            className="relative flex flex-1 flex-col items-center px-1"
                        >
                            {index > 0 ? (
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'absolute top-[17px] right-[calc(50%+22px)] left-[calc(-50%+22px)] h-0.5 rounded-full transition-[background-color] duration-[250ms] motion-reduce:transition-none',
                                        group.isDone || group.isCurrent
                                            ? 'bg-primary'
                                            : 'bg-border',
                                    )}
                                />
                            ) : null}
                            <button
                                type="button"
                                disabled={!canClick || group.isCurrent}
                                aria-current={
                                    group.isCurrent ? 'step' : undefined
                                }
                                onClick={() => onStepClick(group.steps[0])}
                                className="flex flex-col items-center gap-2 rounded-md text-center outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-default"
                            >
                                <span
                                    className={cn(
                                        'flex size-9 items-center justify-center rounded-full border-2 text-sm font-bold transition-[background-color,box-shadow] duration-[250ms] motion-reduce:transition-none',
                                        group.isDone &&
                                            'border-primary bg-primary text-primary-foreground',
                                        group.isCurrent &&
                                            'border-accent bg-accent text-accent-foreground ring-4 ring-accent/30',
                                        !group.isDone &&
                                            !group.isCurrent &&
                                            'border-input bg-card text-muted-foreground',
                                    )}
                                >
                                    {group.isDone ? (
                                        <Check
                                            className="size-4"
                                            strokeWidth={3}
                                        />
                                    ) : (
                                        index + 1
                                    )}
                                </span>
                                <span
                                    className={cn(
                                        'text-xs leading-tight',
                                        group.isCurrent
                                            ? 'font-bold text-foreground'
                                            : group.isDone
                                              ? 'font-semibold text-foreground'
                                              : 'font-medium text-muted-foreground',
                                    )}
                                >
                                    {group.label}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
