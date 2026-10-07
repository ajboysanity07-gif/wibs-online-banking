import { Link, router, usePage } from '@inertiajs/react';
import {
    Banknote,
    FileText,
    LayoutGrid,
    PiggyBank,
    Settings,
    ShieldCheck,
    Users,
} from 'lucide-react';
import { useEffect } from 'react';
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
    useSidebar,
} from '@/components/ui/sidebar';
import {
    memberLoanRequestsNavMatchOptions,
    memberLoansNavMatchOptions,
} from '@/lib/member-sidebar-nav-match';
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
import { index as loanRequestsIndex } from '@/routes/client/loan-requests';
import { edit as profileEdit } from '@/routes/profile';
import { index as staffLoanRequestsIndex } from '@/routes/staff/loan-requests';
import { index as staffMembersIndex } from '@/routes/staff/members';
import { index as staffReportedRequestsIndex } from '@/routes/staff/reported-requests';
import { index as superadminStaffIndex } from '@/routes/superadmin/staff';
import type { Auth, NavItem, WorkspaceName } from '@/types';
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
        ...memberLoansNavMatchOptions,
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
        ...memberLoanRequestsNavMatchOptions,
    },
    {
        title: 'Settings',
        href: profileEdit(),
        icon: Settings,
        match: 'section',
        matchPaths: [profileEdit(), '/settings'],
    },
];

const adminDashboardNavItem = (): NavItem => ({
    title: 'Admin Dashboard',
    href: adminDashboard(),
    icon: LayoutGrid,
});

// Staff share one set of pages; staff with an admin profile are routed to the
// /admin/* variants (same UI), everyone else to /staff/*.
const staffNavItems = (auth: Auth): NavItem[] => {
    const useAdminRoutes = auth.isAdmin && auth.hasActiveStaffAccess;
    const canWork = useAdminRoutes || auth.canAccessLoanWorkflow;
    const canViewMembers = useAdminRoutes || auth.canViewStaffMembers;

    return [
        ...(useAdminRoutes ? [adminDashboardNavItem()] : []),
        ...(canWork
            ? [
                  useAdminRoutes
                      ? {
                            title: 'Requests',
                            href: requestsIndex(),
                            icon: FileText,
                            match: 'section' as const,
                            excludeMatchPaths: [reportedRequests()],
                        }
                      : {
                            title: 'Requests',
                            href: staffLoanRequestsIndex(),
                            icon: FileText,
                            match: 'section' as const,
                            matchPaths: [
                                staffLoanRequestsIndex(),
                                '/staff/loan-requests',
                            ],
                        },
                  {
                      title: 'Reported Requests',
                      href: useAdminRoutes
                          ? reportedRequests()
                          : staffReportedRequestsIndex(),
                      icon: FileText,
                  },
              ]
            : []),
        ...(canWork && canViewMembers
            ? [
                  useAdminRoutes
                      ? {
                            title: 'Members',
                            href: membersIndex(),
                            icon: Users,
                            match: 'section' as const,
                            matchPaths: [membersIndex(), '/admin/members'],
                        }
                      : {
                            title: 'Members',
                            href: staffMembersIndex(),
                            icon: Users,
                            match: 'section' as const,
                            matchPaths: [staffMembersIndex(), '/staff/members'],
                        },
              ]
            : []),
    ];
};

export function AppSidebar() {
    const { auth } = usePage<PageProps>().props;
    const { setOpenMobile } = useSidebar();

    useEffect(
        () => router.on('navigate', () => setOpenMobile(false)),
        [setOpenMobile],
    );

    const activeWorkspace = auth.activeWorkspace;
    const hasMemberWorkspace = auth.availableWorkspaces.includes('member');
    const hasStaffWorkspace = auth.availableWorkspaces.includes('staff');
    const showMemberNav = activeWorkspace === 'member' && hasMemberWorkspace;
    const showStaffNav = activeWorkspace === 'staff' && hasStaffWorkspace;
    const staffWorkspaceItems: NavItem[] = showStaffNav
        ? [
              ...staffNavItems(auth),
              ...(auth.isSuperadmin && auth.hasActiveStaffAccess
                  ? [
                        {
                            title: 'Staff management',
                            href: superadminStaffIndex(),
                            icon: ShieldCheck,
                            match: 'section' as const,
                            matchPaths: [
                                superadminStaffIndex(),
                                '/superadmin/staff',
                            ],
                        },
                    ]
                  : []),
              ...(auth.isAdmin && auth.isSuperadmin && auth.hasActiveStaffAccess
                  ? [
                        {
                            title: 'Organization settings',
                            href: organizationSettings(),
                            icon: Settings,
                        },
                    ]
                  : []),
          ]
        : [];
    const primaryWorkspace: WorkspaceName | null =
        activeWorkspace ??
        (auth.availableWorkspaces.length === 1
            ? auth.availableWorkspaces[0]
            : null);
    const homeLink =
        primaryWorkspace === 'member'
            ? clientDashboard()
            : primaryWorkspace === 'staff'
              ? auth.isAdmin && auth.hasActiveStaffAccess
                  ? adminDashboard()
                  : auth.isSuperadmin
                    ? superadminStaffIndex()
                    : auth.canAccessLoanWorkflow
                      ? staffLoanRequestsIndex()
                      : profileEdit()
              : workspaceDashboard();

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={homeLink} prefetch>
                                <AppLogo
                                    className="group-data-[collapsible=icon]:[&>span]:hidden"
                                    iconClassName="size-10 group-data-[collapsible=icon]:size-8 shrink-0 rounded-full bg-sidebar-foreground object-contain p-1.5"
                                    titleClassName="text-sidebar-foreground"
                                    subtitleClassName="text-sidebar-foreground/75"
                                />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {showStaffNav && staffWorkspaceItems.length > 0 && (
                    <NavMain
                        items={staffWorkspaceItems}
                        label="Staff Workspace"
                        collapsibleStorageKey="sidebar-staff-workspace-collapsed"
                    />
                )}
                {showMemberNav && (
                    <NavMain
                        items={memberNavItems}
                        label="Member Portal"
                        collapsibleStorageKey="sidebar-member-portal-collapsed"
                    />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
