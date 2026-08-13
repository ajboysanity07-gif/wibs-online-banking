export type LoanRequestWizardGroupId =
    | 'loan-details'
    | 'about-you'
    | 'co-makers'
    | 'insurance-health'
    | 'bank-payout'
    | 'declarations-review';

export type LoanRequestWizardStep = {
    id: string;
    title: string;
    description: string;
    /**
     * Key into a group-meta map (label + icon) for the sidebar step
     * indicator. Typed as `string` rather than `LoanRequestWizardGroupId` so
     * other wizards (e.g. the admin correction dialog) can define their own
     * group ids while reusing the same sidebar component.
     */
    group: string;
    /** Label shown in the step-indicator sidebar, if different from `title`. */
    sidebarLabel?: string;
};

/**
 * Single source of truth for the loan request wizard's step content, order,
 * and group membership. The step indicator sidebar derives its group
 * labels/counters from this array — add or remove a step here only.
 */
export const loanRequestWizardSteps: LoanRequestWizardStep[] = [
    {
        id: 'loan-details',
        title: 'Loan details',
        description: 'Set the loan type, amount, term, and purpose.',
        group: 'loan-details',
    },
    {
        id: 'personal-basic',
        title: 'Personal: basic info',
        description: 'Confirm your basic personal information.',
        group: 'about-you',
    },
    {
        id: 'personal-contact',
        title: 'Personal: address & contact',
        description: 'Confirm your address and contact details.',
        group: 'about-you',
    },
    {
        id: 'personal-family',
        title: 'Personal: family & spouse',
        description: 'Confirm civil status, education, and family details.',
        group: 'about-you',
    },
    {
        id: 'work-employment',
        title: 'Work: employment',
        description: 'Share your employment and employer details.',
        group: 'about-you',
    },
    {
        id: 'work-income',
        title: 'Work: income & details',
        description: 'Share your income, position, and business details.',
        group: 'about-you',
    },
    {
        id: 'co-maker-1-basic',
        title: 'Co-maker 1: basic info',
        description: 'Basic personal details for your first co-maker.',
        group: 'co-makers',
    },
    {
        id: 'co-maker-1-contact',
        title: 'Co-maker 1: address & contact',
        description: 'Address and contact details for your first co-maker.',
        group: 'co-makers',
    },
    {
        id: 'co-maker-1-employment',
        title: 'Co-maker 1: employment',
        description: 'Employment and employer details for your first co-maker.',
        group: 'co-makers',
    },
    {
        id: 'co-maker-1-income',
        title: 'Co-maker 1: income & details',
        description: 'Income and business details for your first co-maker.',
        group: 'co-makers',
    },
    {
        id: 'co-maker-2-basic',
        title: 'Co-maker 2: basic info',
        description: 'Basic personal details for your second co-maker.',
        group: 'co-makers',
    },
    {
        id: 'co-maker-2-contact',
        title: 'Co-maker 2: address & contact',
        description: 'Address and contact details for your second co-maker.',
        group: 'co-makers',
    },
    {
        id: 'co-maker-2-employment',
        title: 'Co-maker 2: employment',
        description:
            'Employment and employer details for your second co-maker.',
        group: 'co-makers',
    },
    {
        id: 'co-maker-2-income',
        title: 'Co-maker 2: income & details',
        description: 'Income and business details for your second co-maker.',
        group: 'co-makers',
    },
    {
        id: 'insurance',
        title: 'Insurance & beneficiaries',
        description:
            'Provide beneficiary details -- who receives the insurance payout -- required for document generation.',
        group: 'insurance-health',
    },
    {
        id: 'dependents',
        title: 'Dependents',
        description:
            'Add dependents covered under your group life insurance plan (optional) -- separate from your beneficiaries above.',
        group: 'insurance-health',
    },
    {
        id: 'health',
        title: 'Health Insurance Questionnaire (1 of 5)',
        description: 'Answer the Health Insurance Questionnaire questions.',
        group: 'insurance-health',
        sidebarLabel: 'Health Insurance Questionnaire',
    },
    {
        id: 'health-glapi-2',
        title: 'Health Insurance Questionnaire (2 of 5)',
        description: 'Answer the Health Insurance Questionnaire questions.',
        group: 'insurance-health',
    },
    {
        id: 'health-glapi-3',
        title: 'Health Insurance Questionnaire (3 of 5)',
        description: 'Answer the Health Insurance Questionnaire questions.',
        group: 'insurance-health',
    },
    {
        id: 'health-glapi-4',
        title: 'Health Insurance Questionnaire (4 of 5)',
        description: 'Answer the Health Insurance Questionnaire questions.',
        group: 'insurance-health',
    },
    {
        id: 'health-glapi-5',
        title: 'Health Insurance Questionnaire (5 of 5)',
        description: 'Answer the Health Insurance Questionnaire questions.',
        group: 'insurance-health',
    },
    {
        id: 'banking',
        title: 'Loan Disbursement & Repayment',
        description:
            "Tell us how you'd like to receive your loan and how you'll repay it. Barangay details are optional.",
        group: 'bank-payout',
        sidebarLabel: 'Disbursement & Repayment',
    },
    {
        id: 'declarations',
        title: 'Declarations',
        description: 'Review the required declarations and consent statements.',
        group: 'declarations-review',
    },
    {
        id: 'review',
        title: 'Review',
        description: 'Review and confirm the undertaking.',
        group: 'declarations-review',
        sidebarLabel: 'Review & submit',
    },
];
