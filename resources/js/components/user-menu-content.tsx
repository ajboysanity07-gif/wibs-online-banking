import { Link, router } from '@inertiajs/react';
import {
    BriefcaseBusiness,
    LayoutGrid,
    LogOut,
    Settings,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuShortcut,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { dashboard as workspaceDashboard, logout } from '@/routes';
import { edit } from '@/routes/profile';
import { switchMethod as switchWorkspace } from '@/routes/workspace';
import type { Auth, User, WorkspaceName } from '@/types';

type Props = {
    user: User;
    auth: Auth;
};

const workspaceLabels: Record<WorkspaceName, string> = {
    member: 'Member Portal',
    staff: 'Staff Workspace',
};

export function UserMenuContent({ user, auth }: Props) {
    const cleanup = useMobileNavigation();
    const [switchingWorkspace, setSwitchingWorkspace] =
        useState<WorkspaceName | null>(null);

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    const handleWorkspaceSwitch = (workspace: WorkspaceName) => {
        cleanup();
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
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Settings
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            {auth.hasMultipleWorkspaces ? (
                <>
                    <DropdownMenuSeparator />
                    <DropdownMenuGroup>
                        <DropdownMenuLabel className="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                            Workspace
                        </DropdownMenuLabel>
                        <DropdownMenuItem
                            disabled={
                                switchingWorkspace !== null ||
                                auth.activeWorkspace === 'member'
                            }
                            onSelect={() => handleWorkspaceSwitch('member')}
                        >
                            <UserRound className="mr-2" />
                            Switch to Member Portal
                            {auth.activeWorkspace === 'member' ? (
                                <DropdownMenuShortcut>
                                    Active
                                </DropdownMenuShortcut>
                            ) : null}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            disabled={
                                switchingWorkspace !== null ||
                                auth.activeWorkspace === 'staff'
                            }
                            onSelect={() => handleWorkspaceSwitch('staff')}
                        >
                            <BriefcaseBusiness className="mr-2" />
                            Switch to Staff Workspace
                            {auth.activeWorkspace === 'staff' ? (
                                <DropdownMenuShortcut>
                                    Active
                                </DropdownMenuShortcut>
                            ) : null}
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link
                                className="block w-full cursor-pointer"
                                href={
                                    workspaceDashboard({
                                        query: { choose: 1 },
                                    }).url
                                }
                                prefetch
                                onClick={cleanup}
                            >
                                <LayoutGrid className="mr-2" />
                                View all workspaces
                            </Link>
                        </DropdownMenuItem>
                        {auth.activeWorkspace ? (
                            <DropdownMenuItem disabled>
                                Current: {workspaceLabels[auth.activeWorkspace]}
                            </DropdownMenuItem>
                        ) : null}
                    </DropdownMenuGroup>
                </>
            ) : null}
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
