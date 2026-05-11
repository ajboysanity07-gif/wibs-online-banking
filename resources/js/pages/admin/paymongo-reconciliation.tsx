import { Head, router, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { CheckCircle2, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { PageHero } from '@/components/page-hero';
import { PageShell } from '@/components/page-shell';
import { SectionHeader } from '@/components/section-header';
import { SurfaceCard } from '@/components/surface-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { DataTablePagination } from '@/components/ui/data-table-pagination';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency, formatDate } from '@/lib/formatters';
import { showErrorToast, showSuccessToast } from '@/lib/toast';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    index as paymongoReconciliationIndex,
    update as paymongoReconciliationUpdate,
} from '@/routes/admin/paymongo-reconciliation';
import type {
    PaymongoPaymentStatusFilter,
    PaymongoReconciliationPayment,
    PaymongoReconciliationResponse,
    PaymongoReconciliationStatus,
    PaymongoReconciliationStatusFilter,
} from '@/types/admin';
import type { BreadcrumbItem } from '@/types';

type Props = {
    payments: PaymongoReconciliationResponse;
};

type ReconciliationForm = {
    desktop_reference_no: string;
    official_receipt_no: string;
    reconciliation_notes: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin Dashboard',
        href: adminDashboard().url,
    },
    {
        title: 'PayMongo Reconciliation',
        href: paymongoReconciliationIndex().url,
    },
];

const paymentStatusOptions: Array<{
    value: PaymongoPaymentStatusFilter;
    label: string;
}> = [
    { value: 'paid', label: 'Paid' },
    { value: 'pending', label: 'Pending' },
    { value: 'failed', label: 'Failed' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'expired', label: 'Expired' },
    { value: 'all', label: 'All payment statuses' },
];

const reconciliationStatusOptions: Array<{
    value: PaymongoReconciliationStatusFilter;
    label: string;
}> = [
    { value: 'all', label: 'All reconciliation statuses' },
    { value: 'unreconciled', label: 'Unreconciled' },
    { value: 'reconciled', label: 'Reconciled' },
];

const formatCountLabel = (count: number, label: string): string => {
    return count === 1 ? `${count} ${label}` : `${count} ${label}s`;
};

const formatMethod = (payment: PaymongoReconciliationPayment): string => {
    return payment.payment_method_label ?? payment.payment_method;
};

const textareaClassName =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 flex min-h-[112px] w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50';

const ReconciliationStatusBadge = ({
    status,
}: {
    status: PaymongoReconciliationStatus;
}) => {
    if (status === 'reconciled') {
        return (
            <Badge className="border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/50 dark:text-emerald-300">
                Reconciled
            </Badge>
        );
    }

    return (
        <Badge className="border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/50 dark:text-amber-300">
            Unreconciled
        </Badge>
    );
};

