import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { FormErrorSummary } from '@/components/ui/form-error-summary';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    icon?: LucideIcon;
    children: ReactNode;
    className?: string;
    contentClassName?: string;
    // Optional right-aligned header slot (e.g. a badge or action button)
    // for detail-page cards that need one alongside the title/description.
    headerAction?: ReactNode;
    // Field-level messages are no longer shown under each input -- this
    // renders them all as a single summary at the top of the step instead,
    // with each entry focusing/highlighting its field on click.
    errors?: Record<string, string | undefined>;
    // See FormErrorSummary's onEntryClick -- needed when an error entry's
    // field may live on a different wizard step than the one being viewed.
    onErrorClick?: (key: string) => void;
    // Renders a divider-separated block instead of a bordered card, for use
    // inside a panel that already provides the card surface (Settings).
    flat?: boolean;
    /** Staff workspace density: 17px title, 13px description. */
    workspace?: boolean;
    /** Inline next to the title (e.g. an "Editing" pill). */
    titleAddon?: ReactNode;
};

export function LoanRequestSectionCard({
    title,
    description,
    icon: Icon,
    children,
    className,
    contentClassName,
    headerAction,
    errors,
    onErrorClick,
    flat,
    workspace = false,
    titleAddon,
}: Props) {
    if (flat) {
        return (
            <section
                className={cn('border-t border-border px-6 py-6', className)}
            >
                <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0 space-y-1">
                        <h4 className="flex items-center gap-2 text-base font-semibold">
                            {Icon ? (
                                <Icon className="size-5 shrink-0 text-muted-foreground" />
                            ) : null}
                            {title}
                        </h4>
                        {description ? (
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        ) : null}
                    </div>
                    {headerAction ?? null}
                </div>
                <div className={cn(contentClassName ?? 'space-y-7')}>
                    {errors ? (
                        <FormErrorSummary
                            errors={errors}
                            onEntryClick={onErrorClick}
                        />
                    ) : null}
                    {children}
                </div>
            </section>
        );
    }

    return (
        <Card
            className={cn(
                'animate-in border-border bg-card duration-200 fade-in slide-in-from-top-2',
                className,
            )}
        >
            <CardHeader
                className={cn(
                    'space-y-2 pb-5',
                    headerAction &&
                        'flex flex-row flex-wrap items-start justify-between gap-3 space-y-0',
                )}
            >
                <div className={cn('min-w-0', headerAction && 'space-y-1.5')}>
                    <CardTitle
                        className={cn(
                            'flex items-center gap-2',
                            workspace ? 'text-[17px]' : 'text-lg',
                        )}
                    >
                        {Icon ? (
                            <Icon className="size-5 shrink-0 text-muted-foreground" />
                        ) : null}
                        {title}
                        {titleAddon}
                    </CardTitle>
                    {description ? (
                        <CardDescription
                            className={workspace ? 'text-[13px]' : undefined}
                        >
                            {description}
                        </CardDescription>
                    ) : null}
                </div>
                {headerAction ?? null}
            </CardHeader>
            <CardContent className={cn(contentClassName ?? 'space-y-7')}>
                {errors ? (
                    <FormErrorSummary
                        errors={errors}
                        onEntryClick={onErrorClick}
                    />
                ) : null}
                {children}
            </CardContent>
        </Card>
    );
}
