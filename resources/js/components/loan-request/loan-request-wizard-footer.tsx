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

export function LoanRequestWizardActions({
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
        <div className="rounded-2xl border border-border/40 bg-card/60 p-4 shadow-sm sm:p-5">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="order-3 flex sm:order-1">
                    <Button
                        type="button"
                        variant="ghost"
                        className="w-full sm:w-auto"
                        onClick={onBack}
                        disabled={isFirstStep || isSavingDraft || isSubmitting}
                    >
                        Back
                    </Button>
                </div>
                <div className="order-2 flex sm:order-2 sm:ml-auto sm:mr-3">
                    <Button
                        type="button"
                        variant="outline"
                        className="w-full sm:w-auto"
                        onClick={onSaveDraft}
                        disabled={isSavingDraft || isSubmitting}
                    >
                        Save draft
                    </Button>
                </div>
                <div className="order-1 flex sm:order-3">
                    {isLastStep ? (
                        <Button
                            type="button"
                            className="w-full sm:w-auto"
                            onClick={onSubmit}
                            disabled={
                                disablePrimary || isSavingDraft || isSubmitting
                            }
                        >
                            Submit for Review
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            className="w-full sm:w-auto"
                            onClick={onNext}
                            disabled={
                                disablePrimary || isSavingDraft || isSubmitting
                            }
                        >
                            Next
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}

export const LoanRequestWizardFooter = LoanRequestWizardActions;
