import { Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    BookOpen,
    FileText,
    Folder,
    LayoutGrid,
    PiggyBank,
    Settings,
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
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as requestsIndex } from '@/routes/admin/requests';
import { organization as organizationSettings } from '@/routes/admin/settings';
import { index as membersIndex } from '@/routes/admin/watchlist';
import {
    dashboard as clientDashboard,
    loans as clientLoans,
    savings as clientSavings,
} from '@/routes/client';
import type { Auth, NavItem } from '@/types';
import AppLogo from './app-logo';

type PageProps = {
    auth: Auth;
};

const baseNavItems: NavItem[] = [
    {
        title: 'Member profile',
        href: clientDashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Loans',
        href: clientLoans(),
        icon: Banknote,
        match: 'section',
    },
    {
        title: 'Loan Security',
        href: clientSavings(),
        icon: PiggyBank,
        match: 'section',
    },
];

const adminNavItems = (isSuperadmin: boolean): NavItem[] => [
    {
        title: 'Admin Dashboard',
        href: adminDashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Members',
        href: membersIndex(),
        icon: Users,
        match: 'section',
        matchPaths: [membersIndex(), '/admin/members'],
    },
    {
        title: 'Requests',
        href: requestsIndex(),
        icon: FileText,
        match: 'section',
    },
    ...(isSuperadmin
        ? [
              {
                  title: 'Organization settings',
                  href: organizationSettings(),
                  icon: Settings,
              },
          ]
        : []),
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
    const { auth } = usePage<PageProps>().props;
    const isAdminUser = auth.isAdmin;
    const mainNavItems = isAdminUser
        ? adminNavItems(auth.isSuperadmin)
        : baseNavItems;
    const homeLink = isAdminUser ? adminDashboard() : clientDashboard();

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
