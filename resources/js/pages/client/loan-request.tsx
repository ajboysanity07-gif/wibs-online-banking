import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useMemo, useState } from 'react';
import LoanRequestController from '@/actions/App/Http/Controllers/Client/LoanRequestController';
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
import { LoanRequestWizardFooter } from '@/components/loan-request/loan-request-wizard-footer';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/formatters';
import { loans as clientLoans } from '@/routes/client';
import type { BreadcrumbItem } from '@/types';
import type {
    LoanRequestDraft,
    LoanRequestFormData,
    LoanRequestMemberSummary,
    LoanRequestPersonData,
    LoanRequestPersonFormData,
    LoanRequestReadOnlyMap,
    LoanTypeOption,
} from '@/types/loan-requests';

type Props = {
    loanTypes: LoanTypeOption[];
    applicant: LoanRequestPersonData | null;
    coMakerOne: LoanRequestPersonData | null;
    coMakerTwo: LoanRequestPersonData | null;
    applicantReadOnly: LoanRequestReadOnlyMap | null;
    member: LoanRequestMemberSummary;
    draft: LoanRequestDraft | null;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Loans', href: clientLoans().url },
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
    'birthplace',
    'address',
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
    'employer_business_address',
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

const emptyPerson: LoanRequestPersonFormData = {
    first_name: '',
    middle_name: '',
    last_name: '',
    nickname: '',
    birthdate: '',
    birthplace: '',
    address: '',
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
    employer_business_address: '',
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
        birthdate: person.birthdate ?? '',
        birthplace: person.birthplace ?? '',
        address: person.address ?? '',
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
        employer_business_address: person.employer_business_address ?? '',
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
    applicantReadOnly,
    member,
    draft,
}: Props) {
    const [currentStep, setCurrentStep] = useState(0);
    const [stepDirection, setStepDirection] = useState<
        'forward' | 'backward'
    >('forward');
    const [activeAction, setActiveAction] = useState<
        'draft' | 'submit' | null
    >(null);
    const [lastAction, setLastAction] = useState<'draft' | 'submit' | null>(
        null,
    );

    const initialFormData = useMemo<LoanRequestFormData>(
        () => ({
            typecode:
                draft?.typecode ??
                loanTypes[0]?.typecode ??
                '',
            requested_amount: toStringValue(draft?.requested_amount),
            requested_term: toStringValue(draft?.requested_term),
            loan_purpose: draft?.loan_purpose ?? '',
            availment_status: draft?.availment_status ?? '',
            undertaking_accepted: false,
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

    const updatePersonField =
        (section: 'applicant' | 'co_maker_1' | 'co_maker_2') =>
        (field: keyof LoanRequestPersonFormData, value: string) => {
            form.setData((current) => ({
                ...current,
                [section]: {
                    ...current[section],
                    [field]: value,
                },
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
            onError: (errors) => {
                const step = resolveStepFromErrors(errors);

                if (step !== null) {
                    handleStepChange(step);
                }
            },
            onFinish: () => setActiveAction(null),
        });
    };

    const draftUpdatedAt = draft?.updated_at
        ? formatDateTime(draft.updated_at)
        : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan request" />
            <div className="mx-auto flex w-full max-w-7xl flex-col gap-9 px-4 pb-28 pt-8">
                <div className="rounded-2xl border border-border/40 bg-card/60 p-6 shadow-sm sm:p-7 lg:p-8">
                    <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                        <div className="space-y-2">
                            <p className="text-xs font-semibold uppercase tracking-[0.24em] text-muted-foreground">
                                Loan request
                            </p>
                            <h1 className="text-3xl font-semibold tracking-tight">
                                Apply for a loan
                            </h1>
                            <p className="max-w-2xl text-sm text-muted-foreground">
                                Complete the application form to request a new
                                loan. You can save a draft at any time and
                                resume later.
                            </p>
                            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span className="rounded-full bg-muted/30 px-2 py-1">
                                    Account No: {member.acctno ?? '--'}
                                </span>
                                {draft ? (
                                    <>
                                        <LoanRequestStatusBadge
                                            status={draft.status}
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
                            <Link href={clientLoans().url}>
                                <ArrowLeft className="h-4 w-4" />
                                Back to loans
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
                                <AlertTitle>
                                    Loan types unavailable
                                </AlertTitle>
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
                            />
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={currentStep === 3}
                            direction={stepDirection}
                        >
                            <LoanRequestCoMakerStep
                                title="Co-maker 1"
                                description="Provide details for your first co-maker."
                                prefix="co_maker_1"
                                values={form.data.co_maker_1}
                                errors={form.errors}
                                onChange={updatePersonField('co_maker_1')}
                            />
                        </LoanRequestAnimatedStep>

                        <LoanRequestAnimatedStep
                            show={currentStep === 4}
                            direction={stepDirection}
                        >
                            <LoanRequestCoMakerStep
                                title="Co-maker 2"
                                description="Provide details for your second co-maker."
                                prefix="co_maker_2"
                                values={form.data.co_maker_2}
                                errors={form.errors}
                                onChange={updatePersonField('co_maker_2')}
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
                                onUndertakingChange={(value) =>
                                    form.setData(
                                        'undertaking_accepted',
                                        value,
                                    )
                                }
                            />
                        </LoanRequestAnimatedStep>
                    </div>

                    <LoanRequestSummaryPanel
                        data={form.data}
                        loanTypes={loanTypes}
                        member={member}
                        draft={draft}
                        draftUpdatedAt={draftUpdatedAt}
                    />
                </div>
            </div>

            <LoanRequestWizardFooter
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
        </AppLayout>
    );
}
