import { Head, Link, router, usePage } from '@inertiajs/react';
import { Clock, FileText, Settings, ShieldBan } from 'lucide-react';
import type { ComponentType } from 'react';
import AppLogo from '@/components/app-logo';
import SupportContact from '@/components/support-contact';
import { SurfaceCard } from '@/components/surface-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard, home } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import type { Auth } from '@/types';

type ErrorPageProps = {
    status: number;
};

type StatusCopy = {
    title: string;
    description: string;
    hint?: string;
    icon: ComponentType<{ className?: string }>;
};

const statusCopy: Record<number, StatusCopy> = {
    403: {
        title: 'Access restricted',
        description: 'You do not have permission to view this page.',
        hint: 'If you think this is a mistake, contact support for help.',
        icon: ShieldBan,
    },
    404: {
        title: 'Page not found',
        description:
            'The page you requested may have moved or is no longer available.',
        hint: 'Check the link or return to your dashboard.',
        icon: FileText,
    },
    419: {
        title: 'Session expired',
        description:
            'Your session timed out for security. Please return to your dashboard and try again.',
        hint: 'If you were submitting a form, review your entries before resubmitting.',
        icon: Clock,
    },
    429: {
        title: 'Too many requests',
        description:
            'We are receiving a lot of activity from you right now. Please wait a moment and try again.',
        hint: 'If this keeps happening, slow down and retry in a few minutes.',
        icon: Clock,
    },
    500: {
        title: 'Something went wrong',
        description:
            'We could not complete that request. Please try again shortly.',
        hint: 'We are already looking into the issue.',
        icon: Settings,
    },
    503: {
        title: 'Service unavailable',
        description:
            'The portal is temporarily unavailable for maintenance or high traffic.',
        hint: 'Please try again soon.',
        icon: Settings,
    },
};

const fallbackCopy: StatusCopy = {
    title: 'Something went wrong',
    description: 'We could not complete that request. Please try again.',
    hint: 'If the issue persists, contact support.',
    icon: Settings,
};

type SharedProps = {
    auth: Auth;
};

const resolvePrimaryCta = (auth: Auth) => {
    if (auth.isAdmin) {
        return {
            label: 'Go to admin dashboard',
            route: adminDashboard(),
        };
    }

    if (auth.hasMemberAccess) {
        return {
            label: 'Go to dashboard',
            route: dashboard(),
        };
    }

    return {
        label: 'Back to home',
        route: home(),
    };
};

export default function ErrorPage({ status }: ErrorPageProps) {
    const { auth } = usePage<SharedProps>().props;
    const copy = statusCopy[status] ?? fallbackCopy;
    const { label, route } = resolvePrimaryCta(auth);
    const Icon = copy.icon;

    const handleBack = () => {
        if (typeof window === 'undefined') {
            return;
        }

        if (window.history.length > 1) {
            window.history.back();
            return;
        }

        router.visit(route.url);
    };

    return (
        <div className="relative min-h-screen overflow-hidden bg-background text-foreground">
            <Head title={`${status} ${copy.title}`} />

            <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_15%,hsl(var(--primary)/0.16),transparent_38%),radial-gradient(circle_at_85%_10%,hsl(var(--accent)/0.2),transparent_42%)]" />
            <div className="absolute inset-x-0 top-0 h-56 bg-linear-to-b from-primary/15 via-transparent to-transparent blur-3xl" />
            <div className="absolute bottom-0 right-0 h-56 w-56 rounded-full bg-linear-to-br from-primary/20 via-transparent to-transparent blur-3xl" />

            <div className="relative mx-auto flex min-h-screen w-full max-w-5xl flex-col justify-center px-6 py-12 lg:px-10">
                <div className="flex flex-col gap-6">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <Link href={route} className="focus:outline-none">
                            <AppLogo className="text-foreground" />
                        </Link>
                        <Badge
                            variant="outline"
                            className="border-border/60 bg-card/60 px-3 py-1 text-xs font-semibold uppercase tracking-[0.3em] text-muted-foreground"
                        >
                            Error {status}
                        </Badge>
                    </div>

                    <SurfaceCard
                        variant="hero"
                        padding="lg"
                        className="relative overflow-hidden border-border/60 bg-card/70 shadow-xl backdrop-blur-md motion-safe:animate-in motion-safe:fade-in motion-safe:slide-in-from-bottom-2 motion-safe:duration-500"
                    >
                        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,hsl(var(--primary)/0.15),transparent_35%)]" />

                        <div className="relative grid gap-8 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
                            <div className="space-y-4">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                        <Icon className="h-5 w-5" />
                                    </div>
                                    <p className="text-xs font-semibold uppercase tracking-[0.32em] text-muted-foreground">
                                        {status} status
                                    </p>
                                </div>

                                <div className="space-y-3">
                                    <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                                        {copy.title}
                                    </h1>
                                    <p className="text-base text-muted-foreground sm:text-lg">
                                        {copy.description}
                                    </p>
                                    {copy.hint ? (
                                        <p className="text-sm text-muted-foreground">
                                            {copy.hint}
                                        </p>
                                    ) : null}
                                </div>

                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <Button
                                        asChild
                                        size="lg"
                                        className="shadow-sm"
                                    >
                                        <Link href={route} prefetch>
                                            {label}
                                        </Link>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="lg"
                                        onClick={handleBack}
                                    >
                                        Go back
                                    </Button>
                                </div>
                            </div>

                            <div className="relative">
                                <div className="pointer-events-none absolute inset-0 rounded-3xl bg-linear-to-br from-primary/15 via-transparent to-[hsl(var(--accent)/0.22)] blur-3xl" />
                                <div className="relative space-y-4 rounded-3xl border border-border/70 bg-card/70 p-6 shadow-lg backdrop-blur-md">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="text-sm font-semibold">
                                            Need assistance?
                                        </p>
                                        <Badge
                                            variant="secondary"
                                            className="text-xs"
                                        >
                                            Support
                                        </Badge>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        If the issue persists, our team can help
                                        you get back on track.
                                    </p>
                                    <SupportContact />
                                </div>
                            </div>
                        </div>
                    </SurfaceCard>
                </div>
            </div>
        </div>
    );
}
