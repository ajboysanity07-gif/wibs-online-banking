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
        border: 'border-primary/20',
        bg: 'bg-primary/5',
        icon: 'text-primary',
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
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {title}
                    </p>
                    <Icon
                        className={cn('h-5 w-5', styles.icon)}
                        aria-hidden="true"
                    />
                </div>
                <p className="text-3xl font-semibold tracking-tight tabular-nums sm:text-4xl">
                    {value}
                </p>
                {helper ? (
                    <p className="text-xs text-muted-foreground">{helper}</p>
                ) : null}
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
            className="border-border/40 bg-card/60"
        >
            <div className="flex h-full flex-col gap-3">
                <div className="flex items-center justify-between gap-3">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {title}
                    </p>
                    <Icon
                        className={cn('h-4 w-4', styles.icon)}
                        aria-hidden="true"
                    />
                </div>
                <p className="text-lg font-semibold tabular-nums">{value}</p>
                {description ? (
                    <p className="text-xs text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
        </SurfaceCard>
    );
}
