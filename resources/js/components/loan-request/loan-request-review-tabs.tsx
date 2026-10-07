import {
    useSyncExternalStore,
    type KeyboardEvent,
    type ReactNode,
} from 'react';
import { Button } from '@/components/ui/button';
import {
    nextReviewTab,
    parseReviewTabHash,
    REVIEW_TABS,
    type ReviewTabId,
} from '@/lib/loan-request-review-tab';
import { cn } from '@/lib/utils';

const subscribe = (onChange: () => void) => {
    window.addEventListener('hashchange', onChange);

    return () => window.removeEventListener('hashchange', onChange);
};

/**
 * Selected section lives in the URL hash so refresh and back/forward work.
 * Panel ids are prefixed, so the browser never scrolls to `#docs`.
 */
export function useReviewTab(): [ReviewTabId, (tab: ReviewTabId) => void] {
    const hash = useSyncExternalStore(
        subscribe,
        () => window.location.hash,
        () => '',
    );

    return [
        parseReviewTabHash(hash),
        (tab) => {
            window.location.hash = tab;
        },
    ];
}

export type ReviewTabBadge = {
    label: string;
    tone: 'destructive' | 'success' | 'warning' | 'muted';
};

const badgeTone: Record<ReviewTabBadge['tone'], string> = {
    destructive: 'bg-destructive text-destructive-foreground',
    success: 'bg-secondary text-secondary-foreground',
    warning: 'bg-amber-500/15 text-amber-700 dark:text-amber-200',
    muted: 'bg-muted text-muted-foreground',
};

type TabsProps = {
    tab: ReviewTabId;
    onSelect: (tab: ReviewTabId) => void;
    badges?: Partial<Record<ReviewTabId, ReviewTabBadge | null>>;
};

/** Left section list on lg+, a horizontal chip row below. */
export function LoanRequestReviewTabs({ tab, onSelect, badges }: TabsProps) {
    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const next = nextReviewTab(tab, event.key);

        if (next === null) {
            return;
        }

        event.preventDefault();
        onSelect(next);
        document.getElementById(`review-tab-${next}`)?.focus();
    };

    return (
        <nav
            aria-label="Request sections"
            className="min-w-0 rounded-xl border border-border bg-card p-1 shadow-card lg:sticky lg:top-[72px] lg:p-2"
        >
            <p
                aria-hidden="true"
                className="hidden px-2.5 py-2 text-[11px] font-bold tracking-[0.14em] text-muted-foreground uppercase lg:block"
            >
                Sections
            </p>
            <div
                role="tablist"
                aria-label="Request sections"
                onKeyDown={onKeyDown}
                className="flex gap-0.5 overflow-x-auto [scrollbar-width:none] lg:flex-col lg:overflow-visible [&::-webkit-scrollbar]:hidden"
            >
                {REVIEW_TABS.map(({ id, label }) => {
                    const active = tab === id;
                    const badge = badges?.[id];

                    return (
                        <Button variant="ghost"
                            key={id}
                            id={`review-tab-${id}`}
                            type="button"
                            role="tab"
                            aria-selected={active}
                            aria-controls={`review-panel-${id}`}
                            tabIndex={active ? 0 : -1}
                            onClick={() => onSelect(id)}
                            className={cn(
'h-auto md:h-auto justify-start gap-0 whitespace-normal rounded-none px-0 py-0 has-[>svg]:px-0 font-normal hover:bg-transparent hover:text-current',
                                'flex min-h-11 shrink-0 items-center justify-between gap-2 rounded-lg px-2.5 has-[>svg]:px-2.5 text-left text-sm whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none motion-reduce:transition-none lg:min-h-10 lg:whitespace-normal',
                                active
                                    ? 'bg-secondary font-bold text-secondary-foreground shadow-[inset_3px_0_0_var(--primary)]'
                                    : 'font-medium text-foreground hover:bg-muted',
                            )}
                        >
                            {label}
                            {badge ? (
                                <span
                                    className={cn(
                                        'rounded-full px-2 py-px text-[11px] font-bold',
                                        badgeTone[badge.tone],
                                    )}
                                >
                                    {badge.label}
                                </span>
                            ) : null}
                        </Button>
                    );
                })}
            </div>
        </nav>
    );
}

/** Stays mounted while hidden so edit forms and previews keep their state. */
export function ReviewTabPanel({
    id,
    tab,
    children,
}: {
    id: ReviewTabId;
    tab: ReviewTabId;
    children: ReactNode;
}) {
    return (
        <div
            role="tabpanel"
            id={`review-panel-${id}`}
            aria-labelledby={`review-tab-${id}`}
            hidden={tab !== id}
            className="min-w-0 space-y-4"
        >
            {children}
        </div>
    );
}
