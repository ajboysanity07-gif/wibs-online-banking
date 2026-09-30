import { Check } from 'lucide-react';
import type { ReactNode } from 'react';
import { LoanRequestStatusBadge } from '@/components/loan-request/loan-request-status-badge';
import {
    PROGRESS_STEPS,
    STAGE_GUIDANCE,
    resolveLoanRequestProgress,
} from '@/lib/loan-request-progress';
import { cn } from '@/lib/utils';
import type { LoanRequestStatusValue } from '@/types/loan-requests';

export type RecordHeaderFigure = {
    label: string;
    value: string;
    tone?: 'primary' | 'destructive';
};

type Props = {
    /** Muted line above the reference, e.g. "Loan request · Other Loan (SALARY)". */
    eyebrow: string;
    reference: string;
    status: LoanRequestStatusValue | null;
    applicantName: string;
    figures: RecordHeaderFigure[];
    /** Status before `cancelled`, so a cancelled request keeps its stage. */
    previousStatus?: LoanRequestStatusValue | null;
    /** Workflow action bar. */
    children?: ReactNode;
};

const chevron =
    '[clip-path:polygon(0_0,calc(100%_-_12px)_0,100%_50%,calc(100%_-_12px)_100%,0_100%,12px_50%)]';

export function LoanRequestRecordHeader({
    eyebrow,
    reference,
    status,
    applicantName,
    figures,
    previousStatus,
    children,
}: Props) {
    const { step, held } = resolveLoanRequestProgress(status, previousStatus);

    return (
        <div className="border-b border-border bg-card shadow-card">
            <div className="mx-auto flex w-full max-w-[1440px] flex-wrap items-center gap-4 px-4 pt-4 pb-3 sm:px-6 lg:px-8">
                <div className="min-w-0 flex-1 basis-72">
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground">
                        {eyebrow}
                    </p>
                    <div className="mt-0.5 flex flex-wrap items-center gap-2.5">
                        <h1 className="text-[22px] font-bold tracking-tight sm:text-[26px]">
                            {reference}
                        </h1>
                        <LoanRequestStatusBadge status={status} />
                        <span className="text-[15px] font-semibold">
                            {applicantName}
                        </span>
                    </div>
                </div>
                {children}
            </div>

            <dl className="mx-auto scrollbar-hide flex w-full max-w-[1440px] overflow-x-auto px-4 pb-3.5 sm:px-6 lg:px-8">
                {figures.map((figure) => (
                    <div
                        key={figure.label}
                        className={cn(
                            'min-w-[130px] flex-[1_0_auto] border-l-2 py-0.5 pr-[18px] pl-3.5',
                            figure.tone === 'primary'
                                ? 'border-primary'
                                : 'border-border',
                        )}
                    >
                        <dt className="text-[11px] font-bold tracking-widest whitespace-nowrap text-muted-foreground uppercase">
                            {figure.label}
                        </dt>
                        <dd
                            className={cn(
                                'mt-0.5 text-base font-bold whitespace-nowrap tabular-nums',
                                figure.tone === 'primary' && 'text-primary',
                                figure.tone === 'destructive' &&
                                    'text-destructive',
                            )}
                        >
                            {figure.value}
                        </dd>
                    </div>
                ))}
            </dl>

            <div className="mx-auto w-full max-w-[1440px] px-4 pb-3.5 sm:px-6 lg:px-8">
                <ol
                    aria-label="Stage"
                    className="scrollbar-hide flex gap-[3px] overflow-x-auto"
                >
                    {PROGRESS_STEPS.map((label, index) => {
                        const done = index < step;
                        const current = index === step;

                        return (
                            <li
                                key={label}
                                aria-current={current ? 'step' : undefined}
                                className={cn(
                                    'flex flex-[1_0_120px] items-center justify-center gap-1 px-[18px] py-[9px] text-[13px] whitespace-nowrap',
                                    chevron,
                                    done &&
                                        'bg-secondary font-semibold text-secondary-foreground',
                                    current &&
                                        'bg-primary font-bold text-primary-foreground',
                                    !done &&
                                        !current &&
                                        'bg-muted font-semibold text-muted-foreground',
                                )}
                            >
                                {done ? (
                                    <Check
                                        aria-hidden="true"
                                        className="size-3.5"
                                    />
                                ) : null}
                                {label}
                                {done ? (
                                    <span className="sr-only">(done)</span>
                                ) : null}
                            </li>
                        );
                    })}
                </ol>
                <p className="mt-2 flex flex-wrap items-center gap-2 text-[13px] text-muted-foreground">
                    {held || step < 0 ? (
                        <>
                            <b className="text-foreground">Current status:</b>
                            <LoanRequestStatusBadge status={status} />
                        </>
                    ) : (
                        <span>
                            <b className="text-foreground">Stage guidance:</b>{' '}
                            {STAGE_GUIDANCE[step]}
                        </span>
                    )}
                </p>
            </div>
        </div>
    );
}
