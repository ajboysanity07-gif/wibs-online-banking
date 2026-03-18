import type { ComponentProps, ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type BadgeVariant = ComponentProps<typeof Badge>['variant'];

type MemberStatusCardProps = {
    title?: string;
    description?: string;
    statusLabel: string;
    statusVariant: BadgeVariant;
    actions?: ReactNode;
    helper?: ReactNode;
};

export function MemberStatusCard({
    title = 'Account status',
    description = 'Manage portal access state.',
    statusLabel,
    statusVariant,
    actions,
    helper,
}: MemberStatusCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description ? (
                    <CardDescription>{description}</CardDescription>
                ) : null}
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">
                        Current status
                    </span>
                    <Badge variant={statusVariant}>{statusLabel}</Badge>
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
