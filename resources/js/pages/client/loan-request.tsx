import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import LoanRequestController from '@/actions/App/Http/Controllers/Client/LoanRequestController';
import { LoanRequestAnimatedStep } from '@/components/loan-request/loan-request-animated-step';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import {
    chunkGlapiItemGroups,
    getGlapiItemGroups,
    GLAPI_GROUPS_PER_STEP,
    GLAPI_VIRTUAL_ITEMS,
    LoanRequestApplicantPersonalStep,
    LoanRequestApplicantWorkStep,
    LoanRequestCoMakerStep,
    LoanRequestDataSectionStep,
    LoanRequestDependentsStep,
    LoanRequestHealthQuestionnaireStep,
    LoanRequestHealthStep,
    LoanRequestInsuranceBeneficiariesStep,
    LoanRequestLoanDetailsStep,
    LoanRequestReviewStep,
    parseGlapiItem,
} from '@/components/loan-request/loan-request-steps';
import { LoanRequestSummaryPanel } from '@/components/loan-request/loan-request-summary-panel';
import { LoanRequestWizardActions } from '@/components/loan-request/loan-request-wizard-footer';
import { LoanRequestWizardShell } from '@/components/loan-request/loan-request-wizard-shell';
import { loanRequestWizardSteps as steps } from '@/components/loan-request/loan-request-wizard-steps';
import { PageShell } from '@/components/page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import client from '@/lib/api/client';
import { formatDateTime, toDateInputValue } from '@/lib/formatters';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import { dashboard as clientDashboard } from '@/routes/client';
import { index as loanRequestsIndex } from '@/routes/client/loan-requests';
import type { BreadcrumbItem } from '@/types';
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

const STEP_INDEX: Record<string, number> = Object.fromEntries(
    steps.map((step, index) => [step.id, index]),
);

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
    autoFilledDeclarations: AutoFilledDeclarations;
    bankingPrefilledFromProfile: boolean;
    insurancePrefilledFromProfile: boolean;
    dependentsPrefilledFromProfile: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: clientDashboard().url },
    { title: 'Loan Requests', href: loanRequestsIndexHref },
    { title: 'Loan request', href: LoanRequestController.create().url },
];

type LoanDetailField =
    | 'typecode'
    | 'requested_amount'
    | 'requested_term'
    | 'loan_purpose'
    | 'availment_status';

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
    'spouse_age',
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
    spouse_age: '',
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
        spouse_age: toStringValue(person.spouse_age),
        spouse_cell_no: person.spouse_cell_no ?? '',
        employment_type: person.employment_type ?? '',
        employer_business_name: person.employer_business_name ?? '',
        employer_business_address1: person.employer_business_address1 ?? '',
        employer_business_address2: person.employer_business_address2 ?? '',
        employer_business_address3: person.employer_business_address3 ?? '',
        employer_business_address_zip:
            person.employer_business_address_zip ?? '',
        telephone_no: person.telephone_no ?? '',
        current_position: person.current_position ?? '',
        nature_of_business: person.nature_of_business ?? '',
        years_in_work_business: person.years_in_work_business ?? '',
        employer_date_employed: person.employer_date_employed ?? '',
        gross_monthly_income: toStringValue(person.gross_monthly_income),
        payday: person.payday ?? '',
    };
};

// The GLAPI questionnaire's first sub-step (chunk 0) renders inside the
// 'health' wizard step itself; the remaining chunks occupy the step indices
// immediately after it.
const GLAPI_STEP_START = STEP_INDEX['health'];

