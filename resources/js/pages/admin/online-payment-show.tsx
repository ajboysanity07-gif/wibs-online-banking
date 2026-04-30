import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CreditCard } from 'lucide-react';
import { PageHero } from '@/components/page-hero';
import { PageShell } from '@/components/page-shell';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDateTime } from '@/lib/formatters';
import { index as onlinePaymentsIndex } from '@/routes/admin/online-payments';
import type { BreadcrumbItem } from '@/types';
import type { OnlinePayment, OnlinePaymentStatus } from '@/types/admin';

type Props = {
    payment: OnlinePayment;
};

const statusLabels: Record<OnlinePaymentStatus, string> = {
    pending: 'Pending',
    paid: 'Paid',
    failed: 'Failed',
    expired: 'Expired',
    cancelled: 'Cancelled',
    posted: 'Posted',
};

const statusVariant = (
    status: OnlinePaymentStatus,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'paid' || status === 'posted') {
        return 'default';
    }

    if (status === 'failed' || status === 'expired' || status === 'cancelled') {
        return 'destructive';
    }

    return 'outline';
};

export default function AdminOnlinePaymentShowPage({ payment }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Online payments', href: onlinePaymentsIndex().url },
        { title: `Payment ${payment.id}`, href: '#' },
    ];
    const rawPayload =
        payment.raw_payload === undefined || payment.raw_payload === null
            ? null
            : JSON.stringify(payment.raw_payload, null, 2);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Online Payment ${payment.id}`} />
            <PageShell size="wide">
                <PageHero
                    kicker="Payment review"
                    title={`Online payment ${payment.id}`}
                    description="Review PayMongo payment details before any manual ledger posting."
                    badges={
                        <>
                            <Badge variant={statusVariant(payment.status)}>
                                {statusLabels[payment.status]}
                            </Badge>
                            {payment.status === 'paid' &&
                            !payment.posted_at ? (
                                <Badge variant="outline">
                                    Ready for admin review
                                </Badge>
                            ) : null}
                        </>
                    }
                    rightSlot={
                        <Button asChild variant="outline" size="sm">
                            <Link href={onlinePaymentsIndex().url}>
                                <ArrowLeft />
                                Back to list
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 md:grid-cols-3">
                    <SurfaceCard variant="default" padding="md">
                        <p className="text-xs font-medium text-muted-foreground">
                            Amount
                        </p>
                        <p className="mt-2 text-2xl font-semibold tabular-nums">
                            {formatCurrency(payment.amount)}
                        </p>
                    </SurfaceCard>
                    <SurfaceCard variant="default" padding="md">
                        <p className="text-xs font-medium text-muted-foreground">
                            Loan number
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {payment.loan_number ?? '--'}
                        </p>
                    </SurfaceCard>
                    <SurfaceCard variant="default" padding="md">
                        <p className="text-xs font-medium text-muted-foreground">
                            Account number
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {payment.acctno ?? '--'}
                        </p>
                    </SurfaceCard>
                </div>

                <SurfaceCard variant="default" padding="md" className="space-y-5">
                    <SectionHeader
                        title="PayMongo details"
                        description="Provider identifiers and confirmation timestamps."
                        actions={<CreditCard className="h-5 w-5 text-muted-foreground" />}
                    />
                    <div className="grid gap-4 md:grid-cols-2">
                        <Detail label="Member" value={payment.member_name} />
                        <Detail label="Provider" value={payment.provider} />
                        <Detail
                            label="Checkout ID"
                            value={payment.provider_checkout_id}
                        />
                        <Detail
                            label="Payment ID"
                            value={payment.provider_payment_id}
                        />
                        <Detail
                            label="Reference number"
                            value={payment.reference_number}
                        />
                        <Detail
                            label="Submitted"
                            value={formatDateTime(payment.created_at)}
                        />
                        <Detail
                            label="Paid at"
                            value={formatDateTime(payment.paid_at)}
                        />
                        <Detail
                            label="Posted at"
                            value={formatDateTime(payment.posted_at)}
                        />
                        <Detail label="Posted by" value={payment.posted_by} />
                    </div>
                </SurfaceCard>

                <SurfaceCard variant="default" padding="md" className="space-y-4">
                    <SectionHeader
                        title="Webhook payload"
                        description="Stored PayMongo payload for audit review."
                    />
                    {rawPayload ? (
                        <pre className="max-h-[32rem] overflow-auto rounded-xl border border-border/40 bg-muted/30 p-4 text-xs">
                            {rawPayload}
                        </pre>
                    ) : (
                        <div className="rounded-xl border border-border/30 bg-muted/30 px-4 py-6 text-sm text-muted-foreground">
                            No webhook payload has been stored yet.
                        </div>
                    )}
                </SurfaceCard>
            </PageShell>
        </AppLayout>
    );
}

function Detail({
    label,
    value,
}: {
    label: string;
    value?: string | number | null;
}) {
    return (
        <div className="rounded-xl border border-border/30 bg-muted/30 p-4">
            <p className="text-xs font-medium text-muted-foreground">
                {label}
            </p>
            <p className="mt-2 break-words text-sm font-semibold">
                {value ?? '--'}
            </p>
        </div>
    );
}
