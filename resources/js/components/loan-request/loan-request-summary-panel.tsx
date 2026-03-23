import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { formatCurrency } from '@/lib/formatters';
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

type SummaryRowProps = {
    label: string;
    value: string;
};

const displayValue = (value?: string | null): string =>
    value && value.trim() !== '' ? value : '--';

const displayName = (person: LoanRequestPersonFormData): string => {
    const fullName = [
        person.first_name,
        person.middle_name,
        person.last_name,
    ]
        .map((value) => value.trim())
        .filter(Boolean)
        .join(' ');

    return fullName !== '' ? fullName : '--';
};

const SummaryRow = ({ label, value }: SummaryRowProps) => (
    <div className="flex items-start justify-between gap-3">
        <span className="text-xs text-muted-foreground">{label}</span>
        <span className="text-right text-sm font-medium break-words">
            {value}
        </span>
    </div>
);

export function LoanRequestSummaryPanel({
    data,
    loanTypes,
    member,
    draft,
    draftUpdatedAt,
}: Props) {
    const loanTypeLabel =
        loanTypes.find((type) => type.typecode === data.typecode)?.label ??
        data.typecode;
    const requestedAmount =
        data.requested_amount.trim() !== ''
            ? formatCurrency(Number(data.requested_amount))
            : '--';

    return (
        <div className="space-y-4 lg:sticky lg:top-28">
            <Card className="border-border/30 bg-card/50">
                <CardHeader className="space-y-3">
                    <div className="flex items-center justify-between gap-2">
                        <CardTitle className="text-base">
                            Application summary
                        </CardTitle>
                        {draft ? (
                            <LoanRequestStatusBadge status={draft.status} />
                        ) : (
                            <Badge variant="secondary">New</Badge>
                        )}
                    </div>
                    <CardDescription>
                        Keep your details in sync before submitting.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4 text-sm">
                    <div className="rounded-lg border border-border/30 bg-muted/15 p-3">
                        <p className="text-xs uppercase text-muted-foreground">
                            Member
                        </p>
                        <p className="mt-2 font-medium">{member.name}</p>
                        <p className="text-xs text-muted-foreground">
                            Account No: {member.acctno ?? '--'}
                        </p>
                    </div>

                    <div className="space-y-2">
                        <SummaryRow
                            label="Loan type"
                            value={displayValue(loanTypeLabel)}
                        />
                        <SummaryRow
                            label="Requested amount"
                            value={requestedAmount}
                        />
                        <SummaryRow
                            label="Requested term"
                            value={
                                data.requested_term.trim() !== ''
                                    ? `${data.requested_term} months`
                                    : '--'
                            }
                        />
                        <SummaryRow
                            label="Availment status"
                            value={displayValue(data.availment_status)}
                        />
                        <SummaryRow
                            label="Loan purpose"
                            value={displayValue(data.loan_purpose)}
                        />
                    </div>

                    <Separator />

                    <div className="space-y-2">
                        <SummaryRow
                            label="Applicant"
                            value={displayName(data.applicant)}
                        />
                        <SummaryRow
                            label="Co-maker 1"
                            value={displayName(data.co_maker_1)}
                        />
                        <SummaryRow
                            label="Co-maker 2"
                            value={displayName(data.co_maker_2)}
                        />
                    </div>

                    {draftUpdatedAt ? (
                        <>
                            <Separator />
                            <p className="text-xs text-muted-foreground">
                                Last saved {draftUpdatedAt}
                            </p>
                        </>
                    ) : null}
                </CardContent>
            </Card>

            <Card className="border-border/30 bg-card/40">
                <CardHeader>
                    <CardTitle className="text-base">
                        Tips for faster approval
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-2 text-sm text-muted-foreground">
                    <p>Double-check your employment and income details.</p>
                    <p>
                        Save a draft if you need to gather information from
                        co-makers.
                    </p>
                </CardContent>
            </Card>
        </div>
    );
}
