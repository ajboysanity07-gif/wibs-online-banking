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
                ? `${parts.join(' · ')} to clear before you can recommend approval.`
                : null,
    };
}
