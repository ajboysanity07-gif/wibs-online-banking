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
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="space-y-2">
                <h1 className="text-[2rem] leading-tight font-bold tracking-tight">
                    {title}
                </h1>
                {subtitle ? (
                    <p className="text-[15px] text-muted-foreground">
                        {subtitle}
                    </p>
                ) : null}
                {meta ? (
                    <div className="pt-1 text-xs text-foreground">{meta}</div>
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
