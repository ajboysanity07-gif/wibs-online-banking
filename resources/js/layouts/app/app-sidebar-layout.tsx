import { usePage } from '@inertiajs/react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
    statusLabel,
}: AppLayoutProps) {
    const { component } = usePage();

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-clip">
                <AppSidebarHeader
                    breadcrumbs={breadcrumbs}
                    statusLabel={statusLabel}
                />
                <div
                    key={component}
                    className="animate-page-in flex flex-1 flex-col"
                >
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
