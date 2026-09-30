import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import LoanRequestController from '@/actions/App/Http/Controllers/Client/LoanRequestController';
import { LoanRequestAnimatedStep } from '@/components/loan-request/loan-request-animated-step';
import { LoanRequestCheckRow } from '@/components/loan-request/loan-request-check-row';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import { GROUP_META } from '@/components/loan-request/loan-request-step-indicator';
import { LoanRequestStepper } from '@/components/loan-request/loan-request-stepper';
import {
    LoanRequestApplicantPersonalStep,
    LoanRequestApplicantWorkStep,
    LoanRequestCoMakerStep,
    LoanRequestDataSectionStep,
    LoanRequestLoanDetailsStep,
    LoanRequestReviewStep,
} from '@/components/loan-request/loan-request-steps';
import {
    LoanRequestSummaryBar,
    LoanRequestSummaryPanel,
} from '@/components/loan-request/loan-request-summary-panel';
import { LoanRequestWizardActions } from '@/components/loan-request/loan-request-wizard-footer';
import { LoanRequestWizardHeader } from '@/components/loan-request/loan-request-wizard-header';
import {
    buildStepIndex,
    getVisibleWizardSteps,
} from '@/components/loan-request/loan-request-wizard-steps';
import {
    closeInlineRows,
    InlineEditProvider,
} from '@/components/settings/inline-edit-row';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { focusField } from '@/components/ui/form-error-summary';
import client from '@/lib/api/client';
import { formatDateTime, toDateInputValue } from '@/lib/formatters';
import { getStepMissingFields } from '@/lib/loan-request-step-validation';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import { index as loanRequestsIndex } from '@/routes/client/loan-requests';
import { edit as editProfile } from '@/routes/profile';
import type {
    AutoFilledDeclarations,
    LoanRequestDataSectionDefinitions,
    LoanRequestDataSections,
    LoanRequestDataSectionValues,
    LoanRequestDraft,
    LoanRequestFormData,
    LoanRequestMemberSummary,
    LoanRequestPersonData,
    LoanRequestPersonFormData,
    LoanRequestReadOnlyMap,
    LoanTypeOption,
    SavedCoMakerOption,
} from '@/types/loan-requests';

const loanRequestsIndexHref = loanRequestsIndex().url;

/**
 * No wizard steps are currently skipped. Kept as infrastructure in case a
 * future step needs conditional skipping again.
 */
const EMPTY_SKIPPED_STEP_IDS: ReadonlySet<string> = new Set();

type Props = {
    loanTypes: LoanTypeOption[];
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    applicantReadOnly: LoanRequestReadOnlyMap | null;
    savedCoMakers: SavedCoMakerOption[];
    member: LoanRequestMemberSummary;
    dataSections: LoanRequestDataSections;
    dataSectionDefinitions: LoanRequestDataSectionDefinitions;
    draft: LoanRequestDraft | null;
    initialStep: number;
    initialStepId?: string | null;
    autoFilledDeclarations: AutoFilledDeclarations;
    bankingPrefilledFromProfile: boolean;
    applicantPrefilledFromProfile: boolean;
    applicantWorkIncomePrefilledFromProfile: boolean;
    missingIdentityPrerequisites: string[];
};

type LoanDetailField =
    | 'typecode'
    | 'requested_amount'
    | 'requested_term'
    | 'loan_purpose'
    | 'other_loan_type_name'
    | 'availment_status'
    | 'requested_payment_frequency'
    | 'kind_of_loan';

const applicantBasicFields = new Set([
    'first_name',
    'last_name',
    'middle_name',
    'nickname',
    'birthdate',
    'birthplace_city',
    'birthplace_province',
    'sex',
]);

const applicantContactFields = new Set([
    'address1',
    'address2',
    'address3',
    'length_of_stay',
    'housing_status',
    'cell_no',
]);

const applicantFamilyFields = new Set([
    'civil_status',
    'educational_attainment',
    'number_of_children',
    'spouse_name',
    'spouse_birthdate',
    'spouse_cell_no',
]);

const personWorkFields = new Set([
    'employment_type',
    'employer_business_name',
    'employer_business_address1',
    'employer_business_address2',
    'employer_business_address3',
    'telephone_no',
    'current_position',
    'nature_of_business',
    'years_in_work_business',
    'employer_date_employed',
    'gross_monthly_income',
    'payday',
]);

const applicantEmploymentFields = new Set([
    'employment_type',
    'employer_business_name',
    'employer_business_address1',
    'employer_business_address2',
    'employer_business_address3',
]);

const toStringValue = (
    value?: string | number | null,
    options?: { emptyIfZero?: boolean },
): string => {
    if (value === null || value === undefined) {
        return '';
    }

    const stringValue = `${value}`.trim();
    const emptyIfZero = options?.emptyIfZero ?? true;

    if (emptyIfZero && (stringValue === '0' || stringValue === '0.00')) {
        return '';
    }

    return stringValue;
};

