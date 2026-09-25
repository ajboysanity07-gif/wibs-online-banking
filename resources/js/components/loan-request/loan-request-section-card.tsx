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
    // Field-level messages are no longer shown under each input -- this
    // renders them all as a single summary at the top of the step instead,
    // with each entry focusing/highlighting its field on click.
    errors?: Record<string, string | undefined>;
    // See FormErrorSummary's onEntryClick -- needed when an error entry's
    // field may live on a different wizard step than the one being viewed.
    onErrorClick?: (key: string) => void;
};

export function LoanRequestSectionCard({
    title,
    description,
    icon: Icon,
    children,
    className,
    contentClassName,
    errors,
    onErrorClick,
}: Props) {
    return (
        <Card
            className={cn(
                'animate-in border-border/50 bg-card/70 duration-200 fade-in slide-in-from-top-2',
                className,
            )}
        >
            <CardHeader className="space-y-2 pb-5">
                <CardTitle className="flex items-center gap-2 text-lg">
                    {Icon ? (
                        <Icon className="size-5 text-muted-foreground" />
                    ) : null}
                    {title}
                </CardTitle>
                {description ? (
                    <CardDescription>{description}</CardDescription>
                ) : null}
            </CardHeader>
            <CardContent className={cn('space-y-7', contentClassName)}>
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
