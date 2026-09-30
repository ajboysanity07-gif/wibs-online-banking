import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type PageHeroProps = {
    title: ReactNode;
    kicker?: string;
    description?: ReactNode;
    badges?: ReactNode;
    rightSlot?: ReactNode;
    className?: string;
};

// Primary band. Slots (badges, actions) sit on a card-colored tray so any
// button/badge variant stays legible on both the light and dark primary.
export function PageHero({
    title,
    kicker,
    description,
    badges,
    rightSlot,
    className,
}: PageHeroProps) {
    return (
        <section
            className={cn(
                'space-y-6 rounded-xl bg-primary p-6 text-primary-foreground shadow-card sm:p-7 lg:p-8',
                className,
            )}
        >
            <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div className="space-y-3">
                    {kicker ? (
                        <p className="text-xs font-bold tracking-[0.2em] text-primary-foreground/85 uppercase">
                            {kicker}
                        </p>
                    ) : null}
                    <div className="space-y-2">
                        <h1 className="text-[26px] leading-tight font-bold tracking-tight lg:text-[32px]">
                            {title}
                        </h1>
                        {description ? (
                            <p className="max-w-2xl text-sm text-primary-foreground/90">
                                {description}
                            </p>
                        ) : null}
                    </div>
                    {badges ? (
                        <div className="flex w-fit max-w-full flex-wrap gap-2 rounded-lg bg-card p-1.5 text-card-foreground">
                            {badges}
                        </div>
                    ) : null}
                </div>
                {rightSlot ? (
                    <div className="flex w-full flex-wrap items-center gap-2 rounded-lg bg-card p-2 text-card-foreground max-sm:[&>*]:w-full lg:w-auto lg:justify-end">
                        {rightSlot}
                    </div>
                ) : null}
            </div>
        </section>
    );
}
