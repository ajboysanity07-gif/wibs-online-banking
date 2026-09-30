export const REVIEW_TABS = [
    { id: 'overview', label: 'Overview' },
    { id: 'applicant', label: 'Applicant' },
    { id: 'co-makers', label: 'Co-makers' },
    { id: 'documents', label: 'Documents' },
    { id: 'activity', label: 'Activity' },
] as const;

export type ReviewTabId = (typeof REVIEW_TABS)[number]['id'];

/** `#documents` -> 'documents'; anything unknown falls back to Overview. */
export const parseReviewTabHash = (hash: string): ReviewTabId =>
    REVIEW_TABS.find((tab) => tab.id === hash.replace(/^#/, ''))?.id ??
    'overview';

/** Tab to move to for an arrow/home/end key, or null for other keys. */
export const nextReviewTab = (
    current: ReviewTabId,
    key: string,
): ReviewTabId | null => {
    const index = REVIEW_TABS.findIndex((tab) => tab.id === current);
    const last = REVIEW_TABS.length - 1;
    const target =
        key === 'ArrowRight'
            ? (index + 1) % REVIEW_TABS.length
            : key === 'ArrowLeft'
              ? (index - 1 + REVIEW_TABS.length) % REVIEW_TABS.length
              : key === 'Home'
                ? 0
                : key === 'End'
                  ? last
                  : null;

    return target === null ? null : REVIEW_TABS[target].id;
};
