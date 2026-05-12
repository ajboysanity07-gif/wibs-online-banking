import type { ReactNode } from 'react';
import { SurfaceCard } from '@/components/surface-card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

type MemberProfileHeaderProps = {
    name: string;
    subtitle: string;
    avatarUrl?: string | null;
    avatarFallback: string;
    accessory?: ReactNode;
    meta?: ReactNode;
    statusBadge?: ReactNode;
};

export function MemberProfileHeader({
    name,
    subtitle,
    avatarUrl,
    avatarFallback,
    accessory,
    meta,
    statusBadge,
}: MemberProfileHeaderProps) {
    return (
        <SurfaceCard variant="hero" padding="lg">
            <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <Avatar className="size-16 ring-1 ring-border/60">
                        <AvatarImage src={avatarUrl ?? undefined} alt={name} />
                        <AvatarFallback>{avatarFallback}</AvatarFallback>
                    </Avatar>
                        <div className="space-y-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-2xl font-semibold tracking-tight">
                                    {name}
                                </h1>
                                {statusBadge ? (
                                    <span>{statusBadge}</span>
                                ) : null}
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {subtitle}
                            </p>
                        </div>
                    </div>
                    {meta ? (
                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            {meta}
                        </div>
                    ) : null}
                </div>
                {accessory ? (
                    <div className="flex flex-wrap items-center gap-2">
                        {accessory}
                    </div>
                ) : null}
            </div>
        </SurfaceCard>
    );
}
