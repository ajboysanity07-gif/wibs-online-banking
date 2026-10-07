import { useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useBranding } from '@/hooks/use-branding';
import client from '@/lib/api/client';
import { formatCurrency } from '@/lib/formatters';
import type { MemberLoan } from '@/types/admin';
import { formatShortDate } from './loan-presentation';
import { downloadTextFile } from './payment-presentation';

export type StatementData = {
    month: string;
    periodLabel: string;
    issuedAt: string;
    openingBalance: number | null;
    closingBalance: number | null;
    totals: {
        principal: number;
        interest: number;
        payments: number;
        count: number;
    };
    memberAddress?: string | null;
    movements: Array<{
        date: string | null;
        reference: string;
        description: string;
        principal: number | null;
        interest: number | null;
        credit: number;
        balance: number | null;
    }>;
};

type SoaDialogProps = {
    loan: MemberLoan;
    memberName: string;
    rateLabel: string;
    termLabel: string;
    termCount?: number | null;
    accountNo: string | null;
    month: string | null;
    onClose: () => void;
};

const money = (value: number | null): string =>
    value === null ? '—' : formatCurrency(value);

export function SoaDialog({
    loan,
    memberName,
    rateLabel,
    termLabel,
    termCount = null,
    accountNo,
    month,
    onClose,
}: SoaDialogProps) {
    const branding = useBranding();
    const [data, setData] = useState<StatementData | null>(null);
    const [loading, setLoading] = useState(month !== null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (month === null) {
            return;
        }

        let cancelled = false;

        client
            .get<StatementData>(
                `/client/loans/${encodeURIComponent(String(loan.lnnumber ?? ''))}/statement`,
                { params: { month } },
            )
            .then((response) => {
                if (!cancelled) {
                    setData(response.data);
                    setLoading(false);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setError('Unable to load this statement of account.');
                    setLoading(false);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [month, loan.lnnumber]);

    const facts = useMemo(
        () => [
            ['Borrower', memberName],
            ['Account no', accountNo ?? '--'],
            ['Loan no', String(loan.lnnumber ?? '--')],
            ['Loan type', loan.lntype ?? '--'],
            ['Original principal', formatCurrency(loan.principal ?? 0)],
            ['Monthly due', formatCurrency(loan.monthlyDue ?? 0)],
            ['Rate', rateLabel],
            ['Term', termLabel],
        ],
        [loan, memberName, accountNo, rateLabel, termLabel],
    );

    const installmentsPaid =
        termCount !== null && termCount > 0 && data
            ? `${data.totals.count} of ${termCount}`
            : data
              ? String(data.totals.count)
              : '—';

    return (
        <Dialog
            open={month !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <p className="text-[12px] font-bold tracking-[0.06em] text-muted-foreground uppercase">
                        MRDINC Portal
                    </p>
                    <DialogTitle className="text-[17px]">
                        {data
                            ? `Statement of Account — ${data.periodLabel}`
                            : 'Statement of Account'}
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        {data
                            ? `${data.periodLabel} · Issued ${data.issuedAt}`
                            : 'Loan statement for the selected month.'}
                    </DialogDescription>
                </DialogHeader>

                {loading ? (
                    <p className="px-2 py-10 text-center text-sm text-muted-foreground">
                        Loading statement…
                    </p>
                ) : error !== null ? (
                    <div className="flex flex-col items-center gap-2 px-2 py-10 text-center">
                        <p className="text-base font-bold">
                            Unable to load statement
                        </p>
                        <p className="text-sm text-muted-foreground">{error}</p>
                    </div>
                ) : data ? (
                    <div className="flex flex-col gap-5">
                        <div className="flex flex-col gap-1 border-b border-border pb-4">
                            <p className="text-[12px] font-semibold tracking-[0.06em] text-muted-foreground uppercase">
                                MRDINC Portal
                            </p>
                            <p className="text-[17px] font-bold">
                                Statement of Account
                            </p>
                            <p className="text-[14px] text-muted-foreground">
                                {data.periodLabel} · issued {data.issuedAt}
                            </p>
                        </div>

                        <dl className="grid grid-cols-2 gap-x-4 gap-y-4 sm:grid-cols-4">
                            {facts.map(([label, value]) => (
                                <div key={label} className="min-w-0">
                                    <dt className="text-[11px] font-semibold tracking-[0.05em] text-muted-foreground uppercase">
                                        {label}
                                    </dt>
                                    <dd className="truncate text-[14.5px] tabular-nums">
                                        {value}
                                    </dd>
                                </div>
                            ))}
                        </dl>

                        <div className="overflow-x-auto">
                            <Table
                                className="w-full min-w-[640px] border-collapse text-[13.5px]"
                                aria-label={`Loan movements for ${data.periodLabel}`}
                            >
                                <TableHeader className="bg-transparent">
                                    <TableRow className="border-b border-border text-left text-[11.5px] font-bold tracking-[0.05em] text-muted-foreground uppercase hover:bg-transparent">
                                        <TableHead
                                            scope="col"
                                            className="h-auto bg-transparent px-0 py-3 pr-3 font-bold"
                                        >
                                            Date
                                        </TableHead>
                                        <TableHead
                                            scope="col"
                                            className="h-auto bg-transparent px-0 py-3 pr-3 font-bold"
                                        >
                                            Reference
                                        </TableHead>
                                        <TableHead
                                            scope="col"
                                            className="h-auto bg-transparent px-0 py-3 pr-3 font-bold"
                                        >
                                            Description
                                        </TableHead>
                                        <TableHead
                                            scope="col"
                                            className="h-auto bg-transparent px-0 py-3 pr-3 text-right font-bold"
                                        >
                                            Principal
                                        </TableHead>
                                        <TableHead
                                            scope="col"
                                            className="h-auto bg-transparent px-0 py-3 pr-3 text-right font-bold"
                                        >
                                            Interest
                                        </TableHead>
                                        <TableHead
                                            scope="col"
                                            className="h-auto bg-transparent px-0 py-3 pr-3 text-right font-bold"
                                        >
                                            Payment
                                        </TableHead>
                                        <TableHead
                                            scope="col"
                                            className="h-auto bg-transparent px-0 py-3 text-right font-bold"
                                        >
                                            Balance
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {data.movements.length === 0 ? (
                                        <TableRow className="hover:bg-transparent">
                                            <TableCell
                                                colSpan={7}
                                                className="px-0 py-6 text-center text-muted-foreground"
                                            >
                                                No movements this month.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        data.movements.map((row, index) => (
                                            <TableRow
                                                key={`${row.reference}-${index}`}
                                                className="border-b border-border last:border-b-0"
                                            >
                                                <TableCell className="px-0 py-3 pr-3 tabular-nums">
                                                    {formatShortDate(row.date)}
                                                </TableCell>
                                                <TableCell className="px-0 py-3 pr-3 tabular-nums">
                                                    {row.reference}
                                                </TableCell>
                                                <TableCell className="px-0 py-3 pr-3">
                                                    {row.description}
                                                </TableCell>
                                                <TableCell className="px-0 py-3 pr-3 text-right tabular-nums">
                                                    {formatCurrency(
                                                        row.principal,
                                                    )}
                                                </TableCell>
                                                <TableCell className="px-0 py-3 pr-3 text-right tabular-nums">
                                                    {formatCurrency(
                                                        row.interest,
                                                    )}
                                                </TableCell>
                                                <TableCell className="px-0 py-3 pr-3 text-right tabular-nums">
                                                    {formatCurrency(row.credit)}
                                                </TableCell>
                                                <TableCell className="px-0 py-3 text-right tabular-nums">
                                                    {money(row.balance)}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        <dl className="grid gap-x-10 text-[14px] sm:grid-cols-2">
                            <div>
                                <SummaryRow
                                    label={`Opening balance · ${data.periodLabel}`}
                                    value={money(data.openingBalance)}
                                />
                                <SummaryRow
                                    label="Total interest charged"
                                    value={formatCurrency(data.totals.interest)}
                                />
                                <SummaryRow
                                    label={`Closing balance · ${data.periodLabel}`}
                                    value={money(data.closingBalance)}
                                />
                                <SummaryRow
                                    label="Outstanding balance now"
                                    value={money(loan.balance ?? 0)}
                                />
                            </div>
                            <div>
                                <SummaryRow
                                    label="Total principal applied"
                                    value={formatCurrency(
                                        data.totals.principal,
                                    )}
                                />
                                <SummaryRow
                                    label="Total payments received"
                                    value={formatCurrency(data.totals.payments)}
                                />
                                <SummaryRow
                                    label="Installments paid to date"
                                    value={installmentsPaid}
                                />
                                <SummaryRow
                                    label="Total amount due"
                                    value={formatCurrency(
                                        (loan.monthlyDue ?? 0) +
                                            (loan.penalty ?? 0),
                                    )}
                                />
                            </div>
                        </dl>
                        <p className="text-center text-[12px] text-muted-foreground">
                            This is a system-generated statement. No signature
                            required. Please disregard if payment has already
                            been made.
                        </p>
                    </div>
                ) : null}

                <DialogFooter className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    {data ? (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                onClick={() =>
                                    printStatement(
                                        data,
                                        loan,
                                        memberName,
                                        accountNo,
                                        { rateLabel, termLabel },
                                        {
                                            companyName:
                                                branding.general.companyName,
                                            address: branding.businessAddress,
                                            phone: branding.contact
                                                .supportPhone,
                                            logoUrl:
                                                branding.reports.header
                                                    .designUrl ??
                                                branding.assets.logoFullUrl,
                                            tin: branding.general.businessTin,
                                            registrationNo:
                                                branding.general.registrationNo,
                                            paymentInstructions:
                                                branding.general
                                                    .paymentInstructions,
                                            contactName:
                                                branding.contact
                                                    .supportContactName,
                                            contactEmail:
                                                branding.contact.supportEmail,
                                        },
                                    )
                                }
                            >
                                Print
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                onClick={() =>
                                    downloadStatement(data, loan, memberName)
                                }
                            >
                                Download
                            </Button>
                        </>
                    ) : null}
                    <Button
                        type="button"
                        onClick={onClose}
                        className="w-full sm:col-start-3"
                    >
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function SummaryRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4 border-b border-border py-2.5">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-bold tabular-nums">{value}</dd>
        </div>
    );
}

type StatementBrand = {
    companyName: string;
    address: string | null;
    phone: string | null;
    logoUrl: string;
    tin: string | null;
    registrationNo: string | null;
    paymentInstructions: string | null;
    contactName: string | null;
    contactEmail: string | null;
};

const STATEMENT_CSS = `
:root{--primary:#176433;--tile:#eaf1da;--ink:#14170f;--muted:#4a4f3f;--border:#b7c399;--head:#e6e6e6}
@page{size:A4 portrait;margin:10mm}
*{box-sizing:border-box}
body{margin:0;padding:24px;font:14px/1.5 "Instrument Sans",system-ui,sans-serif;color:var(--ink);background:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact}
h1{margin:0;font-size:18px;line-height:1.2;letter-spacing:.06em;text-transform:uppercase;color:var(--primary)}
.top{display:flex;flex-direction:column;align-items:center;gap:6px;margin-bottom:8px}
.ttl{text-align:center}
.brand{flex:0 1 auto;height:80px;max-width:100%;aspect-ratio:1137/309;margin:0;display:flex;justify-content:center}
.brand img{width:100%;height:100%;object-fit:contain;object-position:center}
.sub{margin:2px 0 0;font-size:12.5px;color:var(--muted)}
hr{border:0;border-top:3px solid var(--primary);margin:0 0 10px}
.label{font-size:11.5px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
.parties{display:grid;grid-template-columns:1fr 1fr;gap:32px}
.parties div{display:flex;flex-direction:column}.parties .label{margin-bottom:6px;color:var(--ink)}
.blank{display:inline-block;min-width:140px;border-bottom:1px solid var(--muted);height:1em;vertical-align:baseline}
.meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin:12px 0;padding:12px 16px;background:var(--tile);border-radius:12px}
.meta span{display:block}.meta b{font-weight:600}
.terms{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px 16px;margin:0 0 14px;padding:12px 16px;border:1px solid var(--border);border-radius:12px}
.terms span{display:block}.terms b{font-weight:600}
h2{margin:0 0 8px;font-size:14px}
table{width:100%;border-collapse:collapse;font-size:12px;font-variant-numeric:tabular-nums}
th{padding:8px 6px;font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;color:var(--ink);text-align:left;white-space:nowrap;background:var(--head)}
td{padding:7px 6px;vertical-align:top}
tr{break-inside:avoid}
.nw{white-space:nowrap}
td.d{width:100%}
.n{text-align:right;white-space:nowrap}td.n:last-child{font-weight:700}
.bf td{font-style:italic;color:var(--muted)}
tbody tr:last-child td{border-bottom:1px solid var(--border)}
.totals{width:min(100%,340px);margin:16px 0 0 auto;border:2px solid var(--primary);border-radius:10px;overflow:hidden}
.totals div{display:flex;justify-content:space-between;gap:16px;padding:6px 12px}
.totals .strong{background:var(--tile);font-weight:700;font-size:15px;border-top:1px solid var(--border)}
.note{margin:6px 0 0;font-size:11.5px;color:var(--muted);text-align:right}
.pay{margin-top:18px;padding:12px 16px;border:1px solid var(--border);border-radius:12px;font-size:12.5px;white-space:pre-line}
.pay .label{display:block;margin-bottom:4px}
.lines{display:block;height:40px;border-bottom:1px solid var(--muted)}
.foot{margin-top:24px;padding-top:10px;border-top:1px solid var(--border);font-size:11.5px;color:var(--muted);text-align:center}
.foot b{color:var(--ink)}
@media print{body{padding:0}}
@media(max-width:560px){body{padding:16px}.parties,.meta,.terms{grid-template-columns:1fr}table{font-size:12px}th,td{padding:6px 3px}}
`;

const longDate = (value: string | null | undefined): string => {
    if (!value) {
        return '--';
    }

    const parsed = new Date(
        /^\d{4}-\d{2}-\d{2}$/.test(value) ? `${value}T00:00:00` : value,
    );

    return Number.isNaN(parsed.getTime())
        ? '--'
        : parsed.toLocaleDateString('en-PH', {
              year: 'numeric',
              month: 'long',
              day: 'numeric',
          });
};

const peso = (value: number | null | undefined): string =>
    formatCurrency(value).replace(/^PHP\s?/, '₱');

type PrintTerms = { rateLabel: string; termLabel: string };

function printStatement(
    data: StatementData,
    loan: MemberLoan,
    memberName: string,
    accountNo: string | null,
    terms: PrintTerms,
    brand: StatementBrand,
): void {
    // Create an iframe for printing (no new window = SPA-friendly)
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    document.body.appendChild(iframe);

    const text = (value: string): string =>
        value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    const num = (value: number | null): string =>
        value === null ? '' : peso(value);
    const orBlank = (value: string | null): string =>
        value ? text(value) : '<span class="blank"></span>';
    const loanNo = text(String(loan.lnnumber ?? '--'));
    const logo = brand.logoUrl
        ? `<img src="${text(new URL(brand.logoUrl, window.location.origin).href)}" alt="${text(brand.companyName)}">`
        : '';
    const monthlyDue = loan.monthlyDue ?? 0;
    const penalty = loan.penalty ?? 0;
    const contact = [brand.contactName, brand.phone, brand.contactEmail]
        .filter(Boolean)
        .map((part) => text(String(part)))
        .join(' · ');
    const term = (label: string, value: string): string =>
        `<div><span class="label">${label}</span><b>${text(value)}</b></div>`;

    const html =
        `<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SOA ${loanNo}</title>` +
        `<link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><style>${STATEMENT_CSS}</style></head><body>` +
        `<header class="top"><div class="brand" data-org-logo>${logo}</div><div class="ttl"><h1>Statement of Account</h1><p class="sub">${text(brand.companyName)} · Member Loan Portal</p></div></header><hr>` +
        `<section class="parties"><div><span class="label">Borrower</span><b>${text(memberName)}</b>${data.memberAddress ? `<span>${text(data.memberAddress)}</span>` : ''}<span>Account no: ${text(accountNo ?? '--')}</span></div>` +
        `<div><span class="label">From</span><b>${text(brand.companyName)}</b>${brand.address ? `<span>${text(brand.address)}</span>` : ''}${brand.phone ? `<span>Tel: ${text(brand.phone)}</span>` : ''}<span>TIN: ${orBlank(brand.tin)}</span><span>CDA/SEC Reg. No.: ${orBlank(brand.registrationNo)}</span></div></section>` +
        `<section class="meta"><div><span class="label">Statement period</span><b>${text(data.periodLabel)}</b></div><div><span class="label">Date issued</span><b>${text(longDate(data.issuedAt))}</b></div><div><span class="label">Loan reference</span><b>${loanNo}</b></div></section>` +
        `<section class="terms">${term('Loan type', loan.lntype ?? '--')}${term('Original principal', peso(loan.principal))}${term('Interest rate', terms.rateLabel)}${term('Term', terms.termLabel)}${term('Monthly amortization', peso(monthlyDue))}${term('Next due date', longDate(loan.dueDate))}${term('Outstanding balance', peso(loan.balance))}</section>` +
        `<h2>Account activity — ${text(data.periodLabel)}</h2>` +
        `<table><thead><tr><th>Date</th><th>OR No.</th><th>Particulars</th><th class="n">Principal</th><th class="n">Interest</th><th class="n">Payment</th><th class="n">Balance</th></tr></thead><tbody>` +
        `<tr class="bf"><td colspan="6">Balance brought forward</td><td class="n">${text(num(data.openingBalance) || '—')}</td></tr>` +
        data.movements
            .map(
                (row) =>
                    `<tr><td class="nw">${text(longDate(row.date))}</td><td class="nw">${text(row.reference)}</td><td class="d">${text(row.description)}</td><td class="n">${num(row.principal)}</td><td class="n">${num(row.interest)}</td><td class="n">${num(row.credit)}</td><td class="n">${num(row.balance)}</td></tr>`,
            )
            .join('') +
        `</tbody></table>` +
        `<div class="totals"><div><span>Monthly amortization</span><b>${peso(monthlyDue)}</b></div>${penalty > 0 ? `<div><span>Penalty</span><b>${peso(penalty)}</b></div>` : ''}<div class="strong"><span>Total amount due</span><span>${peso(monthlyDue + penalty)}</span></div></div>` +
        `<p class="note">Due on ${text(longDate(loan.dueDate))}. Please pay on or before the due date to avoid penalties.</p>` +
        `<section class="pay"><span class="label">Payment instructions</span>${brand.paymentInstructions ? text(brand.paymentInstructions) : '<span class="lines"></span><span class="lines"></span>'}</section>` +
        `<section class="foot"><b>This is a system-generated statement. No signature required.</b><br>Please disregard if payment has already been made.${contact ? `<br>For queries, contact ${contact}` : ''}</section>` +
        `</body></html>`;

    iframe.contentDocument?.open();
    iframe.contentDocument?.write(html);
    iframe.contentDocument?.close();

    iframe.onload = () => {
        iframe.contentWindow?.focus();
        iframe.contentWindow?.print();
        setTimeout(() => document.body.removeChild(iframe), 1000);
    };
}
function downloadStatement(
    data: StatementData,
    loan: MemberLoan,
    memberName: string,
): void {
    const lines = [
        'MRDINC Portal — Statement of Account',
        `Period: ${data.periodLabel}  Issued: ${data.issuedAt}`,
        `Borrower: ${memberName}  Loan no: ${String(loan.lnnumber ?? '--')}`,
        '',
        'Date        OR No.          Particulars                 Principal   Interest     Credit      Balance',
        ...(data.movements.map(
            (row) =>
                `${(row.date ?? '--').padEnd(11)} ${row.reference.padEnd(15)} ${row.description.padEnd(31)} ${(row.principal?.toFixed(2) ?? '--').padStart(10)} ${(row.interest?.toFixed(2) ?? '--').padStart(9)} ${row.credit.toFixed(2).padStart(10)} ${(row.balance ?? 0).toFixed(2).padStart(10)}`,
        ) ?? []),
        '',
        `Opening balance: ${data.openingBalance?.toFixed(2) ?? '--'}`,
        `Principal applied: ${data.totals.principal.toFixed(2)}`,
        `Interest charged: ${data.totals.interest.toFixed(2)}`,
        `Payments received: ${data.totals.payments.toFixed(2)}`,
        `Closing balance: ${data.closingBalance?.toFixed(2) ?? '--'}`,
        '',
        'This is a system-generated statement. No signature required.',
        'Please disregard if payment has already been made.',
    ];

    downloadTextFile(
        `SOA_${loan.lnnumber ?? 'loan'}_${data.month}.txt`,
        lines.join('\n'),
        'text/plain;charset=utf-8;',
    );
}
