import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

type MemberListCardSkeletonProps = {
    metaRows?: number;
    showSubtitle?: boolean;
    showAction?: boolean;
    className?: string;
};

export function MemberListCardSkeleton({
    metaRows = 3,
    showSubtitle = true,
    showAction = true,
    className,
}: MemberListCardSkeletonProps) {
    return (
        <div
            className={cn(
                'rounded-2xl border border-border/40 bg-card/70 p-4 shadow-sm',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="space-y-2">
                    <Skeleton className="h-4 w-32" />
                    {showSubtitle ? <Skeleton className="h-3 w-24" /> : null}
                </div>
                <Skeleton className="h-5 w-16" />
            </div>
            <div className="mt-3 space-y-2 rounded-xl border border-border/30 bg-muted/30 p-3">
                {Array.from({ length: metaRows }).map((_, index) => (
                    <div
                        key={`member-card-meta-${index}`}
                        className="flex items-center justify-between"
                    >
                        <Skeleton className="h-3 w-20" />
                        <Skeleton className="h-4 w-24" />
                    </div>
                ))}
            </div>
            {showAction ? (
                <div className="mt-3">
                    <Skeleton className="h-8 w-28" />
                </div>
            ) : null}
        </div>
    );
}
