import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import LoanRequestController from '@/actions/App/Http/Controllers/Client/LoanRequestController';
import { LoanRequestAnimatedStep } from '@/components/loan-request/loan-request-animated-step';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { LoanRequestStepIndicator } from '@/components/loan-request/loan-request-step-indicator';
import {
    LoanRequestApplicantPersonalStep,
    LoanRequestApplicantWorkStep,
    LoanRequestCoMakerStep,
    LoanRequestDataSectionStep,
    LoanRequestInsuranceBeneficiariesStep,
    LoanRequestLoanDetailsStep,
    LoanRequestReviewStep,
} from '@/components/loan-request/loan-request-steps';
import { LoanRequestSummaryPanel } from '@/components/loan-request/loan-request-summary-panel';
import { LoanRequestWizardActions } from '@/components/loan-request/loan-request-wizard-footer';
import { PageShell } from '@/components/page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import client from '@/lib/api/client';
import { formatDateTime, toDateInputValue } from '@/lib/formatters';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import { dashboard as clientDashboard } from '@/routes/client';
import { index as loanRequestsIndex } from '@/routes/client/loan-requests';
import type { BreadcrumbItem } from '@/types';
import type {
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
} from '@/types/loan-requests';

const loanRequestsIndexHref = loanRequestsIndex().url;

