import { AlertCircle, CreditCard, Info } from 'lucide-react';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Button } from '@/components/ui/button';
import {
    DataTablePagination,
    DataTablePaginationSkeleton,
} from '@/components/ui/data-table-pagination';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCurrency } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { MemberLoanPayment, PaginationMeta } from '@/types/admin';
import { formatShortDate, loanTypeLabel } from './loan-presentation';
import {
    paymentAmount,
    paymentInterest,
    paymentPrincipal,
    paymentReference,
} from './payment-presentation';

type PaymentLedgerCardProps = {
    payments: MemberLoanPayment[];
    meta: PaginationMeta;
    loanType: string | null;
    showSkeleton?: boolean;
    amountFilterActive?: boolean;
    onPageChange: (page: number) => void;
    onSelectPayment: (payment: MemberLoanPayment) => void;
};

function LedgerSkeleton() {
    return (
        <div className="flex flex-col gap-3" aria-busy="true">
            {Array.from({ length: 3 }).map((_, index) => (
                <Skeleton
                    key={`ledger-skeleton-${index}`}
                    className="h-[62px] w-full rounded-[10px]"
                />
            ))}
        </div>
    );
}

function StateBox({
    icon: Icon,
    title,
    description,
    destructive = false,
}: {
    icon: typeof AlertCircle;
    title: string;
    description: string;
    destructive?: boolean;
}) {
    return (
        <div className="flex flex-col items-center gap-2.5 px-4 py-10 text-center">
            <Icon
                aria-hidden="true"
                className={cn(
                    'h-7 w-7',
                    destructive ? 'text-destructive' : 'text-muted-foreground',
                )}
            />
            <p className="text-base font-bold">{title}</p>
            <p className="text-sm text-muted-foreground">{description}</p>
        </div>
    );
}

