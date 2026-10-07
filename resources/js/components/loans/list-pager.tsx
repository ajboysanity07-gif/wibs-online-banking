import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * One shared pager for the Loans page lists so the table and both lists
 * can't drift: every page number plus a live count line that appends the
 * active filter/search summary when one is set.
 */
export function ListPager({
    page,
    perPage,
    total,
    noun,
    summary = '',
    paginationLabel,
    onPageChange,
    statusOverride,
}: {
    page: number;
    perPage: number;
    total: number;
    noun: string;
    summary?: string;
    paginationLabel: string;
    onPageChange: (page: number) => void;
    statusOverride?: string;
}) {
    const lastPage = Math.max(1, Math.ceil(total / perPage));
    const currentPage = Math.min(Math.max(1, page), lastPage);
    const from = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
    const to = Math.min(currentPage * perPage, total);
    const narrowed = summary.trim() !== '';

    const count =
        statusOverride ??
        (narrowed
            ? `${from}–${to} of ${total} ${noun} · ${summary}`
            : `Showing ${from}–${to} of ${total} ${noun}`);

    return (
        <div className="flex flex-wrap items-center justify-between gap-2.5">
            <p
                className="text-[13.5px] text-muted-foreground tabular-nums"
                role="status"
                aria-live="polite"
            >
                {count}
            </p>

            {statusOverride === undefined ? (
                <div
                    className="flex flex-wrap items-center gap-2"
                    role="navigation"
                    aria-label={paginationLabel}
                >
                    <PagerButton
                        label="Previous page"
                        text="Prev"
                        disabled={currentPage <= 1}
                        onClick={() => onPageChange(currentPage - 1)}
                    />
                    {Array.from(
                        { length: lastPage },
                        (_, index) => index + 1,
                    ).map((pageNumber) => (
                        <PagerButton
                            key={`page-${pageNumber}`}
                            label={`Page ${pageNumber}`}
                            text={String(pageNumber)}
                            current={pageNumber === currentPage}
                            onClick={() => onPageChange(pageNumber)}
                        />
                    ))}
                    <PagerButton
                        label="Next page"
                        text="Next"
                        disabled={currentPage >= lastPage}
                        onClick={() => onPageChange(currentPage + 1)}
                    />
                </div>
            ) : null}
        </div>
    );
}

function PagerButton({
    label,
    text,
    disabled = false,
    current = false,
    onClick,
}: {
    label: string;
    text: string;
    disabled?: boolean;
    current?: boolean;
    onClick: () => void;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            aria-label={label}
            aria-current={current ? 'page' : undefined}
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'h-auto min-h-10 min-w-10 rounded-[8px] border px-2.5 py-1.5 text-[14px] font-semibold tabular-nums disabled:opacity-45 md:h-auto',
                current
                    ? 'border-primary bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground'
                    : 'border-border bg-card text-card-foreground hover:bg-muted hover:text-card-foreground',
            )}
        >
            {text}
        </Button>
    );
}
