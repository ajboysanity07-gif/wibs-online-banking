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
        <header className="bg-background border-border flex h-14 shrink-0 items-center gap-2 border-b px-6 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            {auth.isAdmin || auth.hasMemberAccess ? (
                <div className="ml-auto flex items-center gap-2">
                    <NotificationBell />
                </div>
            ) : null}
        </header>
    );
}
