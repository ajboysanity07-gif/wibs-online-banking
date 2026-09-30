import {
    INSURANCE_RATE_AGE_BANDS,
    calculateAgeFromBirthdate,
    resolveDefaultLoanSecurityRate,
    snapshotPercent,
    type RecommendationPreviewState,
} from '@/components/loan-request/processing-details-panel';
import { formatCurrency } from '@/lib/formatters';
import {
    INSTITUTIONAL_EMPLOYER_CATEGORY_LABELS,
    institutionalEmployerCategoryMismatch,
    resolveInstitutionalEmployerCategory,
} from '@/lib/institutional-employer-category';
import { cn } from '@/lib/utils';
import type {
    LoanRequestDetail,
    LoanRequestPersonData,
} from '@/types/loan-requests';

type Props = {
    loanRequest: LoanRequestDetail;
    applicant: LoanRequestPersonData | null;
    /** `processing` data section (loan_security_rate lives here). */
    processing: Record<string, unknown>;
    /** Computed preview mirrored from the Processing details panel. */
    preview: RecommendationPreviewState | null;
    /** "Employer category confirmed with member" condition; null when it can't be tracked. */
    categoryConfirmation?: { verified: boolean; by: string | null } | null;
};

type Rule = {
    tone: 'ok' | 'action' | 'info';
    title: string;
    description: string;
};

const toneClassName: Record<Rule['tone'], string> = {
    ok: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-200',
    action: 'bg-destructive/10 text-destructive',
    info: 'bg-sky-500/10 text-sky-700 dark:text-sky-200',
};
const toneMark: Record<Rule['tone'], string> = {
    ok: '✓',
    action: '!',
    info: 'i',
};

const num = (value: unknown): number | null =>
    value === null ||
    value === undefined ||
    `${value}`.trim() === '' ||
    Number.isNaN(Number(value))
        ? null
        : Number(value);

function buildRules({
    loanRequest,
    applicant,
    processing,
    categoryConfirmation,
}: Props): Rule[] {
    const rules: Rule[] = [];
    const requested = num(loanRequest.requested_amount);
    const recommended = num(loanRequest.recommended_amount);
    const term = num(loanRequest.recommended_term);

    if (recommended === null) {
        rules.push({
            tone: 'action',
            title: 'Recommended amount not set',
            description: 'Enter it under Processing details.',
        });
    } else if (requested !== null && recommended === requested) {
        rules.push({
            tone: 'ok',
            title: 'Amount matches request',
            description: `Recommended ${formatCurrency(recommended)} equals requested.`,
        });
    } else if (requested !== null && recommended < requested) {
        rules.push({
            tone: 'info',
            title: 'Amount reduced from request',
            description: `Recommended ${formatCurrency(recommended)} of ${formatCurrency(requested)} requested.`,
        });
    } else {
        rules.push({
            tone: 'action',
            title: 'Amount exceeds request',
            description: `Recommended ${formatCurrency(recommended)} is above the requested amount.`,
        });
    }

    // Mirrors ApprovedLoanDocumentDataBuilder: only a term under 2 months skips insurance.
    if (term === null) {
        rules.push({
            tone: 'action',
            title: 'Recommended term not set',
            description: 'Insurance applicability depends on the term.',
        });
    } else if (term >= 2) {
        rules.push({
            tone: 'ok',
            title: `Term ${term} months`,
            description: 'Two months or longer, so insurance applies.',
        });
    } else {
        rules.push({
            tone: 'info',
            title: `Term ${term} month${term === 1 ? '' : 's'}`,
            description: 'Under two months, so no insurance premium.',
        });
    }

    const age = calculateAgeFromBirthdate(applicant?.birthdate ?? null);
    const band =
        age === null
            ? null
            : (INSURANCE_RATE_AGE_BANDS.find(
                  ({ minAge, maxAge }) => age >= minAge && age <= maxAge,
              ) ?? null);

    if (age !== null && term !== null && term >= 2) {
        rules.push({
            tone: 'info',
            title: `Applicant age ${age}`,
            description: band
                ? `Falls in the ${band.minAge}-${band.maxAge} insurance band, rate ${band.rate} per PHP 1,000 per month (locked).`
                : 'Outside the senior insurance bands, so the standard rate of 1 applies (locked).',
        });
    }

    const declared = applicant?.institutional_employer_category ?? null;
    const detected = resolveInstitutionalEmployerCategory(
        applicant?.employer_business_name,
        applicant?.employment_type,
        applicant?.nature_of_business,
    );

    if (
        institutionalEmployerCategoryMismatch(
            declared,
            applicant?.employer_business_name,
            applicant?.employment_type,
            applicant?.nature_of_business,
        )
    ) {
        rules.push({
            tone: 'action',
            title: 'Employer category may be outdated',
            description:
                "Declared category doesn't match the employer details; deduction documents depend on it.",
        });
    } else if (declared) {
        const label =
            INSTITUTIONAL_EMPLOYER_CATEGORY_LABELS[declared] ?? declared;
        const unconfirmed = categoryConfirmation?.verified === false;

        rules.push({
            tone: unconfirmed ? 'action' : 'ok',
            title: 'Employer category',
            description: unconfirmed
                ? `${label}, consistent with employer details but not yet confirmed with the member.`
                : categoryConfirmation
                  ? `${label}, confirmed with the member${categoryConfirmation.by ? ` by ${categoryConfirmation.by}` : ''}.`
                  : `${label}, consistent with employer details.`,
        });
    } else if (detected) {
        rules.push({
            tone: 'action',
            title: 'Employer category not set',
            description: `Employer details look like ${INSTITUTIONAL_EMPLOYER_CATEGORY_LABELS[detected]}; deduction documents depend on it.`,
        });
    }

    // Mirrors WIBS desktop: Other Loan (typecode 01) 2%, every other type 5%.
    const securityRate =
        num(processing.loan_security_rate) ??
        resolveDefaultLoanSecurityRate(loanRequest.typecode);

    rules.push({
        tone: 'info',
        title: `Loan security ${snapshotPercent(securityRate)}`,
        description:
            loanRequest.typecode === '01'
                ? 'Other Loan rate; savings rate follows.'
                : 'Standard rate for this loan type; savings rate follows.',
    });

    return rules;
}

