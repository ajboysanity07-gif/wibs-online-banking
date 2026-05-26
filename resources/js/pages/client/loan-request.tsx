import { Head, Link, router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { ArrowLeft } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import LoanRequestController from '@/actions/App/Http/Controllers/Client/LoanRequestController';
import LoanRequestSignatureLinkController from '@/actions/App/Http/Controllers/Client/LoanRequestSignatureLinkController';
import { LoanRequestAnimatedStep } from '@/components/loan-request/loan-request-animated-step';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { LoanRequestStepIndicator } from '@/components/loan-request/loan-request-step-indicator';
import {
    LoanRequestApplicantPersonalStep,
    LoanRequestApplicantWorkStep,
    LoanRequestCoMakerStep,
    LoanRequestLoanDetailsStep,
    LoanRequestReviewStep,
} from '@/components/loan-request/loan-request-steps';
import { LoanRequestSummaryPanel } from '@/components/loan-request/loan-request-summary-panel';
import { LoanRequestWizardActions } from '@/components/loan-request/loan-request-wizard-footer';
import { PageShell } from '@/components/page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import AppLayout from '@/layouts/app-layout';
import api, { getApiErrorMessage, mapValidationErrors } from '@/lib/api';
import { formatDateTime, toDateInputValue } from '@/lib/formatters';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import { dashboard as clientDashboard } from '@/routes/client';
import { index as loanRequestsIndex } from '@/routes/client/loan-requests';
import type { BreadcrumbItem } from '@/types';
import type {
    LoanRequestCoMakerSignatureState,
    LoanRequestDraft,
    LoanRequestFormData,
    LoanRequestGeneratedSignatureLink,
    LoanRequestMemberSummary,
    LoanRequestPersonData,
    LoanRequestPersonFormData,
    LoanRequestReadOnlyMap,
    LoanTypeOption,
} from '@/types/loan-requests';

const loanRequestsIndexHref = loanRequestsIndex().url;

type Props = {
    loanTypes: LoanTypeOption[];
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    coMakerOneSignature: LoanRequestCoMakerSignatureState;
    coMakerTwoSignature: LoanRequestCoMakerSignatureState;
    applicantReadOnly: LoanRequestReadOnlyMap | null;
    member: LoanRequestMemberSummary;
    draft: LoanRequestDraft | null;
};

type SignatureRole = 'co_maker_1' | 'co_maker_2';
type SignatureMethod = 'in_person' | 'share_link';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Overview', href: clientDashboard().url },
    { title: 'Loan Requests', href: loanRequestsIndexHref },
    { title: 'Loan request', href: LoanRequestController.create().url },
];

const steps = [
    {
        id: 'loan-details',
        title: 'Loan details',
        description: 'Set the loan type, amount, term, and purpose.',
    },
    {
        id: 'personal',
        title: 'Personal data',
        description: 'Confirm your personal information.',
    },
    {
        id: 'work-finances',
        title: 'Work & finances',
        description: 'Share your employment and income details.',
    },
    {
        id: 'co-maker-1',
        title: 'Co-maker 1',
        description: 'Add details for your first co-maker.',
    },
    {
        id: 'co-maker-2',
        title: 'Co-maker 2',
        description: 'Add details for your second co-maker.',
    },
    {
        id: 'review',
        title: 'Review',
        description: 'Review and confirm the undertaking.',
    },
];

type LoanDetailField =
    | 'typecode'
    | 'requested_amount'
    | 'requested_term'
    | 'loan_purpose'
    | 'availment_status';

const applicantPersonalFields = new Set([
    'first_name',
    'last_name',
    'middle_name',
    'nickname',
    'birthdate',
    'birthplace_city',
    'birthplace_province',
    'address1',
    'address2',
    'address3',
    'length_of_stay',
    'housing_status',
    'cell_no',
    'civil_status',
    'educational_attainment',
    'number_of_children',
    'spouse_name',
    'spouse_age',
    'spouse_cell_no',
]);

