import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { LoanRequestStatusValue } from '@/types/loan-requests';

type Props = {
    status?: LoanRequestStatusValue | null;
    className?: string;
};

const statusLabels: Partial<Record<LoanRequestStatusValue, string>> = {
    draft: 'Draft',
    pending_co_maker_signatures: 'Pending Co-Maker Signatures',
    submitted: 'Submitted',
    pending_review: 'Pending Review',
    under_review: 'Under review',
    needs_revision: 'Needs Revision',
    recommended_for_approval: 'Recommended for Approval',
    rejected: 'Rejected',
    approved: 'Approved',
    declined: 'Declined',
    converted_to_loan: 'Converted to Loan',
    cancelled: 'Cancelled',
};

const statusVariant = (status?: LoanRequestStatusValue | null) => {
    if (status === 'approved' || status === 'converted_to_loan') {
        return 'default';
    }

    if (
        status === 'declined' ||
        status === 'rejected' ||
        status === 'cancelled'
    ) {
        return 'destructive';
    }

    if (
        status === 'pending_review' ||
        status === 'under_review' ||
        status === 'recommended_for_approval'
    ) {
        return 'secondary';
    }

    return 'outline';
};

const statusClassName = (status?: LoanRequestStatusValue | null): string => {
    if (status === 'needs_revision') {
        return 'border-orange-500/30 bg-orange-500/10 text-orange-700 dark:text-orange-200';
    }

    if (status === 'recommended_for_approval') {
        return 'border-indigo-500/30 bg-indigo-500/10 text-indigo-700 dark:text-indigo-200';
    }

    if (status === 'pending_review') {
        return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-200';
    }

    if (status === 'under_review') {
        return 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-200';
    }

    if (status === 'converted_to_loan') {
        return 'border-teal-500/30 bg-teal-500/10 text-teal-700 dark:text-teal-200';
    }

    return '';
};

export function LoanRequestStatusBadge({ status, className }: Props) {
    return (
        <Badge
            variant={statusVariant(status)}
            className={cn(statusClassName(status), className)}
        >
            {status ? (statusLabels[status] ?? 'Unknown') : 'Unknown'}
        </Badge>
    );
}