const emptyPerson: LoanRequestPersonFormData = {
    first_name: '',
    middle_name: '',
    last_name: '',
    nickname: '',
    birthdate: '',
    birthplace_city: '',
    birthplace_province: '',
    address1: '',
    address_barangay: '',
    address2: '',
    address3: '',
    address_zip: '',
    length_of_stay: '',
    housing_status: '',
    cell_no: '',
    civil_status: '',
    sex: '',
    educational_attainment: '',
    number_of_children: '',
    spouse_name: '',
    spouse_birthdate: '',
    spouse_cell_no: '',
    employment_type: '',
    employer_business_name: '',
    employer_business_address1: '',
    employer_business_address_barangay: '',
    employer_business_address2: '',
    employer_business_address3: '',
    employer_business_address_zip: '',
    telephone_no: '',
    current_position: '',
    nature_of_business: '',
    institutional_employer_category: '',
    years_in_work_business: '',
    employer_date_employed: '',
    gross_monthly_income: '',
    payday: '',
    save_for_reuse: false,
    saved_co_maker_id: '',
    saved_co_maker_label: '',
};

const toPersonForm = (
    person: LoanRequestPersonData | null,
): LoanRequestPersonFormData => {
    if (!person) {
        return { ...emptyPerson };
    }

    return {
        ...emptyPerson,
        first_name: person.first_name ?? '',
        middle_name: person.middle_name ?? '',
        last_name: person.last_name ?? '',
        nickname: person.nickname ?? '',
        birthdate: toDateInputValue(person.birthdate),
        birthplace_city: person.birthplace_city ?? '',
        birthplace_province: person.birthplace_province ?? '',
        address1: person.address1 ?? '',
        address_barangay: person.address_barangay ?? '',
        address2: person.address2 ?? '',
        address3: person.address3 ?? '',
        address_zip: person.address_zip ?? '',
        length_of_stay: person.length_of_stay ?? '',
        housing_status: person.housing_status ?? '',
        cell_no: person.cell_no ?? '',
        civil_status: person.civil_status ?? '',
        sex: person.sex ?? '',
        educational_attainment: person.educational_attainment ?? '',
        number_of_children: toStringValue(person.number_of_children, {
            emptyIfZero: false,
        }),
        spouse_name: person.spouse_name ?? '',
        spouse_birthdate: toDateInputValue(person.spouse_birthdate),
        spouse_cell_no: person.spouse_cell_no ?? '',
        employment_type: person.employment_type ?? '',
        employer_business_name: person.employer_business_name ?? '',
        employer_business_address1: person.employer_business_address1 ?? '',
        employer_business_address_barangay:
            person.employer_business_address_barangay ?? '',
        employer_business_address2: person.employer_business_address2 ?? '',
        employer_business_address3: person.employer_business_address3 ?? '',
        employer_business_address_zip:
            person.employer_business_address_zip ?? '',
        telephone_no: person.telephone_no ?? '',
        current_position: person.current_position ?? '',
        nature_of_business: person.nature_of_business ?? '',
        institutional_employer_category:
            person.institutional_employer_category ?? '',
        years_in_work_business: person.years_in_work_business ?? '',
        employer_date_employed: person.employer_date_employed ?? '',
        gross_monthly_income: toStringValue(person.gross_monthly_income),
        payday: person.payday ?? '',
    };
};

type ApplicantConfirmSectionKey = 'basic' | 'contact' | 'family' | 'income';

// Classifies an `applicant.*` field into the confirm-step section it belongs
// to, shared between error-to-step routing and the auto-unlock-on-error
// logic (a validation error on a hidden/locked field must both navigate to
// its step AND unlock that section so the field is actually visible).
const classifyApplicantField = (field: string): ApplicantConfirmSectionKey => {
    if (applicantBasicFields.has(field)) return 'basic';
    if (applicantContactFields.has(field)) return 'contact';
    if (applicantFamilyFields.has(field)) return 'family';
    if (applicantEmploymentFields.has(field)) return 'income';
    if (personWorkFields.has(field)) return 'income';
    return 'basic';
};

const resolveStepForErrorKey = (
    key: string,
    stepIndex: Record<string, number>,
): number | null => {
    if (
        key === 'typecode' ||
        key === 'requested_amount' ||
        key === 'requested_term' ||
        key === 'loan_purpose' ||
        key === 'other_loan_type_name' ||
        key === 'availment_status'
    ) {
        return stepIndex['loan-details'];
    }

    if (key.startsWith('applicant.')) {
        const field = key.replace('applicant.', '');
        const section = classifyApplicantField(field);
        const stepId =
            section === 'basic'
                ? 'personal-basic'
                : section === 'contact'
                  ? 'personal-contact'
                  : section === 'family'
                    ? 'personal-family'
                    : 'work-employment';

        // personal-contact/personal-family are absent from stepIndex when
        // collapsed into the "Confirm your details" step, and work-income is
        // always absent (collapsed into work-employment) -- fall back to
        // wherever that content actually lives now.
        return (
            stepIndex[stepId] ??
            stepIndex['personal-basic'] ??
            stepIndex['work-employment']
        );
    }

    if (key.startsWith('co_maker_1.')) {
        const field = key.replace('co_maker_1.', '');
        return applicantBasicFields.has(field)
            ? stepIndex['co-maker-1-basic']
            : applicantContactFields.has(field) ||
                field === 'educational_attainment'
              ? stepIndex['co-maker-1-contact']
              : stepIndex['co-maker-1-employment'];
    }

    if (key.startsWith('co_maker_2.')) {
        const field = key.replace('co_maker_2.', '');
        return applicantBasicFields.has(field)
            ? stepIndex['co-maker-2-basic']
            : applicantContactFields.has(field) ||
                field === 'educational_attainment'
              ? stepIndex['co-maker-2-contact']
              : stepIndex['co-maker-2-employment'];
    }

    if (key.startsWith('banking.')) {
        return stepIndex['banking'];
    }

    if (key.startsWith('declarations.')) {
        return stepIndex['declarations'];
    }

    if (key === 'undertaking_accepted') {
        return stepIndex['review'];
    }

    return null;
};

