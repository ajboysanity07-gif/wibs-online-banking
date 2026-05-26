import { Head, useForm } from '@inertiajs/react';
import { CircleAlert, CircleCheckBig, Link2Off } from 'lucide-react';
import { useState } from 'react';
import SignaturePadField from '@/components/signature-pad-field';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { formatCurrency } from '@/lib/formatters';

type PublicSigningStatus = 'ready' | 'invalid' | 'revoked' | 'expired' | 'signed';

type SigningPayload = {
    borrower_name: string;
    loan_type: string | null;
    requested_amount: number | string | null;
    requested_term: number | string | null;
    co_maker_name: string;
    contact_number: string | null;
    address: string | null;
    employment_type: string | null;
    employer_business_name: string | null;
    employer_business_address: string | null;
    current_position: string | null;
    nature_of_business: string | null;
    role_label: string;
};

type Props = {
    status: PublicSigningStatus;
    signing: SigningPayload | null;
    submitUrl: string;
    recentlySigned: boolean;
};

const displayValue = (value?: string | number | null): string => {
    if (value === null || value === undefined) {
        return '--';
    }

    const normalized = `${value}`.trim();

    return normalized !== '' ? normalized : '--';
};

const displayAmount = (value?: string | number | null): string => {
    if (value === null || value === undefined || `${value}`.trim() === '') {
        return '--';
    }

    const numericValue = Number(value);

    return Number.isFinite(numericValue)
        ? formatCurrency(numericValue)
        : `${value}`;
};

const displayTerm = (value?: string | number | null): string => {
    if (value === null || value === undefined || `${value}`.trim() === '') {
        return '--';
    }

    return `${value} months`;
};

const DetailItem = ({
    label,
    value,
}: {
    label: string;
    value: string;
}) => (
    <div className="space-y-1 rounded-xl border border-border/50 bg-muted/10 p-3">
        <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
            {label}
        </p>
        <p className="text-sm font-medium text-foreground break-words">
            {value}
        </p>
    </div>
);

const statusCopy: Record<
    Exclude<PublicSigningStatus, 'ready'>,
    { title: string; description: string }
> = {
    invalid: {
        title: 'Invalid signing link',
        description:
            'This signing link is not valid. Please contact the borrower or cooperative office for a new link.',
    },
    revoked: {
        title: 'Signing link revoked',
        description:
            'This signing link is no longer active. Please contact the borrower or cooperative office for a replacement link.',
    },
    expired: {
        title: 'Signing link expired',
        description:
            'This signing link has expired. Please contact the borrower or cooperative office for a new link.',
    },
    signed: {
        title: 'Signature already completed',
        description:
            'This co-maker signature link has already been used and cannot be submitted again.',
    },
};

