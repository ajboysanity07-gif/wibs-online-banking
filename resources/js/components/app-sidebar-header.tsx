import { usePage } from '@inertiajs/react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { NotificationBell } from '@/components/notification-bell';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { Auth, BreadcrumbItem as BreadcrumbItemType } from '@/types';

type PageProps = {
    auth: Auth;
};

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { auth } = usePage<PageProps>().props;

    return (
        <header className="flex h-14 shrink-0 items-center gap-2 border-b border-border bg-background px-6 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="ml-auto flex items-center gap-3">
                <span className="hidden items-center gap-2 rounded-full border border-border bg-secondary px-3 py-1 text-xs font-semibold text-secondary-foreground sm:flex">
                    <span className="size-2 rounded-full bg-primary" />
                    Secure session
                </span>
                {auth.isAdmin || auth.hasMemberAccess ? (
                    <NotificationBell />
                ) : null}
            </div>
        </header>
    );
}
