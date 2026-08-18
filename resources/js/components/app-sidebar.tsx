import { Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    BookOpen,
    FileText,
    Folder,
    LayoutGrid,
    PiggyBank,
    Settings,
    ShieldCheck,
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
import { index as superadminStaffIndex } from '@/routes/superadmin/staff';
import type { Auth, NavItem, WorkspaceName } from '@/types';
import AppLogo from './app-logo';
import { index as staffMembersIndex } from '@/routes/staff/members';
import { index as staffReportedRequestsIndex } from '@/routes/staff/reported-requests';

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

const legacyAdminNavItems = (): NavItem[] => [
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
];

const staffWorkflowNavItems = (auth: Auth): NavItem[] => [
    {
        title: 'Loan Workflow',
        href: staffLoanRequestsIndex(),
        icon: FileText,
        match: 'section',
        matchPaths: [staffLoanRequestsIndex(), '/staff/loan-requests'],
    },
    {
        title: 'Reported Requests',
        href: staffReportedRequestsIndex(),
        icon: FileText,
    },
    ...(auth.canViewStaffMembers
        ? [
              {
                  title: 'Members',
                  href: staffMembersIndex(),
                  icon: Users,
                  match: 'section' as const,
                  matchPaths: [staffMembersIndex(), '/staff/members'],
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
    const activeWorkspace = auth.activeWorkspace;
    const hasMemberWorkspace = auth.availableWorkspaces.includes('member');
    const hasStaffWorkspace = auth.availableWorkspaces.includes('staff');
    const showMemberNav = activeWorkspace === 'member' && hasMemberWorkspace;
    const showStaffNav = activeWorkspace === 'staff' && hasStaffWorkspace;
    const staffWorkspaceItems: NavItem[] = showStaffNav
        ? [
              ...(auth.canAccessLoanWorkflow
                  ? staffWorkflowNavItems(auth)
                  : []),
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
              ...(auth.isAdmin && auth.hasActiveStaffAccess
                  ? legacyAdminNavItems()
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
              ? auth.isSuperadmin
                  ? superadminStaffIndex()
                  : auth.isAdmin && auth.hasActiveStaffAccess
                    ? adminDashboard()
                    : auth.canAccessLoanWorkflow
                      ? staffLoanRequestsIndex()
                      : profileEdit()
              : workspaceDashboard();

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
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
