import type { ReactNode } from 'react';
import { SurfaceCard } from '@/components/surface-card';

type MemberDetailPageHeaderProps = {
    title: string;
    subtitle?: ReactNode;
    meta?: ReactNode;
    actions?: ReactNode;
};

export function MemberDetailPageHeader({
    title,
    subtitle,
    meta,
    actions,
}: MemberDetailPageHeaderProps) {
    return (
        <SurfaceCard variant="hero" padding="lg">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div className="space-y-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    {subtitle ? (
                        <p className="text-sm text-muted-foreground">
                            {subtitle}
                        </p>
                    ) : null}
                    {meta ? (
                        <div className="text-xs text-muted-foreground">
                            {meta}
                        </div>
                    ) : null}
                </div>
                {actions ? (
                    <div className="flex flex-wrap items-center gap-2">
                        {actions}
                    </div>
                ) : null}
            </div>
        </SurfaceCard>
    );
}
