import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronLeft, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import LoanRequestController from '@/actions/App/Http/Controllers/Client/LoanRequestController';
import { CoMakerSheet } from '@/components/loan-request/co-maker-sheet';
import { LoanApplicationCalculator } from '@/components/loan-request/loan-application-calculator';
import {
    LoanEstimateBreakdown,
    LoanEstimateFigures,
    LoanEstimateWarnings,
    loanEstimateNote,
    loanEstimateWarnings,
} from '@/components/loan-request/loan-application-estimate';
import {
    LoanApplicationOverview,
    SectionStatusBadge,
} from '@/components/loan-request/loan-application-overview';
import { LoanApplicationProgress } from '@/components/loan-request/loan-application-progress';
import { LoanRequestAboutYouAccordion } from '@/components/loan-request/loan-request-about-you-accordion';
import { LoanRequestCheckRow } from '@/components/loan-request/loan-request-check-row';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import {
    LoanRequestDataSectionStep,
    LoanRequestLoanDetailsStep,
    LoanRequestReviewStep,
} from '@/components/loan-request/loan-request-steps';
import { LoanRequestWizardHeader } from '@/components/loan-request/loan-request-wizard-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { focusField } from '@/components/ui/form-error-summary';
import { useLoanEstimate } from '@/hooks/loan-request/use-loan-estimate';
import { useIsMobile } from '@/hooks/use-mobile';
import client from '@/lib/api/client';
import {
    formatCurrency,
    formatDateTime,
    toDateInputValue,
} from '@/lib/formatters';
import {
    APPLICATION_SECTIONS,
    deriveSectionStatus,
    nextIncompleteSection,
    SECTION_LABELS,
    sectionErrorMessages,
    sectionForErrorKey,
    type ApplicationSection,
    type SectionStatus,
} from '@/lib/loan-application-flow';
import { getStepMissingFields } from '@/lib/loan-request-step-validation';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { index as loanRequestsIndex } from '@/routes/client/loan-requests';
import { edit as editProfile } from '@/routes/profile';
import type {
    AutoFilledDeclarations,
    LoanEstimateLimits,
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
    LoanWizardConfirmation,
    SavedCoMakerOption,
} from '@/types/loan-requests';

const loanRequestsIndexHref = loanRequestsIndex().url;

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
    wizardConfirmations: LoanWizardConfirmation[];
    estimateLimits: LoanEstimateLimits;
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

type CoMakerSlot = 'co_maker_1' | 'co_maker_2';

/** calc -> hub (overview) -> a section -> review. */
type View = 'calc' | 'hub' | ApplicationSection | 'review';

/**
 * Where a section was opened from: `flow` = guided first pass (progress bar,
 * "Save and continue"), otherwise save returns to that screen.
 */
type Origin = 'flow' | 'hub' | 'review';

const SECTION_DESCRIPTIONS: Record<ApplicationSection, string> = {
    loan: 'Choose the amount, term and purpose. Your estimate updates as you type.',
    about: 'Pre-filled from your member profile. Check each group, then confirm.',
    co: 'Your loan needs two co-makers. They sign the documents with you at release.',
    disb: 'How you receive the loan and how you pay it back.',
    decl: 'Read and accept each statement before you submit.',
};

