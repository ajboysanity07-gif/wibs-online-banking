import { Head } from '@inertiajs/react';
import DeleteUser from '@/components/delete-user';
import { PasswordRow } from '@/components/settings/password-row';
import {
    SettingsPanel,
    SettingsRow,
} from '@/components/settings/settings-panel';
import { TwoFactorSection } from '@/components/settings/two-factor-section';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { security as securitySettings } from '@/routes/settings';
import type { BreadcrumbItem } from '@/types';

type Props = {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
    twoFactorAvailable?: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Security settings',
        href: securitySettings().url,
    },
];

export default function Security({
    requiresConfirmation = false,
    twoFactorEnabled = false,
    twoFactorAvailable = true,
}: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Security settings" />

            <h1 className="sr-only">Security Settings</h1>

            <SettingsLayout>
                <SettingsPanel
                    title="Security"
                    description="Manage your password and two-factor authentication."
                >
                    <PasswordRow />
                    <TwoFactorSection
                        requiresConfirmation={requiresConfirmation}
                        twoFactorEnabled={twoFactorEnabled}
                        twoFactorAvailable={twoFactorAvailable}
                    />
                    <SettingsRow label="Account">
                        <DeleteUser />
                    </SettingsRow>
                </SettingsPanel>
            </SettingsLayout>
        </AppLayout>
    );
}
