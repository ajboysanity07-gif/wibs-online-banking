import axios from 'axios';
import {
    ExternalLink,
    Landmark,
    LoaderCircle,
    ShieldCheck,
    WalletCards,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Separator } from '@/components/ui/separator';
import api, { getApiErrorMessage, mapValidationErrors } from '@/lib/api';
import { formatCurrency } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import { store as storePaymongoPayment } from '@/routes/client/loan-payments/paymongo';
import type { PaymongoLoanPaymentMethod } from '@/types/admin';

type MemberLoanPaymongoPaymentCardProps = {
    loanNumber: string | number | null;
    loanBalance: number;
};

type PaymongoCheckoutResponse = {
    payment_id: string;
    checkout_url: string;
    base_amount: number;
    service_fee: number;
    total_amount: number;
    payment_method: PaymongoLoanPaymentMethod;
};

type PaymongoFieldErrors = Partial<Record<'amount' | 'payment_method', string>>;

const vatMultiplier = 1.12;
const fixedFeeCents = 1339;

const visiblePaymentMethods: Array<{
    value: PaymongoLoanPaymentMethod;
    label: string;
    helper: string;
    badge: string;
    rate: number;
    fixedFeeCents: number;
    usesMinimum?: boolean;
}> = [
    {
        value: 'gcash',
        label: 'GCash',
        helper: 'Fast checkout for wallet payments.',
        badge: 'Wallet',
        rate: 0.0223,
        fixedFeeCents: 0,
    },
    {
        value: 'maya',
        label: 'Maya',
        helper: 'Direct checkout through Maya.',
        badge: 'Wallet',
        rate: 0.0179,
        fixedFeeCents: 0,
    },
    {
        value: 'qrph',
        label: 'QRPh',
        helper: 'Scan and pay with QRPh-compatible apps.',
        badge: 'QR',
        rate: 0.0134,
        fixedFeeCents: 0,
    },
    {
        value: 'online_banking',
        label: 'Online Banking',
        helper: 'Bank redirect checkout with PayMongo.',
        badge: 'Bank',
        rate: 0.0071,
        fixedFeeCents,
        usesMinimum: true,
    },
];

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
    const definition = visiblePaymentMethods.find(
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
        : calculatePassOnFee(baseAmountCents, rate, vatInclusiveFixedFeeCents);

    return {
        baseAmountCents,
        serviceFeeCents,
        grossAmountCents: baseAmountCents + serviceFeeCents,
    };
};

const summaryRowClassName = 'flex items-center justify-between gap-3 text-sm';

