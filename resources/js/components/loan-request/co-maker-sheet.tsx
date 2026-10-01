import { useState } from 'react';
import { LoanApplicationProgress } from '@/components/loan-request/loan-application-progress';
import { LoanRequestCoMakerStep } from '@/components/loan-request/loan-request-steps';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';
import { getStepMissingFields } from '@/lib/loan-request-step-validation';
import { cn } from '@/lib/utils';
import type {
    LoanRequestFormData,
    LoanRequestPersonFormData,
    SavedCoMakerOption,
} from '@/types/loan-requests';

type Slot = 'co_maker_1' | 'co_maker_2';

const STEPS = [
    { section: 'basic', title: 'Basic info' },
    { section: 'contact', title: 'Address & contact' },
    { section: 'all', title: 'Work & income' },
] as const;

type Props = {
    slot: Slot | null;
    initial: LoanRequestPersonFormData | null;
    errors: Record<string, string | undefined>;
    savedCoMakers: SavedCoMakerOption[];
    /** Guided first pass: show the 5-segment progress (mobile substeps). */
    guided: boolean;
    isSaving: boolean;
    isSavingForReuse: boolean;
    onLoadSaved: (id: number) => Promise<LoanRequestPersonFormData | null>;
    onRemoveSaved: (id: number) => void;
    onSaveForReuse: (person: LoanRequestPersonFormData) => Promise<void>;
    onSave: (slot: Slot, person: LoanRequestPersonFormData) => void;
    onClose: () => void;
};

/**
 * Co-maker form. Desktop/tablet: all fields in one sheet. Mobile (<640px):
 * three steps, Basic info -> Contact -> Work. Edits stay local until "Save
 * co-maker", so closing the sheet discards them.
 */
export function CoMakerSheet(props: Props) {
    return (
        <Sheet
            open={props.slot !== null}
            onOpenChange={(open) => (!open ? props.onClose() : undefined)}
        >
            <SheetContent side="right" className="w-full gap-0 sm:max-w-xl">
                {props.slot !== null && props.initial !== null ? (
                    <CoMakerForm
                        key={props.slot}
                        {...props}
                        slot={props.slot}
                        initial={props.initial}
                    />
                ) : null}
            </SheetContent>
        </Sheet>
    );
}

function CoMakerForm({
    slot,
    initial,
    errors,
    savedCoMakers,
    guided,
    isSaving,
    isSavingForReuse,
    onLoadSaved,
    onRemoveSaved,
    onSaveForReuse,
    onSave,
}: Props & { slot: Slot; initial: LoanRequestPersonFormData }) {
    const isPhone = useIsMobile(640);
    const [person, setPerson] = useState(initial);
    const [step, setStep] = useState(0);
    const number = slot === 'co_maker_1' ? 1 : 2;
    const visibleSteps = isPhone ? [STEPS[step]] : STEPS;
    const isLastStep = !isPhone || step === STEPS.length - 1;
    const hasName =
        person.first_name.trim() !== '' && person.last_name.trim() !== '';
    const stepIds = [
        `co-maker-${number}-basic`,
        `co-maker-${number}-contact`,
        `co-maker-${number}-employment`,
    ];
    const data = { [slot]: person } as unknown as LoanRequestFormData;
    const missing = (isPhone ? [stepIds[step]] : stepIds).flatMap((id) =>
        getStepMissingFields(id, data, {
            applicantPrefilledFromProfile: true,
            applicantWorkIncomePrefilledFromProfile: true,
        }),
    );

    const update = (field: keyof LoanRequestPersonFormData, value: string) =>
        setPerson((current) => ({ ...current, [field]: value }));

    const loadSaved = async (id: number) => {
        const record = await onLoadSaved(id);

        if (record) {
            setPerson(record);
        }
    };

    return (
        <>
            <SheetHeader className="border-b border-border pr-14">
                <p className="text-[11px] font-bold tracking-[0.14em] text-muted-foreground uppercase">
                    Co-maker {number}
                </p>
                <SheetTitle className="text-xl font-bold">
                    Co-maker details
                </SheetTitle>
                <SheetDescription className="sr-only">
                    Name, contact and work details for co-maker {number}.
                </SheetDescription>
                {isPhone ? (
                    guided ? (
                        <LoanApplicationProgress
                            section="co"
                            substep={step + 1}
                            substeps={STEPS.length}
                        />
                    ) : (
                        <p className="text-sm font-semibold text-muted-foreground">
                            Step {step + 1} of {STEPS.length} ·{' '}
                            {STEPS[step].title}
                        </p>
                    )
                ) : null}
            </SheetHeader>

            <div className="wizard-fields min-w-0 flex-1 space-y-5 overflow-x-hidden overflow-y-auto p-4">
                {visibleSteps.map(({ section, title }) => (
                    <LoanRequestCoMakerStep
                        key={section}
                        title={title}
                        description={
                            section === 'basic'
                                ? 'Basic personal details.'
                                : section === 'contact'
                                  ? 'Address and contact details.'
                                  : 'Employment, employer, and income details.'
                        }
                        prefix={slot}
                        section={section}
                        values={person}
                        errors={errors}
                        onChange={update}
                        savedCoMakers={
                            section === 'basic' ? savedCoMakers : undefined
                        }
                        onLoadSavedCoMaker={loadSaved}
                        onRemoveSavedCoMaker={onRemoveSaved}
                        onSaveCoMaker={() => onSaveForReuse(person)}
                        isSavingCoMaker={isSavingForReuse}
                        bare
                    />
                ))}
            </div>

            <SheetFooter className="flex-row flex-wrap items-center gap-2.5 border-t border-border">
                <p
                    role="status"
                    className={cn(
                        'min-w-36 flex-1 text-xs',
                        missing.length > 0
                            ? 'text-amber-900 dark:text-amber-100'
                            : 'text-muted-foreground',
                    )}
                >
                    {!hasName
                        ? 'First and last name are required to save.'
                        : missing.length > 0
                          ? `Still needed: ${missing.join(', ')}`
                          : 'Ready to save'}
                </p>
                {isPhone && step > 0 ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11"
                        onClick={() => setStep(step - 1)}
                    >
                        Back
                    </Button>
                ) : null}
                <Button
                    type="button"
                    className="min-h-11 font-bold md:min-h-11"
                    disabled={isLastStep ? !hasName || isSaving : false}
                    onClick={() =>
                        isLastStep ? onSave(slot, person) : setStep(step + 1)
                    }
                >
                    {isLastStep ? 'Save co-maker' : 'Next'}
                </Button>
            </SheetFooter>
        </>
    );
}
