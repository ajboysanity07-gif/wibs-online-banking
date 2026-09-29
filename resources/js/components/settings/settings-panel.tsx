import type { ComponentPropsWithoutRef, ReactNode } from 'react';
import { SurfaceCard } from '@/components/surface-card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export function SettingsPanel({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <SurfaceCard padding="none" className="overflow-hidden">
            <div className="px-6 pt-6 pb-5">
                <h3 className="text-xl font-bold tracking-tight">{title}</h3>
                {description ? (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            {children}
        </SurfaceCard>
    );
}

// label / value / action grid; collapses to a stack on narrow screens.
// `children` render full-width beneath the row (inline forms, lists).
export function SettingsRow({
    label,
    value,
    action,
    children,
}: {
    label: string;
    value?: ReactNode;
    action?: ReactNode;
    children?: ReactNode;
}) {
    return (
        <div className="border-t border-border px-6 py-5">
            <div className="grid gap-x-6 gap-y-2 sm:grid-cols-[minmax(130px,210px)_minmax(0,1fr)_auto] sm:items-start">
                <p className="text-sm font-semibold text-muted-foreground">
                    {label}
                </p>
                <div className="min-w-0 text-sm">{value}</div>
                <div className="sm:justify-self-end">{action}</div>
            </div>
            {children ? <div className="mt-5">{children}</div> : null}
        </div>
    );
}

export function RowEditButton({
    className,
    ...props
}: ComponentPropsWithoutRef<typeof Button>) {
    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            className={cn(
                'border-primary text-primary hover:text-primary',
                className,
            )}
            {...props}
        />
    );
}

export function SettingsSwitch({
    checked,
    label,
    className,
    ...props
}: { checked: boolean; label: string } & ComponentPropsWithoutRef<'button'>) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            className={cn(
                'relative inline-flex h-[26px] w-[46px] shrink-0 items-center rounded-full border-2 border-transparent transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50',
                checked ? 'bg-primary' : 'bg-input',
                className,
            )}
            {...props}
        >
            <span
                className={cn(
                    'block size-5 rounded-full bg-card transition-transform',
                    checked ? 'translate-x-[22px]' : 'translate-x-0',
                )}
            />
        </button>
    );
}