const CO_MAKER_STEP_IDS = [
    'co-maker-1-basic',
    'co-maker-1-contact',
    'co-maker-1-employment',
    'co-maker-2-basic',
    'co-maker-2-contact',
    'co-maker-2-employment',
];
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
    savedCoMakers: initialSavedCoMakers,
    member,
    dataSections,
    dataSectionDefinitions,
    draft,
    wizardConfirmations,
    estimateLimits,
    autoFilledDeclarations,
    bankingPrefilledFromProfile,
    applicantPrefilledFromProfile,
    applicantWorkIncomePrefilledFromProfile,
    missingIdentityPrerequisites,
}: Props) {
    const isPhone = useIsMobile(640);
    // A returning applicant (saved draft) lands on the overview; a first-time
    // applicant starts at the calculator.
    const [view, setView] = useState<View>(draft ? 'hub' : 'calc');
    const [origin, setOrigin] = useState<Origin | null>(null);
    // Mobile only: Loan details is split into amount & term, then purpose.
    const [loanPart, setLoanPart] = useState<0 | 1>(0);
    const [coMakerSlot, setCoMakerSlot] = useState<CoMakerSlot | null>(null);
    const [confirmSubmitOpen, setConfirmSubmitOpen] = useState(false);
    const [confirmations, setConfirmations] = useState(
        () => new Set<LoanWizardConfirmation>(wizardConfirmations),
    );
    const [updateProfile, setUpdateProfile] = useState(true);
    const [activeAction, setActiveAction] = useState<'draft' | 'submit' | null>(
        null,
    );
    const [draftState, setDraftState] = useState<LoanRequestDraft | null>(
        draft,
    );
    const [savedCoMakers, setSavedCoMakers] =
        useState<SavedCoMakerOption[]>(initialSavedCoMakers);
    const [isSavingCoMakerForReuse, setIsSavingCoMakerForReuse] =
        useState(false);
    const [isDiscardingDraft, setIsDiscardingDraft] = useState(false);

    const form = useForm<LoanRequestFormData>({
        typecode:
            draft?.typecode ??
            queryTypecode(loanTypes) ??
            loanTypes[0]?.typecode ??
            '',
        requested_amount: toStringValue(draft?.requested_amount),
        // Calculator starting point only; nothing is saved until "Apply".
        requested_term:
            toStringValue(draft?.requested_term) ||
            String(Math.min(12, estimateLimits.maxTermMonths)),
        loan_purpose: draft?.loan_purpose ?? '',
        other_loan_type_name: draft?.other_loan_type_name ?? '',
        availment_status: draft?.availment_status ?? '',
        requested_payment_frequency: draft?.requested_payment_frequency ?? '',
        kind_of_loan: draft?.kind_of_loan ?? '',
        undertaking_accepted: false,
        applicant: toPersonForm(applicant),
        co_maker_1: toPersonForm(coMakerOne),
        co_maker_2: toPersonForm(coMakerTwo),
        insurance: { ...dataSections.insurance },
        banking: { ...dataSections.banking },
        declarations: {
            ...dataSections.declarations,
            ...autoFilledDeclarations,
        },
        dependents: { ...dataSections.dependents },
    });
    const isSavingDraft = activeAction === 'draft';
    const isSubmitting = form.processing && activeAction === 'submit';
    const hasSavedDraft = draftState?.status === 'draft';

    useEffect(() => {
        setDraftState(draft);
    }, [draft]);

    const { estimate, isLoading: isEstimating } = useLoanEstimate({
        amount: form.data.requested_amount,
        term: form.data.requested_term,
        maxTerm: estimateLimits.maxTermMonths,
        typecode: form.data.typecode,
        paymentFrequency:
            form.data.requested_payment_frequency || form.data.applicant.payday,
        applicantBirthdate: form.data.applicant.birthdate || null,
    });
    const estimateWarnings = loanEstimateWarnings({
        amount: form.data.requested_amount,
        term: form.data.requested_term,
        maxTerm: estimateLimits.maxTermMonths,
        grossMonthlyIncome: form.data.applicant.gross_monthly_income,
        estimate,
    });

    // --- Section status, derived from the (saved) form data -------------

    const validationContext = {
        applicantPrefilledFromProfile,
        applicantWorkIncomePrefilledFromProfile,
    };
    const missingFor = (stepId: string) =>
        getStepMissingFields(stepId, form.data, validationContext);
    const isConfirmed = (key: LoanWizardConfirmation, prefilled: boolean) =>
        !prefilled || confirmations.has(key);
    const personalConfirmed = isConfirmed(
        'applicant_personal',
        applicantPrefilledFromProfile,
    );
    const workIncomeConfirmed = isConfirmed(
        'applicant_work_income',
        applicantWorkIncomePrefilledFromProfile,
    );
    const needsBankConfirmation =
        bankingPrefilledFromProfile &&
        (form.data.banking.release_method === 'Bank Transfer' ||
            form.data.banking.release_method === 'ATM');
    const bankConfirmed =
        !needsBankConfirmation || confirmations.has('bank_account');
    const banking = form.data.banking;
    const isBankingComplete =
        Boolean(banking.release_method && banking.payment_option) &&
        !(
            (banking.release_method === 'ATM' ||
                banking.release_method === 'Bank Transfer') &&
            !banking.release_saved_account_id
        ) &&
        !(
            (banking.payment_option === 'ATM Deduction' ||
                banking.payment_option === 'Bank Transfer') &&
            !banking.payment_saved_account_id
        );
    const declarations = form.data.declarations;
    const coMakerMissing = (slot: CoMakerSlot) =>
        CO_MAKER_STEP_IDS.filter((id) =>
            id.startsWith(slot === 'co_maker_1' ? 'co-maker-1' : 'co-maker-2'),
        ).flatMap(missingFor);
    const hasCoMaker = (slot: CoMakerSlot) =>
        form.data[slot].first_name.trim() !== '' ||
        form.data[slot].last_name.trim() !== '';

    // What still blocks each section; [] = done.
    const blockers: Record<ApplicationSection, string[]> = {
        loan: missingFor('loan-details'),
        about: [
            ...missingFor('personal-basic'),
            ...(personalConfirmed
                ? []
                : ['Confirmation that your details are correct']),
            ...(workIncomeConfirmed
                ? []
                : ['Confirmation of your work & income details']),
            ...(missingIdentityPrerequisites.length > 0
                ? [
                      'Profile details: ' +
                          missingIdentityPrerequisites.join(', '),
                  ]
                : []),
        ],
        co: [
            ...(hasCoMaker('co_maker_1')
                ? coMakerMissing('co_maker_1').map((f) => `Co-maker 1: ${f}`)
                : ['Co-maker 1']),
            ...(hasCoMaker('co_maker_2')
                ? coMakerMissing('co_maker_2').map((f) => `Co-maker 2: ${f}`)
                : ['Co-maker 2']),
        ],
        disb: [
            ...(isBankingComplete
                ? []
                : ['Release method, payment option, and saved accounts']),
            ...(bankConfirmed ? [] : ['Confirmation of your bank details']),
        ],
        decl: [
            ...(declarations.declaration_truth_confirmation === true
                ? []
                : ['Truthfulness declaration']),
            ...(declarations.declaration_data_privacy_consent === true
                ? []
                : ['Data privacy consent']),
        ],
    };
    const started: Record<ApplicationSection, boolean> = {
        loan: Boolean(
            draftState &&
            (form.data.requested_amount || form.data.loan_purpose),
        ),
        about:
            confirmations.has('applicant_personal') ||
            confirmations.has('applicant_work_income') ||
            (!applicantPrefilledFromProfile &&
                form.data.applicant.first_name !== ''),
        co: hasCoMaker('co_maker_1') || hasCoMaker('co_maker_2'),
        disb: Boolean(banking.release_method || banking.payment_option),
        decl:
            declarations.declaration_truth_confirmation === true ||
            declarations.declaration_data_privacy_consent === true,
    };
    const errorKeys = Object.keys(form.errors).filter((key) =>
        Boolean(form.errors[key as keyof typeof form.errors]),
    );
    const statuses = Object.fromEntries(
        APPLICATION_SECTIONS.map((section) => [
            section,
            deriveSectionStatus({
                complete: blockers[section].length === 0,
                started: started[section],
                hasErrors:
                    errorKeys.some(
                        (key) => sectionForErrorKey(key) === section,
                    ) ||
                    (section === 'about' &&
                        missingIdentityPrerequisites.length > 0),
            }),
        ]),
    ) as Record<ApplicationSection, SectionStatus>;
    const allDone = nextIncompleteSection(statuses) === null;
    const aboutErrors = sectionErrorMessages(form.errors, 'about');

    const loanTypeLabel =
        loanTypes.find((type) => type.typecode === form.data.typecode)?.label ??
        'Loan';
    const coMakerName = (slot: CoMakerSlot) =>
        [form.data[slot].first_name, form.data[slot].last_name]
            .filter(Boolean)
            .join(' ');
    const coMakerNames = (['co_maker_1', 'co_maker_2'] as const)
        .filter(hasCoMaker)
        .map(coMakerName);
    const summaries: Record<ApplicationSection, string> = {
        loan:
            statuses.loan === 'done'
                ? `${formatCurrency(Number(form.data.requested_amount))} · ${form.data.requested_term} months · ${form.data.loan_purpose}`
                : 'Amount, term and purpose',
        about: `${member.name}${statuses.about === 'done' ? ' · details confirmed' : ' · confirm your details'}`,
        co:
            coMakerNames.length > 0
                ? coMakerNames.join(', ') +
                  (coMakerNames.length < 2 ? ' · 1 more needed' : '')
                : 'Add 2 co-makers',
        disb:
            banking.release_method || banking.payment_option
                ? [banking.release_method, banking.payment_option]
                      .filter(Boolean)
                      .join(' · ')
                : 'How you receive and repay',
        decl:
            statuses.decl === 'done'
                ? 'All statements accepted'
                : 'Truthfulness and data privacy',
    };

    // --- Navigation ------------------------------------------------------

    const go = (next: View, from: Origin | null = null) => {
        setView(next);
        setOrigin(from);
        setLoanPart(0);
        window.scrollTo(0, 0);
    };

    const handleErrorClick = (key: string) => {
        const section = sectionForErrorKey(key);

        if (section !== null && section !== view) {
            go(section, 'review');
        }

        window.setTimeout(() => focusField(key), 250);
    };

    // --- Persistence -----------------------------------------------------

    const handleSaveDraft = async (
        extra: Partial<{ confirmations: Set<LoanWizardConfirmation> }> = {},
    ): Promise<boolean> => {
        setActiveAction('draft');
        const payload = {
            ...form.data,
            wizard_confirmations: [...(extra.confirmations ?? confirmations)],
        };

        try {
            if (!draftState) {
                const response = await client.patch<LoanRequestDraft>(
                    LoanRequestController.draft().url,
                    payload,
                );
                setDraftState(response.data);
            } else {
                await client.patch(
                    LoanRequestController.saveDraft(draftState).url,
                    payload,
                );
                setDraftState({
                    ...draftState,
                    updated_at: new Date().toISOString(),
                });
            }

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

    const toggleConfirmation =
        (key: LoanWizardConfirmation) => (checked: boolean) =>
            setConfirmations((current) => {
                const next = new Set(current);

                if (checked) {
                    next.add(key);
                } else {
                    next.delete(key);
                }

                return next;
            });

    const handleApplyCalculator = async () => {
        const wasReturning = hasSavedDraft;

        if (!(await handleSaveDraft())) {
            return;
        }

        showSuccessToast('Your terms are in.', { id: 'manual-save-draft' });

        if (wasReturning) {
            go('hub');

            return;
        }

        // Guided first pass. Amount & term are set, so on a phone skip
        // straight to the purpose half of Loan details.
        go('loan', 'flow');
        setLoanPart(1);
    };

    // "Save and continue" / "Save and return to ...": always saves (an
    // incomplete section is kept as In progress), then moves on.
    const handleSaveSection = async () => {
        if (view === 'calc' || view === 'hub' || view === 'review') {
            return;
        }

        if (isPhone && view === 'loan' && loanPart === 0) {
            setLoanPart(1);
            window.scrollTo(0, 0);

            return;
        }

        if (!(await handleSaveDraft())) {
            return;
        }

        showSuccessToast(
            `${SECTION_LABELS[view]} ${blockers[view].length === 0 ? 'saved.' : 'saved as in progress.'}`,
            { id: 'manual-save-draft' },
        );

        if (origin === 'review') {
            go(allDone ? 'review' : 'hub');
        } else if (origin === 'flow') {
            const next = nextIncompleteSection(statuses, view);

            go(next ?? 'review', next ? 'flow' : null);
        } else {
            go('hub');
        }
    };

    const handleSaveAndExit = async () => {
        if (await handleSaveDraft()) {
            showSuccessToast('Draft saved.', { id: 'manual-save-draft' });
            router.visit(loanRequestsIndexHref);
        }
    };

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

    const handleSubmit = () => {
        setConfirmSubmitOpen(false);
        setActiveAction('submit');
        form.transform((data) => ({
            ...data,
            update_profile: updateProfile,
        }));
        // Success redirects to the request page, which shows the
        // confirmation (reference number) and then tracks the request.
        form.post(LoanRequestController.store().url, {
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

                const section = APPLICATION_SECTIONS.find((candidate) =>
                    Object.keys(errors).some(
                        (key) => sectionForErrorKey(key) === candidate,
                    ),
                );

                if (section) {
                    go(section, 'review');
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

    // --- Form field handlers ---------------------------------------------

    const handleLoanDetailChange = (field: LoanDetailField, value: string) => {
        form.setData(field, value);
    };

    const updateDataSection =
        (sectionKey: 'banking' | 'declarations') =>
        (field: string, value: string | number | boolean | null) => {
            form.setData((current) => ({
                ...current,
                [sectionKey]: {
                    ...(current[sectionKey] as LoanRequestDataSectionValues),
                    [field]: value,
                },
            }));
        };

    // Explicit opt-in only: loading a saved co-maker fills the fields as a
    // starting point, and saving one for reuse is its own button press. See
    // SavedCoMakersService.
    const loadSavedCoMaker = async (
        id: number,
    ): Promise<LoanRequestPersonFormData | null> => {
        try {
            const response = await client.get<{
                ok: boolean;
                data: LoanRequestPersonData & { label: string | null };
            }>(`/client/co-makers/${id}`);
            const record = response.data.data;

            return {
                ...toPersonForm(record),
                saved_co_maker_id: String(id),
                saved_co_maker_label: record.label ?? '',
            };
        } catch (error) {
            showErrorToast(error, 'Unable to load that saved co-maker.');

            return null;
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

    // The backend dedupes on normalized name + birthdate/cell number. See
    // SavedCoMakersService::findDuplicate().
    const saveCoMakerForReuse = async (person: LoanRequestPersonFormData) => {
        setIsSavingCoMakerForReuse(true);

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

            setSavedCoMakers((current) => [
                {
                    id: record.id,
                    label: record.label ?? '',
                    last_used_at: new Date().toISOString(),
                },
                ...current.filter((existing) => existing.id !== record.id),
            ]);
            showSuccessToast(
                response.data.duplicate
                    ? "Already saved -- updated this co-maker's details."
                    : 'Co-maker saved for reuse.',
            );
        } catch (error) {
            showErrorToast(error, 'Unable to save this co-maker.');
        } finally {
            setIsSavingCoMakerForReuse(false);
        }
    };

    const saveCoMaker = (
        slot: CoMakerSlot,
        person: LoanRequestPersonFormData,
    ) => {
        form.setData(slot, person);
        setCoMakerSlot(null);
    };

    const removeCoMaker = (slot: CoMakerSlot) =>
        form.setData(slot, toPersonForm(null));

    // --- Render ----------------------------------------------------------

    const draftUpdatedAt = draftState?.updated_at
        ? formatDateTime(draftState.updated_at)
        : null;
    const estimateNote = loanEstimateNote(estimateLimits);
    const isSection = view !== 'calc' && view !== 'hub' && view !== 'review';
    const loanSplit = isPhone && view === 'loan';
    const showProgress = isSection && origin === 'flow';
    const sectionIndex = isSection ? APPLICATION_SECTIONS.indexOf(view) : -1;
    const currentBlockers = isSection ? blockers[view] : [];
    const nextInFlow = isSection ? nextIncompleteSection(statuses, view) : null;
    const primaryLabel =
        loanSplit && loanPart === 0
            ? 'Next: purpose'
            : origin === 'review'
              ? 'Save and return to review'
              : origin === 'flow'
                ? nextInFlow === null
                    ? 'Save and check answers'
                    : 'Save and continue'
                : 'Save and return to overview';
    const barNote =
        loanSplit && loanPart === 0
            ? ''
            : currentBlockers.length > 0
              ? `Still needed: ${currentBlockers.join(', ')}`
              : origin === 'flow' && nextInFlow !== null
                ? `Next: ${SECTION_LABELS[nextInFlow]}`
                : '';
    const estimateCard = (
        <section className="space-y-3 rounded-xl border border-border bg-card p-5 shadow-card">
            <div className="flex items-center justify-between gap-2">
                <h3 className="text-base font-bold">Your estimate</h3>
                <span className="rounded-full bg-secondary px-2.5 py-0.5 text-xs font-bold text-secondary-foreground">
                    Updates as you type
                </span>
            </div>
            <LoanEstimateFigures estimate={estimate} isLoading={isEstimating} />
            <LoanEstimateBreakdown
                amount={Number(form.data.requested_amount) || 0}
                estimate={estimate}
            />
            <LoanEstimateWarnings warnings={estimateWarnings} />
            <p className="text-xs text-muted-foreground">{estimateNote}</p>
        </section>
    );

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

            <main className="wizard-fields mx-auto flex w-full max-w-[760px] flex-1 flex-col gap-4 px-4 py-6 sm:px-7 sm:py-7">
                {loanTypes.length === 0 ? (
                    <Alert variant="destructive">
                        <AlertTitle>Loan types unavailable</AlertTitle>
                        <AlertDescription>
                            Please contact support to load available loan
                            options before submitting a request.
                        </AlertDescription>
                    </Alert>
                ) : null}

                {view === 'calc' ? (
                    <LoanApplicationCalculator
                        loanTypes={loanTypes}
                        typecode={form.data.typecode}
                        amount={form.data.requested_amount}
                        term={form.data.requested_term}
                        limits={estimateLimits}
                        estimate={estimate}
                        isEstimating={isEstimating}
                        warnings={estimateWarnings}
                        hasDraft={hasSavedDraft}
                        isSaving={isSavingDraft}
                        onTypecodeChange={(value) =>
                            form.setData('typecode', value)
                        }
                        onAmountChange={(value) =>
                            form.setData('requested_amount', value)
                        }
                        onTermChange={(value) =>
                            form.setData('requested_term', value)
                        }
                        onApply={handleApplyCalculator}
                        onContinueDraft={() => go('hub')}
                    />
                ) : null}

                {view === 'hub' ? (
                    <LoanApplicationOverview
                        statuses={statuses}
                        summaries={summaries}
                        headline={{
                            title: `${loanTypeLabel} · ${form.data.requested_term || '--'} months`,
                            amount: formatCurrency(
                                Number(form.data.requested_amount) || null,
                            ),
                        }}
                        estimate={estimate}
                        isEstimating={isEstimating}
                        estimateNote={estimateNote}
                        onOpenSection={(section) => go(section, 'hub')}
                        onStart={(section) => go(section, 'flow')}
                        onReview={() => go('review')}
                        onRecalculate={() => go('calc')}
                    />
                ) : null}

                {isSection || view === 'review' ? (
                    <>
                        <Button
                            type="button"
                            variant="link"
                            className="min-h-11 self-start px-0 font-bold md:min-h-11"
                            onClick={() =>
                                loanSplit && loanPart === 1
                                    ? setLoanPart(0)
                                    : go('hub')
                            }
                        >
                            <ChevronLeft aria-hidden="true" />
                            {loanSplit && loanPart === 1
                                ? 'Amount & term'
                                : 'Application overview'}
                        </Button>

                        {showProgress ? (
                            <LoanApplicationProgress
                                section={view as ApplicationSection}
                                substep={
                                    loanSplit
                                        ? loanPart + 1
                                        : view === 'co'
                                          ? coMakerNames.length
                                          : 1
                                }
                                substeps={loanSplit ? 2 : view === 'co' ? 2 : 1}
                            />
                        ) : null}

                        <div className="space-y-1.5 px-0.5">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="text-xs font-bold tracking-[0.16em] text-primary uppercase">
                                    {view === 'review'
                                        ? 'Last step'
                                        : `Section ${sectionIndex + 1} of ${APPLICATION_SECTIONS.length}${loanSplit ? ` · Part ${loanPart + 1} of 2` : ''}`}
                                </p>
                                {isSection && !showProgress ? (
                                    <SectionStatusBadge
                                        status={statuses[view]}
                                    />
                                ) : null}
                            </div>
                            <h2 className="text-2xl font-bold tracking-tight sm:text-[30px]">
                                {view === 'review'
                                    ? 'Check your answers'
                                    : SECTION_LABELS[
                                          view as ApplicationSection
                                      ]}
                            </h2>
                            <p className="text-[15px] text-muted-foreground">
                                {view === 'review'
                                    ? 'Make sure everything is right. You cannot edit the application after submitting.'
                                    : SECTION_DESCRIPTIONS[
                                          view as ApplicationSection
                                      ]}
                            </p>
                        </div>
                    </>
                ) : null}

                {view === 'loan' ? (
                    <LoanRequestLoanDetailsStep
                        data={form.data}
                        errors={form.errors}
                        loanTypes={loanTypes}
                        onChange={handleLoanDetailChange}
                        part={
                            loanSplit
                                ? loanPart === 0
                                    ? 'terms'
                                    : 'purpose'
                                : 'all'
                        }
                        termsFooter={estimateCard}
                    />
                ) : null}

                {view === 'about' ? (
                    <>
                        {missingIdentityPrerequisites.length > 0 ? (
                            <Alert variant="destructive">
                                <AlertTitle>
                                    Complete your profile before continuing
                                </AlertTitle>
                                <AlertDescription className="space-y-2">
                                    <p>
                                        Please add these details in your Profile
                                        Settings before submitting a loan
                                        request:{' '}
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
                        {aboutErrors.length > 0 ? (
                            <Alert variant="destructive">
                                <AlertTitle>
                                    Some of your details need fixing
                                </AlertTitle>
                                <AlertDescription className="space-y-2">
                                    <ul className="list-disc space-y-1 pl-5">
                                        {aboutErrors.map((message) => (
                                            <li key={message}>{message}</li>
                                        ))}
                                    </ul>
                                    <p>
                                        These come from your profile. Update
                                        them in Profile Settings, then come back
                                        to submit.
                                    </p>
                                    <Button asChild size="sm">
                                        <Link href={editProfile().url}>
                                            Go to Profile Settings
                                        </Link>
                                    </Button>
                                </AlertDescription>
                            </Alert>
                        ) : null}
                        <LoanRequestAboutYouAccordion
                            values={form.data.applicant}
                        />
                        {applicantPrefilledFromProfile ||
                        applicantWorkIncomePrefilledFromProfile ? (
                            <div className="flex flex-col gap-2.5">
                                {applicantPrefilledFromProfile ? (
                                    <LoanRequestCheckRow
                                        id="applicant_personal_confirmed"
                                        checked={personalConfirmed}
                                        onCheckedChange={toggleConfirmation(
                                            'applicant_personal',
                                        )}
                                    >
                                        My personal details are correct
                                    </LoanRequestCheckRow>
                                ) : null}
                                {applicantWorkIncomePrefilledFromProfile ? (
                                    <LoanRequestCheckRow
                                        id="applicant_work_income_confirmed"
                                        checked={workIncomeConfirmed}
                                        onCheckedChange={toggleConfirmation(
                                            'applicant_work_income',
                                        )}
                                    >
                                        My work &amp; income details are correct
                                    </LoanRequestCheckRow>
                                ) : null}
                            </div>
                        ) : null}
                        <p className="text-sm text-muted-foreground">
                            Something wrong?{' '}
                            <Link
                                href={editProfile().url}
                                className="font-semibold text-primary underline underline-offset-4"
                            >
                                Update your profile
                            </Link>{' '}
                            first; this application picks up the change.
                        </p>
                    </>
                ) : null}

                {view === 'co'
                    ? (['co_maker_1', 'co_maker_2'] as const).map(
                          (slot, index) => {
                              const filled = hasCoMaker(slot);
                              const person = form.data[slot];
                              const missing = coMakerMissing(slot);

                              return (
                                  <section
                                      key={slot}
                                      className={cn(
                                          'flex flex-wrap items-center gap-3.5 rounded-xl border-[1.5px] p-4 sm:px-5',
                                          filled
                                              ? 'border-border bg-card shadow-card'
                                              : 'border-dashed border-input bg-muted',
                                      )}
                                  >
                                      <div className="min-w-0 flex-[1_1_200px]">
                                          <div className="mb-0.5 flex items-center gap-2">
                                              <span className="text-xs font-bold tracking-widest text-muted-foreground uppercase">
                                                  Co-maker {index + 1}
                                              </span>
                                              <SectionStatusBadge
                                                  status={
                                                      !filled
                                                          ? 'not_started'
                                                          : missing.length > 0
                                                            ? 'in_progress'
                                                            : 'done'
                                                  }
                                              />
                                          </div>
                                          <p className="text-[17px] font-bold">
                                              {filled
                                                  ? coMakerName(slot)
                                                  : 'Not added yet'}
                                          </p>
                                          <p className="text-[13px] text-muted-foreground">
                                              {filled
                                                  ? missing.length > 0
                                                      ? `Still needed: ${missing.join(', ')}`
                                                      : [
                                                            person.current_position,
                                                            person.employer_business_name,
                                                            person.cell_no,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')
                                                  : 'Name, contact and work details'}
                                          </p>
                                      </div>
                                      <div className="flex items-center gap-2">
                                          {filled ? (
                                              <Button
                                                  type="button"
                                                  variant="link"
                                                  className="min-h-11 px-2 text-muted-foreground underline md:min-h-11"
                                                  aria-label={`Remove co-maker ${index + 1}`}
                                                  onClick={() =>
                                                      removeCoMaker(slot)
                                                  }
                                              >
                                                  Remove
                                              </Button>
                                          ) : null}
                                          <Button
                                              type="button"
                                              variant={
                                                  filled ? 'outline' : 'default'
                                              }
                                              className="min-h-11 font-bold md:min-h-11"
                                              onClick={() =>
                                                  setCoMakerSlot(slot)
                                              }
                                          >
                                              {filled ? 'Edit' : 'Add co-maker'}
                                          </Button>
                                      </div>
                                  </section>
                              );
                          },
                      )
                    : null}

                {view === 'disb' ? (
                    <>
                        <LoanRequestDataSectionStep
                            sectionKey="banking"
                            title="Loan disbursement & repayment"
                            description="Tell us how you'd like to receive your loan and how you'll repay it."
                            values={form.data.banking}
                            definition={dataSectionDefinitions.banking}
                            errors={form.errors}
                            onChange={updateDataSection('banking')}
                            applicantFullName={member.name}
                            applicantEmployerBusinessName={
                                form.data.applicant.employer_business_name
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
                        {needsBankConfirmation ? (
                            <LoanRequestSectionCard
                                title="Confirm bank details"
                                description="These details were pre-filled from your member profile."
                            >
                                <LoanRequestCheckRow
                                    id="bank_account_confirmed"
                                    checked={bankConfirmed}
                                    onCheckedChange={toggleConfirmation(
                                        'bank_account',
                                    )}
                                >
                                    My bank details are correct
                                </LoanRequestCheckRow>
                            </LoanRequestSectionCard>
                        ) : null}
                    </>
                ) : null}

                {view === 'decl' ? (
                    <LoanRequestDataSectionStep
                        sectionKey="declarations"
                        title="Personal declarations and consent"
                        description="Complete the declarations and consent items required before processing can begin."
                        values={form.data.declarations}
                        definition={dataSectionDefinitions.declarations}
                        errors={form.errors}
                        onChange={updateDataSection('declarations')}
                    />
                ) : null}

                {view === 'review' ? (
                    <>
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
                            onChangeSection={(section) => go(section, 'review')}
                        />
                        <p className="rounded-xl bg-secondary px-4 py-3.5 text-[13px] leading-relaxed text-secondary-foreground">
                            <b>Good to know:</b> documents are collected and
                            signed at release. Signatures will be collected
                            physically upon loan release. Nothing to upload or
                            print now.
                        </p>
                    </>
                ) : null}
            </main>

            {isSection || view === 'review' ? (
                <div className="sticky bottom-0 z-20 border-t border-border bg-card shadow-[0_-4px_16px_rgba(20,23,15,0.08)]">
                    <div className="mx-auto flex w-full max-w-[760px] flex-col gap-2 px-4 py-3 sm:px-7">
                        {view === 'review' && !allDone ? (
                            <p
                                role="status"
                                className="text-[13px] font-semibold text-amber-900 dark:text-amber-100"
                            >
                                Finish every section before you submit.
                            </p>
                        ) : barNote ? (
                            <p
                                role="status"
                                className={cn(
                                    'text-[13px] font-semibold',
                                    currentBlockers.length > 0
                                        ? 'text-amber-900 dark:text-amber-100'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {barNote}
                            </p>
                        ) : null}
                        <div className="flex items-center gap-2.5">
                            <Button
                                type="button"
                                variant="outline"
                                className="hidden h-12 rounded-lg px-4 text-[15px] font-semibold sm:inline-flex md:h-12"
                                onClick={() => go('hub')}
                            >
                                Back to overview
                            </Button>
                            <span className="flex-1" />
                            {view === 'review' ? (
                                <Button
                                    type="button"
                                    className="h-12 flex-1 rounded-lg px-7 text-[15px] font-bold sm:flex-none md:h-12"
                                    disabled={
                                        !allDone ||
                                        isSubmitting ||
                                        loanTypes.length === 0
                                    }
                                    onClick={() => setConfirmSubmitOpen(true)}
                                >
                                    {isSubmitting ? (
                                        <Loader2 className="animate-spin" />
                                    ) : null}
                                    Submit for review
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    className="h-12 flex-1 rounded-lg px-7 text-[15px] font-bold sm:flex-none md:h-12"
                                    disabled={isSavingDraft}
                                    onClick={handleSaveSection}
                                >
                                    {isSavingDraft ? (
                                        <Loader2 className="animate-spin" />
                                    ) : null}
                                    {primaryLabel}
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            ) : null}

            <CoMakerSheet
                slot={coMakerSlot}
                initial={coMakerSlot ? form.data[coMakerSlot] : null}
                errors={form.errors}
                savedCoMakers={savedCoMakers.filter(
                    (option) =>
                        String(option.id) !==
                        form.data[
                            coMakerSlot === 'co_maker_1'
                                ? 'co_maker_2'
                                : 'co_maker_1'
                        ].saved_co_maker_id,
                )}
                guided={origin === 'flow'}
                isSaving={isSavingDraft}
                isSavingForReuse={isSavingCoMakerForReuse}
                onLoadSaved={loadSavedCoMaker}
                onRemoveSaved={removeSavedCoMaker}
                onSaveForReuse={saveCoMakerForReuse}
                onSave={saveCoMaker}
                onClose={() => setCoMakerSlot(null)}
            />

            <AlertDialog
                open={confirmSubmitOpen}
                onOpenChange={setConfirmSubmitOpen}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Submit your application?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            You are applying for{' '}
                            {formatCurrency(Number(form.data.requested_amount))}{' '}
                            over {form.data.requested_term} months. After
                            submitting you cannot edit it; a loan processor
                            reviews it next.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="min-h-11 md:min-h-11">
                            Keep editing
                        </AlertDialogCancel>
                        <AlertDialogAction
                            className="min-h-11 md:min-h-11"
                            onClick={handleSubmit}
                        >
                            Submit for review
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
