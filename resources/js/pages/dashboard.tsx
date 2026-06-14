import { Head, router, usePage } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { PageShell } from '@/components/page-shell';
import { SurfaceCard } from '@/components/surface-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { switchMethod as switchWorkspace } from '@/routes/workspace';
import type {
    Auth,
    BreadcrumbItem,
    LoanWorkflowRole,
    WorkspaceName,
} from '@/types';

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
    const [switchingWorkspace, setSwitchingWorkspace] =
        useState<WorkspaceName | null>(null);
    const showMemberWorkspace = auth.availableWorkspaces.includes('member');
    const showStaffWorkspace = auth.availableWorkspaces.includes('staff');
    const staffRoleLabels = resolveStaffRoleLabels(auth);

    const openWorkspace = (workspace: WorkspaceName) => {
        setSwitchingWorkspace(workspace);

        router.post(
            switchWorkspace.url(),
            { workspace },
            {
                preserveScroll: true,
                onFinish: () => setSwitchingWorkspace(null),
            },
        );
    };

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
                        Pick the context you want to work in. Navigation and
                        landing pages follow your selected workspace, while
                        backend authorization stays enforced separately.
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
                                        <UserRound className="h-4 w-4" />
                                        <span>Member Portal</span>
                                    </div>
                                    <h2 className="text-lg font-semibold">
                                        Member Portal
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Manage your personal account, loan
                                        applications, documents, and status.
                                    </p>
                                </div>
                                <Badge variant="secondary">Member</Badge>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <Badge variant="outline">Loans</Badge>
                                <Badge variant="outline">Documents</Badge>
                                <Badge variant="outline">Status</Badge>
                            </div>

                            <div className="mt-auto">
                                <Button
                                    type="button"
                                    onClick={() => openWorkspace('member')}
                                    disabled={switchingWorkspace !== null}
                                >
                                    Open Workspace
                                </Button>
                            </div>
                        </SurfaceCard>
                    )}

                    {showStaffWorkspace && (
                        <SurfaceCard
                            variant="default"
                            padding="lg"
                            className="flex h-full flex-col gap-5"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                                        <BriefcaseBusiness className="h-4 w-4" />
                                        <span>Staff Workspace</span>
                                    </div>
                                    <h2 className="text-lg font-semibold">
                                        Staff Workspace
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Review and manage loan applications
                                        according to your assigned staff roles.
                                    </p>
                                </div>
                                <Badge variant="default">Staff</Badge>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {staffRoleLabels.map((roleLabel) => (
                                    <Badge
                                        key={roleLabel}
                                        variant={
                                            roleLabel === 'Superadmin'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {roleLabel}
                                    </Badge>
                                ))}
                                {staffRoleLabels.length === 0 ? (
                                    <Badge variant="outline">
                                        Staff access
                                    </Badge>
                                ) : null}
                            </div>

                            <div className="mt-auto">
                                <Button
                                    type="button"
                                    onClick={() => openWorkspace('staff')}
                                    disabled={switchingWorkspace !== null}
                                >
                                    Open Workspace
                                </Button>
                            </div>
                        </SurfaceCard>
                    )}

                    {!showMemberWorkspace && !showStaffWorkspace && (
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

function resolveStaffRoleLabels(auth: Auth): string[] {
    const labels = new Set<string>();

    if (auth.isSuperadmin) {
        labels.add('Superadmin');
    }

    if (auth.isAdmin && !auth.isSuperadmin) {
        labels.add('Admin');
    }

    auth.loanWorkflowRoles.forEach((role) => {
        labels.add(workflowRoleLabel(role));
    });

    return Array.from(labels);
}

function workflowRoleLabel(role: LoanWorkflowRole): string {
    if (role === 'superadmin') {
        return 'Superadmin';
    }

    if (role === 'loan_officer') {
        return 'Loan Officer';
    }

    return 'Loan Manager';
}
