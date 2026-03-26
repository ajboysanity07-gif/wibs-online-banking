import type { ComponentProps, ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

type BadgeVariant = ComponentProps<typeof Badge>['variant'];

type MemberStatusCardProps = {
    title?: string;
    description?: string;
    statusLabel: string;
    statusVariant: BadgeVariant;
    actions?: ReactNode;
    helper?: ReactNode;
    className?: string;
    contentClassName?: string;
};

export function MemberStatusCard({
    title = 'Account status',
    description = 'Manage portal access state.',
    statusLabel,
    statusVariant,
    actions,
    helper,
    className,
    contentClassName,
}: MemberStatusCardProps) {
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
            <CardContent className={cn('space-y-4', contentClassName)}>
                <div className="flex items-center justify-between rounded-lg border border-border/30 bg-muted/20 px-3 py-2">
                    <span className="text-xs font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                        Current status
                    </span>
                    <Badge
                        variant={statusVariant}
                        className="text-[0.65rem] uppercase tracking-[0.2em]"
                    >
                        {statusLabel}
                    </Badge>
                </div>
                {actions ? (
                    <div className="flex flex-wrap gap-2">{actions}</div>
                ) : null}
                {helper ? (
                    <p className="text-xs text-muted-foreground">{helper}</p>
                ) : null}
            </CardContent>
        </Card>
    );
}
