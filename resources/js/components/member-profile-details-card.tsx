import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { LoanRequestSectionCard } from '@/components/loan-request/loan-request-section-card';
import { cn } from '@/lib/utils';

type MemberProfileDetailItem = {
    label: string;
    value: ReactNode;
};

type MemberProfileDetailsCardProps = {
    title: string;
    description?: string;
    icon?: LucideIcon;
    items: MemberProfileDetailItem[];
    className?: string;
    contentClassName?: string;
    itemClassName?: string;
};

export function MemberProfileDetailsCard({
    title,
    description,
    icon,
    items,
    className,
    contentClassName,
    itemClassName,
}: MemberProfileDetailsCardProps) {
    return (
        <LoanRequestSectionCard
            title={title}
            description={description}
            icon={icon}
            className={className}
            contentClassName={cn('grid gap-4 sm:grid-cols-2', contentClassName)}
        >
            {items.map((item) => (
                <div
                    key={item.label}
                    className={cn(
                        'rounded-lg border border-border/30 bg-muted/20 p-3',
                        itemClassName,
                    )}
                >
                    <p className="text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                        {item.label}
                    </p>
                    <p className="mt-1 text-sm font-semibold text-foreground">
                        {item.value}
                    </p>
                </div>
            ))}
        </LoanRequestSectionCard>
    );
}
