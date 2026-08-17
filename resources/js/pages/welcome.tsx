import { Head, Link, usePage } from '@inertiajs/react';
import {
    BellRing,
    FileStack,
    PiggyBank,
    ReceiptText,
    ShieldCheck,
    Wallet,
} from 'lucide-react';
import SupportContact from '@/components/support-contact';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useBranding } from '@/hooks/use-branding';
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
        title: 'Request a loan online',
        description:
            'Fill out one guided form — employment, banking, co-makers, and required documents in a single pass. Choose a regular term or a lump-sum payoff for eligible loan types.',
        icon: Wallet,
    },
    {
        title: 'Track every application step',
        description:
            'Follow your request from submission through review, recommendation, and approval, with the reason for any revision request shown inline.',
        icon: FileStack,
    },
    {
        title: 'Review savings and loan balances',
        description:
            'See running balances, payment schedules, and posted transactions synced from WIBS Desktop — no separate statement request needed.',
        icon: PiggyBank,
    },
    {
        title: 'Check payment history',
        description:
            'Look up dues paid, amounts due, and upcoming schedule dates for every active loan.',
        icon: ReceiptText,
    },
    {
        title: 'Get notified on changes',
        description:
            'Receive an alert the moment your request moves stages, needs a correction, or is ready for release.',
        icon: BellRing,
    },
    {
        title: 'Verified member access only',
        description:
            'Portal accounts are matched against your membership record before login access is created.',
        icon: ShieldCheck,
    },
];

const pipeline = [
    { label: 'Submitted', detail: 'Application and documents received' },
    { label: 'Under review', detail: 'Loan processor checks requirements' },
    { label: 'Recommended', detail: 'Sent to loan manager for approval' },
    { label: 'Approved', detail: 'Ready for release scheduling' },
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
        title: 'Start onboarding',
        description:
            'Complete your profile details so you can use every portal feature.',
    },
];

export default function Welcome() {
    const { auth, canRegister } = usePage<PageProps>().props;
    const branding = useBranding();
    const isAuthenticated = Boolean(auth?.user);
    const showCompanyName = !branding.logoIsWordmark;

    return (
        <div className="min-h-screen bg-background text-foreground">
            <Head title="Welcome" />

            <div className="mx-auto flex max-w-6xl flex-col px-6 pt-8 pb-16 lg:px-10 lg:pt-12 lg:pb-24">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <img
                            src={branding.logoUrl}
                            alt={branding.appTitle}
                            className="h-10 w-auto object-contain md:h-12"
                        />
                        <div>
                            {showCompanyName ? (
                                <p className="text-sm font-semibold">
                                    {branding.companyName}
                                </p>
                            ) : null}
                            <p className="text-xs text-muted-foreground">
                                {branding.portalLabel}
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
                            <Badge variant="secondary" className="w-fit">
                                {branding.appTitle}
                            </Badge>
                            <h1 className="text-4xl leading-tight font-semibold tracking-tight sm:text-5xl">
                                Your loan account, one clear request away.
                            </h1>
                            <p className="text-lg text-muted-foreground sm:text-xl">
                                Request a loan, follow it through approval, and
                                keep an eye on savings and payments — all kept
                                in sync with WIBS Desktop.
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

                        <Card className="gap-4 py-6">
                            <div className="flex items-center justify-between px-6">
                                <p className="text-sm font-semibold">
                                    Where your request stands
                                </p>
                                <span className="text-xs text-muted-foreground">
                                    Sample application
                                </span>
                            </div>
                            <div className="space-y-0 px-6">
                                {pipeline.map((stage, index) => {
                                    const isCurrent =
                                        index === pipeline.length - 2;
                                    const isDone = index < pipeline.length - 2;

                                    return (
                                        <div
                                            key={stage.label}
                                            className="flex gap-3"
                                        >
                                            <div className="flex flex-col items-center">
                                                <span
                                                    className={
                                                        isDone || isCurrent
                                                            ? 'flex h-6 w-6 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground'
                                                            : 'flex h-6 w-6 items-center justify-center rounded-full border border-border text-xs font-semibold text-muted-foreground'
                                                    }
                                                >
                                                    {index + 1}
                                                </span>
                                                {index <
                                                    pipeline.length - 1 && (
                                                    <span
                                                        className={
                                                            isDone
                                                                ? 'w-px flex-1 bg-primary'
                                                                : 'w-px flex-1 bg-border'
                                                        }
                                                    />
                                                )}
                                            </div>
                                            <div className="pb-6">
                                                <p
                                                    className={
                                                        isCurrent
                                                            ? 'text-sm font-semibold text-primary'
                                                            : 'text-sm font-semibold'
                                                    }
                                                >
                                                    {stage.label}
                                                    {isCurrent && (
                                                        <Badge className="ml-2 align-middle">
                                                            In progress
                                                        </Badge>
                                                    )}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {stage.detail}
                                                </p>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </Card>
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
                                <Card key={feature.title} className="p-5">
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
                                </Card>
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
                                before registration and onboarding.
                            </p>
                        </div>
                        <div className="grid gap-4">
                            {steps.map((step, index) => (
                                <Card
                                    key={step.title}
                                    className="flex-row gap-4 p-4"
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
                                </Card>
                            ))}
                        </div>
                    </section>
                </main>

                <footer className="mt-16 border-t border-border pt-8 text-sm text-muted-foreground">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <img
                                src={branding.logoUrl}
                                alt={branding.appTitle}
                                className="h-9 w-auto object-contain"
                            />
                            <div>
                                {showCompanyName ? (
                                    <p className="text-sm font-semibold text-foreground">
                                        {branding.companyName}
                                    </p>
                                ) : null}
                                <p className="text-xs">
                                    Integrated with WIBS Desktop
                                </p>
                            </div>
                        </div>
                        <SupportContact variant="inline" />
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
