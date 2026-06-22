import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import { PageHero } from '@/components/page-hero';
import { PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes/admin';
import { exportMethod as exportReport } from '@/routes/admin/reports';
import type { BreadcrumbItem } from '@/types';
import type { DateRange, ReportingMetrics, ReportType } from '@/types/reports';

type Props = {
    reportingMetrics: ReportingMetrics;
    canExport: boolean;
    dateRange: DateRange;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin Dashboard', href: dashboard().url },
    { title: 'Reports', href: '#' },
];

const REPORT_DEFINITIONS: { type: ReportType; label: string; description: string }[] = [
    {
        type: 'monthly-applications',
        label: 'Monthly Applications',
        description: 'All loan applications with status, amounts, and decision dates.',
    },
    {
        type: 'rejection-reasons',
        label: 'Rejection Reasons',
        description: 'Rejected and declined applications with stated reasons.',
    },
    {
        type: 'turnaround-time',
        label: 'Turnaround Time',
        description: 'Processing days from submission to decision per application.',
    },
    {
        type: 'processor-workload',
        label: 'Processor Workload',
        description: 'Per-processor totals: assigned, approved, rejected, and average processing days.',
    },
];

export default function Reports({ reportingMetrics, canExport, dateRange }: Props) {
    const [from, setFrom] = useState(dateRange.from ?? '');
    const [to, setTo] = useState(dateRange.to ?? '');

    function applyDateFilter() {
        router.get(
            window.location.pathname,
            { ...(from ? { from } : {}), ...(to ? { to } : {}) },
            { preserveScroll: true, replace: true },
        );
    }

    function downloadReport(type: ReportType) {
        const params = new URLSearchParams();
        if (from) params.set('from', from);
        if (to) params.set('to', to);
        const qs = params.toString() ? `?${params.toString()}` : '';
        window.location.href = exportReport(type).url + qs;
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />
            <PageShell size="wide" className="gap-8">
                <PageHero
                    kicker="Admin"
                    title="Reports"
                    description="Export loan workflow data for analysis."
                />

                <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                    <CardHeader>
                        <CardTitle>Date range filter</CardTitle>
                        <CardDescription>
                            Filter all metrics and exports to a specific period.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap items-end gap-4">
                            <div className="space-y-1">
                                <Label htmlFor="from">From</Label>
                                <Input
                                    id="from"
                                    type="date"
                                    value={from}
                                    onChange={(e) => setFrom(e.target.value)}
                                    className="w-44"
                                />
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="to">To</Label>
                                <Input
                                    id="to"
                                    type="date"
                                    value={to}
                                    onChange={(e) => setTo(e.target.value)}
                                    className="w-44"
                                />
                            </div>
                            <Button onClick={applyDateFilter} size="sm">
                                Apply
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardDescription>Pending</CardDescription>
                            <CardTitle className="text-3xl">{reportingMetrics.pending_count}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardDescription>Approved</CardDescription>
                            <CardTitle className="text-3xl">{reportingMetrics.approved_count}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardDescription>Approval rate</CardDescription>
                            <CardTitle className="text-3xl">{reportingMetrics.approval_rate}%</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card className="rounded-2xl border-border/40 bg-card/70 shadow-sm">
                        <CardHeader>
                            <CardDescription>Avg processing days</CardDescription>
                            <CardTitle className="text-3xl">
                                {reportingMetrics.average_processing_days ?? '--'}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    {REPORT_DEFINITIONS.map((def) => (
                        <Card
                            key={def.type}
                            className="rounded-2xl border-border/40 bg-card/70 shadow-sm"
                        >
                            <CardHeader>
                                <CardTitle>{def.label}</CardTitle>
                                <CardDescription>{def.description}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={!canExport}
                                    onClick={() => downloadReport(def.type)}
                                >
                                    <Download className="mr-2 h-4 w-4" />
                                    Download Excel
                                </Button>
                                {!canExport ? (
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        You do not have permission to export reports.
                                    </p>
                                ) : null}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </PageShell>
        </AppLayout>
    );
}
