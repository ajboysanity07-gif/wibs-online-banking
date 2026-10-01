// Section model for the member loan application (calculator -> overview ->
// five sections -> review). Pure functions only, so tests/js can import it.

export const APPLICATION_SECTIONS = [
    'loan',
    'about',
    'co',
    'disb',
    'decl',
] as const;

export type ApplicationSection = (typeof APPLICATION_SECTIONS)[number];

export const SECTION_LABELS: Record<ApplicationSection, string> = {
    loan: 'Loan details',
    about: 'About you',
    co: 'Co-makers',
    disb: 'Disbursement & repayment',
    decl: 'Declarations',
};

export type SectionStatus =
    | 'not_started'
    | 'in_progress'
    | 'done'
    | 'needs_attention';

export const SECTION_STATUS_LABELS: Record<SectionStatus, string> = {
    not_started: 'Not started',
    in_progress: 'In progress',
    done: 'Done',
    needs_attention: 'Needs attention',
};

export type SectionFacts = {
    complete: boolean;
    started: boolean;
    hasErrors: boolean;
};

export function deriveSectionStatus({
    complete,
    started,
    hasErrors,
}: SectionFacts): SectionStatus {
    if (hasErrors) {
        return 'needs_attention';
    }

    if (complete) {
        return 'done';
    }

    return started ? 'in_progress' : 'not_started';
}

/** Which section a server validation error key belongs to. */
export function sectionForErrorKey(key: string): ApplicationSection | null {
    if (
        /^(typecode|requested_amount|requested_term|loan_purpose|other_loan_type_name|availment_status|requested_payment_frequency|kind_of_loan)$/.test(
            key,
        )
    ) {
        return 'loan';
    }

    if (key.startsWith('applicant.') || key === 'loan_prerequisites') {
        return 'about';
    }

    if (key.startsWith('co_maker_1.') || key.startsWith('co_maker_2.')) {
        return 'co';
    }

    if (key.startsWith('banking.')) {
        return 'disb';
    }

    if (key.startsWith('declarations.')) {
        return 'decl';
    }

    return null;
}

/**
 * Next section still not done, searching forward from `after` and wrapping
 * around (so skipping ahead never strands an earlier section). Null when
 * every section is done.
 */
export function nextIncompleteSection(
    statuses: Record<ApplicationSection, SectionStatus>,
    after: ApplicationSection | null = null,
): ApplicationSection | null {
    const start = after === null ? 0 : APPLICATION_SECTIONS.indexOf(after) + 1;
    const ordered = [
        ...APPLICATION_SECTIONS.slice(start),
        ...APPLICATION_SECTIONS.slice(0, start),
    ];

    return ordered.find((section) => statuses[section] !== 'done') ?? null;
}

/**
 * Guided-pass progress: five equal segments, earlier ones full, the current
 * one filled by how far through its mobile substeps the member is (a section
 * without substeps counts as 1 of 1).
 */
export function guidedProgress(
    section: ApplicationSection,
    substep = 1,
    substeps = 1,
): { step: number; total: number; fills: number[]; value: number } {
    const index = APPLICATION_SECTIONS.indexOf(section);
    const current = Math.min(1, Math.max(0, substep / Math.max(1, substeps)));
    const fills = APPLICATION_SECTIONS.map((_, i) =>
        i < index ? 1 : i === index ? current : 0,
    );

    return {
        step: index + 1,
        total: APPLICATION_SECTIONS.length,
        fills,
        value: Math.round((index + current) * 100) / 100,
    };
}
