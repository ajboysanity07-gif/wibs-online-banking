export type LoanRequestWizardGroupId =
    | 'loan-details'
    | 'about-you'
    | 'co-makers'
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
        title: 'Work & income',
        description: 'Share your employment, employer, and income details.',
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
        title: 'Co-maker 1: work & income',
        description:
            'Employment, employer, and income details for your first co-maker.',
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
        title: 'Co-maker 2: work & income',
        description:
            'Employment, employer, and income details for your second co-maker.',
        group: 'co-makers',
    },
    {
        id: 'co-maker-2-income',
        title: 'Co-maker 2: income & details',
        description: 'Income and business details for your second co-maker.',
        group: 'co-makers',
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

/**
 * Applicant personal, contact, family and work/income content all render on
 * one "About you" step (at `personal-basic`'s slot), and each employment/
 * income pair (applicant and both co-makers) shares the employment step's
 * slot. The collapsed ids stay in `loanRequestWizardSteps` so error-key-to-
 * step resolution keeps working off stable ids -- only this filtered view
 * (and the `STEP_INDEX` built from it) collapses them.
 */
const COLLAPSED_STEP_IDS = new Set([
    'personal-contact',
    'personal-family',
    'work-employment',
    'work-income',
    'co-maker-1-income',
    'co-maker-2-income',
]);

/** Returns the step list the client wizard should actually render/index. */
export function getVisibleWizardSteps(): LoanRequestWizardStep[] {
    return loanRequestWizardSteps
        .filter((step) => !COLLAPSED_STEP_IDS.has(step.id))
        .map((step) =>
            step.id === 'personal-basic'
                ? {
                      ...step,
                      title: 'About you',
                      description:
                          'Confirm your personal, contact, family, and work details.',
                  }
                : step,
        );
}

export function buildStepIndex(
    steps: LoanRequestWizardStep[],
): Record<string, number> {
    return Object.fromEntries(steps.map((step, index) => [step.id, index]));
}
