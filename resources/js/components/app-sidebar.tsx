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
import { dashboard as workspaceDashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    index as requestsIndex,
    reported as reportedRequests,
} from '@/routes/admin/requests';
import { organization as organizationSettings } from '@/routes/admin/settings';
import { index as membersIndex } from '@/routes/admin/watchlist';
import {
    dashboard as clientDashboard,
    loans as clientLoans,
    savings as clientSavings,
} from '@/routes/client';
import {
    create as loanRequestCreate,
    index as loanRequestsIndex,
} from '@/routes/client/loan-requests';
import { edit as profileEdit } from '@/routes/profile';
import type { Auth, NavItem } from '@/types';
import AppLogo from './app-logo';

type PageProps = {
    auth: Auth;
};

const memberNavItems: NavItem[] = [
    {
        title: 'Overview',
        href: clientDashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Loans',
        href: clientLoans(),
        icon: Banknote,
    },
    {
        title: 'Loan Security',
        href: clientSavings(),
        icon: PiggyBank,
        match: 'section',
    },
    {
        title: 'Loan requests',
        href: loanRequestsIndex(),
        icon: FileText,
        match: 'section',
        matchPaths: [loanRequestsIndex(), loanRequestCreate()],
    },
    {
        title: 'Settings',
        href: profileEdit(),
        icon: Settings,
        match: 'section',
        matchPaths: [profileEdit(), '/settings'],
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
        excludeMatchPaths: [reportedRequests()],
    },
    {
        title: 'Reported Requests',
        href: reportedRequests(),
        icon: FileText,
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
    const hasMemberAccess = auth.hasMemberAccess;
    const showAdminNav = auth.isAdmin;
    const showMemberNav = hasMemberAccess;
    const adminItems = showAdminNav
        ? adminNavItems(auth.isSuperadmin)
        : [];
    const memberItems = showMemberNav ? memberNavItems : [];
    const adminLabel = 'Admin Workspace';
    const memberLabel = 'My Account';
    const homeLink =
        auth.experience === 'user-admin'
            ? workspaceDashboard()
            : auth.isAdmin
              ? adminDashboard()
              : clientDashboard();

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
                {showAdminNav && (
                    <NavMain items={adminItems} label={adminLabel} />
                )}
                {showMemberNav && (
                    <NavMain items={memberItems} label={memberLabel} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
