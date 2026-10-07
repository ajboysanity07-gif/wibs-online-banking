import { Check, ChevronRight } from 'lucide-react';
import { LoanEstimateFigures } from '@/components/loan-request/loan-application-estimate';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    APPLICATION_SECTIONS,
    nextIncompleteSection,
    SECTION_LABELS,
    SECTION_STATUS_LABELS,
    type ApplicationSection,
    type SectionStatus,
} from '@/lib/loan-application-flow';
import { cn } from '@/lib/utils';
import type { LoanEstimate } from '@/types/loan-requests';

const STATUS_CLASS: Record<SectionStatus, string> = {
    not_started: 'border-border bg-muted text-muted-foreground',
    in_progress:
        'border-sky-500/30 bg-sky-500/10 text-sky-800 dark:text-sky-100',
    done: 'border-secondary-foreground/25 bg-secondary text-secondary-foreground',
    needs_attention:
        'border-amber-500/40 bg-amber-500/10 text-amber-900 dark:text-amber-100',
};

export function SectionStatusBadge({ status }: { status: SectionStatus }) {
    return (
        <Badge
            variant="outline"
            className={cn('shrink-0 font-bold', STATUS_CLASS[status])}
        >
            {SECTION_STATUS_LABELS[status]}
        </Badge>
    );
}

type Props = {
    statuses: Record<ApplicationSection, SectionStatus>;
    summaries: Record<ApplicationSection, string>;
    headline: { title: string; amount: string };
    estimate: LoanEstimate | null;
    isEstimating: boolean;
    estimateNote: string;
    onOpenSection: (section: ApplicationSection) => void;
    onStart: (section: ApplicationSection) => void;
    onReview: () => void;
    onRecalculate: () => void;
};

