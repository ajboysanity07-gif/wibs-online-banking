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
    return (
        <ol
            className={cn(
                'grid gap-4 sm:grid-cols-3 lg:grid-cols-6',
                className,
            )}
            aria-label="Loan request steps"
        >
            {steps.map((step, index) => {
                const isActive = index === currentStep;
                const isComplete = index < currentStep;
                const canNavigate = Boolean(onStepChange);

                return (
                    <li key={step.id} className="h-full">
                        <button
                            type="button"
                            className={cn(
                                'group flex h-full w-full flex-col items-center gap-2 rounded-xl border border-transparent px-3 py-3 text-center text-xs font-medium transition',
                                isActive
                                    ? 'border-primary/40 bg-primary/10 text-primary'
                                    : isComplete
                                      ? 'text-foreground'
                                      : 'text-muted-foreground',
                                canNavigate
                                    ? 'hover:border-muted-foreground/40 hover:bg-muted/30'
                                    : 'cursor-default',
                            )}
                            onClick={() => onStepChange?.(index)}
                            aria-current={isActive ? 'step' : undefined}
                            disabled={!canNavigate}
                            title={step.description ?? step.title}
                        >
                            <span
                                className={cn(
                                    'flex h-9 w-9 items-center justify-center rounded-full border text-xs font-semibold transition',
                                    isActive
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : isComplete
                                          ? 'border-emerald-400/60 bg-emerald-500/10 text-emerald-600'
                                          : 'border-border/60 bg-muted/40 text-muted-foreground',
                                )}
                            >
                                {isComplete ? (
                                    <Check className="h-4 w-4" />
                                ) : (
                                    index + 1
                                )}
                            </span>
                            <span className="leading-tight">{step.title}</span>
                        </button>
                    </li>
                );
            })}
        </ol>
    );
}
