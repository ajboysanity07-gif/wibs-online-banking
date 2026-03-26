import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

type Step = {
    id: string;
    title: string;
    description?: string;
};

type Props = {
    steps: Step[];
    currentStep: number;
    onStepChange?: (index: number) => void;
    className?: string;
};

export function LoanRequestStepIndicator({
    steps,
    currentStep,
    onStepChange,
    className,
}: Props) {
    const totalSteps = steps.length;
    const progressPercentage =
        totalSteps > 1 ? (currentStep / (totalSteps - 1)) * 100 : 0;
    const lineInsetPercent = totalSteps > 1 ? 100 / (totalSteps * 2) : 0;

    return (
        <div className={cn('overflow-x-auto pb-1', className)}>
            <div className="relative min-w-[600px] px-3 sm:min-w-[640px]">
                <div
                    className="absolute top-3.5 h-px bg-border/30"
                    style={{
                        left: `${lineInsetPercent}%`,
                        right: `${lineInsetPercent}%`,
                    }}
                    aria-hidden="true"
                />
                <div
                    className="absolute top-3.5 h-px"
                    style={{
                        left: `${lineInsetPercent}%`,
                        right: `${lineInsetPercent}%`,
                    }}
                    aria-hidden="true"
                >
                    <span
                        className="block h-full bg-primary/40 transition-all motion-reduce:transition-none"
                        style={{ width: `${progressPercentage}%` }}
                    />
                </div>

                <ol
                    className="grid grid-cols-6 gap-2"
                    aria-label="Loan request steps"
                >
                    {steps.map((step, index) => {
                        const isActive = index === currentStep;
                        const isComplete = index < currentStep;
                        const canNavigate = Boolean(onStepChange);

                        return (
                            <li
                                key={step.id}
                                className="flex min-w-[96px] flex-col items-center text-center sm:min-w-[112px]"
                            >
                                <button
                                    type="button"
                                    className={cn(
                                        'group relative z-10 flex flex-col items-center gap-2 text-[10px] font-medium sm:text-xs',
                                        canNavigate
                                            ? 'cursor-pointer'
                                            : 'cursor-default',
                                        'focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none',
                                    )}
                                    onClick={() => onStepChange?.(index)}
                                    aria-current={isActive ? 'step' : undefined}
                                    disabled={!canNavigate}
                                    title={step.description ?? step.title}
                                >
                                    <span
                                        className={cn(
                                            'flex h-7 w-7 items-center justify-center rounded-full border text-[10px] font-semibold transition-colors duration-200 sm:h-8 sm:w-8',
                                            isComplete
                                                ? 'border-primary bg-primary text-primary-foreground shadow-sm shadow-primary/20'
                                                : isActive
                                                  ? 'border-primary/70 bg-card text-primary ring-2 ring-primary/20'
                                                  : 'border-border/30 bg-muted/15 text-muted-foreground',
                                        )}
                                    >
                                        {isComplete ? (
                                            <Check className="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                                        ) : (
                                            index + 1
                                        )}
                                    </span>
                                    <span
                                        className={cn(
                                            'max-w-[6.5rem] truncate leading-tight transition-colors',
                                            isActive
                                                ? 'font-semibold text-foreground'
                                                : isComplete
                                                  ? 'text-foreground/70'
                                                  : 'text-muted-foreground',
                                        )}
                                    >
                                        {step.title}
                                    </span>
                                </button>
                            </li>
                        );
                    })}
                </ol>
            </div>
        </div>
    );
}
