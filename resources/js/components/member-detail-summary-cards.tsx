import type { LucideIcon } from 'lucide-react';
import { SurfaceCard } from '@/components/surface-card';
import { cn } from '@/lib/utils';

export type DetailAccent = 'primary' | 'accent';

type MemberDetailPrimaryCardProps = {
    title: string;
    value: string;
    helper?: string;
    icon: LucideIcon;
    accent: DetailAccent;
};

type MemberDetailSupportingCardProps = {
    title: string;
    value: string;
    description?: string;
    icon: LucideIcon;
    accent: DetailAccent;
};

const accentStyles: Record<
    DetailAccent,
    {
        border: string;
        bg: string;
        icon: string;
    }
> = {
    primary: {
        border: 'border-primary',
        bg: 'bg-primary text-primary-foreground',
        icon: 'text-primary-foreground/80',
    },
    accent: {
        border: 'border-accent/20',
        bg: 'bg-accent/5',
        icon: 'text-accent',
    },
};

export function MemberDetailPrimaryCard({
    title,
    value,
    helper,
    icon: Icon,
    accent,
}: MemberDetailPrimaryCardProps) {
    const styles = accentStyles[accent];

    return (
        <SurfaceCard
            variant="default"
            padding="md"
            className={cn(styles.border, styles.bg)}
        >
            <div className="flex flex-col gap-3">
                <div className="flex items-center justify-between gap-3">
                    <p className="text-[13px] font-semibold">{title}</p>
                    <Icon
                        className={cn('h-5 w-5', styles.icon)}
                        aria-hidden="true"
                    />
                </div>
                <p className="text-4xl font-bold tracking-tight tabular-nums">
                    {value}
                </p>
                {helper ? <p className="text-[13px]">{helper}</p> : null}
            </div>
        </SurfaceCard>
    );
}

export function MemberDetailSupportingCard({
    title,
    value,
    description,
    icon: Icon,
    accent,
}: MemberDetailSupportingCardProps) {
    const styles = accentStyles[accent];

    return (
        <SurfaceCard
            variant="default"
            padding="md"
            className="border-border bg-card"
        >
            <div className="flex h-full flex-col gap-3">
                <div className="flex items-center justify-between gap-3">
                    <p className="text-[13px] font-semibold text-muted-foreground">
                        {title}
                    </p>
                    <Icon
                        className={cn('h-4 w-4', styles.icon)}
                        aria-hidden="true"
                    />
                </div>
                <p className="text-4xl font-bold tabular-nums">{value}</p>
                {description ? (
                    <p className="text-[13px] text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
        </SurfaceCard>
    );
}
