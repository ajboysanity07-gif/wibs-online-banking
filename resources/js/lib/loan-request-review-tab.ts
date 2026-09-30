import type {
    LoanRequestDataSectionDefinitions,
    LoanRequestDocumentChecklistItem,
} from '@/types/loan-requests';

export const REVIEW_TABS = [
    { id: 'summary', label: 'Summary' },
    { id: 'applicant', label: 'Applicant' },
    { id: 'terms', label: 'Terms & charges' },
    { id: 'signatories', label: 'Signatories & insurance' },
    { id: 'docs', label: 'Documents' },
    { id: 'history', label: 'History' },
] as const;

export type ReviewTabId = (typeof REVIEW_TABS)[number]['id'];

// Hashes from the previous tab row, so old links and history entries land
// somewhere sensible. Anything else falls back to Summary.
const LEGACY_TABS: Record<string, ReviewTabId> = {
    overview: 'summary',
    'co-makers': 'applicant',
    documents: 'docs',
    activity: 'history',
};

/** `#docs` -> 'docs', `#documents` -> 'docs'; unknown falls back to Summary. */
export const parseReviewTabHash = (hash: string): ReviewTabId => {
    const id = hash.replace(/^#/, '');

    return (
        REVIEW_TABS.find((tab) => tab.id === id)?.id ??
        LEGACY_TABS[id] ??
        'summary'
    );
};

/** Tab to move to for an arrow/home/end key, or null for other keys. */
export const nextReviewTab = (
    current: ReviewTabId,
    key: string,
): ReviewTabId | null => {
    const index = REVIEW_TABS.findIndex((tab) => tab.id === current);
    const last = REVIEW_TABS.length - 1;
    // Vertical list on desktop, chip row below lg: both arrow pairs work.
    const target =
        key === 'ArrowRight' || key === 'ArrowDown'
            ? (index + 1) % REVIEW_TABS.length
            : key === 'ArrowLeft' || key === 'ArrowUp'
              ? (index - 1 + REVIEW_TABS.length) % REVIEW_TABS.length
              : key === 'Home'
                ? 0
                : key === 'End'
                  ? last
                  : null;

    return target === null ? null : REVIEW_TABS[target].id;
};

const SIGNATORY_FIELDS = [
    'witness_one_name',
    'witness_two_name',
    'authority_to_deduct_officer_1_name',
    'authority_to_deduct_officer_1_title',
    'authority_to_deduct_officer_2_name',
    'authority_to_deduct_officer_2_title',
    'barangay_official_name',
    'barangay_official_title',
];

/**
 * True when an applicable document is blocked on a signatory field. The
 * backend reports missing required fields as "{label} is required."
 */
export const hasMissingSignatory = (
    documents: LoanRequestDocumentChecklistItem[],
    definitions: LoanRequestDataSectionDefinitions,
): boolean => {
    const fields = definitions.processing?.fields ?? {};
    const messages = SIGNATORY_FIELDS.flatMap((key) =>
        fields[key] ? [`${fields[key].label} is required.`] : [],
    );

    return documents.some(
        (document) =>
            document.is_applicable &&
            document.blockers.some((blocker) => messages.includes(blocker)),
    );
};

/** Applicable documents, and how many of them are generated and current. */
export const documentPackageCounts = (
    documents: LoanRequestDocumentChecklistItem[],
): { current: number; total: number } => {
    const applicable = documents.filter((document) => document.is_applicable);

    return {
        current: applicable.filter(
            (document) => document.status === 'generated_current',
        ).length,
        total: applicable.length,
    };
};
