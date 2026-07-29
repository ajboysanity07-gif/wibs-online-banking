import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import axios from 'axios';
import { Copy, History, MoreHorizontal, Search, ShieldCheck, ShieldOff, UserPlus, Users } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { PageHero } from '@/components/page-hero';
import { PageShell } from '@/components/page-shell';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable } from '@/components/ui/data-table';
import {
    DataTablePagination,
    DataTablePaginationSkeleton,
} from '@/components/ui/data-table-pagination';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PasswordInput } from '@/components/ui/password-input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    TableSkeleton,
    type TableSkeletonColumn,
} from '@/components/ui/table-skeleton';
import { useStaffDirectory } from '@/hooks/admin/use-staff-directory';
import AppLayout from '@/layouts/app-layout';
import { mapValidationErrors } from '@/lib/api';
import { adminApi } from '@/lib/api/admin';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { index as superadminStaffIndex } from '@/routes/superadmin/staff';
import type { BreadcrumbItem } from '@/types';
import type {
    EditableStaffRoleName,
    StaffAccessStatus,
    StaffAccount,
    StaffHistoryEntry,
} from '@/types/admin';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Staff management',
        href: superadminStaffIndex().url,
    },
];

const tableSkeletonColumns: TableSkeletonColumn[] = [
    { headerClassName: 'w-32', cellClassName: 'w-40' },
    { headerClassName: 'w-20', cellClassName: 'w-16' },
    { headerClassName: 'w-28', cellClassName: 'w-36' },
    { headerClassName: 'w-28', cellClassName: 'w-28' },
    { headerClassName: 'w-36', cellClassName: 'w-40' },
    { headerClassName: 'w-12', cellClassName: 'h-8 w-10', align: 'right' },
];

const textareaClassName =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex min-h-24 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50';

const editableRoleOptions: Array<{
    value: EditableStaffRoleName;
    label: string;
    description: string;
}> = [
    {
        value: 'superadmin',
        label: 'Superadmin',
        description: 'Manage staff, monitor loan applications, and access existing Superadmin pages.',
    },
    {
        value: 'loan_processor',
        label: 'Loan Processor',
        description: 'Review applications, request revisions, reject, and recommend approval.',
    },
    {
        value: 'loan_manager',
        label: 'Loan Manager',
        description: 'Approve or decline applications after officer recommendation.',
    },
];

const formatDateTime = (value?: string | null): string => {
    if (!value) {
        return '--';
    }

    return new Date(value).toLocaleString();
};

const roleBadgeVariant = (
    roleName: string,
): 'default' | 'secondary' | 'outline' => {
    if (roleName === 'superadmin') {
        return 'default';
    }

    if (roleName === 'member') {
        return 'outline';
    }

    return 'secondary';
};

const staffAccessVariant = (
    status: StaffAccessStatus,
): 'secondary' | 'destructive' | 'outline' => {
    if (status === 'active') {
        return 'secondary';
    }

    if (status === 'suspended') {
        return 'destructive';
    }

    return 'outline';
};

const staffAccessLabel = (status: StaffAccessStatus): string => {
    if (status === 'active') {
        return 'Active';
    }

    if (status === 'suspended') {
        return 'Suspended';
    }

    return 'Not managed';
};

const describeLastChange = (staff: StaffAccount): string => {
    if (!staff.last_change) {
        return 'No recorded staff changes yet.';
    }

    const actor = staff.last_change.actor_name
        ? ` by ${staff.last_change.actor_name}`
        : '';

    return `${staff.last_change.action_label}${actor} on ${formatDateTime(
        staff.last_change.created_at,
    )}`;
};

const historyStatusLabel = (status: StaffAccessStatus | null): string => {
    if (status === null) {
        return '--';
    }

    return staffAccessLabel(status);
};

type FieldErrors = Record<string, string>;

type CreateStaffForm = {
    username: string;
    email: string;
    phoneno: string;
    password: string;
    password_confirmation: string;
    roles: EditableStaffRoleName[];
    reason: string;
};

type RoleMutationState = {
    open: boolean;
    user: StaffAccount | null;
    role: EditableStaffRoleName;
    operation: 'assign' | 'remove';
    reason: string;
    processing: boolean;
    errors: FieldErrors;
};

type StaffAccessMutationState = {
    open: boolean;
    user: StaffAccount | null;
    action: 'suspend' | 'reactivate';
    reason: string;
    processing: boolean;
    errors: FieldErrors;
};

type ResetPasswordMutationState = {
    open: boolean;
    user: StaffAccount | null;
    reason: string;
    processing: boolean;
    errors: FieldErrors;
};

type ResetPasswordResult = {
    user: StaffAccount;
    temporaryPassword: string;
};

const initialCreateForm: CreateStaffForm = {
    username: '',
    email: '',
    phoneno: '',
    password: '',
    password_confirmation: '',
    roles: [],
    reason: '',
};

const initialRoleMutationState: RoleMutationState = {
    open: false,
    user: null,
    role: 'loan_processor',
    operation: 'assign',
    reason: '',
    processing: false,
    errors: {},
};

const initialAccessMutationState: StaffAccessMutationState = {
    open: false,
    user: null,
    action: 'suspend',
    reason: '',
    processing: false,
    errors: {},
};

const initialResetPasswordMutationState: ResetPasswordMutationState = {
    open: false,
    user: null,
    reason: '',
    processing: false,
    errors: {},
};

type PromoteDialogState = {
    open: boolean;
    step: 1 | 2;
    stepDirection: 'forward' | 'back';
    query: string;
    searchLoading: boolean;
    searchError: string | null;
    searchResults: StaffAccount[];
    selectedMember: StaffAccount | null;
    role: EditableStaffRoleName | '';
    reason: string;
    processing: boolean;
    errors: FieldErrors;
};

const initialPromoteDialogState: PromoteDialogState = {
    open: false,
    step: 1,
    stepDirection: 'forward',
    query: '',
    searchLoading: false,
    searchError: null,
    searchResults: [],
    selectedMember: null,
    role: '',
    reason: '',
    processing: false,
    errors: {},
};