export function LoanRequestRecommendationSummary(props: Props) {
    const { loanRequest, preview } = props;
    const requested = num(loanRequest.requested_amount);
    const recommended = num(loanRequest.recommended_amount);
    const net = preview?.net_proceeds_raw ?? null;
    const deductions = preview?.deductions_total_raw ?? null;

    return (
        <div className="grid grid-cols-[repeat(auto-fit,minmax(min(300px,100%),1fr))] items-stretch gap-4">
            <div className="flex flex-col gap-2.5 rounded-xl bg-primary p-5 text-primary-foreground shadow-card">
                <p className="text-xs font-semibold tracking-wider uppercase">
                    Recommended net proceeds
                </p>
                <p className="text-[28px] font-bold tracking-tight tabular-nums sm:text-4xl">
                    {net === null ? '—' : formatCurrency(net)}
                </p>
                <div className="text-sm leading-relaxed">
                    <p>
                        Requested {formatCurrency(requested)} ·{' '}
                        {recommended === null
                            ? 'no recommendation yet'
                            : recommended === requested
                              ? 'recommended the same'
                              : `recommended ${formatCurrency(recommended)}`}
                    </p>
                    <p>
                        {loanRequest.recommended_term ?? '—'} months ·{' '}
                        {snapshotPercent(loanRequest.recommended_interest_rate)}{' '}
                        / mo ·{' '}
                        {loanRequest.recommended_payment_frequency ?? '—'}
                    </p>
                    <p>
                        Deductions{' '}
                        {deductions === null ? '—' : formatCurrency(deductions)}
                    </p>
                </div>
            </div>
            <div className="rounded-xl border border-border bg-card px-5 py-4 shadow-card">
                <h2 className="mb-1.5 text-[15px] font-semibold">
                    Why this recommendation
                </h2>
                <ul>
                    {buildRules(props).map((rule) => (
                        <li
                            key={rule.title}
                            className="flex items-start gap-2.5 border-t border-border py-2 text-[13px]"
                        >
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'grid size-5 shrink-0 place-items-center rounded-full text-[11px] font-bold',
                                    toneClassName[rule.tone],
                                )}
                            >
                                {toneMark[rule.tone]}
                            </span>
                            <span className="leading-snug">
                                <b>{rule.title}</b>{' '}
                                <span className="text-muted-foreground">
                                    {rule.description}
                                </span>
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
