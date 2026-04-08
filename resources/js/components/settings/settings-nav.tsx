import type { InertiaLinkProps } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { edit as appearanceEdit } from '@/routes/appearance';
import { edit as profileEdit } from '@/routes/profile';
import { security as securitySettings } from '@/routes/settings';
import { edit as passwordEdit } from '@/routes/user-password';
import { show as twoFactorShow } from '@/routes/two-factor';

type SettingsNavItem = {
    label: string;
    href: NonNullable<InertiaLinkProps['href']>;
    match?: 'exact' | 'section';
    matchPaths?: Array<NonNullable<InertiaLinkProps['href']>>;
};

const settingsNavItems: SettingsNavItem[] = [
    {
        label: 'Profile',
        href: profileEdit(),
    },
    {
        label: 'Security',
        href: securitySettings(),
        match: 'section',
        matchPaths: [securitySettings(), passwordEdit(), twoFactorShow()],
    },
    {
        label: 'Appearance',
        href: appearanceEdit(),
    },
];

export function SettingsNav() {
    const { isMatch } = useCurrentUrl();

    return (
        <nav aria-label="Settings" className="flex flex-wrap gap-2">
            {settingsNavItems.map((item) => {
                const isActive = isMatch({
                    href: item.href,
                    match: item.match,
                    matchPaths: item.matchPaths,
                });

                return (
                    <Link
                        key={item.label}
                        href={item.href}
                        prefetch
                        className={cn(
                            'rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                            isActive
                                ? 'bg-muted text-foreground shadow-sm'
                                : 'text-muted-foreground hover:bg-muted/70 hover:text-foreground',
                        )}
                    >
                        {item.label}
                    </Link>
                );
            })}
        </nav>
    );
}
