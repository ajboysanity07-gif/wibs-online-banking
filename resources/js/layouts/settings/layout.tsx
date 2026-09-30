import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { PageShell } from '@/components/page-shell';
import type { ProfileSectionControl } from '@/components/settings/settings-nav';
import { SettingsNav } from '@/components/settings/settings-nav';
import { cn } from '@/lib/utils';
import { edit as profileEdit } from '@/routes/profile';

const backClassName =
    'inline-flex min-h-11 w-fit items-center gap-1 text-sm font-semibold text-primary lg:hidden';

// Below lg the menu and a section are separate screens. On the profile page
// `mobileSectionOpen` toggles between them; other settings pages always show
// their section and link back to the profile menu.
export default function SettingsLayout({
    children,
    profileSection,
    mobileSectionOpen,
    onMobileBack,
}: PropsWithChildren<{
    profileSection?: ProfileSectionControl;
    mobileSectionOpen?: boolean;
    onMobileBack?: () => void;
}>) {
    const isProfile = mobileSectionOpen !== undefined;
    const showMenuOnMobile = isProfile && !mobileSectionOpen;

    return (
        <PageShell size="wide" className="gap-6">
            <div className="space-y-1">
                <h2 className="text-2xl font-bold tracking-tight">Settings</h2>
                <p className="text-sm text-muted-foreground">
                    Manage your profile, security and preferences.
                </p>
            </div>
            <div className="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)] lg:items-start">
                <SettingsNav
                    profileSection={profileSection}
                    className={cn(!showMenuOnMobile && 'max-lg:hidden')}
                />
                <div
                    className={cn(
                        'min-w-0 space-y-6',
                        showMenuOnMobile && 'max-lg:hidden',
                    )}
                >
                    {isProfile ? (
                        <button
                            type="button"
                            onClick={onMobileBack}
                            className={backClassName}
                        >
                            <ChevronLeft className="size-4" />
                            Settings
                        </button>
                    ) : (
                        <Link href={profileEdit()} className={backClassName}>
                            <ChevronLeft className="size-4" />
                            Settings
                        </Link>
                    )}
                    {children}
                </div>
            </div>
        </PageShell>
    );
}
