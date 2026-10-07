import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import axios from 'axios';
import {
    Copy,
    MoreHorizontal,
    Search,
    ShieldCheck,
    ShieldOff,
    UserPlus,
    Users,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { RequestsPager } from '@/components/loan-request/loan-request-queue-page';
import { PageShell } from '@/components/page-shell';
import { SurfaceCard } from '@/components/surface-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
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
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    TableSkeleton,
    type TableSkeletonColumn,
} from '@/components/ui/table-skeleton';
import { Textarea } from '@/components/ui/textarea';
import { useStaffDirectory } from '@/hooks/admin/use-staff-directory';
import AppLayout from '@/layouts/app-layout';
import { mapValidationErrors } from '@/lib/api';
import { adminApi } from '@/lib/api/admin';
import { statusTones } from '@/lib/status-tones';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { index as superadminStaffIndex } from '@/routes/superadmin/staff';
import type { BreadcrumbItem } from '@/types';
import type {
    EditableStaffRoleName,
    PaginationMeta,
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

const promoteSkeletonColumns: TableSkeletonColumn[] = [
    { headerClassName: 'w-24', cellClassName: 'w-36' },
    { headerClassName: 'w-20', cellClassName: 'w-16' },
    { headerClassName: 'w-16', cellClassName: 'w-36' },
    { headerClassName: 'w-24', cellClassName: 'w-24' },
    { headerClassName: 'w-12', cellClassName: 'h-8 w-14', align: 'right' },
];

const editableRoleOptions: Array<{
    value: EditableStaffRoleName;
    label: string;
    description: string;
}> = [
    {
        value: 'superadmin',
        label: 'Superadmin',
        description:
            'Manage staff, monitor loan applications, and access existing Superadmin pages.',
    },
    {
        value: 'loan_processor',
        label: 'Loan Processor',
        description:
            'Review applications, request revisions, reject, and recommend approval.',
    },
    {
        value: 'loan_manager',
        label: 'Loan Manager',
        description:
            'Approve or decline applications after officer recommendation.',
    },
];

const getAssignableRoleOptions = (
    assignedEditableRoles: Array<{ name: string }>,
) => (assignedEditableRoles.length > 0 ? [] : editableRoleOptions);

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

const toneClassNames = statusTones;

const roleToneClassName = (roleName: string): string => {
    if (roleName === 'superadmin') {
        return toneClassNames.act;
    }

    if (roleName === 'loan_manager') {
        return toneClassNames.ok;
    }

    if (roleName === 'loan_processor') {
        return toneClassNames.info;
    }

    return toneClassNames.neutral;
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
    page: number;
    searchLoading: boolean;
    searchError: string | null;
    searchResults: StaffAccount[];
    searchMeta: PaginationMeta;
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
    page: 1,
    searchLoading: false,
    searchError: null,
    searchResults: [],
    searchMeta: { page: 1, perPage: 10, total: 0, lastPage: 1 },
    selectedMember: null,
    role: '',
    reason: '',
    processing: false,
    errors: {},
};

export default function SuperadminStaffPage() {
    const [search, setSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState<EditableStaffRoleName | 'all'>(
        'all',
    );
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
        useState<ResetPasswordMutationState>(initialResetPasswordMutationState);
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
                showErrorToast(requestError, 'Failed to load staff history.');
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

        const timer = setTimeout(
            async () => {
                try {
                    const { members, meta: searchMeta } =
                        await adminApi.searchMembers(
                            query,
                            promoteDialog.page,
                            controller.signal,
                        );

                    if (!controller.signal.aborted) {
                        setPromoteDialog((current) => {
                            if (current.step === 2) return current;
                            return {
                                ...current,
                                searchLoading: false,
                                searchResults: members,
                                searchMeta,
                            };
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
            },
            query === '' ? 0 : 300,
        );

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [
        promoteDialog.query,
        promoteDialog.page,
        promoteDialog.open,
        promoteDialog.step,
    ]);

    const showSkeleton = loading && items.length === 0;
    const searchValue = search.trim();
    const filterCount = [
        searchValue !== '',
        roleFilter !== 'all',
        accessFilter !== 'all',
    ].filter(Boolean).length;
    const totalResults = meta.total;
    const pageStart = totalResults > 0 ? (meta.page - 1) * meta.perPage + 1 : 0;
    const pageEnd =
        totalResults > 0 ? Math.min(meta.page * meta.perPage, totalResults) : 0;
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
                        requestError.response?.data?.errors,
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

    const handleCreateRoleSelect = (role: EditableStaffRoleName) => {
        setCreateForm((current) => ({
            ...current,
            roles: current.roles.includes(role) ? [] : [role],
        }));
        setCreateErrors((current) => ({ ...current, roles: '' }));
    };

    const handleCreateStaff = async (event: FormEvent<HTMLFormElement>) => {
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
                response.message ?? 'Staff account created successfully.',
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
                    mapValidationErrors(requestError.response?.data?.errors),
                );
            }

            showErrorToast(requestError, 'Failed to create the staff account.');
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

    const handleRoleMutation = async (event: FormEvent<HTMLFormElement>) => {
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
                        requestError.response?.data?.errors,
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

    const handleAccessMutation = async (event: FormEvent<HTMLFormElement>) => {
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
                        requestError.response?.data?.errors,
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

    const handleResetPassword = async (event: FormEvent<HTMLFormElement>) => {
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
                        requestError.response?.data?.errors,
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

    const promoteColumns: ColumnDef<StaffAccount>[] = [
        {
            accessorKey: 'display_name',
            meta: {
                priority: 'title',
                text: (row) => row.display_name ?? '',
            },
            header: 'Name',
            cell: ({ row }) => (
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-bold">
                        {row.original.display_name}
                    </span>
                    {row.original.roles.some((role) => role.editable) ? (
                        <Badge
                            variant="secondary"
                            className="text-xs whitespace-nowrap"
                        >
                            Already a staff member
                        </Badge>
                    ) : null}
                </div>
            ),
        },
        {
            accessorKey: 'acctno',
            meta: { priority: 'detail' },
            header: 'Account No',
            cell: ({ row }) => row.original.acctno ?? '--',
        },
        {
            accessorKey: 'email',
            meta: { priority: 'detail' },
            header: 'Email',
            cell: ({ row }) => (
                <span className="text-sm">{row.original.email ?? '--'}</span>
            ),
        },
        {
            accessorKey: 'roles',
            meta: { priority: 'detail' },
            header: 'Current roles',
            cell: ({ row }) => {
                const displayRoles = row.original.roles.filter(
                    (role) => role.name !== 'member',
                );

                return displayRoles.length === 0 ? (
                    <span className="text-sm text-muted-foreground">None</span>
                ) : (
                    <div className="flex flex-wrap gap-1">
                        {displayRoles.map((role) => (
                            <Badge
                                key={`${row.original.user_id}-${role.name}`}
                                variant={roleBadgeVariant(role.name)}
                                className="text-xs"
                            >
                                {role.label}
                            </Badge>
                        ))}
                    </div>
                );
            },
        },
        {
            id: 'actions',
            meta: { priority: 'action', label: 'Actions' },
            header: '',
            cell: ({ row }) => (
                <div className="flex justify-end">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                            setPromoteDialog((current) => ({
                                ...current,
                                step: 2,
                                stepDirection: 'forward',
                                selectedMember: row.original,
                                role: '',
                                reason: '',
                                errors: {},
                            }))
                        }
                    >
                        Select
                    </Button>
                </div>
            ),
        },
    ];

    const columns: ColumnDef<StaffAccount>[] = [
        {
            accessorKey: 'display_name',
            meta: {
                priority: 'title',
                text: (row) => row.display_name ?? '',
            },
            header: 'Staff account',
            cell: ({ row }) => (
                <div className="flex flex-col">
                    <span className="font-bold">
                        {row.original.display_name}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {[
                            row.original.username,
                            row.original.acctno
                                ? `Account ${row.original.acctno}`
                                : row.original.display_code,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'has_member_access',
            meta: { priority: 'detail' },
            header: 'Member access',
            cell: ({ row }) =>
                row.original.has_member_access ? (
                    <Badge
                        variant="outline"
                        className={cn('font-bold', toneClassNames.info)}
                    >
                        Has member access
                    </Badge>
                ) : (
                    <Badge
                        variant="outline"
                        className={cn('font-bold', toneClassNames.neutral)}
                    >
                        Staff only
                    </Badge>
                ),
        },
        {
            accessorKey: 'email',
            meta: { priority: 'detail' },
            header: 'Contact',
            cell: ({ row }) => (
                <div className="flex flex-col text-sm">
                    <span>{row.original.email ?? '--'}</span>
                    <span className="text-xs text-muted-foreground">
                        {row.original.phoneno ?? '--'}
                    </span>
                </div>
            ),
        },
        {
            accessorKey: 'roles',
            meta: { priority: 'detail' },
            header: 'Roles',
            cell: ({ row }) => (
                <div className="flex flex-wrap gap-2">
                    {row.original.roles.length === 0 ? (
                        <Badge variant="outline">No staff roles yet</Badge>
                    ) : (
                        row.original.roles.map((role) => (
                            <Badge
                                key={`${row.original.user_id}-${role.name}`}
                                variant="outline"
                                className={cn(
                                    'font-bold',
                                    roleToneClassName(role.name),
                                )}
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
            meta: { priority: 'badge' },
            header: 'Status',
            cell: ({ row }) => (
                <Badge
                    variant="outline"
                    className={cn(
                        'font-bold',
                        row.original.staff_access_status === 'active'
                            ? toneClassNames.ok
                            : row.original.staff_access_status === 'suspended'
                              ? toneClassNames.bad
                              : toneClassNames.neutral,
                    )}
                >
                    <span
                        className="size-[7px] rounded-full bg-current"
                        aria-hidden="true"
                    />
                    {staffAccessLabel(row.original.staff_access_status)}
                </Badge>
            ),
        },
        {
            id: 'actions',
            meta: { priority: 'action', label: 'Actions' },
            header: '',
            cell: ({ row }) => {
                const staff = row.original;
                const assignedEditableRoles = staff.roles.filter(
                    (role) => role.editable,
                );
                const assignableRoles = getAssignableRoleOptions(
                    assignedEditableRoles,
                );

                return (
                    <div className="flex justify-end">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    aria-label={`Manage ${staff.display_name}`}
                                >
                                    Manage
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                <DropdownMenuLabel>
                                    Manage roles
                                </DropdownMenuLabel>
                                {assignableRoles.length === 0 ? (
                                    <DropdownMenuItem disabled>
                                        {assignedEditableRoles.length > 0
                                            ? 'Remove the current role to assign a different one'
                                            : 'No editable roles available'}
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
                                                openResetPasswordMutation(staff)
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
                <section className="flex flex-wrap items-start gap-5">
                    <div>
                        <p className="text-[11px] font-bold tracking-[0.14em] text-primary uppercase">
                            Superadmin
                        </p>
                        <h1 className="mt-1 text-[22px] leading-tight font-bold sm:text-[26px]">
                            Staff and role management
                        </h1>
                        <p className="mt-1.5 max-w-prose text-sm text-muted-foreground">
                            Create staff-only accounts, assign workflow roles,
                            suspend staff access without touching member access,
                            and review the audit trail for every change.
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
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
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2 pt-1.5 max-sm:w-full sm:ml-auto">
                        <Button
                            type="button"
                            variant="outline"
                            className="max-sm:flex-1"
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
                            className="max-sm:flex-1"
                            onClick={() => setCreateDialogOpen(true)}
                        >
                            <UserPlus className="h-4 w-4" />
                            Create staff account
                        </Button>
                    </div>
                </section>

                <Card
                    className="gap-0 overflow-hidden py-0"
                    aria-label="Search and filter staff"
                >
                    <div className="flex flex-wrap items-end gap-3 px-5 py-4">
                        <div className="grid min-w-[260px] flex-1 gap-1">
                            <Label
                                htmlFor="staff-search"
                                className="text-[11px] font-bold tracking-[0.08em] text-muted-foreground uppercase"
                            >
                                Search staff
                            </Label>
                            <Input
                                id="staff-search"
                                type="search"
                                value={search}
                                onChange={(event) => {
                                    setSearch(event.target.value);
                                    setPage(1);
                                }}
                                placeholder="Search by username, name, email, phone, or account no"
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label
                                htmlFor="staff-role-filter"
                                className="text-[11px] font-bold tracking-[0.08em] text-muted-foreground uppercase"
                            >
                                Role
                            </Label>
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
                                    className="w-full sm:w-48"
                                >
                                    <SelectValue placeholder="All staff roles" />
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
                        <div className="grid gap-1">
                            <Label
                                htmlFor="staff-access-filter"
                                className="text-[11px] font-bold tracking-[0.08em] text-muted-foreground uppercase"
                            >
                                Staff access
                            </Label>
                            <Select
                                value={accessFilter}
                                onValueChange={(value) => {
                                    setAccessFilter(
                                        value as 'all' | 'active' | 'suspended',
                                    );
                                    setPage(1);
                                }}
                            >
                                <SelectTrigger
                                    id="staff-access-filter"
                                    aria-label="Filter by staff access"
                                    className="w-full sm:w-48"
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
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                setSearch('');
                                setRoleFilter('all');
                                setAccessFilter('all');
                                setPage(1);
                            }}
                        >
                            Clear filters
                        </Button>
                    </div>
                    <p
                        className="px-5 pb-4 text-[13px] text-muted-foreground"
                        role="status"
                    >
                        {resultsLabel}
                    </p>
                </Card>

                {error ? (
                    <Alert variant="destructive">
                        <AlertTitle>Unable to load staff accounts</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                ) : null}

                <Card className="gap-0 overflow-hidden py-0">
                    <div className="flex flex-wrap items-baseline gap-2 px-5 pt-[18px] pb-4">
                        <h2 className="text-base font-bold">Results</h2>
                        <span className="text-[13px] text-muted-foreground">
                            {totalResults} staff account
                            {totalResults === 1 ? '' : 's'}
                        </span>
                        <span className="ml-auto flex items-center gap-2">
                            {loading ? (
                                <span className="text-xs text-muted-foreground">
                                    Updating...
                                </span>
                            ) : null}
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="text-primary"
                                onClick={refreshDirectory}
                            >
                                Refresh
                            </Button>
                        </span>
                    </div>
                    {showSkeleton ? (
                        <>
                            <div
                                className="space-y-3 px-4 pb-3 md:hidden"
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
                            <div className="hidden md:block" aria-busy="true">
                                <TableSkeleton
                                    columns={tableSkeletonColumns}
                                    rows={perPage}
                                    tableClassName="bg-transparent"
                                />
                            </div>
                        </>
                    ) : (
                        <DataTable
                            columns={columns}
                            data={items}
                            emptyMessage="No staff accounts found."
                            className="rounded-none border-0 border-t border-border bg-transparent"
                        />
                    )}
                    {showSkeleton ? null : (
                        <RequestsPager
                            page={meta.page}
                            perPage={meta.perPage}
                            total={meta.total}
                            onPageChange={setPage}
                        />
                    )}
                </Card>
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
                                <InputError message={createErrors.username} />
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
                                <InputError message={createErrors.password} />
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
                                    message={createErrors.password_confirmation}
                                />
                            </div>
                        </div>

                        <div className="space-y-3">
                            <div className="space-y-1">
                                <Label>Staff roles</Label>
                                <p className="text-sm text-muted-foreground">
                                    Select exactly one staff role. Member access
                                    is granted separately and is never assigned
                                    or removed from this page.
                                </p>
                            </div>
                            <RadioGroup
                                className="grid gap-3 md:grid-cols-3"
                                value={createForm.roles[0] ?? ''}
                                onValueChange={(value) =>
                                    handleCreateRoleSelect(
                                        value as EditableStaffRoleName,
                                    )
                                }
                            >
                                {editableRoleOptions.map((role) => {
                                    const checked = createForm.roles.includes(
                                        role.value,
                                    );

                                    return (
                                        <Label
                                            key={role.value}
                                            className={cn(
                                                'flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-card p-4 transition-colors',
                                                checked
                                                    ? 'border-primary/40 bg-primary/5'
                                                    : 'hover:border-border',
                                            )}
                                        >
                                            <RadioGroupItem
                                                value={role.value}
                                                className="mt-1"
                                            />
                                            <div className="space-y-1">
                                                <p className="text-sm font-medium">
                                                    {role.label}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {role.description}
                                                </p>
                                            </div>
                                        </Label>
                                    );
                                })}
                            </RadioGroup>
                            <InputError message={createErrors.roles} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-staff-reason">Reason</Label>
                            <Textarea
                                id="create-staff-reason"
                                className="min-h-24"
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
                    <form className="space-y-4" onSubmit={handleRoleMutation}>
                        <div className="rounded-xl border border-border bg-muted p-4 text-sm">
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
                            <Label htmlFor="role-mutation-reason">Reason</Label>
                            <Textarea
                                id="role-mutation-reason"
                                className="min-h-24"
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
                    <form className="space-y-4" onSubmit={handleAccessMutation}>
                        <div className="rounded-xl border border-border bg-muted p-4 text-sm">
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
                            <Label htmlFor="staff-access-reason">Reason</Label>
                            <Textarea
                                id="staff-access-reason"
                                className="min-h-24"
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
                    <form className="space-y-4" onSubmit={handleResetPassword}>
                        <div className="rounded-xl border border-border bg-muted p-4 text-sm text-muted-foreground">
                            A random temporary password will be generated and
                            shown once. You will not be able to view it again,
                            so relay it to the staff member right away.
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="reset-password-reason">
                                Reason
                            </Label>
                            <Textarea
                                id="reset-password-reason"
                                className="min-h-24"
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
                    <div className="flex items-center gap-2 rounded-xl border border-border bg-muted p-3">
                        <code className="flex-1 font-mono text-sm break-all">
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
                            <div className="rounded-xl border border-border bg-muted px-4 py-6 text-center text-sm text-muted-foreground">
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
                                            <div className="rounded-xl border border-border bg-muted p-3">
                                                <p className="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                                    Before
                                                </p>
                                                <div className="mt-2 flex flex-wrap gap-2">
                                                    {entry.before_roles
                                                        .length === 0 ? (
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
                                            <div className="rounded-xl border border-border bg-muted p-3">
                                                <p className="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                                                    After
                                                </p>
                                                <div className="mt-2 flex flex-wrap gap-2">
                                                    {entry.after_roles
                                                        .length === 0 ? (
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

                                        <div className="space-y-1 rounded-xl border border-border bg-background p-3">
                                            <p className="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">
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
                            'overflow-y-auto pr-1 motion-safe:animate-in motion-safe:duration-200 motion-safe:fade-in-0',
                            promoteDialog.stepDirection === 'forward'
                                ? 'motion-safe:slide-in-from-right-2'
                                : 'motion-safe:slide-in-from-left-2',
                        )}
                    >
                        {promoteDialog.step === 1 ? (
                            (() => {
                                const searchQuery = promoteDialog.query.trim();
                                const memberCount =
                                    promoteDialog.searchMeta.total;
                                const countLabel = promoteDialog.searchLoading
                                    ? searchQuery === ''
                                        ? 'Loading members…'
                                        : 'Searching…'
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
                                                <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                                <Input
                                                    id="promote-search-query"
                                                    value={promoteDialog.query}
                                                    placeholder="Name, email, or account number"
                                                    className="pl-9"
                                                    autoFocus
                                                    onChange={(event) =>
                                                        setPromoteDialog(
                                                            (current) => ({
                                                                ...current,
                                                                query: event
                                                                    .target
                                                                    .value,
                                                                page: 1,
                                                                searchError:
                                                                    null,
                                                            }),
                                                        )
                                                    }
                                                />
                                            </div>
                                            {promoteDialog.searchError ? (
                                                <InputError
                                                    message={
                                                        promoteDialog.searchError
                                                    }
                                                />
                                            ) : null}
                                        </div>

                                        <p className="text-sm text-muted-foreground">
                                            {countLabel}
                                        </p>

                                        <div
                                            className={cn(
                                                'overflow-hidden rounded-xl border border-border motion-safe:transition-opacity motion-safe:duration-150',
                                                promoteDialog.searchLoading
                                                    ? 'opacity-60'
                                                    : 'opacity-100',
                                            )}
                                        >
                                            {promoteDialog.searchLoading &&
                                            promoteDialog.searchResults
                                                .length === 0 ? (
                                                <div aria-busy="true">
                                                    <TableSkeleton
                                                        columns={
                                                            promoteSkeletonColumns
                                                        }
                                                        rows={5}
                                                        tableClassName="bg-transparent"
                                                    />
                                                </div>
                                            ) : (
                                                <DataTable
                                                    columns={promoteColumns}
                                                    data={
                                                        promoteDialog.searchResults
                                                    }
                                                    emptyMessage={
                                                        searchQuery === ''
                                                            ? 'No registered members yet. Members must self-register before they can be promoted to staff.'
                                                            : 'No members found.'
                                                    }
                                                    className="rounded-none border-0 bg-transparent"
                                                />
                                            )}
                                            {promoteDialog.searchMeta.total >
                                            0 ? (
                                                <RequestsPager
                                                    page={
                                                        promoteDialog.searchMeta
                                                            .page
                                                    }
                                                    perPage={
                                                        promoteDialog.searchMeta
                                                            .perPage
                                                    }
                                                    total={
                                                        promoteDialog.searchMeta
                                                            .total
                                                    }
                                                    onPageChange={(page) =>
                                                        setPromoteDialog(
                                                            (current) => ({
                                                                ...current,
                                                                page,
                                                            }),
                                                        )
                                                    }
                                                />
                                            ) : null}
                                        </div>

                                        <DialogFooter>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                onClick={resetPromoteDialog}
                                            >
                                                Cancel
                                            </Button>
                                        </DialogFooter>
                                    </div>
                                );
                            })()
                        ) : (
                            <form
                                className="space-y-4"
                                onSubmit={handlePromoteMember}
                            >
                                {promoteDialog.selectedMember ? (
                                    <div className="rounded-xl border border-border bg-muted p-4 text-sm">
                                        <p className="font-semibold">
                                            {
                                                promoteDialog.selectedMember
                                                    .display_name
                                            }
                                        </p>
                                        <p className="mt-0.5 text-muted-foreground">
                                            {promoteDialog.selectedMember
                                                .email ?? '--'}
                                        </p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            Account no:{' '}
                                            {promoteDialog.selectedMember
                                                .acctno ?? '--'}
                                        </p>
                                        {promoteDialog.selectedMember.roles.filter(
                                            (r) => r.name !== 'member',
                                        ).length > 0 ? (
                                            <div className="mt-2 flex flex-wrap gap-2">
                                                {promoteDialog.selectedMember.roles
                                                    .filter(
                                                        (r) =>
                                                            r.name !== 'member',
                                                    )
                                                    .map((r) => (
                                                        <Badge
                                                            key={r.name}
                                                            variant={roleBadgeVariant(
                                                                r.name,
                                                            )}
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
                                    <RadioGroup
                                        className="grid gap-3"
                                        value={promoteDialog.role}
                                        onValueChange={(value) =>
                                            setPromoteDialog((current) => ({
                                                ...current,
                                                role: value as typeof current.role,
                                                errors: {
                                                    ...current.errors,
                                                    role: '',
                                                },
                                            }))
                                        }
                                    >
                                        {editableRoleOptions.map((role) => {
                                            const checked =
                                                promoteDialog.role ===
                                                role.value;

                                            return (
                                                <Label
                                                    key={role.value}
                                                    className={cn(
                                                        'flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-card p-4 transition-colors',
                                                        checked
                                                            ? 'border-primary/40 bg-primary/5'
                                                            : 'hover:border-border',
                                                    )}
                                                >
                                                    <RadioGroupItem
                                                        value={role.value}
                                                        className="mt-0.5"
                                                    />
                                                    <div className="space-y-1">
                                                        <p className="text-sm font-medium">
                                                            {role.label}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {role.description}
                                                        </p>
                                                    </div>
                                                </Label>
                                            );
                                        })}
                                    </RadioGroup>
                                    <InputError
                                        message={promoteDialog.errors.role}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="promote-reason">
                                        Notes
                                    </Label>
                                    <Textarea
                                        id="promote-reason"
                                        className="min-h-24"
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
                                            !promoteDialog.selectedMember
                                                ?.acctno
                                        }
                                    >
                                        {promoteDialog.processing
                                            ? 'Promoting…'
                                            : 'Promote member'}
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
