import { Head, Link, usePage } from '@inertiajs/react';
import { LayoutGrid, ShieldCheck, Users } from 'lucide-react';
import { PageShell } from '@/components/page-shell';
import { SurfaceCard } from '@/components/surface-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as membersIndex } from '@/routes/admin/watchlist';
import {
    dashboard as clientDashboard,
} from '@/routes/client';
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

export default function Dashboard() {
    const { auth } = usePage<PageProps>().props;
    const showMemberWorkspace = auth.hasMemberAccess;
    const showAdminWorkspace = auth.isAdmin;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <PageShell size="wide" className="gap-6 lg:gap-8">
                <div className="space-y-3">
                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                        Workspaces
                    </p>
                    <h1 className="text-3xl font-semibold">
                        Switch workspaces
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Pick the context you want to work in. Your sidebar
                        updates to show the full navigation for each workspace.
                    </p>
                </div>

                <div className="grid gap-5 lg:grid-cols-2">
                    {showMemberWorkspace && (
                        <SurfaceCard
                            variant="default"
                            padding="lg"
                            className="flex h-full flex-col gap-5"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                                        <LayoutGrid className="h-4 w-4" />
                                        <span>Member workspace</span>
                                    </div>
                                    <h2 className="text-lg font-semibold">
                                        My Account
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Everyday member tools, account activity,
                                        and personal details.
                                    </p>
                                </div>
                                <Badge variant="secondary">Member</Badge>
                            </div>

                            <div className="space-y-2">
                                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                                    Best for
                                </p>
                                <ul className="grid gap-2 text-sm text-muted-foreground">
                                    <li className="flex items-start gap-2">
                                        <span className="mt-2 h-1.5 w-1.5 rounded-full bg-muted-foreground/60" />
                                        <span>
                                            Tracking loan status and savings
                                            health.
                                        </span>
                                    </li>
                                    <li className="flex items-start gap-2">
                                        <span className="mt-2 h-1.5 w-1.5 rounded-full bg-muted-foreground/60" />
                                        <span>
                                            Submitting requests and updating
                                            your profile.
                                        </span>
                                    </li>
                                </ul>
                            </div>

                            <div className="mt-auto flex flex-wrap gap-2">
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
                            className="flex h-full flex-col gap-5"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                                        <Users className="h-4 w-4" />
                                        <span>Admin workspace</span>
                                    </div>
                                    <h2 className="text-lg font-semibold">
                                        Admin Workspace
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Oversight tools for member operations,
                                        approvals, and controls.
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

                            <div className="space-y-2">
                                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                                    Best for
                                </p>
                                <ul className="grid gap-2 text-sm text-muted-foreground">
                                    <li className="flex items-start gap-2">
                                        <span className="mt-2 h-1.5 w-1.5 rounded-full bg-muted-foreground/60" />
                                        <span>
                                            Reviewing member activity and
                                            approvals.
                                        </span>
                                    </li>
                                    <li className="flex items-start gap-2">
                                        <span className="mt-2 h-1.5 w-1.5 rounded-full bg-muted-foreground/60" />
                                        <span>
                                            Monitoring operational signals and
                                            exceptions.
                                        </span>
                                    </li>
                                </ul>
                            </div>

                            <div className="mt-auto flex flex-wrap gap-2">
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
