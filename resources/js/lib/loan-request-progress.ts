import type { LoanRequestStatusValue } from '@/types/loan-requests';

export const PROGRESS_STEPS = [
    'Draft',
    'Submitted',
    'In processing',
    'Manager approval',
    'Release',
] as const;

/** One line per step; the last entry covers `released` (every step done). */
export const STAGE_GUIDANCE = [
    'the member is still completing the application; there is nothing to process yet.',
    'claim the request or wait for assignment, then start processing.',
    'verify the conditions, clear exceptions, generate the document package, then recommend approval to the Loan Manager.',
    'the Loan Manager reviews the recommendation and approves or declines; the member then accepts the terms.',
    'encode the loan in WIBS, schedule the release, then confirm it.',
    'the loan has been released; no further processing is needed.',
] as const;

export type LoanRequestProgress = {
    /** Index of the current step; PROGRESS_STEPS.length = every step done, -1 = unknown. */
    step: number;
    /** Off the happy path (revision, rejected, ...): keep the step, show a status badge. */
    held: boolean;
};

// Exhaustive on purpose: adding a LoanRequestStatusValue is a type error here.
const PROGRESS: Record<
    Exclude<LoanRequestStatusValue, 'cancelled'>,
    LoanRequestProgress
> = {
    draft: { step: 0, held: false },
    // Legacy pre-submission state.
    pending_co_maker_signatures: { step: 0, held: true },
    // Legacy: submitted but not yet in the processing queue.
    submitted: { step: 1, held: false },
    pending_review: { step: 1, held: false },
    under_review: { step: 2, held: false },
    needs_revision: { step: 2, held: true },
    awaiting_member_information: { step: 2, held: true },
    rejected: { step: 2, held: true },
    recommended_for_approval: { step: 3, held: false },
    awaiting_member_acceptance: { step: 3, held: true },
    declined: { step: 3, held: true },
    member_declined_terms: { step: 3, held: true },
    approved: { step: 4, held: false },
    converted_to_loan: { step: 4, held: false },
    for_wibs_encoding: { step: 4, held: false },
    wibs_loan_created: { step: 4, held: false },
    release_scheduled: { step: 4, held: false },
    released: { step: PROGRESS_STEPS.length, held: false },
};

/**
 * Maps a workflow status onto the 5-step progress line. `cancelled` can happen
 * at any step, so it keeps the step of `previousStatus` (last audit transition
 * into cancelled); without one no step is highlighted.
 */
export function resolveLoanRequestProgress(
    status: LoanRequestStatusValue | null,
    previousStatus?: LoanRequestStatusValue | null,
): LoanRequestProgress {
    if (status === null) {
        return { step: -1, held: false };
    }

    if (status === 'cancelled') {
        const previous =
            previousStatus && previousStatus !== 'cancelled'
                ? PROGRESS[previousStatus]
                : null;

        return { step: previous?.step ?? -1, held: true };
    }

    return PROGRESS[status] ?? { step: -1, held: false };
}