function MobileStaffCard({
    staff,
    onOpenHistory,
    onOpenRoleMutation,
    onOpenAccessMutation,
    onOpenResetPassword,
}: {
    staff: StaffAccount;
    onOpenHistory: (staff: StaffAccount) => void;
    onOpenRoleMutation: (
        staff: StaffAccount,
        role: EditableStaffRoleName,
        operation: 'assign' | 'remove',
    ) => void;
    onOpenAccessMutation: (
        staff: StaffAccount,
        action: 'suspend' | 'reactivate',
    ) => void;
    onOpenResetPassword: (staff: StaffAccount) => void;
}) {
    const assignedEditableRoles = staff.roles.filter((role) => role.editable);
    const assignableRoles = editableRoleOptions.filter(
        (role) =>
            !assignedEditableRoles.some(
                (assignedRole) => assignedRole.name === role.value,
            ),
    );

    return (
        <SurfaceCard variant="default" padding="sm">
            <div className="flex items-start justify-between gap-3">
                <div className="space-y-1">
                    <p className="text-sm font-semibold">{staff.display_name}</p>
                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <span>{staff.display_code}</span>
                        {staff.username ? <span>{staff.username}</span> : null}
                    </div>
                </div>
                <Badge variant={staffAccessVariant(staff.staff_access_status)}>
                    {staffAccessLabel(staff.staff_access_status)}
                </Badge>
            </div>

            <div className="mt-3 space-y-3 rounded-xl border border-border/30 bg-muted/30 p-3 text-xs">
                <div className="flex items-center justify-between gap-3">
                    <span className="text-muted-foreground">Member access</span>
                    <span className="text-sm font-medium">
                        {staff.has_member_access ? 'Yes' : 'No'}
                    </span>
                </div>
                <div className="flex items-center justify-between gap-3">
                    <span className="text-muted-foreground">Account no</span>
                    <span className="text-sm font-medium">
                        {staff.acctno ?? '--'}
                    </span>
                </div>
                <div className="space-y-2">
                    <span className="text-muted-foreground">Roles</span>
                    <div className="flex flex-wrap gap-2">
                        {staff.roles.length === 0 ? (
                            <Badge variant="outline">No staff roles yet</Badge>
                        ) : (
                            staff.roles.map((role) => (
                                <Badge
                                    key={`${staff.user_id}-${role.name}`}
                                    variant={roleBadgeVariant(role.name)}
                                >
                                    {role.label}
                                </Badge>
                            ))
                        )}
                    </div>
                </div>
                <div className="space-y-1">
                    <span className="text-muted-foreground">
                        Last role or access change
                    </span>
                    <p className="text-sm font-medium">{describeLastChange(staff)}</p>
                </div>
            </div>

            <div className="mt-3 flex items-center justify-between gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => onOpenHistory(staff)}
                >
                    <History className="h-4 w-4" />
                    History
                </Button>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button type="button" variant="ghost" size="icon">
                            <MoreHorizontal className="h-4 w-4" />
                            <span className="sr-only">Manage staff</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                        <DropdownMenuLabel>Manage roles</DropdownMenuLabel>
                        {assignableRoles.length === 0 ? (
                            <DropdownMenuItem disabled>
                                All editable roles assigned
                            </DropdownMenuItem>
                        ) : (
                            assignableRoles.map((role) => (
                                <DropdownMenuItem
                                    key={`assign-${staff.user_id}-${role.value}`}
                                    onSelect={() =>
                                        onOpenRoleMutation(
                                            staff,
                                            role.value,
                                            'assign',
                                        )
                                    }
                                >
                                    Assign {role.label}
                                </DropdownMenuItem>
                            ))
                        )}
                        {assignedEditableRoles.length > 0 ? (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuLabel>Remove roles</DropdownMenuLabel>
                                {assignedEditableRoles.map((role) => (
                                    <DropdownMenuItem
                                        key={`remove-${staff.user_id}-${role.name}`}
                                        variant={
                                            role.name === 'superadmin'
                                                ? 'destructive'
                                                : 'default'
                                        }
                                        onSelect={() =>
                                            onOpenRoleMutation(
                                                staff,
                                                role.name as EditableStaffRoleName,
                                                'remove',
                                            )
                                        }
                                    >
                                        Remove {role.label}
                                    </DropdownMenuItem>
                                ))}
                            </>
                        ) : null}
                        {staff.staff_access_status !== 'not_staff' ? (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    variant={
                                        staff.staff_access_status === 'active'
                                            ? 'destructive'
                                            : 'default'
                                    }
                                    onSelect={() =>
                                        onOpenAccessMutation(
                                            staff,
                                            staff.staff_access_status ===
                                                'suspended'
                                                ? 'reactivate'
                                                : 'suspend',
                                        )
                                    }
                                >
                                    {staff.staff_access_status === 'suspended'
                                        ? 'Reactivate staff access'
                                        : 'Suspend staff access'}
                                </DropdownMenuItem>
                            </>
                        ) : null}
                        {staff.staff_access_status !== 'not_staff' ? (
                            <>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onSelect={() => onOpenResetPassword(staff)}
                                >
                                    Reset password
                                </DropdownMenuItem>
                            </>
                        ) : null}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </SurfaceCard>
    );
}

