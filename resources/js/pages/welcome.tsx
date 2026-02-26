import { Head, Link, usePage } from '@inertiajs/react';
import {
    BellRing,
    Clock3,
    Headset,
    LineChart,
    ShieldCheck,
    Sparkles,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

type PageProps = {
    auth?: {
        user?: {
            username?: string | null;
        } | null;
    } | null;
    canRegister?: boolean;
};

const features = [
    {
        title: 'View loan applications and status',
        description:
            'See current loan requests and track approvals from submission to release.',
        icon: LineChart,
    },
    {
        title: 'Track payments and balances',
        description:
            'Review dues, balances, and payment history synced from the system of record.',
        icon: Clock3,
    },
    {
        title: 'Request a new loan',
        description:
            'Submit requests online so your cooperative can review them faster.',
        icon: Sparkles,
    },
    {
        title: 'Upload required documents',
        description:
            'Share supporting documents securely when your request requires them.',
        icon: Headset,
    },
    {
        title: 'Notifications and status updates',
        description:
            'Get updates on approvals, requirements, and changes to your requests.',
        icon: BellRing,
    },
    {
        title: 'Secure member verification',
        description:
            'Only verified members can create portal access to protect your data.',
        icon: ShieldCheck,
    },
];

const steps = [
    {
        title: 'Verify membership',
        description:
            'Confirm your account number and name so we can match you to the records.',
    },
    {
        title: 'Create portal login',
        description:
            'Set your login details to access requests, balances, and account updates.',
    },
    {
        title: 'Admin approval',
        description:
            'Your account stays pending until the cooperative reviews and activates it.',
    },
];

export default function Welcome() {
    const { auth, canRegister } = usePage<PageProps>().props;
    const isAuthenticated = Boolean(auth?.user);

    return (
        <div className="relative min-h-screen overflow-hidden bg-background text-foreground">
            <Head title="Welcome" />

            <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_15%,hsl(var(--primary)/0.14),transparent_35%),radial-gradient(circle_at_85%_10%,hsl(var(--accent)/0.18),transparent_40%)]" />
            <div className="absolute inset-x-0 top-0 h-56 bg-linear-to-b from-primary/10 via-transparent to-transparent blur-3xl opacity-80" />

            <div className="relative mx-auto flex max-w-6xl flex-col px-6 pb-16 pt-8 lg:px-10 lg:pb-24 lg:pt-12">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <img
                            src="/mrdinc-logo-mark.png"
                            alt="MRDINC Portal"
                            className="h-10 w-auto object-contain md:h-12"
                        />
                        <div>
                            <p className="text-sm font-semibold">
                                MRDINC Portal
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Member Portal
                            </p>
                        </div>
                    </div>

                    <div className="hidden items-center gap-3 sm:flex">
                        {isAuthenticated ? (
                            <Button asChild variant="outline" size="sm">
                                <Link href={dashboard()}>Go to dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="outline" size="sm">
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                {canRegister && (
                                    <Button asChild size="sm">
                                        <Link href={register()}>
                                            Create portal login
                                        </Link>
                                    </Button>
                                )}
                            </>
                        )}
                    </div>
                </header>

                <main className="mt-8 space-y-16 lg:mt-20">
                    <section className="grid gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                        <div className="space-y-6">
                            <p className="hidden text-xs font-semibold uppercase tracking-[0.3em] text-primary sm:flex">
                                MRDINC Portal
                            </p>
                            <h1 className="text-4xl font-semibold leading-tight tracking-tight sm:text-5xl">
                                Member portal built for cooperative services.
                            </h1>
                            <p className="text-lg text-muted-foreground sm:text-xl">
                                View loan history, payments, and submit
                                requests—processed in WIBS Desktop.
                            </p>

                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                {isAuthenticated ? (
                                    <Button
                                        asChild
                                        size="lg"
                                        className="shadow-sm"
                                    >
                                        <Link href={dashboard()}>
                                            Go to dashboard
                                        </Link>
                                    </Button>
                                ) : (
                                    <>
                                        <Button
                                            asChild
                                            size="lg"
                                            className="shadow-sm"
                                        >
                                            <Link href={login()}>Log in</Link>
                                        </Button>
                                        {canRegister && (
                                            <Button
                                                asChild
                                                size="lg"
                                                variant="outline"
                                                className="shadow-sm"
                                            >
                                                <Link href={register()}>
                                                    Create portal login
                                                </Link>
                                            </Button>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>

                        <div className="relative">
                            <div className="pointer-events-none absolute inset-0 rounded-3xl bg-linear-to-br from-primary/15 via-transparent to-[hsl(var(--accent)/0.18)] blur-3xl opacity-80" />
                            <div className="relative rounded-3xl border border-border bg-card/90 p-8 shadow-xl backdrop-blur-md">
                                <div className="space-y-4">
                                    <div className="rounded-2xl border border-border bg-muted/60 p-4">
                                        <p className="text-sm font-semibold">
                                            Integrated with WIBS Desktop
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            Requests, approvals, and balances
                                            stay aligned with the system of
                                            record.
                                        </p>
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {[
                                            {
                                                label: 'Member verification',
                                                detail: 'Protects access before registration.',
                                            },
                                            {
                                                label: 'Admin approval',
                                                detail: 'Pending access until reviewed.',
                                            },
                                        ].map((item) => (
                                            <div
                                                key={item.label}
                                                className="rounded-2xl border border-border bg-card/80 px-4 py-3 text-sm shadow-sm"
                                            >
                                                <p className="font-semibold">
                                                    {item.label}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {item.detail}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section className="space-y-6">
                        <div className="space-y-2">
                            <p className="text-sm font-semibold text-primary">
                                What you can do
                            </p>
                            <h2 className="text-2xl font-semibold">
                                Everything your membership needs in one place.
                            </h2>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {features.map((feature) => (
                                <div
                                    key={feature.title}
                                    className="rounded-2xl border border-border bg-card/80 p-5 shadow-sm backdrop-blur-sm"
                                >
                                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                        <feature.icon className="h-5 w-5" />
                                    </div>
                                    <div className="mt-4 space-y-2">
                                        <p className="font-semibold">
                                            {feature.title}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {feature.description}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="grid gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
                        <div className="space-y-4">
                            <p className="text-sm font-semibold text-primary">
                                How it works
                            </p>
                            <h2 className="text-2xl font-semibold">
                                Verified members only.
                            </h2>
                            <p className="text-muted-foreground">
                                We keep portal access safe by verifying members
                                and requiring cooperative approval before
                                dashboard access.
                            </p>
                        </div>
                        <div className="grid gap-4">
                            {steps.map((step, index) => (
                                <div
                                    key={step.title}
                                    className="flex gap-4 rounded-2xl border border-border bg-card/80 p-4 shadow-sm"
                                >
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                        {index + 1}
                                    </div>
                                    <div className="space-y-1">
                                        <p className="font-semibold">
                                            {step.title}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {step.description}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                </main>

                <footer className="mt-16 border-t border-border pt-8 text-sm text-muted-foreground">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <img
                                src="/mrdinc-logo-mark.png"
                                alt="MRDINC Portal"
                                className="h-9 w-auto object-contain"
                            />
                            <div>
                                <p className="text-sm font-semibold text-foreground">
                                    MRDINC Portal
                                </p>
                                <p className="text-xs">
                                    Integrated with WIBS Desktop
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-4 text-xs">
                            {['Privacy', 'Terms', 'Support'].map((label) => (
                                <a
                                    key={label}
                                    href="#"
                                    className="transition-colors hover:text-primary"
                                >
                                    {label}
                                </a>
                            ))}
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    );
}
