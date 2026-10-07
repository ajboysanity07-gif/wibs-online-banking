import { Head } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import { SettingsPanel } from '@/components/settings/settings-panel';
import { Button } from '@/components/ui/button';
import type { Appearance as AppearanceMode } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: editAppearance().url,
    },
];

const options: { value: AppearanceMode; label: string; icon: LucideIcon }[] = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'Match device', icon: Monitor },
];

export default function Appearance() {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Appearance settings" />

            <h1 className="sr-only">Appearance Settings</h1>

            <SettingsLayout>
                <SettingsPanel
                    title="Appearance"
                    description="Update your account's appearance settings."
                >
                    <div
                        role="radiogroup"
                        aria-label="Theme"
                        className="grid gap-3 border-t border-border px-6 py-6 sm:grid-cols-3"
                    >
                        {options.map(({ value, label, icon: Icon }) => {
                            const selected = appearance === value;

                            return (
                                <Button
                                    variant="ghost"
                                    key={value}
                                    type="button"
                                    role="radio"
                                    aria-checked={selected}
                                    onClick={() => updateAppearance(value)}
                                    className={cn(
                                        'h-auto flex-col gap-3 rounded-xl border-2 p-5 text-sm font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        selected
                                            ? 'border-primary bg-secondary text-secondary-foreground'
                                            : 'border-border bg-card hover:bg-muted',
                                    )}
                                >
                                    <Icon className="size-6 text-primary" />
                                    {label}
                                </Button>
                            );
                        })}
                    </div>
                </SettingsPanel>
            </SettingsLayout>
        </AppLayout>
    );
}
