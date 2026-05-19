import { router, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    CircleAlert,
    Coins,
    PiggyBank,
    ShieldCheck,
    Wallet,
} from 'lucide-react';
import { useEffect } from 'react';
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
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { formatCurrency } from '@/lib/formatters';
import { showSuccessToast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { security as loanSecurityPayment } from '@/routes/client/loan-payments';
import type { MemberLoanSecurityPaymentSummary } from '@/types/admin';

type MemberLoanSecurityPaymentCardProps = {
    loanNumber: string | number | null;
    loanBalance: number;
    securityPayment: MemberLoanSecurityPaymentSummary;
};

const quickFillAmount = (amount: number): string =>
    amount > 0 ? amount.toFixed(2) : '';

type MetricCardProps = {
    title: string;
    value: number;
};

function MetricCard({ title, value }: MetricCardProps) {
    return (
        <Card className="rounded-2xl border-border/50 bg-background/82 shadow-none backdrop-blur-sm">
            <CardContent className="space-y-2 px-5 py-4">
                <p className="text-xs font-medium tracking-[0.16em] text-muted-foreground uppercase">
                    {title}
                </p>
                <p className="text-2xl font-semibold tabular-nums">
                    {formatCurrency(value)}
                </p>
            </CardContent>
        </Card>
    );
}

type PreviewRowProps = {
    label: string;
    value: number;
};

function PreviewRow({ label, value }: PreviewRowProps) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium tabular-nums">
                {formatCurrency(value)}
            </span>
        </div>
    );
}

