import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import {
    Banknote,
    CalendarCheck,
    Clock,
    CreditCard,
    Download,
    ExternalLink,
    Printer,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { MemberAccountAlert } from '@/features/member-accounts/components/member-account-alert';
import {
    MemberDetailPrimaryCard,
    MemberDetailSupportingCard,
} from '@/components/member-detail-summary-cards';
import { MemberLoanDetailHeader } from '@/components/member-loan-detail-header';
import { MemberLoanPaymentsFiltersCard } from '@/components/member-loan-payments-filters-card';
import { MemberLoanPaymentsRecordsCard } from '@/components/member-loan-payments-records-card';
import { SurfaceCard } from '@/components/surface-card';
import { Button } from '@/components/ui/button';
import { FieldMessage } from '@/components/ui/field-message';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { PageShell } from '@/components/page-shell';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import api, { getApiErrorMessage, mapValidationErrors } from '@/lib/api';
import { formatCurrency, formatDate } from '@/lib/formatters';
import {
    dashboard as clientDashboard,
    loanPayments,
    loanSchedule,
    loans as clientLoans,
} from '@/routes/client';
import loanPaymentsRoutes from '@/routes/client/loan-payments';
import { store as storePaymongoPayment } from '@/routes/client/loan-payments/paymongo';
import type { BreadcrumbItem } from '@/types';
import type {
    MemberLoan,
    MemberLoanPaymentsFilters,
    MemberLoanPaymentsResponse,
    MemberLoanSummary,
    PaymongoLoanPaymentMethod,
} from '@/types/admin';

type MemberSummary = {
    member_name: string | null;
    acctno: string | null;
};

type Props = {
    member: MemberSummary;
    loan: MemberLoan;
    summary: MemberLoanSummary;
    payments: MemberLoanPaymentsResponse;
};

const presetRanges: Array<{
    value: MemberLoanPaymentsFilters['range'];
    label: string;
}> = [
    { value: 'current_month', label: 'Current Month' },
    { value: 'current_year', label: 'Current Year' },
    { value: 'last_30_days', label: 'Last 30 Days' },
    { value: 'all', label: 'All Transactions' },
    { value: 'custom', label: 'Custom Range' },
];

const vatMultiplier = 1.12;
const fixedFeeCents = 1339;

const paymongoPaymentMethods: Array<{
    value: PaymongoLoanPaymentMethod;
    label: string;
    rate: number;
    fixedFeeCents: number;
    usesMinimum?: boolean;
}> = [
    { value: 'gcash', label: 'GCash', rate: 0.0223, fixedFeeCents: 0 },
    { value: 'maya', label: 'Maya', rate: 0.0179, fixedFeeCents: 0 },
    { value: 'qrph', label: 'QRPh', rate: 0.0134, fixedFeeCents: 0 },
    {
        value: 'online_banking',
        label: 'Online Banking',
        rate: 0.0071,
        fixedFeeCents,
        usesMinimum: true,
    },
];

type PaymongoCheckoutResponse = {
    payment_id: string;
    checkout_url: string;
    base_amount: number;
    service_fee: number;
    total_amount: number;
    payment_method: PaymongoLoanPaymentMethod;
};

type PaymongoFieldErrors = Partial<
    Record<'amount' | 'payment_method', string>
>;

const amountToCents = (value: string): number | null => {
    const amount = Number(value);

    if (!Number.isFinite(amount) || amount <= 0) {
        return null;
    }

    return Math.round(amount * 100);
};

const withVatCents = (amount: number): number =>
    amount === 0 ? 0 : Math.ceil(amount * vatMultiplier);

const calculatePassOnFee = (
    baseAmountCents: number,
    rate: number,
    vatInclusiveFixedFeeCents: number,
): number =>
    Math.ceil(
        (baseAmountCents + vatInclusiveFixedFeeCents) / (1 - rate) -
            baseAmountCents,
    );

const calculatePaymongoAmounts = (
    baseAmountCents: number | null,
    method: PaymongoLoanPaymentMethod,
) => {
    const definition = paymongoPaymentMethods.find(
        (paymentMethod) => paymentMethod.value === method,
    );

    if (!definition || baseAmountCents === null) {
        return {
            baseAmountCents: 0,
            serviceFeeCents: 0,
            grossAmountCents: 0,
        };
    }

    const rate = definition.rate * vatMultiplier;
    const vatInclusiveFixedFeeCents = withVatCents(definition.fixedFeeCents);
    const percentageFeeCents = calculatePassOnFee(baseAmountCents, rate, 0);
    const serviceFeeCents = definition.usesMinimum
        ? Math.max(percentageFeeCents, vatInclusiveFixedFeeCents)
        : calculatePassOnFee(
              baseAmountCents,
              rate,
              vatInclusiveFixedFeeCents,
          );

    return {
        baseAmountCents,
        serviceFeeCents,
        grossAmountCents: baseAmountCents + serviceFeeCents,
    };
};

export default function LoanPayments({
    member,
    loan,
    summary,
    payments,
}: Props) {
    const loanNumber = loan.lnnumber ?? null;
    const perPage = payments.meta.perPage;
    const defaultOnlinePaymentAmount =
        summary.balance && summary.balance > 0 ? summary.balance.toFixed(2) : '';

    const [filters, setFilters] = useState<MemberLoanPaymentsFilters>(
        payments.filters,
    );
    const [loading, setLoading] = useState(false);
    const [onlinePaymentAmount, setOnlinePaymentAmount] = useState(
        defaultOnlinePaymentAmount,
    );
    const [paymentMethod, setPaymentMethod] =
        useState<PaymongoLoanPaymentMethod>('gcash');
    const [checkoutLoading, setCheckoutLoading] = useState(false);
    const [checkoutError, setCheckoutError] = useState<string | null>(null);
    const [checkoutFieldErrors, setCheckoutFieldErrors] =
        useState<PaymongoFieldErrors>({});

    const filtersReady =
        filters.range !== 'custom' ||
        (Boolean(filters.start) && Boolean(filters.end));

    const items = payments.items ?? [];
    const meta = payments.meta;
    const openingBalance = payments.openingBalance;
    const closingBalance = payments.closingBalance;
    const showSkeleton = loading && items.length === 0;
    const canNavigate = Boolean(member.acctno && loanNumber);
    const scheduleHref = loanNumber ? loanSchedule(loanNumber).url : null;
    const paymentsHref = loanNumber ? loanPayments(loanNumber).url : null;
    const backToLoansHref = clientLoans().url;
    const backToProfileHref = clientDashboard().url;
    const onlinePaymentAmountCents = amountToCents(onlinePaymentAmount);
    const outstandingBalanceCents =
        summary.balance && summary.balance > 0
            ? Math.round(summary.balance * 100)
            : null;
    const onlinePaymentEstimate = calculatePaymongoAmounts(
        onlinePaymentAmountCents,
        paymentMethod,
    );
    const canStartCheckout = Boolean(
        loanNumber && onlinePaymentAmountCents && !checkoutLoading,
    );

    const reloadPayments = (
        nextPage: number,
        nextFilters: MemberLoanPaymentsFilters,
    ) => {
        if (!loanNumber) {
            return;
        }

        setLoading(true);
        router.get(
            loanPayments(loanNumber).url,
            {
                page: nextPage,
                perPage,
                range: nextFilters.range,
                start: nextFilters.start ?? undefined,
                end: nextFilters.end ?? undefined,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setLoading(false);
                },
            },
        );
    };

    const handlePageChange = (nextPage: number) => {
        if (nextPage === meta.page || !filtersReady) {
            return;
        }

        reloadPayments(nextPage, filters);
    };

    const updateRange = (range: MemberLoanPaymentsFilters['range']) => {
        const nextFilters = {
            range,
            start: range === 'custom' ? filters.start : null,
            end: range === 'custom' ? filters.end : null,
        };

        setFilters(nextFilters);

        if (range !== 'custom' || (nextFilters.start && nextFilters.end)) {
            reloadPayments(1, nextFilters);
        }
    };

    const updateStart = (value: string) => {
        const nextFilters = { ...filters, start: value || null };

        setFilters(nextFilters);

        if (
            filters.range !== 'custom' ||
            (nextFilters.start && nextFilters.end)
        ) {
            reloadPayments(1, nextFilters);
        }
    };

    const updateEnd = (value: string) => {
        const nextFilters = { ...filters, end: value || null };

        setFilters(nextFilters);

        if (
            filters.range !== 'custom' ||
            (nextFilters.start && nextFilters.end)
        ) {
            reloadPayments(1, nextFilters);
        }
    };

    const buildExportUrl = (download?: boolean) =>
        loanPaymentsRoutes.export(
            { loanNumber: loanNumber ?? '' },
            {
                query: {
                    format: 'pdf',
                    range: filters.range,
                    start: filters.start ?? undefined,
                    end: filters.end ?? undefined,
                    download: download ? 1 : undefined,
                },
            },
        ).url;

    const buildPrintUrl = () =>
        loanPaymentsRoutes.print(
            { loanNumber: loanNumber ?? '' },
            {
                query: {
                    range: filters.range,
                    start: filters.start ?? undefined,
                    end: filters.end ?? undefined,
                },
            },
        ).url;

    const handleOnlinePaymentSubmit = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        if (!loanNumber) {
            return;
        }

        setCheckoutError(null);
        setCheckoutFieldErrors({});

        if (onlinePaymentAmountCents === null) {
            setCheckoutFieldErrors({
                amount: 'Enter a valid payment amount.',
            });

            return;
        }

        if (
            outstandingBalanceCents !== null &&
            onlinePaymentAmountCents > outstandingBalanceCents
        ) {
            setCheckoutFieldErrors({
                amount: 'Amount cannot exceed the outstanding balance.',
            });

            return;
        }

        setCheckoutLoading(true);

        try {
            const response = await api.post<PaymongoCheckoutResponse>(
                storePaymongoPayment({ loanNumber }).url,
                {
                    amount: onlinePaymentAmount,
                    payment_method: paymentMethod,
                },
            );

            window.location.assign(response.data.checkout_url);
        } catch (error) {
            if (axios.isAxiosError(error) && error.response?.status === 422) {
                const payload = error.response.data as {
                    errors?: Record<string, string[]>;
                    message?: string;
                };

                setCheckoutFieldErrors(
                    mapValidationErrors(payload.errors) as PaymongoFieldErrors,
                );
                setCheckoutError(payload.message ?? 'Review the payment form.');
            } else {
                setCheckoutError(
                    getApiErrorMessage(
                        error,
                        'PayMongo checkout could not be started.',
                    ),
                );
            }
        } finally {
            setCheckoutLoading(false);
        }
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Member profile', href: clientDashboard().url },
        { title: 'Loans', href: clientLoans().url },
        { title: 'Payments', href: '#' },
    ];

    const summaryBalance = formatCurrency(summary.balance);
    const nextPayment = summary.next_payment_date
        ? formatDate(summary.next_payment_date)
        : 'No upcoming schedule';
    const lastPayment = summary.last_payment_date
        ? formatDate(summary.last_payment_date)
        : 'No payment recorded yet';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Loan Payments" />
            <PageShell>
                <MemberLoanDetailHeader
                    title="Loan Payments"
                    subtitle={`${member.member_name ?? 'Member'} - Loan ${loan.lnnumber ?? '--'}`}
                    meta={`Account No: ${member.acctno ?? '--'} | Loan Type: ${loan.lntype ?? '--'}`}
                    currentView="payments"
                    scheduleHref={scheduleHref}
                    paymentsHref={paymentsHref}
                    canNavigate={canNavigate}
                    backToLoansHref={backToLoansHref}
                    backToProfileHref={backToProfileHref}
                />

                {!member.acctno || !loanNumber ? (
                    <MemberAccountAlert
                        title="Loan not available"
                        description="This member needs a valid loan number and account number before payments can be displayed."
                    />
                ) : null}

                {showSkeleton ? (
                    <div className="grid gap-4 md:grid-cols-3">
                        {Array.from({ length: 3 }).map((_, index) => (
                            <SurfaceCard
                                key={`summary-skeleton-${index}`}
                                variant="default"
                                padding="md"
                            >
                                <div className="space-y-3">
                                    <Skeleton className="h-3 w-24" />
                                    <Skeleton className="h-8 w-32" />
                                    <Skeleton className="h-3 w-28" />
                                </div>
                            </SurfaceCard>
                        ))}
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-3">
                        <MemberDetailPrimaryCard
                            title="Outstanding Loan Balance"
                            value={summaryBalance}
                            helper="Current balance for this loan."
                            icon={Banknote}
                            accent="primary"
                        />
                        <MemberDetailSupportingCard
                            title="Next Payment Date"
                            description="Nearest scheduled payment date."
                            value={nextPayment}
                            icon={CalendarCheck}
                            accent="primary"
                        />
                        <MemberDetailSupportingCard
                            title="Last Payment Date"
                            description="Most recent payment recorded."
                            value={lastPayment}
                            icon={Clock}
                            accent="accent"
                        />
                    </div>
                )}

                <SurfaceCard variant="default" padding="md">
                    <form
                        className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(280px,360px)]"
                        onSubmit={handleOnlinePaymentSubmit}
                    >
                        <div className="space-y-4">
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div className="min-w-0 space-y-1">
                                    <div className="flex items-center gap-2 text-sm font-semibold text-foreground">
                                        <CreditCard className="size-4 text-primary" />
                                        Pay Online
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        PayMongo checkout for this loan.
                                    </p>
                                </div>
                                <div className="text-left sm:text-right">
                                    <p className="text-xs font-medium text-muted-foreground">
                                        Outstanding Balance
                                    </p>
                                    <p className="text-sm font-semibold text-foreground">
                                        {summaryBalance}
                                    </p>
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="paymongo-amount">
                                        Amount
                                    </Label>
                                    <Input
                                        id="paymongo-amount"
                                        type="number"
                                        min="1"
                                        max={summary.balance || undefined}
                                        step="0.01"
                                        inputMode="decimal"
                                        value={onlinePaymentAmount}
                                        aria-invalid={
                                            checkoutFieldErrors.amount
                                                ? true
                                                : undefined
                                        }
                                        onChange={(event) => {
                                            setOnlinePaymentAmount(
                                                event.target.value,
                                            );
                                            setCheckoutFieldErrors(
                                                (current) => ({
                                                    ...current,
                                                    amount: undefined,
                                                }),
                                            );
                                        }}
                                    />
                                    <FieldMessage
                                        error={checkoutFieldErrors.amount}
                                        hint={
                                            outstandingBalanceCents
                                                ? `Maximum ${formatCurrency(summary.balance)}`
                                                : undefined
                                        }
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="paymongo-method">
                                        Payment Method
                                    </Label>
                                    <Select
                                        value={paymentMethod}
                                        onValueChange={(value) => {
                                            setPaymentMethod(
                                                value as PaymongoLoanPaymentMethod,
                                            );
                                            setCheckoutFieldErrors(
                                                (current) => ({
                                                    ...current,
                                                    payment_method: undefined,
                                                }),
                                            );
                                        }}
                                    >
                                        <SelectTrigger
                                            id="paymongo-method"
                                            aria-invalid={
                                                checkoutFieldErrors.payment_method
                                                    ? true
                                                    : undefined
                                            }
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {paymongoPaymentMethods.map(
                                                (method) => (
                                                    <SelectItem
                                                        key={method.value}
                                                        value={method.value}
                                                    >
                                                        {method.label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <FieldMessage
                                        error={
                                            checkoutFieldErrors.payment_method
                                        }
                                    />
                                </div>
                            </div>

                            {checkoutError ? (
                                <p className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                                    {checkoutError}
                                </p>
                            ) : null}
                        </div>

                        <div className="rounded-lg border border-border/50 bg-background/60 p-4">
                            <div className="space-y-3 text-sm">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-muted-foreground">
                                        Loan Payment
                                    </span>
                                    <span className="font-medium text-foreground">
                                        {formatCurrency(
                                            onlinePaymentEstimate.baseAmountCents /
                                                100,
                                        )}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-muted-foreground">
                                        Service Fee
                                    </span>
                                    <span className="font-medium text-foreground">
                                        {formatCurrency(
                                            onlinePaymentEstimate.serviceFeeCents /
                                                100,
                                        )}
                                    </span>
                                </div>
                                <div className="border-t border-border/60 pt-3">
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="font-semibold text-foreground">
                                            Total Amount
                                        </span>
                                        <span className="text-lg font-semibold text-foreground">
                                            {formatCurrency(
                                                onlinePaymentEstimate.grossAmountCents /
                                                    100,
                                            )}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                disabled={!canStartCheckout}
                            >
                                <ExternalLink />
                                {checkoutLoading
                                    ? 'Starting checkout...'
                                    : 'Continue to PayMongo'}
                            </Button>
                        </div>
                    </form>
                </SurfaceCard>

                <MemberLoanPaymentsFiltersCard
                    filters={filters}
                    presets={presetRanges}
                    isUpdating={loading}
                    description="Filter and export loan payments."
                    openingBalance={openingBalance}
                    closingBalance={closingBalance}
                    onRangeChange={updateRange}
                    onStartChange={updateStart}
                    onEndChange={updateEnd}
                    footer={
                        <>
                            <Button
                                asChild
                                size="sm"
                                disabled={!filtersReady || !loanNumber}
                            >
                                <a href={buildExportUrl(true)}>
                                    <Download />
                                    Download Pdf
                                </a>
                            </Button>
                            <Button
                                asChild
                                size="sm"
                                variant="outline"
                                disabled={!filtersReady || !loanNumber}
                            >
                                <a
                                    href={buildPrintUrl()}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Printer />
                                    Print
                                </a>
                            </Button>
                        </>
                    }
                />

                <MemberLoanPaymentsRecordsCard
                    items={items}
                    meta={meta}
                    isUpdating={loading}
                    onPageChange={handlePageChange}
                    showSkeleton={showSkeleton}
                />
            </PageShell>
        </AppLayout>
    );
}
