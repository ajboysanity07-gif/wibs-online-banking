import type { InertiaLinkProps } from '@inertiajs/react';
import { Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Briefcase,
    IdCard,
    Landmark,
    Lock,
    Smartphone,
    Sun,
    User,
    Users,
} from 'lucide-react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { edit as appearanceEdit } from '@/routes/appearance';
import { edit as profileEdit } from '@/routes/profile';
import { security as securitySettings } from '@/routes/settings';
import { show as twoFactorShow } from '@/routes/two-factor';
import { edit as passwordEdit } from '@/routes/user-password';

export type ProfileSectionKey =
    | 'account'
    | 'personal'
    | 'work'
    | 'bank'
    | 'dependents';

export type ProfileSectionControl = {
    active: ProfileSectionKey;
    onSelect: (section: ProfileSectionKey) => void;
};

type NavItem = {
    label: string;
    description: string;
    icon: LucideIcon;
    // Profile sections switch in-page state on the profile page; elsewhere
    // they link to it via the existing ?tab= query.
    section?: ProfileSectionKey;
    memberOnly?: boolean;
    href?: NonNullable<InertiaLinkProps['href']>;
    matchPaths?: Array<NonNullable<InertiaLinkProps['href']>>;
};

const groups: Array<{ heading: string; items: NavItem[] }> = [
    {
        heading: 'Profile',
        items: [
            {
                label: 'Account',
                description: 'Sign-in, photo and contact',
                icon: User,
                section: 'account',
            },
            {
                label: 'Personal',
                description: 'Name, birthdate and civil status',
                icon: IdCard,
                section: 'personal',
                memberOnly: true,
            },
            {
                label: 'Work & Finances',
                description: 'Employment and income',
                icon: Briefcase,
                section: 'work',
                memberOnly: true,
            },
            {
                label: 'Release Method',
                description: 'Bank and payout details',
                icon: Landmark,
                section: 'bank',
                memberOnly: true,
            },
            {
                label: 'Dependents',
                description: 'Spouse and family',
                icon: Users,
                section: 'dependents',
                memberOnly: true,
            },
        ],
    },
    {
        heading: 'Security',
        items: [
            {
                label: 'Password',
                description: 'Change your password',
                icon: Lock,
                href: passwordEdit(),
                matchPaths: [securitySettings()],
            },
            {
                label: 'Two-factor authentication',
                description: 'Extra sign-in protection',
                icon: Smartphone,
                href: twoFactorShow(),
            },
        ],
    },
    {
        heading: 'Preferences',
        items: [
            {
                label: 'Appearance',
                description: 'Theme and display',
                icon: Sun,
                href: appearanceEdit(),
            },
        ],
    },
];

const itemClassName = (active: boolean) =>
    cn(
        'relative flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
        active ? 'bg-secondary' : 'hover:bg-muted',
    );

function ItemBody({ item, active }: { item: NavItem; active: boolean }) {
    return (
        <>
            {active && (
                <span
                    aria-hidden
                    className="absolute inset-y-1.5 left-0 w-1 rounded-full bg-primary"
                />
            )}
            <span
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-lg',
                    active
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted text-primary',
                )}
            >
                <item.icon className="size-4" />
            </span>
            <span className="min-w-0">
                <span
                    className={cn(
                        'block text-sm',
                        active ? 'font-bold' : 'font-semibold',
                    )}
                >
                    {item.label}
                </span>
                <span className="block text-xs text-muted-foreground">
                    {item.description}
                </span>
            </span>
        </>
    );
}

export function SettingsNav({
    profileSection,
    className,
}: {
    profileSection?: ProfileSectionControl;
    className?: string;
}) {
    const { isMatch } = useCurrentUrl();
    const { auth } = usePage<{ auth: { hasMemberAccess: boolean } }>().props;

    return (
        <nav
            aria-label="Settings"
            className={cn(
                'rounded-xl border border-border bg-card p-3 shadow-card lg:sticky lg:top-20',
                className,
            )}
        >
            {groups.map((group) => {
                const items = group.items.filter(
                    (item) => !item.memberOnly || auth.hasMemberAccess,
                );

                return (
                    <div key={group.heading} className="mb-1 last:mb-0">
                        <p className="px-3 pt-3 pb-1 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                            {group.heading}
                        </p>
                        <div className="space-y-0.5">
                            {items.map((item) => {
                                if (item.section) {
                                    const section = item.section;
                                    const active =
                                        profileSection?.active === section;

                                    return profileSection ? (
                                        <button
                                            key={item.label}
                                            type="button"
                                            aria-current={active || undefined}
                                            onClick={() =>
                                                profileSection.onSelect(section)
                                            }
                                            className={itemClassName(active)}
                                        >
                                            <ItemBody
                                                item={item}
                                                active={active}
                                            />
                                        </button>
                                    ) : (
                                        <Link
                                            key={item.label}
                                            href={profileEdit({
                                                query: { tab: section },
                                            })}
                                            prefetch
                                            className={itemClassName(false)}
                                        >
                                            <ItemBody
                                                item={item}
                                                active={false}
                                            />
                                        </Link>
                                    );
                                }

                                const active = item.href
                                    ? isMatch({
                                          href: item.href,
                                          match: 'exact',
                                          matchPaths: item.matchPaths,
                                      })
                                    : false;

                                return (
                                    <Link
                                        key={item.label}
                                        href={item.href ?? '#'}
                                        prefetch
                                        aria-current={
                                            active ? 'page' : undefined
                                        }
                                        className={itemClassName(active)}
                                    >
                                        <ItemBody item={item} active={active} />
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                );
            })}
        </nav>
    );
}
