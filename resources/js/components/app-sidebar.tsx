import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    FileText,
    Folder,
    LayoutGrid,
    Settings,
    UserCheck,
    Users,
} from 'lucide-react';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as requestsIndex } from '@/routes/admin/requests';
import { organization as organizationSettings } from '@/routes/admin/settings';
import { pending as pendingApprovals } from '@/routes/admin/users';
import { index as membersIndex } from '@/routes/admin/watchlist';
import type { NavItem } from '@/types';
import AppLogo from './app-logo';

const baseNavItems: NavItem[] = [
    {
        title: 'Member profile',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Admin Dashboard',
        href: adminDashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Members',
        href: membersIndex(),
        icon: Users,
    },
    {
        title: 'Pending approvals',
        href: pendingApprovals(),
        icon: UserCheck,
    },
    {
        title: 'Requests',
        href: requestsIndex(),
        icon: FileText,
    },
    {
        title: 'Organization settings',
        href: organizationSettings(),
        icon: Settings,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { url } = usePage();
    const isAdminSection = url.startsWith('/admin');
    const mainNavItems = isAdminSection ? adminNavItems : baseNavItems;
    const homeLink = isAdminSection ? adminDashboard() : dashboard();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={homeLink} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
