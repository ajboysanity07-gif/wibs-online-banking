import type {
    LoanRequestDocumentChecklistItem,
    LoanRequestWorkflowHealth,
} from '@/types/loan-requests';

export type AttentionTone = 'blocking' | 'warning' | 'info';

export type AttentionActionKey =
    | 'edit-category'
    | 'open-documents'
    | 'view-loan-details';

export type AttentionRow = {
    key: string;
    tone: AttentionTone;
    title: string;
    description: string;
    action?: { key: AttentionActionKey; label: string };
    tag?: string;
};

export type AttentionInput = {
    categoryMismatch: boolean;
    /** Whether the viewer may open the Processing details edit. */
    canEditCategory: boolean;
    documents: Pick<
        LoanRequestDocumentChecklistItem,
        | 'key'
        | 'label'
        | 'is_applicable'
        | 'status'
        | 'blockers'
        | 'failure_message'
    >[];
    health: LoanRequestWorkflowHealth;
    processingAgeTargetDays: number;
    memberAction: { message: string | null } | null;
    /** `warning_message` of the applicant's problem-loan summary, when it requires attention. */
    problemLoanMessage: string | null;
    managerStage: { title: string; description: string } | null;
};

const ORDER: Record<AttentionTone, number> = {
    blocking: 0,
    warning: 1,
    info: 2,
};

const plural = (count: number, word: string) =>
    `${count} ${word}${count === 1 ? '' : 's'}`;

/**
 * Builds the "Needs your attention" rows from data the page already has.
 * Rows are derived, never stored, so a blocking row disappears the moment the
 * underlying data is fixed. Document readiness itself is gate 3, not counted
 * here beyond documents that report blockers or failed generation.
 */
export function buildAttentionRows(input: AttentionInput): {
    rows: AttentionRow[];
    blockingCount: number;
} {
    const rows: AttentionRow[] = [];
    const applicable = input.documents.filter((d) => d.is_applicable);

    if (input.categoryMismatch) {
        rows.push({
            key: 'category-mismatch',
            tone: 'blocking',
            title: 'Institutional employer category may be outdated',
            description:
                "Declared category doesn't match the applicant's employer details. Verify with the member before generating deduction documents.",
            action: input.canEditCategory
                ? { key: 'edit-category', label: 'Confirm category' }
                : undefined,
        });
    }

    applicable.forEach((document) => {
        if (document.blockers.length > 0) {
            rows.push({
                key: `blockers-${document.key}`,
                tone: 'blocking',
                title: `${document.label}: missing information`,
                description: document.blockers.join(' · '),
                action: { key: 'open-documents', label: 'Open documents' },
            });
        } else if (document.status === 'generation_failed') {
            rows.push({
                key: `failed-${document.key}`,
                tone: 'blocking',
                title: `${document.label} failed to generate`,
                description:
                    document.failure_message ?? 'Try generating it again.',
                action: { key: 'open-documents', label: 'Open documents' },
            });
        }
    });

    if (input.problemLoanMessage !== null) {
        rows.push({
            key: 'problem-loans',
            tone: 'warning',
            title: input.problemLoanMessage,
            description:
                'Soft warning: you may approve or decline based on your assessment.',
            action: { key: 'view-loan-details', label: 'View loans' },
        });
    }

    const stale = applicable.filter((d) => d.status === 'generated_stale');

    if (stale.length > 0) {
        rows.push({
            key: 'stale-documents',
            tone: 'warning',
            title: `${plural(stale.length, 'document')} out of date`,
            description: `${stale.map((d) => d.label).join(', ')} changed since generation. Regenerate before recommending.`,
            action: { key: 'open-documents', label: 'Open documents' },
        });
    }

    const { health } = input;

    if (
        health.processing_age_days !== null &&
        health.processing_age_days >= input.processingAgeTargetDays
    ) {
        rows.push({
            key: 'processing-age',
            tone: 'warning',
            title: `Processing age ${health.processing_age_days}d`,
            description: `Past the ${input.processingAgeTargetDays}-day target.`,
        });
    }

    (
        [
            [
                health.legacy_blocker_count,
                'legacy-blockers',
                'legacy blocker',
                'Legacy data needs cleanup before this request can move forward.',
            ],
            [
                health.notification_failure_count,
                'notification-failures',
                'notification failure',
                'A member notification did not deliver. See Activity.',
            ],
            [
                health.workflow_failed_job_count,
                'failed-jobs',
                'failed workflow job',
                'A background job for this request failed.',
            ],
        ] as const
    ).forEach(([count, key, noun, description]) => {
        if (count > 0) {
            rows.push({
                key,
                tone: 'warning',
                title: plural(count, noun),
                description,
            });
        }
    });

    if (input.memberAction !== null) {
        rows.push({
            key: 'member-action',
            tone: 'info',
            title: 'Pending member action',
            description:
                input.memberAction.message ??
                'This request is waiting for a member response.',
            tag: 'Waiting on member',
        });
    }

    applicable
        .filter((d) => d.status === 'awaiting_member_confirmation')
        .forEach((document) => {
            rows.push({
                key: `awaiting-${document.key}`,
                tone: 'info',
                title: `${document.label} waiting on member`,
                description: 'The member has not confirmed this document yet.',
                tag: 'Waiting on member',
            });
        });

    if (input.managerStage !== null) {
        rows.push({
            key: 'manager-stage',
            tone: 'info',
            title: input.managerStage.title,
            description: input.managerStage.description,
        });
    }

    rows.sort((a, b) => ORDER[a.tone] - ORDER[b.tone]);

    return {
        rows,
        blockingCount: rows.filter((row) => row.tone === 'blocking').length,
    };
}
