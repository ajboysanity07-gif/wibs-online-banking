import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { CreditCard } from 'lucide-react';
import { useMemo, useState } from 'react';
import { PageHero } from '@/components/page-hero';
import { PageShell } from '@/components/page-shell';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { DataTablePagination } from '@/components/ui/data-table-pagination';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDateTime } from '@/lib/formatters';
import {
    index as onlinePaymentsIndex,
    show as onlinePaymentsShow,
} from '@/routes/admin/online-payments';
import type { BreadcrumbItem } from '@/types';
import type {
    OnlinePayment,
    OnlinePaymentsFilters,
    OnlinePaymentsResponse,
    OnlinePaymentStatus,
} from '@/types/admin';

type Props = {
    payments: OnlinePaymentsResponse;
    filters: OnlinePaymentsFilters;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Online payments', href: onlinePaymentsIndex().url },
];

const statusLabels: Record<OnlinePaymentStatus, string> = {
    pending: 'Pending',
    paid: 'Paid',
    failed: 'Failed',
    expired: 'Expired',
    cancelled: 'Cancelled',
    posted: 'Posted',
};

const statusOptions: Array<{ value: OnlinePaymentStatus; label: string }> = [
    { value: 'pending', label: 'Pending' },
    { value: 'paid', label: 'Paid' },
    { value: 'failed', label: 'Failed' },
    { value: 'expired', label: 'Expired' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'posted', label: 'Posted' },
];

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

