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
    children: ReactNode;
    className?: string;
    contentClassName?: string;
    // Field-level messages are no longer shown under each input -- this
    // renders them all as a single summary at the top of the step instead,
    // with each entry focusing/highlighting its field on click.
    errors?: Record<string, string | undefined>;
};

export function LoanRequestSectionCard({
    title,
    description,
    children,
    className,
    contentClassName,
    errors,
}: Props) {
    return (
        <Card className={cn('border-border/50 bg-card/70', className)}>
            <CardHeader className="space-y-2 pb-5">
                <CardTitle className="text-lg">{title}</CardTitle>
                {description ? (
                    <CardDescription>{description}</CardDescription>
                ) : null}
            </CardHeader>
            <CardContent className={cn('space-y-7', contentClassName)}>
                {errors ? <FormErrorSummary errors={errors} /> : null}
                {children}
            </CardContent>
        </Card>
    );
}
