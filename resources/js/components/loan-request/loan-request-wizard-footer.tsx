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
        <div className="sticky bottom-0 z-20 border-t border-border/60 bg-background/90 px-4 py-4 shadow-[0_-12px_24px_-24px_rgba(0,0,0,0.35)] backdrop-blur supports-[backdrop-filter]:bg-background/70 sm:px-6 sm:py-5">
            <div className="mx-auto w-full max-w-5xl">
                <div className="grid gap-4 sm:grid-cols-3 sm:items-center">
                    <div className="flex justify-start">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={onBack}
                            disabled={
                                isFirstStep || isSavingDraft || isSubmitting
                            }
                        >
                            Back
                        </Button>
                    </div>
                    <div className="flex justify-start sm:justify-center">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onSaveDraft}
                            disabled={isSavingDraft || isSubmitting}
                        >
                            Save draft
                        </Button>
                    </div>
                    <div className="flex justify-start sm:justify-end">
                        {isLastStep ? (
                            <Button
                                type="button"
                                onClick={onSubmit}
                                disabled={
                                    disablePrimary ||
                                    isSavingDraft ||
                                    isSubmitting
                                }
                            >
                                Submit application
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                onClick={onNext}
                                disabled={
                                    disablePrimary ||
                                    isSavingDraft ||
                                    isSubmitting
                                }
                            >
                                Next
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
