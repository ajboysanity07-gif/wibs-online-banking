import { router } from '@inertiajs/react';
import { format } from 'date-fns';
import { enUS } from 'date-fns/locale';
import { Check, Clock, Info } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatCurrency } from '@/lib/formatters';
import {
    create as loanRequestCreate,
    show as loanRequestShow,
} from '@/routes/client/loan-requests';

const chevron =
    '[clip-path:polygon(0_0,calc(100%_-_12px)_0,100%_50%,calc(100%_-_12px)_100%,0_100%,12px_50%)]';

interface LoanRequestConfirmationProps {
    loanRequestId: number;
    reference: string;
    requestedAmount: number;
    requestedTerm: number;
    submittedDate: string | null;
}

const timelineSteps = [
    { label: 'Submitted', active: true },
    { label: 'Processing', active: false },
    { label: 'Approval', active: false },
    { label: 'Release', active: false },
];

const nextSteps = [
    {
        title: 'Initial review',
        description:
            'A loan processor reviews your application and supporting documents',
    },
    {
        title: 'Verification call',
        description:
            'We may call you or your co-makers if we need more information',
    },
    {
        title: 'Document signing',
        description:
            'You and your co-makers sign the documents in person when the loan is released',
    },
];

export function LoanRequestConfirmation({
    loanRequestId,
    reference,
    requestedAmount,
    requestedTerm,
    submittedDate,
}: LoanRequestConfirmationProps) {
    const handleTrackApplication = () => {
        // A fresh visit no longer carries the one-time submit flash, so the
        // server renders the tracking view. Replace the history entry so Back
        // doesn't return to the success screen.
        router.visit(loanRequestShow(loanRequestId).url, { replace: true });
    };

    const handleApplyForAnother = () => {
        router.visit(loanRequestCreate().url);
    };

    return (
        <section className="mx-auto mt-6 w-full max-w-[680px] px-4 sm:px-6">
            <div className="flex flex-col gap-4">
                {/* 1. Success Header Card */}
                <Card>
                    <CardContent className="flex flex-col items-center gap-4 p-8 text-center">
                        <div className="grid size-16 place-items-center rounded-full bg-secondary">
                            <Check
                                className="size-8 text-secondary-foreground"
                                strokeWidth={3}
                                aria-hidden="true"
                            />
                        </div>
                        <div>
                            <h2 className="mb-1 text-2xl font-semibold">
                                Application submitted successfully
                            </h2>
                            <p className="text-[15px] text-muted-foreground">
                                We've received your loan application
                            </p>
                        </div>
                    </CardContent>
                </Card>

                {/* 2. Application Details Card */}
                <Card>
                    <CardContent className="space-y-3 p-5 px-6">
                        <div className="mb-3 text-[13px] font-semibold tracking-wide text-muted-foreground uppercase">
                            Application details
                        </div>
                        <div className="flex flex-col gap-3">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">
                                    Reference number
                                </span>
                                <span className="text-[15px] font-semibold">
                                    {reference}
                                </span>
                            </div>
                            <div className="h-px bg-border" />
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">
                                    Loan amount
                                </span>
                                <span className="text-[15px] font-semibold">
                                    {formatCurrency(requestedAmount)}
                                </span>
                            </div>
                            <div className="h-px bg-border" />
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">
                                    Payment term
                                </span>
                                <span className="text-[15px] font-semibold">
                                    {requestedTerm} month
                                    {requestedTerm > 1 ? 's' : ''}
                                </span>
                            </div>
                            <div className="h-px bg-border" />
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">
                                    Submitted on
                                </span>
                                <span className="text-[15px] font-semibold">
                                    {submittedDate
                                        ? format(
                                              new Date(submittedDate),
                                              'MMMM d, yyyy',
                                              { locale: enUS },
                                          )
                                        : '—'}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* 3. Status Timeline Card */}
                <Card>
                    <CardContent className="space-y-4 p-5 px-6">
                        <div className="text-[13px] font-semibold tracking-wide text-muted-foreground uppercase">
                            Application status
                        </div>
                        <ol
                            aria-label="Application status"
                            className="scrollbar-hide flex gap-[3px] overflow-x-auto"
                        >
                            {timelineSteps.map(({ label, active }) => (
                                <li
                                    key={label}
                                    aria-current={active ? 'step' : undefined}
                                    className={[
                                        'flex-[1_0_118px] px-[18px] py-[9px] text-center text-[13px] whitespace-nowrap',
                                        chevron,
                                        active
                                            ? 'bg-secondary font-semibold text-secondary-foreground'
                                            : 'bg-muted font-medium text-muted-foreground',
                                    ].join(' ')}
                                >
                                    {label}
                                </li>
                            ))}
                        </ol>
                        <div className="flex items-center gap-2 rounded-lg bg-muted p-3 text-sm text-muted-foreground">
                            <Clock
                                className="size-4 flex-shrink-0"
                                aria-hidden="true"
                            />
                            <span>Expected review within 3 working days</span>
                        </div>
                    </CardContent>
                </Card>

                {/* 4. What Happens Next Card */}
                <Card>
                    <CardContent className="space-y-4 p-5 px-6">
                        <h3 className="text-base font-semibold">
                            What happens next
                        </h3>
                        <ol className="flex flex-col">
                            {nextSteps.map((step, index) => {
                                const active = index === 0;

                                return (
                                    <li
                                        key={step.title}
                                        className="flex gap-3 border-t border-border py-2.5 first:border-t-0"
                                    >
                                        <div
                                            className={[
                                                'grid size-8 flex-shrink-0 place-items-center rounded-full text-sm font-semibold',
                                                active
                                                    ? 'bg-secondary text-secondary-foreground'
                                                    : 'bg-muted text-muted-foreground',
                                            ].join(' ')}
                                        >
                                            {index + 1}
                                        </div>
                                        <div>
                                            <div className="mb-0.5 font-medium">
                                                {step.title}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {step.description}
                                            </div>
                                        </div>
                                    </li>
                                );
                            })}
                        </ol>
                    </CardContent>
                </Card>

                {/* 5. Important Reminder Banner */}
                <Alert className="border-secondary bg-secondary text-secondary-foreground">
                    <Info
                        className="size-5! text-secondary-foreground"
                        aria-hidden="true"
                    />
                    <AlertTitle className="font-semibold text-secondary-foreground">
                        Keep your phone accessible
                    </AlertTitle>
                    <AlertDescription className="text-sm text-secondary-foreground opacity-90">
                        We'll send SMS and email updates about your application.
                        Make sure to check your notifications regularly.
                    </AlertDescription>
                </Alert>

                {/* 6. Action Buttons */}
                <div className="flex flex-col gap-3">
                    <Button
                        onClick={handleTrackApplication}
                        className="min-h-[44px] w-full"
                    >
                        Track my application
                    </Button>
                    <Button
                        onClick={handleApplyForAnother}
                        variant="outline"
                        className="min-h-[44px] w-full"
                    >
                        Apply for another loan
                    </Button>
                </div>
            </div>
        </section>
    );
}
