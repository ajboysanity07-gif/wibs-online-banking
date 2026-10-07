import { FileSpreadsheet, FileText, ScrollText } from 'lucide-react';
import { useState } from 'react';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Button } from '@/components/ui/button';
import type { MemberLoan, MemberLoanScheduleEntry } from '@/types/admin';
import {
    formatMonthLabel,
    formatRateLabel,
    formatShortDate,
} from './loan-presentation';
import { downloadTextFile } from './payment-presentation';
import { SoaDialog } from './soa-dialog';

export type MemberLoanDocuments = {
    soaMonths: Array<{ month: string; transactions: number }>;
    certificateEligible: boolean;
};

export type LoanManagerSummary = {
    name: string | null;
    role: string | null;
    signatureUrl?: string | null;
};

type DocumentsTabProps = {
    loan: MemberLoan;
    documents: MemberLoanDocuments;
    schedule: MemberLoanScheduleEntry[];
    memberName: string;
    memberSignatureUrl?: string | null;
    loanManager?: LoanManagerSummary | null;
    accountNo: string | null;
};

const monthLabel = (month: string): string => formatMonthLabel(month);

function DocumentRow({
    icon,
    title,
    meta,
    action,
}: {
    icon: React.ReactNode;
    title: string;
    meta: string;
    action: React.ReactNode;
}) {
    return (
        <li className="flex flex-wrap items-center justify-between gap-3 border-b border-border py-4 last:border-b-0">
            <div className="flex min-w-0 items-center gap-3">
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] border border-border bg-muted/60 text-primary">
                    {icon}
                </span>
                <div className="min-w-0">
                    <p className="text-[14.5px] font-bold">{title}</p>
                    <p className="text-[12.5px] text-muted-foreground">
                        {meta}
                    </p>
                </div>
            </div>
            {action}
        </li>
    );
}

export function DocumentsTab({
    loan,
    documents,
    schedule,
    memberName,
    loanManager = null,
    accountNo,
}: DocumentsTabProps) {
    const [activeMonth, setActiveMonth] = useState<string | null>(null);

    const managerName = loanManager?.name?.trim() || 'Name on file';

    const scheduleDates = schedule
        .map((entry) => entry.date_pay ?? '')
        .filter((date) => date !== '')
        .sort();
    const maturesLabel =
        scheduleDates.length > 0
            ? formatShortDate(scheduleDates[scheduleDates.length - 1])
            : null;

    const scheduleMetaParts = [`${schedule.length} installments`];

    if (loan.monthlyRate !== null && loan.monthlyRate !== undefined) {
        scheduleMetaParts.push(formatRateLabel(loan.monthlyRate));
    }

    if (maturesLabel) {
        scheduleMetaParts.push(`matures ${maturesLabel}`);
    }

    const termLabel =
        schedule.length > 0
            ? `${schedule.length} months`
            : loan.termMonths !== null &&
                loan.termMonths !== undefined &&
                Number.isInteger(loan.termMonths) &&
                loan.termMonths >= 1 &&
                loan.termMonths <= 360
              ? `${loan.termMonths} months`
              : '—';

    const scheduleCsv = (): string => {
        const header = [
            'Installment date',
            'Amount',
            'Interest',
            'Balance',
        ].join(',');

        const rows = schedule.map((entry) =>
            [
                entry.date_pay ?? '',
                (entry.amortization ?? 0).toFixed(2),
                (entry.interest ?? 0).toFixed(2),
                (entry.balance ?? 0).toFixed(2),
            ].join(','),
        );

        return [header, ...rows].join('\n');
    };

    const certificateText = (): string => {
        const lines = [
            'MRDINC Portal — Certificate of Full Payment',
            '',
            `This certifies that ${memberName}, holder of account ${accountNo ?? '--'}, has fully paid loan ${String(loan.lnnumber ?? '--')}.`,
            '',
            `Loan type: ${loan.lntype ?? '--'}`,
            `Original principal: ₱${(loan.principal ?? 0).toFixed(2)}`,
            '',
            `Member signature        : ${memberName}`,
            `Loan Manager signature  : ${managerName}`,
        ];

        return lines.join('\n');
    };

    return (
        <SurfaceCard variant="default" padding="md" className="space-y-4">
            <SectionHeader
                title="Documents"
                description="Statements and certificates for this loan."
                titleClassName="text-base font-semibold"
            />

            <p className="text-[13px] text-muted-foreground">
                Payment receipts are not listed here — open any payment in
                Payment history to download its receipt.
            </p>

            <ul className="flex flex-col">
                {documents.soaMonths.length === 0 ? (
                    <li className="border-b border-border py-4 text-[13.5px] text-muted-foreground">
                        No statements available yet.
                    </li>
                ) : (
                    documents.soaMonths.map(({ month, transactions }) => (
                        <DocumentRow
                            key={month}
                            icon={
                                <ScrollText
                                    aria-hidden="true"
                                    className="h-5 w-5"
                                />
                            }
                            title={`Statement of Account — ${monthLabel(month)}`}
                            meta={`${transactions} transaction(s) · ${loan.lnnumber ?? '--'}`}
                            action={
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setActiveMonth(month)}
                                >
                                    Open
                                </Button>
                            }
                        />
                    ))
                )}

                <DocumentRow
                    icon={
                        <FileSpreadsheet
                            aria-hidden="true"
                            className="h-5 w-5"
                        />
                    }
                    title="Amortization schedule"
                    meta={scheduleMetaParts.join(' · ')}
                    action={
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                downloadTextFile(
                                    `AMS_${String(loan.lnnumber ?? 'loan')}.csv`,
                                    scheduleCsv(),
                                )
                            }
                        >
                            Download
                        </Button>
                    }
                />

                {documents.certificateEligible ? (
                    <DocumentRow
                        icon={
                            <FileText aria-hidden="true" className="h-5 w-5" />
                        }
                        title="Certificate of Full Payment"
                        meta={`Loan fully settled · ${String(loan.lnnumber ?? '--')}`}
                        action={
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    downloadTextFile(
                                        `CFP_${String(loan.lnnumber ?? 'loan')}.txt`,
                                        certificateText(),
                                        'text/plain;charset=utf-8;',
                                    )
                                }
                            >
                                Download
                            </Button>
                        }
                    />
                ) : null}
            </ul>

            <SoaDialog
                key={activeMonth ?? 'closed'}
                loan={loan}
                memberName={memberName}
                rateLabel={formatRateLabel(loan.monthlyRate)}
                termLabel={termLabel}
                termCount={schedule.length > 0 ? schedule.length : null}
                accountNo={accountNo}
                month={activeMonth}
                onClose={() => setActiveMonth(null)}
            />
        </SurfaceCard>
    );
}
