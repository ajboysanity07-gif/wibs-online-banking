import type { ReactNode } from 'react';
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
        <section>
            <div className="flex flex-wrap items-start gap-5">
                <div className="flex flex-col gap-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <Avatar className="size-16 ring-1 ring-border/60">
                            <AvatarImage
                                src={avatarUrl ?? undefined}
                                alt={name}
                            />
                            <AvatarFallback>{avatarFallback}</AvatarFallback>
                        </Avatar>
                        <div className="space-y-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-[22px] leading-tight font-bold sm:text-[26px]">
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
                    <div className="flex flex-wrap items-center gap-2 sm:ml-auto">
                        {accessory}
                    </div>
                ) : null}
            </div>
        </section>
    );
}
