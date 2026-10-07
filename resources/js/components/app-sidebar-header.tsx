import { usePage } from '@inertiajs/react';
import { Moon, Sun } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { NotificationBell } from '@/components/notification-bell';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/hooks/use-appearance';
import type { Auth, BreadcrumbItem as BreadcrumbItemType } from '@/types';

type PageProps = {
    auth: Auth;
};

export function AppSidebarHeader({
    breadcrumbs = [],
    statusLabel = 'Secure session',
}: {
    breadcrumbs?: BreadcrumbItemType[];
    statusLabel?: string;
}) {
    const { auth } = usePage<PageProps>().props;
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const dark = resolvedAppearance === 'dark';

    return (
        <header className="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-2 border-b border-border bg-background px-4 md:px-7">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1 size-11 shrink-0 lg:size-7" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="ml-auto flex shrink-0 items-center gap-3">
                <span className="hidden items-center gap-2 rounded-full border border-border bg-card px-3 py-1 text-xs font-semibold whitespace-nowrap sm:flex">
                    <span className="size-2 rounded-full bg-primary" />
                    {statusLabel}
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-10 bg-card"
                    aria-label="Toggle dark mode"
                    title="Toggle dark mode"
                    onClick={() => updateAppearance(dark ? 'light' : 'dark')}
                >
                    {dark ? (
                        <Sun className="size-[18px]" />
                    ) : (
                        <Moon className="size-[18px]" />
                    )}
                </Button>
                {auth.isAdmin || auth.hasMemberAccess ? (
                    <NotificationBell />
                ) : null}
            </div>
        </header>
    );
}
