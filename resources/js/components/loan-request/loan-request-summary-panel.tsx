import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import {
    OTHER_LOAN_TYPECODE,
    resolveLoanTypeAbbreviation,
} from '@/components/loan-request/loan-request-steps';
import { Badge } from '@/components/ui/badge';
import { formatCurrency, formatDisplayText } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type {
    LoanRequestDraft,
    LoanRequestFormData,
    LoanRequestMemberSummary,
    LoanRequestPersonFormData,
    LoanTypeOption,
} from '@/types/loan-requests';

type Props = {
    data: LoanRequestFormData;
    loanTypes: LoanTypeOption[];
    member: LoanRequestMemberSummary;
    draft: LoanRequestDraft | null;
    draftUpdatedAt: string | null;
};

const displayValue = (value?: string | null): string =>
    value && value.trim() !== '' ? value : '--';

const displayText = (value?: string | null): string => {
    const normalized = formatDisplayText(value);

    return normalized !== '' ? normalized : '--';
};

const displayName = (person: LoanRequestPersonFormData): string => {
    const fullName = [person.first_name, person.middle_name, person.last_name]
        .map((value) => formatDisplayText(value))
        .map((value) => value.trim())
        .filter((value) => value !== '')
        .join(' ');

    return fullName !== '' ? fullName : '--';
};

const buildSummary = ({ data, loanTypes, member }: Props) => {
    const loanTypeLabel =
        loanTypes.find((type) => type.typecode === data.typecode)?.label ??
        data.typecode;
    const loanTypeAbbreviation = resolveLoanTypeAbbreviation(
        loanTypeLabel,
        data.kind_of_loan,
    );
    const requestedAmount =
        data.requested_amount.trim() !== ''
            ? formatCurrency(Number(data.requested_amount))
            : '--';
    const requestedTerm =
        data.requested_term.trim() !== ''
            ? `${data.requested_term} months`
            : '--';

    const rows: { label: string; value: string }[] = [
        { label: 'Member', value: displayText(member.name) },
        {
            label: 'Loan type',
            value: loanTypeAbbreviation
                ? `${displayText(loanTypeLabel)} (${loanTypeAbbreviation})`
                : displayText(loanTypeLabel),
        },
        { label: 'Requested amount', value: requestedAmount },
        { label: 'Requested term', value: requestedTerm },
        {
            label: 'Availment status',
            value: displayValue(data.availment_status),
        },
        ...(data.typecode === OTHER_LOAN_TYPECODE
            ? [
                  {
                      label: 'Loan name',
                      value: displayText(data.other_loan_type_name),
                  },
              ]
            : []),
        { label: 'Loan purpose', value: displayText(data.loan_purpose) },
        { label: 'Applicant', value: displayName(data.applicant) },
        { label: 'Co-maker 1', value: displayName(data.co_maker_1) },
        { label: 'Co-maker 2', value: displayName(data.co_maker_2) },
    ];

    return {
        rows,
        headline:
            requestedAmount === '--'
                ? 'No amount entered yet'
                : `${requestedAmount} · ${requestedTerm}`,
    };
};

const SummaryRows = ({
    rows,
}: {
    rows: { label: string; value: string }[];
}) => (
    <dl className="divide-y divide-border">
        {rows.map((row) => (
            <div
                key={row.label}
                className="flex items-start justify-between gap-4 py-2.5 text-sm"
            >
                <dt className="text-muted-foreground">{row.label}</dt>
                <dd className="text-right font-semibold break-words">
                    {row.value}
                </dd>
            </div>
        ))}
    </dl>
);

/** >= 900px: right-hand column with the tips box. */
export function LoanRequestSummaryPanel(props: Props) {
    const { draft, draftUpdatedAt } = props;
    const { rows } = buildSummary(props);

    return (
        <div className="space-y-4 min-[900px]:sticky min-[900px]:top-20">
            <section className="rounded-xl border border-border bg-card p-5 shadow-card">
                <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-base font-semibold">
                        Application summary
                    </h2>
                    {draft ? (
                        <LoanRequestStatusBadge status={draft.status} />
                    ) : (
                        <Badge variant="secondary">New</Badge>
                    )}
                </div>
                <SummaryRows rows={rows} />
                {draftUpdatedAt ? (
                    <p className="border-t border-border pt-3 text-xs text-muted-foreground">
                        Last saved {draftUpdatedAt}
                    </p>
                ) : null}
            </section>

            <section className="rounded-xl bg-secondary p-5 text-secondary-foreground">
                <h2 className="text-sm font-semibold">
                    Tips for faster approval
                </h2>
                <ul className="mt-2 space-y-2 text-sm">
                    <li>Double-check your employment and income details.</li>
                    <li>
                        Signatures will be collected physically upon loan
                        release.
                    </li>
                </ul>
            </section>
        </div>
    );
}

/** < 900px: tappable bar above the form that expands the summary in place. */
export function LoanRequestSummaryBar(props: Props) {
    const [open, setOpen] = useState(false);
    const { rows, headline } = buildSummary(props);

    return (
        <section className="rounded-xl border border-border bg-card shadow-card">
            <button
                type="button"
                aria-expanded={open}
                onClick={() => setOpen((current) => !current)}
                className="flex min-h-14 w-full items-center gap-3 px-4 py-3 text-left"
            >
                <span className="min-w-0 flex-1">
                    <span className="block text-xs text-muted-foreground">
                        Application summary
                    </span>
                    <span className="block truncate text-sm font-semibold">
                        {headline}
                    </span>
                </span>
                <ChevronDown
                    aria-hidden="true"
                    className={cn(
                        'size-5 shrink-0 text-muted-foreground transition-transform duration-200 motion-reduce:transition-none',
                        open && 'rotate-180',
                    )}
                />
            </button>
            <div
                className={cn(
                    'grid transition-[grid-template-rows] duration-200 ease-out motion-reduce:transition-none',
                    open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]',
                )}
                inert={!open}
            >
                <div className="overflow-hidden">
                    <div className="border-t border-border px-4 pb-2">
                        <SummaryRows rows={rows} />
                    </div>
                </div>
            </div>
        </section>
    );
}
