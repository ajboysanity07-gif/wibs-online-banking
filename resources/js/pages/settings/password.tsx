import { Head } from '@inertiajs/react';
import { PasswordRow } from '@/components/settings/password-row';
import { SettingsPanel } from '@/components/settings/settings-panel';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/user-password';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Password settings',
        href: edit().url,
    },
];

export default function Password() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Password settings" />

            <h1 className="sr-only">Password Settings</h1>

            <SettingsLayout>
                <SettingsPanel
                    title="Password"
                    description="Keep your account secure with a strong password."
                >
                    <PasswordRow />
                </SettingsPanel>
            </SettingsLayout>
        </AppLayout>
    );
}
