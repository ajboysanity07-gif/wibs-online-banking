import { Head, Link } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Clock } from 'lucide-react';
import { PageShell } from '@/components/page-shell';
import { SurfaceCard } from '@/components/surface-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDateTime } from '@/lib/formatters';
import {
    dashboard as clientDashboard,
    loanPayments,
    loans as clientLoans,
} from '@/routes/client';
import type { BreadcrumbItem } from '@/types';
import type { OnlinePaymentStatus } from '@/types/admin';

type OnlinePayment = {
    id: number;
    loan_number: string | null;
    acctno: string | null;
    amount: number;
    currency: string;
    provider: string;
    reference_number: string | null;
    status: OnlinePaymentStatus;
    paid_at: string | null;
    created_at: string | null;
};

type Props = {
    payment: OnlinePayment;
    state: 'success' | 'failed';
    message: string;
};

const statusLabels: Record<OnlinePaymentStatus, string> = {
    pending: 'Pending',
    paid: 'Paid',
    failed: 'Failed',
    expired: 'Expired',
    cancelled: 'Cancelled',
    posted: 'Posted',
};

export default function OnlinePaymentStatusPage({
    payment,
    state,
    message,
}: Props) {
    const loanPaymentsHref = payment.loan_number
        ? loanPayments(payment.loan_number).url
        : clientLoans().url;
    const Icon =
        payment.status === 'paid'
            ? CheckCircle2
            : state === 'failed'
              ? AlertCircle
              : Clock;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Member profile', href: clientDashboard().url },
        { title: 'Loans', href: clientLoans().url },
        { title: 'Payment status', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment Status" />
            <PageShell>
                <SurfaceCard variant="hero" padding="lg" className="space-y-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-start gap-3">
                            <div className="rounded-full border border-border/40 bg-muted/40 p-3">
                                <Icon className="h-6 w-6 text-primary" />
                            </div>
                            <div className="space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h1 className="text-2xl font-semibold tracking-tight">
                                        {message}
                                    </h1>
                                    <Badge variant="outline">
                                        {statusLabels[payment.status]}
                                    </Badge>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Loan {payment.loan_number ?? '--'} | Account{' '}
                                    {payment.acctno ?? '--'}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-3 md:grid-cols-3">
                        <div className="rounded-xl border border-border/30 bg-muted/30 p-4">
                            <p className="text-xs font-medium text-muted-foreground">
                                Amount
                            </p>
                            <p className="mt-2 text-xl font-semibold tabular-nums">
                                {formatCurrency(payment.amount)}
                            </p>
                        </div>
                        <div className="rounded-xl border border-border/30 bg-muted/30 p-4">
                            <p className="text-xs font-medium text-muted-foreground">
                                Reference
                            </p>
                            <p className="mt-2 text-sm font-semibold">
                                {payment.reference_number ?? '--'}
                            </p>
                        </div>
                        <div className="rounded-xl border border-border/30 bg-muted/30 p-4">
                            <p className="text-xs font-medium text-muted-foreground">
                                Submitted
                            </p>
                            <p className="mt-2 text-sm font-semibold">
                                {formatDateTime(payment.created_at)}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button asChild>
                            <Link href={loanPaymentsHref}>
                                Back to loan payments
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={clientLoans().url}>View loans</Link>
                        </Button>
                    </div>
                </SurfaceCard>
            </PageShell>
        </AppLayout>
    );
}
