import type { LoanRequestStatusFilterOption } from '@/components/loan-request/loan-request-page-sections';
import type { LoanRequestStatusValue } from '@/types/loan-requests';

export type LoanRequestQueueStatusFilter =
    | 'all'
    | 'pending_review'
    | 'under_review'
    | 'needs_revision'
    | 'awaiting_member_information'
    | 'recommended_for_approval'
    | 'awaiting_member_acceptance'
    | 'rejected'
    | 'approved'
    | 'declined'
    | 'member_declined_terms'
    | 'converted_to_loan'
    | 'cancelled'
    | 'reported';

export const loanRequestQueueStatusLabels: Record<
    Exclude<LoanRequestQueueStatusFilter, 'all'>,
    string
> = {
    pending_review: 'Pending Processing',
    under_review: 'In Processing',
    needs_revision: 'Awaiting Member Correction',
    awaiting_member_information: 'Awaiting Member Information',
    recommended_for_approval: 'For Loan Manager Review',
    awaiting_member_acceptance: 'Awaiting Member Acceptance',
    rejected: 'Rejected During Processing',
    approved: 'Approved',
    declined: 'Declined by Loan Manager',
    member_declined_terms: 'Member Declined Terms',
    converted_to_loan: 'Converted to Loan',
    cancelled: 'Cancelled',
    reported: 'Reported',
};

export const adminLoanRequestQueueStatusOptions: Array<
    LoanRequestStatusFilterOption<LoanRequestQueueStatusFilter>
> = [
    { value: 'all', label: 'All' },
    { value: 'pending_review', label: 'Pending Processing' },
    { value: 'under_review', label: 'In Processing' },
    { value: 'needs_revision', label: 'Awaiting Member Correction' },
    {
        value: 'awaiting_member_information',
        label: 'Awaiting Member Information',
    },
    { value: 'recommended_for_approval', label: 'For Loan Manager Review' },
    {
        value: 'awaiting_member_acceptance',
        label: 'Awaiting Member Acceptance',
    },
    { value: 'rejected', label: 'Rejected During Processing' },
    { value: 'approved', label: 'Approved' },
    { value: 'declined', label: 'Declined by Loan Manager' },
    { value: 'member_declined_terms', label: 'Member Declined Terms' },
    { value: 'converted_to_loan', label: 'Converted to Loan' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'reported', label: 'Reported' },
];

const workflowStatusOrder: Array<
    Exclude<LoanRequestQueueStatusFilter, 'all' | 'reported' | 'cancelled'>
> = [
    'pending_review',
    'under_review',
    'needs_revision',
    'awaiting_member_information',
    'recommended_for_approval',
    'awaiting_member_acceptance',
    'rejected',
    'member_declined_terms',
    'approved',
    'declined',
    'converted_to_loan',
];

const statusesForRole: Record<string, typeof workflowStatusOrder> = {
    loan_processor: [
        'pending_review',
        'under_review',
        'needs_revision',
        'awaiting_member_information',
        'recommended_for_approval',
        'awaiting_member_acceptance',
        'rejected',
        'member_declined_terms',
    ],
    loan_manager: [
        'recommended_for_approval',
        'awaiting_member_acceptance',
        'approved',
        'declined',
        'member_declined_terms',
        'converted_to_loan',
    ],
};

export const normalizeLoanRequestQueueStatus = (
    status: LoanRequestStatusValue | null,
): LoanRequestStatusValue | null => {
    if (status === 'submitted' || status === 'pending_co_maker_signatures') {
        return 'pending_review';
    }

    return status;
};

export const buildStaffLoanRequestQueueStatusOptions = (
    roles: string[],
): Array<LoanRequestStatusFilterOption<LoanRequestQueueStatusFilter>> => {
    const resolvedStatuses = new Set<
        Exclude<LoanRequestQueueStatusFilter, 'all' | 'reported' | 'cancelled'>
    >();

    if (roles.includes('superadmin')) {
        workflowStatusOrder.forEach((status) => resolvedStatuses.add(status));
    }

    roles.forEach((role) => {
        (statusesForRole[role] ?? []).forEach((status) =>
            resolvedStatuses.add(status),
        );
    });

    const orderedStatuses =
        resolvedStatuses.size > 0
            ? workflowStatusOrder.filter((status) =>
                  resolvedStatuses.has(status),
              )
            : [];

    return [
        { value: 'all', label: 'All' },
        ...orderedStatuses.map((status) => ({
            value: status,
            label: loanRequestQueueStatusLabels[status],
        })),
        { value: 'reported', label: 'Reported' },
    ];
};
