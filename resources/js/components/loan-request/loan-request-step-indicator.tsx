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
import {
    loanRequestWizardSteps,
    type LoanRequestWizardGroupId,
} from '@/components/loan-request/loan-request-wizard-steps';
import { cn } from '@/lib/utils';

type StepGroup = {
    label: string;
    icon: LucideIcon;
    steps: number[];
    stepNames: string[];
};

const GROUP_META: Record<
    LoanRequestWizardGroupId,
    { label: string; icon: LucideIcon }
> = {
    'loan-details': { label: 'Loan details', icon: FileText },
    'about-you': { label: 'About you', icon: User },
    'co-makers': { label: 'Co-makers', icon: Users },
    'insurance-health': { label: 'Insurance & health', icon: HeartPulse },
    'bank-payout': { label: 'Bank & payout', icon: Building2 },
    'declarations-review': {
        label: 'Declarations & review',
        icon: ClipboardCheck,
    },
};

/**
 * Derives the sidebar's group/sub-step structure from the wizard's single
 * source of truth (loanRequestWizardSteps) instead of a hand-maintained list,
 * so adding a step there is picked up here automatically.
 */
function buildStepGroups(): StepGroup[] {
    const groups: StepGroup[] = [];

    loanRequestWizardSteps.forEach((step, index) => {
        const lastGroup = groups[groups.length - 1];
        const meta = GROUP_META[step.group];

        const stepName = step.sidebarLabel ?? step.title;

        if (lastGroup && lastGroup.label === meta.label) {
            lastGroup.steps.push(index);
            lastGroup.stepNames.push(stepName);
            return;
        }

        groups.push({
            label: meta.label,
            icon: meta.icon,
            steps: [index],
            stepNames: [stepName],
        });
    });

    return groups;
}

const STEP_GROUPS: StepGroup[] = buildStepGroups();

const TOTAL_STEPS = loanRequestWizardSteps.length;

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
