import { Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    isFirstStep: boolean;
    isLastStep: boolean;
    page: number;
    totalPages: number;
    blockers: string[];
    onBack: () => void;
    onNext: () => void;
    onSubmit: () => void;
    isSavingDraft: boolean;
    isSubmitting: boolean;
    disablePrimary?: boolean;
};

export function LoanRequestWizardActions({
    isFirstStep,
    isLastStep,
    page,
    totalPages,
    blockers,
    onBack,
    onNext,
    onSubmit,
    isSavingDraft,
    isSubmitting,
    disablePrimary = false,
}: Props) {
    return (
        <footer className="sticky bottom-0 z-20 border-t border-border bg-card shadow-[0_-6px_16px_-8px_color-mix(in_oklab,var(--foreground)_20%,transparent)]">
            <div className="mx-auto w-full max-w-[1040px] space-y-2 px-4 py-3 md:px-7">
                {blockers.length > 0 ? (
                    <p
                        role="status"
                        className="animate-fade-in rounded-md bg-destructive/10 px-3 py-2 text-sm font-medium text-destructive"
                    >
                        Required to continue: {blockers.join(', ')}
                    </p>
                ) : null}
                <div className="flex items-center gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-12 px-6"
                        onClick={onBack}
                        disabled={isFirstStep || isSavingDraft || isSubmitting}
                    >
                        Back
                    </Button>
                    <p className="hidden flex-1 text-center text-sm text-muted-foreground sm:block">
                        Page {page} of {totalPages}
                    </p>
                    <Button
                        type="button"
                        className="min-h-12 flex-1 px-8 font-bold disabled:opacity-45 sm:flex-none"
                        onClick={isLastStep ? onSubmit : onNext}
                        disabled={
                            disablePrimary || isSavingDraft || isSubmitting
                        }
                    >
                        {isSubmitting ? (
                            <Loader2 className="animate-spin" />
                        ) : null}
                        {isLastStep ? 'Submit for Review' : 'Next'}
                    </Button>
                </div>
            </div>
        </footer>
    );
}

export const LoanRequestWizardFooter = LoanRequestWizardActions;
