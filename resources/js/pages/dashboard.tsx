import { Head } from '@inertiajs/react';
import { PageShell } from '@/components/page-shell';
import { SurfaceCard } from '@/components/surface-card';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <PageShell size="wide">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    {Array.from({ length: 3 }).map((_, index) => (
                        <SurfaceCard
                            key={`dashboard-card-${index}`}
                            variant="default"
                            padding="none"
                            className="relative aspect-video overflow-hidden"
                        >
                            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                        </SurfaceCard>
                    ))}
                </div>
                <SurfaceCard
                    variant="default"
                    padding="none"
                    className="relative min-h-[60vh] overflow-hidden"
                >
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                </SurfaceCard>
            </PageShell>
        </AppLayout>
    );
}
