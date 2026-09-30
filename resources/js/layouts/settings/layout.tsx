import type { PropsWithChildren } from 'react';
import { PageShell } from '@/components/page-shell';
import type { ProfileSectionControl } from '@/components/settings/settings-nav';
import { SettingsNav } from '@/components/settings/settings-nav';

export default function SettingsLayout({
    children,
    profileSection,
}: PropsWithChildren<{ profileSection?: ProfileSectionControl }>) {
    return (
        <PageShell size="wide" className="gap-6">
            <div className="space-y-1">
                <h2 className="text-2xl font-bold tracking-tight">Settings</h2>
                <p className="text-sm text-muted-foreground">
                    Manage your profile, security and preferences.
                </p>
            </div>
            <div className="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)] lg:items-start">
                <SettingsNav profileSection={profileSection} />
                <div className="min-w-0 space-y-6">{children}</div>
            </div>
        </PageShell>
    );
}
