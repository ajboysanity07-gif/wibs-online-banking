import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import { logout } from '@/routes';

export default function PendingApproval() {
    return (
        <AuthLayout
            title="Pending approval"
            description="Your account is awaiting admin approval"
        >
            <Head title="Pending approval" />
            <div className="flex flex-col gap-6">
                <p className="text-sm text-muted-foreground">
                    You'll be able to access your dashboard once your
                    registration is approved.
                </p>
                <div className="flex flex-col gap-3">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() => router.reload()}
                    >
                        Refresh status
                    </Button>
                    <Button asChild>
                        <Link href={logout()} method="post" as="button">
                            Log out
                        </Link>
                    </Button>
                </div>
            </div>
        </AuthLayout>
    );
}
