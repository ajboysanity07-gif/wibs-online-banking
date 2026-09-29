import { Head } from '@inertiajs/react';
import { SettingsPanel } from '@/components/settings/settings-panel';
import { TwoFactorSection } from '@/components/settings/two-factor-section';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { show } from '@/routes/two-factor';
import type { BreadcrumbItem } from '@/types';

type Props = {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Two-Factor Authentication',
        href: show.url(),
    },
];

export default function TwoFactor({
    requiresConfirmation = false,
    twoFactorEnabled = false,
}: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Two-Factor Authentication" />

            <h1 className="sr-only">Two-Factor Authentication Settings</h1>

            <SettingsLayout>
                <SettingsPanel
                    title="Two-factor authentication"
                    description="Manage your two-factor authentication settings."
                >
                    <TwoFactorSection
                        requiresConfirmation={requiresConfirmation}
                        twoFactorEnabled={twoFactorEnabled}
                    />
                </SettingsPanel>
            </SettingsLayout>
        </AppLayout>
    );
}
