import type { ReactNode } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

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
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                <Avatar className="size-12">
                    <AvatarImage src={avatarUrl ?? undefined} alt={name} />
                    <AvatarFallback>{avatarFallback}</AvatarFallback>
                </Avatar>
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold">{name}</h1>
                    <p className="text-sm text-muted-foreground">{subtitle}</p>
                </div>
            </div>
            {accessory ? (
                <div className="flex flex-wrap items-center gap-2">
                    {accessory}
                </div>
            ) : null}
        </div>
    );
}
