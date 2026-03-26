import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type PageShellProps = {
    children: ReactNode;
    size?: 'default' | 'wide' | 'full';
    className?: string;
};

const sizeClasses: Record<NonNullable<PageShellProps['size']>, string> = {
    default: 'max-w-6xl',
    wide: 'max-w-7xl',
    full: 'max-w-screen-2xl',
};

export function PageShell({
    children,
    size = 'default',
    className,
}: PageShellProps) {
    return (
        <div
            className={cn(
                'mx-auto flex w-full flex-col gap-6 px-4 pb-10 pt-6',
                sizeClasses[size],
                className,
            )}
        >
            {children}
        </div>
    );
}