export default function PaymongoReconciliationPage({ payments }: Props) {
    const [search, setSearch] = useState(payments.filters.search ?? '');
    const [status, setStatus] = useState<PaymongoPaymentStatusFilter>(
        payments.filters.status,
    );
    const [reconciliationStatus, setReconciliationStatus] =
        useState<PaymongoReconciliationStatusFilter>(
            payments.filters.reconciliation_status,
        );
    const [selectedPayment, setSelectedPayment] =
        useState<PaymongoReconciliationPayment | null>(null);
    const form = useForm<ReconciliationForm>({
        desktop_reference_no: '',
        official_receipt_no: '',
        reconciliation_notes: '',
    });

    const visitIndex = (
        overrides: Partial<{
            page: number;
            search: string;
            status: PaymongoPaymentStatusFilter;
            reconciliation_status: PaymongoReconciliationStatusFilter;
        }> = {},
    ) => {
        router.get(
            paymongoReconciliationIndex().url,
            {
                search: overrides.search ?? search,
                status: overrides.status ?? status,
                reconciliation_status:
                    overrides.reconciliation_status ?? reconciliationStatus,
                page: overrides.page ?? 1,
                perPage: payments.meta.perPage,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    const openReconciliationDialog = (
        payment: PaymongoReconciliationPayment,
    ) => {
        if (payment.status !== 'paid') {
            return;
        }

        form.clearErrors();
        form.setData({
            desktop_reference_no: payment.desktop_reference_no ?? '',
            official_receipt_no: payment.official_receipt_no ?? '',
            reconciliation_notes: payment.reconciliation_notes ?? '',
        });
        setSelectedPayment(payment);
    };

    const closeReconciliationDialog = () => {
        if (form.processing) {
            return;
        }

        setSelectedPayment(null);
        form.reset();
        form.clearErrors();
    };

    const submitReconciliation = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!selectedPayment || selectedPayment.status !== 'paid') {
            return;
        }

        form.patch(paymongoReconciliationUpdate(selectedPayment.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                showSuccessToast('PayMongo payment marked as reconciled.');
                closeReconciliationDialog();
            },
            onError: (errors) => {
                showErrorToast(
                    { errors },
                    'Failed to mark PayMongo payment as reconciled.',
                );
            },
        });
    };

    const columns: ColumnDef<PaymongoReconciliationPayment>[] = [
        {
            accessorKey: 'paid_at',
            header: 'Paid At',
            cell: ({ row }) => formatDate(row.original.paid_at),
        },
        {
            accessorKey: 'acctno',
            header: 'Account No',
            cell: ({ row }) => row.original.acctno,
        },
        {
            accessorKey: 'loan_number',
            header: 'Loan No',
            cell: ({ row }) => row.original.loan_number,
        },
        {
            accessorKey: 'base_amount',
            header: 'Loan Payment Amount',
            cell: ({ row }) => formatCurrency(row.original.base_amount),
        },
        {
            accessorKey: 'service_fee',
            header: 'Service Fee',
            cell: ({ row }) => formatCurrency(row.original.service_fee),
        },
        {
            accessorKey: 'gross_amount',
            header: 'Total Paid',
            cell: ({ row }) => formatCurrency(row.original.gross_amount),
        },
        {
            accessorKey: 'payment_method',
            header: 'Payment Method',
            cell: ({ row }) => formatMethod(row.original),
        },
        {
            accessorKey: 'provider_reference_number',
            header: 'PayMongo Reference',
            cell: ({ row }) => row.original.provider_reference_number ?? '--',
        },
        {
            accessorKey: 'reconciliation_status',
            header: 'Reconciliation Status',
            cell: ({ row }) => (
                <ReconciliationStatusBadge
                    status={row.original.reconciliation_status}
                />
            ),
        },
        {
            accessorKey: 'desktop_reference_no',
            header: 'Desktop Reference',
            cell: ({ row }) => row.original.desktop_reference_no ?? '--',
        },
        {
            accessorKey: 'official_receipt_no',
            header: 'Official Receipt',
            cell: ({ row }) => row.original.official_receipt_no ?? '--',
        },
        {
            id: 'actions',
            header: () => <div className="text-right">Actions</div>,
            cell: ({ row }) => {
                const payment = row.original;

                return (
                    <div className="flex justify-end">
                        <Button
                            type="button"
                            size="sm"
                            variant={
                                payment.reconciliation_status === 'unreconciled'
                                    ? 'default'
                                    : 'outline'
                            }
                            disabled={payment.status !== 'paid'}
                            onClick={() => openReconciliationDialog(payment)}
                        >
                            <CheckCircle2 />
                            Mark as Reconciled
                        </Button>
                    </div>
                );
            },
        },
    ];

    const unreconciledCount = payments.items.filter(
        (payment) =>
            payment.status === 'paid' &&
            payment.reconciliation_status === 'unreconciled',
    ).length;
    const hasFilters =
        status !== 'paid' ||
        reconciliationStatus !== 'all' ||
        search.trim() !== '';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="PayMongo Reconciliation" />

            <PageShell size="wide">
                <PageHero
                    kicker="Admin"
                    title="PayMongo Reconciliation"
                    description="Track paid PayMongo loan payments after they have been manually posted in Desktop WIBS."
                    badges={
                        <>
                            <Badge variant="secondary">
                                {formatCountLabel(
                                    payments.meta.total,
                                    'payment',
                                )}
                            </Badge>
                            {unreconciledCount > 0 ? (
                                <Badge className="border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/50 dark:text-amber-300">
                                    {formatCountLabel(
                                        unreconciledCount,
                                        'unreconciled paid payment',
                                    )}
                                </Badge>
                            ) : null}
                        </>
                    }
                />

                <SurfaceCard variant="default" padding="md">
                    <form
                        className="flex flex-col gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            visitIndex();
                        }}
                    >
                        <SectionHeader
                            title="Filters"
                            description="Search by account, loan, PayMongo reference, Desktop reference, or official receipt."
                            actions={
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    disabled={!hasFilters}
                                    onClick={() => {
                                        setSearch('');
                                        setStatus('paid');
                                        setReconciliationStatus('all');
                                        visitIndex({
                                            search: '',
                                            status: 'paid',
                                            reconciliation_status: 'all',
                                        });
                                    }}
                                >
                                    Clear filters
                                </Button>
                            }
                        />

                        <div className="grid gap-3 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
                            <div className="space-y-1">
                                <Label htmlFor="paymongo-reconciliation-search">
                                    Search
                                </Label>
                                <Input
                                    id="paymongo-reconciliation-search"
                                    value={search}
                                    placeholder="Account, loan, PayMongo ref, Desktop ref, OR"
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-1">
                                <Label>Payment status</Label>
                                <Select
                                    value={status}
                                    onValueChange={(value) =>
                                        setStatus(
                                            value as PaymongoPaymentStatusFilter,
                                        )
                                    }
                                >
                                    <SelectTrigger aria-label="Payment status">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {paymentStatusOptions.map((option) => (
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
                                <Label>Reconciliation status</Label>
                                <Select
                                    value={reconciliationStatus}
                                    onValueChange={(value) =>
                                        setReconciliationStatus(
                                            value as PaymongoReconciliationStatusFilter,
                                        )
                                    }
                                >
                                    <SelectTrigger aria-label="Reconciliation status">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {reconciliationStatusOptions.map(
                                            (option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button type="submit">
                                <Search />
                                Apply
                            </Button>
                        </div>
                    </form>
                </SurfaceCard>

                <SurfaceCard
                    variant="default"
                    padding="none"
                    className="overflow-hidden"
                >
                    <div className="border-b border-border/40 bg-card/70 px-6 py-4">
                        <SectionHeader
                            title="Payments"
                            description={`Showing page ${payments.meta.page} of ${payments.meta.lastPage}.`}
                            titleClassName="text-lg"
                        />
                    </div>

                    <div className="overflow-x-auto px-2 pb-2 sm:px-4 sm:pb-4">
                        <DataTable
                            columns={columns}
                            data={payments.items}
                            emptyMessage="No PayMongo payments match the current filters."
                            className="min-w-[1320px] border-0 bg-transparent"
                        />
                    </div>
                </SurfaceCard>

                <DataTablePagination
                    page={payments.meta.page}
                    perPage={payments.meta.perPage}
                    total={payments.meta.total}
                    onPageChange={(page) => visitIndex({ page })}
                />
            </PageShell>

            <Dialog
                open={selectedPayment !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        closeReconciliationDialog();
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Mark as Reconciled</DialogTitle>
                        <DialogDescription>
                            Record the Desktop WIBS posting details for this
                            paid PayMongo payment.
                        </DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitReconciliation}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="desktop_reference_no">
                                    Desktop Reference No
                                </Label>
                                <Input
                                    id="desktop_reference_no"
                                    maxLength={100}
                                    value={form.data.desktop_reference_no}
                                    disabled={form.processing}
                                    onChange={(event) =>
                                        form.setData(
                                            'desktop_reference_no',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.desktop_reference_no}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="official_receipt_no">
                                    Official Receipt No
                                </Label>
                                <Input
                                    id="official_receipt_no"
                                    maxLength={100}
                                    value={form.data.official_receipt_no}
                                    disabled={form.processing}
                                    onChange={(event) =>
                                        form.setData(
                                            'official_receipt_no',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.official_receipt_no}
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="reconciliation_notes">Notes</Label>
                            <textarea
                                id="reconciliation_notes"
                                className={textareaClassName}
                                maxLength={1000}
                                value={form.data.reconciliation_notes}
                                disabled={form.processing}
                                onChange={(event) =>
                                    form.setData(
                                        'reconciliation_notes',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.reconciliation_notes}
                            />
                            <InputError
                                message={
                                    (
                                        form.errors as Record<
                                            string,
                                            string | undefined
                                        >
                                    ).payment
                                }
                            />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={closeReconciliationDialog}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                <CheckCircle2 />
                                Mark as Reconciled
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