const applicantWorkFields = new Set([
    'employment_type',
    'employer_business_name',
    'employer_business_address1',
    'employer_business_address2',
    'employer_business_address3',
    'telephone_no',
    'current_position',
    'nature_of_business',
    'years_in_work_business',
    'gross_monthly_income',
    'payday',
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

const personHasValues = (person: LoanRequestPersonFormData): boolean =>
    Object.values(person).some((value) => value.trim() !== '');

const personFormsMatch = (
    current: LoanRequestPersonFormData,
    persisted: LoanRequestPersonFormData,
): boolean =>
    Object.keys(current).every((key) => {
        const field = key as keyof LoanRequestPersonFormData;

        return current[field] === persisted[field];
    });

const emptyPerson: LoanRequestPersonFormData = {
    first_name: '',
    middle_name: '',
    last_name: '',
    nickname: '',
    birthdate: '',
    birthplace_city: '',
    birthplace_province: '',
    address1: '',
    address2: '',
    address3: '',
    length_of_stay: '',
    housing_status: '',
    cell_no: '',
    civil_status: '',
    educational_attainment: '',
    number_of_children: '',
    spouse_name: '',
    spouse_age: '',
    spouse_cell_no: '',
    employment_type: '',
    employer_business_name: '',
    employer_business_address1: '',
    employer_business_address2: '',
    employer_business_address3: '',
    telephone_no: '',
    current_position: '',
    nature_of_business: '',
    years_in_work_business: '',
    gross_monthly_income: '',
    payday: '',
};

const signatureFieldByRole: Record<
    SignatureRole,
    'co_maker_1_signature_data' | 'co_maker_2_signature_data'
> = {
    co_maker_1: 'co_maker_1_signature_data',
    co_maker_2: 'co_maker_2_signature_data',
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
        length_of_stay: person.length_of_stay ?? '',
        housing_status: person.housing_status ?? '',
        cell_no: person.cell_no ?? '',
        civil_status: person.civil_status ?? '',
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
        telephone_no: person.telephone_no ?? '',
        current_position: person.current_position ?? '',
        nature_of_business: person.nature_of_business ?? '',
        years_in_work_business: person.years_in_work_business ?? '',
        gross_monthly_income: toStringValue(person.gross_monthly_income),
        payday: person.payday ?? '',
    };
};

const resolveStepFromErrors = (
    errors: Record<string, string | undefined>,
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
            stepMatches.push(0);
            return;
        }

        if (key === 'applicant_signature_data') {
            stepMatches.push(2);
            return;
        }

        if (key === 'co_maker_1_signature_data') {
            stepMatches.push(3);
            return;
        }

        if (key === 'co_maker_2_signature_data') {
            stepMatches.push(4);
            return;
        }

        if (
            key === 'co_maker_1.signature' ||
            key === 'co_maker_2.signature' ||
            key === 'signature_link' ||
            key === 'link'
        ) {
            stepMatches.push(5);
            return;
        }

        if (key.startsWith('co_maker_1.')) {
            stepMatches.push(3);
            return;
        }

        if (key.startsWith('co_maker_2.')) {
            stepMatches.push(4);
            return;
        }

        if (key.startsWith('applicant.')) {
            const field = key.replace('applicant.', '');
            stepMatches.push(
                applicantWorkFields.has(field)
                    ? 2
                    : applicantPersonalFields.has(field)
                      ? 1
                      : 1,
            );
            return;
        }

        if (key === 'undertaking_accepted') {
            stepMatches.push(5);
        }
    });

    return stepMatches.length > 0 ? Math.min(...stepMatches) : null;
};

