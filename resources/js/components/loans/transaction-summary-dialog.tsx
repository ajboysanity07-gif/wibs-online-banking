import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatCurrency } from '@/lib/formatters';
import type { MemberLoan, MemberLoanPayment } from '@/types/admin';
import { formatShortDate, loanTypeLabel } from './loan-presentation';
import {
    downloadTextFile,
    paymentAmount,
    paymentInterest,
    paymentPrincipal,
    paymentReference,
    paymentStatusLabel,
    receiptFileName,
    receiptText,
} from './payment-presentation';

type TransactionSummaryDialogProps = {
    payment: MemberLoanPayment | null;
    loan: MemberLoan;
    memberName?: string | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function TransactionSummaryDialog({
    payment,
    loan,
    memberName,
    open,
    onOpenChange,
}: TransactionSummaryDialogProps) {
    const loanLabel = `${loan.lnnumber ?? '--'} · ${loanTypeLabel(loan.lntype)}`;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <p className="text-[12px] font-bold tracking-[0.06em] text-muted-foreground uppercase">
                        Payment receipt
                    </p>
                    <DialogTitle className="text-[17px]">
                        Transaction summary
                    </DialogTitle>
                    <DialogDescription className="sr-only">
                        Full details for this posted loan payment.
                    </DialogDescription>
                </DialogHeader>

                {payment ? (
                    <div className="flex flex-col gap-4">
                        <div className="flex flex-col items-center gap-1.5 rounded-[10px] border border-primary/30 bg-primary/10 p-4 text-center text-primary">
                            <span className="text-[12.5px] font-semibold tracking-[0.05em] uppercase">
                                Amount paid
                            </span>
                            <span className="text-[26px] font-bold tabular-nums sm:text-[32px]">
                                {formatCurrency(paymentAmount(payment))}
                            </span>
                            <Badge className="border border-primary/30 bg-primary/10 text-primary">
                                {paymentStatusLabel(payment)}
                            </Badge>
                        </div>

                        <dl className="flex flex-col">
                            <SummaryRow
                                label="Reference no"
                                value={paymentReference(payment)}
                            />
                            <SummaryRow
                                label="Transaction date"
                                value={formatShortDate(payment.date_in)}
                            />
                            <SummaryRow label="Loan" value={loanLabel} />
                            <SummaryRow
                                label="Principal"
                                value={formatCurrency(
                                    paymentPrincipal(payment),
                                )}
                            />
                            <SummaryRow
                                label="Interest"
                                value={formatCurrency(paymentInterest(payment))}
                            />
                            <SummaryRow
                                label="Balance after payment"
                                value={formatCurrency(payment.balance ?? 0)}
                            />
                        </dl>
                    </div>
                ) : null}

                <DialogFooter className="flex-col gap-2 sm:flex-col">
                    {payment ? (
                        <Button
                            type="button"
                            variant="outline"
                            className="w-full"
                            onClick={() =>
                                downloadTextFile(
                                    receiptFileName(loan.lnnumber, payment),
                                    receiptText(
                                        payment,
                                        loan,
                                        memberName ?? '--',
                                    ),
                                    'text/plain;charset=utf-8;',
                                )
                            }
                        >
                            Download receipt
                        </Button>
                    ) : null}
                    <Button
                        type="button"
                        onClick={() => onOpenChange(false)}
                        className="w-full"
                    >
                        Done
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function SummaryRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-baseline justify-between gap-4 border-t border-border py-2.5 first:border-t-0 first:pt-0">
            <dt className="text-[13.5px] text-muted-foreground">{label}</dt>
            <dd className="text-right text-[14.5px] font-semibold tabular-nums">
                {value}
            </dd>
        </div>
    );
}
