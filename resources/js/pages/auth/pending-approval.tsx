import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import api from '@/lib/api';

export default function PendingApproval() {
    const [refreshing, setRefreshing] = useState(false);
    const [loggingOut, setLoggingOut] = useState(false);

    const refreshStatus = async () => {
        setRefreshing(true);

        try {
            const response = await api.get('/spa/auth/me');
            const user = response.data?.data?.user;

            if (user?.role === 'admin') {
                router.visit('/admin/dashboard');
                return;
            }

            if (user?.status === 'active') {
                router.visit('/client/dashboard');
            }
        } catch (error) {
            if (axios.isAxiosError(error)) {
                return;
            }
        } finally {
            setRefreshing(false);
        }
    };

    const handleLogout = async () => {
        setLoggingOut(true);

        try {
            const response = await api.post('/spa/auth/logout');
            const redirectTo = response.data?.redirect_to ?? '/';

            router.visit(redirectTo);
        } catch (error) {
            if (axios.isAxiosError(error)) {
                return;
            }
        } finally {
            setLoggingOut(false);
        }
    };

    return (
        <AuthLayout
            title="Account unavailable"
            description="Your account is currently unavailable"
        >
            <Head title="Account unavailable" />
            <div className="flex flex-col gap-6">
                <p className="text-sm text-muted-foreground">
                    Your account access is on hold. Please contact your
                    cooperative if you believe this is a mistake.
                </p>
                <div className="flex flex-col gap-3">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={refreshStatus}
                        disabled={refreshing}
                    >
                        Refresh status
                    </Button>
                    <Button
                        type="button"
                        onClick={handleLogout}
                        disabled={loggingOut}
                    >
                        Log out
                    </Button>
                </div>
            </div>
        </AuthLayout>
    );
}
