import type { ReactNode } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

type MemberMobileCardMetaRow = {
    label: ReactNode;
    value: ReactNode;
};

type MemberMobileCardProps = {
    title: ReactNode;
    subtitle?: ReactNode;
    valueLabel?: ReactNode;
    value?: ReactNode;
    meta?: MemberMobileCardMetaRow[];
    footer?: ReactNode;
};

type MemberMobileCardSkeletonProps = {
    metaRows?: number;
    actionCount?: number;
    titleClassName?: string;
    subtitleClassName?: string;
    valueLabelClassName?: string;
    valueClassName?: string;
};

export function MemberMobileCard({
    title,
    subtitle,
    valueLabel,
    value,
    meta = [],
    footer,
}: MemberMobileCardProps) {
    return (
        <div className="rounded-lg border border-border bg-card p-4">
            <div className="flex items-start justify-between gap-3">
                <div className="space-y-1">
                    <p className="text-sm font-semibold">{title}</p>
                    {subtitle ? (
                        <p className="text-xs text-muted-foreground">
                            {subtitle}
                        </p>
                    ) : null}
                </div>
                {value ? (
                    <div className="text-right">
                        {valueLabel ? (
                            <p className="text-xs text-muted-foreground">
                                {valueLabel}
                            </p>
                        ) : null}
                        <p className="text-lg font-semibold tabular-nums">
                            {value}
                        </p>
                    </div>
                ) : null}
            </div>
            {meta.length > 0 ? (
                <div className="mt-3 space-y-2 rounded-md border border-border/60 bg-muted/40 p-3">
                    {meta.map((item, index) => (
                        <div
                            key={`mobile-card-meta-${index}`}
                            className="flex items-center justify-between text-xs"
                        >
                            <span className="text-muted-foreground">
                                {item.label}
                            </span>
                            <span className="text-sm font-medium tabular-nums">
                                {item.value}
                            </span>
                        </div>
                    ))}
                </div>
            ) : null}
            {footer ? <div className="mt-3">{footer}</div> : null}
        </div>
    );
}

export function MemberMobileCardSkeleton({
    metaRows = 3,
    actionCount = 0,
    titleClassName,
    subtitleClassName,
    valueLabelClassName,
    valueClassName,
}: MemberMobileCardSkeletonProps) {
    return (
        <div className="rounded-lg border border-border bg-card p-4">
            <div className="flex items-start justify-between gap-3">
                <div className="space-y-2">
                    <Skeleton className={cn('h-4 w-24', titleClassName)} />
                    <Skeleton className={cn('h-3 w-20', subtitleClassName)} />
                </div>
                <div className="space-y-2 text-right">
                    <Skeleton
                        className={cn(
                            'ml-auto h-3 w-16',
                            valueLabelClassName,
                        )}
                    />
                    <Skeleton
                        className={cn('ml-auto h-6 w-20', valueClassName)}
                    />
                </div>
            </div>
            <div className="mt-3 space-y-2 rounded-md border border-border/60 bg-muted/40 p-3">
                {Array.from({ length: metaRows }).map((_, index) => (
                    <div
                        key={`mobile-card-meta-${index}`}
                        className="flex items-center justify-between"
                    >
                        <Skeleton className="h-3 w-20" />
                        <Skeleton className="h-4 w-24" />
                    </div>
                ))}
            </div>
            {actionCount > 0 ? (
                <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                    {Array.from({ length: actionCount }).map((_, index) => (
                        <Skeleton
                            key={`mobile-card-action-${index}`}
                            className="h-8 w-full sm:w-24"
                        />
                    ))}
                </div>
            ) : null}
        </div>
    );
}