const resolveStepFromErrors = (
    errors: Record<string, string | undefined>,
    stepIndex: Record<string, number>,
): number | null => {
    const stepMatches = Object.keys(errors)
        .filter((key) => Boolean(errors[key]))
        .map((key) => resolveStepForErrorKey(key, stepIndex))
        .filter((step): step is number => step !== null);

    return stepMatches.length > 0 ? Math.min(...stepMatches) : null;
};

/** Preselects the loan type linked from the welcome page (?typecode=). */
function queryTypecode(loanTypes: LoanTypeOption[]): string | undefined {
    if (typeof window === 'undefined') {
        return undefined;
    }

    const value = new URLSearchParams(window.location.search).get('typecode');

    return loanTypes.find((type) => type.typecode === value)?.typecode;
}

export default function LoanRequestPage({
    loanTypes,
    applicant,
    coMakerOne,
    coMakerTwo,
    applicantReadOnly,
    savedCoMakers: initialSavedCoMakers,
    member,
    dataSections,
    dataSectionDefinitions,
    draft,
    initialStep,
    initialStepId = null,
    autoFilledDeclarations,
    bankingPrefilledFromProfile,
    applicantPrefilledFromProfile,
    applicantWorkIncomePrefilledFromProfile,
    missingIdentityPrerequisites,
}: Props) {
    const steps = useMemo(() => getVisibleWizardSteps(), []);
    const STEP_INDEX = useMemo(() => buildStepIndex(steps), [steps]);

    // Resume on the step the member last worked on. The id survives step
    // list changes; a legacy draft only has an index, which may be past the
    // end of today's list.
    const savedStepIndex = initialStepId
        ? steps.findIndex((step) => step.id === initialStepId)
        : -1;
    const clampedStep = Math.min(initialStep, steps.length - 1);
    const startStep = savedStepIndex >= 0 ? savedStepIndex : clampedStep;
    const [currentStep, setCurrentStep] = useState(startStep);
    const [highestStepReached, setHighestStepReached] = useState(
        Math.max(startStep, clampedStep),
    );
    const [stepDirection, setStepDirection] = useState<'forward' | 'backward'>(
        'forward',
    );
    const [bankAccountConfirmed, setBankAccountConfirmed] = useState(
        !bankingPrefilledFromProfile,
    );
    const [applicantPersonalConfirmed, setApplicantPersonalConfirmed] =
        useState(!applicantPrefilledFromProfile);
    const [applicantWorkIncomeConfirmed, setApplicantWorkIncomeConfirmed] =
        useState(!applicantWorkIncomePrefilledFromProfile);
    const [updateProfile, setUpdateProfile] = useState(true);
    const [activeAction, setActiveAction] = useState<'draft' | 'submit' | null>(
        null,
    );
    const [, setLastAction] = useState<'draft' | 'submit' | null>(null);
    const [draftState, setDraftState] = useState<LoanRequestDraft | null>(
        draft,
    );
    const [savedCoMakers, setSavedCoMakers] =
        useState<SavedCoMakerOption[]>(initialSavedCoMakers);
    const [savingCoMakerSlot, setSavingCoMakerSlot] = useState<
        'co_maker_1' | 'co_maker_2' | null
    >(null);

    const initialFormData: LoanRequestFormData = {
        typecode:
            draft?.typecode ??
            queryTypecode(loanTypes) ??
            loanTypes[0]?.typecode ??
            '',
        requested_amount: toStringValue(draft?.requested_amount),
        requested_term: toStringValue(draft?.requested_term),
        loan_purpose: draft?.loan_purpose ?? '',
        other_loan_type_name: draft?.other_loan_type_name ?? '',
        availment_status: draft?.availment_status ?? '',
        requested_payment_frequency: draft?.requested_payment_frequency ?? '',
        kind_of_loan: draft?.kind_of_loan ?? '',
        undertaking_accepted: false,
        applicant: toPersonForm(applicant),
        co_maker_1: toPersonForm(coMakerOne),
        co_maker_2: toPersonForm(coMakerTwo),
        insurance: {
            ...dataSections.insurance,
        },
        banking: {
            ...dataSections.banking,
        },
        declarations: {
            ...dataSections.declarations,
            ...autoFilledDeclarations,
        },
        dependents: {
            ...dataSections.dependents,
        },
    };

    const form = useForm<LoanRequestFormData>(initialFormData);
    // Applicant values as last saved to the draft -- "Cancel" on an About you
    // row restores them.
    const savedApplicantRef = useRef(form.data.applicant);
    const isFirstStep = currentStep === 0;
    const isLastStep = currentStep === steps.length - 1;
    const isReviewStep = isLastStep;
    const isSavingDraft = activeAction === 'draft';
    const isSubmitting = form.processing && activeAction === 'submit';
    const hasLoanTypes = loanTypes.length > 0;

    const skippedStepIds = EMPTY_SKIPPED_STEP_IDS;

    const isBankingComplete = useMemo(() => {
        const banking = form.data.banking;
        const releaseMethod = banking.release_method;
        const paymentOption = banking.payment_option;

        if (!releaseMethod || !paymentOption) {
            return false;
        }

        if (
            (releaseMethod === 'ATM' || releaseMethod === 'Bank Transfer') &&
            !banking.release_saved_account_id
        ) {
            return false;
        }

        if (
            (paymentOption === 'ATM Deduction' ||
                paymentOption === 'Bank Transfer') &&
            !banking.payment_saved_account_id
        ) {
            return false;
        }

        return true;
    }, [form.data.banking]);

    const isApplicantPersonalComplete = useMemo(() => {
        if (!applicantPrefilledFromProfile) return true;
        return applicantPersonalConfirmed;
    }, [applicantPrefilledFromProfile, applicantPersonalConfirmed]);

    const isApplicantWorkIncomeComplete = useMemo(() => {
        if (!applicantWorkIncomePrefilledFromProfile) return true;
        return applicantWorkIncomeConfirmed;
    }, [applicantWorkIncomePrefilledFromProfile, applicantWorkIncomeConfirmed]);

    const isDeclarationsComplete = useMemo(() => {
        const declarations = form.data.declarations;
        return (
            declarations.declaration_truth_confirmation === true &&
            declarations.declaration_data_privacy_consent === true
        );
    }, [form.data.declarations]);

    useEffect(() => {
        setDraftState(draft);
    }, [draft]);

    // If the member skips insurance/health after already having navigated
    // into that group (or a restored draft lands there), jump forward past
    // it so a skipped step is never left showing.
    useEffect(() => {
        if (!skippedStepIds.has(steps[currentStep]?.id)) {
            return;
        }

        let adjusted = currentStep;
        while (
            adjusted < steps.length - 1 &&
            skippedStepIds.has(steps[adjusted].id)
        ) {
            adjusted += 1;
        }

        if (adjusted !== currentStep) {
            setCurrentStep(adjusted);
        }
    }, [skippedStepIds, currentStep, steps]);

    const handleStepChange = (step: number) => {
        if (step === currentStep) {
            return;
        }

        setStepDirection(step > currentStep ? 'forward' : 'backward');
        setCurrentStep(step);
    };

    // The review step lists errors from every step at once, so clicking one
    // may need to jump to a different step before the erroring field exists
    // in the DOM to focus. The animated step transition mounts the target
    // step's content as soon as `currentStep` changes, so a short delay
    // after navigating is enough for the field to be focusable.
    const handleErrorClick = (key: string) => {
        const step = resolveStepForErrorKey(key, STEP_INDEX);

        if (step === null || step === currentStep) {
            focusField(key);
            return;
        }

        handleStepChange(step);
        window.setTimeout(() => focusField(key), 250);
    };

    const currentStepMissingFields = useMemo(
        () =>
            getStepMissingFields(steps[currentStep]?.id, form.data, {
                applicantPrefilledFromProfile,
                applicantWorkIncomePrefilledFromProfile,
            }),
        [
            steps,
            currentStep,
            form.data,
            applicantPrefilledFromProfile,
            applicantWorkIncomePrefilledFromProfile,
        ],
    );

    // Everything that must be satisfied before leaving the current step:
    // missing required fields plus the "confirm pre-filled data" checkboxes
    // and completeness flags. Drives the footer alert, the Next button, and
    // the sidebar step navigation so none of them can bypass a gate.
    const currentStepId = steps[currentStep]?.id;
    const currentStepBlockers = [...currentStepMissingFields];

    if (
        currentStepId === 'personal-basic' &&
        applicantPrefilledFromProfile &&
        !applicantPersonalConfirmed
    ) {
        currentStepBlockers.push('Confirmation that your details are correct');
    }

    if (
        currentStepId === 'personal-basic' &&
        applicantWorkIncomePrefilledFromProfile &&
        !applicantWorkIncomeConfirmed
    ) {
        currentStepBlockers.push('Confirmation of your work & income details');
    }

    if (currentStepId === 'banking') {
        if (!isBankingComplete) {
            currentStepBlockers.push(
                'Release method, payment option, and saved accounts',
            );
        }

        if (
            bankingPrefilledFromProfile &&
            (form.data.banking.release_method === 'Bank Transfer' ||
                form.data.banking.release_method === 'ATM') &&
            !bankAccountConfirmed
        ) {
            currentStepBlockers.push('Confirmation of your bank details');
        }
    }

    if (currentStepId === 'declarations') {
        if (form.data.declarations.declaration_truth_confirmation !== true) {
            currentStepBlockers.push('Truthfulness declaration');
        }

        if (form.data.declarations.declaration_data_privacy_consent !== true) {
            currentStepBlockers.push('Data privacy consent');
        }
    }

    // Sidebar clicks: going back is always free; going forward requires the
    // current step to be clear and the target to be a step already reached
    // (later steps must be unlocked via Next).
    const handleSidebarStepClick = (step: number) => {
        if (
            step > currentStep &&
            (currentStepBlockers.length > 0 || step > highestStepReached)
        ) {
            return;
        }

        handleStepChange(step);
    };

    const handleNextStep = () => {
        if (currentStep >= steps.length - 1) {
            return;
        }

        if (currentStepBlockers.length > 0) {
            return;
        }

        let nextStep = currentStep + 1;
        while (
            nextStep < steps.length - 1 &&
            skippedStepIds.has(steps[nextStep].id)
        ) {
            nextStep += 1;
        }

        handleStepChange(nextStep);
        setHighestStepReached((prev) => Math.max(prev, nextStep));
    };

    const handlePreviousStep = () => {
        if (currentStep === 0) {
            return;
        }

        let prevStep = currentStep - 1;
        while (prevStep > 0 && skippedStepIds.has(steps[prevStep].id)) {
            prevStep -= 1;
        }

        handleStepChange(prevStep);
    };

    const handleLoanDetailChange = (field: LoanDetailField, value: string) => {
        form.setData(field, value);
    };

    const updatePersonField =
        (personKey: 'applicant' | 'co_maker_1' | 'co_maker_2') =>
        (field: keyof LoanRequestPersonFormData, value: string) => {
            form.setData((previousData) => ({
                ...previousData,
                [personKey]: {
                    ...previousData[personKey],
                    [field]: value,
                },
            }));
        };

    // Explicit opt-in only: loading a saved co-maker fills the fields as a
    // starting point (still fully editable), and saving one back for reuse
    // requires its own separate checkbox -- nothing here is silent. See
    // SavedCoMakersService.
    const loadSavedCoMaker =
        (personKey: 'co_maker_1' | 'co_maker_2') => async (id: number) => {
            try {
                const response = await client.get<{
                    ok: boolean;
                    data: LoanRequestPersonData & { label: string | null };
                }>(`/client/co-makers/${id}`);
                const record = response.data.data;

                form.setData(personKey, {
                    ...toPersonForm(record),
                    save_for_reuse: form.data[personKey].save_for_reuse,
                    saved_co_maker_id: String(id),
                    saved_co_maker_label: record.label ?? '',
                });
            } catch (error) {
                showErrorToast(error, 'Unable to load that saved co-maker.');
            }
        };

    const removeSavedCoMaker = async (id: number) => {
        try {
            await client.delete(`/client/co-makers/${id}`);
            setSavedCoMakers((current) =>
                current.filter((option) => option.id !== id),
            );
        } catch (error) {
            showErrorToast(error, 'Unable to remove that saved co-maker.');
        }
    };

    // Explicit action only: saving a co-maker for reuse is a direct button
    // press, not a passive checkbox that silently applies at submit time.
    // The backend matches on normalized name + birthdate/cell number to
    // avoid forking a duplicate contact when the member re-enters the same
    // person's details from scratch instead of loading them from the saved
    // list. See SavedCoMakersService::findDuplicate().
    const saveCoMakerNow =
        (personKey: 'co_maker_1' | 'co_maker_2') => async () => {
            const person = form.data[personKey];

            setSavingCoMakerSlot(personKey);

            try {
                const response = await client.post<{
                    ok: boolean;
                    data: LoanRequestPersonData & {
                        id: number;
                        label: string | null;
                    };
                    duplicate: boolean;
                }>('/client/co-makers', {
                    ...person,
                    saved_co_maker_id: person.saved_co_maker_id || null,
                    label: person.saved_co_maker_label || null,
                });
                const record = response.data.data;
                const isDuplicate = response.data.duplicate;

                form.setData(personKey, {
                    ...person,
                    saved_co_maker_id: String(record.id),
                    saved_co_maker_label: record.label ?? '',
                });

                setSavedCoMakers((current) => {
                    const option: SavedCoMakerOption = {
                        id: record.id,
                        label: record.label ?? '',
                        last_used_at: new Date().toISOString(),
                    };
                    const withoutExisting = current.filter(
                        (existing) => existing.id !== record.id,
                    );

                    return [option, ...withoutExisting];
                });

                showSuccessToast(
                    isDuplicate
                        ? "Already saved -- updated this co-maker's details."
                        : 'Co-maker saved for reuse.',
                );
            } catch (error) {
                showErrorToast(error, 'Unable to save this co-maker.');
            } finally {
                setSavingCoMakerSlot(null);
            }
        };

    const updateDataSection =
        (
            sectionKey: keyof Pick<
                LoanRequestFormData,
                'insurance' | 'banking' | 'declarations'
            >,
        ) =>
        (field: string, value: string | number | boolean | null) => {
            form.setData((current) => ({
                ...current,
                [sectionKey]: {
                    ...(current[sectionKey] as LoanRequestDataSectionValues),
                    [field]: value,
                },
            }));
        };

    const handleSaveDraft = async (): Promise<boolean> => {
        setActiveAction('draft');

        try {
            if (!draftState) {
                const response = await client.patch<LoanRequestDraft>(
                    LoanRequestController.draft().url,
                    {
                        ...form.data,
                        wizard_step: highestStepReached,
                        wizard_step_id: steps[currentStep]?.id,
                    },
                );
                setDraftState(response.data);
            } else {
                await client.patch(
                    LoanRequestController.saveDraft(draftState).url,
                    {
                        ...form.data,
                        wizard_step: highestStepReached,
                        wizard_step_id: steps[currentStep]?.id,
                    },
                );
            }
            savedApplicantRef.current = form.data.applicant;
            showSuccessToast('Draft saved.', { id: 'manual-save-draft' });
            setLastAction('draft');

            return true;
        } catch {
            showErrorToast(null, 'Unable to save draft.', {
                id: 'manual-save-draft',
            });

            return false;
        } finally {
            setActiveAction(null);
        }
    };

    const handleSubmit = () => {
        setActiveAction('submit');
        form.transform((data) => ({
            ...data,
            update_profile: updateProfile,
        }));
        form.post(LoanRequestController.store().url, {
            onSuccess: () => {
                showSuccessToast('Loan request submitted for review.', {
                    id: 'loan-request-submit',
                });
            },
            onError: (errors) => {
                if (errors.loan_prerequisites) {
                    showErrorToast(
                        errors.loan_prerequisites,
                        errors.loan_prerequisites,
                        {
                            id: 'loan-request-submit',
                        },
                    );

                    return;
                }

                const step = resolveStepFromErrors(errors, STEP_INDEX);

                if (step !== null) {
                    handleStepChange(step);
                }

                if (Object.keys(errors).length === 0) {
                    showErrorToast(null, 'Unable to submit the loan request.', {
                        id: 'loan-request-submit',
                    });
                }
            },
            onFinish: () => setActiveAction(null),
        });
    };

    const draftUpdatedAt = draftState?.updated_at
        ? formatDateTime(draftState.updated_at)
        : null;

    const [isDiscardingDraft, setIsDiscardingDraft] = useState(false);

    const handleDiscardDraft = async () => {
        if (!draftState) {
            return;
        }

        setIsDiscardingDraft(true);

        try {
            await client.delete(
                LoanRequestController.discardDraft(draftState.id).url,
            );
            showSuccessToast('Draft discarded.');
            router.visit(loanRequestsIndexHref);
        } catch (error) {
            showErrorToast(error, 'Unable to discard this draft.');
        } finally {
            setIsDiscardingDraft(false);
        }
    };

    const handleSaveAboutYouRow = async () => {
        if (await handleSaveDraft()) {
            closeInlineRows();
        }
    };

    const handleCancelAboutYouRow = () => {
        const saved = savedApplicantRef.current;

        (Object.keys(saved) as (keyof typeof saved)[]).forEach((field) => {
            if (form.data.applicant[field] !== saved[field]) {
                updatePersonField('applicant')(field, saved[field] as string);
            }
        });
        closeInlineRows();
    };

    const handleSaveAndExit = async () => {
        if (await handleSaveDraft()) {
            router.visit(loanRequestsIndexHref);
        }
    };

    const currentStepMeta = steps[currentStep];
    const groupSteps = steps.filter(
        (step) => step.group === currentStepMeta.group,
    );
    const groupLabel =
        GROUP_META[currentStepMeta.group as keyof typeof GROUP_META]?.label ??
        currentStepMeta.title;
    const eyebrow =
        groupSteps.length > 1
            ? `${groupLabel} · Part ${groupSteps.indexOf(currentStepMeta) + 1} of ${groupSteps.length}`
            : groupLabel;
    const hasSavedDraft = draftState?.status === 'draft';

    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            <Head title="Loan request" />
            <LoanRequestWizardHeader
                accountNo={member.acctno ?? '--'}
                lastSaved={draftUpdatedAt}
                confirmExit={form.isDirty || hasSavedDraft}
                canDiscard={hasSavedDraft}
                isSaving={isSavingDraft}
                isDiscarding={isDiscardingDraft}
                onExit={() => router.visit(loanRequestsIndexHref)}
                onSaveAndExit={handleSaveAndExit}
                onDiscard={handleDiscardDraft}
            />
            <div className="border-b border-border bg-card">
                <div className="mx-auto w-full max-w-[1040px] px-4 py-4 md:px-7 md:py-5">
                    <LoanRequestStepper
                        steps={steps}
                        currentStep={currentStep}
                        highestStepReached={highestStepReached}
                        onStepClick={handleSidebarStepClick}
                    />
                </div>
            </div>

            <main className="wizard-fields mx-auto w-full max-w-[1040px] flex-1 px-4 py-6 md:px-7 md:py-8">
                <div className="mb-5 space-y-1">
                    <p className="text-xs font-bold tracking-wide text-primary uppercase">
                        {eyebrow}
                    </p>
                    <h2 className="text-2xl font-bold tracking-tight md:text-[30px]">
                        {currentStepMeta.title}
                    </h2>
                </div>

                {!isReviewStep ? (
                    <div className="mb-5 min-[900px]:hidden">
                        <LoanRequestSummaryBar
                            data={form.data}
                            loanTypes={loanTypes}
                            member={member}
                            draft={draftState}
                            draftUpdatedAt={draftUpdatedAt}
                        />
                    </div>
                ) : null}

                <div
                    className={
                        isReviewStep
                            ? 'grid gap-8'
                            : 'grid gap-6 min-[900px]:grid-cols-[minmax(0,1fr)_320px]'
                    }
                >
                    <div className="min-w-0 space-y-8">
                        {loanTypes.length === 0 ? (
                            <Alert variant="destructive">
                                <AlertTitle>Loan types unavailable</AlertTitle>
                                <AlertDescription>
                                    Please contact support to load available
                                    loan options before submitting a request.
                                </AlertDescription>
                            </Alert>
                        ) : null}

                        <LoanRequestAnimatedStep
                            show={currentStep === 0}
                            direction={stepDirection}
                        >
                            <LoanRequestLoanDetailsStep
                                data={form.data}
                                errors={form.errors}
                                loanTypes={loanTypes}
                                onChange={handleLoanDetailChange}
                            />
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={currentStep === STEP_INDEX['personal-basic']}
                            direction={stepDirection}
                        >
                            {missingIdentityPrerequisites.length > 0 ? (
                                <Alert variant="destructive" className="mb-5">
                                    <AlertTitle>
                                        Complete your profile before continuing
                                    </AlertTitle>
                                    <AlertDescription className="space-y-2">
                                        <p>
                                            Please add these details in your
                                            Profile Settings before submitting a
                                            loan request:{' '}
                                            {missingIdentityPrerequisites.join(
                                                ', ',
                                            )}
                                            .
                                        </p>
                                        <Button asChild size="sm">
                                            <Link href={editProfile().url}>
                                                Go to Profile Settings
                                            </Link>
                                        </Button>
                                    </AlertDescription>
                                </Alert>
                            ) : null}
                            <InlineEditProvider
                                rowMode
                                processing={isSavingDraft}
                                onSave={handleSaveAboutYouRow}
                                onDiscard={handleCancelAboutYouRow}
                            >
                                <div className="space-y-5">
                                    <LoanRequestApplicantPersonalStep
                                        section="basic"
                                        values={form.data.applicant}
                                        errors={form.errors}
                                        readOnly={applicantReadOnly}
                                        onChange={updatePersonField(
                                            'applicant',
                                        )}
                                    />
                                    <LoanRequestApplicantPersonalStep
                                        section="contact"
                                        values={form.data.applicant}
                                        errors={form.errors}
                                        readOnly={applicantReadOnly}
                                        onChange={updatePersonField(
                                            'applicant',
                                        )}
                                        contactNumberOnFile={member.telephone}
                                    />
                                    <LoanRequestApplicantPersonalStep
                                        section="family"
                                        values={form.data.applicant}
                                        errors={form.errors}
                                        readOnly={applicantReadOnly}
                                        onChange={updatePersonField(
                                            'applicant',
                                        )}
                                    />
                                    {applicantPrefilledFromProfile ? (
                                        <LoanRequestSectionCard
                                            title="Confirm your details"
                                            description="These details were pre-filled from your member profile. Please confirm they are still accurate."
                                        >
                                            <LoanRequestCheckRow
                                                id="applicant_personal_confirmed"
                                                checked={
                                                    applicantPersonalConfirmed
                                                }
                                                onCheckedChange={
                                                    setApplicantPersonalConfirmed
                                                }
                                            >
                                                These details are still correct
                                            </LoanRequestCheckRow>
                                        </LoanRequestSectionCard>
                                    ) : null}
                                    <LoanRequestApplicantWorkStep
                                        values={form.data.applicant}
                                        errors={form.errors}
                                        onChange={updatePersonField(
                                            'applicant',
                                        )}
                                    />
                                    {applicantWorkIncomePrefilledFromProfile ? (
                                        <LoanRequestSectionCard
                                            title="Confirm work & income details"
                                            description="These details were pre-filled from a previous loan request. Please confirm they are still accurate."
                                        >
                                            <LoanRequestCheckRow
                                                id="applicant_work_income_confirmed"
                                                checked={
                                                    applicantWorkIncomeConfirmed
                                                }
                                                onCheckedChange={
                                                    setApplicantWorkIncomeConfirmed
                                                }
                                            >
                                                Confirm these details are still
                                                correct
                                            </LoanRequestCheckRow>
                                        </LoanRequestSectionCard>
                                    ) : null}
                                </div>
                            </InlineEditProvider>
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={
                                currentStep === STEP_INDEX['co-maker-1-basic']
                            }
                            direction={stepDirection}
                        >
                            <LoanRequestCoMakerStep
                                title="Co-maker 1 — basic info"
                                description="Basic personal details for your first co-maker."
                                prefix="co_maker_1"
                                section="basic"
                                values={form.data.co_maker_1}
                                errors={form.errors}
                                onChange={updatePersonField('co_maker_1')}
                                savedCoMakers={savedCoMakers.filter(
                                    (option) =>
                                        String(option.id) !==
                                        form.data.co_maker_2.saved_co_maker_id,
                                )}
                                onLoadSavedCoMaker={loadSavedCoMaker(
                                    'co_maker_1',
                                )}
                                onRemoveSavedCoMaker={removeSavedCoMaker}
                            />
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={
                                currentStep === STEP_INDEX['co-maker-1-contact']
                            }
                            direction={stepDirection}
                        >
                            <LoanRequestCoMakerStep
                                title="Co-maker 1 — address & contact"
                                description="Address and contact details for your first co-maker."
                                prefix="co_maker_1"
                                section="contact"
                                values={form.data.co_maker_1}
                                errors={form.errors}
                                onChange={updatePersonField('co_maker_1')}
                            />
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={
                                currentStep ===
                                STEP_INDEX['co-maker-1-employment']
                            }
                            direction={stepDirection}
                        >
                            <LoanRequestCoMakerStep
                                title="Co-maker 1 — work & income"
                                description="Employment, employer, and income details for your first co-maker."
                                prefix="co_maker_1"
                                section="all"
                                values={form.data.co_maker_1}
                                errors={form.errors}
                                onChange={updatePersonField('co_maker_1')}
                                onSaveCoMaker={saveCoMakerNow('co_maker_1')}
                                isSavingCoMaker={
                                    savingCoMakerSlot === 'co_maker_1'
                                }
                            />
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={
                                currentStep === STEP_INDEX['co-maker-2-basic']
                            }
                            direction={stepDirection}
                        >
                            <LoanRequestCoMakerStep
                                title="Co-maker 2 — basic info"
                                description="Basic personal details for your second co-maker."
                                prefix="co_maker_2"
                                section="basic"
                                values={form.data.co_maker_2}
                                errors={form.errors}
                                onChange={updatePersonField('co_maker_2')}
                                savedCoMakers={savedCoMakers.filter(
                                    (option) =>
                                        String(option.id) !==
                                        form.data.co_maker_1.saved_co_maker_id,
                                )}
                                onLoadSavedCoMaker={loadSavedCoMaker(
                                    'co_maker_2',
                                )}
                                onRemoveSavedCoMaker={removeSavedCoMaker}
                            />
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={
                                currentStep === STEP_INDEX['co-maker-2-contact']
                            }
                            direction={stepDirection}
                        >
                            <LoanRequestCoMakerStep
                                title="Co-maker 2 — address & contact"
                                description="Address and contact details for your second co-maker."
                                prefix="co_maker_2"
                                section="contact"
                                values={form.data.co_maker_2}
                                errors={form.errors}
                                onChange={updatePersonField('co_maker_2')}
                            />
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={
                                currentStep ===
                                STEP_INDEX['co-maker-2-employment']
                            }
                            direction={stepDirection}
                        >
                            <LoanRequestCoMakerStep
                                title="Co-maker 2 — work & income"
                                description="Employment, employer, and income details for your second co-maker."
                                prefix="co_maker_2"
                                section="all"
                                values={form.data.co_maker_2}
                                errors={form.errors}
                                onChange={updatePersonField('co_maker_2')}
                                onSaveCoMaker={saveCoMakerNow('co_maker_2')}
                                isSavingCoMaker={
                                    savingCoMakerSlot === 'co_maker_2'
                                }
                            />
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={currentStep === STEP_INDEX['banking']}
                            direction={stepDirection}
                        >
                            <div className="space-y-5">
                                <LoanRequestDataSectionStep
                                    sectionKey="banking"
                                    title="Loan Disbursement & Repayment"
                                    description="Tell us how you'd like to receive your loan and how you'll repay it."
                                    values={form.data.banking}
                                    definition={dataSectionDefinitions.banking}
                                    errors={form.errors}
                                    onChange={updateDataSection('banking')}
                                    applicantFullName={member.name}
                                    applicantEmployerBusinessName={
                                        form.data.applicant
                                            .employer_business_name
                                    }
                                    applicantEmploymentType={
                                        form.data.applicant.employment_type
                                    }
                                    applicantNatureOfBusiness={
                                        form.data.applicant.nature_of_business
                                    }
                                    applicantInstitutionalEmployerCategory={
                                        form.data.applicant
                                            .institutional_employer_category
                                    }
                                />

                                {bankingPrefilledFromProfile &&
                                (form.data.banking.release_method ===
                                    'Bank Transfer' ||
                                    form.data.banking.release_method ===
                                        'ATM') ? (
                                    <LoanRequestSectionCard
                                        title="Confirm bank details"
                                        description="These details were pre-filled from your member profile."
                                    >
                                        <LoanRequestCheckRow
                                            id="bank_account_confirmed"
                                            checked={bankAccountConfirmed}
                                            onCheckedChange={
                                                setBankAccountConfirmed
                                            }
                                        >
                                            Confirm this bank account is still
                                            correct
                                        </LoanRequestCheckRow>
                                    </LoanRequestSectionCard>
                                ) : null}
                            </div>
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={currentStep === STEP_INDEX['declarations']}
                            direction={stepDirection}
                        >
                            <LoanRequestDataSectionStep
                                sectionKey="declarations"
                                title="Personal declarations and consent"
                                description="Complete the declarations and consent items required before processing can begin."
                                values={form.data.declarations}
                                definition={dataSectionDefinitions.declarations}
                                errors={form.errors}
                                onChange={updateDataSection('declarations')}
                            />
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={currentStep === STEP_INDEX['review']}
                            direction={stepDirection}
                        >
                            <LoanRequestReviewStep
                                data={form.data}
                                loanTypes={loanTypes}
                                member={member}
                                errors={form.errors}
                                sectionDefinitions={dataSectionDefinitions}
                                onUndertakingChange={(value) =>
                                    form.setData('undertaking_accepted', value)
                                }
                                updateProfile={updateProfile}
                                onUpdateProfileChange={setUpdateProfile}
                                onErrorClick={handleErrorClick}
                            />
                        </LoanRequestAnimatedStep>
                    </div>

                    {!isReviewStep && (
                        <aside className="hidden min-[900px]:block">
                            <LoanRequestSummaryPanel
                                data={form.data}
                                loanTypes={loanTypes}
                                member={member}
                                draft={draftState}
                                draftUpdatedAt={draftUpdatedAt}
                            />
                        </aside>
                    )}
                </div>
            </main>

            <LoanRequestWizardActions
                isFirstStep={isFirstStep}
                isLastStep={isLastStep}
                page={currentStep + 1}
                totalPages={steps.length}
                blockers={currentStepBlockers}
                onBack={handlePreviousStep}
                onNext={handleNextStep}
                onSubmit={handleSubmit}
                isSavingDraft={isSavingDraft}
                isSubmitting={isSubmitting}
                disablePrimary={
                    !hasLoanTypes ||
                    currentStepBlockers.length > 0 ||
                    (currentStep === STEP_INDEX['personal-basic'] &&
                        missingIdentityPrerequisites.length > 0) ||
                    (isLastStep &&
                        (!isBankingComplete ||
                            !isDeclarationsComplete ||
                            !isApplicantPersonalComplete ||
                            !isApplicantWorkIncomeComplete ||
                            missingIdentityPrerequisites.length > 0))
                }
            />
        </div>
    );
}