export function MemberLoanPaymongoPaymentCard({
    loanNumber,
    loanBalance,
}: MemberLoanPaymongoPaymentCardProps) {
    const defaultAmount = loanBalance > 0 ? loanBalance.toFixed(2) : '';

    const [onlinePaymentAmount, setOnlinePaymentAmount] =
        useState(defaultAmount);
    const [paymentMethod, setPaymentMethod] =
        useState<PaymongoLoanPaymentMethod>('gcash');
    const [checkoutLoading, setCheckoutLoading] = useState(false);
    const [checkoutError, setCheckoutError] = useState<string | null>(null);
    const [checkoutFieldErrors, setCheckoutFieldErrors] =
        useState<PaymongoFieldErrors>({});

    const selectedMethod = visiblePaymentMethods.find(
        (method) => method.value === paymentMethod,
    );
    const onlinePaymentAmountCents = amountToCents(onlinePaymentAmount);
    const outstandingBalanceCents =
        loanBalance > 0 ? Math.round(loanBalance * 100) : null;
    const onlinePaymentEstimate = calculatePaymongoAmounts(
        onlinePaymentAmountCents,
        paymentMethod,
    );
    const canStartCheckout = Boolean(
        loanNumber &&
        onlinePaymentAmountCents &&
        !checkoutLoading &&
        loanBalance > 0,
    );

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

    return (
        <Card className="overflow-hidden rounded-3xl border-border/50 bg-card/75 shadow-sm">
            <CardHeader className="gap-4 border-b border-border/50 bg-[linear-gradient(135deg,rgba(15,118,110,0.08),rgba(255,255,255,0.75)_42%,rgba(14,165,233,0.04))] dark:bg-[linear-gradient(135deg,rgba(45,212,191,0.08),rgba(15,23,42,0.92)_42%,rgba(14,165,233,0.08))]">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-3">
                        <Badge className="w-fit gap-2 rounded-full px-3 py-1 text-[11px] font-semibold tracking-[0.18em] uppercase shadow-none">
                            <WalletCards className="size-3.5" />
                            Pay Online
                        </Badge>
                        <div className="space-y-1">
                            <CardTitle className="text-xl tracking-tight sm:text-2xl">
                                Pay this loan through PayMongo checkout
                            </CardTitle>
                            <CardDescription className="max-w-3xl text-sm leading-6">
                                Choose a supported payment method, review the
                                estimated fee, and continue in a hosted checkout
                                flow without leaving the loan context.
                            </CardDescription>
                        </div>
                    </div>

                    <Card className="w-full max-w-xs rounded-2xl border-border/50 bg-background/85 shadow-none">
                        <CardContent className="flex items-center gap-3 px-4 py-4">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <ShieldCheck className="size-4" />
                            </div>
                            <div className="space-y-1">
                                <p className="text-[11px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                    Outstanding Balance
                                </p>
                                <p className="text-sm font-medium">
                                    {formatCurrency(loanBalance)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </CardHeader>

            <CardContent className="p-6 lg:p-8">
                <form
                    className="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(300px,0.8fr)]"
                    onSubmit={handleOnlinePaymentSubmit}
                >
                    <div className="space-y-6">
                        <div className="grid gap-5 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="paymongo-amount">Amount</Label>
                                <Input
                                    id="paymongo-amount"
                                    type="number"
                                    min="1"
                                    max={loanBalance || undefined}
                                    step="0.01"
                                    inputMode="decimal"
                                    value={onlinePaymentAmount}
                                    aria-invalid={
                                        checkoutFieldErrors.amount
                                            ? true
                                            : undefined
                                    }
                                    disabled={
                                        checkoutLoading || loanBalance <= 0
                                    }
                                    onChange={(event) => {
                                        setOnlinePaymentAmount(
                                            event.target.value,
                                        );
                                        setCheckoutFieldErrors((current) => ({
                                            ...current,
                                            amount: undefined,
                                        }));
                                    }}
                                    className="h-12 rounded-2xl"
                                />
                                <FieldMessage
                                    error={checkoutFieldErrors.amount}
                                    hint={
                                        outstandingBalanceCents
                                            ? `Maximum ${formatCurrency(loanBalance)}`
                                            : 'This loan does not have an outstanding balance.'
                                    }
                                    reserveSpace={false}
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
                                        setCheckoutFieldErrors((current) => ({
                                            ...current,
                                            payment_method: undefined,
                                        }));
                                    }}
                                >
                                    <SelectTrigger
                                        id="paymongo-method"
                                        aria-invalid={
                                            checkoutFieldErrors.payment_method
                                                ? true
                                                : undefined
                                        }
                                        className="h-12 rounded-2xl"
                                    >
                                        <SelectValue placeholder="Choose a payment method" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {visiblePaymentMethods.map((method) => (
                                            <SelectItem
                                                key={method.value}
                                                value={method.value}
                                            >
                                                {method.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <FieldMessage
                                    error={checkoutFieldErrors.payment_method}
                                    hint={selectedMethod?.helper}
                                    reserveSpace={false}
                                />
                            </div>
                        </div>

                        {checkoutError ? (
                            <Alert variant="destructive">
                                <AlertTitle>Checkout unavailable</AlertTitle>
                                <AlertDescription>
                                    {checkoutError}
                                </AlertDescription>
                            </Alert>
                        ) : null}

                        <Alert className="border-border/60 bg-muted/35">
                            <Landmark className="size-4" />
                            <AlertTitle>
                                {loanBalance > 0
                                    ? 'Hosted checkout'
                                    : 'Loan already settled'}
                            </AlertTitle>
                            <AlertDescription className="leading-6">
                                {loanBalance > 0
                                    ? 'After you continue, PayMongo will handle the payment method flow and return you to this loan page.'
                                    : 'Online checkout is unavailable because this loan has no remaining outstanding balance.'}
                            </AlertDescription>
                        </Alert>
                    </div>

                    <Card className="rounded-3xl border-border/50 bg-background/80 shadow-none">
                        <CardHeader className="gap-3">
                            <div className="flex items-center justify-between gap-3">
                                <div className="space-y-1">
                                    <CardTitle className="text-base">
                                        Checkout estimate
                                    </CardTitle>
                                    <CardDescription className="leading-6">
                                        Review the payment amount and estimated
                                        PayMongo fee before redirect.
                                    </CardDescription>
                                </div>
                                {selectedMethod ? (
                                    <Badge
                                        variant="outline"
                                        className="rounded-full px-3 py-1 text-[11px] font-semibold tracking-[0.16em] uppercase"
                                    >
                                        {selectedMethod.badge}
                                    </Badge>
                                ) : null}
                            </div>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            <div className="space-y-3">
                                <div className={summaryRowClassName}>
                                    <span className="text-muted-foreground">
                                        Loan payment
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {formatCurrency(
                                            onlinePaymentEstimate.baseAmountCents /
                                                100,
                                        )}
                                    </span>
                                </div>
                                <div className={summaryRowClassName}>
                                    <span className="text-muted-foreground">
                                        Estimated fee
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {formatCurrency(
                                            onlinePaymentEstimate.serviceFeeCents /
                                                100,
                                        )}
                                    </span>
                                </div>
                                <Separator className="bg-border/60" />
                                <div
                                    className={cn(
                                        summaryRowClassName,
                                        'text-base',
                                    )}
                                >
                                    <span className="font-semibold">
                                        Total at checkout
                                    </span>
                                    <span className="font-semibold tabular-nums">
                                        {formatCurrency(
                                            onlinePaymentEstimate.grossAmountCents /
                                                100,
                                        )}
                                    </span>
                                </div>
                            </div>

                            <Button
                                type="submit"
                                size="lg"
                                className="w-full rounded-2xl"
                                disabled={!canStartCheckout}
                            >
                                {checkoutLoading ? (
                                    <LoaderCircle className="size-4 animate-spin" />
                                ) : (
                                    <ExternalLink className="size-4" />
                                )}
                                {checkoutLoading
                                    ? 'Starting checkout...'
                                    : 'Continue to PayMongo'}
                            </Button>
                        </CardContent>
                    </Card>
                </form>
            </CardContent>
        </Card>
    );
}
