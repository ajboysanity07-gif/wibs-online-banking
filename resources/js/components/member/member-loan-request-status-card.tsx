import { Link } from '@inertiajs/react';
import LoanRequestController from '@/actions/App/Http/Controllers/Client/LoanRequestController';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { Button } from '@/components/ui/button';
import {
    formatCurrency,
    formatDate,
    formatDateTime,
    MASKED_AMOUNT,
} from '@/lib/formatters';
import { cn } from '@/lib/utils';
import type { ActiveLoanRequestSummary } from '@/types/loan-requests';

type MemberLoanRequestStatusCardProps = {
    activeRequest: ActiveLoanRequestSummary | null;
    activeDraft: { id: number; updated_at: string | null } | null;
    hideAmounts?: boolean;
};

export function MemberLoanRequestStatusCard({
    activeRequest,
    activeDraft,
    hideAmounts = false,
}: MemberLoanRequestStatusCardProps) {
    return (
        <section
            aria-labelledby="loan-request-status-heading"
            className="rounded-xl border border-border bg-card p-5 shadow-card"
        >
            <h2
                id="loan-request-status-heading"
                className="text-base font-bold"
            >
                Loan request status
            </h2>

            {activeRequest ? (
                <div className="mt-4 flex flex-col gap-3.5">
                    <div className="flex flex-wrap items-center gap-2">
                        <LoanRequestStatusBadge status={activeRequest.status} />
                        <span className="text-xs font-semibold text-muted-foreground">
                            Step {activeRequest.step} of{' '}
                            {activeRequest.total_steps}
                        </span>
                    </div>
                    <div
                        role="progressbar"
                        aria-label="Loan request progress"
                        aria-valuenow={activeRequest.step}
                        aria-valuemin={1}
                        aria-valuemax={activeRequest.total_steps}
                        className="flex gap-1.5"
                    >
                        {Array.from({ length: activeRequest.total_steps }).map(
                            (_, index) => (
                                <span
                                    key={index}
                                    className={cn(
                                        'h-1.5 flex-1 rounded-full border border-border bg-muted',
                                        index < activeRequest.step &&
                                            'border-primary bg-primary',
                                    )}
                                />
                            ),
                        )}
                    </div>
                    <dl className="space-y-2 text-sm">
                        <div className="flex justify-between gap-3">
                            <dt className="text-muted-foreground">
                                Application
                            </dt>
                            <dd className="font-semibold">
                                {activeRequest.reference}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-3">
                            <dt className="text-muted-foreground">Amount</dt>
                            <dd className="font-semibold tabular-nums">
                                {hideAmounts
                                    ? MASKED_AMOUNT
                                    : formatCurrency(
                                          activeRequest.requested_amount ===
                                              null
                                              ? null
                                              : Number(
                                                    activeRequest.requested_amount,
                                                ),
                                      )}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-3">
                            <dt className="text-muted-foreground">Submitted</dt>
                            <dd className="font-semibold">
                                {formatDate(activeRequest.submitted_at)}
                            </dd>
                        </div>
                    </dl>
                    <Button
                        asChild
                        variant="outline"
                        className="justify-center"
                    >
                        <Link
                            href={
                                LoanRequestController.show(activeRequest.id).url
                            }
                        >
                            Track request
                        </Link>
                    </Button>
                    {activeRequest.more_count > 0 ? (
                        <Link
                            href={LoanRequestController.index().url}
                            className="text-center text-sm font-semibold text-primary hover:underline"
                        >
                            +{activeRequest.more_count} more{' '}
                            {activeRequest.more_count === 1
                                ? 'request'
                                : 'requests'}{' '}
                            · View all
                        </Link>
                    ) : null}
                </div>
            ) : activeDraft ? (
                <div className="mt-4 flex flex-col gap-3.5">
                    <p className="text-sm text-muted-foreground">
                        You have a loan request draft
                        {activeDraft.updated_at
                            ? ` last saved ${formatDateTime(activeDraft.updated_at)}`
                            : ''}
                        .
                    </p>
                    <Button asChild className="justify-center">
                        <Link href={LoanRequestController.create().url}>
                            Continue your application
                        </Link>
                    </Button>
                </div>
            ) : (
                <div className="mt-4 flex flex-col gap-3.5">
                    <p className="text-sm text-muted-foreground">
                        You have no loan request in progress.
                    </p>
                    <Button
                        asChild
                        variant="outline"
                        className="justify-center"
                    >
                        <Link href={LoanRequestController.create().url}>
                            Apply for a loan
                        </Link>
                    </Button>
                </div>
            )}
        </section>
    );
}
