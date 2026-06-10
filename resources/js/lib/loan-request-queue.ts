import type { LoanRequestStatusFilterOption } from '@/components/loan-request/loan-request-page-sections';
import type { LoanRequestStatusValue } from '@/types/loan-requests';

export type LoanRequestQueueStatusFilter =
    | 'all'
    | 'pending_review'
    | 'under_review'
    | 'needs_revision'
    | 'recommended_for_approval'
    | 'rejected'
    | 'approved'
    | 'declined'
    | 'converted_to_loan'
    | 'cancelled'
    | 'reported';

export const loanRequestQueueStatusLabels: Record<
    Exclude<LoanRequestQueueStatusFilter, 'all'>,
    string
> = {
    pending_review: 'Pending Review',
    under_review: 'Under review',
    needs_revision: 'Needs Revision',
    recommended_for_approval: 'Recommended for Approval',
    rejected: 'Rejected',
    approved: 'Approved',
    declined: 'Declined',
    converted_to_loan: 'Converted to Loan',
    cancelled: 'Cancelled',
    reported: 'Reported',
};

export const adminLoanRequestQueueStatusOptions: Array<
    LoanRequestStatusFilterOption<LoanRequestQueueStatusFilter>
> = [
    { value: 'all', label: 'All' },
    { value: 'pending_review', label: 'Pending Review' },
    { value: 'under_review', label: 'Under review' },
    { value: 'needs_revision', label: 'Needs Revision' },
    { value: 'recommended_for_approval', label: 'Recommended for Approval' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'approved', label: 'Approved' },
    { value: 'declined', label: 'Declined' },
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
    'recommended_for_approval',
    'rejected',
    'approved',
    'declined',
    'converted_to_loan',
];

const statusesForRole: Record<string, typeof workflowStatusOrder> = {
    admin: workflowStatusOrder,
    loan_officer: [
        'pending_review',
        'under_review',
        'needs_revision',
        'recommended_for_approval',
        'rejected',
    ],
    loan_manager: [
        'recommended_for_approval',
        'approved',
        'declined',
        'converted_to_loan',
    ],
};

export const normalizeLoanRequestQueueStatus = (
    status: LoanRequestStatusValue | null,
): LoanRequestStatusValue | null => {
    if (status === 'submitted' || status === 'pending_co_maker_signatures') {
        return 'under_review';
    }

    return status;
};

export const buildStaffLoanRequestQueueStatusOptions = (
    roles: string[],
    isAdmin: boolean,
): Array<LoanRequestStatusFilterOption<LoanRequestQueueStatusFilter>> => {
    const resolvedStatuses = new Set<
        Exclude<LoanRequestQueueStatusFilter, 'all' | 'reported' | 'cancelled'>
    >();

    if (isAdmin) {
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
            : workflowStatusOrder;

    return [
        { value: 'all', label: 'All' },
        ...orderedStatuses.map((status) => ({
            value: status,
            label: loanRequestQueueStatusLabels[status],
        })),
    ];
};