const resolveStepFromErrors = (
    errors: Record<string, string | undefined>,
    glapiItemNumberToStepOffset: Record<string, number>,
): number | null => {
    const stepMatches: number[] = [];

    Object.keys(errors).forEach((key) => {
        if (!errors[key]) {
            return;
        }

        if (
            key === 'typecode' ||
            key === 'requested_amount' ||
            key === 'requested_term' ||
            key === 'loan_purpose' ||
            key === 'availment_status'
        ) {
            stepMatches.push(STEP_INDEX['loan-details']);
            return;
        }

        if (key.startsWith('applicant.')) {
            const field = key.replace('applicant.', '');
            stepMatches.push(
                applicantBasicFields.has(field)
                    ? STEP_INDEX['personal-basic']
                    : applicantContactFields.has(field)
                      ? STEP_INDEX['personal-contact']
                      : applicantFamilyFields.has(field)
                        ? STEP_INDEX['personal-family']
                        : applicantEmploymentFields.has(field)
                          ? STEP_INDEX['work-employment']
                          : personWorkFields.has(field)
                            ? STEP_INDEX['work-income']
                            : STEP_INDEX['personal-basic'],
            );
            return;
        }

        if (key.startsWith('co_maker_1.')) {
            const field = key.replace('co_maker_1.', '');
            stepMatches.push(
                applicantBasicFields.has(field)
                    ? STEP_INDEX['co-maker-1-basic']
                    : applicantContactFields.has(field) ||
                        field === 'educational_attainment'
                      ? STEP_INDEX['co-maker-1-contact']
                      : applicantEmploymentFields.has(field)
                        ? STEP_INDEX['co-maker-1-employment']
                        : personWorkFields.has(field)
                          ? STEP_INDEX['co-maker-1-income']
                          : STEP_INDEX['co-maker-1-basic'],
            );
            return;
        }

        if (key.startsWith('co_maker_2.')) {
            const field = key.replace('co_maker_2.', '');
            stepMatches.push(
                applicantBasicFields.has(field)
                    ? STEP_INDEX['co-maker-2-basic']
                    : applicantContactFields.has(field) ||
                        field === 'educational_attainment'
                      ? STEP_INDEX['co-maker-2-contact']
                      : applicantEmploymentFields.has(field)
                        ? STEP_INDEX['co-maker-2-employment']
                        : personWorkFields.has(field)
                          ? STEP_INDEX['co-maker-2-income']
                          : STEP_INDEX['co-maker-2-basic'],
            );
            return;
        }

        if (key.startsWith('insurance.') || key === 'document_data') {
            stepMatches.push(STEP_INDEX['insurance']);
            return;
        }

        if (key.startsWith('health.')) {
            const field = key.replace('health.', '');
            const chunkIndex = glapiItemNumberToStepOffset[field];

            stepMatches.push(
                chunkIndex !== undefined
                    ? GLAPI_STEP_START + chunkIndex
                    : STEP_INDEX['health'],
            );
            return;
        }

        if (key.startsWith('health_glapi.')) {
            const field = key.replace('health_glapi.', '');
            const itemNumber = parseGlapiItem(field)?.number ?? field;
            const chunkIndex = glapiItemNumberToStepOffset[itemNumber];

            stepMatches.push(GLAPI_STEP_START + (chunkIndex ?? 0));
            return;
        }

        if (key.startsWith('banking.')) {
            stepMatches.push(STEP_INDEX['banking']);
            return;
        }

        if (key.startsWith('declarations.')) {
            stepMatches.push(STEP_INDEX['declarations']);
            return;
        }

        if (key.startsWith('dependents.')) {
            stepMatches.push(STEP_INDEX['dependents']);
            return;
        }

        if (key === 'undertaking_accepted') {
            stepMatches.push(STEP_INDEX['review']);
        }
    });

    return stepMatches.length > 0 ? Math.min(...stepMatches) : null;
};

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
    autoFilledDeclarations,
    bankingPrefilledFromProfile,
    insurancePrefilledFromProfile,
    dependentsPrefilledFromProfile,
}: Props) {
    const [currentStep, setCurrentStep] = useState(initialStep);
    const [highestStepReached, setHighestStepReached] = useState(initialStep);
    const [stepDirection, setStepDirection] = useState<'forward' | 'backward'>(
        'forward',
    );
    const [bankAccountConfirmed, setBankAccountConfirmed] = useState(
        !bankingPrefilledFromProfile,
    );
    const [beneficiariesConfirmed, setBeneficiariesConfirmed] = useState(
        !insurancePrefilledFromProfile,
    );
    const [dependentsConfirmed, setDependentsConfirmed] = useState(
        !dependentsPrefilledFromProfile,
    );
    const [activeAction, setActiveAction] = useState<'draft' | 'submit' | null>(
        null,
    );
    const [lastAction, setLastAction] = useState<'draft' | 'submit' | null>(
        null,
    );
    const [draftState, setDraftState] = useState<LoanRequestDraft | null>(
        draft,
    );
    const [savedCoMakers, setSavedCoMakers] =
        useState<SavedCoMakerOption[]>(initialSavedCoMakers);

    const glapiChunks = chunkGlapiItemGroups(
        getGlapiItemGroups(dataSectionDefinitions.health_glapi),
        GLAPI_GROUPS_PER_STEP,
    );

    const glapiItemNumberToStepOffset: Record<string, number> = {};

    glapiChunks.forEach((chunk, chunkIndex) => {
        chunk.forEach((group) => {
            glapiItemNumberToStepOffset[group.number] = chunkIndex;
        });
    });

    GLAPI_VIRTUAL_ITEMS.forEach((virtual) => {
        if (virtual.afterNumber in glapiItemNumberToStepOffset) {
            glapiItemNumberToStepOffset[virtual.key] =
                glapiItemNumberToStepOffset[virtual.afterNumber];
        }
    });

    const initialFormData: LoanRequestFormData = {
        typecode: draft?.typecode ?? loanTypes[0]?.typecode ?? '',
        requested_amount: toStringValue(draft?.requested_amount),
        requested_term: toStringValue(draft?.requested_term),
        loan_purpose: draft?.loan_purpose ?? '',
        availment_status: draft?.availment_status ?? '',
        undertaking_accepted: false,
        applicant: toPersonForm(applicant),
        co_maker_1: toPersonForm(coMakerOne),
        co_maker_2: toPersonForm(coMakerTwo),
        insurance: {
            ...dataSections.insurance,
        },
        health: {
            ...dataSections.health,
        },
        health_glapi: {
            ...dataSections.health_glapi,
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
    const isFirstStep = currentStep === 0;
    const isLastStep = currentStep === steps.length - 1;
    const isReviewStep = isLastStep;
    const isSavingDraft = activeAction === 'draft';
    const isSubmitting = form.processing && activeAction === 'submit';
    const hasLoanTypes = loanTypes.length > 0;

    useEffect(() => {
        setDraftState(draft);
    }, [draft]);

    const handleStepChange = (step: number) => {
        if (step === currentStep) {
            return;
        }

        setStepDirection(step > currentStep ? 'forward' : 'backward');
        setCurrentStep(step);
    };

    const handleNextStep = () => {
        if (currentStep >= steps.length - 1) {
            return;
        }

        const nextStep = currentStep + 1;
        handleStepChange(nextStep);
        setHighestStepReached((prev) => Math.max(prev, nextStep));
    };

    const handlePreviousStep = () => {
        if (currentStep === 0) {
            return;
        }

        handleStepChange(currentStep - 1);
    };

    const handleLoanDetailChange = (field: LoanDetailField, value: string) => {
        form.setData(field, value);
    };

    const updatePersonField =
        (personKey: 'applicant' | 'co_maker_1' | 'co_maker_2') =>
        (field: keyof LoanRequestPersonFormData, value: string) => {
            form.setData(personKey, {
                ...form.data[personKey],
                [field]: value,
            });
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

    const toggleSaveCoMakerForReuse =
        (personKey: 'co_maker_1' | 'co_maker_2') => (checked: boolean) => {
            form.setData(personKey, {
                ...form.data[personKey],
                save_for_reuse: checked,
            });
        };

    const updateDataSection =
        (
            sectionKey: keyof Pick<
                LoanRequestFormData,
                | 'insurance'
                | 'health'
                | 'health_glapi'
                | 'banking'
                | 'declarations'
                | 'dependents'
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

    const handleSaveDraft = async () => {
        setActiveAction('draft');

        try {
            if (!draftState) {
                const response = await client.patch<LoanRequestDraft>(
                    LoanRequestController.draft().url,
                    { ...form.data, wizard_step: highestStepReached },
                );
                setDraftState(response.data);
            } else {
                await client.patch(
                    LoanRequestController.saveDraft(draftState).url,
                    { ...form.data, wizard_step: highestStepReached },
                );
            }
            showSuccessToast('Draft saved.', { id: 'manual-save-draft' });
            setLastAction('draft');
        } catch {
            showErrorToast(null, 'Unable to save draft.', {
                id: 'manual-save-draft',
            });
        } finally {
            setActiveAction(null);
        }
    };

    const handleSubmit = () => {
        setActiveAction('submit');
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

                const step = resolveStepFromErrors(
                    errors,
                    glapiItemNumberToStepOffset,
                );

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan request" />
            <PageShell size="wide" className="gap-9 pt-8">
                <div className="rounded-2xl border border-border/40 bg-card/60 p-6 shadow-sm sm:p-7 lg:p-8">
                    <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                        <div className="space-y-2">
                            <p className="text-xs font-semibold tracking-[0.24em] text-muted-foreground uppercase">
                                Loan request
                            </p>
                            <h1 className="text-3xl font-semibold tracking-tight">
                                Apply for a loan
                            </h1>
                            <p className="max-w-2xl text-sm text-muted-foreground">
                                Complete the application form and save a draft
                                at any time. Signatures will be collected
                                physically upon loan release.
                            </p>
                            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span className="rounded-full bg-muted/30 px-2 py-1">
                                    Account No: {member.acctno ?? '--'}
                                </span>
                                {draftState ? (
                                    <>
                                        <LoanRequestStatusBadge
                                            status={draftState.status}
                                        />
                                        {draftUpdatedAt ? (
                                            <span>
                                                Last saved {draftUpdatedAt}
                                            </span>
                                        ) : null}
                                    </>
                                ) : (
                                    <span>No draft saved yet</span>
                                )}
                                {form.recentlySuccessful &&
                                lastAction === 'draft' ? (
                                    <span className="text-emerald-600">
                                        Draft saved.
                                    </span>
                                ) : null}
                            </div>
                        </div>
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="gap-2 self-start"
                        >
                            <Link href={loanRequestsIndexHref}>
                                <ArrowLeft className="h-4 w-4" />
                                Back to loan requests
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-2xl border border-border/40 bg-card/60 shadow-sm">
                    <LoanRequestWizardShell
                        currentStep={currentStep}
                        onStepClick={handleStepChange}
                        contentClassName="p-6 sm:p-7 lg:p-8"
                        footer={
                            <div className="mt-8">
                                <LoanRequestWizardActions
                                    isFirstStep={isFirstStep}
                                    isLastStep={isLastStep}
                                    onBack={handlePreviousStep}
                                    onNext={handleNextStep}
                                    onSaveDraft={handleSaveDraft}
                                    onSubmit={handleSubmit}
                                    isSavingDraft={isSavingDraft}
                                    isSubmitting={isSubmitting}
                                    disablePrimary={
                                        !hasLoanTypes ||
                                        (currentStep ===
                                            STEP_INDEX['insurance'] &&
                                            insurancePrefilledFromProfile &&
                                            !beneficiariesConfirmed) ||
                                        (currentStep ===
                                            STEP_INDEX['banking'] &&
                                            bankingPrefilledFromProfile &&
                                            (form.data.banking
                                                .release_method ===
                                                'Bank Transfer' ||
                                                form.data.banking
                                                    .release_method ===
                                                    'ATM') &&
                                            !bankAccountConfirmed) ||
                                        (currentStep ===
                                            STEP_INDEX['dependents'] &&
                                            dependentsPrefilledFromProfile &&
                                            !dependentsConfirmed) ||
                                        (currentStep ===
                                            STEP_INDEX['declarations'] &&
                                            (form.data.declarations
                                                .declaration_truth_confirmation !==
                                                true ||
                                                form.data.declarations
                                                    .declaration_data_privacy_consent !==
                                                    true))
                                    }
                                />
                            </div>
                        }
                    >
                        <div
                            className={
                                isReviewStep
                                    ? 'grid gap-8'
                                    : 'grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:gap-10 xl:grid-cols-[minmax(0,1fr)_24rem]'
                            }
                        >
                            <div className="space-y-8">
                                {loanTypes.length === 0 ? (
                                    <Alert variant="destructive">
                                        <AlertTitle>
                                            Loan types unavailable
                                        </AlertTitle>
                                        <AlertDescription>
                                            Please contact support to load
                                            available loan options before
                                            submitting a request.
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
                                    show={currentStep === 1}
                                    direction={stepDirection}
                                >
                                    <LoanRequestApplicantPersonalStep
                                        section="basic"
                                        values={form.data.applicant}
                                        errors={form.errors}
                                        readOnly={applicantReadOnly}
                                        onChange={updatePersonField(
                                            'applicant',
                                        )}
                                    />
                                </LoanRequestAnimatedStep>

                                <LoanRequestAnimatedStep
                                    show={currentStep === 2}
                                    direction={stepDirection}
                                >
                                    <LoanRequestApplicantPersonalStep
                                        section="contact"
                                        values={form.data.applicant}
                                        errors={form.errors}
                                        readOnly={applicantReadOnly}
                                        onChange={updatePersonField(
                                            'applicant',
                                        )}
                                    />
                                </LoanRequestAnimatedStep>

                                <LoanRequestAnimatedStep
                                    show={currentStep === 3}
                                    direction={stepDirection}
                                >
                                    <LoanRequestApplicantPersonalStep
                                        section="family"
                                        values={form.data.applicant}
                                        errors={form.errors}
                                        readOnly={applicantReadOnly}
                                        onChange={updatePersonField(
                                            'applicant',
                                        )}
                                    />
                                </LoanRequestAnimatedStep>

                                <LoanRequestAnimatedStep
                                    show={
                                        currentStep === STEP_INDEX['dependents']
                                    }
                                    direction={stepDirection}
                                >
                                    <div className="space-y-5">
                                        <LoanRequestDependentsStep
                                            sectionKey="dependents"
                                            title="Dependents"
                                            description="Add any dependents applicable to you. This section is optional."
                                            values={form.data.dependents}
                                            definition={
                                                dataSectionDefinitions.dependents
                                            }
                                            errors={form.errors}
                                            crossSectionValues={{
                                                'applicant.civil_status':
                                                    form.data.applicant
                                                        .civil_status,
                                            }}
                                            onChange={updateDataSection(
                                                'dependents',
                                            )}
                                            hasExistingProfileData={
                                                dependentsPrefilledFromProfile
                                            }
                                        />

                                        {dependentsPrefilledFromProfile ? (
                                            <LoanRequestSectionCard
                                                title="Confirm dependents"
                                                description="These details were pre-filled from your member profile. Please confirm they are still accurate."
                                            >
                                                <div className="flex items-start gap-3">
                                                    <Checkbox
                                                        id="dependents_confirmed"
                                                        checked={
                                                            dependentsConfirmed
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            setDependentsConfirmed(
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                    <Label htmlFor="dependents_confirmed">
                                                        Confirm these dependents
                                                        are still correct
                                                    </Label>
                                                </div>
                                            </LoanRequestSectionCard>
                                        ) : null}
                                    </div>
                                </LoanRequestAnimatedStep>

                                <LoanRequestAnimatedStep
                                    show={
                                        currentStep ===
                                        STEP_INDEX['work-employment']
                                    }
                                    direction={stepDirection}
                                >
                                    <LoanRequestApplicantWorkStep
                                        section="employment"
                                        values={form.data.applicant}
                                        errors={form.errors}
                                        onChange={updatePersonField(
                                            'applicant',
                                        )}
                                    />
                                </LoanRequestAnimatedStep>

                                <LoanRequestAnimatedStep
                                    show={
                                        currentStep ===
                                        STEP_INDEX['work-income']
                                    }
                                    direction={stepDirection}
                                >
                                    <LoanRequestApplicantWorkStep
                                        section="income"
                                        values={form.data.applicant}
                                        errors={form.errors}
                                        onChange={updatePersonField(
                                            'applicant',
                                        )}
                                    />
                                </LoanRequestAnimatedStep>

                                <LoanRequestAnimatedStep
                                    show={
                                        currentStep ===
                                        STEP_INDEX['co-maker-1-basic']
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
                                        onChange={updatePersonField(
                                            'co_maker_1',
                                        )}
                                        savedCoMakers={savedCoMakers}
                                        onLoadSavedCoMaker={loadSavedCoMaker(
                                            'co_maker_1',
                                        )}
                                        onRemoveSavedCoMaker={
                                            removeSavedCoMaker
                                        }
                                    />
                                </LoanRequestAnimatedStep>

                                <LoanRequestAnimatedStep
                                    show={
                                        currentStep ===
                                        STEP_INDEX['co-maker-1-contact']
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
                                        onChange={updatePersonField(
                                            'co_maker_1',
                                        )}
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
                                        title="Co-maker 1 — employment"
                                        description="Employment and employer details for your first co-maker."
                                        prefix="co_maker_1"
                                        section="employment"
                                        values={form.data.co_maker_1}
                                        errors={form.errors}
                                        onChange={updatePersonField(
                                            'co_maker_1',
                                        )}
                                    />
                                </LoanRequestAnimatedStep>

                                <LoanRequestAnimatedStep
                                    show={
                                        currentStep ===
                                        STEP_INDEX['co-maker-1-income']
                                    }
                                    direction={stepDirection}
                                >
                                    <LoanRequestCoMakerStep
                                        title="Co-maker 1 — income & details"
                                        description="Income and business details for your first co-maker."
                                        prefix="co_maker_1"
                                        section="income"
                                        values={form.data.co_maker_1}
                                        errors={form.errors}
                                        onChange={updatePersonField(
                                            'co_maker_1',
                                        )}
                                        onToggleSaveForReuse={toggleSaveCoMakerForReuse(
                                            'co_maker_1',
                                        )}
                                    />
                                </LoanRequestAnimatedStep>

                                <LoanRequestAnimatedStep
                                    show={
                                        currentStep ===
                                        STEP_INDEX['co-maker-2-basic']
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
                                        onChange={updatePersonField(
                                            'co_maker_2',
                                        )}
                                        savedCoMakers={savedCoMakers}
                                        onLoadSavedCoMaker={loadSavedCoMaker(
                                            'co_maker_2',
                                        )}
                                        onRemoveSavedCoMaker={
                                            removeSavedCoMaker
                                        }
                                    />
                                </LoanRequestAnimatedStep>

                                <LoanRequestAnimatedStep
                                    show={
                                        currentStep ===
                                        STEP_INDEX['co-maker-2-contact']
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
                                        onChange={updatePersonField(
                                            'co_maker_2',
                                        )}
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
                                        title="Co-maker 2 — employment"
                                        description="Employment and employer details for your second co-maker."
                                        prefix="co_maker_2"
                                        section="employment"
                                        values={form.data.co_maker_2}
                                        errors={form.errors}
                                        onChange={updatePersonField(
                                            'co_maker_2',
                                        )}
                                    />
                                </LoanRequestAnimatedStep>

                                <LoanRequestAnimatedStep
                                    show={
                                        currentStep ===
                                        STEP_INDEX['co-maker-2-income']
                                    }
                                    direction={stepDirection}
                                >
                                    <LoanRequestCoMakerStep
                                        title="Co-maker 2 — income & details"
                                        description="Income and business details for your second co-maker."
                                        prefix="co_maker_2"
                                        section="income"
                                        values={form.data.co_maker_2}
                                        errors={form.errors}
                                        onChange={updatePersonField(
                                            'co_maker_2',
                                        )}
                                        onToggleSaveForReuse={toggleSaveCoMakerForReuse(
                                            'co_maker_2',
                                        )}
                                    />
                                </LoanRequestAnimatedStep>

                                <LoanRequestAnimatedStep
                                    show={
                                        currentStep === STEP_INDEX['insurance']
                                    }
                                    direction={stepDirection}
                                >
                                    <div className="space-y-5">
                                        <LoanRequestInsuranceBeneficiariesStep
                                            sectionKey="insurance"
                                            title="Insurance and beneficiaries"
                                            description="Provide beneficiary details that will be reused across the required documents."
                                            values={form.data.insurance}
                                            definition={
                                                dataSectionDefinitions.insurance
                                            }
                                            errors={form.errors}
                                            onChange={updateDataSection(
                                                'insurance',
                                            )}
                                        />

                                        {insurancePrefilledFromProfile ? (
                                            <LoanRequestSectionCard
                                                title="Confirm beneficiaries"
                                                description="These details were pre-filled from your member profile. A wrong beneficiary can delay or invalidate a claim, so please confirm they are still accurate."
                                            >
                                                <div className="flex items-start gap-3">
                                                    <Checkbox
                                                        id="beneficiaries_confirmed"
                                                        checked={
                                                            beneficiariesConfirmed
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            setBeneficiariesConfirmed(
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                    <Label htmlFor="beneficiaries_confirmed">
                                                        Confirm these
                                                        beneficiaries are still
                                                        correct
                                                    </Label>
                                                </div>
                                            </LoanRequestSectionCard>
                                        ) : null}
                                    </div>
                                </LoanRequestAnimatedStep>

                                <LoanRequestAnimatedStep
                                    show={currentStep === STEP_INDEX['health']}
                                    direction={stepDirection}
                                >
                                    <LoanRequestHealthStep
                                        healthValues={form.data.health}
                                        healthDefinition={
                                            dataSectionDefinitions.health
                                        }
                                        glapiValues={form.data.health_glapi}
                                        glapiDefinition={
                                            dataSectionDefinitions.health_glapi
                                        }
                                        glapiTitle={`Health Insurance Questionnaire (1 of ${glapiChunks.length})`}
                                        glapiDescription="Answer each item honestly -- this is required for insurance coverage on this loan."
                                        glapiItemNumbers={(
                                            glapiChunks[0] ?? []
                                        ).map((group) => group.number)}
                                        errors={form.errors}
                                        crossSectionValues={{
                                            'applicant.sex':
                                                form.data.applicant.sex,
                                        }}
                                        onHealthChange={updateDataSection(
                                            'health',
                                        )}
                                        onGlapiChange={updateDataSection(
                                            'health_glapi',
                                        )}
                                    />
                                </LoanRequestAnimatedStep>

                                {glapiChunks
                                    .slice(1)
                                    .map((chunk, offsetIndex) => {
                                        const chunkIndex = offsetIndex + 1;

                                        return (
                                            <LoanRequestAnimatedStep
                                                key={`health-glapi-${chunkIndex}`}
                                                show={
                                                    currentStep ===
                                                    GLAPI_STEP_START +
                                                        chunkIndex
                                                }
                                                direction={stepDirection}
                                            >
                                                <LoanRequestHealthQuestionnaireStep
                                                    sectionKey="health_glapi"
                                                    title={`Health Insurance Questionnaire (${chunkIndex + 1} of ${glapiChunks.length})`}
                                                    description="Answer each item honestly -- this is required for insurance coverage on this loan."
                                                    values={
                                                        form.data.health_glapi
                                                    }
                                                    definition={
                                                        dataSectionDefinitions.health_glapi
                                                    }
                                                    errors={form.errors}
                                                    crossSectionValues={{
                                                        'applicant.sex':
                                                            form.data.applicant
                                                                .sex,
                                                    }}
                                                    onChange={updateDataSection(
                                                        'health_glapi',
                                                    )}
                                                    itemNumbers={chunk.map(
                                                        (group) => group.number,
                                                    )}
                                                    healthValues={
                                                        form.data.health
                                                    }
                                                    healthDefinition={
                                                        dataSectionDefinitions.health
                                                    }
                                                    onHealthChange={updateDataSection(
                                                        'health',
                                                    )}
                                                />
                                            </LoanRequestAnimatedStep>
                                        );
                                    })}

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
                                            definition={
                                                dataSectionDefinitions.banking
                                            }
                                            errors={form.errors}
                                            onChange={updateDataSection(
                                                'banking',
                                            )}
                                            applicantFullName={member.name}
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
                                                <div className="flex items-start gap-3">
                                                    <Checkbox
                                                        id="bank_account_confirmed"
                                                        checked={
                                                            bankAccountConfirmed
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            setBankAccountConfirmed(
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                    <Label htmlFor="bank_account_confirmed">
                                                        Confirm this bank
                                                        account is still correct
                                                    </Label>
                                                </div>
                                            </LoanRequestSectionCard>
                                        ) : null}
                                    </div>
                                </LoanRequestAnimatedStep>

                                <LoanRequestAnimatedStep
                                    show={
                                        currentStep ===
                                        STEP_INDEX['declarations']
                                    }
                                    direction={stepDirection}
                                >
                                    <LoanRequestDataSectionStep
                                        sectionKey="declarations"
                                        title="Personal declarations and consent"
                                        description="Complete the declarations and consent items required before processing can begin."
                                        values={form.data.declarations}
                                        definition={
                                            dataSectionDefinitions.declarations
                                        }
                                        errors={form.errors}
                                        onChange={updateDataSection(
                                            'declarations',
                                        )}
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
                                        sectionDefinitions={
                                            dataSectionDefinitions
                                        }
                                        onUndertakingChange={(value) =>
                                            form.setData(
                                                'undertaking_accepted',
                                                value,
                                            )
                                        }
                                    />
                                </LoanRequestAnimatedStep>
                            </div>

                            {!isReviewStep && (
                                <LoanRequestSummaryPanel
                                    data={form.data}
                                    loanTypes={loanTypes}
                                    member={member}
                                    draft={draftState}
                                    draftUpdatedAt={draftUpdatedAt}
                                />
                            )}
                        </div>
                    </LoanRequestWizardShell>
                </div>
            </PageShell>
        </AppLayout>
    );
}
