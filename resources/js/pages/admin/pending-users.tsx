import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { approve, pending } from '@/routes/admin/users';
import type { BreadcrumbItem } from '@/types';

type PendingUser = {
    user_id: number;
    acctno: string;
    username: string;
    email: string;
    phoneno: string | null;
    created_at: string;
};

type Props = {
    pendingUsers: PendingUser[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pending approvals',
        href: pending().url,
    },
];

export default function PendingUsers({ pendingUsers }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pending approvals" />
            <div className="flex flex-col gap-4 p-4">
                <div className="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-sidebar-border/70 text-xs uppercase text-muted-foreground dark:border-sidebar-border">
                                <tr>
                                    <th className="px-4 py-3">Account</th>
                                    <th className="px-4 py-3">Name</th>
                                    <th className="px-4 py-3">Email</th>
                                    <th className="px-4 py-3">Phone</th>
                                    <th className="px-4 py-3">Requested</th>
                                    <th className="px-4 py-3 text-right">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {pendingUsers.length === 0 ? (
                                    <tr>
                                        <td
                                            className="px-4 py-6 text-center text-sm text-muted-foreground"
                                            colSpan={6}
                                        >
                                            No pending approvals.
                                        </td>
                                    </tr>
                                ) : (
                                    pendingUsers.map((user) => (
                                        <tr
                                            key={user.user_id}
                                            className="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                                        >
                                            <td className="px-4 py-3 font-medium">
                                                {user.acctno}
                                            </td>
                                            <td className="px-4 py-3">
                                                {user.username}
                                            </td>
                                            <td className="px-4 py-3">
                                                {user.email}
                                            </td>
                                            <td className="px-4 py-3">
                                                {user.phoneno ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {new Date(
                                                    user.created_at,
                                                ).toLocaleDateString()}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Form
                                                    {...approve.form(
                                                        user.user_id,
                                                    )}
                                                >
                                                    {({ processing }) => (
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            disabled={processing}
                                                        >
                                                            Approve
                                                        </Button>
                                                    )}
                                                </Form>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