export default function AdminOnlinePaymentsPage({ payments, filters }: Props) {
    const [localFilters, setLocalFilters] =
        useState<OnlinePaymentsFilters>(filters);
    const items = payments.items ?? [];
    const meta = payments.meta;
    const filterCount = [
        localFilters.status,
        localFilters.start,
        localFilters.end,
        localFilters.loan_number,
        localFilters.acctno,
        localFilters.reference_number,
    ].filter(Boolean).length;

    const visitIndex = (page = 1, nextFilters = localFilters) => {
        router.get(
            onlinePaymentsIndex().url,
            {
                page,
                perPage: nextFilters.perPage,
                status: nextFilters.status ?? undefined,
                start: nextFilters.start ?? undefined,
                end: nextFilters.end ?? undefined,
                loan_number: nextFilters.loan_number ?? undefined,
                acctno: nextFilters.acctno ?? undefined,
                reference_number: nextFilters.reference_number ?? undefined,
            },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    };

    const clearFilters = () => {
        const nextFilters: OnlinePaymentsFilters = {
            status: null,
            start: null,
            end: null,
            loan_number: null,
            acctno: null,
            reference_number: null,
            perPage: localFilters.perPage,
        };

        setLocalFilters(nextFilters);
        visitIndex(1, nextFilters);
    };

    const columns = useMemo<ColumnDef<OnlinePayment>[]>(
        () => [
            {
                accessorKey: 'created_at',
                header: 'Date',
                cell: ({ row }) => formatDateTime(row.original.created_at),
            },
            {
                accessorKey: 'member_name',
                header: 'Member / Account',
                cell: ({ row }) => (
                    <div className="flex flex-col">
                        <span className="font-medium">
                            {row.original.member_name ?? '--'}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {row.original.acctno ?? '--'}
                        </span>
                    </div>
                ),
            },
            {
                accessorKey: 'loan_number',
                header: 'Loan number',
                cell: ({ row }) => row.original.loan_number ?? '--',
            },
            {
                accessorKey: 'amount',
                header: 'Amount',
                cell: ({ row }) => formatCurrency(row.original.amount),
            },
            {
                accessorKey: 'provider',
                header: 'Provider',
                cell: ({ row }) => row.original.provider,
            },
            {
                accessorKey: 'reference_number',
                header: 'Reference number',
                cell: ({ row }) => row.original.reference_number ?? '--',
            },
            {
                accessorKey: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <Badge variant={statusVariant(row.original.status)}>
                        {statusLabels[row.original.status]}
                    </Badge>
                ),
            },
            {
                id: 'action',
                header: () => <div className="text-right">Action</div>,
                cell: ({ row }) => (
                    <div className="flex justify-end">
                        <Button asChild size="sm" variant="outline">
                            <Link href={onlinePaymentsShow(row.original.id).url}>
                                Review
                            </Link>
                        </Button>
                    </div>
                ),
            },
        ],
        [],
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Online Payments" />
            <PageShell size="wide">
                <PageHero
                    kicker="Payments"
                    title="Online payments"
                    description="Review PayMongo-confirmed loan payments before any ledger posting."
                    badges={
                        <>
                            <Badge variant="secondary">
                                {meta.total} payments
                            </Badge>
                            {filterCount > 0 ? (
                                <Badge variant="outline">
                                    {filterCount} filters
                                </Badge>
                            ) : null}
                        </>
                    }
                    rightSlot={<CreditCard className="h-5 w-5 text-muted-foreground" />}
                />

                <SurfaceCard variant="default" padding="md">
                    <div className="flex flex-col gap-4">
                        <SectionHeader
                            title="Filters"
                            description="Filter by status, date range, loan number, account number, or PayMongo reference."
                            actions={
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    disabled={filterCount === 0}
                                    onClick={clearFilters}
                                >
                                    Clear filters
                                </Button>
                            }
                        />
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                            <div className="space-y-1">
                                <span className="text-xs font-medium text-muted-foreground">
                                    Status
                                </span>
                                <Select
                                    value={localFilters.status ?? 'all'}
                                    onValueChange={(value) =>
                                        setLocalFilters((current) => ({
                                            ...current,
                                            status:
                                                value === 'all'
                                                    ? null
                                                    : (value as OnlinePaymentStatus),
                                        }))
                                    }
                                >
                                    <SelectTrigger aria-label="Status">
                                        <SelectValue placeholder="All statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All statuses
                                        </SelectItem>
                                        {statusOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1">
                                <label
                                    className="text-xs font-medium text-muted-foreground"
                                    htmlFor="online-payments-start"
                                >
                                    Start date
                                </label>
                                <Input
                                    id="online-payments-start"
                                    type="date"
                                    value={localFilters.start ?? ''}
                                    onChange={(event) =>
                                        setLocalFilters((current) => ({
                                            ...current,
                                            start:
                                                event.target.value || null,
                                        }))
                                    }
                                />
                            </div>
                            <div className="space-y-1">
                                <label
                                    className="text-xs font-medium text-muted-foreground"
                                    htmlFor="online-payments-end"
                                >
                                    End date
                                </label>
                                <Input
                                    id="online-payments-end"
                                    type="date"
                                    value={localFilters.end ?? ''}
                                    onChange={(event) =>
                                        setLocalFilters((current) => ({
                                            ...current,
                                            end: event.target.value || null,
                                        }))
                                    }
                                />
                            </div>
                            <div className="space-y-1">
                                <label
                                    className="text-xs font-medium text-muted-foreground"
                                    htmlFor="online-payments-loan"
                                >
                                    Loan number
                                </label>
                                <Input
                                    id="online-payments-loan"
                                    value={localFilters.loan_number ?? ''}
                                    onChange={(event) =>
                                        setLocalFilters((current) => ({
                                            ...current,
                                            loan_number:
                                                event.target.value || null,
                                        }))
                                    }
                                />
                            </div>
                            <div className="space-y-1">
                                <label
                                    className="text-xs font-medium text-muted-foreground"
                                    htmlFor="online-payments-acctno"
                                >
                                    Account number
                                </label>
                                <Input
                                    id="online-payments-acctno"
                                    value={localFilters.acctno ?? ''}
                                    onChange={(event) =>
                                        setLocalFilters((current) => ({
                                            ...current,
                                            acctno: event.target.value || null,
                                        }))
                                    }
                                />
                            </div>
                            <div className="space-y-1">
                                <label
                                    className="text-xs font-medium text-muted-foreground"
                                    htmlFor="online-payments-reference"
                                >
                                    Reference number
                                </label>
                                <Input
                                    id="online-payments-reference"
                                    value={
                                        localFilters.reference_number ?? ''
                                    }
                                    onChange={(event) =>
                                        setLocalFilters((current) => ({
                                            ...current,
                                            reference_number:
                                                event.target.value || null,
                                        }))
                                    }
                                />
                            </div>
                        </div>
                        <div className="flex justify-end">
                            <Button type="button" onClick={() => visitIndex(1)}>
                                Apply filters
                            </Button>
                        </div>
                    </div>
                </SurfaceCard>

                <SurfaceCard
                    variant="default"
                    padding="none"
                    className="overflow-hidden"
                >
                    <div className="border-b border-border/40 bg-card/70 px-6 py-4">
                        <SectionHeader
                            title="Payments"
                            description="PayMongo payments are stored here before ledger posting."
                            titleClassName="text-lg"
                        />
                    </div>
                    <div className="overflow-x-auto px-2 pb-2 sm:px-4 sm:pb-4">
                        <DataTable
                            columns={columns}
                            data={items}
                            emptyMessage="No online payments found."
                            className="min-w-[1080px] border-0 bg-transparent"
                        />
                    </div>
                </SurfaceCard>

                <DataTablePagination
                    page={meta.page}
                    perPage={meta.perPage}
                    total={meta.total}
                    onPageChange={(nextPage) => visitIndex(nextPage)}
                />
            </PageShell>
        </AppLayout>
    );
}
