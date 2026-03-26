import type { ReactNode } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { SurfaceCard } from '@/components/surface-card';

type MemberProfileHeaderProps = {
    name: string;
    subtitle: string;
    avatarUrl?: string | null;
    avatarFallback: string;
    accessory?: ReactNode;
};

export function MemberProfileHeader({
    name,
    subtitle,
    avatarUrl,
    avatarFallback,
    accessory,
}: MemberProfileHeaderProps) {
    return (
        <SurfaceCard variant="hero" padding="lg">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <Avatar className="size-14 ring-1 ring-border/60">
                        <AvatarImage src={avatarUrl ?? undefined} alt={name} />
                        <AvatarFallback>{avatarFallback}</AvatarFallback>
                    </Avatar>
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {subtitle}
                        </p>
                    </div>
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
