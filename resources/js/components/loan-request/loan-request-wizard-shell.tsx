import type { LucideIcon } from 'lucide-react';
import { LoanRequestStepIndicator } from '@/components/loan-request/loan-request-step-indicator';
import type { LoanRequestWizardStep } from '@/components/loan-request/loan-request-wizard-steps';
import { cn } from '@/lib/utils';

type GroupMeta = { label: string; icon: LucideIcon };

type Props = {
    currentStep: number;
    onStepClick?: (index: number) => void;
    steps?: LoanRequestWizardStep[];
    groupMeta?: Record<string, GroupMeta>;
    hiddenStepIds?: ReadonlySet<string>;
    children: React.ReactNode;
    footer?: React.ReactNode;
    sidebarClassName?: string;
    contentClassName?: string;
};

/**
 * Shared layout shell for loan request wizards.
 *
 * Provides the sidebar+content two-column split with `LoanRequestStepIndicator`
 * in the sidebar and an arbitrary content area on the right. Both the client
 * wizard (`pages/client/loan-request.tsx`) and the admin correction dialog
 * (`admin-loan-request-correction-dialog.tsx`) use this shell to avoid
 * duplicating the same layout scaffolding.
 *
 * The shell does not own the step content or footer logic — those are supplied
 * by the caller as `children` and `footer` respectively, since the two callers
 * have different data models, validation strategies, and footer semantics.
 */
export function LoanRequestWizardShell({
    currentStep,
    onStepClick,
    steps,
    groupMeta,
    hiddenStepIds,
    children,
    footer,
    sidebarClassName,
    contentClassName,
}: Props) {
    return (
        <div className="flex flex-col lg:flex-row">
            <div
                className={cn(
                    'w-full border-b border-border/50 bg-muted/15 lg:w-60 lg:shrink-0 lg:border-r lg:border-b-0',
                    sidebarClassName,
                )}
            >
                <LoanRequestStepIndicator
                    currentStep={currentStep}
                    onStepClick={onStepClick}
                    steps={steps}
                    groupMeta={groupMeta}
                    hiddenStepIds={hiddenStepIds}
                />
            </div>
            <div className={cn('min-w-0 flex-1', contentClassName)}>
                {children}
                {footer}
            </div>
        </div>
    );
}
