import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { LoanRequestStatusValue } from '@/types/loan-requests';

type Props = {
    status?: LoanRequestStatusValue | null;
    className?: string;
};

const statusLabels: Partial<Record<LoanRequestStatusValue, string>> = {
    draft: 'Draft',
    pending_co_maker_signatures: 'Legacy Pending Co-Maker Signatures',
    submitted: 'Legacy Submitted',
    pending_review: 'Pending Processing',
    under_review: 'In Processing',
    needs_revision: 'Awaiting Member Correction',
    awaiting_member_information: 'Awaiting Member Information',
    recommended_for_approval: 'For Loan Manager Review',
    awaiting_member_acceptance: 'Awaiting Member Acceptance',
    rejected: 'Rejected During Processing',
    approved: 'Approved',
    declined: 'Declined by Loan Manager',
    member_declined_terms: 'Member Declined Revised Terms',
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
        status === 'recommended_for_approval' ||
        status === 'awaiting_member_information' ||
        status === 'awaiting_member_acceptance'
    ) {
        return 'secondary';
    }

    return 'outline';
};

const statusClassName = (status?: LoanRequestStatusValue | null): string => {
    if (status === 'approved') {
        return 'border-[var(--ok-bd)] bg-[var(--ok-bg)] text-[var(--ok-ink)]';
    }

    if (
        status === 'declined' ||
        status === 'rejected' ||
        status === 'cancelled'
    ) {
        return 'border-[var(--bad-bd)] bg-[var(--bad-bg)] text-[var(--bad-ink)]';
    }

    if (status === 'needs_revision') {
        return 'border-[var(--warn-bd)] bg-[var(--warn-bg)] text-[var(--warn-ink)]';
    }

    if (status === 'awaiting_member_information') {
        return 'border-yellow-500/30 bg-yellow-500/10 text-yellow-700 dark:text-yellow-200';
    }

    if (status === 'recommended_for_approval') {
        return 'border-indigo-500/30 bg-indigo-500/10 text-indigo-700 dark:text-indigo-200';
    }

    if (status === 'awaiting_member_acceptance') {
        return 'border-violet-500/30 bg-violet-500/10 text-violet-700 dark:text-violet-200';
    }

    if (status === 'pending_review') {
        return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-200';
    }

    if (status === 'under_review') {
        return 'border-[var(--info-bd)] bg-[var(--info-bg)] text-[var(--info-ink)]';
    }

    if (status === 'converted_to_loan') {
        return 'border-[var(--act-bd)] bg-[var(--act-bg)] text-[var(--act-ink)]';
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
