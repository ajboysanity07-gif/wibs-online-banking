import { Head, Link, router, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowLeft,
    Clock3,
    Home,
    LifeBuoy,
    RefreshCw,
    SearchX,
    ServerCrash,
    ShieldBan,
    TriangleAlert,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import SupportContact from '@/components/support-contact';
import { SurfaceCard } from '@/components/surface-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { dashboard, home } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import type { Auth, Branding } from '@/types';

type ErrorPageProps = {
    status: number;
};

type StatusTone = 'info' | 'warning' | 'danger';

type StatusCopy = {
    title: string;
    description: string;
    hint?: string;
    eyebrow: string;
    icon: LucideIcon;
    tone: StatusTone;
    recommendations: string[];
};

const statusCopy: Record<number, StatusCopy> = {
    403: {
        title: 'Access restricted',
        description:
            'This area is protected. Your account does not currently have permission to view it.',
        hint: 'If you expected access here, contact support or your administrator for assistance.',
        eyebrow: 'Permission required',
        icon: ShieldBan,
        tone: 'warning',
        recommendations: [
            'Return to a page you normally use, such as your dashboard or home screen.',
            'Confirm you are signed in with the correct account and role.',
            'Reach out to support if you believe access should already be enabled.',
        ],
    },
    404: {
        title: 'Page not found',
        description:
            'The page you requested may have moved, been removed, or the link may be incomplete.',
        hint: 'Use the dashboard or home page to continue browsing from a stable starting point.',
        eyebrow: 'Missing destination',
        icon: SearchX,
        tone: 'info',
        recommendations: [
            'Check the URL or navigation path that brought you here.',
            'Return to your dashboard and reopen the section from the main navigation.',
            'If someone shared this link with you, ask for the latest destination.',
        ],
    },
    419: {
        title: 'Session expired',
        description:
            'Your session timed out to protect your account. Refresh the page or return to the portal to continue securely.',
        hint: 'If you were working on a form, review your information after reloading before submitting again.',
        eyebrow: 'Security timeout',
        icon: Clock3,
        tone: 'warning',
        recommendations: [
            'Reload the page to establish a fresh session.',
            'If needed, return to the dashboard and reopen the task you were completing.',
            'Before resubmitting any form, quickly confirm your entries are still correct.',
        ],
    },
    429: {
        title: 'Too many requests',
        description:
            'The system is receiving requests too quickly from this session. Please pause briefly before trying again.',
        hint: 'This limit helps keep the portal stable and responsive for everyone.',
        eyebrow: 'Rate limit reached',
        icon: Clock3,
        tone: 'warning',
        recommendations: [
            'Wait a moment before retrying the same action.',
            'Avoid rapid repeated clicks or refreshes while the page is processing.',
            'If this continues after a short pause, contact support with the action you were taking.',
        ],
    },
    500: {
        title: 'Something went wrong',
        description:
            'The portal could not complete that request. We recommend retrying shortly or returning to a stable page.',
        hint: 'If the issue persists, support can help you verify whether the problem is already being investigated.',
        eyebrow: 'Unexpected issue',
        icon: ServerCrash,
        tone: 'danger',
        recommendations: [
            'Try the request again in a moment to rule out a temporary issue.',
            'If you need to continue working now, return to the dashboard or home page.',
            'Contact support if the same page keeps failing or blocks an important task.',
        ],
    },
    503: {
        title: 'Service temporarily unavailable',
        description:
            'The portal is temporarily unavailable, which can happen during maintenance or a short-lived service disruption.',
        hint: 'Please try again soon. Your branded error page remains available so you still have a clear path back into the app.',
        eyebrow: 'Temporary outage',
        icon: TriangleAlert,
        tone: 'danger',
        recommendations: [
            'Wait a short moment, then retry the page.',
            'If you only need general navigation, return to the home page and try again later.',
            'If the outage lasts longer than expected, contact support for an update.',
        ],
    },
};

const fallbackCopy: StatusCopy = {
    title: 'Something went wrong',
    description:
        'We could not complete that request. Please try again or return to a safe page in the portal.',
    hint: 'If the issue continues, contact support for help.',
    eyebrow: 'Unexpected issue',
    icon: ServerCrash,
    tone: 'danger',
    recommendations: [
        'Retry the action after a short pause.',
        'Return to the dashboard or home page if you need a stable starting point.',
        'Contact support if the issue continues.',
    ],
};

type SharedProps = {
    auth?: Partial<Auth> | null;
    branding?: Branding | null;
};

const toneClassNames: Record<StatusTone, string> = {
    info: 'bg-primary/10 text-primary ring-primary/15',
    warning:
        'bg-amber-500/10 text-amber-700 ring-amber-500/15 dark:bg-amber-500/15 dark:text-amber-200',
    danger:
        'bg-rose-500/10 text-rose-700 ring-rose-500/15 dark:bg-rose-500/15 dark:text-rose-200',
};

const reloadableStatuses = [419, 429, 500, 503];

const resolvePrimaryCta = (auth?: Partial<Auth> | null) => {
    if (auth?.isAdmin) {
        return {
            label: 'Go to admin dashboard',
            route: adminDashboard(),
        };
    }

    if (auth?.hasMemberAccess) {
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
    const { auth, branding } = usePage<SharedProps>().props;
    const copy = statusCopy[status] ?? fallbackCopy;
    const { label, route } = resolvePrimaryCta(auth);
    const Icon = copy.icon;
    const canReload = reloadableStatuses.includes(status);
    const hasSupportDetails = Boolean(
        branding?.supportContactName ||
            branding?.supportEmail ||
            branding?.supportPhone,
    );
    const brandLabel =
        branding?.portalLabel?.trim() ||
        branding?.companyName?.trim() ||
        'Member Portal';

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

    const handleReload = () => {
        if (typeof window === 'undefined') {
            return;
        }

        window.location.reload();
    };

    return (
        <div className="relative min-h-screen overflow-hidden bg-background text-foreground">
            <Head title={`${status} ${copy.title}`} />

            <div className="absolute inset-0 bg-[radial-gradient(circle_at_14%_16%,hsl(var(--primary)/0.14),transparent_34%),radial-gradient(circle_at_84%_12%,hsl(var(--accent)/0.18),transparent_32%),radial-gradient(circle_at_50%_100%,hsl(var(--primary)/0.08),transparent_45%)]" />
            <div className="absolute inset-x-0 top-0 h-64 bg-linear-to-b from-primary/10 via-transparent to-transparent" />
            <div className="absolute bottom-0 right-0 h-72 w-72 rounded-full bg-accent/15 blur-3xl" />

            <div className="relative mx-auto flex min-h-screen w-full max-w-6xl flex-col justify-center px-6 py-10 sm:py-14 lg:px-10">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                    <Link
                        href={route}
                        className="rounded-2xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40"
                        prefetch
                    >
                        <AppLogo className="items-center" />
                    </Link>

                    <div className="flex flex-wrap items-center gap-2">
                        <Badge
                            variant="outline"
                            className="rounded-full border-border/60 bg-card/70 px-3 py-1 text-[11px] font-medium text-muted-foreground"
                        >
                            {brandLabel}
                        </Badge>
                        <Badge
                            variant="outline"
                            className="rounded-full border-border/60 bg-card/80 px-3 py-1 text-[11px] font-semibold tracking-[0.24em] text-muted-foreground uppercase"
                        >
                            Error {status}
                        </Badge>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1.45fr)_minmax(18rem,0.95fr)]">
                    <SurfaceCard
                        variant="hero"
                        padding="lg"
                        className="relative overflow-hidden border-border/60 bg-card/82 shadow-[0_20px_60px_rgba(15,23,42,0.12)] backdrop-blur-md"
                    >
                        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_14%_20%,hsl(var(--primary)/0.14),transparent_32%)]" />
                        <div className="pointer-events-none absolute top-5 right-5 text-[5rem] font-semibold tracking-tight text-foreground/[0.04] sm:text-[7rem]">
                            {status}
                        </div>

                        <div className="relative space-y-8">
                            <div className="flex items-start gap-4">
                                <div
                                    className={cn(
                                        'flex size-14 shrink-0 items-center justify-center rounded-2xl ring-1 ring-inset',
                                        toneClassNames[copy.tone],
                                    )}
                                >
                                    <Icon className="size-6" />
                                </div>

                                <div className="min-w-0 space-y-2">
                                    <Badge
                                        variant="outline"
                                        className="rounded-full border-border/60 bg-background/70 px-2.5 py-0.5 text-[10px] font-semibold tracking-[0.24em] text-muted-foreground uppercase"
                                    >
                                        {copy.eyebrow}
                                    </Badge>
                                    <p className="text-sm font-medium text-muted-foreground">
                                        {branding?.appTitle ?? brandLabel}
                                    </p>
                                </div>
                            </div>

                            <div className="max-w-3xl space-y-3">
                                <h1 className="text-balance text-3xl font-semibold tracking-tight sm:text-4xl lg:text-[2.75rem]">
                                    {copy.title}
                                </h1>
                                <p className="max-w-2xl text-sm leading-7 text-muted-foreground sm:text-base">
                                    {copy.description}
                                </p>
                                {copy.hint ? (
                                    <p className="max-w-xl text-sm leading-6 text-muted-foreground">
                                        {copy.hint}
                                    </p>
                                ) : null}
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <Button asChild size="lg" className="shadow-sm">
                                    <Link href={route} prefetch>
                                        <Home className="size-4" />
                                        {label}
                                    </Link>
                                </Button>

                                {canReload ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="lg"
                                        onClick={handleReload}
                                    >
                                        <RefreshCw className="size-4" />
                                        Try again
                                    </Button>
                                ) : null}

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="lg"
                                    onClick={handleBack}
                                    className="text-muted-foreground hover:text-foreground"
                                >
                                    <ArrowLeft className="size-4" />
                                    Go back
                                </Button>
                            </div>

                            <div className="flex flex-wrap gap-2 pt-1">
                                <Badge
                                    variant="outline"
                                    className="rounded-full border-border/60 bg-background/70 px-2.5 py-1 text-[11px] text-muted-foreground"
                                >
                                    Status {status}
                                </Badge>
                                <Badge
                                    variant="outline"
                                    className="rounded-full border-border/60 bg-background/70 px-2.5 py-1 text-[11px] text-muted-foreground"
                                >
                                    {hasSupportDetails
                                        ? 'Support details available'
                                        : 'Support via administrator'}
                                </Badge>
                                <Badge
                                    variant="outline"
                                    className="rounded-full border-border/60 bg-background/70 px-2.5 py-1 text-[11px] text-muted-foreground"
                                >
                                    {brandLabel}
                                </Badge>
                            </div>
                        </div>
                    </SurfaceCard>

                    <div className="grid gap-4">
                        <SurfaceCard
                            variant="default"
                            padding="md"
                            className="border-border/60 bg-card/78 shadow-[0_16px_40px_rgba(15,23,42,0.1)] backdrop-blur-md"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="space-y-1">
                                    <p className="text-sm font-semibold">
                                        What you can do next
                                    </p>
                                    <p className="text-xs leading-5 text-muted-foreground">
                                        Recommended steps for a fast recovery.
                                    </p>
                                </div>
                                <Badge variant="secondary" className="text-xs">
                                    {copy.eyebrow}
                                </Badge>
                            </div>

                            <Separator className="my-4 bg-border/60" />

                            <ol className="space-y-3">
                                {copy.recommendations.map(
                                    (recommendation, index) => (
                                        <li
                                            key={recommendation}
                                            className="flex items-start gap-3"
                                        >
                                            <span className="flex size-6 shrink-0 items-center justify-center rounded-full border border-border/60 bg-background/80 text-xs font-semibold text-muted-foreground">
                                                {index + 1}
                                            </span>
                                            <p className="text-sm leading-6 text-muted-foreground">
                                                {recommendation}
                                            </p>
                                        </li>
                                    ),
                                )}
                            </ol>
                        </SurfaceCard>

                        <SurfaceCard
                            variant="muted"
                            padding="md"
                            className="border-border/60 bg-card/74 shadow-[0_16px_36px_rgba(15,23,42,0.08)] backdrop-blur-md"
                        >
                            <Alert className="border-border/60 bg-background/75">
                                <LifeBuoy className="text-primary" />
                                <AlertTitle>Support and recovery</AlertTitle>
                                <AlertDescription className="gap-3">
                                    <p>
                                        If this page keeps appearing, support
                                        can help you confirm whether the issue
                                        is account-specific or platform-wide.
                                    </p>
                                    {hasSupportDetails ? (
                                        <SupportContact
                                            className="text-sm"
                                            label="Support"
                                        />
                                    ) : (
                                        <p className="text-sm leading-6 text-muted-foreground">
                                            Organization-specific support
                                            details are unavailable right now.
                                            Please try again later or contact
                                            your administrator directly.
                                        </p>
                                    )}
                                </AlertDescription>
                            </Alert>
                        </SurfaceCard>
                    </div>
                </div>
            </div>
        </div>
    );
}
