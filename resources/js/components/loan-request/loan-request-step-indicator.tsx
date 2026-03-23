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
};

export function LoanRequestStepIndicator({
    steps,
    currentStep,
    onStepChange,
}: Props) {
    return (
        <div className="flex flex-wrap gap-2">
            {steps.map((step, index) => {
                const isActive = index === currentStep;
                const isComplete = index < currentStep;
                const canNavigate = Boolean(onStepChange);

                return (
                    <button
                        key={step.id}
                        type="button"
                        className={cn(
                            'flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium transition',
                            isActive
                                ? 'border-primary bg-primary text-primary-foreground'
                                : isComplete
                                  ? 'border-primary/40 text-primary'
                                  : 'border-muted-foreground/20 text-muted-foreground',
                            !canNavigate && 'cursor-default',
                        )}
                        onClick={() => onStepChange?.(index)}
                        aria-current={isActive ? 'step' : undefined}
                        disabled={!canNavigate}
                        title={step.description ?? step.title}
                    >
                        <span
                            className={cn(
                                'flex h-5 w-5 items-center justify-center rounded-full text-[10px]',
                                isActive
                                    ? 'bg-primary-foreground/20 text-primary-foreground'
                                    : 'bg-muted text-foreground',
                            )}
                        >
                            {index + 1}
                        </span>
                        <span className="whitespace-nowrap">
                            {step.title}
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
