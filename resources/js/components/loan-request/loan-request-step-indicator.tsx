import {
    Building2,
    Check,
    ClipboardCheck,
    FileText,
    HeartPulse,
    User,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { cn } from '@/lib/utils';

type StepGroup = {
    label: string;
    icon: LucideIcon;
    steps: number[];
    stepNames: string[];
};

const STEP_GROUPS: StepGroup[] = [
    {
        label: 'Loan details',
        icon: FileText,
        steps: [0],
        stepNames: ['Loan details'],
    },
    {
        label: 'About you',
        icon: User,
        steps: [1, 2, 3, 4, 5],
        stepNames: [
            'Personal: basic info',
            'Personal: address & contact',
            'Personal: family & spouse',
            'Work: employment',
            'Work: income & details',
        ],
    },
    {
        label: 'Co-makers',
        icon: Users,
        steps: [6, 7, 8, 9, 10, 11, 12, 13],
        stepNames: [
            'Co-maker 1: basic info',
            'Co-maker 1: address & contact',
            'Co-maker 1: employment',
            'Co-maker 1: income & details',
            'Co-maker 2: basic info',
            'Co-maker 2: address & contact',
            'Co-maker 2: employment',
            'Co-maker 2: income & details',
        ],
    },
    {
        label: 'Insurance & health',
        icon: HeartPulse,
        steps: [14, 15, 16, 17, 18, 19],
        stepNames: [
            'Insurance & beneficiaries',
            'Health declarations',
            'Generali health (1 of 4)',
            'Generali health (2 of 4)',
            'Generali health (3 of 4)',
            'Generali health (4 of 4)',
        ],
    },
    {
        label: 'Bank & payout',
        icon: Building2,
        steps: [20, 21],
        stepNames: ['Bank & payout', 'Barangay information'],
    },
    {
        label: 'Declarations & review',
        icon: ClipboardCheck,
        steps: [22, 23, 24],
        stepNames: ['Declarations', 'Dependents', 'Review & submit'],
    },
];

const TOTAL_STEPS = 25;

type Props = {
    currentStep: number;
    onStepClick?: (index: number) => void;
};

export function LoanRequestStepIndicator({ currentStep, onStepClick }: Props) {
    return (
        <div className="flex h-full flex-col py-5">
            <nav
                className="scrollbar-hide hidden flex-1 overflow-y-auto px-3 lg:block"
                aria-label="Loan request steps"
            >
                {STEP_GROUPS.map((group) => {
                    const isDone = group.steps.every((s) => s < currentStep);
                    const isActive = group.steps.includes(currentStep);
                    const GroupIcon = group.icon;

                    return (
                        <div key={group.label} className="mb-0.5">
                            <button
                                type="button"
                                className={cn(
                                    'flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left transition-colors',
                                    isActive
                                        ? 'bg-primary/10 text-primary'
                                        : 'hover:bg-muted/50',
                                )}
                                onClick={() => onStepClick?.(group.steps[0])}
                                aria-current={isActive ? 'step' : undefined}
                            >
                                <span
                                    className={cn(
                                        'flex h-5.5 w-5.5 shrink-0 items-center justify-center',
                                        isDone
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : isActive
                                              ? 'text-primary'
                                              : 'text-muted-foreground',
                                    )}
                                >
                                    {isDone ? (
                                        <Check size={16} strokeWidth={2.5} />
                                    ) : (
                                        <GroupIcon
                                            size={18}
                                            strokeWidth={1.5}
                                        />
                                    )}
                                </span>
                                <span
                                    className={cn(
                                        'text-[13px] leading-tight font-medium',
                                        isDone
                                            ? 'text-foreground/60'
                                            : isActive
                                              ? 'text-primary'
                                              : 'text-foreground',
                                    )}
                                >
                                    {group.label}
                                </span>
                            </button>

                            {isActive && (
                                <div className="mt-0.5 mb-1 ml-5 space-y-0.5 border-l border-border/50 pl-4">
                                    {group.steps.map((stepIndex, i) => {
                                        const isSubDone =
                                            stepIndex < currentStep;
                                        const isSubActive =
                                            stepIndex === currentStep;

                                        return (
                                            <button
                                                key={stepIndex}
                                                type="button"
                                                className={cn(
                                                    'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left transition-colors',
                                                    isSubActive
                                                        ? 'bg-primary/10'
                                                        : 'hover:bg-muted/40',
                                                )}
                                                onClick={() =>
                                                    onStepClick?.(stepIndex)
                                                }
                                                aria-current={
                                                    isSubActive
                                                        ? 'step'
                                                        : undefined
                                                }
                                            >
                                                <span
                                                    className={cn(
                                                        'h-1.5 w-1.5 shrink-0 rounded-full',
                                                        isSubDone
                                                            ? 'bg-emerald-500 dark:bg-emerald-400'
                                                            : isSubActive
                                                              ? 'bg-primary'
                                                              : 'bg-muted-foreground/40',
                                                    )}
                                                />
                                                <span
                                                    className={cn(
                                                        'text-[12px] leading-tight',
                                                        isSubActive
                                                            ? 'font-medium text-primary'
                                                            : isSubDone
                                                              ? 'text-foreground/60'
                                                              : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {group.stepNames[i]}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    );
                })}
            </nav>

            <div className="px-5 pt-4 pb-3">
                <div className="mb-1.5 flex items-center justify-between text-[11px] text-muted-foreground">
                    <span>Progress</span>
                    <span>
                        Step {currentStep + 1} of {TOTAL_STEPS}
                    </span>
                </div>
                <div className="h-0.5 w-full overflow-hidden rounded-full bg-border/50">
                    <div
                        className="h-full bg-primary/50 transition-all duration-300 motion-reduce:transition-none"
                        style={{
                            width: `${((currentStep + 1) / TOTAL_STEPS) * 100}%`,
                        }}
                    />
                </div>
            </div>
        </div>
    );
}
