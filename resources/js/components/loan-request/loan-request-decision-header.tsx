import type { ReactNode } from 'react';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import { cn } from '@/lib/utils';
import type { LoanRequestStatusValue } from '@/types/loan-requests';

type Props = {
    reference: string;
    status: LoanRequestStatusValue | null;
    /** Muted one-liner under the reference; hidden on mobile. */
    details: string[];
    age?: { days: number | null; targetDays: number };
    /** Warning line shown when the forward action is gated. */
    blockedNote?: string | null;
    /** Workflow action bar. */
    children?: ReactNode;
    /** Phase 3: tabs row. */
    tabs?: ReactNode;
};

export function LoanRequestDecisionHeader({
    reference,
    status,
    details,
    age,
    blockedNote,
    children,
    tabs,
}: Props) {
    const ageExceeded =
        age?.days !== null &&
        age?.days !== undefined &&
        age.days >= age.targetDays;

    return (
        <div className="sticky top-14 z-10 border-b border-border bg-card shadow-card">
            <div className="mx-auto flex w-full max-w-7xl flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3.5 sm:px-6 lg:px-8">
                <div className="min-w-0 flex-1 basis-64">
                    <div className="flex flex-wrap items-center gap-2.5">
                        <h1 className="text-[22px] font-bold tracking-tight sm:text-[26px]">
                            {reference}
                        </h1>
                        <LoanRequestStatusBadge status={status} />
                    </div>
                    <p className="mt-0.5 hidden text-[13px] text-muted-foreground sm:block">
                        {details.join(' · ')}
                        {age ? (
                            <>
                                {details.length > 0 ? ' · ' : null}
                                <span
                                    className={cn(
                                        'font-semibold',
                                        ageExceeded
                                            ? 'text-destructive'
                                            : 'text-foreground',
                                    )}
                                >
                                    Age{' '}
                                    {age.days === null ? '-' : `${age.days}d`}
                                </span>{' '}
                                of {age.targetDays}d target
                            </>
                        ) : null}
                    </p>
                </div>
                {children}
            </div>
            {blockedNote ? (
                <p className="mx-auto w-full max-w-7xl px-4 pb-2.5 text-[13px] font-semibold text-amber-700 sm:px-6 lg:px-8 dark:text-amber-200">
                    {blockedNote}
                </p>
            ) : null}
            {tabs}
        </div>
    );
}
