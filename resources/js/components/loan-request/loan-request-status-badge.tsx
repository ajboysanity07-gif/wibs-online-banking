import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { LoanRequestStatusValue } from '@/types/loan-requests';

type Props = {
    status?: LoanRequestStatusValue | null;
    className?: string;
};

const normalizeStatus = (
    status?: LoanRequestStatusValue | null,
): LoanRequestStatusValue | null => {
    if (!status) {
        return null;
    }

    if (status === 'submitted') {
        return 'under_review';
    }

    return status;
};

const statusLabel = (status?: LoanRequestStatusValue | null): string => {
    if (status === 'draft') {
        return 'Draft';
    }

    if (status === 'pending_co_maker_signatures') {
        return 'Pending Co-maker Signatures';
    }

    if (status === 'under_review') {
        return 'Under review';
    }

    if (status === 'approved') {
        return 'Approved';
    }

    if (status === 'declined') {
        return 'Declined';
    }

    if (status === 'cancelled') {
        return 'Cancelled';
    }

    return 'Unknown';
};

const statusVariant = (status?: LoanRequestStatusValue | null) => {
    if (status === 'approved') {
        return 'default';
    }

    if (status === 'declined') {
        return 'destructive';
    }

    if (status === 'under_review') {
        return 'secondary';
    }

    return 'outline';
};

const statusClassName = (status?: LoanRequestStatusValue | null): string => {
    if (status === 'pending_co_maker_signatures') {
        return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-200';
    }

    return '';
};

export function LoanRequestStatusBadge({ status, className }: Props) {
    const resolvedStatus = normalizeStatus(status);

    return (
        <Badge
            variant={statusVariant(resolvedStatus)}
            className={cn(statusClassName(resolvedStatus), className)}
        >
            {statusLabel(resolvedStatus)}
        </Badge>
    );
}
