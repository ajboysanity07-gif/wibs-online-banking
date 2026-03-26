import type { ReactNode } from 'react';
import { SurfaceCard } from '@/components/surface-card';
import { cn } from '@/lib/utils';

type PageHeroProps = {
    title: ReactNode;
    kicker?: string;
    description?: ReactNode;
    badges?: ReactNode;
    rightSlot?: ReactNode;
    className?: string;
};

export function PageHero({
    title,
    kicker,
    description,
    badges,
    rightSlot,
    className,
}: PageHeroProps) {
    return (
        <SurfaceCard
            variant="hero"
            padding="lg"
            className={cn('space-y-6', className)}
        >
            <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div className="space-y-3">
                    {kicker ? (
                        <p className="text-xs font-semibold tracking-[0.28em] text-muted-foreground uppercase">
                            {kicker}
                        </p>
                    ) : null}
                    <div className="space-y-2">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        {description ? (
                            <p className="max-w-2xl text-sm text-muted-foreground">
                                {description}
                            </p>
                        ) : null}
                    </div>
                    {badges ? (
                        <div className="flex flex-wrap gap-2">
                            {badges}
                        </div>
                    ) : null}
                </div>
                {rightSlot ? (
                    <div className="flex w-full flex-wrap items-center gap-2 lg:w-auto lg:justify-end">
                        {rightSlot}
                    </div>
                ) : null}
            </div>
        </SurfaceCard>
    );
}
