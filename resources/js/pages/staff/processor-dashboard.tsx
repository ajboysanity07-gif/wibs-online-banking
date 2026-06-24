import { Head, Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { PageHero } from '@/components/page-hero';
import { PageShell } from '@/components/page-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { show as loanRequestShow } from '@/routes/staff/loan-requests';
import { index as processorDashboardIndex } from '@/routes/staff/processor-dashboard';
import type { BreadcrumbItem } from '@/types';
import type { ProcessorQueueData } from '@/types/reports';

type ThisMonth = {
    approved: number;
    rejected: number;
    month_label: string;
};

type Props = {
    queueData: ProcessorQueueData;
    thisMonth: ThisMonth;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Dashboard', href: processorDashboardIndex().url },
];

export default function ProcessorDashboard({ queueData, thisMonth }: Props) {
    const agingItems = queueData.items.filter((i) => i.is_aging);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Dashboard" />
            <PageShell size="wide" className="gap-8">
                <PageHero
                    kicker="Loan Processor"
                    title="My Dashboard"
                    description="Your assigned queue and this month's activity."
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardDescription>My queue</CardDescription>
                            <CardTitle className="text-3xl">{queueData.queue_count}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">Active assigned applications</p>
                        </CardContent>
                    </Card>
                    <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardDescription>Approved — {thisMonth.month_label}</CardDescription>
                            <CardTitle className="text-3xl">{thisMonth.approved}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardDescription>Rejected — {thisMonth.month_label}</CardDescription>
                            <CardTitle className="text-3xl">{thisMonth.rejected}</CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                {agingItems.length > 0 ? (
                    <Alert variant="destructive">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertTitle>
                            {agingItems.length} aging application{agingItems.length !== 1 ? 's' : ''}
                        </AlertTitle>
                        <AlertDescription>
                            {agingItems.length} application{agingItems.length !== 1 ? 's have' : ' has'} exceeded {queueData.aging_threshold_days} business days in your queue.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                    <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle>My queue</CardTitle>
                            <CardDescription>
                                All applications currently assigned to you.
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="px-0">
                        {queueData.items.length === 0 ? (
                            <div className="px-6 py-8 text-center text-sm text-muted-foreground">
                                Your queue is empty.
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="px-6">Reference</TableHead>
                                        <TableHead className="px-6">Status</TableHead>
                                        <TableHead className="px-6">Business days</TableHead>
                                        <TableHead className="px-6">Aging</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {queueData.items.map((item) => (
                                        <TableRow key={item.id} className={item.is_aging ? 'bg-destructive/5' : ''}>
                                            <TableCell className="px-6 font-medium">
                                                <Link
                                                    href={loanRequestShow(item.id).url}
                                                    className="underline-offset-4 hover:underline"
                                                >
                                                    {item.reference}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="px-6">
                                                <Badge variant="outline">{item.status}</Badge>
                                            </TableCell>
                                            <TableCell className="px-6">{item.business_days_in_queue}</TableCell>
                                            <TableCell className="px-6">
                                                {item.is_aging ? (
                                                    <Badge variant="destructive">Aging</Badge>
                                                ) : (
                                                    <Badge variant="secondary">On time</Badge>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </PageShell>
        </AppLayout>
    );
}