export default function PublicLoanRequestCoMakerSignaturePage({
    status,
    signing,
    submitUrl,
    recentlySigned,
}: Props) {
    const [showIncorrectInfoNotice, setShowIncorrectInfoNotice] =
        useState(false);
    const form = useForm({
        consent: false,
        signature_data: '',
    });
    const showReadyState = status === 'ready' && signing !== null;
    const showSignedState = status === 'signed';
    const unavailableStateCopy =
        !showReadyState && status !== 'ready'
            ? statusCopy[showSignedState ? 'signed' : status]
            : null;
    const linkError = form.errors[
        'link' as keyof typeof form.errors
    ] as string | undefined;

    const submitSignature = () => {
        form.post(submitUrl, {
            preserveScroll: true,
        });
    };

    return (
        <div className="min-h-svh bg-[radial-gradient(circle_at_top,_rgba(14,116,144,0.12),_transparent_45%),linear-gradient(180deg,_rgba(248,250,252,1)_0%,_rgba(248,250,252,0.96)_40%,_rgba(241,245,249,1)_100%)] px-4 py-8 sm:px-6 lg:py-12">
            <Head title="Co-maker Signature" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <div className="space-y-2 text-center">
                    <p className="text-xs font-semibold tracking-[0.24em] text-muted-foreground uppercase">
                        Loan Request
                    </p>
                    <h1 className="text-3xl font-semibold tracking-tight text-foreground">
                        Co-maker Signature
                    </h1>
                    <p className="mx-auto max-w-2xl text-sm text-muted-foreground">
                        Review the proposed co-maker details, confirm your
                        consent, and sign to confirm this loan request.
                    </p>
                </div>

                {recentlySigned ? (
                    <Alert className="border-emerald-500/30 bg-emerald-500/10 text-emerald-900 dark:text-emerald-100">
                        <CircleCheckBig className="size-4" />
                        <AlertTitle>Signature submitted</AlertTitle>
                        <AlertDescription>
                            Your co-maker confirmation has been recorded
                            successfully.
                        </AlertDescription>
                    </Alert>
                ) : null}

                {!showReadyState ? (
                    <Card className="border-border/50 bg-card/90">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                {status === 'signed' ? (
                                    <CircleCheckBig className="size-5 text-emerald-600" />
                                ) : (
                                    <Link2Off className="size-5 text-rose-600" />
                                )}
                                {unavailableStateCopy?.title ??
                                    'Signing link unavailable'}
                            </CardTitle>
                            <CardDescription>
                                {unavailableStateCopy?.description ??
                                    'This signing link cannot be used.'}
                            </CardDescription>
                        </CardHeader>
                        {signing ? (
                            <CardContent className="grid gap-3 sm:grid-cols-2">
                                <DetailItem
                                    label="Borrower / Member"
                                    value={displayValue(signing.borrower_name)}
                                />
                                <DetailItem
                                    label="Co-maker"
                                    value={displayValue(signing.co_maker_name)}
                                />
                                <DetailItem
                                    label="Loan type"
                                    value={displayValue(signing.loan_type)}
                                />
                                <DetailItem
                                    label="Requested amount"
                                    value={displayAmount(
                                        signing.requested_amount,
                                    )}
                                />
                            </CardContent>
                        ) : null}
                    </Card>
                ) : null}

                {showReadyState ? (
                    <>
                        <Card className="border-border/50 bg-card/90">
                            <CardHeader>
                                <CardTitle>Loan request summary</CardTitle>
                                <CardDescription>
                                    Only the minimum information needed for
                                    co-maker review is shown here.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 sm:grid-cols-2">
                                <DetailItem
                                    label="Borrower / Member"
                                    value={displayValue(signing.borrower_name)}
                                />
                                <DetailItem
                                    label="Role"
                                    value={displayValue(signing.role_label)}
                                />
                                <DetailItem
                                    label="Loan type"
                                    value={displayValue(signing.loan_type)}
                                />
                                <DetailItem
                                    label="Requested amount"
                                    value={displayAmount(
                                        signing.requested_amount,
                                    )}
                                />
                                <DetailItem
                                    label="Requested term"
                                    value={displayTerm(signing.requested_term)}
                                />
                                <DetailItem
                                    label="Co-maker name"
                                    value={displayValue(signing.co_maker_name)}
                                />
                            </CardContent>
                        </Card>

                        <Card className="border-border/50 bg-card/90">
                            <CardHeader>
                                <CardTitle>Proposed co-maker details</CardTitle>
                                <CardDescription>
                                    Review the information entered by the
                                    borrower before signing.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 sm:grid-cols-2">
                                <DetailItem
                                    label="Contact number"
                                    value={displayValue(signing.contact_number)}
                                />
                                <DetailItem
                                    label="Address"
                                    value={displayValue(signing.address)}
                                />
                                <DetailItem
                                    label="Employment type"
                                    value={displayValue(signing.employment_type)}
                                />
                                <DetailItem
                                    label="Employer / Business"
                                    value={displayValue(
                                        signing.employer_business_name,
                                    )}
                                />
                                <DetailItem
                                    label="Employer / Business address"
                                    value={displayValue(
                                        signing.employer_business_address,
                                    )}
                                />
                                <DetailItem
                                    label="Position / Nature of business"
                                    value={`${displayValue(signing.current_position)} / ${displayValue(signing.nature_of_business)}`}
                                />
                            </CardContent>
                        </Card>

                        <Alert className="border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-100">
                            <CircleAlert className="size-4" />
                            <AlertTitle>Before you sign</AlertTitle>
                            <AlertDescription>
                                If any information is incorrect, please contact
                                the borrower or cooperative office before
                                signing.
                            </AlertDescription>
                        </Alert>

                        {showIncorrectInfoNotice ? (
                            <Alert>
                                <CircleAlert className="size-4" />
                                <AlertTitle>Incorrect information noted</AlertTitle>
                                <AlertDescription>
                                    Do not sign until the borrower or
                                    cooperative office confirms the corrected
                                    details and provides a new link if needed.
                                </AlertDescription>
                            </Alert>
                        ) : null}

                        <Card className="border-border/50 bg-card/95">
                            <CardHeader>
                                <CardTitle>Consent and signature</CardTitle>
                                <CardDescription>
                                    Confirm the information, then sign in the
                                    box below. Only the co-maker should sign.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                <div className="rounded-xl border border-border/50 bg-muted/10 p-4">
                                    <div className="flex items-start gap-3">
                                        <Checkbox
                                            id="consent"
                                            checked={form.data.consent}
                                            onCheckedChange={(checked) =>
                                                form.setData(
                                                    'consent',
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor="consent"
                                                className="text-sm leading-relaxed"
                                            >
                                                I confirm that the information
                                                shown is correct and I agree to
                                                act as co-maker for this loan
                                                request.
                                            </Label>
                                            {form.errors.consent ? (
                                                <p className="text-sm text-destructive">
                                                    {form.errors.consent}
                                                </p>
                                            ) : null}
                                        </div>
                                    </div>
                                </div>

                                <SignaturePadField
                                    name="signature_data"
                                    label="Co-maker signature"
                                    value={form.data.signature_data}
                                    error={form.errors.signature_data}
                                    onChange={(value) =>
                                        form.setData('signature_data', value)
                                    }
                                />

                                {linkError ? (
                                    <Alert variant="destructive">
                                        <AlertTitle>
                                            Unable to complete signing
                                        </AlertTitle>
                                        <AlertDescription>
                                            {linkError}
                                        </AlertDescription>
                                    </Alert>
                                ) : null}

                                <Separator className="bg-border/40" />

                                <div className="grid gap-3">
                                    <Button
                                        type="button"
                                        className="h-12 w-full"
                                        disabled={form.processing}
                                        onClick={submitSignature}
                                    >
                                        {form.processing
                                            ? 'Submitting signature...'
                                            : 'Submit Signature'}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-12 w-full"
                                        disabled={form.processing}
                                        onClick={() =>
                                            setShowIncorrectInfoNotice(
                                                (current) => !current,
                                            )
                                        }
                                    >
                                        Information is incorrect
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </>
                ) : null}
            </div>
        </div>
    );
}