export default function LoanRequestPage({
    loanTypes,
    applicant,
    coMakerOne,
    coMakerTwo,
    coMakerOneSignature,
    coMakerTwoSignature,
    applicantReadOnly,
    member,
    draft,
}: Props) {
    const [currentStep, setCurrentStep] = useState(0);
    const [stepDirection, setStepDirection] = useState<'forward' | 'backward'>(
        'forward',
    );
    const [activeAction, setActiveAction] = useState<'draft' | 'submit' | null>(
        null,
    );
    const [lastAction, setLastAction] = useState<'draft' | 'submit' | null>(
        null,
    );
    const [draftState, setDraftState] = useState<LoanRequestDraft | null>(draft);
    const [coMakerOneSignatureState, setCoMakerOneSignatureState] =
        useState<LoanRequestCoMakerSignatureState>(coMakerOneSignature);
    const [coMakerTwoSignatureState, setCoMakerTwoSignatureState] =
        useState<LoanRequestCoMakerSignatureState>(coMakerTwoSignature);
    const [generatedLinks, setGeneratedLinks] = useState<
        Partial<Record<SignatureRole, LoanRequestGeneratedSignatureLink>>
    >({});
    const [selectedSigningMethods, setSelectedSigningMethods] = useState<
        Partial<Record<SignatureRole, SignatureMethod>>
    >({
        co_maker_1:
            coMakerOneSignature.state === 'link_active' ||
            coMakerOneSignature.state === 'expired'
                ? 'share_link'
                : undefined,
        co_maker_2:
            coMakerTwoSignature.state === 'link_active' ||
            coMakerTwoSignature.state === 'expired'
                ? 'share_link'
                : undefined,
    });
    const [editableSignedRoles, setEditableSignedRoles] = useState<
        Partial<Record<SignatureRole, boolean>>
    >({});
    const [signatureActionRole, setSignatureActionRole] =
        useState<SignatureRole | null>(null);
    const [isRefreshingSignatures, setIsRefreshingSignatures] = useState(false);
    const [, copyToClipboard] = useClipboard();

    const initialFormData = useMemo<LoanRequestFormData>(
        () => ({
            typecode: draft?.typecode ?? loanTypes[0]?.typecode ?? '',
            requested_amount: toStringValue(draft?.requested_amount),
            requested_term: toStringValue(draft?.requested_term),
            loan_purpose: draft?.loan_purpose ?? '',
            availment_status: draft?.availment_status ?? '',
            undertaking_accepted: false,
            applicant_signature_data: '',
            co_maker_1_signature_data: '',
            co_maker_2_signature_data: '',
            applicant: toPersonForm(applicant),
            co_maker_1: toPersonForm(coMakerOne),
            co_maker_2: toPersonForm(coMakerTwo),
        }),
        [applicant, coMakerOne, coMakerTwo, draft, loanTypes],
    );

    const form = useForm<LoanRequestFormData>(initialFormData);
    const isFirstStep = currentStep === 0;
    const isLastStep = currentStep === steps.length - 1;
    const isSavingDraft = form.processing && activeAction === 'draft';
    const isSubmitting = form.processing && activeAction === 'submit';
    const hasLoanTypes = loanTypes.length > 0;
    const stepMeta = steps[currentStep];
    const persistedCoMakerOne = toPersonForm(coMakerOne);
    const persistedCoMakerTwo = toPersonForm(coMakerTwo);
    const coMakerOneRequired = personHasValues(form.data.co_maker_1);
    const coMakerTwoRequired = personHasValues(form.data.co_maker_2);
    const coMakerOneNeedsResign =
        coMakerOneSignatureState.is_confirmed &&
        !personFormsMatch(form.data.co_maker_1, persistedCoMakerOne);
    const coMakerTwoNeedsResign =
        coMakerTwoSignatureState.is_confirmed &&
        !personFormsMatch(form.data.co_maker_2, persistedCoMakerTwo);
    const effectiveCoMakerOneSignatureState = coMakerOneNeedsResign
        ? {
              ...coMakerOneSignatureState,
              state: 'proposed' as const,
              is_confirmed: false,
              has_signature: false,
              signed_at: null,
          }
        : coMakerOneSignatureState;
    const effectiveCoMakerTwoSignatureState = coMakerTwoNeedsResign
        ? {
              ...coMakerTwoSignatureState,
              state: 'proposed' as const,
              is_confirmed: false,
              has_signature: false,
              signed_at: null,
          }
        : coMakerTwoSignatureState;
    const coMakerOneHasPendingInPersonSignature =
        (form.data.co_maker_1_signature_data ?? '').trim() !== '';
    const coMakerTwoHasPendingInPersonSignature =
        (form.data.co_maker_2_signature_data ?? '').trim() !== '';
    const coMakerOneReadyForSubmit =
        !coMakerOneRequired ||
        effectiveCoMakerOneSignatureState.is_confirmed ||
        coMakerOneHasPendingInPersonSignature;
    const coMakerTwoReadyForSubmit =
        !coMakerTwoRequired ||
        effectiveCoMakerTwoSignatureState.is_confirmed ||
        coMakerTwoHasPendingInPersonSignature;
    const canSubmitForReview =
        hasLoanTypes && coMakerOneReadyForSubmit && coMakerTwoReadyForSubmit;
    const submitDisabledMessage = coMakerOneNeedsResign || coMakerTwoNeedsResign
        ? 'A signed co-maker detail was changed. Save the updated proposed details, then collect a fresh co-maker signature before submitting.'
        : !canSubmitForReview
          ? 'Submit for Review is available after all required co-makers have signed.'
          : null;
    const coMakerOneFieldsLocked =
        coMakerOneSignatureState.is_confirmed &&
        !coMakerOneNeedsResign &&
        editableSignedRoles.co_maker_1 !== true;
    const coMakerTwoFieldsLocked =
        coMakerTwoSignatureState.is_confirmed &&
        !coMakerTwoNeedsResign &&
        editableSignedRoles.co_maker_2 !== true;
    const coMakerOneConfirmationError = form.errors[
        'co_maker_1.signature' as keyof typeof form.errors
    ] as string | undefined;
    const coMakerTwoConfirmationError = form.errors[
        'co_maker_2.signature' as keyof typeof form.errors
    ] as string | undefined;

    useEffect(() => {
        setDraftState(draft);
    }, [draft]);

    useEffect(() => {
        setCoMakerOneSignatureState(coMakerOneSignature);

        if (coMakerOneSignature.is_confirmed) {
            setGeneratedLinks((current) => {
                const next = { ...current };
                delete next.co_maker_1;

                return next;
            });
            form.setData('co_maker_1_signature_data', '');
            setSelectedSigningMethods((current) => ({
                ...current,
                co_maker_1: undefined,
            }));
            setEditableSignedRoles((current) => ({
                ...current,
                co_maker_1: false,
            }));
        } else if (
            coMakerOneSignature.state === 'link_active' ||
            coMakerOneSignature.state === 'expired'
        ) {
            setSelectedSigningMethods((current) => ({
                ...current,
                co_maker_1: current.co_maker_1 ?? 'share_link',
            }));
        }
    }, [coMakerOneSignature, form]);

    useEffect(() => {
        setCoMakerTwoSignatureState(coMakerTwoSignature);

        if (coMakerTwoSignature.is_confirmed) {
            setGeneratedLinks((current) => {
                const next = { ...current };
                delete next.co_maker_2;

                return next;
            });
            form.setData('co_maker_2_signature_data', '');
            setSelectedSigningMethods((current) => ({
                ...current,
                co_maker_2: undefined,
            }));
            setEditableSignedRoles((current) => ({
                ...current,
                co_maker_2: false,
            }));
        } else if (
            coMakerTwoSignature.state === 'link_active' ||
            coMakerTwoSignature.state === 'expired'
        ) {
            setSelectedSigningMethods((current) => ({
                ...current,
                co_maker_2: current.co_maker_2 ?? 'share_link',
            }));
        }
    }, [coMakerTwoSignature, form]);

    const updatePersonField =
        (section: 'applicant' | 'co_maker_1' | 'co_maker_2') =>
        (field: keyof LoanRequestPersonFormData, value: string) => {
            if (section === 'co_maker_1' || section === 'co_maker_2') {
                setGeneratedLinks((current) => {
                    const next = { ...current };
                    delete next[section];

                    return next;
                });
            }

            form.setData((current) => ({
                ...current,
                ...(section === 'co_maker_1' || section === 'co_maker_2'
                    ? {
                          [signatureFieldByRole[section]]: '',
                      }
                    : {}),
                [section]: {
                    ...current[section],
                    [field]: value,
                },
            }));
        };

    const handleSelectSigningMethod = (
        role: SignatureRole,
        method: SignatureMethod,
    ) => {
        setSelectedSigningMethods((current) => ({
            ...current,
            [role]: method,
        }));

        if (method === 'share_link') {
            form.setData(signatureFieldByRole[role], '');
        }
    };

    const handleCoMakerSignatureChange = (
        role: SignatureRole,
        value: string,
    ) => {
        form.setData(signatureFieldByRole[role], value);

        if (value.trim() !== '') {
            setSelectedSigningMethods((current) => ({
                ...current,
                [role]: 'in_person',
            }));
        }
    };

    const handleEnableSignedCoMakerEditing = (role: SignatureRole) => {
        setEditableSignedRoles((current) => ({
            ...current,
            [role]: true,
        }));
    };

    const handleLoanDetailChange = (field: LoanDetailField, value: string) => {
        form.setData(field, value);
    };

    const handleStepChange = (nextStep: number) => {
        setCurrentStep((current) => {
            if (nextStep === current) {
                return current;
            }

            setStepDirection(nextStep > current ? 'forward' : 'backward');

            return nextStep;
        });
    };

    const handleNextStep = () => {
        setCurrentStep((current) => {
            const nextStep = Math.min(steps.length - 1, current + 1);
            setStepDirection('forward');

            return nextStep;
        });
    };

    const handlePreviousStep = () => {
        setCurrentStep((current) => {
            const nextStep = Math.max(0, current - 1);
            setStepDirection('backward');

            return nextStep;
        });
    };

    const applyValidationErrors = (errors: Record<string, string>) => {
        Object.entries(errors).forEach(([field, message]) => {
            form.setError(field as never, message);
        });

        const step = resolveStepFromErrors(errors);

        if (step !== null) {
            handleStepChange(step);
        }
    };

    const handleGenerateSignatureLink = async (role: SignatureRole) => {
        const isRequired =
            role === 'co_maker_1' ? coMakerOneRequired : coMakerTwoRequired;

        if (!isRequired) {
            showErrorToast(
                null,
                role === 'co_maker_1'
                    ? 'Enter the proposed details for Co-maker 1 before generating a signing link.'
                    : 'Enter the proposed details for Co-maker 2 before generating a signing link.',
                { id: `loan-request-signature-link-${role}` },
            );

            handleStepChange(role === 'co_maker_1' ? 3 : 4);

            return;
        }

        setSignatureActionRole(role);

        try {
            const response = await api.post(
                LoanRequestSignatureLinkController.store(role).url,
                form.data,
            );
            const payload = response.data?.data as
                | {
                      loanRequest: LoanRequestDraft;
                      coMakerOneSignature: LoanRequestCoMakerSignatureState;
                      coMakerTwoSignature: LoanRequestCoMakerSignatureState;
                      signingLink: LoanRequestGeneratedSignatureLink;
                  }
                | undefined;

            if (!payload) {
                throw new Error('Unable to generate the signature link.');
            }

            setDraftState(payload.loanRequest);
            setCoMakerOneSignatureState(payload.coMakerOneSignature);
            setCoMakerTwoSignatureState(payload.coMakerTwoSignature);
            setGeneratedLinks((current) => ({
                ...current,
                [role]: payload.signingLink,
            }));
            setSelectedSigningMethods((current) => ({
                ...current,
                [role]: 'share_link',
            }));

            showSuccessToast(
                'Secure signing link generated.',
                {
                    id: `loan-request-signature-link-${role}`,
                },
            );
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.status === 422) {
                const validationErrors = mapValidationErrors(
                    error.response.data?.errors as
                        | Record<string, string[]>
                        | undefined,
                );

                applyValidationErrors(validationErrors);

                return;
            }

            showErrorToast(
                null,
                getApiErrorMessage(
                    error,
                    'Unable to generate the co-maker signing link.',
                ),
                { id: `loan-request-signature-link-${role}` },
            );
        } finally {
            setSignatureActionRole(null);
        }
    };

    const handleCopySignatureLink = async (role: SignatureRole) => {
        const link = generatedLinks[role];

        if (!link) {
            showErrorToast(
                null,
                'For security, links can only be copied immediately after generation. Generate a new link to share it again.',
                { id: `loan-request-signature-copy-${role}` },
            );

            return;
        }

        const copied = await copyToClipboard(link.signing_url);

        if (copied) {
            showSuccessToast('Signing link copied.', {
                id: `loan-request-signature-copy-${role}`,
            });

            return;
        }

        showErrorToast(null, 'Unable to copy the signing link.', {
            id: `loan-request-signature-copy-${role}`,
        });
    };

    const handleRefreshSignatures = () => {
        setIsRefreshingSignatures(true);

        router.reload({
            only: ['draft', 'coMakerOneSignature', 'coMakerTwoSignature'],
            onFinish: () => setIsRefreshingSignatures(false),
        });
    };

    const handleSaveDraft = () => {
        setActiveAction('draft');
        form.patch(LoanRequestController.draft().url, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setLastAction('draft'),
            onFinish: () => setActiveAction(null),
        });
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
                const step = resolveStepFromErrors(errors);

                if (step !== null) {
                    handleStepChange(step);
                }

                if (Object.keys(errors).length === 0) {
                    showErrorToast(
                        null,
                        'Unable to submit the loan request.',
                        { id: 'loan-request-submit' },
                    );
                }
            },
            onFinish: () => setActiveAction(null),
        });
    };

    const draftUpdatedAt = draftState?.updated_at
        ? formatDateTime(draftState.updated_at)
        : null;
    const draftUpdatedLabel =
        draftState?.status === 'pending_co_maker_signatures'
            ? 'Updated'
            : 'Last saved';

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
                                Complete the application form, save a draft at
                                any time, and use secure signing links to
                                collect required co-maker consent before admin
                                review.
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
                                                {draftUpdatedLabel}{' '}
                                                {draftUpdatedAt}
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

                    <div className="mt-5 rounded-xl border border-border/30 bg-muted/15 p-4 sm:p-5">
                        <div className="space-y-2">
                            <div className="space-y-1">
                                <p className="text-sm font-semibold text-foreground">
                                    {stepMeta?.title}
                                </p>
                                {stepMeta?.description ? (
                                    <p className="text-xs text-muted-foreground">
                                        {stepMeta.description}
                                    </p>
                                ) : null}
                            </div>
                            <LoanRequestStepIndicator
                                steps={steps}
                                currentStep={currentStep}
                                onStepChange={handleStepChange}
                            />
                        </div>
                    </div>
                </div>

                <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:gap-10 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <div className="space-y-8">
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
                            show={currentStep === 1}
                            direction={stepDirection}
                        >
                            <LoanRequestApplicantPersonalStep
                                values={form.data.applicant}
                                errors={form.errors}
                                readOnly={applicantReadOnly}
                                onChange={updatePersonField('applicant')}
                            />
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={currentStep === 2}
                            direction={stepDirection}
                        >
                            <LoanRequestApplicantWorkStep
                                values={form.data.applicant}
                                errors={form.errors}
                                onChange={updatePersonField('applicant')}
                                signatureData={
                                    form.data.applicant_signature_data ?? ''
                                }
                                onSignatureChange={(value) =>
                                    form.setData('applicant_signature_data', value)
                                }
                            />
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={currentStep === 3}
                            direction={stepDirection}
                        >
                            <LoanRequestCoMakerStep
                                title="Co-maker 1"
                                description="Add the proposed details for your first co-maker. The co-maker is only confirmed after personally reviewing, consenting, and signing."
                                prefix="co_maker_1"
                                values={form.data.co_maker_1}
                                errors={form.errors}
                                onChange={updatePersonField('co_maker_1')}
                                signatureState={
                                    effectiveCoMakerOneSignatureState
                                }
                                isSignatureRequired={coMakerOneRequired}
                                isLocked={coMakerOneFieldsLocked}
                                selectedSigningMethod={
                                    selectedSigningMethods.co_maker_1
                                }
                                generatedLink={generatedLinks.co_maker_1}
                                isGeneratingSignatureLink={
                                    signatureActionRole === 'co_maker_1'
                                }
                                signatureData={
                                    form.data.co_maker_1_signature_data ?? ''
                                }
                                signatureError={coMakerOneConfirmationError}
                                signatureDataError={
                                    form.errors.co_maker_1_signature_data
                                }
                                onSelectSigningMethod={(method) =>
                                    handleSelectSigningMethod(
                                        'co_maker_1',
                                        method,
                                    )
                                }
                                onSignatureChange={(value) =>
                                    handleCoMakerSignatureChange(
                                        'co_maker_1',
                                        value,
                                    )
                                }
                                onEnableSignedEditing={() =>
                                    handleEnableSignedCoMakerEditing(
                                        'co_maker_1',
                                    )
                                }
                                onGenerateSignatureLink={() =>
                                    handleGenerateSignatureLink('co_maker_1')
                                }
                                onCopySignatureLink={() =>
                                    handleCopySignatureLink('co_maker_1')
                                }
                            />
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={currentStep === 4}
                            direction={stepDirection}
                        >
                            <LoanRequestCoMakerStep
                                title="Co-maker 2"
                                description="Add the proposed details for your second co-maker. The co-maker is only confirmed after personally reviewing, consenting, and signing."
                                prefix="co_maker_2"
                                values={form.data.co_maker_2}
                                errors={form.errors}
                                onChange={updatePersonField('co_maker_2')}
                                signatureState={
                                    effectiveCoMakerTwoSignatureState
                                }
                                isSignatureRequired={coMakerTwoRequired}
                                isLocked={coMakerTwoFieldsLocked}
                                selectedSigningMethod={
                                    selectedSigningMethods.co_maker_2
                                }
                                generatedLink={generatedLinks.co_maker_2}
                                isGeneratingSignatureLink={
                                    signatureActionRole === 'co_maker_2'
                                }
                                signatureData={
                                    form.data.co_maker_2_signature_data ?? ''
                                }
                                signatureError={coMakerTwoConfirmationError}
                                signatureDataError={
                                    form.errors.co_maker_2_signature_data
                                }
                                onSelectSigningMethod={(method) =>
                                    handleSelectSigningMethod(
                                        'co_maker_2',
                                        method,
                                    )
                                }
                                onSignatureChange={(value) =>
                                    handleCoMakerSignatureChange(
                                        'co_maker_2',
                                        value,
                                    )
                                }
                                onEnableSignedEditing={() =>
                                    handleEnableSignedCoMakerEditing(
                                        'co_maker_2',
                                    )
                                }
                                onGenerateSignatureLink={() =>
                                    handleGenerateSignatureLink('co_maker_2')
                                }
                                onCopySignatureLink={() =>
                                    handleCopySignatureLink('co_maker_2')
                                }
                            />
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={currentStep === 5}
                            direction={stepDirection}
                        >
                            <LoanRequestReviewStep
                                data={form.data}
                                loanTypes={loanTypes}
                                member={member}
                                errors={form.errors}
                                coMakerOneSignature={
                                    effectiveCoMakerOneSignatureState
                                }
                                coMakerTwoSignature={
                                    effectiveCoMakerTwoSignatureState
                                }
                                coMakerOneRequired={coMakerOneRequired}
                                coMakerTwoRequired={coMakerTwoRequired}
                                generatedLinks={generatedLinks}
                                onGenerateSignatureLink={
                                    handleGenerateSignatureLink
                                }
                                onCopySignatureLink={handleCopySignatureLink}
                                coMakerOneHasPendingInPersonSignature={
                                    coMakerOneHasPendingInPersonSignature
                                }
                                coMakerTwoHasPendingInPersonSignature={
                                    coMakerTwoHasPendingInPersonSignature
                                }
                                onRefreshSignatures={handleRefreshSignatures}
                                isGeneratingSignatureLinkRole={
                                    signatureActionRole
                                }
                                isRefreshingSignatures={
                                    isRefreshingSignatures
                                }
                                canSubmitForReview={canSubmitForReview}
                                submitDisabledMessage={submitDisabledMessage}
                                onUndertakingChange={(value) =>
                                    form.setData('undertaking_accepted', value)
                                }
                            />
                        </LoanRequestAnimatedStep>

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
                                (isLastStep && !canSubmitForReview)
                            }
                        />
                    </div>

                    <LoanRequestSummaryPanel
                        data={form.data}
                        loanTypes={loanTypes}
                        member={member}
                        draft={draftState}
                        draftUpdatedAt={draftUpdatedAt}
                    />
                </div>
            </PageShell>
        </AppLayout>
    );
}
