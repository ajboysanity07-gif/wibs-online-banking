import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
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
        <Card className={cn('border', styles.border, styles.bg)}>
            <CardContent className="flex flex-col gap-3 p-6">
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
            </CardContent>
        </Card>
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
        <Card className="border-border/60 bg-card">
            <CardContent className="flex h-full flex-col gap-3 p-6">
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
            </CardContent>
        </Card>
    );
}
