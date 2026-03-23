import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { LoanRequestStepIndicator } from '@/components/loan-request/loan-request-step-indicator';
import {
    LoanRequestApplicantPersonalStep,
    LoanRequestApplicantWorkStep,
    LoanRequestCoMakerStep,
    LoanRequestLoanDetailsStep,
    LoanRequestReviewStep,
} from '@/components/loan-request/loan-request-steps';
import { LoanRequestWizardFooter } from '@/components/loan-request/loan-request-wizard-footer';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/formatters';
import { loans as clientLoans } from '@/routes/client';
import LoanRequestController from '@/actions/App/Http/Controllers/Client/LoanRequestController';
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

const toStringValue = (value?: string | number | null): string => {
    if (value === null || value === undefined) {
        return '';
    }

    const stringValue = `${value}`.trim();

    return stringValue === '0' || stringValue === '0.00' ? '' : stringValue;
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
        number_of_children: toStringValue(person.number_of_children),
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
                    setCurrentStep(step);
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
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <Heading
                            title="Loan request"
                            description="Complete the application form to request a new loan."
                        />
                        <p className="text-sm text-muted-foreground">
                            Account No: {member.acctno ?? '--'}
                        </p>
                    </div>
                    <Button asChild variant="ghost" size="sm">
                        <Link href={clientLoans().url}>Back to loans</Link>
                    </Button>
                </div>

                <div className="space-y-3">
                    <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <span className="text-xs font-semibold uppercase">
                            Step {currentStep + 1} of {steps.length}
                        </span>
                        <span>{steps[currentStep]?.title}</span>
                    </div>
                    <LoanRequestStepIndicator
                        steps={steps}
                        currentStep={currentStep}
                        onStepChange={(index) => setCurrentStep(index)}
                    />
                </div>

                {draft ? (
                    <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <LoanRequestStatusBadge status={draft.status} />
                        {draftUpdatedAt ? (
                            <span>Last saved {draftUpdatedAt}</span>
                        ) : null}
                        {form.recentlySuccessful && lastAction === 'draft' ? (
                            <span className="text-xs text-emerald-600">
                                Draft saved.
                            </span>
                        ) : null}
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        You can save this request as a draft at any time and
                        resume later.
                    </p>
                )}

                {loanTypes.length === 0 ? (
                    <Alert variant="destructive">
                        <AlertTitle>Loan types unavailable</AlertTitle>
                        <AlertDescription>
                            Please contact support to load available loan
                            options before submitting a request.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="space-y-6">
                    {currentStep === 0 ? (
                        <LoanRequestLoanDetailsStep
                            data={form.data}
                            errors={form.errors}
                            loanTypes={loanTypes}
                            onChange={handleLoanDetailChange}
                        />
                    ) : null}

                    {currentStep === 1 ? (
                        <LoanRequestApplicantPersonalStep
                            values={form.data.applicant}
                            errors={form.errors}
                            readOnly={applicantReadOnly}
                            onChange={updatePersonField('applicant')}
                        />
                    ) : null}

                    {currentStep === 2 ? (
                        <LoanRequestApplicantWorkStep
                            values={form.data.applicant}
                            errors={form.errors}
                            onChange={updatePersonField('applicant')}
                        />
                    ) : null}

                    {currentStep === 3 ? (
                        <LoanRequestCoMakerStep
                            title="Co-maker 1"
                            description="Provide details for your first co-maker."
                            prefix="co_maker_1"
                            values={form.data.co_maker_1}
                            errors={form.errors}
                            onChange={updatePersonField('co_maker_1')}
                        />
                    ) : null}

                    {currentStep === 4 ? (
                        <LoanRequestCoMakerStep
                            title="Co-maker 2"
                            description="Provide details for your second co-maker."
                            prefix="co_maker_2"
                            values={form.data.co_maker_2}
                            errors={form.errors}
                            onChange={updatePersonField('co_maker_2')}
                        />
                    ) : null}

                    {currentStep === 5 ? (
                        <LoanRequestReviewStep
                            data={form.data}
                            loanTypes={loanTypes}
                            member={member}
                            errors={form.errors}
                            onUndertakingChange={(value) =>
                                form.setData('undertaking_accepted', value)
                            }
                        />
                    ) : null}
                </div>

                <LoanRequestWizardFooter
                    isFirstStep={isFirstStep}
                    isLastStep={isLastStep}
                    onBack={() =>
                        setCurrentStep((step) => Math.max(0, step - 1))
                    }
                    onNext={() =>
                        setCurrentStep((step) =>
                            Math.min(steps.length - 1, step + 1),
                        )
                    }
                    onSaveDraft={handleSaveDraft}
                    onSubmit={handleSubmit}
                    isSavingDraft={isSavingDraft}
                    isSubmitting={isSubmitting}
                    disablePrimary={!hasLoanTypes}
                />
            </div>
        </AppLayout>
    );
}
