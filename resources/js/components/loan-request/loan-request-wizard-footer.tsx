import { Button } from '@/components/ui/button';

type Props = {
    isFirstStep: boolean;
    isLastStep: boolean;
    onBack: () => void;
    onNext: () => void;
    onSaveDraft: () => void;
    onSubmit: () => void;
    isSavingDraft: boolean;
    isSubmitting: boolean;
    disablePrimary?: boolean;
};

export function LoanRequestWizardFooter({
    isFirstStep,
    isLastStep,
    onBack,
    onNext,
    onSaveDraft,
    onSubmit,
    isSavingDraft,
    isSubmitting,
    disablePrimary = false,
}: Props) {
    return (
        <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    onClick={onBack}
                    disabled={isFirstStep || isSavingDraft || isSubmitting}
                >
                    Back
                </Button>
                {isLastStep ? (
                    <Button
                        type="button"
                        onClick={onSubmit}
                        disabled={disablePrimary || isSavingDraft || isSubmitting}
                    >
                        Submit application
                    </Button>
                ) : (
                    <Button
                        type="button"
                        onClick={onNext}
                        disabled={disablePrimary || isSavingDraft || isSubmitting}
                    >
                        Next
                    </Button>
                )}
            </div>
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={onSaveDraft}
                    disabled={isSavingDraft || isSubmitting}
                >
                    Save draft
                </Button>
            </div>
        </div>
    );
}
