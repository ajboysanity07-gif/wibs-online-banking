import type { ReactNode } from 'react';

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
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div className="space-y-1">
                <h1 className="text-2xl font-semibold">{title}</h1>
                {subtitle ? (
                    <p className="text-sm text-muted-foreground">{subtitle}</p>
                ) : null}
                {meta ? (
                    <p className="text-xs text-muted-foreground">{meta}</p>
                ) : null}
            </div>
            {actions ? (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            ) : null}
        </div>
    );
}