export default function SuperadminStaffPage() {
    const [search, setSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState<
        EditableStaffRoleName | 'all'
    >('all');
    const [accessFilter, setAccessFilter] = useState<
        'all' | 'active' | 'suspended'
    >('all');
    const [page, setPage] = useState(1);
    const [perPage] = useState(10);
    const [refreshKey, setRefreshKey] = useState(0);

    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [createForm, setCreateForm] =
        useState<CreateStaffForm>(initialCreateForm);
    const [createErrors, setCreateErrors] = useState<FieldErrors>({});
    const [createProcessing, setCreateProcessing] = useState(false);

    const [roleMutation, setRoleMutation] = useState<RoleMutationState>(
        initialRoleMutationState,
    );
    const [accessMutation, setAccessMutation] =
        useState<StaffAccessMutationState>(initialAccessMutationState);

    const [resetPasswordMutation, setResetPasswordMutation] =
        useState<ResetPasswordMutationState>(
            initialResetPasswordMutationState,
        );
    const [resetPasswordResult, setResetPasswordResult] =
        useState<ResetPasswordResult | null>(null);

    const [historyUser, setHistoryUser] = useState<StaffAccount | null>(null);
    const [historyItems, setHistoryItems] = useState<StaffHistoryEntry[]>([]);
    const [historyLoading, setHistoryLoading] = useState(false);
    const [historyError, setHistoryError] = useState<string | null>(null);

    const [promoteDialog, setPromoteDialog] = useState<PromoteDialogState>(
        initialPromoteDialogState,
    );

    const { items, meta, loading, error } = useStaffDirectory({
        search,
        role: roleFilter,
        access: accessFilter,
        page,
        perPage,
        refreshKey,
    });

    useEffect(() => {
        if (!historyUser) {
            setHistoryItems([]);
            setHistoryError(null);

            return;
        }

        const controller = new AbortController();
        setHistoryLoading(true);
        setHistoryError(null);

        void adminApi
            .getStaffHistory(historyUser.user_id, 50, controller.signal)
            .then((result) => {
                setHistoryItems(result);
                setHistoryLoading(false);
            })
            .catch((requestError) => {
                if (controller.signal.aborted) {
                    return;
                }

                setHistoryLoading(false);
                setHistoryError('Unable to load staff history right now.');
                showErrorToast(
                    requestError,
                    'Failed to load staff history.',
                );
            });

        return () => {
            controller.abort();
        };
    }, [historyUser]);

    useEffect(() => {
        if (!promoteDialog.open) return;
        if (promoteDialog.step === 2) return;

        const query = promoteDialog.query.trim();
        const controller = new AbortController();

        setPromoteDialog((current) => {
            if (current.step === 2) return current;
            return { ...current, searchLoading: true, searchError: null };
        });

        const timer = setTimeout(async () => {
            try {
                const members = await adminApi.searchMembers(query, controller.signal);

                if (!controller.signal.aborted) {
                    setPromoteDialog((current) => {
                        if (current.step === 2) return current;
                        return { ...current, searchLoading: false, searchResults: members };
                    });
                }
            } catch {
                if (!controller.signal.aborted) {
                    setPromoteDialog((current) => ({
                        ...current,
                        searchLoading: false,
                        searchError: 'Unable to load members right now.',
                    }));
                }
            }
        }, query === '' ? 0 : 300);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [promoteDialog.query, promoteDialog.open]);

    const showSkeleton = loading && items.length === 0;
    const searchValue = search.trim();
    const filterCount = [
        searchValue !== '',
        roleFilter !== 'all',
        accessFilter !== 'all',
    ].filter(Boolean).length;
    const totalResults = meta.total;
    const pageStart =
        totalResults > 0 ? (meta.page - 1) * meta.perPage + 1 : 0;
    const pageEnd =
        totalResults > 0
            ? Math.min(meta.page * meta.perPage, totalResults)
            : 0;
    const resultsLabel =
        totalResults > 0
            ? `Showing ${pageStart}-${pageEnd} of ${totalResults} staff accounts`
            : 'No staff accounts found.';

    const refreshDirectory = () => {
        setRefreshKey((current) => current + 1);
    };

    const resetCreateDialog = () => {
        setCreateDialogOpen(false);
        setCreateForm(initialCreateForm);
        setCreateErrors({});
        setCreateProcessing(false);
    };

    const resetPromoteDialog = () => {
        setPromoteDialog(initialPromoteDialogState);
    };

    const handlePromoteMember = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!promoteDialog.role || !promoteDialog.selectedMember?.acctno) {
            return;
        }

        setPromoteDialog((current) => ({
            ...current,
            processing: true,
            errors: {},
        }));

        try {
            const response = await adminApi.promoteMember({
                account_number: promoteDialog.selectedMember!.acctno!,
                role: promoteDialog.role as EditableStaffRoleName,
                reason: promoteDialog.reason,
            });

            showSuccessToast(
                response.message ?? 'Member promoted to staff successfully.',
            );
            resetPromoteDialog();
            setPage(1);
            refreshDirectory();
        } catch (requestError) {
            if (
                axios.isAxiosError(requestError) &&
                requestError.response?.status === 422
            ) {
                setPromoteDialog((current) => ({
                    ...current,
                    processing: false,
                    errors: mapValidationErrors(
                        requestError.response.data?.errors,
                    ),
                }));
            } else {
                setPromoteDialog((current) => ({
                    ...current,
                    processing: false,
                }));
            }

            showErrorToast(requestError, 'Failed to promote the member.');
        }
    };

    const handleCreateRoleToggle = (
        role: EditableStaffRoleName,
        checked: boolean,
    ) => {
        setCreateForm((current) => ({
            ...current,
            roles: checked
                ? Array.from(new Set([...current.roles, role]))
                : current.roles.filter((currentRole) => currentRole !== role),
        }));
        setCreateErrors((current) => ({ ...current, roles: '' }));
    };

    const handleCreateStaff = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();
        setCreateProcessing(true);
        setCreateErrors({});

        try {
            const response = await adminApi.createStaff({
                username: createForm.username,
                email: createForm.email,
                phoneno:
                    createForm.phoneno.trim() === ''
                        ? null
                        : createForm.phoneno,
                password: createForm.password,
                password_confirmation: createForm.password_confirmation,
                roles: createForm.roles,
                reason: createForm.reason,
            });

            showSuccessToast(
                response.message ??
                    'Staff account created successfully.',
            );
            resetCreateDialog();
            setPage(1);
            refreshDirectory();
        } catch (requestError) {
            if (
                axios.isAxiosError(requestError) &&
                requestError.response?.status === 422
            ) {
                setCreateErrors(
                    mapValidationErrors(requestError.response.data?.errors),
                );
            }

            showErrorToast(
                requestError,
                'Failed to create the staff account.',
            );
            setCreateProcessing(false);
        }
    };

    const openRoleMutation = (
        staff: StaffAccount,
        role: EditableStaffRoleName,
        operation: 'assign' | 'remove',
    ) => {
        setRoleMutation({
            open: true,
            user: staff,
            role,
            operation,
            reason: '',
            processing: false,
            errors: {},
        });
    };

    const handleRoleMutation = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        if (!roleMutation.user) {
            return;
        }

        setRoleMutation((current) => ({
            ...current,
            processing: true,
            errors: {},
        }));

        try {
            await adminApi.updateStaffRole(roleMutation.user.user_id, {
                role: roleMutation.role,
                operation: roleMutation.operation,
                reason: roleMutation.reason,
            });

            showSuccessToast(
                roleMutation.operation === 'assign'
                    ? 'Staff role assigned successfully.'
                    : 'Staff role removed successfully.',
            );
            setRoleMutation(initialRoleMutationState);
            refreshDirectory();
        } catch (requestError) {
            if (
                axios.isAxiosError(requestError) &&
                requestError.response?.status === 422
            ) {
                setRoleMutation((current) => ({
                    ...current,
                    processing: false,
                    errors: mapValidationErrors(
                        requestError.response.data?.errors,
                    ),
                }));
            } else {
                setRoleMutation((current) => ({
                    ...current,
                    processing: false,
                }));
            }

            showErrorToast(
                requestError,
                roleMutation.operation === 'assign'
                    ? 'Failed to assign the staff role.'
                    : 'Failed to remove the staff role.',
            );
        }
    };

    const openAccessMutation = (
        staff: StaffAccount,
        action: 'suspend' | 'reactivate',
    ) => {
        setAccessMutation({
            open: true,
            user: staff,
            action,
            reason: '',
            processing: false,
            errors: {},
        });
    };

    const handleAccessMutation = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        if (!accessMutation.user) {
            return;
        }

        setAccessMutation((current) => ({
            ...current,
            processing: true,
            errors: {},
        }));

        try {
            if (accessMutation.action === 'suspend') {
                await adminApi.suspendStaff(accessMutation.user.user_id, {
                    reason: accessMutation.reason,
                });
            } else {
                await adminApi.reactivateStaff(accessMutation.user.user_id, {
                    reason: accessMutation.reason,
                });
            }

            showSuccessToast(
                accessMutation.action === 'suspend'
                    ? 'Staff access suspended successfully.'
                    : 'Staff access reactivated successfully.',
            );
            setAccessMutation(initialAccessMutationState);
            refreshDirectory();
        } catch (requestError) {
            if (
                axios.isAxiosError(requestError) &&
                requestError.response?.status === 422
            ) {
                setAccessMutation((current) => ({
                    ...current,
                    processing: false,
                    errors: mapValidationErrors(
                        requestError.response.data?.errors,
                    ),
                }));
            } else {
                setAccessMutation((current) => ({
                    ...current,
                    processing: false,
                }));
            }

            showErrorToast(
                requestError,
                accessMutation.action === 'suspend'
                    ? 'Failed to suspend staff access.'
                    : 'Failed to reactivate staff access.',
            );
        }
    };

    const openResetPasswordMutation = (staff: StaffAccount) => {
        setResetPasswordMutation({
            open: true,
            user: staff,
            reason: '',
            processing: false,
            errors: {},
        });
    };

    const handleResetPassword = async (
        event: FormEvent<HTMLFormElement>,
    ) => {
        event.preventDefault();

        if (!resetPasswordMutation.user) {
            return;
        }

        setResetPasswordMutation((current) => ({
            ...current,
            processing: true,
            errors: {},
        }));

        try {
            const response = await adminApi.resetStaffPassword(
                resetPasswordMutation.user.user_id,
                { reason: resetPasswordMutation.reason },
            );

            showSuccessToast(
                response.message ?? 'Password reset successfully.',
            );
            setResetPasswordResult({
                user: response.staff,
                temporaryPassword: response.temporary_password,
            });
            setResetPasswordMutation(initialResetPasswordMutationState);
            refreshDirectory();
        } catch (requestError) {
            if (
                axios.isAxiosError(requestError) &&
                requestError.response?.status === 422
            ) {
                setResetPasswordMutation((current) => ({
                    ...current,
                    processing: false,
                    errors: mapValidationErrors(
                        requestError.response.data?.errors,
                    ),
                }));
            } else {
                setResetPasswordMutation((current) => ({
                    ...current,
                    processing: false,
                }));
            }

            showErrorToast(requestError, 'Failed to reset the password.');
        }
    };

    const columns: ColumnDef<StaffAccount>[] = [
        {
            accessorKey: 'display_name',
            header: 'Staff account',
            cell: ({ row }) => (
                <div className="space-y-1">
                    <p className="font-medium">{row.original.display_name}</p>
                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        <span>{row.original.display_code}</span>
                        {row.original.username ? (
                            <span>{row.original.username}</span>
                        ) : null}
                    </div>
                </div>
            ),
        },
        {
            accessorKey: 'has_member_access',
            header: 'Member access',
            cell: ({ row }) => (
                <div className="space-y-1 text-sm">
                    <p className="font-medium">
                        {row.original.has_member_access ? 'Yes' : 'No'}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {row.original.acctno ?? '--'}
                    </p>
                </div>
            ),
        },
        {
            accessorKey: 'email',
            header: 'Contact',
            cell: ({ row }) => (
                <div className="space-y-1 text-sm">
                    <p>{row.original.email ?? '--'}</p>
                    <p className="text-xs text-muted-foreground">
                        {row.original.phoneno ?? '--'}
                    </p>
                </div>
            ),
        },
        {
            accessorKey: 'roles',
            header: 'Roles',
            cell: ({ row }) => (
                <div className="flex flex-wrap gap-2">
                    {row.original.roles.length === 0 ? (
                        <Badge variant="outline">No staff roles yet</Badge>
                    ) : (
                        row.original.roles.map((role) => (
                            <Badge
                                key={`${row.original.user_id}-${role.name}`}
                                variant={roleBadgeVariant(role.name)}
                            >
                                {role.label}
                            </Badge>
                        ))
                    )}
                </div>
            ),
        },
        {
            accessorKey: 'staff_access_status',
            header: 'Status',
            cell: ({ row }) => (
                <div className="space-y-2">
                    <Badge
                        variant={staffAccessVariant(
                            row.original.staff_access_status,
                        )}
                    >
                        {staffAccessLabel(row.original.staff_access_status)}
                    </Badge>
                    <p className="max-w-xs text-xs text-muted-foreground">
                        {describeLastChange(row.original)}
                    </p>
                </div>
            ),
        },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) => {
                const staff = row.original;
                const assignedEditableRoles = staff.roles.filter(
                    (role) => role.editable,
                );
                const assignableRoles = editableRoleOptions.filter(
                    (role) =>
                        !assignedEditableRoles.some(
                            (assignedRole) =>
                                assignedRole.name === role.value,
                        ),
                );

                return (
                    <div className="flex justify-end">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button type="button" variant="ghost" size="icon">
                                    <MoreHorizontal className="h-4 w-4" />
                                    <span className="sr-only">
                                        Manage staff
                                    </span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                align="end"
                                className="w-56"
                            >
                                <DropdownMenuLabel>
                                    Manage roles
                                </DropdownMenuLabel>
                                {assignableRoles.length === 0 ? (
                                    <DropdownMenuItem disabled>
                                        All editable roles assigned
                                    </DropdownMenuItem>
                                ) : (
                                    assignableRoles.map((role) => (
                                        <DropdownMenuItem
                                            key={`assign-${staff.user_id}-${role.value}`}
                                            onSelect={() =>
                                                openRoleMutation(
                                                    staff,
                                                    role.value,
                                                    'assign',
                                                )
                                            }
                                        >
                                            Assign {role.label}
                                        </DropdownMenuItem>
                                    ))
                                )}
                                {assignedEditableRoles.length > 0 ? (
                                    <>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuLabel>
                                            Remove roles
                                        </DropdownMenuLabel>
                                        {assignedEditableRoles.map((role) => (
                                            <DropdownMenuItem
                                                key={`remove-${staff.user_id}-${role.name}`}
                                                variant={
                                                    role.name === 'superadmin'
                                                        ? 'destructive'
                                                        : 'default'
                                                }
                                                onSelect={() =>
                                                    openRoleMutation(
                                                        staff,
                                                        role.name as EditableStaffRoleName,
                                                        'remove',
                                                    )
                                                }
                                            >
                                                Remove {role.label}
                                            </DropdownMenuItem>
                                        ))}
                                    </>
                                ) : null}
                                {staff.staff_access_status !== 'not_staff' ? (
                                    <>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            variant={
                                                staff.staff_access_status ===
                                                'active'
                                                    ? 'destructive'
                                                    : 'default'
                                            }
                                            onSelect={() =>
                                                openAccessMutation(
                                                    staff,
                                                    staff.staff_access_status ===
                                                        'suspended'
                                                        ? 'reactivate'
                                                        : 'suspend',
                                                )
                                            }
                                        >
                                            {staff.staff_access_status ===
                                            'suspended'
                                                ? 'Reactivate staff access'
                                                : 'Suspend staff access'}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onSelect={() =>
                                                openResetPasswordMutation(
                                                    staff,
                                                )
                                            }
                                        >
                                            Reset password
                                        </DropdownMenuItem>
                                    </>
                                ) : null}
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onSelect={() => setHistoryUser(staff)}
                                >
                                    View audit history
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                );
            },
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Staff management" />
            <PageShell size="wide">
                <PageHero
                    kicker="Superadmin"
                    title="Staff and role management"
                    description="Create staff-only accounts, assign workflow roles, suspend staff access without touching member access, and review the audit trail for every change."
                    badges={
                        <>
                            <Badge variant="secondary">
                                {totalResults} staff account
                                {totalResults === 1 ? '' : 's'}
                            </Badge>
                            {filterCount > 0 ? (
                                <Badge variant="outline">
                                    {filterCount} active filter
                                    {filterCount === 1 ? '' : 's'}
                                </Badge>
                            ) : null}
                        </>
                    }
                    rightSlot={
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    setPromoteDialog((current) => ({
                                        ...current,
                                        open: true,
                                    }))
                                }
                            >
                                <Users className="h-4 w-4" />
                                Promote existing member
                            </Button>
                            <Button
                                type="button"
                                onClick={() => setCreateDialogOpen(true)}
                            >
                                <UserPlus className="h-4 w-4" />
                                Create staff account
                            </Button>
                        </div>
                    }
                />

                <SurfaceCard variant="default" padding="md">
                    <div className="flex flex-col gap-4">
                        <SectionHeader
                            title="Filters"
                            description="Search by username, name, email, phone, or account number, then narrow the results by role and staff access."
                            actions={
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    disabled={filterCount === 0}
                                    onClick={() => {
                                        setSearch('');
                                        setRoleFilter('all');
                                        setAccessFilter('all');
                                        setPage(1);
                                    }}
                                >
                                    Clear filters
                                </Button>
                            }
                        />
                        <div className="grid gap-3 md:grid-cols-[minmax(0,1.6fr)_minmax(0,0.8fr)_minmax(0,0.8fr)]">
                            <div className="space-y-1">
                                <Label htmlFor="staff-search">Search</Label>
                                <Input
                                    id="staff-search"
                                    value={search}
                                    placeholder="Search by username, name, email, phone, or account no"
                                    onChange={(event) => {
                                        setSearch(event.target.value);
                                        setPage(1);
                                    }}
                                />
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="staff-role-filter">Role</Label>
                                <Select
                                    value={roleFilter}
                                    onValueChange={(value) => {
                                        setRoleFilter(
                                            value as EditableStaffRoleName | 'all',
                                        );
                                        setPage(1);
                                    }}
                                >
                                    <SelectTrigger
                                        id="staff-role-filter"
                                        aria-label="Filter by role"
                                    >
                                        <SelectValue placeholder="All roles" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All staff roles
                                        </SelectItem>
                                        {editableRoleOptions.map((role) => (
                                            <SelectItem
                                                key={role.value}
                                                value={role.value}
                                            >
                                                {role.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="staff-access-filter">
                                    Staff access
                                </Label>
                                <Select
                                    value={accessFilter}
                                    onValueChange={(value) => {
                                        setAccessFilter(
                                            value as
                                                | 'all'
                                                | 'active'
                                                | 'suspended',
                                        );
                                        setPage(1);
                                    }}
                                >
                                    <SelectTrigger
                                        id="staff-access-filter"
                                        aria-label="Filter by staff access"
                                    >
                                        <SelectValue placeholder="All access states" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All access states
                                        </SelectItem>
                                        <SelectItem value="active">
                                            Active
                                        </SelectItem>
                                        <SelectItem value="suspended">
                                            Suspended
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>
                </SurfaceCard>

                {error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Unable to load staff accounts</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                <SurfaceCard
                    variant="default"
                    padding="none"
                    className="overflow-hidden"
                >
                    <div className="border-b border-border/40 bg-card/70 px-6 py-4">
                        <SectionHeader
                            title="Results"
                            description={resultsLabel}
                            titleClassName="text-lg"
                            actions={
                                loading ? (
                                    <Badge variant="outline">Updating</Badge>
                                ) : null
                            }
                        />
                    </div>
                    <div className="px-2 pb-2 sm:px-4 sm:pb-4">
                        {showSkeleton ? (
                            <>
                                <div
                                    className="space-y-3 px-2 pb-3 pt-4 md:hidden"
                                    aria-busy="true"
                                >
                                    {Array.from({ length: 4 }).map((_, index) => (
                                        <SurfaceCard
                                            key={`staff-mobile-skeleton-${index}`}
                                            variant="default"
                                            padding="sm"
                                            className="space-y-3"
                                        >
                                            <div className="h-4 w-36 animate-pulse rounded bg-muted" />
                                            <div className="h-16 animate-pulse rounded-xl bg-muted/70" />
                                            <div className="h-8 w-28 animate-pulse rounded bg-muted" />
                                        </SurfaceCard>
                                    ))}
                                </div>
                                <div
                                    className="hidden md:block"
                                    aria-busy="true"
                                >
                                    <TableSkeleton
                                        columns={tableSkeletonColumns}
                                        rows={perPage}
                                        className="pt-4"
                                        tableClassName="bg-transparent"
                                    />
                                </div>
                            </>
                        ) : (
                            <>
                                <div className="space-y-3 px-2 pb-3 pt-4 md:hidden">
                                    {items.length === 0 ? (
                                        <div className="rounded-xl border border-border/30 bg-muted/30 px-4 py-6 text-center text-sm text-muted-foreground">
                                            No staff accounts found.
                                        </div>
                                    ) : (
                                        items.map((staff) => (
                                            <MobileStaffCard
                                                key={staff.user_id}
                                                staff={staff}
                                                onOpenHistory={setHistoryUser}
                                                onOpenRoleMutation={
                                                    openRoleMutation
                                                }
                                                onOpenAccessMutation={
                                                    openAccessMutation
                                                }
                                                onOpenResetPassword={
                                                    openResetPasswordMutation
                                                }
                                            />
                                        ))
                                    )}
                                </div>
                                <div className="hidden md:block">
                                    <DataTable
                                        columns={columns}
                                        data={items}
                                        emptyMessage="No staff accounts found."
                                        className="border-0 bg-transparent"
                                    />
                                </div>
                            </>
                        )}
                    </div>
                </SurfaceCard>

                {showSkeleton ? (
                    <DataTablePaginationSkeleton />
                ) : (
                    <DataTablePagination
                        page={meta.page}
                        perPage={meta.perPage}
                        total={meta.total}
                        onPageChange={(nextPage) => setPage(nextPage)}
                    />
                )}
            </PageShell>

            <Dialog
                open={createDialogOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        resetCreateDialog();

                        return;
                    }

                    setCreateDialogOpen(true);
                }}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Create staff account</DialogTitle>
                        <DialogDescription>
                            Create a staff-only account or prepare a hybrid
                            member account for workflow access. Legacy Admin is
                            intentionally excluded here, and the temporary
                            password is never shown again after creation.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-5" onSubmit={handleCreateStaff}>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="create-staff-username">
                                    Username
                                </Label>
                                <Input
                                    id="create-staff-username"
                                    value={createForm.username}
                                    onChange={(event) =>
                                        setCreateForm((current) => ({
                                            ...current,
                                            username: event.target.value,
                                        }))
                                    }
                                />
                                <InputError
                                    message={createErrors.username}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="create-staff-email">
                                    Email
                                </Label>
                                <Input
                                    id="create-staff-email"
                                    type="email"
                                    value={createForm.email}
                                    onChange={(event) =>
                                        setCreateForm((current) => ({
                                            ...current,
                                            email: event.target.value,
                                        }))
                                    }
                                />
                                <InputError message={createErrors.email} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="create-staff-phone">
                                    Phone
                                </Label>
                                <Input
                                    id="create-staff-phone"
                                    value={createForm.phoneno}
                                    placeholder="Optional 11-digit mobile number"
                                    onChange={(event) =>
                                        setCreateForm((current) => ({
                                            ...current,
                                            phoneno: event.target.value,
                                        }))
                                    }
                                />
                                <InputError message={createErrors.phoneno} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="create-staff-password">
                                    Temporary password
                                </Label>
                                <PasswordInput
                                    id="create-staff-password"
                                    value={createForm.password}
                                    onChange={(event) =>
                                        setCreateForm((current) => ({
                                            ...current,
                                            password: event.target.value,
                                        }))
                                    }
                                />
                                <InputError
                                    message={createErrors.password}
                                />
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="create-staff-password-confirmation">
                                    Confirm temporary password
                                </Label>
                                <PasswordInput
                                    id="create-staff-password-confirmation"
                                    value={createForm.password_confirmation}
                                    onChange={(event) =>
                                        setCreateForm((current) => ({
                                            ...current,
                                            password_confirmation:
                                                event.target.value,
                                        }))
                                    }
                                />
                                <InputError
                                    message={
                                        createErrors.password_confirmation
                                    }
                                />
                            </div>
                        </div>

                        <div className="space-y-3">
                            <div className="space-y-1">
                                <Label>Staff roles</Label>
                                <p className="text-sm text-muted-foreground">
                                    Multiple staff roles are allowed. Member is
                                    visible in the directory when present, but it
                                    is never assigned or removed from this page.
                                </p>
                            </div>
                            <div className="grid gap-3 md:grid-cols-3">
                                {editableRoleOptions.map((role) => {
                                    const checked = createForm.roles.includes(
                                        role.value,
                                    );

                                    return (
                                        <label
                                            key={role.value}
                                            className={cn(
                                                'flex cursor-pointer items-start gap-3 rounded-xl border border-border/40 bg-card/60 p-4 transition-colors',
                                                checked
                                                    ? 'border-primary/40 bg-primary/5'
                                                    : 'hover:border-border',
                                            )}
                                        >
                                            <Checkbox
                                                checked={checked}
                                                onCheckedChange={(value) =>
                                                    handleCreateRoleToggle(
                                                        role.value,
                                                        value === true,
                                                    )
                                                }
                                            />
                                            <div className="space-y-1">
                                                <p className="text-sm font-medium">
                                                    {role.label}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {role.description}
                                                </p>
                                            </div>
                                        </label>
                                    );
                                })}
                            </div>
                            <InputError message={createErrors.roles} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-staff-reason">
                                Reason
                            </Label>
                            <textarea
                                id="create-staff-reason"
                                className={textareaClassName}
                                value={createForm.reason}
                                onChange={(event) =>
                                    setCreateForm((current) => ({
                                        ...current,
                                        reason: event.target.value,
                                    }))
                                }
                                placeholder="Why is this staff account being created?"
                            />
                            <p className="text-xs text-muted-foreground">
                                Reason is required for every staff mutation.
                            </p>
                            <InputError message={createErrors.reason} />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={resetCreateDialog}
                                disabled={createProcessing}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={createProcessing}>
                                Create staff account
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={roleMutation.open}
                onOpenChange={(open) => {
                    if (!open) {
                        setRoleMutation(initialRoleMutationState);
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {roleMutation.operation === 'assign'
                                ? 'Assign staff role'
                                : 'Remove staff role'}
                        </DialogTitle>
                        <DialogDescription>
                            {roleMutation.user
                                ? `${roleMutation.operation === 'assign' ? 'Confirm the staff role assignment for' : 'Confirm the staff role removal for'} ${roleMutation.user.display_name}.`
                                : 'Confirm the requested staff role update.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={handleRoleMutation}
                    >
                        <div className="rounded-xl border border-border/40 bg-muted/30 p-4 text-sm">
                            <p className="font-medium">
                                {editableRoleOptions.find(
                                    (role) => role.value === roleMutation.role,
                                )?.label ?? roleMutation.role}
                            </p>
                            <p className="mt-1 text-muted-foreground">
                                {roleMutation.operation === 'assign'
                                    ? 'This grants the selected workflow or Superadmin responsibility without affecting Member access.'
                                    : 'This removes only the selected staff role. Member access stays untouched.'}
                            </p>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="role-mutation-reason">
                                Reason
                            </Label>
                            <textarea
                                id="role-mutation-reason"
                                className={textareaClassName}
                                value={roleMutation.reason}
                                onChange={(event) =>
                                    setRoleMutation((current) => ({
                                        ...current,
                                        reason: event.target.value,
                                    }))
                                }
                                placeholder="Document why this role change is necessary."
                            />
                            <InputError
                                message={
                                    roleMutation.errors.reason ??
                                    roleMutation.errors.role ??
                                    roleMutation.errors.staff
                                }
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() =>
                                    setRoleMutation(initialRoleMutationState)
                                }
                                disabled={roleMutation.processing}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant={
                                    roleMutation.operation === 'remove'
                                        ? 'destructive'
                                        : 'default'
                                }
                                disabled={roleMutation.processing}
                            >
                                {roleMutation.operation === 'assign'
                                    ? 'Assign role'
                                    : 'Remove role'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={accessMutation.open}
                onOpenChange={(open) => {
                    if (!open) {
                        setAccessMutation(initialAccessMutationState);
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {accessMutation.action === 'suspend'
                                ? 'Suspend staff access'
                                : 'Reactivate staff access'}
                        </DialogTitle>
                        <DialogDescription>
                            {accessMutation.user
                                ? accessMutation.action === 'suspend'
                                    ? `Suspend staff-only access for ${accessMutation.user.display_name}. Member access stays intact, and active officer assignments return to the queue.`
                                    : `Restore staff-only access for ${accessMutation.user.display_name}. Previous officer assignments are not reclaimed automatically.`
                                : 'Confirm the requested staff access update.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={handleAccessMutation}
                    >
                        <div className="rounded-xl border border-border/40 bg-muted/30 p-4 text-sm">
                            <div className="flex items-center gap-2 font-medium">
                                {accessMutation.action === 'suspend' ? (
                                    <ShieldOff className="h-4 w-4" />
                                ) : (
                                    <ShieldCheck className="h-4 w-4" />
                                )}
                                <span>
                                    {accessMutation.action === 'suspend'
                                        ? 'Staff routes and workflow APIs will return 403.'
                                        : 'Staff routes will be available again after reactivation.'}
                                </span>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="staff-access-reason">
                                Reason
                            </Label>
                            <textarea
                                id="staff-access-reason"
                                className={textareaClassName}
                                value={accessMutation.reason}
                                onChange={(event) =>
                                    setAccessMutation((current) => ({
                                        ...current,
                                        reason: event.target.value,
                                    }))
                                }
                                placeholder="Document why this access change is necessary."
                            />
                            <InputError
                                message={
                                    accessMutation.errors.reason ??
                                    accessMutation.errors.staff ??
                                    accessMutation.errors.role
                                }
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() =>
                                    setAccessMutation(
                                        initialAccessMutationState,
                                    )
                                }
                                disabled={accessMutation.processing}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant={
                                    accessMutation.action === 'suspend'
                                        ? 'destructive'
                                        : 'default'
                                }
                                disabled={accessMutation.processing}
                            >
                                {accessMutation.action === 'suspend'
                                    ? 'Suspend access'
                                    : 'Reactivate access'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={resetPasswordMutation.open}
                onOpenChange={(open) => {
                    if (!open) {
                        setResetPasswordMutation(
                            initialResetPasswordMutationState,
                        );
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Reset password</DialogTitle>
                        <DialogDescription>
                            {resetPasswordMutation.user
                                ? `Generate a new temporary password for ${resetPasswordMutation.user.display_name}. They must set their own password on next login.`
                                : 'Confirm the requested password reset.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={handleResetPassword}
                    >
                        <div className="rounded-xl border border-border/40 bg-muted/30 p-4 text-sm text-muted-foreground">
                            A random temporary password will be generated and
                            shown once. You will not be able to view it
                            again, so relay it to the staff member right
                            away.
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="reset-password-reason">
                                Reason
                            </Label>
                            <textarea
                                id="reset-password-reason"
                                className={textareaClassName}
                                value={resetPasswordMutation.reason}
                                onChange={(event) =>
                                    setResetPasswordMutation((current) => ({
                                        ...current,
                                        reason: event.target.value,
                                    }))
                                }
                                placeholder="Document why this password is being reset."
                            />
                            <InputError
                                message={
                                    resetPasswordMutation.errors.reason ??
                                    resetPasswordMutation.errors.staff
                                }
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() =>
                                    setResetPasswordMutation(
                                        initialResetPasswordMutationState,
                                    )
                                }
                                disabled={resetPasswordMutation.processing}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={resetPasswordMutation.processing}
                            >
                                Reset password
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={resetPasswordResult !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setResetPasswordResult(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Temporary password generated</DialogTitle>
                        <DialogDescription>
                            {resetPasswordResult
                                ? `Share this temporary password with ${resetPasswordResult.user.display_name} now. It will not be shown again, and they must set a new password on next login.`
                                : 'This password will not be shown again.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex items-center gap-2 rounded-xl border border-border/40 bg-muted/30 p-3">
                        <code className="flex-1 break-all font-mono text-sm">
                            {resetPasswordResult?.temporaryPassword}
                        </code>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            onClick={() => {
                                if (!resetPasswordResult) {
                                    return;
                                }

                                void navigator.clipboard
                                    .writeText(
                                        resetPasswordResult.temporaryPassword,
                                    )
                                    .then(() =>
                                        showSuccessToast(
                                            'Temporary password copied.',
                                        ),
                                    );
                            }}
                        >
                            <Copy className="h-4 w-4" />
                            <span className="sr-only">Copy password</span>
                        </Button>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            onClick={() => setResetPasswordResult(null)}
                        >
                            Done
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={historyUser !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setHistoryUser(null);
                    }
                }}
            >
                <DialogContent className="max-h-[calc(100vh-2rem)] overflow-hidden sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Role and access history</DialogTitle>
                        <DialogDescription>
                            {historyUser
                                ? `Review role assignments, removals, and staff access changes for ${historyUser.display_name}.`
                                : 'Review role and staff access history.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 overflow-y-auto pr-1">
                        {historyError ? (
                            <Alert variant="destructive">
                                <AlertTitle>
                                    Unable to load staff history
                                </AlertTitle>
                                <AlertDescription>
                                    {historyError}
                                </AlertDescription>
                            </Alert>
                        ) : null}

                        {historyLoading ? (
                            <div className="space-y-3" aria-busy="true">
                                {Array.from({ length: 3 }).map((_, index) => (
                                    <SurfaceCard
                                        key={`history-skeleton-${index}`}
                                        variant="default"
                                        padding="sm"
                                        className="space-y-3"
                                    >
                                        <div className="h-4 w-32 animate-pulse rounded bg-muted" />
                                        <div className="h-12 animate-pulse rounded bg-muted/70" />
                                    </SurfaceCard>
                                ))}
                            </div>
                        ) : historyItems.length === 0 ? (
                            <div className="rounded-xl border border-border/30 bg-muted/30 px-4 py-6 text-center text-sm text-muted-foreground">
                                No audit entries found for this account yet.
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {historyItems.map((entry) => (
                                    <SurfaceCard
                                        key={entry.id}
                                        variant="default"
                                        padding="sm"
                                        className="space-y-3"
                                    >
                                        <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div className="space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="text-sm font-semibold">
                                                        {entry.action_label}
                                                    </p>
                                                    {entry.role_label ? (
                                                        <Badge
                                                            variant={roleBadgeVariant(
                                                                entry.role_name ??
                                                                    'member',
                                                            )}
                                                        >
                                                            {entry.role_label}
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    {entry.actor
                                                        ? `${entry.actor.name} (${entry.actor.display_code})`
                                                        : 'System action'}{' '}
                                                    on{' '}
                                                    {formatDateTime(
                                                        entry.created_at,
                                                    )}
                                                </p>
                                            </div>
                                            <div className="flex flex-wrap gap-2 text-xs">
                                                <Badge
                                                    variant={staffAccessVariant(
                                                        entry.after_staff_status ??
                                                            'not_staff',
                                                    )}
                                                >
                                                    {historyStatusLabel(
                                                        entry.after_staff_status,
                                                    )}
                                                </Badge>
                                            </div>
                                        </div>

                                        <div className="grid gap-3 md:grid-cols-2">
                                            <div className="rounded-xl border border-border/30 bg-muted/20 p-3">
                                                <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                                                    Before
                                                </p>
                                                <div className="mt-2 flex flex-wrap gap-2">
                                                    {entry.before_roles.length ===
                                                    0 ? (
                                                        <Badge variant="outline">
                                                            No visible roles
                                                        </Badge>
                                                    ) : (
                                                        entry.before_roles.map(
                                                            (role) => (
                                                                <Badge
                                                                    key={`before-${entry.id}-${role.name}`}
                                                                    variant={roleBadgeVariant(
                                                                        role.name,
                                                                    )}
                                                                >
                                                                    {role.label}
                                                                </Badge>
                                                            ),
                                                        )
                                                    )}
                                                </div>
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    Staff access:{' '}
                                                    {historyStatusLabel(
                                                        entry.before_staff_status,
                                                    )}
                                                </p>
                                            </div>
                                            <div className="rounded-xl border border-border/30 bg-muted/20 p-3">
                                                <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                                                    After
                                                </p>
                                                <div className="mt-2 flex flex-wrap gap-2">
                                                    {entry.after_roles.length ===
                                                    0 ? (
                                                        <Badge variant="outline">
                                                            No visible roles
                                                        </Badge>
                                                    ) : (
                                                        entry.after_roles.map(
                                                            (role) => (
                                                                <Badge
                                                                    key={`after-${entry.id}-${role.name}`}
                                                                    variant={roleBadgeVariant(
                                                                        role.name,
                                                                    )}
                                                                >
                                                                    {role.label}
                                                                </Badge>
                                                            ),
                                                        )
                                                    )}
                                                </div>
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    Staff access:{' '}
                                                    {historyStatusLabel(
                                                        entry.after_staff_status,
                                                    )}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="space-y-1 rounded-xl border border-border/30 bg-background/60 p-3">
                                            <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                                                Reason
                                            </p>
                                            <p className="text-sm">
                                                {entry.reason}
                                            </p>
                                        </div>
                                    </SurfaceCard>
                                ))}
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            <Dialog
                open={promoteDialog.open}
                onOpenChange={(open) => {
                    if (!open) {
                        resetPromoteDialog();
                    }
                }}
            >
                <DialogContent className="max-h-[calc(100vh-2rem)] overflow-hidden sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Promote existing member</DialogTitle>
                        <DialogDescription>
                            Find a registered member and assign a staff role.
                            Their portal access is preserved.
                        </DialogDescription>
                        <div className="flex items-center gap-1.5 pt-0.5">
                            {([1, 2] as const).map((s) => (
                                <div
                                    key={s}
                                    className={cn(
                                        'h-1.5 rounded-full motion-safe:transition-all motion-safe:duration-200',
                                        s === promoteDialog.step
                                            ? 'w-6 bg-primary'
                                            : s < promoteDialog.step
                                              ? 'w-2 bg-primary/40'
                                              : 'w-2 bg-muted',
                                    )}
                                />
                            ))}
                            <span className="ml-1 text-xs text-muted-foreground">
                                Step {promoteDialog.step} of 2
                            </span>
                        </div>
                    </DialogHeader>

                    <div
                        key={promoteDialog.step}
                        className={cn(
                            'overflow-y-auto pr-1 motion-safe:animate-in motion-safe:fade-in-0 motion-safe:duration-200',
                            promoteDialog.stepDirection === 'forward'
                                ? 'motion-safe:slide-in-from-right-2'
                                : 'motion-safe:slide-in-from-left-2',
                        )}
                    >
                    {promoteDialog.step === 1 ? (() => {
                        const searchQuery = promoteDialog.query.trim();
                        const memberCount = promoteDialog.searchResults.length;
                        const countLabel = promoteDialog.searchLoading
                            ? (searchQuery === '' ? 'Loading members…' : 'Searching…')
                            : searchQuery !== ''
                                ? `Showing results for “${searchQuery}”`
                                : `${memberCount} registered member${memberCount !== 1 ? 's' : ''}`;

                        return (
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="promote-search-query">
                                        Filter members
                                    </Label>
                                    <div className="relative">
                                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground pointer-events-none" />
                                        <Input
                                            id="promote-search-query"
                                            value={promoteDialog.query}
                                            placeholder="Name, email, or account number"
                                            className="pl-9"
                                            autoFocus
                                            onChange={(event) =>
                                                setPromoteDialog((current) => ({
                                                    ...current,
                                                    query: event.target.value,
                                                    searchError: null,
                                                }))
                                            }
                                        />
                                    </div>
                                    {promoteDialog.searchError ? (
                                        <InputError message={promoteDialog.searchError} />
                                    ) : null}
                                </div>

                                <p className="text-sm text-muted-foreground">{countLabel}</p>

                                <div className={cn('overflow-x-auto overflow-y-hidden rounded-xl border border-border/40 motion-safe:transition-opacity motion-safe:duration-150', promoteDialog.searchLoading ? 'opacity-60' : 'opacity-100')}>
                                    <Table>
                                        <TableHeader className="bg-muted/30">
                                            <TableRow>
                                                <TableHead className="min-w-[180px]">Name</TableHead>
                                                <TableHead className="whitespace-nowrap">Account No</TableHead>
                                                <TableHead className="min-w-[160px]">Email</TableHead>
                                                <TableHead className="min-w-[120px]">Current roles</TableHead>
                                                <TableHead className="whitespace-nowrap text-right">Action</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {promoteDialog.searchLoading ? (
                                                <TableRow>
                                                    <TableCell colSpan={5} className="h-24 text-center text-sm text-muted-foreground">
                                                        {searchQuery === '' ? 'Loading members…' : 'Searching…'}
                                                    </TableCell>
                                                </TableRow>
                                            ) : promoteDialog.searchResults.length === 0 ? (
                                                <TableRow>
                                                    <TableCell colSpan={5} className="h-24 text-center text-sm text-muted-foreground">
                                                        {searchQuery === ''
                                                            ? 'No registered members yet. Members must self-register before they can be promoted to staff.'
                                                            : 'No members found.'}
                                                    </TableCell>
                                                </TableRow>
                                            ) : (
                                                promoteDialog.searchResults.map((member) => {
                                                    const staffRoles = member.roles.filter((r) => r.editable);
                                                    const displayRoles = member.roles.filter((r) => r.name !== 'member');

                                                    return (
                                                        <TableRow key={member.user_id}>
                                                            <TableCell className="min-w-[180px]">
                                                                <div className="flex flex-wrap items-center gap-2">
                                                                    <p className="font-medium">{member.display_name}</p>
                                                                    {staffRoles.length > 0 ? (
                                                                        <Badge variant="secondary" className="text-xs whitespace-nowrap">
                                                                            Already a staff member
                                                                        </Badge>
                                                                    ) : null}
                                                                </div>
                                                            </TableCell>
                                                            <TableCell className="whitespace-nowrap">{member.acctno ?? '--'}</TableCell>
                                                            <TableCell
                                                                className="max-w-[180px] truncate"
                                                                title={member.email ?? undefined}
                                                            >
                                                                {member.email ?? '--'}
                                                            </TableCell>
                                                            <TableCell>
                                                                {displayRoles.length === 0 ? (
                                                                    <span className="text-sm text-muted-foreground">None</span>
                                                                ) : (
                                                                    <div className="flex flex-wrap gap-1">
                                                                        {displayRoles.map((r) => (
                                                                            <Badge
                                                                                key={r.name}
                                                                                variant={roleBadgeVariant(r.name)}
                                                                                className="text-xs"
                                                                            >
                                                                                {r.label}
                                                                            </Badge>
                                                                        ))}
                                                                    </div>
                                                                )}
                                                            </TableCell>
                                                            <TableCell className="whitespace-nowrap text-right">
                                                                <Button
                                                                    type="button"
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() =>
                                                                        setPromoteDialog((current) => ({
                                                                            ...current,
                                                                            step: 2,
                                                                            stepDirection: 'forward',
                                                                            selectedMember: member,
                                                                            role: '',
                                                                            reason: '',
                                                                            errors: {},
                                                                        }))
                                                                    }
                                                                >
                                                                    Select
                                                                </Button>
                                                            </TableCell>
                                                        </TableRow>
                                                    );
                                                })
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>

                                <DialogFooter>
                                    <Button type="button" variant="ghost" onClick={resetPromoteDialog}>
                                        Cancel
                                    </Button>
                                </DialogFooter>
                            </div>
                        );
                    })() : (
                        <form
                            className="space-y-4"
                            onSubmit={handlePromoteMember}
                        >
                            {promoteDialog.selectedMember ? (
                                <div className="rounded-xl border border-border/40 bg-muted/30 p-4 text-sm">
                                    <p className="font-semibold">
                                        {promoteDialog.selectedMember.display_name}
                                    </p>
                                    <p className="mt-0.5 text-muted-foreground">
                                        {promoteDialog.selectedMember.email ?? '--'}
                                    </p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Account no:{' '}
                                        {promoteDialog.selectedMember.acctno ?? '--'}
                                    </p>
                                    {promoteDialog.selectedMember.roles.filter(
                                        (r) => r.name !== 'member',
                                    ).length > 0 ? (
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {promoteDialog.selectedMember.roles
                                                .filter((r) => r.name !== 'member')
                                                .map((r) => (
                                                    <Badge
                                                        key={r.name}
                                                        variant={roleBadgeVariant(r.name)}
                                                    >
                                                        {r.label}
                                                    </Badge>
                                                ))}
                                        </div>
                                    ) : null}
                                </div>
                            ) : null}

                            <div className="space-y-3">
                                <Label>Staff role to assign</Label>
                                <div className="grid gap-3">
                                    {editableRoleOptions.map((role) => {
                                        const checked = promoteDialog.role === role.value;

                                        return (
                                            <label
                                                key={role.value}
                                                className={cn(
                                                    'flex cursor-pointer items-start gap-3 rounded-xl border border-border/40 bg-card/60 p-4 transition-colors',
                                                    checked
                                                        ? 'border-primary/40 bg-primary/5'
                                                        : 'hover:border-border',
                                                )}
                                            >
                                                <input
                                                    type="radio"
                                                    className="mt-0.5"
                                                    name="promote-role"
                                                    value={role.value}
                                                    checked={checked}
                                                    onChange={() =>
                                                        setPromoteDialog((current) => ({
                                                            ...current,
                                                            role: role.value,
                                                            errors: {
                                                                ...current.errors,
                                                                role: '',
                                                            },
                                                        }))
                                                    }
                                                />
                                                <div className="space-y-1">
                                                    <p className="text-sm font-medium">{role.label}</p>
                                                    <p className="text-xs text-muted-foreground">{role.description}</p>
                                                </div>
                                            </label>
                                        );
                                    })}
                                </div>
                                <InputError message={promoteDialog.errors.role} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="promote-reason">Notes</Label>
                                <textarea
                                    id="promote-reason"
                                    className={textareaClassName}
                                    value={promoteDialog.reason}
                                    onChange={(event) =>
                                        setPromoteDialog((current) => ({
                                            ...current,
                                            reason: event.target.value,
                                        }))
                                    }
                                    placeholder="Optional reason for promotion — recorded in the audit trail."
                                />
                                <InputError
                                    message={
                                        promoteDialog.errors.reason ??
                                        promoteDialog.errors.account_number
                                    }
                                />
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    disabled={promoteDialog.processing}
                                    onClick={() =>
                                        setPromoteDialog((current) => ({
                                            ...current,
                                            step: 1,
                                            stepDirection: 'back',
                                            selectedMember: null,
                                            role: '',
                                            reason: '',
                                            errors: {},
                                        }))
                                    }
                                >
                                    Back
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        promoteDialog.processing ||
                                        promoteDialog.role === '' ||
                                        !promoteDialog.selectedMember?.acctno
                                    }
                                >
                                    {promoteDialog.processing ? 'Promoting…' : 'Promote member'}
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                    </div>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
