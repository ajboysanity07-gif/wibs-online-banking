import {
    useSyncExternalStore,
    type KeyboardEvent,
    type ReactNode,
} from 'react';
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
 * Selected tab lives in the URL hash so refresh and back/forward work.
 * Panel ids are prefixed, so the browser never scrolls to `#documents`.
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

type TabsProps = {
    tab: ReviewTabId;
    onSelect: (tab: ReviewTabId) => void;
    counts?: Partial<Record<ReviewTabId, number>>;
};

export function LoanRequestReviewTabs({ tab, onSelect, counts }: TabsProps) {
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
        <div className="border-t border-border">
            <div
                role="tablist"
                aria-label="Request sections"
                onKeyDown={onKeyDown}
                className="mx-auto flex w-full max-w-7xl gap-1 overflow-x-auto px-4 [scrollbar-width:none] sm:px-6 lg:px-8 [&::-webkit-scrollbar]:hidden"
            >
                {REVIEW_TABS.map(({ id, label }) => {
                    const active = tab === id;
                    const count = counts?.[id];

                    return (
                        <button
                            key={id}
                            id={`review-tab-${id}`}
                            type="button"
                            role="tab"
                            aria-selected={active}
                            aria-controls={`review-panel-${id}`}
                            tabIndex={active ? 0 : -1}
                            onClick={() => onSelect(id)}
                            className={cn(
                                'flex min-h-11 items-center gap-1.5 border-b-[3px] px-3.5 text-sm whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none focus-visible:ring-inset motion-reduce:transition-none lg:min-h-10',
                                active
                                    ? 'border-primary font-bold text-foreground'
                                    : 'border-transparent font-medium text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {label}
                            {count ? (
                                <span className="rounded-full bg-primary/10 px-[7px] py-px text-[11px] font-bold text-primary">
                                    {count}
                                </span>
                            ) : null}
                        </button>
                    );
                })}
            </div>
        </div>
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
