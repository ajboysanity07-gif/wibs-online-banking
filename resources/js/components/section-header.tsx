import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type SectionHeaderProps = {
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    className?: string;
    titleClassName?: string;
    descriptionClassName?: string;
    actionsClassName?: string;
};

export function SectionHeader({
    title,
    description,
    actions,
    className,
    titleClassName,
    descriptionClassName,
    actionsClassName,
}: SectionHeaderProps) {
    return (
        <div
            className={cn(
                'flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
        >
            <div className="space-y-1">
                <h2 className={cn('text-base font-semibold', titleClassName)}>
                    {title}
                </h2>
                {description ? (
                    <p
                        className={cn(
                            'text-sm text-muted-foreground',
                            descriptionClassName,
                        )}
                    >
                        {description}
                    </p>
                ) : null}
            </div>
            {actions ? (
                <div
                    className={cn('flex items-center gap-2', actionsClassName)}
                >
                    {actions}
                </div>
            ) : null}
        </div>
    );
}
