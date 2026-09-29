import type { ComponentPropsWithoutRef, ReactNode } from 'react';
import { cn } from '@/lib/utils';

type SurfaceCardProps = {
    children?: ReactNode;
    className?: string;
    variant?: 'default' | 'hero' | 'muted';
    padding?: 'none' | 'sm' | 'md' | 'lg';
} & ComponentPropsWithoutRef<'div'>;

const variantClasses: Record<
    NonNullable<SurfaceCardProps['variant']>,
    string
> = {
    default: 'border-border bg-card shadow-card',
    hero: 'border-border bg-card shadow-card',
    muted: 'border-border bg-muted shadow-card',
};

const paddingClasses: Record<
    NonNullable<SurfaceCardProps['padding']>,
    string
> = {
    none: 'p-0',
    sm: 'p-4',
    md: 'p-5',
    lg: 'p-6 sm:p-7 lg:p-8',
};

export function SurfaceCard({
    children,
    className,
    variant = 'default',
    padding = 'md',
    ...props
}: SurfaceCardProps) {
    return (
        <div
            className={cn(
                'rounded-xl border',
                variantClasses[variant],
                paddingClasses[padding],
                className,
            )}
            {...props}
        >
            {children}
        </div>
    );
}
