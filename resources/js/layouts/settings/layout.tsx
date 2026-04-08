import type { PropsWithChildren } from 'react';
import { PageHero } from '@/components/page-hero';
import { PageShell } from '@/components/page-shell';
import { SettingsNav } from '@/components/settings/settings-nav';

export default function SettingsLayout({ children }: PropsWithChildren) {
    return (
        <PageShell size="wide" className="gap-8">
            <PageHero
                kicker="Settings"
                title="Settings"
                description="Manage your profile, security, and appearance settings."
            />
            <SettingsNav />
            {children}
        </PageShell>
    );
}
