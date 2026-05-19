import { router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { ExternalLink, LoaderCircle, ShieldCheck, Wallet } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import api, { getApiErrorMessage, mapValidationErrors } from '@/lib/api';
import { formatCurrency } from '@/lib/formatters';
import { showSuccessToast } from '@/lib/toast';
import { security as storeLoanSecurityPayment } from '@/routes/client/loan-payments';
import { store as storePaymongoPayment } from '@/routes/client/loan-payments/paymongo';
import type {
    MemberLoanSecurityPaymentSummary,
    PaymongoLoanPaymentMethod,
} from '@/types/admin';

type PaymongoMethod = Exclude<PaymongoLoanPaymentMethod, 'card'>;

type UnifiedPaymentMethod = 'loan_security' | PaymongoMethod;

type MemberLoanPaymentCardProps = {
    loanNumber: string | number | null;
    loanBalance: number;
    securityPayment: MemberLoanSecurityPaymentSummary;
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

const paymongoPaymentMethods: Array<{
    value: PaymongoMethod;
    label: string;
    helper: string;
    rate: number;
    fixedFeeCents: number;
    usesMinimum?: boolean;
}> = [
    {
        value: 'gcash',
        label: 'GCash',
        helper: 'Fast checkout for wallet payments.',
        rate: 0.0223,
        fixedFeeCents: 0,
    },
    {
        value: 'maya',
        label: 'Maya',
        helper: 'Direct checkout through Maya.',
        rate: 0.0179,
        fixedFeeCents: 0,
    },
    {
        value: 'qrph',
        label: 'QRPh',
        helper: 'Scan and pay with QRPh-compatible apps.',
        rate: 0.0134,
        fixedFeeCents: 0,
    },
    {
        value: 'online_banking',
        label: 'Online Banking',
        helper: 'Bank redirect checkout with PayMongo.',
        rate: 0.0071,
        fixedFeeCents,
        usesMinimum: true,
    },
];

const paymentMethodOptions: Array<{
    value: UnifiedPaymentMethod;
    label: string;
    helper: string;
}> = [
    {
        value: 'loan_security',
        label: 'Loan Security',
        helper: 'Apply from available loan security while preserving the reserve.',
    },
    ...paymongoPaymentMethods.map((method) => ({
        value: method.value as UnifiedPaymentMethod,
        label: method.label,
        helper: method.helper,
    })),
];

const summaryRowClassName = 'flex items-center justify-between gap-3 text-sm';
const securityDetailsRowClassName =
    'flex items-center justify-between gap-3 text-xs sm:text-sm';

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
    method: PaymongoMethod,
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
        : calculatePassOnFee(baseAmountCents, rate, vatInclusiveFixedFeeCents);

    return {
        baseAmountCents,
        serviceFeeCents,
        grossAmountCents: baseAmountCents + serviceFeeCents,
    };
};

const isPaymongoMethod = (
    method: UnifiedPaymentMethod,
): method is PaymongoMethod => {
    return method !== 'loan_security';
};

const describeSecurityGuardrail = ({
    hasSecurityAccount,
    loanBalance,
    maxPayable,
    minimumBalance,
    requestedAmount,
}: {
    hasSecurityAccount: boolean;
    loanBalance: number;
    maxPayable: number;
    minimumBalance: number;
    requestedAmount: number | null;
}): {
    title: string;
    description: string;
} => {
    if (!hasSecurityAccount) {
        return {
            title: 'Security account unavailable',
            description:
                'No loan security account is available for this loan payment.',
        };
    }

    if (loanBalance <= 0) {
        return {
            title: 'Loan already paid',
            description: 'This loan is already fully paid.',
        };
    }

    if (maxPayable <= 0) {
        return {
            title: 'Reserve fully protected',
            description: `Your current loan security is fully protected by the ${formatCurrency(minimumBalance)} reserve.`,
        };
    }

    if (requestedAmount === null) {
        return {
            title: 'Awaiting amount',
            description: `Enter an amount up to ${formatCurrency(maxPayable)} to preview the payment effect.`,
        };
    }

    if (requestedAmount > maxPayable) {
        return {
            title: 'Exceeds available security',
            description: `This request exceeds the currently available ${formatCurrency(maxPayable)} while preserving the reserve.`,
        };
    }

    if (requestedAmount > loanBalance) {
        return {
            title: 'Capped by loan balance',
            description: `Only ${formatCurrency(loanBalance)} will be applied because that is the remaining loan balance.`,
        };
    }

    return {
        title: 'Ready to submit',
        description: `This payment will apply ${formatCurrency(requestedAmount)} immediately and keep your reserve intact.`,
    };
};

export function MemberLoanPaymentCard({
    loanNumber,
    loanBalance,
    securityPayment,
}: MemberLoanPaymentCardProps) {
    const defaultAmount = loanBalance > 0 ? loanBalance.toFixed(2) : '';
    const paymentForm = useForm({
        amount: defaultAmount,
    });

    const [selectedPaymentMethod, setSelectedPaymentMethod] =
        useState<UnifiedPaymentMethod>('gcash');
    const [checkoutLoading, setCheckoutLoading] = useState(false);
    const [checkoutError, setCheckoutError] = useState<string | null>(null);
    const [checkoutFieldErrors, setCheckoutFieldErrors] =
        useState<PaymongoFieldErrors>({});

    const hasSecurityAccount = Boolean(securityPayment.svnumber);
    const currentSecurityBalance = Number(securityPayment.currentBalance ?? 0);
    const protectedReserve = Number(securityPayment.minimumBalance ?? 0);
    const availableSecurityAmount = Number(securityPayment.maxPayable ?? 0);
    const parsedRequestedAmount = Number(paymentForm.data.amount);
    const hasRequestedAmount =
        paymentForm.data.amount.trim() !== '' &&
        Number.isFinite(parsedRequestedAmount);
    const requestedAmount =
        hasRequestedAmount && parsedRequestedAmount > 0
            ? parsedRequestedAmount
            : null;
    const amountApplied =
        requestedAmount !== null
            ? Math.min(
                  requestedAmount,
                  availableSecurityAmount,
                  Math.max(loanBalance, 0),
              )
            : 0;
    const securityAfterPayment = Math.max(
        0,
        currentSecurityBalance - amountApplied,
    );
    const loanAfterPayment = Math.max(0, loanBalance - amountApplied);
    const outstandingBalanceCents =
        loanBalance > 0 ? Math.round(loanBalance * 100) : null;
    const requestedAmountCents = amountToCents(paymentForm.data.amount);

    const paymongoEstimate =
        requestedAmountCents !== null && isPaymongoMethod(selectedPaymentMethod)
            ? calculatePaymongoAmounts(
                  requestedAmountCents,
                  selectedPaymentMethod,
              )
            : {
                  baseAmountCents: 0,
                  serviceFeeCents: 0,
                  grossAmountCents: 0,
              };

    const selectedMethodDefinition = paymentMethodOptions.find(
        (method) => method.value === selectedPaymentMethod,
    );
    const guardrail = describeSecurityGuardrail({
        hasSecurityAccount,
        loanBalance,
        maxPayable: availableSecurityAmount,
        minimumBalance: protectedReserve,
        requestedAmount,
    });
    const exceedsAvailableSecurity =
        requestedAmount !== null && requestedAmount > availableSecurityAmount;

    const submitting = paymentForm.processing || checkoutLoading;
    const canSubmitLoanSecurity =
        Boolean(loanNumber) &&
        hasSecurityAccount &&
        availableSecurityAmount > 0 &&
        loanBalance > 0 &&
        requestedAmount !== null &&
        !exceedsAvailableSecurity &&
        !submitting;
    const canSubmitPaymongo =
        Boolean(loanNumber) &&
        requestedAmountCents !== null &&
        loanBalance > 0 &&
        !submitting;
    const canSubmit = isPaymongoMethod(selectedPaymentMethod)
        ? canSubmitPaymongo
        : canSubmitLoanSecurity;

    const amountError = isPaymongoMethod(selectedPaymentMethod)
        ? checkoutFieldErrors.amount
        : paymentForm.errors.amount ??
          (exceedsAvailableSecurity
              ? `Exceeds available security. Maximum available while preserving reserve is ${formatCurrency(availableSecurityAmount)}.`
              : undefined);

    const setAmount = (value: string) => {
        paymentForm.setData('amount', value);
        paymentForm.clearErrors('amount');
        setCheckoutError(null);
        setCheckoutFieldErrors((current) => ({
            ...current,
            amount: undefined,
        }));
    };
    const useAvailableSecurityAmount = () => {
        if (availableSecurityAmount <= 0) {
            return;
        }

        setAmount(availableSecurityAmount.toFixed(2));
    };

    useEffect(() => {
        return router.on('flash', (event) => {
            const status = event.detail.flash.status;

            if (typeof status === 'string' && status.trim() !== '') {
                showSuccessToast(status);
            }
        });
    }, []);

    const submitLoanSecurityPayment = () => {
        if (!loanNumber) {
            return;
        }

        if (requestedAmount === null) {
            paymentForm.setError('amount', 'Enter a valid payment amount.');

            return;
        }

        if (requestedAmount > availableSecurityAmount) {
            paymentForm.setError(
                'amount',
                `Exceeds available security. Maximum available while preserving reserve is ${formatCurrency(availableSecurityAmount)}.`,
            );

            return;
        }

        paymentForm.submit(storeLoanSecurityPayment({ loanNumber }), {
            preserveScroll: true,
            onSuccess: () => {
                paymentForm.setData('amount', '');
                paymentForm.clearErrors('amount');
            },
        });
    };

    const submitPaymongoPayment = async (method: PaymongoMethod) => {
        if (!loanNumber) {
            return;
        }

        if (requestedAmountCents === null) {
            setCheckoutFieldErrors({
                amount: 'Enter a valid payment amount.',
            });

            return;
        }

        if (
            outstandingBalanceCents !== null &&
            requestedAmountCents > outstandingBalanceCents
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
                    amount: paymentForm.data.amount,
                    payment_method: method,
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

    const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        setCheckoutError(null);
        setCheckoutFieldErrors({});

        if (isPaymongoMethod(selectedPaymentMethod)) {
            await submitPaymongoPayment(selectedPaymentMethod);
            return;
        }

        submitLoanSecurityPayment();
    };

    return (
        <SurfaceCard variant="default" padding="md" className="space-y-5">
            <SectionHeader
                title="Pay Loan"
                description="Choose a payment method, review the breakdown, and submit the payment."
                actions={
                    <div className="rounded-xl border border-border/40 bg-background px-3 py-2 text-right">
                        <p className="text-xs text-muted-foreground">
                            Outstanding balance
                        </p>
                        <p className="text-sm font-semibold tabular-nums">
                            {formatCurrency(loanBalance)}
                        </p>
                    </div>
                }
                titleClassName="text-base font-semibold"
            />

            <form
                className="grid gap-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(280px,0.8fr)]"
                onSubmit={handleSubmit}
            >
                <div className="space-y-5">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="loan-payment-amount">Amount</Label>
                            <Input
                                id="loan-payment-amount"
                                type="number"
                                min="0.01"
                                max={loanBalance > 0 ? loanBalance : undefined}
                                step="0.01"
                                inputMode="decimal"
                                value={paymentForm.data.amount}
                                aria-invalid={amountError ? true : undefined}
                                disabled={submitting || loanBalance <= 0}
                                onChange={(event) =>
                                    setAmount(event.target.value)
                                }
                            />
                            <FieldMessage
                                error={amountError}
                                hint={
                                    selectedPaymentMethod === 'loan_security'
                                        ? `Available now: ${formatCurrency(availableSecurityAmount)} | Outstanding loan: ${formatCurrency(loanBalance)}`
                                        : `Maximum ${formatCurrency(loanBalance)}`
                                }
                                lines={2}
                                reserveSpace={false}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="loan-payment-method">
                                Payment Method
                            </Label>
                            <Select
                                value={selectedPaymentMethod}
                                onValueChange={(value) => {
                                    setSelectedPaymentMethod(
                                        value as UnifiedPaymentMethod,
                                    );
                                    paymentForm.clearErrors('amount');
                                    setCheckoutError(null);
                                    setCheckoutFieldErrors({});
                                }}
                            >
                                <SelectTrigger
                                    id="loan-payment-method"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Choose a payment method" />
                                </SelectTrigger>
                                <SelectContent>
                                    {paymentMethodOptions.map((method) => (
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
                                hint={selectedMethodDefinition?.helper}
                                reserveSpace={false}
                            />
                        </div>
                    </div>

                    {checkoutError && isPaymongoMethod(selectedPaymentMethod) ? (
                        <Alert variant="destructive">
                            <AlertTitle>Checkout unavailable</AlertTitle>
                            <AlertDescription>{checkoutError}</AlertDescription>
                        </Alert>
                    ) : null}

                    {selectedPaymentMethod === 'loan_security' ? (
                        <div className="space-y-3 rounded-xl border border-border/60 bg-muted/35 p-3">
                            <Alert className="border-border/60 bg-background/70">
                                <ShieldCheck className="size-4" />
                                <AlertTitle>{guardrail.title}</AlertTitle>
                                <AlertDescription>
                                    {guardrail.description}
                                </AlertDescription>
                            </Alert>

                            {exceedsAvailableSecurity ? (
                                <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/60 bg-background/80 px-3 py-2">
                                    <p className="text-xs text-muted-foreground">
                                        Maximum available while preserving
                                        reserve:{' '}
                                        {formatCurrency(availableSecurityAmount)}
                                    </p>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={useAvailableSecurityAmount}
                                    >
                                        Use available amount
                                    </Button>
                                </div>
                            ) : null}

                            <div className="grid gap-x-5 gap-y-2 sm:grid-cols-2">
                                <div className={securityDetailsRowClassName}>
                                    <span className="text-muted-foreground">
                                        Current security balance
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {formatCurrency(currentSecurityBalance)}
                                    </span>
                                </div>
                                <div className={securityDetailsRowClassName}>
                                    <span className="text-muted-foreground">
                                        Protected reserve
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {formatCurrency(protectedReserve)}
                                    </span>
                                </div>
                                <div className={securityDetailsRowClassName}>
                                    <span className="text-muted-foreground">
                                        Available amount
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {formatCurrency(availableSecurityAmount)}
                                    </span>
                                </div>
                                <div className={securityDetailsRowClassName}>
                                    <span className="text-muted-foreground">
                                        Security after payment
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {formatCurrency(securityAfterPayment)}
                                    </span>
                                </div>
                                <div
                                    className={`${securityDetailsRowClassName} sm:col-span-2`}
                                >
                                    <span className="text-muted-foreground">
                                        Loan after payment
                                    </span>
                                    <span className="font-medium tabular-nums">
                                        {formatCurrency(loanAfterPayment)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <Alert className="border-border/60 bg-muted/35">
                            <ExternalLink className="size-4" />
                            <AlertTitle>
                                {loanBalance > 0
                                    ? 'Hosted checkout'
                                    : 'Loan already settled'}
                            </AlertTitle>
                            <AlertDescription>
                                {loanBalance > 0
                                    ? 'After you continue, PayMongo will handle the selected payment flow and return you to this page.'
                                    : 'PayMongo checkout is unavailable because this loan has no remaining balance.'}
                            </AlertDescription>
                        </Alert>
                    )}
                </div>

                <div className="flex h-full flex-col space-y-4 rounded-xl border border-border/40 bg-background p-4 lg:min-h-[252px]">
                    <p className="text-sm font-semibold">Payment Summary</p>

                    {selectedPaymentMethod === 'loan_security' ? (
                        <div className="flex-1 space-y-3">
                            <div className={summaryRowClassName}>
                                <span className="text-muted-foreground">
                                    Requested amount
                                </span>
                                <span className="font-medium tabular-nums">
                                    {formatCurrency(requestedAmount ?? 0)}
                                </span>
                            </div>
                            <div className={summaryRowClassName}>
                                <span className="text-muted-foreground">
                                    Amount applied
                                </span>
                                <span className="font-medium tabular-nums">
                                    {formatCurrency(amountApplied)}
                                </span>
                            </div>
                            <div className={summaryRowClassName}>
                                <span className="text-muted-foreground">
                                    Fee
                                </span>
                                <span className="font-medium tabular-nums">
                                    {formatCurrency(0)}
                                </span>
                            </div>
                            <div className={summaryRowClassName}>
                                <span className="font-semibold">
                                    Total applied
                                </span>
                                <span className="font-semibold tabular-nums">
                                    {formatCurrency(amountApplied)}
                                </span>
                            </div>
                        </div>
                    ) : (
                        <div className="flex-1 space-y-3">
                            <div className={summaryRowClassName}>
                                <span className="text-muted-foreground">
                                    Loan payment
                                </span>
                                <span className="font-medium tabular-nums">
                                    {formatCurrency(
                                        paymongoEstimate.baseAmountCents / 100,
                                    )}
                                </span>
                            </div>
                            <div className={summaryRowClassName}>
                                <span className="text-muted-foreground">
                                    PayMongo fee
                                </span>
                                <span className="font-medium tabular-nums">
                                    {formatCurrency(
                                        paymongoEstimate.serviceFeeCents / 100,
                                    )}
                                </span>
                            </div>
                            <div className={summaryRowClassName}>
                                <span className="font-semibold">
                                    Total at checkout
                                </span>
                                <span className="font-semibold tabular-nums">
                                    {formatCurrency(
                                        paymongoEstimate.grossAmountCents / 100,
                                    )}
                                </span>
                            </div>
                        </div>
                    )}

                    <Button
                        type="submit"
                        size="lg"
                        className="mt-auto w-full"
                        disabled={!canSubmit}
                    >
                        {submitting ? (
                            <LoaderCircle className="size-4 animate-spin" />
                        ) : selectedPaymentMethod === 'loan_security' ? (
                            <Wallet className="size-4" />
                        ) : (
                            <ExternalLink className="size-4" />
                        )}
                        {submitting
                            ? selectedPaymentMethod === 'loan_security'
                                ? 'Applying payment...'
                                : 'Starting checkout...'
                            : selectedPaymentMethod === 'loan_security'
                              ? 'Apply from security'
                              : 'Continue to PayMongo'}
                    </Button>
                </div>
            </form>
        </SurfaceCard>
    );
}
