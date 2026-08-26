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
    type LoanRequestWizardStep,
} from '@/components/loan-request/loan-request-wizard-steps';
import { cn } from '@/lib/utils';

type StepGroup = {
    label: string;
    icon: LucideIcon;
    steps: number[];
    stepNames: string[];
};

type GroupMeta = { label: string; icon: LucideIcon };

/**
 * Steps present in the full step list but currently skipped (e.g. insurance
 * & health steps when the member requested a 1-month Due date repayment).
 * Hidden from the sidebar entirely, but their index in `steps` is preserved
 * so it keeps lining up with the wizard's `currentStep` -- callers must not
 * pass a pre-filtered `steps` array.
 */
type HiddenStepIds = ReadonlySet<string>;

const GROUP_META: Record<LoanRequestWizardGroupId, GroupMeta> = {
    'loan-details': { label: 'Loan details', icon: FileText },
    'about-you': { label: 'About you', icon: User },
    'co-makers': { label: 'Co-makers', icon: Users },
    'insurance-health': { label: 'Insurance & health', icon: HeartPulse },
    'bank-payout': {
        label: 'Disbursement & Repayment',
        icon: Building2,
    },
    'declarations-review': {
        label: 'Declarations & review',
        icon: ClipboardCheck,
    },
};

/**
 * Derives the sidebar's group/sub-step structure from a wizard step list
 * instead of a hand-maintained list, so adding a step there is picked up
 * here automatically. Defaults to the client wizard's single source of
 * truth (loanRequestWizardSteps), but accepts any step list + group meta so
 * other wizards (e.g. the admin correction dialog) can reuse the same
 * sidebar shell with their own steps.
 */
function buildStepGroups(
    steps: LoanRequestWizardStep[],
    groupMeta: Record<string, GroupMeta>,
    hiddenStepIds: HiddenStepIds,
): StepGroup[] {
    const groups: StepGroup[] = [];

    steps.forEach((step, index) => {
        if (hiddenStepIds.has(step.id)) {
            return;
        }

        const lastGroup = groups[groups.length - 1];
        const meta = groupMeta[step.group];

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

const EMPTY_HIDDEN_STEP_IDS: HiddenStepIds = new Set();

type Props = {
    currentStep: number;
    onStepClick?: (index: number) => void;
    steps?: LoanRequestWizardStep[];
    groupMeta?: Record<string, GroupMeta>;
    hiddenStepIds?: HiddenStepIds;
};

export function LoanRequestStepIndicator({
    currentStep,
    onStepClick,
    steps = loanRequestWizardSteps,
    groupMeta = GROUP_META,
    hiddenStepIds = EMPTY_HIDDEN_STEP_IDS,
}: Props) {
    const stepGroups = buildStepGroups(steps, groupMeta, hiddenStepIds);
    const visibleStepIndexes = steps
        .map((step, index) => ({ id: step.id, index }))
        .filter((entry) => !hiddenStepIds.has(entry.id))
        .map((entry) => entry.index);
    const totalSteps = visibleStepIndexes.length;
    const currentVisiblePosition = Math.max(
        0,
        visibleStepIndexes.indexOf(currentStep),
    );

    return (
        <div className="flex h-full flex-col py-5">
            <nav
                className="scrollbar-hide hidden flex-1 overflow-y-auto px-3 lg:block"
                aria-label="Loan request steps"
            >
                {stepGroups.map((group) => {
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
                        Step {currentVisiblePosition + 1} of {totalSteps}
                    </span>
                </div>
                <div className="h-0.5 w-full overflow-hidden rounded-full bg-border/50">
                    <div
                        className="h-full bg-primary/50 transition-all duration-300 motion-reduce:transition-none"
                        style={{
                            width: `${((currentVisiblePosition + 1) / totalSteps) * 100}%`,
                        }}
                    />
                </div>
            </div>
        </div>
    );
}
