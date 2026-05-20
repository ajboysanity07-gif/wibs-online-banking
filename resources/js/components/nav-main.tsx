import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import type { NavItem } from '@/types';

export function NavMain({
    items = [],
    label = 'Platform',
    collapsibleStorageKey,
}: {
    items: NavItem[];
    label?: string;
    collapsibleStorageKey?: string;
}) {
    const { isMatch } = useCurrentUrl();
    const [isCollapsed, setIsCollapsed] = useState<boolean>(() => {
        if (typeof window === 'undefined' || !collapsibleStorageKey) {
            return false;
        }

        return window.localStorage.getItem(collapsibleStorageKey) === 'true';
    });
    const groupContentId = useMemo(
        () =>
            `${(collapsibleStorageKey ?? label)
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')}-content`,
        [collapsibleStorageKey, label],
    );

    const handleToggleCollapse = (): void => {
        setIsCollapsed((currentValue) => {
            const nextValue = !currentValue;

            if (
                typeof window !== 'undefined' &&
                collapsibleStorageKey
            ) {
                window.localStorage.setItem(
                    collapsibleStorageKey,
                    String(nextValue),
                );
            }

            return nextValue;
        });
    };

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel asChild>
                <button
                    type="button"
                    className="w-full"
                    aria-expanded={!isCollapsed}
                    aria-controls={groupContentId}
                    onClick={handleToggleCollapse}
                >
                    <span>{label}</span>
                    <ChevronDown
                        className={cn(
                            'ml-auto transition-transform duration-200',
                            isCollapsed ? '-rotate-90' : 'rotate-0',
                        )}
                    />
                </button>
            </SidebarGroupLabel>

            <SidebarGroupContent
                id={groupContentId}
                className={cn(isCollapsed && 'hidden')}
            >
                <SidebarMenu>
                    {items.map((item) => (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={
                                    isMatch({
                                        href: item.href,
                                        match: item.match,
                                        matchPaths: item.matchPaths,
                                        excludeMatchPaths:
                                            item.excludeMatchPaths,
                                    })
                                }
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}