export function LoanApplicationOverview({
    statuses,
    summaries,
    headline,
    estimate,
    isEstimating,
    estimateNote,
    onOpenSection,
    onStart,
    onReview,
    onRecalculate,
}: Props) {
    const doneCount = APPLICATION_SECTIONS.filter(
        (section) => statuses[section] === 'done',
    ).length;
    const next = nextIncompleteSection(statuses);
    const ready = next === null;
    const remaining = APPLICATION_SECTIONS.filter(
        (section) => statuses[section] !== 'done',
    ).map((section) => SECTION_LABELS[section].toLowerCase());

    return (
        <>
            <div className="space-y-1.5 px-0.5">
                <p className="text-xs font-bold tracking-[0.16em] text-primary uppercase">
                    Draft application
                </p>
                <h2 className="text-2xl font-bold tracking-tight sm:text-[30px]">
                    Your loan application
                </h2>
                <p className="text-[15px] text-muted-foreground">
                    Complete each section in any order. We save as you go.
                </p>
            </div>

            <div className="flex items-center gap-3 px-0.5">
                <div className="h-2 flex-1 overflow-hidden rounded-full bg-border">
                    <div
                        className="h-full bg-primary transition-[width] duration-300 motion-reduce:transition-none"
                        style={{
                            width: `${(doneCount / APPLICATION_SECTIONS.length) * 100}%`,
                        }}
                    />
                </div>
                <span className="text-sm font-bold whitespace-nowrap">
                    {doneCount} of {APPLICATION_SECTIONS.length} sections done
                </span>
            </div>

            <section className="rounded-xl border border-border bg-card p-5 shadow-card">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <p className="text-sm font-semibold text-muted-foreground">
                            {headline.title}
                        </p>
                        <p className="text-[28px] font-bold tracking-tight tabular-nums">
                            {headline.amount}
                        </p>
                    </div>
                    <div className="flex shrink-0 flex-wrap justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            className="min-h-11 md:min-h-11"
                            onClick={onRecalculate}
                        >
                            Calculator
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11 md:min-h-11"
                            onClick={() => onOpenSection('loan')}
                        >
                            Change
                        </Button>
                    </div>
                </div>
                <div className="mt-3.5 border-t border-border pt-3.5">
                    <LoanEstimateFigures
                        estimate={estimate}
                        isLoading={isEstimating}
                        tone="plain"
                    />
                </div>
                <p className="mt-2 text-xs text-muted-foreground">
                    {estimateNote}
                </p>
            </section>

            <nav
                aria-label="Application sections"
                className="overflow-hidden rounded-xl border border-border bg-card shadow-card"
            >
                <ol>
                    {APPLICATION_SECTIONS.map((section, index) => {
                        const status = statuses[section];
                        const done = status === 'done';

                        return (
                            <li
                                key={section}
                                className={cn(
                                    index > 0 && 'border-t border-border',
                                )}
                            >
                                <Button variant="ghost"
                                    type="button"
                                    onClick={() => onOpenSection(section)}
                                    className="h-auto md:h-auto justify-start gap-0 whitespace-normal rounded-none px-0 py-0 has-[>svg]:px-0 font-normal hover:bg-transparent hover:text-current flex min-h-16 w-full items-center gap-3.5 px-5 has-[>svg]:px-5 py-4 text-left transition-colors duration-150 hover:bg-muted focus-visible:bg-muted focus-visible:outline-none motion-reduce:transition-none"
                                >
                                    <span
                                        aria-hidden="true"
                                        className={cn(
                                            'grid size-7 shrink-0 place-items-center rounded-full border-[1.5px] text-[13px] font-bold',
                                            done
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-input bg-card text-muted-foreground',
                                        )}
                                    >
                                        {done ? (
                                            <Check className="size-4" />
                                        ) : (
                                            index + 1
                                        )}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-base font-semibold">
                                            {SECTION_LABELS[section]}
                                        </span>
                                        <span className="block truncate text-[13px] text-muted-foreground">
                                            {summaries[section]}
                                        </span>
                                    </span>
                                    <SectionStatusBadge status={status} />
                                    <ChevronRight
                                        aria-hidden="true"
                                        className="size-[18px] shrink-0 text-muted-foreground"
                                    />
                                </Button>
                            </li>
                        );
                    })}
                    <li className="border-t border-border bg-muted">
                        <Button variant="ghost"
                            type="button"
                            disabled={!ready}
                            onClick={onReview}
                            className="h-auto md:h-auto justify-start gap-0 whitespace-normal rounded-none px-0 py-0 has-[>svg]:px-0 font-normal hover:bg-transparent hover:text-current flex min-h-16 w-full items-center gap-3.5 px-5 has-[>svg]:px-5 py-4 text-left disabled:cursor-default"
                        >
                            <span
                                aria-hidden="true"
                                className={cn(
                                    'grid size-7 shrink-0 place-items-center rounded-full border-[1.5px] text-[13px] font-bold',
                                    ready
                                        ? 'border-accent bg-accent text-accent-foreground'
                                        : 'border-border bg-card text-muted-foreground',
                                )}
                            >
                                6
                            </span>
                            <span className="min-w-0 flex-1">
                                <span
                                    className={cn(
                                        'block text-base font-semibold',
                                        !ready && 'text-muted-foreground',
                                    )}
                                >
                                    Check and submit
                                </span>
                                <span className="block text-[13px] text-muted-foreground">
                                    {ready
                                        ? 'Check your answers, then submit'
                                        : `Finish ${remaining.join(', ')} first`}
                                </span>
                            </span>
                            <Badge
                                variant="outline"
                                className={cn(
                                    'shrink-0 font-bold',
                                    ready
                                        ? STATUS_CLASS.done
                                        : STATUS_CLASS.not_started,
                                )}
                            >
                                {ready ? 'Ready' : 'Cannot start yet'}
                            </Badge>
                        </Button>
                    </li>
                </ol>
            </nav>

            <div className="flex flex-wrap items-center gap-3.5">
                <Button
                    type="button"
                    className="min-h-12 px-6 text-[15px] font-bold md:min-h-12"
                    onClick={() => (next === null ? onReview() : onStart(next))}
                >
                    {ready
                        ? 'Check and submit'
                        : doneCount === 0
                          ? 'Start application'
                          : 'Continue application'}
                </Button>
                <span className="text-sm text-muted-foreground">
                    {ready
                        ? 'All sections done'
                        : `Next: ${SECTION_LABELS[next]}`}
                </span>
            </div>

            <p className="rounded-xl bg-secondary px-4 py-3.5 text-[13px] leading-relaxed text-secondary-foreground">
                <b>Good to know:</b> documents are collected and signed in
                person when the loan is released. You and your co-makers sign
                them then. Nothing to upload or print now.
            </p>
        </>
    );
}
