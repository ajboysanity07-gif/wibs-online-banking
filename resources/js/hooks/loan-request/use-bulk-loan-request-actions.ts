import { useState } from 'react';
import { adminApi } from '@/lib/api/admin';
import type { LoanRequestBulkActionResult } from '@/types/loan-requests';

export function useBulkLoanRequestActions() {
    const [isSubmitting, setIsSubmitting] = useState(false);

    const bulkClaim = async (
        loanRequestIds: number[],
    ): Promise<LoanRequestBulkActionResult | null> => {
        setIsSubmitting(true);

        try {
            return await adminApi.bulkClaimLoanRequests(loanRequestIds);
        } finally {
            setIsSubmitting(false);
        }
    };

    const bulkCancel = async (
        loanRequestIds: number[],
        cancellationReason: string,
    ): Promise<LoanRequestBulkActionResult | null> => {
        setIsSubmitting(true);

        try {
            return await adminApi.bulkCancelLoanRequests(
                loanRequestIds,
                cancellationReason,
            );
        } finally {
            setIsSubmitting(false);
        }
    };

    return { isSubmitting, bulkClaim, bulkCancel };
}
