import { Form, Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { approve } from '@/routes/admin/users';
import type { BreadcrumbItem } from '@/types';

type PendingUser = {
    user_id: number;
    acctno: string | null;
    username: string;
    email: string;
    created_at: string;
};

type SearchResult = {
    user_id: number;
    acctno: string | null;
    username: string;
    email: string;
    status: string | null;
    created_at: string | null;
};

type Metrics = {
    pendingCount: number;
    activeCount: number;
    totalCount: number;
    requestsCount: number | null;
    lastSync: string | null;
};

type Props = {
    metrics: Metrics;
    pendingUsers: PendingUser[];
    search: string;
    searchResults: SearchResult[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin Dashboard',
        href: '/admin/dashboard',
    },
];

const formatDate = (value?: string | null): string => {
    if (!value) {
        return '--';
    }

    return new Date(value).toLocaleDateString();
};

export default function AdminDashboard({
    metrics,
    pendingUsers,
    search,
    searchResults,
}: Props) {
    const hasSearch = search.trim().length > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />
            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold">
                            Admin Dashboard
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Member approvals, requests, and system overview
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild size="sm">
                            <a href="#pending-approvals">Approve Members</a>
                        </Button>
                        <Button asChild size="sm" variant="secondary">
                            <a href="#requests">Review Requests</a>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <a href="#member-lookup">Member Lookup</a>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <Card>
                        <CardHeader>
                            <CardDescription>
                                Pending member approvals
                            </CardDescription>
                            <CardTitle className="text-3xl">
                                {metrics.pendingCount}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Awaiting review and approval
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Active members</CardDescription>
                            <CardTitle className="text-3xl">
                                {metrics.activeCount}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Approved portal access
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Total portal users</CardDescription>
                            <CardTitle className="text-3xl">
                                {metrics.totalCount}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                All logins in the portal
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>
                                Requests awaiting review
                            </CardDescription>
                            <CardTitle className="text-3xl">
                                {metrics.requestsCount ?? '--'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Not available yet
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>WIBS Desktop sync</CardDescription>
                            <CardTitle className="text-2xl">
                                {metrics.lastSync ?? '--'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                System of record processing
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card id="pending-approvals">
                    <CardHeader>
                        <CardTitle>Pending member approvals</CardTitle>
                        <CardDescription>
                            Review new portal registrations before activation.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="px-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-sidebar-border/70 text-xs uppercase text-muted-foreground dark:border-sidebar-border">
                                    <tr>
                                        <th className="px-6 py-3">Member</th>
                                        <th className="px-6 py-3">
                                            Account No
                                        </th>
                                        <th className="px-6 py-3">Email</th>
                                        <th className="px-6 py-3">Created</th>
                                        <th className="px-6 py-3">Status</th>
                                        <th className="px-6 py-3 text-right">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {pendingUsers.length === 0 ? (
                                        <tr>
                                            <td
                                                className="px-6 py-6 text-center text-sm text-muted-foreground"
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
                                                <td className="px-6 py-3 font-medium">
                                                    {user.username}
                                                </td>
                                                <td className="px-6 py-3">
                                                    {user.acctno ?? '--'}
                                                </td>
                                                <td className="px-6 py-3">
                                                    {user.email}
                                                </td>
                                                <td className="px-6 py-3">
                                                    {formatDate(
                                                        user.created_at,
                                                    )}
                                                </td>
                                                <td className="px-6 py-3">
                                                    <Badge variant="secondary">
                                                        Pending
                                                    </Badge>
                                                </td>
                                                <td className="px-6 py-3 text-right">
                                                    <Form
                                                        {...approve.form(
                                                            user.user_id,
                                                        )}
                                                    >
                                                        {({ processing }) => (
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                disabled={
                                                                    processing
                                                                }
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
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card id="requests">
                        <CardHeader>
                            <CardTitle>Recent requests</CardTitle>
                            <CardDescription>
                                Track member-submitted requests that need review.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="rounded-lg border border-dashed border-border/60 p-6 text-center text-sm text-muted-foreground">
                                Requests module coming soon.
                            </div>
                        </CardContent>
                    </Card>

                    <Card id="member-lookup">
                        <CardHeader>
                            <CardTitle>Member lookup</CardTitle>
                            <CardDescription>
                                Search by account no, username, or email.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <Form
                                action="/admin/dashboard"
                                method="get"
                                className="flex flex-col gap-2 sm:flex-row sm:items-center"
                            >
                                <Input
                                    name="search"
                                    defaultValue={search}
                                    placeholder="Search by account no, username, or email"
                                    className="sm:flex-1"
                                />
                                <Button type="submit" size="sm">
                                    Search
                                </Button>
                            </Form>

                            <div className="rounded-lg border border-border/60">
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead className="border-b border-border/60 text-xs uppercase text-muted-foreground">
                                            <tr>
                                                <th className="px-4 py-3">
                                                    Member
                                                </th>
                                                <th className="px-4 py-3">
                                                    Account No
                                                </th>
                                                <th className="px-4 py-3">
                                                    Email
                                                </th>
                                                <th className="px-4 py-3">
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {!hasSearch ? (
                                                <tr>
                                                    <td
                                                        className="px-4 py-6 text-center text-sm text-muted-foreground"
                                                        colSpan={4}
                                                    >
                                                        Enter a search term to
                                                        look up members.
                                                    </td>
                                                </tr>
                                            ) : searchResults.length === 0 ? (
                                                <tr>
                                                    <td
                                                        className="px-4 py-6 text-center text-sm text-muted-foreground"
                                                        colSpan={4}
                                                    >
                                                        {`No members match "${search}".`}
                                                    </td>
                                                </tr>
                                            ) : (
                                                searchResults.map((result) => (
                                                    <tr
                                                        key={result.user_id}
                                                        className="border-b border-border/60 last:border-b-0"
                                                    >
                                                        <td className="px-4 py-3 font-medium">
                                                            {result.username}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {result.acctno ??
                                                                '--'}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {result.email}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <Badge
                                                                variant={
                                                                    result.status ===
                                                                    'active'
                                                                        ? 'default'
                                                                        : result.status ===
                                                                            'pending'
                                                                          ? 'secondary'
                                                                          : 'outline'
                                                                }
                                                            >
                                                                {result.status ??
                                                                    'Unknown'}
                                                            </Badge>
                                                        </td>
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
