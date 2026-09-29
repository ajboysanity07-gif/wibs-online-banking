import { Head, Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Banknote,
    BellRing,
    Check,
    ClipboardList,
    Crosshair,
    Eye,
    FilePlus2,
    FileStack,
    HeartHandshake,
    LayoutDashboard,
    ListChecks,
    LockKeyhole,
    LogIn,
    PhoneOff,
    PiggyBank,
    ReceiptText,
    ShieldCheck,
    UserPlus,
    Wallet,
} from 'lucide-react';
import { useEffect } from 'react';
import SupportContact from '@/components/support-contact';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useBranding } from '@/hooks/use-branding';
import { dashboard, login, register } from '@/routes';
import { create, index } from '@/routes/client/loan-requests';
import type { LoanTypeOption } from '@/types/loan-requests';

type PageProps = {
    auth?: {
        user?: {
            username?: string | null;
        } | null;
    } | null;
    canRegister?: boolean;
    loanTypes?: LoanTypeOption[];
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

const navLinks = [
    { label: 'Services', href: '#services' },
    { label: 'Loan products', href: '#loans' },
    { label: 'How it works', href: '#how' },
    { label: 'About us', href: '#about' },
    { label: 'FAQ', href: '#faq' },
];

const values = [
    { title: 'Transparent', detail: 'Clear balances and terms', icon: Eye },
    { title: 'Accurate', detail: 'Same records as staff', icon: Crosshair },
    {
        title: 'Supportive',
        detail: 'Help when you need it',
        icon: HeartHandshake,
    },
];

const securityTiles = [
    {
        title: 'Verified access',
        detail: 'Members are matched to records',
        icon: BadgeCheck,
    },
    {
        title: 'Protected sessions',
        detail: 'Secure login with optional two-factor',
        icon: LockKeyhole,
    },
    {
        title: 'Audit trail',
        detail: 'Every staff action is logged',
        icon: ClipboardList,
    },
    {
        title: 'No password requests',
        detail: 'We never ask by phone or email',
        icon: PhoneOff,
    },
];

type Faq = {
    group: string;
    question: string;
    answer: string;
    list?: string[];
};

const faqs: Faq[] = [
    {
        group: 'Using the portal',
        question: 'Who can use the member portal?',
        answer: 'Verified members only. We match your account number and name against your membership record before your login is created.',
    },
    {
        group: 'Using the portal',
        question: 'How do I request a loan?',
        answer: 'Log in, open Loan requests and choose Request a loan. One guided form covers your details, employment, banking, co-makers and declarations. You can save a draft and come back anytime.',
    },
    {
        group: 'Using the portal',
        question: 'Where do my balances come from?',
        answer: 'Balances, schedules and posted transactions are synced from WIBS Desktop, so you see the same records our staff see.',
    },
    {
        group: 'Using the portal',
        question: 'How will I know when my request changes?',
        answer: 'You get an alert when your request moves stages, needs a correction, or is ready for release. The reason for any revision request is shown on the request itself.',
    },
    {
        group: 'Rates, terms and fees',
        question: 'How much interest will I be charged?',
        answer: 'Your interest rate is based on the specific loan product, the amount borrowed, your repayment term, and our credit assessment. All applicable interest rates and fees will be fully disclosed to you before you sign any loan agreement.',
    },
    {
        group: 'Rates, terms and fees',
        question: 'Are there any hidden fees or upfront charges?',
        answer: 'We believe in 100% transparency. Any processing fees, service charges, or other upfront deductions will be clearly itemized in your Disclosure Statement and deducted from your loan. There are no surprise charges.',
    },
    {
        group: 'Repayments',
        question: 'What are my payment options?',
        answer: 'You may choose from the following payment methods:',
        list: [
            'Auto-Debit (automatic deduction from your designated bank account)',
            'Online Banking (transfer payments through our partner banks)',
            'Over-the-Counter Payments (make payments at accredited payment centers, GCash, or bank branches nationwide)',
        ],
    },
    {
        group: 'Repayments',
        question: 'What happens if I miss a payment or am late on my due date?',
        answer: 'If you anticipate a delay in making your payment, please contact one of our Account Officers immediately. Late payments may incur corresponding penalty fees and could negatively affect your credit standing and future borrowing limit with us.',
    },
];

const sectionLabel = 'text-xs font-bold tracking-widest text-primary uppercase';
const wrap = 'mx-auto w-full max-w-6xl px-6 lg:px-10';
// Primary and accent are both lime in dark mode, so flip to the foreground token.
const onPrimary =
    'dark:bg-primary-foreground dark:text-primary dark:hover:bg-primary-foreground/90';

export default function Welcome() {
    const { auth, canRegister, loanTypes = [] } = usePage<PageProps>().props;
    const branding = useBranding();

    // Smooth in-page anchor scrolling, scoped to this page so Inertia
    // navigations elsewhere still jump; skipped for reduced-motion users.
    useEffect(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const root = document.documentElement;
        root.style.scrollBehavior = 'smooth';

        return () => {
            root.style.scrollBehavior = '';
        };
    }, []);

    const isAuthenticated = Boolean(auth?.user);
    const showCompanyName = !branding.logoIsWordmark;
    const hasLoanTypes = loanTypes.length > 0;
    const hasSupport = Boolean(
        branding.supportEmail ||
        branding.supportPhone ||
        branding.supportContactName,
    );

    // Guests go to login and are sent back to the member page afterwards.
    const memberHref = (url: string) =>
        isAuthenticated ? url : login({ query: { redirect: url } });
    const requestHref = (typecode?: string) =>
        memberHref(create.url(typecode ? { query: { typecode } } : undefined));
    const trackHref = memberHref(index.url());
    const contactHref = branding.supportEmail
        ? `mailto:${branding.supportEmail}`
        : '#contact';

    const quickLinks = [
        {
            title: 'Log in',
            detail: 'Access your account',
            icon: LogIn,
            href: login(),
            show: !isAuthenticated,
        },
        {
            title: 'Create login',
            detail: 'For verified members',
            icon: UserPlus,
            href: register(),
            show: !isAuthenticated && Boolean(canRegister),
        },
        {
            title: 'Go to dashboard',
            detail: 'Your account overview',
            icon: LayoutDashboard,
            href: dashboard(),
            show: isAuthenticated,
        },
        {
            title: 'Request a loan',
            detail: 'Guided online form',
            icon: FilePlus2,
            href: requestHref(),
            show: true,
        },
        {
            title: 'Track a request',
            detail: 'See every stage',
            icon: ListChecks,
            href: trackHref,
            show: true,
        },
    ].filter((link) => link.show);

    const footerColumns = [
        {
            heading: 'Portal',
            links: [
                ...(isAuthenticated
                    ? [{ label: 'Go to dashboard', href: dashboard() }]
                    : [{ label: 'Log in', href: login() }]),
                ...(!isAuthenticated && canRegister
                    ? [{ label: 'Create portal login', href: register() }]
                    : []),
                { label: 'Request a loan', href: requestHref() },
                { label: 'Track a request', href: trackHref },
            ],
        },
        {
            heading: 'Company',
            links: [
                { label: 'About us', href: '#about' },
                ...(hasLoanTypes
                    ? [{ label: 'Loan products', href: '#loans' }]
                    : []),
                { label: 'FAQ', href: '#faq' },
                { label: 'Contact', href: '#contact' },
            ],
        },
    ];

    return (
        <div className="min-h-screen bg-background text-foreground">
            <Head title="Welcome" />

            <div className="bg-sidebar text-sidebar-foreground">
                <div
                    className={`${wrap} flex flex-wrap justify-between gap-x-6 gap-y-1 py-2 text-xs`}
                >
                    <span>Member portal · Synced with WIBS Desktop</span>
                    {branding.supportEmail ? (
                        <a
                            href={`mailto:${branding.supportEmail}`}
                            className="hover:underline"
                        >
                            Support: {branding.supportEmail}
                        </a>
                    ) : null}
                </div>
            </div>

            <header className="sticky top-0 z-20 border-b border-border bg-card shadow-card">
                <div
                    className={`${wrap} flex flex-wrap items-center gap-x-6 gap-y-3 py-3`}
                >
                    <div className="flex flex-none items-center gap-3">
                        <img
                            src={branding.logoUrl}
                            alt={branding.appTitle}
                            className="h-10 w-auto object-contain"
                        />
                        <div className="leading-tight">
                            {showCompanyName ? (
                                <p className="text-sm font-bold whitespace-nowrap">
                                    {branding.companyName}
                                </p>
                            ) : null}
                            <p className="text-xs whitespace-nowrap text-muted-foreground">
                                {branding.portalLabel}
                            </p>
                        </div>
                    </div>

                    <nav className="hidden flex-1 flex-wrap gap-x-5 gap-y-1 text-sm font-semibold lg:flex">
                        {navLinks
                            .filter(
                                (link) =>
                                    link.href !== '#loans' || hasLoanTypes,
                            )
                            .map((link) => (
                                <a
                                    key={link.href}
                                    href={link.href}
                                    className="whitespace-nowrap transition-colors hover:text-primary"
                                >
                                    {link.label}
                                </a>
                            ))}
                    </nav>

                    <div className="ml-auto flex flex-wrap items-center gap-2">
                        {isAuthenticated ? (
                            <Button asChild size="sm">
                                <Link
                                    href={dashboard()}
                                    className="whitespace-nowrap"
                                >
                                    Go to dashboard
                                </Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="outline" size="sm">
                                    <Link
                                        href={login()}
                                        className="whitespace-nowrap"
                                    >
                                        Log in
                                    </Link>
                                </Button>
                                {canRegister && (
                                    <Button asChild size="sm">
                                        <Link
                                            href={register()}
                                            className="whitespace-nowrap"
                                        >
                                            Create portal login
                                        </Link>
                                    </Button>
                                )}
                            </>
                        )}
                    </div>
                </div>
            </header>

            <main>
                <section
                    id="home"
                    className="bg-primary pb-20 text-primary-foreground"
                >
                    <div
                        className={`${wrap} grid gap-12 pt-14 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:pt-20`}
                    >
                        <div className="space-y-6">
                            <span
                                className={`inline-block rounded-md bg-accent px-3 py-1 text-xs font-bold tracking-widest text-accent-foreground ${onPrimary}`}
                            >
                                MEMBER PORTAL
                            </span>
                            <h1 className="text-4xl leading-tight font-bold tracking-tight sm:text-5xl">
                                Your loan account, one clear request away.
                            </h1>
                            <p className="max-w-xl text-lg text-primary-foreground/90">
                                Request a loan, follow it through approval, and
                                keep an eye on savings and payments — all kept
                                in sync with WIBS Desktop.
                            </p>

                            <div className="flex flex-wrap gap-3">
                                {isAuthenticated ? (
                                    <Button
                                        asChild
                                        size="lg"
                                        variant="accent"
                                        className={onPrimary}
                                    >
                                        <Link
                                            href={dashboard()}
                                            className="whitespace-nowrap"
                                        >
                                            Go to dashboard
                                        </Link>
                                    </Button>
                                ) : (
                                    <>
                                        <Button
                                            asChild
                                            size="lg"
                                            variant="accent"
                                            className={onPrimary}
                                        >
                                            <Link
                                                href={login()}
                                                className="whitespace-nowrap"
                                            >
                                                Log in
                                            </Link>
                                        </Button>
                                        {canRegister && (
                                            <Button
                                                asChild
                                                size="lg"
                                                variant="outline"
                                                className="border-primary-foreground bg-transparent text-primary-foreground shadow-none hover:bg-primary-foreground/10 hover:text-primary-foreground"
                                            >
                                                <Link
                                                    href={register()}
                                                    className="whitespace-nowrap"
                                                >
                                                    Create portal login
                                                </Link>
                                            </Button>
                                        )}
                                    </>
                                )}
                            </div>

                            <ul className="flex flex-wrap gap-x-5 gap-y-2 text-sm font-semibold">
                                {[
                                    'Verified members only',
                                    'Secure session',
                                    'Live balances',
                                ].map((tick) => (
                                    <li
                                        key={tick}
                                        className="flex items-center gap-1.5"
                                    >
                                        <Check className="h-4 w-4" />
                                        {tick}
                                    </li>
                                ))}
                            </ul>
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
                                {pipeline.map((stage, stageIndex) => {
                                    const isCurrent =
                                        stageIndex === pipeline.length - 2;
                                    const isDone =
                                        stageIndex < pipeline.length - 2;

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
                                                    {stageIndex + 1}
                                                </span>
                                                {stageIndex <
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
                    </div>
                </section>

                <section className={`${wrap} relative -mt-8`}>
                    <Card className="grid gap-0 divide-y divide-border p-0 sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                        {quickLinks.map((link) => (
                            <Link
                                key={link.title}
                                href={link.href}
                                className="flex items-center gap-4 px-6 py-5 transition-colors hover:bg-muted"
                            >
                                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-border bg-secondary text-secondary-foreground">
                                    <link.icon className="h-5 w-5" />
                                </span>
                                <span className="flex flex-col">
                                    <span className="font-bold">
                                        {link.title}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {link.detail}
                                    </span>
                                </span>
                            </Link>
                        ))}
                    </Card>
                </section>

                <section
                    id="services"
                    className={`${wrap} scroll-mt-24 space-y-6 pt-20`}
                >
                    <div className="space-y-2">
                        <p className={sectionLabel}>Services</p>
                        <h2 className="text-3xl font-bold tracking-tight">
                            Everything your membership needs in one place.
                        </h2>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {features.map((feature) => (
                            <Card key={feature.title} className="p-6">
                                <div className="flex h-11 w-11 items-center justify-center rounded-xl border border-border bg-secondary text-secondary-foreground">
                                    <feature.icon className="h-5 w-5" />
                                </div>
                                <div className="mt-2 space-y-2">
                                    <p className="text-lg font-bold">
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

                {hasLoanTypes && (
                    <section
                        id="loans"
                        className={`${wrap} scroll-mt-24 space-y-6 pt-20`}
                    >
                        <div className="space-y-2">
                            <p className={sectionLabel}>Loan products</p>
                            <h2 className="text-3xl font-bold tracking-tight">
                                Choose the loan that fits.
                            </h2>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {loanTypes.map((type, typeIndex) => {
                                const highlighted = typeIndex === 0;

                                return (
                                    <Card
                                        key={type.typecode}
                                        className={
                                            highlighted
                                                ? 'justify-between border-primary bg-primary p-6 text-primary-foreground'
                                                : 'justify-between p-6'
                                        }
                                    >
                                        <div className="space-y-3">
                                            <div
                                                className={
                                                    highlighted
                                                        ? 'flex h-11 w-11 items-center justify-center rounded-xl bg-primary-foreground/15 text-primary-foreground'
                                                        : 'flex h-11 w-11 items-center justify-center rounded-xl border border-border bg-secondary text-secondary-foreground'
                                                }
                                            >
                                                <Banknote className="h-5 w-5" />
                                            </div>
                                            <p className="text-xl font-bold">
                                                {type.label.trim()}
                                            </p>
                                        </div>
                                        <div>
                                            <Button
                                                asChild
                                                size="sm"
                                                variant={
                                                    highlighted
                                                        ? 'accent'
                                                        : 'default'
                                                }
                                                className={
                                                    highlighted
                                                        ? onPrimary
                                                        : undefined
                                                }
                                            >
                                                <Link
                                                    href={requestHref(
                                                        type.typecode,
                                                    )}
                                                    className="whitespace-nowrap"
                                                >
                                                    Request this loan
                                                </Link>
                                            </Button>
                                        </div>
                                    </Card>
                                );
                            })}
                        </div>
                    </section>
                )}

                <section
                    id="how"
                    className={`${wrap} grid scroll-mt-24 gap-8 pt-20 lg:grid-cols-[0.9fr_1.1fr] lg:items-start`}
                >
                    <div className="space-y-3">
                        <p className={sectionLabel}>How it works</p>
                        <h2 className="text-3xl font-bold tracking-tight">
                            Verified members only.
                        </h2>
                        <p className="max-w-md text-muted-foreground">
                            We keep portal access safe by verifying members
                            before registration and onboarding.
                        </p>
                    </div>
                    <div className="grid gap-4">
                        {steps.map((step, stepIndex) => (
                            <Card
                                key={step.title}
                                className="flex-row gap-4 p-5"
                            >
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground">
                                    {stepIndex + 1}
                                </div>
                                <div className="space-y-1">
                                    <p className="font-bold">{step.title}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {step.description}
                                    </p>
                                </div>
                            </Card>
                        ))}
                    </div>
                </section>

                <section
                    id="about"
                    className="mt-20 scroll-mt-24 border-y border-border bg-card"
                >
                    <div
                        className={`${wrap} grid gap-12 py-16 lg:grid-cols-2 lg:items-center`}
                    >
                        <div className="space-y-4">
                            <p className={sectionLabel}>About us</p>
                            <h2 className="text-3xl font-bold tracking-tight">
                                Built around our members.
                            </h2>
                            <p className="text-muted-foreground">
                                {branding.companyName} Portal is the
                                member-facing side of our loan system.
                                Everything you see here comes straight from the
                                same records our staff use, so your balances,
                                schedules and request status are always current.
                            </p>
                            <div className="grid gap-3 sm:grid-cols-3">
                                {values.map((value) => (
                                    <div
                                        key={value.title}
                                        className="rounded-lg border border-border bg-muted p-4"
                                    >
                                        <value.icon className="mb-2 h-5 w-5 text-primary" />
                                        <p className="font-bold">
                                            {value.title}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {value.detail}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                        <div className="flex min-h-64 items-center justify-center rounded-xl border border-border bg-muted p-8">
                            <img
                                src={branding.logoFullUrl}
                                alt={branding.companyName}
                                className="max-h-32 w-auto object-contain"
                            />
                        </div>
                    </div>
                </section>

                <section className={`${wrap} pt-20`}>
                    <div className="grid gap-8 rounded-xl bg-sidebar p-8 text-sidebar-foreground shadow-card lg:grid-cols-[0.8fr_1.2fr] lg:items-center lg:p-10">
                        <div className="space-y-2">
                            <p className="text-xs font-bold tracking-widest text-sidebar-primary uppercase">
                                Security
                            </p>
                            <h2 className="text-3xl font-bold tracking-tight">
                                Your account is protected.
                            </h2>
                            <p className="text-sm text-sidebar-foreground/85">
                                {branding.companyName} will never ask for your
                                password or one-time code by phone, text or
                                email.
                            </p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {securityTiles.map((tile) => (
                                <div
                                    key={tile.title}
                                    className="rounded-lg border border-sidebar-border bg-sidebar-foreground/10 p-4"
                                >
                                    <tile.icon className="mb-2 h-5 w-5 text-sidebar-primary" />
                                    <p className="font-bold">{tile.title}</p>
                                    <p className="text-sm text-sidebar-foreground/85">
                                        {tile.detail}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section
                    id="faq"
                    className="mx-auto w-full max-w-3xl scroll-mt-24 px-6 pt-20 lg:px-10"
                >
                    <div className="mb-6 space-y-2 text-center">
                        <p className={sectionLabel}>FAQ</p>
                        <h2 className="text-3xl font-bold tracking-tight">
                            Frequently asked questions
                        </h2>
                    </div>

                    <Accordion
                        type="single"
                        collapsible
                        defaultValue="faq-0"
                        className="space-y-3"
                    >
                        {faqs.map((faq, faqIndex) => (
                            <div key={faq.question} className="space-y-3">
                                {faqIndex === 0 ||
                                faqs[faqIndex - 1].group !== faq.group ? (
                                    <p className="pt-3 text-xs font-bold tracking-widest text-primary uppercase">
                                        {faq.group}
                                    </p>
                                ) : null}
                                <AccordionItem
                                    value={`faq-${faqIndex}`}
                                    className="rounded-xl border border-border bg-card px-5 shadow-card last:border-b"
                                >
                                    <AccordionTrigger className="text-base font-bold hover:no-underline">
                                        {faq.question}
                                    </AccordionTrigger>
                                    <AccordionContent className="space-y-2 text-sm text-muted-foreground">
                                        <p>{faq.answer}</p>
                                        {faq.list ? (
                                            <ul className="list-disc space-y-1 pl-5">
                                                {faq.list.map((item) => (
                                                    <li key={item}>{item}</li>
                                                ))}
                                            </ul>
                                        ) : null}
                                    </AccordionContent>
                                </AccordionItem>
                            </div>
                        ))}
                    </Accordion>

                    <p className="mt-6 rounded-lg border border-border bg-secondary px-4 py-3 text-center text-sm font-medium text-secondary-foreground">
                        For more information, please call{' '}
                        <b>{branding.supportPhone ?? '[phone number]'}</b> or
                        visit our Facebook page <b>[page link]</b>. Thank you.
                    </p>
                </section>

                <section id="contact" className={`${wrap} scroll-mt-24 py-20`}>
                    <div className="flex flex-wrap items-center justify-between gap-6 rounded-xl bg-accent p-8 text-accent-foreground shadow-card lg:p-10">
                        <div className="space-y-2">
                            <h2 className="text-3xl font-bold tracking-tight">
                                Ready to get started?
                            </h2>
                            <p className="font-medium">
                                Create your portal login in a few minutes, or
                                contact us if you need help.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-3">
                            {!isAuthenticated && canRegister && (
                                <Button
                                    asChild
                                    size="lg"
                                    className="dark:bg-accent-foreground dark:text-accent dark:hover:bg-accent-foreground/90"
                                >
                                    <Link
                                        href={register()}
                                        className="whitespace-nowrap"
                                    >
                                        Create portal login
                                    </Link>
                                </Button>
                            )}
                            <Button
                                asChild
                                size="lg"
                                variant="outline"
                                className="border-accent-foreground bg-transparent text-accent-foreground shadow-none hover:bg-accent-foreground/10 hover:text-accent-foreground"
                            >
                                <a
                                    href={contactHref}
                                    className="whitespace-nowrap"
                                >
                                    Contact support
                                </a>
                            </Button>
                        </div>
                    </div>
                </section>
            </main>

            <footer className="bg-sidebar text-sidebar-foreground">
                <div
                    className={`${wrap} grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr]`}
                >
                    <div className="space-y-3">
                        <div className="flex items-center gap-3">
                            <img
                                src={branding.logoUrl}
                                alt={branding.appTitle}
                                className="box-content h-9 w-auto rounded-md bg-sidebar-foreground object-contain p-1"
                            />
                            {showCompanyName ? (
                                <p className="font-bold">
                                    {branding.companyName}
                                </p>
                            ) : null}
                        </div>
                        <p className="max-w-xs text-sm text-sidebar-foreground/85">
                            Integrated with WIBS Desktop. Your records, always
                            current.
                        </p>
                    </div>

                    {footerColumns.map((column) => (
                        <div key={column.heading}>
                            <p className="mb-3 text-xs font-bold tracking-widest text-sidebar-primary uppercase">
                                {column.heading}
                            </p>
                            <ul className="space-y-2 text-sm">
                                {column.links.map((link) => (
                                    <li key={link.label}>
                                        {typeof link.href === 'string' &&
                                        link.href.startsWith('#') ? (
                                            <a
                                                href={link.href}
                                                className="hover:underline"
                                            >
                                                {link.label}
                                            </a>
                                        ) : (
                                            <Link
                                                href={link.href}
                                                className="hover:underline"
                                            >
                                                {link.label}
                                            </Link>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}

                    {hasSupport && (
                        <div>
                            <p className="mb-3 text-xs font-bold tracking-widest text-sidebar-primary uppercase">
                                Support
                            </p>
                            <SupportContact
                                label="Contact"
                                className="text-sm [&_span]:text-sidebar-foreground"
                            />
                        </div>
                    )}
                </div>
                <div className="border-t border-sidebar-border">
                    <div
                        className={`${wrap} py-4 text-xs text-sidebar-foreground/85`}
                    >
                        © {new Date().getFullYear()} {branding.companyName}. All
                        rights reserved.
                    </div>
                </div>
            </footer>
        </div>
    );
}