type Props = {
    loanTypes: LoanTypeOption[];
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    applicantReadOnly: LoanRequestReadOnlyMap | null;
    member: LoanRequestMemberSummary;
    dataSections: LoanRequestDataSections;
    dataSectionDefinitions: LoanRequestDataSectionDefinitions;
    draft: LoanRequestDraft | null;
    initialStep: number;
};

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
        id: 'personal-basic',
        title: 'Personal: basic info',
        description: 'Confirm your basic personal information.',
    },
    {
        id: 'personal-contact',
        title: 'Personal: address & contact',
        description: 'Confirm your address and contact details.',
    },
    {
        id: 'personal-family',
        title: 'Personal: family & spouse',
        description: 'Confirm civil status, education, and family details.',
    },
    {
        id: 'work-employment',
        title: 'Work: employment',
        description: 'Share your employment and employer details.',
    },
    {
        id: 'work-income',
        title: 'Work: income & details',
        description: 'Share your income, position, and business details.',
    },
    {
        id: 'co-maker-1-basic',
        title: 'Co-maker 1: basic info',
        description: 'Basic personal details for your first co-maker.',
    },
    {
        id: 'co-maker-1-contact',
        title: 'Co-maker 1: address & contact',
        description: 'Address and contact details for your first co-maker.',
    },
    {
        id: 'co-maker-1-employment',
        title: 'Co-maker 1: employment',
        description: 'Employment and employer details for your first co-maker.',
    },
    {
        id: 'co-maker-1-income',
        title: 'Co-maker 1: income & details',
        description: 'Income and business details for your first co-maker.',
    },
    {
        id: 'co-maker-2-basic',
        title: 'Co-maker 2: basic info',
        description: 'Basic personal details for your second co-maker.',
    },
    {
        id: 'co-maker-2-contact',
        title: 'Co-maker 2: address & contact',
        description: 'Address and contact details for your second co-maker.',
    },
    {
        id: 'co-maker-2-employment',
        title: 'Co-maker 2: employment',
        description: 'Employment and employer details for your second co-maker.',
    },
    {
        id: 'co-maker-2-income',
        title: 'Co-maker 2: income & details',
        description: 'Income and business details for your second co-maker.',
    },
    {
        id: 'insurance',
        title: 'Insurance & beneficiaries',
        description:
            'Provide beneficiary details required for document generation.',
    },
    {
        id: 'health',
        title: 'Health declarations',
        description:
            'Complete the required health declarations for the request.',
    },
    {
        id: 'banking',
        title: 'Bank & payout',
        description: 'Provide the payout bank and account information.',
    },
    {
        id: 'barangay',
        title: 'Barangay information',
        description: 'Provide the barangay details required for the forms.',
    },
    {
        id: 'declarations',
        title: 'Declarations',
        description: 'Review the required declarations and consent statements.',
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

const applicantBasicFields = new Set([
    'first_name',
    'last_name',
    'middle_name',
    'nickname',
    'birthdate',
    'birthplace_city',
    'birthplace_province',
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

        if (key.startsWith('applicant.')) {
            const field = key.replace('applicant.', '');
            stepMatches.push(
                applicantBasicFields.has(field)
                    ? 1
                    : applicantContactFields.has(field)
                      ? 2
                      : applicantFamilyFields.has(field)
                        ? 3
                        : applicantEmploymentFields.has(field)
                          ? 4
                          : personWorkFields.has(field)
                            ? 5
                            : 1,
            );
            return;
        }

        if (key.startsWith('co_maker_1.')) {
            const field = key.replace('co_maker_1.', '');
            stepMatches.push(
                applicantBasicFields.has(field)
                    ? 6
                    : applicantContactFields.has(field) ||
                        field === 'educational_attainment'
                      ? 7
                      : applicantEmploymentFields.has(field)
                        ? 8
                        : personWorkFields.has(field)
                          ? 9
                          : 6,
            );
            return;
        }

        if (key.startsWith('co_maker_2.')) {
            const field = key.replace('co_maker_2.', '');
            stepMatches.push(
                applicantBasicFields.has(field)
                    ? 10
                    : applicantContactFields.has(field) ||
                        field === 'educational_attainment'
                      ? 11
                      : applicantEmploymentFields.has(field)
                        ? 12
                        : personWorkFields.has(field)
                          ? 13
                          : 10,
            );
            return;
        }

        if (key.startsWith('insurance.') || key === 'document_data') {
            stepMatches.push(14);
            return;
        }

        if (key.startsWith('health.')) {
            stepMatches.push(15);
            return;
        }

        if (key.startsWith('banking.')) {
            stepMatches.push(16);
            return;
        }

        if (key.startsWith('barangay.')) {
            stepMatches.push(17);
            return;
        }

        if (key.startsWith('declarations.')) {
            stepMatches.push(18);
            return;
        }

        if (key === 'undertaking_accepted') {
            stepMatches.push(19);
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
    member,
    dataSections,
    dataSectionDefinitions,
    draft,
    initialStep,
}: Props) {
    const [currentStep, setCurrentStep] = useState(initialStep);
    const [highestStepReached, setHighestStepReached] = useState(initialStep);
    const [stepDirection, setStepDirection] = useState<'forward' | 'backward'>(
        'forward',
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

    const initialFormData = useMemo<LoanRequestFormData>(
        () => ({
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
            banking: {
                ...dataSections.banking,
            },
            barangay: {
                ...dataSections.barangay,
            },
            declarations: {
                ...dataSections.declarations,
            },
        }),
        [applicant, coMakerOne, coMakerTwo, dataSections, draft, loanTypes],
    );

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

    const updateDataSection =
        (
            sectionKey: keyof Pick<
                LoanRequestFormData,
                'insurance' | 'health' | 'banking' | 'barangay' | 'declarations'
            >,
        ) =>
        (field: string, value: string | number | boolean | null) => {
            form.setData(sectionKey, {
                ...(form.data[sectionKey] as LoanRequestDataSectionValues),
                [field]: value,
            });
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
                const step = resolveStepFromErrors(errors);

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
                                Complete the application form and save a draft at any time.
                                Signatures will be collected physically upon loan release.
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
                    <div className="flex flex-col lg:flex-row">
                        <div className="w-full border-b border-border/50 bg-muted/15 lg:w-60 lg:shrink-0 lg:border-r lg:border-b-0">
                            <LoanRequestStepIndicator
                                currentStep={currentStep}
                                onStepClick={handleStepChange}
                            />
                        </div>
                        <div className="min-w-0 flex-1 p-6 sm:p-7 lg:p-8">
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
                                        show={currentStep === 4}
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
                                        show={currentStep === 5}
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
                                        show={currentStep === 6}
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
                                        />
                                    </LoanRequestAnimatedStep>

                                    <LoanRequestAnimatedStep
                                        show={currentStep === 7}
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
                                        show={currentStep === 8}
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
                                        show={currentStep === 9}
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
                                        />
                                    </LoanRequestAnimatedStep>

                                    <LoanRequestAnimatedStep
                                        show={currentStep === 10}
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
                                        />
                                    </LoanRequestAnimatedStep>

                                    <LoanRequestAnimatedStep
                                        show={currentStep === 11}
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
                                        show={currentStep === 12}
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
                                        show={currentStep === 13}
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
                                        />
                                    </LoanRequestAnimatedStep>

                                    <LoanRequestAnimatedStep
                                        show={currentStep === 14}
                                        direction={stepDirection}
                                    >
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
                                    </LoanRequestAnimatedStep>

                                    <LoanRequestAnimatedStep
                                        show={currentStep === 15}
                                        direction={stepDirection}
                                    >
                                        <LoanRequestDataSectionStep
                                            sectionKey="health"
                                            title="Health declarations"
                                            description="Confirm the required health declarations before submission."
                                            values={form.data.health}
                                            definition={
                                                dataSectionDefinitions.health
                                            }
                                            errors={form.errors}
                                            onChange={updateDataSection(
                                                'health',
                                            )}
                                        />
                                    </LoanRequestAnimatedStep>

                                    <LoanRequestAnimatedStep
                                        show={currentStep === 16}
                                        direction={stepDirection}
                                    >
                                        <LoanRequestDataSectionStep
                                            sectionKey="banking"
                                            title="Bank and payout information"
                                            description="Provide the payout bank account details that staff will use for processing."
                                            values={form.data.banking}
                                            definition={
                                                dataSectionDefinitions.banking
                                            }
                                            errors={form.errors}
                                            onChange={updateDataSection(
                                                'banking',
                                            )}
                                        />
                                    </LoanRequestAnimatedStep>

                                    <LoanRequestAnimatedStep
                                        show={currentStep === 17}
                                        direction={stepDirection}
                                    >
                                        <LoanRequestDataSectionStep
                                            sectionKey="barangay"
                                            title="Barangay information"
                                            description="Provide the barangay details required for the supporting documents."
                                            values={form.data.barangay}
                                            definition={
                                                dataSectionDefinitions.barangay
                                            }
                                            errors={form.errors}
                                            onChange={updateDataSection(
                                                'barangay',
                                            )}
                                        />
                                    </LoanRequestAnimatedStep>

                                    <LoanRequestAnimatedStep
                                        show={currentStep === 18}
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
                                        show={currentStep === 19}
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

                                    <LoanRequestWizardActions
                                        isFirstStep={isFirstStep}
                                        isLastStep={isLastStep}
                                        onBack={handlePreviousStep}
                                        onNext={handleNextStep}
                                        onSaveDraft={handleSaveDraft}
                                        onSubmit={handleSubmit}
                                        isSavingDraft={isSavingDraft}
                                        isSubmitting={isSubmitting}
                                        disablePrimary={!hasLoanTypes}
                                    />
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
                        </div>
                    </div>
                </div>
            </PageShell>
        </AppLayout>
    );
}
