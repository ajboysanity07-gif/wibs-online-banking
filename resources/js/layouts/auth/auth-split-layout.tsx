import { Link } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import SupportContact from '@/components/support-contact';
import { SurfaceCard } from '@/components/surface-card';
import { useBranding } from '@/hooks/use-branding';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const branding = useBranding();

    return (
        <div className="grid min-h-svh lg:grid-cols-2">
            <div className="relative hidden flex-col justify-between bg-sidebar p-12 text-sidebar-foreground lg:flex">
                <Link href={home()} className="flex items-center gap-2">
                    <AppLogo
                        iconClassName="size-10 shrink-0 rounded-full bg-sidebar-foreground object-contain p-1.5"
                        titleClassName="text-lg font-bold text-sidebar-foreground"
                        subtitleClassName="text-sm text-sidebar-foreground/75"
                    />
                </Link>
                <div className="space-y-4">
                    <p className="max-w-md text-4xl leading-tight font-bold tracking-tight">
                        Your loans and loan security, in one place.
                    </p>
                    <p className="max-w-sm text-base text-sidebar-foreground/75">
                        Check balances, payment schedules and loan requests
                        anytime.
                    </p>
                </div>
                <p className="text-sm text-sidebar-foreground/75">
                    © {branding.companyName}
                    {branding.portalLabel ? ` · ${branding.portalLabel}` : ''}
                </p>
            </div>
            <div className="flex items-center justify-center bg-background p-6 sm:p-8">
                <div className="w-full max-w-md space-y-6">
                    <Link
                        href={home()}
                        className="flex justify-center lg:hidden"
                    >
                        <AppLogo variant="stacked" />
                    </Link>
                    <SurfaceCard padding="lg">
                        <div className="flex flex-col gap-6">
                            <div className="space-y-1">
                                <h1 className="text-2xl font-bold tracking-tight">
                                    {title}
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                            {children}
                        </div>
                    </SurfaceCard>
                    <SupportContact variant="stacked" className="text-center" />
                </div>
            </div>
        </div>
    );
}
