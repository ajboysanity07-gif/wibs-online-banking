import type { ComponentPropsWithoutRef, ReactNode } from 'react';
import { SurfaceCard } from '@/components/surface-card';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
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
    ...props
}: { checked: boolean; label: string } & Omit<
    ComponentPropsWithoutRef<typeof Switch>,
    'checked'
>) {
    return <Switch checked={checked} aria-label={label} {...props} />;
}