export function MemberLoanSecurityPaymentCard({
    loanNumber,
    loanBalance,
    securityPayment,
}: MemberLoanSecurityPaymentCardProps) {
    const paymentForm = useForm({
        amount: '',
    });

    const hasSecurityAccount = Boolean(securityPayment.svnumber);
    const currentBalance = Number(securityPayment.currentBalance ?? 0);
    const minimumBalance = Number(securityPayment.minimumBalance ?? 0);
    const maxPayable = Number(securityPayment.maxPayable ?? 0);
    const suggestedAmount = Math.min(maxPayable, Math.max(loanBalance, 0));
    const requestedAmount = Number(paymentForm.data.amount);
    const hasRequestedAmount =
        paymentForm.data.amount.trim() !== '' &&
        Number.isFinite(requestedAmount);
    const positiveRequestedAmount =
        hasRequestedAmount && requestedAmount > 0 ? requestedAmount : null;
    const previewAppliedAmount =
        positiveRequestedAmount !== null
            ? Math.min(positiveRequestedAmount, maxPayable, loanBalance)
            : 0;
    const projectedSecurityBalance = Math.max(
        0,
        currentBalance - previewAppliedAmount,
    );
    const projectedLoanBalance = Math.max(
        0,
        loanBalance - previewAppliedAmount,
    );
    const canSubmit =
        Boolean(loanNumber) &&
        hasSecurityAccount &&
        maxPayable > 0 &&
        loanBalance > 0 &&
        positiveRequestedAmount !== null &&
        !paymentForm.processing;

    useEffect(() => {
        return router.on('flash', (event) => {
            const status = event.detail.flash.status;

            if (typeof status === 'string' && status.trim() !== '') {
                showSuccessToast(status);
            }
        });
    }, []);

    const setAmount = (amount: string) => {
        paymentForm.setData('amount', amount);
        paymentForm.clearErrors('amount');
    };

    const submitPayment = () => {
        if (!loanNumber) {
            return;
        }

        paymentForm.submit(loanSecurityPayment({ loanNumber }), {
            preserveScroll: true,
            onSuccess: () => {
                paymentForm.reset('amount');
                paymentForm.clearErrors('amount');
            },
        });
    };

    const previewNote = (() => {
        if (!hasSecurityAccount) {
            return {
                badgeVariant: 'outline' as const,
                tone: 'text-muted-foreground',
                text: 'No loan security account is available for this loan payment.',
                title: 'Security account unavailable',
            };
        }

        if (loanBalance <= 0) {
            return {
                badgeVariant: 'outline' as const,
                tone: 'text-muted-foreground',
                text: 'This loan is already fully paid.',
                title: 'Loan already paid',
            };
        }

        if (maxPayable <= 0) {
            return {
                badgeVariant: 'outline' as const,
                tone: 'text-muted-foreground',
                text: `Your current loan security is fully protected by the ${formatCurrency(minimumBalance)} reserve.`,
                title: 'Reserve fully protected',
            };
        }

        if (positiveRequestedAmount === null) {
            return {
                badgeVariant: 'outline' as const,
                tone: 'text-muted-foreground',
                text: `Enter an amount up to ${formatCurrency(maxPayable)} to preview the payment effect.`,
                title: 'Awaiting amount',
            };
        }

        if (positiveRequestedAmount > maxPayable) {
            return {
                badgeVariant: 'secondary' as const,
                tone: 'text-amber-700 dark:text-amber-300',
                text: `This request exceeds the currently available ${formatCurrency(maxPayable)} while preserving the reserve.`,
                title: 'Exceeds available security',
            };
        }

        if (positiveRequestedAmount > loanBalance) {
            return {
                badgeVariant: 'secondary' as const,
                tone: 'text-sky-700 dark:text-sky-300',
                text: `Only ${formatCurrency(loanBalance)} will be applied because that is the remaining loan balance.`,
                title: 'Capped by loan balance',
            };
        }

        return {
            badgeVariant: 'default' as const,
            tone: 'text-emerald-700 dark:text-emerald-300',
            text: `This payment will apply ${formatCurrency(previewAppliedAmount)} immediately and keep your reserve intact.`,
            title: 'Ready to submit',
        };
    })();

    return (
        <Card className="relative overflow-hidden border-primary/20 bg-[linear-gradient(135deg,rgba(27,94,32,0.06),rgba(255,255,255,0.88)_38%,rgba(15,23,42,0.02))] dark:bg-[linear-gradient(135deg,rgba(74,222,128,0.10),rgba(15,23,42,0.88)_42%,rgba(15,23,42,0.96))]">
            <div className="pointer-events-none absolute inset-y-0 right-0 w-48 bg-[radial-gradient(circle_at_top,rgba(34,197,94,0.16),transparent_62%)]" />

            <CardHeader className="relative gap-5 px-6 pt-6 sm:px-7 sm:pt-7 lg:px-8 lg:pt-8">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div className="space-y-3">
                        <Badge className="w-fit gap-2 rounded-full border-primary/15 bg-primary/10 px-3 py-1 text-[11px] font-semibold tracking-[0.18em] text-primary uppercase shadow-none hover:bg-primary/10">
                            <ShieldCheck className="size-3.5" />
                            Security-Powered Payment
                        </Badge>
                        <div className="space-y-1">
                            <CardTitle className="text-xl tracking-tight sm:text-2xl">
                                Apply loan security without touching the last{' '}
                                {formatCurrency(minimumBalance)}
                            </CardTitle>
                            <CardDescription className="max-w-3xl text-sm leading-6">
                                Payments are applied instantly to the loan,
                                capped by the remaining loan balance, and never
                                reduce loan security below the protected
                                reserve.
                            </CardDescription>
                        </div>
                    </div>

                    <Card className="w-full max-w-xs rounded-2xl border-white/60 bg-white/80 shadow-none backdrop-blur-sm dark:border-white/10 dark:bg-white/5">
                        <CardContent className="flex items-center gap-3 px-4 py-4">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <PiggyBank className="size-4" />
                            </div>
                            <div className="space-y-1">
                                <p className="text-[11px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                    Security Account
                                </p>
                                <p className="text-sm font-medium">
                                    {securityPayment.svnumber ?? 'Unavailable'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </CardHeader>

            <CardContent className="relative space-y-6 px-6 pb-6 sm:px-7 sm:pb-7 lg:px-8 lg:pb-8">
                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <MetricCard
                        title="Current Security"
                        value={currentBalance}
                    />
                    <MetricCard
                        title="Protected Reserve"
                        value={minimumBalance}
                    />
                    <MetricCard title="Available Now" value={maxPayable} />
                    <MetricCard
                        title="Suggested Payment"
                        value={suggestedAmount}
                    />
                </div>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
                    <Card className="rounded-3xl border-border/40 bg-background/82 shadow-sm backdrop-blur-sm">
                        <CardHeader className="gap-4 pb-0">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div className="space-y-1">
                                    <CardTitle className="text-base">
                                        Payment amount
                                    </CardTitle>
                                    <CardDescription className="leading-6">
                                        Choose how much of your available loan
                                        security to apply.
                                    </CardDescription>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={suggestedAmount <= 0}
                                        onClick={() =>
                                            setAmount(
                                                quickFillAmount(
                                                    suggestedAmount,
                                                ),
                                            )
                                        }
                                    >
                                        Use suggested
                                    </Button>
                                    {maxPayable > suggestedAmount ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            disabled={maxPayable <= 0}
                                            onClick={() =>
                                                setAmount(
                                                    quickFillAmount(maxPayable),
                                                )
                                            }
                                        >
                                            Use max available
                                        </Button>
                                    ) : null}
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent className="pt-0">
                            <form
                                className="flex flex-col gap-5"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    submitPayment();
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="loan-security-payment-amount">
                                        Amount to apply
                                    </Label>
                                    <Input
                                        id="loan-security-payment-amount"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        inputMode="decimal"
                                        placeholder="0.00"
                                        value={paymentForm.data.amount}
                                        onChange={(event) =>
                                            setAmount(event.target.value)
                                        }
                                        aria-invalid={
                                            paymentForm.errors.amount
                                                ? 'true'
                                                : 'false'
                                        }
                                        disabled={
                                            paymentForm.processing ||
                                            !hasSecurityAccount ||
                                            loanBalance <= 0
                                        }
                                        className="h-12 rounded-2xl bg-background/90 text-base"
                                    />
                                    <FieldMessage
                                        error={paymentForm.errors.amount}
                                        hint={`Available now: ${formatCurrency(maxPayable)} | Outstanding loan: ${formatCurrency(loanBalance)}`}
                                        lines={2}
                                        reserveSpace={false}
                                    />
                                </div>

                                <Separator className="bg-border/50" />

                                <div className="grid gap-3 sm:grid-cols-2">
                                    <Card className="rounded-2xl border-border/40 bg-card/45 shadow-none">
                                        <CardContent className="flex items-center gap-3 px-4 py-4">
                                            <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                                <Coins className="size-4" />
                                            </div>
                                            <div className="space-y-1">
                                                <p className="text-xs font-medium tracking-[0.12em] text-muted-foreground uppercase">
                                                    Amount Applied
                                                </p>
                                                <p className="text-lg font-semibold tabular-nums">
                                                    {formatCurrency(
                                                        previewAppliedAmount,
                                                    )}
                                                </p>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <Card className="rounded-2xl border-border/40 bg-card/45 shadow-none">
                                        <CardContent className="flex items-center gap-3 px-4 py-4">
                                            <div className="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                                <ShieldCheck className="size-4" />
                                            </div>
                                            <div className="space-y-1">
                                                <p className="text-xs font-medium tracking-[0.12em] text-muted-foreground uppercase">
                                                    Security After
                                                </p>
                                                <p className="text-lg font-semibold tabular-nums">
                                                    {formatCurrency(
                                                        projectedSecurityBalance,
                                                    )}
                                                </p>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>

                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <p className="text-sm leading-6 text-muted-foreground">
                                        If you request more than the remaining
                                        loan balance, only the outstanding
                                        balance is applied.
                                    </p>
                                    <Button
                                        type="submit"
                                        size="lg"
                                        disabled={!canSubmit}
                                        className="min-w-56 rounded-2xl"
                                    >
                                        {paymentForm.processing ? (
                                            <Spinner className="size-4" />
                                        ) : (
                                            <Wallet className="size-4" />
                                        )}
                                        Apply from security
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <Card className="rounded-3xl border-border/40 bg-slate-950/[0.03] shadow-sm backdrop-blur-sm dark:bg-white/[0.03]">
                        <CardHeader className="gap-3">
                            <div className="flex items-start justify-between gap-3">
                                <div className="space-y-1">
                                    <CardTitle className="text-base">
                                        Payment preview
                                    </CardTitle>
                                    <CardDescription className="leading-6">
                                        Review the immediate effect before you
                                        submit.
                                    </CardDescription>
                                </div>
                                <ArrowRight className="mt-1 size-4 text-muted-foreground" />
                            </div>
                            <Badge
                                variant={previewNote.badgeVariant}
                                className={cn(
                                    'w-fit gap-2 rounded-full px-3 py-1 text-[11px] font-semibold tracking-[0.16em] uppercase shadow-none',
                                    previewNote.badgeVariant === 'default'
                                        ? 'bg-emerald-600 text-white hover:bg-emerald-600'
                                        : null,
                                )}
                            >
                                {previewNote.title}
                            </Badge>
                        </CardHeader>

                        <CardContent className="space-y-4">
                            <div className="space-y-3 text-sm">
                                <PreviewRow
                                    label="Requested amount"
                                    value={positiveRequestedAmount ?? 0}
                                />
                                <PreviewRow
                                    label="Amount to apply"
                                    value={previewAppliedAmount}
                                />
                                <PreviewRow
                                    label="Security after payment"
                                    value={projectedSecurityBalance}
                                />
                                <PreviewRow
                                    label="Loan after payment"
                                    value={projectedLoanBalance}
                                />
                            </div>

                            <Separator className="bg-border/50" />

                            <Alert className="border-dashed border-border/60 bg-background/70">
                                <CircleAlert className="text-muted-foreground" />
                                <AlertTitle>Guardrail check</AlertTitle>
                                <AlertDescription
                                    className={cn(
                                        'leading-6',
                                        previewNote.tone,
                                    )}
                                >
                                    {previewNote.text}
                                </AlertDescription>
                            </Alert>
                        </CardContent>
                    </Card>
                </div>
            </CardContent>
        </Card>
    );
}
