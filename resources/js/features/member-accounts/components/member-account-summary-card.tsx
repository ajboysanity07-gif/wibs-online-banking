import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

export type SummaryAccent = 'primary' | 'accent';

type MemberAccountSummaryCardProps = {
    title: string;
    subtitle?: string;
    primaryLabel: string;
    primaryValue: string;
    secondaryLabel: string;
    secondaryValue: string;
    icon: LucideIcon;
    accent: SummaryAccent;
    actionLabel?: string;
    actionHref?: string;
    actionDisabled?: boolean;
    loading?: boolean;
};

const summaryAccents: Record<
    SummaryAccent,
    { stripe: string; icon: string; iconWrap: string }
> = {
    primary: {
        stripe: 'bg-primary/60',
        icon: 'text-primary',
        iconWrap: 'border-primary/20 bg-primary/10',
    },
    accent: {
        stripe: 'bg-accent/60',
        icon: 'text-accent',
        iconWrap: 'border-accent/30 bg-accent/10',
    },
};

export function MemberAccountSummaryCard({
    title,
    subtitle,
    primaryLabel,
    primaryValue,
    secondaryLabel,
    secondaryValue,
    icon: Icon,
    accent,
    actionLabel,
    actionHref,
    actionDisabled = false,
    loading = false,
}: MemberAccountSummaryCardProps) {
    const accentClasses = summaryAccents[accent];
    const hasAction = Boolean(actionLabel);

    return (
        <Card className="relative overflow-hidden rounded-2xl border-border/40 bg-card/70 shadow-sm">
            <div
                className={cn(
                    'absolute inset-x-0 top-0 h-1',
                    accentClasses.stripe,
                )}
            />
            <CardHeader className="flex flex-row items-start justify-between gap-4 pb-4">
                <div className="flex items-start gap-3">
                    <div
                        className={cn(
                            'flex h-11 w-11 items-center justify-center rounded-2xl border',
                            accentClasses.iconWrap,
                        )}
                    >
                        <Icon
                            className={cn('h-5 w-5', accentClasses.icon)}
                            aria-hidden="true"
                        />
                    </div>
                    <div className="space-y-1">
                        <CardTitle className="text-base">{title}</CardTitle>
                        {subtitle ? (
                            <CardDescription className="text-xs">
                                {subtitle}
                            </CardDescription>
                        ) : null}
                    </div>
                </div>
                {hasAction ? (
                    actionHref && !actionDisabled ? (
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="h-8 px-3 text-xs"
                        >
                            <Link href={actionHref}>{actionLabel}</Link>
                        </Button>
                    ) : (
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-8 px-3 text-xs"
                            disabled
                        >
                            {actionLabel}
                        </Button>
                    )
                ) : null}
            </CardHeader>
            <CardContent className="space-y-5">
                <div className="space-y-1">
                    <p className="text-xs text-muted-foreground">
                        {primaryLabel}
                    </p>
                    {loading ? (
                        <Skeleton className="h-9 w-40" />
                    ) : (
                        <p className="text-3xl font-semibold tracking-tight tabular-nums">
                            {primaryValue}
                        </p>
                    )}
                </div>
                <div className="rounded-lg border border-border/30 bg-muted/20 px-3 py-2">
                    <p className="text-[0.7rem] font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                        {secondaryLabel}
                    </p>
                    {loading ? (
                        <Skeleton className="mt-2 h-4 w-32" />
                    ) : (
                        <p className="mt-1 text-sm font-semibold tabular-nums">
                            {secondaryValue}
                        </p>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
