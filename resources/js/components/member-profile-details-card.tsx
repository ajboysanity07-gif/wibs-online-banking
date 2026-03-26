import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

type MemberProfileDetailItem = {
    label: string;
    value: ReactNode;
};

type MemberProfileDetailsCardProps = {
    title: string;
    description?: string;
    items: MemberProfileDetailItem[];
    className?: string;
    contentClassName?: string;
    itemClassName?: string;
};

export function MemberProfileDetailsCard({
    title,
    description,
    items,
    className,
    contentClassName,
    itemClassName,
}: MemberProfileDetailsCardProps) {
    return (
        <Card
            className={cn(
                'rounded-2xl border-border/40 bg-card/70 shadow-sm',
                className,
            )}
        >
            <CardHeader className="space-y-2 pb-4">
                <CardTitle className="text-lg">{title}</CardTitle>
                {description ? (
                    <CardDescription>{description}</CardDescription>
                ) : null}
            </CardHeader>
            <CardContent
                className={cn('grid gap-4 sm:grid-cols-2', contentClassName)}
            >
                {items.map((item) => (
                    <div
                        key={item.label}
                        className={cn(
                            'rounded-lg border border-border/30 bg-muted/20 p-3',
                            itemClassName,
                        )}
                    >
                        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                            {item.label}
                        </p>
                        <p className="mt-1 text-sm font-semibold text-foreground">
                            {item.value}
                        </p>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
