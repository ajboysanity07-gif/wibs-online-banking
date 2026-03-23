import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    children: ReactNode;
    className?: string;
    contentClassName?: string;
};

export function LoanRequestSectionCard({
    title,
    description,
    children,
    className,
    contentClassName,
}: Props) {
    return (
        <Card className={cn('border-border/60 bg-card/80', className)}>
            <CardHeader className="space-y-1.5 pb-4">
                <CardTitle className="text-lg">{title}</CardTitle>
                {description ? (
                    <CardDescription>{description}</CardDescription>
                ) : null}
            </CardHeader>
            <CardContent className={cn('space-y-6', contentClassName)}>
                {children}
            </CardContent>
        </Card>
    );
}