export function PaymentLedgerCard({
    payments,
    meta,
    loanType,
    showSkeleton = false,
    amountFilterActive = false,
    onPageChange,
    onSelectPayment,
}: PaymentLedgerCardProps) {
    const showFooter = !showSkeleton && payments.length > 0;

    return (
        <SurfaceCard variant="default" padding="md" className="space-y-5">
            <SectionHeader
                title="Loan Payments"
                description="Payment history for this loan."
                titleClassName="text-base font-semibold"
            />

            {showSkeleton ? (
                <LedgerSkeleton />
            ) : payments.length === 0 ? (
                <StateBox
                    icon={CreditCard}
                    title="No payments found for this period."
                    description="Try a wider date range or clear the amount filter."
                />
            ) : (
                <>
                    <div className="hidden text-[12px] font-bold tracking-[0.06em] text-muted-foreground uppercase md:grid md:grid-cols-[116px_minmax(0,1.5fr)_minmax(0,1.1fr)_minmax(0,0.9fr)_minmax(0,0.9fr)] md:gap-3 md:rounded-t-lg md:border-b md:border-border md:bg-muted/50 md:px-4 md:py-2.5">
                        <span>Transaction date</span>
                        <span>Reference no</span>
                        <span>Principal · Interest</span>
                        <span className="text-right">Amount</span>
                        <span className="text-right">Balance</span>
                    </div>

                    <div className="flex flex-col">
                        {payments.map((payment) => {
                            const reference = paymentReference(payment);
                            const date = formatShortDate(payment.date_in);

                            return (
                                <Button
                                    variant="ghost"
                                    key={`${reference}-${payment.date_in ?? ''}`}
                                    type="button"
                                    onClick={() => onSelectPayment(payment)}
                                    aria-label={`View transaction summary for ${reference} on ${date}`}
                                    className="grid h-auto min-h-11 w-full grid-cols-[minmax(0,1fr)_auto] items-center justify-normal gap-x-3 gap-y-1.5 rounded-none border-b border-border px-1 py-3 text-left font-normal whitespace-normal last:border-b-0 hover:bg-muted/40 hover:text-inherit md:h-auto md:grid-cols-[116px_minmax(0,1.5fr)_minmax(0,1.1fr)_minmax(0,0.9fr)_minmax(0,0.9fr)] md:px-4"
                                >
                                    <span className="col-start-1 row-start-1 min-w-0 md:col-span-1 md:col-start-auto md:row-start-auto">
                                        <span className="block text-[14.5px] font-semibold tabular-nums">
                                            {date}
                                        </span>
                                        <span className="block text-[12.5px] text-muted-foreground md:hidden">
                                            Posted
                                        </span>
                                    </span>

                                    <span className="col-start-1 row-start-2 min-w-0 md:col-span-1 md:col-start-auto md:row-start-auto">
                                        <span className="block truncate text-[14px] font-semibold tabular-nums">
                                            {reference}
                                        </span>
                                        <span className="block truncate text-[12.5px] text-muted-foreground">
                                            {loanTypeLabel(
                                                payment.loan_type ?? loanType,
                                            )}
                                        </span>
                                    </span>

                                    <span className="col-span-2 col-start-1 row-start-3 flex flex-wrap gap-x-4 md:col-span-1 md:col-start-auto md:row-start-auto md:block">
                                        <span className="block text-[12.5px] text-muted-foreground tabular-nums">
                                            Principal{' '}
                                            <strong className="font-bold text-card-foreground">
                                                {formatCurrency(
                                                    paymentPrincipal(payment),
                                                )}
                                            </strong>
                                        </span>
                                        <span className="block text-[12.5px] text-muted-foreground tabular-nums">
                                            Interest{' '}
                                            <strong className="font-bold text-card-foreground">
                                                {formatCurrency(
                                                    paymentInterest(payment),
                                                )}
                                            </strong>
                                        </span>
                                    </span>

                                    <span className="col-start-2 row-start-1 text-right text-[15.5px] font-bold whitespace-nowrap tabular-nums md:col-span-1 md:col-start-auto md:row-start-auto">
                                        {formatCurrency(paymentAmount(payment))}
                                    </span>

                                    <span className="col-start-2 row-start-2 text-right md:col-span-1 md:col-start-auto md:row-start-auto">
                                        <span className="block text-[12px] text-muted-foreground">
                                            Balance
                                        </span>
                                        <span className="block text-[14.5px] font-semibold whitespace-nowrap tabular-nums">
                                            {formatCurrency(
                                                payment.balance ?? 0,
                                            )}
                                        </span>
                                    </span>
                                </Button>
                            );
                        })}
                    </div>
                </>
            )}

            {showFooter ? (
                <DataTablePagination
                    page={meta.page}
                    perPage={meta.perPage}
                    total={meta.total}
                    onPageChange={onPageChange}
                />
            ) : showSkeleton ? (
                <DataTablePaginationSkeleton />
            ) : null}

            {amountFilterActive ? (
                <p className="text-xs text-muted-foreground">
                    The amount filter narrows the payments loaded on this page.
                    Clear it to see every posted payment.
                </p>
            ) : null}

            <div className="flex items-start gap-2.5 rounded-[10px] border border-border bg-muted/50 p-3 text-[13.5px] text-muted-foreground">
                <Info aria-hidden="true" className="mt-0.5 h-4 w-4 shrink-0" />
                <p>
                    Payment records are posted by the cooperative once your
                    payment is confirmed. Select any payment to see its full
                    transaction summary.
                </p>
            </div>
        </SurfaceCard>
    );
}

export function PaymentRetryState({ onRetry }: { onRetry: () => void }) {
    return (
        <SurfaceCard variant="default" padding="md" className="space-y-2">
            <StateBox
                icon={AlertCircle}
                title="Unable to load payments"
                description="Something went wrong while fetching this loan's payment records."
                destructive
            />
            <div className="flex justify-center">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={onRetry}
                >
                    Try again
                </Button>
            </div>
        </SurfaceCard>
    );
}
