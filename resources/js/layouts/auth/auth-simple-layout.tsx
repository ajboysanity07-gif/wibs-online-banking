import { Link } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { SurfaceCard } from '@/components/surface-card';
import SupportContact from '@/components/support-contact';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-gradient-to-b from-muted/30 via-background to-background p-6 md:p-10">
            <div className="w-full max-w-md">
                <SurfaceCard variant="default" padding="lg">
                    <div className="flex flex-col gap-8">
                        <div className="flex flex-col items-center gap-4">
                            <Link
                                href={home()}
                                className="flex flex-col items-center gap-2 font-medium"
                            >
                                <AppLogo
                                    variant="stacked"
                                    iconClassName="h-12 w-auto object-contain"
                                />
                                <span className="sr-only">{title}</span>
                            </Link>

                            <div className="space-y-2 text-center">
                                <h1 className="text-xl font-medium">
                                    {title}
                                </h1>
                                <p className="text-center text-sm text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                        </div>
                        {children}
                        <SupportContact
                            variant="stacked"
                            className="text-center"
                        />
                    </div>
                </SurfaceCard>
            </div>
        </div>
    );
}
