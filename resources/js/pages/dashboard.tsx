import { Head, Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    FileText,
    LayoutGrid,
    PiggyBank,
    Settings,
    ShieldCheck,
    Users,
} from 'lucide-react';
import { PageShell } from '@/components/page-shell';
import { SurfaceCard } from '@/components/surface-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as requestsIndex } from '@/routes/admin/requests';
import { organization as organizationSettings } from '@/routes/admin/settings';
import { index as membersIndex } from '@/routes/admin/watchlist';
import {
    dashboard as clientDashboard,
    loans as clientLoans,
    savings as clientSavings,
} from '@/routes/client';
import { create as loanRequestCreate } from '@/routes/client/loan-requests';
import { edit as profileEdit } from '@/routes/profile';
import type { Auth, BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

type PageProps = {
    auth: Auth;
};

type QuickLink = {
    label: string;
    href: string;
    icon: typeof LayoutGrid;
};

export default function Dashboard() {
    const { auth } = usePage<PageProps>().props;
    const showMemberWorkspace = auth.hasMemberAccess;
    const showAdminWorkspace = auth.isAdmin;

    const memberLinks: QuickLink[] = [
        { label: 'Overview', href: clientDashboard().url, icon: LayoutGrid },
        { label: 'Loans', href: clientLoans().url, icon: Banknote },
        { label: 'Loan Security', href: clientSavings().url, icon: PiggyBank },
        {
            label: 'Loan Requests',
            href: loanRequestCreate().url,
            icon: FileText,
        },
        { label: 'Settings', href: profileEdit().url, icon: Settings },
    ];

    const adminLinks: QuickLink[] = [
        { label: 'Dashboard', href: adminDashboard().url, icon: LayoutGrid },
        { label: 'Members', href: membersIndex().url, icon: Users },
        { label: 'Requests', href: requestsIndex().url, icon: FileText },
    ];

    if (auth.isSuperadmin) {
        adminLinks.push({
            label: 'Organization settings',
            href: organizationSettings().url,
            icon: Settings,
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <PageShell size="wide" className="gap-8">
                <div className="space-y-2">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                        Workspace
                    </p>
                    <h1 className="text-3xl font-semibold">
                        Choose your workspace
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Switch between your member account and admin tools
                        without losing context.
                    </p>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    {showMemberWorkspace && (
                        <SurfaceCard
                            variant="default"
                            padding="lg"
                            className="flex h-full flex-col gap-6"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="space-y-1">
                                    <h2 className="text-lg font-semibold">
                                        My Account
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Member services, loan activity, and
                                        personal settings.
                                    </p>
                                </div>
                                <Badge variant="secondary">Member</Badge>
                            </div>

                            <div className="grid gap-2 text-sm text-muted-foreground">
                                {memberLinks.map((link) => (
                                    <Link
                                        key={link.label}
                                        href={link.href}
                                        className="flex items-center gap-2 transition-colors hover:text-foreground"
                                    >
                                        <link.icon className="h-4 w-4 text-muted-foreground" />
                                        <span>{link.label}</span>
                                    </Link>
                                ))}
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Button asChild>
                                    <Link href={clientDashboard().url}>
                                        Go to member dashboard
                                    </Link>
                                </Button>
                                <Button variant="ghost" asChild>
                                    <Link href={profileEdit().url}>
                                        Manage settings
                                    </Link>
                                </Button>
                            </div>
                        </SurfaceCard>
                    )}

                    {showAdminWorkspace && (
                        <SurfaceCard
                            variant="default"
                            padding="lg"
                            className="flex h-full flex-col gap-6"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="space-y-1">
                                    <h2 className="text-lg font-semibold">
                                        Admin Workspace
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Member management, requests, and
                                        operational controls.
                                    </p>
                                </div>
                                <Badge
                                    variant={
                                        auth.isSuperadmin
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {auth.isSuperadmin
                                        ? 'Superadmin'
                                        : 'Admin'}
                                </Badge>
                            </div>

                            <div className="grid gap-2 text-sm text-muted-foreground">
                                {adminLinks.map((link) => (
                                    <Link
                                        key={link.label}
                                        href={link.href}
                                        className="flex items-center gap-2 transition-colors hover:text-foreground"
                                    >
                                        <link.icon className="h-4 w-4 text-muted-foreground" />
                                        <span>{link.label}</span>
                                    </Link>
                                ))}
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Button asChild>
                                    <Link href={adminDashboard().url}>
                                        Open admin dashboard
                                    </Link>
                                </Button>
                                <Button variant="ghost" asChild>
                                    <Link href={membersIndex().url}>
                                        View members
                                    </Link>
                                </Button>
                            </div>
                        </SurfaceCard>
                    )}

                    {!showMemberWorkspace && !showAdminWorkspace && (
                        <SurfaceCard
                            variant="muted"
                            padding="lg"
                            className="flex flex-col gap-4"
                        >
                            <ShieldCheck className="h-5 w-5 text-muted-foreground" />
                            <div className="space-y-1">
                                <h2 className="text-lg font-semibold">
                                    Access pending
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Your account does not have an active
                                    workspace assigned yet. Please contact
                                    support for assistance.
                                </p>
                            </div>
                        </SurfaceCard>
                    )}
                </div>
            </PageShell>
        </AppLayout>
    );
}
