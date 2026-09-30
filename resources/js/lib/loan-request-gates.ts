export type GateCount = { done: number; total: number };

export type RecommendGates = {
    /** null when conditions can't gate (table not deployed / viewer can't sign off). */
    conditions: GateCount | null;
    exceptions: GateCount;
    documents: GateCount;
    /** Number of gates that are not yet clear. */
    openCount: number;
    /** Set only when the forward action must be disabled; null when clear or not enforced. */
    blockedReason: string | null;
};

const plural = (count: number, word: string) =>
    `${count} ${word}${count === 1 ? '' : 's'}`;

/**
 * Gates before hand-off. They only ever ADD a disabled state to
 * "Recommend approval"; the server keeps its own validation (it enforces
 * documents and pending member action, not conditions or exceptions).
 *
 * Exceptions "done" counts listed blocking/warning rows that are not blocking,
 * so warnings never gate.
 */
export function buildRecommendGates(input: {
    conditions: { done: number; total: number } | null;
    blockingCount: number;
    exceptionRowCount: number;
    documents: { is_applicable: boolean; status: string }[];
    enforced: boolean;
}): RecommendGates {
    const applicable = input.documents.filter((d) => d.is_applicable);
    const documents = {
        done: applicable.filter((d) => d.status === 'generated_current').length,
        total: applicable.length,
    };
    const exceptions = {
        done: Math.max(input.exceptionRowCount - input.blockingCount, 0),
        total: Math.max(input.exceptionRowCount, input.blockingCount),
    };
    const openConditions = input.conditions
        ? input.conditions.total - input.conditions.done
        : 0;
    const documentsOpen = documents.done !== documents.total;
    const openCount =
        (openConditions > 0 ? 1 : 0) +
        (input.blockingCount > 0 ? 1 : 0) +
        (documentsOpen ? 1 : 0);

    const parts = [
        openConditions > 0 ? plural(openConditions, 'condition') : null,
        input.blockingCount > 0
            ? plural(input.blockingCount, 'exception')
            : null,
        documentsOpen
            ? `documents not all current (${documents.done}/${documents.total})`
            : null,
    ].filter((part): part is string => part !== null);

    return {
        conditions: input.conditions,
        exceptions,
        documents,
        openCount,
        blockedReason:
            input.enforced && parts.length > 0
                ? `${parts.join(' - ')} to clear before you can recommend approval.`
                : null,
    };
}

export type TaskExceptionAction =
    | 'confirm-category'
    | 'fix-signatories'
    | 'open-documents';

export type TaskException = {
    key: string;
    title: string;
    description: string;
    /** Resolves only when the underlying data does; never dismissed by hand. */
    open: boolean;
    action: { key: TaskExceptionAction; label: string } | null;
};

/**
 * Exceptions for the task panel: every blocking attention row, plus the
 * category check shown as resolved once the member confirmed it.
 */
export function buildTaskExceptions(input: {
    rows: {
        key: string;
        tone: string;
        title: string;
        description: string;
        action?: { key: string };
    }[];
    categoryConfirmedAfterMismatch: boolean;
    /** Whether a `blockers-{document}` row is about a missing signatory. */
    isSignatoryBlocker: (rowKey: string) => boolean;
}): TaskException[] {
    const open: TaskException[] = input.rows
        .filter((row) => row.tone === 'blocking')
        .map((row) => ({
            key: row.key,
            title: row.title,
            description: row.description,
            open: true,
            action:
                row.action?.key === 'edit-category'
                    ? { key: 'confirm-category', label: 'Confirm' }
                    : input.isSignatoryBlocker(row.key)
                      ? { key: 'fix-signatories', label: 'Fix' }
                      : { key: 'open-documents', label: 'Fix' },
        }));

    return input.categoryConfirmedAfterMismatch
        ? [
              {
                  key: 'category-mismatch',
                  title: 'Employer category confirmed',
                  description: 'Checked with the member.',
                  open: false,
                  action: null,
              },
              ...open,
          ]
        : open;
}

/** Tasks card totals and the "what is left" phrase ("2 conditions, the document package"). */
export function summarizeTasks(input: {
    conditions: GateCount | null;
    exceptions: TaskException[];
    documents: GateCount;
}): { done: number; total: number; left: string | null } {
    const conditions = input.conditions ?? { done: 0, total: 0 };
    const openExceptions = input.exceptions.filter((e) => e.open).length;
    const openConditions = conditions.total - conditions.done;
    const documentsDone = input.documents.done === input.documents.total;
    const left = [
        openConditions > 0 ? plural(openConditions, 'condition') : null,
        openExceptions > 0 ? plural(openExceptions, 'exception') : null,
        documentsDone ? null : 'the document package',
    ].filter((part): part is string => part !== null);

    return {
        done:
            conditions.done +
            (input.exceptions.length - openExceptions) +
            (documentsDone ? 1 : 0),
        total: conditions.total + input.exceptions.length + 1,
        left: left.length > 0 ? left.join(', ') : null,
    };
}
